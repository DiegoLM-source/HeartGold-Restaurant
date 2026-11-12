<?php

namespace App\Http\Controllers\Cliente;

use App\Http\Controllers\Controller;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class DashboardController extends Controller
{
    public function index()
    {
        $vista = [
            'sesion' => null,
            'mesa' => null,
            'error' => null,
        ];

        $usuarioId = session('usuario.id');
        $token = session('supabase_access_token');
        $url = rtrim((string) config('services.supabase.url'), '/');
        $key = config('services.supabase.key');

        if (!$usuarioId || !$token || !$url || !$key) {
            $vista['error'] = 'No se pudo validar la sesión con el restaurante. Cierra sesión e inicia nuevamente.';

            return view('cliente.dashboard', $vista);
        }

        try {
            $respuesta = Http::acceptJson()
                ->withToken($token)
                ->withHeaders(['apikey' => $key])
                ->connectTimeout(5)
                ->timeout(15)
                ->get($url . '/rest/v1/sesiones', [
                    'cliente_id' => 'eq.' . $usuarioId,
                    'activa' => 'eq.true',
                    'select' => 'id,mesa_id,iniciada_en,activa',
                    'order' => 'iniciada_en.desc',
                    'limit' => '1',
                ]);

            if (!$respuesta->successful()) {
                logger()->error('Error consultando sesión del cliente', [
                    'status' => $respuesta->status(),
                ]);
                $vista['error'] = 'No se pudo consultar tu sesión actual.';

                return view('cliente.dashboard', $vista);
            }

            $vista['sesion'] = ($respuesta->json() ?? [])[0] ?? null;

            if ($vista['sesion'] && !empty($vista['sesion']['mesa_id'])) {
                $respuestaMesa = Http::acceptJson()
                    ->withToken($token)
                    ->withHeaders(['apikey' => $key])
                    ->connectTimeout(5)
                    ->timeout(15)
                    ->get($url . '/rest/v1/mesas', [
                        'id' => 'eq.' . $vista['sesion']['mesa_id'],
                        'select' => 'id,nombre,capacidad,zona,estado',
                        'limit' => '1',
                    ]);

                if ($respuestaMesa->successful()) {
                    $vista['mesa'] = ($respuestaMesa->json() ?? [])[0] ?? null;
                    if (!$vista['mesa']) {
                        $vista['error'] = 'La sesión está activa, pero la mesa asociada ya no está disponible.';
                    }
                } else {
                    logger()->warning('Error consultando mesa de la sesión del cliente', [
                        'status' => $respuestaMesa->status(),
                    ]);
                    $vista['error'] = 'Tu sesión está activa, pero no se pudo consultar la mesa asociada.';
                }
            }
        } catch (ConnectionException $e) {
            report($e);
            $vista['error'] = 'No fue posible conectar con el restaurante. Inténtalo nuevamente.';
        }

        return view('cliente.dashboard', $vista);
    }
}
