<?php

namespace App\Http\Controllers\Cliente;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ReservaController extends Controller
{
    public function create()
    {
        $mesas = [];
        $error = null;
        if (!session('supabase_access_token') || !config('services.supabase.url') || !config('services.supabase.key')) {
            $error = 'No se pudo validar la sesión con el restaurante. Inicia sesión nuevamente.';

            return view('cliente.reservas.create', compact('mesas', 'error'));
        }
        try {
            $respuesta = Http::acceptJson()->withToken(session('supabase_access_token'))
                ->withHeaders(['apikey' => config('services.supabase.key')])->connectTimeout(5)->timeout(15)
                ->get(rtrim((string) config('services.supabase.url'), '/') . '/rest/v1/mesas', [
                    'select' => 'id,nombre,capacidad,zona,activa',
                    'activa' => 'eq.true',
                    'order' => 'capacidad.asc,nombre.asc',
                    'limit' => '200',
                ]);
            if ($respuesta->successful()) {
                $mesas = $respuesta->json() ?? [];
            } else {
                logger()->warning('No se pudieron consultar mesas para reservar', ['status' => $respuesta->status()]);
                $error = 'No se pudo consultar la lista de mesas. Puedes solicitar asignación automática.';
            }
        } catch (ConnectionException $e) {
            report($e);
            $error = 'No fue posible conectar con el restaurante. Puedes intentarlo más tarde.';
        }

        return view('cliente.reservas.create', compact('mesas', 'error'));
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'fecha_hora' => ['required', 'date'],
            'personas' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'mesa_id' => ['nullable', 'uuid'],
            'duracion_minutos' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ]);
        if (!CarbonImmutable::parse($datos['fecha_hora'], 'America/Bogota')->isAfter(CarbonImmutable::now('America/Bogota'))) {
            return back()->withInput()->withErrors(['fecha_hora' => 'La fecha de reserva debe ser futura.']);
        }
        $url = rtrim((string) config('services.supabase.url'), '/');
        $key = config('services.supabase.key');
        $token = session('supabase_access_token');
        if (!$url || !$key || !$token) {
            return back()->withInput()->withErrors(['reserva' => 'No se pudo validar la sesión con el restaurante.']);
        }

        try {
            $fecha = CarbonImmutable::parse($datos['fecha_hora'], 'America/Bogota')->toIso8601String();
            $respuesta = Http::acceptJson()->withToken($token)->withHeaders(['apikey' => $key])
                ->connectTimeout(5)->timeout(20)
                ->post($url . '/rest/v1/rpc/crear_reserva', [
                    'p_fecha_hora' => $fecha,
                    'p_personas' => (int) $datos['personas'],
                    'p_mesa_id' => $datos['mesa_id'] ?? null,
                    'p_duracion_minutos' => (int) $datos['duracion_minutos'],
                    'p_observaciones' => trim((string) ($datos['observaciones'] ?? '')) ?: null,
                ]);

            if (!$respuesta->successful()) {
                logger()->warning('RPC crear_reserva rechazó la solicitud', ['status' => $respuesta->status()]);
                $mensaje = $respuesta->json('message') ?? $respuesta->json('msg');
                return back()->withInput()->withErrors([
                    'reserva' => is_string($mensaje) && strlen($mensaje) <= 240
                        ? $mensaje
                        : 'No se pudo crear la reserva. Verifica fecha y disponibilidad.',
                ]);
            }

            return redirect()->route('cliente.reservas.index')->with('success', 'La solicitud de reserva fue registrada.');
        } catch (ConnectionException $e) {
            report($e);
            return back()->withInput()->withErrors(['reserva' => 'No fue posible conectar con el restaurante. Inténtalo nuevamente.']);
        }
    }

    public function cancelar(string $reserva)
    {
        abort_unless(Str::isUuid($reserva), 404);

        $usuarioId = session('usuario.id');
        $token = session('supabase_access_token');
        $url = rtrim((string) config('services.supabase.url'), '/');
        $key = config('services.supabase.key');
        if (!$usuarioId || !$token || !$url || !$key) {
            return back()->withErrors(['reserva' => 'No se pudo validar tu sesión. Inicia sesión nuevamente.']);
        }

        try {
            $consulta = Http::acceptJson()->withToken($token)->withHeaders(['apikey' => $key])
                ->connectTimeout(5)->timeout(15)->get($url . '/rest/v1/reservas', [
                    'id' => 'eq.' . $reserva,
                    'cliente_id' => 'eq.' . $usuarioId,
                    'select' => 'id,estado',
                    'limit' => '1',
                ]);
            if (!$consulta->successful()) {
                logger()->warning('Error validando propiedad de reserva del cliente', ['status' => $consulta->status()]);
                return back()->withErrors(['reserva' => 'No se pudo validar la reserva. Inténtalo nuevamente.']);
            }

            $registro = ($consulta->json() ?? [])[0] ?? null;
            abort_unless($registro, 404);
            if (!in_array($registro['estado'] ?? '', ['pendiente', 'confirmada'], true)) {
                return back()->withErrors(['reserva' => 'Esta reserva ya no admite una solicitud de cancelación.']);
            }

            $respuesta = Http::acceptJson()->withToken($token)->withHeaders([
                'apikey' => $key,
                'Prefer' => 'return=representation',
            ])->connectTimeout(5)->timeout(15)->patch(
                $url . '/rest/v1/reservas?id=eq.' . urlencode($reserva)
                    . '&cliente_id=eq.' . urlencode($usuarioId)
                    . '&estado=in.(pendiente,confirmada)',
                ['estado' => 'cancelada']
            );

            if (!$respuesta->successful() || empty($respuesta->json()[0]['id'])) {
                logger()->warning('RLS o las reglas de Supabase rechazaron la cancelación del cliente', [
                    'status' => $respuesta->status(),
                    'reserva_id' => $reserva,
                ]);
                return back()->withErrors(['reserva' => 'La reserva no pudo cancelarse. Puede que las reglas de permisos o su estado no permitan esta operación.']);
            }

            return back()->with('success', 'La reserva fue cancelada.');
        } catch (ConnectionException $e) {
            report($e);
            return back()->withErrors(['reserva' => 'No fue posible conectar con el restaurante. Inténtalo nuevamente.']);
        }
    }

    public function index()
    {
        $reservas = [];
        $error = null;
        $usuarioId = session('usuario.id');
        $token = session('supabase_access_token');
        $url = rtrim((string) config('services.supabase.url'), '/');
        $key = config('services.supabase.key');

        if (!$usuarioId || !$token || !$url || !$key) {
            $error = 'No se pudo validar la sesión con el restaurante. Cierra sesión e inicia nuevamente.';
        } else {
            try {
                $respuesta = Http::acceptJson()->withToken($token)
                    ->withHeaders(['apikey' => $key])->connectTimeout(5)->timeout(15)
                    ->get($url . '/rest/v1/reservas', [
                        'cliente_id' => 'eq.' . $usuarioId,
                        'select' => 'id,cliente_id,mesa_id,fecha_hora_inicio,fecha_hora_fin,personas,estado,observaciones,creado_en',
                        'order' => 'fecha_hora_inicio.desc',
                        'limit' => '100',
                    ]);

                if (!$respuesta->successful()) {
                    logger()->error('Error consultando reservas propias del cliente', ['status' => $respuesta->status()]);
                    $error = 'No se pudieron consultar tus reservas.';
                } else {
                    $reservas = $respuesta->json() ?? [];
                    $mesaIds = array_values(array_unique(array_filter(array_column($reservas, 'mesa_id'))));
                    if ($mesaIds) {
                        $mesasRespuesta = Http::acceptJson()->withToken($token)
                            ->withHeaders(['apikey' => $key])->connectTimeout(5)->timeout(15)
                            ->get($url . '/rest/v1/mesas', [
                                'id' => 'in.(' . implode(',', $mesaIds) . ')',
                                'select' => 'id,nombre,zona',
                            ]);

                        if ($mesasRespuesta->successful()) {
                            $mesas = [];
                            foreach ($mesasRespuesta->json() ?? [] as $mesa) {
                                $mesas[$mesa['id']] = $mesa;
                            }
                            foreach ($reservas as &$reserva) {
                                $reserva['mesa'] = $mesas[$reserva['mesa_id'] ?? ''] ?? null;
                            }
                            unset($reserva);
                        }
                    }
                }
            } catch (ConnectionException $e) {
                report($e);
                $error = 'No fue posible conectar con el restaurante. Inténtalo nuevamente.';
            }
        }

        return view('cliente.reservas.index', compact('reservas', 'error'));
    }
}
