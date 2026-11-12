<?php

namespace App\Http\Controllers\Cliente;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Str;

class AccesoController extends Controller
{
    /**
     * Mostrar la información de una mesa mediante su QR permanente.
     */
    public function mostrarMesa(string $codigo)
    {
        if (!Str::isUuid($codigo)) {
            abort(404, 'El código QR no es válido.');
        }

        $url = rtrim(config('services.supabase.url'), '/');
        $key = config('services.supabase.key');

        if (!$url || !$key) {
            abort(503, 'El servicio del restaurante no está disponible.');
        }

        try {
            $respuesta = Http::acceptJson()
                ->connectTimeout(5)
                ->timeout(15)
                ->withHeaders([
                    'apikey' => $key,
                ])
                ->post($url . '/rest/v1/rpc/consultar_mesa_qr', [
                    'p_codigo_qr' => $codigo,
                ]);

        } catch (ConnectionException $e) {
            report($e);
            abort(503, 'No se pudo conectar con el restaurante.');
        }

        if (!$respuesta->successful()) {
            logger()->error('Error consultando mesa QR', [
                'status' => $respuesta->status(),
                'response' => $respuesta->json(),
            ]);

            abort(503, 'No se pudo consultar la mesa.');
        }

        $resultado = $respuesta->json();

        if (empty($resultado) || !isset($resultado[0])) {
            abort(404, 'La mesa no existe o está inactiva.');
        }

        $mesa = $resultado[0];

        $solicitud = null;
        $sesionActiva = false;
        $puedeSolicitar = false;
        $errorSolicitudConsulta = null;

        /*
         * Consultar solicitudes únicamente para clientes
         * que hayan iniciado sesión.
         */
        if (
            session('usuario.role') === 'customer' &&
            session('supabase_access_token') &&
            session('usuario.id')
        ) {
            $token = session('supabase_access_token');

            try {
                $solicitudRespuesta = Http::acceptJson()
                    ->connectTimeout(5)
                    ->timeout(15)
                    ->withToken($token)
                    ->withHeaders([
                        'apikey' => $key,
                    ])
                    ->get($url . '/rest/v1/solicitudes_mesa', [
                        'mesa_id' => 'eq.' . $mesa['mesa_id'],
                        'cliente_id' => 'eq.' . session('usuario.id'),
                        'select' => 'id,estado,creado_en,sesion_id',
                        'order' => 'creado_en.desc',
                        'limit' => 1,
                    ]);

                if ($solicitudRespuesta->successful()) {
                    $solicitudes = $solicitudRespuesta->json();

                    $solicitud = $solicitudes[0] ?? null;

                    /*
                     * Si la solicitud fue aceptada,
                     * comprobar que su sesión siga activa.
                     */
                    if (
                        $solicitud &&
                        $solicitud['estado'] === 'aceptada' &&
                        !empty($solicitud['sesion_id'])
                    ) {
                        $sesionRespuesta = Http::acceptJson()
                            ->connectTimeout(5)
                            ->timeout(15)
                            ->withToken($token)
                            ->withHeaders([
                                'apikey' => $key,
                            ])
                            ->get($url . '/rest/v1/sesiones', [
                                'id' => 'eq.' . $solicitud['sesion_id'],
                                'cliente_id' => 'eq.' . session('usuario.id'),
                                'select' => 'id,activa',
                                'limit' => 1,
                            ]);

                        if ($sesionRespuesta->successful()) {
                            $sesiones = $sesionRespuesta->json();

                            $sesionActiva = (bool) (
                                $sesiones[0]['activa'] ?? false
                            );
                        }
                    }

                    /*
                     * Solo permitir una nueva solicitud cuando:
                     * - No existe una solicitud anterior.
                     * - La anterior fue rechazada.
                     * - La anterior fue cancelada.
                     * - La anterior fue aceptada, pero su sesión terminó.
                     *
                     * Además, la mesa debe estar libre.
                     */
                    $estadoSolicitud = $solicitud['estado'] ?? null;

                    $solicitudTerminada =
                        $solicitud === null ||
                        in_array(
                            $estadoSolicitud,
                            ['rechazada', 'cancelada'],
                            true
                        ) ||
                        (
                            $estadoSolicitud === 'aceptada' &&
                            !$sesionActiva
                        );

                    $puedeSolicitar =
                        ($mesa['estado'] ?? null) === 'libre' &&
                        $solicitudTerminada;

                } else {
                    $errorSolicitudConsulta =
                        'No se pudo consultar tu solicitud. Actualiza la página o inicia sesión nuevamente.';
                }

            } catch (ConnectionException $e) {
                report($e);

                $errorSolicitudConsulta =
                    'No se pudo conectar para consultar tu solicitud.';
            }
        }

        return view('cliente.mesa.qr', compact(
            'mesa',
            'codigo',
            'solicitud',
            'sesionActiva',
            'puedeSolicitar',
            'errorSolicitudConsulta'
        ));
    }

    /**
     * Enviar una solicitud para ocupar la mesa.
     */
    public function solicitarAcceso(string $codigo)
    {
        if (!Str::isUuid($codigo)) {
            abort(404, 'El código QR no es válido.');
        }

        $url = rtrim(config('services.supabase.url'), '/');
        $key = config('services.supabase.key');
        $token = session('supabase_access_token');

        if (!$url || !$key) {
            abort(503, 'El servicio del restaurante no está disponible.');
        }

        if (
            !$token ||
            session('usuario.role') !== 'customer' ||
            !session('usuario.id')
        ) {
            return redirect()
                ->route('login', ['mesa' => $codigo])
                ->withErrors([
                    'email' => 'Debes iniciar sesión como cliente para solicitar una mesa.',
                ]);
        }

        try {
            $respuesta = Http::acceptJson()
                ->connectTimeout(5)
                ->timeout(15)
                ->withToken($token)
                ->withHeaders([
                    'apikey' => $key,
                ])
                ->post($url . '/rest/v1/rpc/solicitar_acceso_mesa', [
                    'p_codigo_qr' => $codigo,
                ]);

        } catch (ConnectionException $e) {
            report($e);

            return redirect()
                ->route('mesa.qr', ['codigo' => $codigo])
                ->withErrors([
                    'mesa' => 'No se pudo conectar con el restaurante. Inténtalo nuevamente.',
                ]);
        }

        if (!$respuesta->successful()) {
            $mensaje = $respuesta->json('message')
                ?? $respuesta->json('msg')
                ?? 'No se pudo enviar la solicitud.';

            return redirect()
                ->route('mesa.qr', ['codigo' => $codigo])
                ->withErrors([
                    'mesa' => $mensaje,
                ]);
        }

        return redirect()
            ->route('mesa.qr', ['codigo' => $codigo])
            ->with('status', 'Tu solicitud fue enviada. Espera a que un trabajador la acepte.');
    }
}
