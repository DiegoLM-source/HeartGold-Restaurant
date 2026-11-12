<?php

namespace App\Http\Controllers\Cliente;

use App\Http\Controllers\Controller;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class SesionController extends Controller
{
    public function show()
    {
        $sesion = null;
        $mesa = null;
        $pedidos = [];
        $error = null;
        $usuarioId = session('usuario.id');
        $token = session('supabase_access_token');
        $url = rtrim((string) config('services.supabase.url'), '/');
        $key = config('services.supabase.key');

        if (!$usuarioId || !$token || !$url || !$key) {
            $error = 'No se pudo validar la sesión con el restaurante. Cierra sesión e inicia nuevamente.';
        } else {
            try {
                $respuesta = Http::acceptJson()->withToken($token)->withHeaders(['apikey' => $key])
                    ->connectTimeout(5)->timeout(15)->get($url . '/rest/v1/sesiones', [
                        'cliente_id' => 'eq.' . $usuarioId,
                        'activa' => 'eq.true',
                        'select' => 'id,mesa_id,iniciada_en,activa',
                        'order' => 'iniciada_en.desc',
                        'limit' => '1',
                    ]);

                if (!$respuesta->successful()) {
                    logger()->error('Error consultando sesión activa del cliente', ['status' => $respuesta->status()]);
                    $error = 'No se pudo consultar tu sesión actual.';
                } else {
                    $sesion = ($respuesta->json() ?? [])[0] ?? null;
                    if ($sesion) {
                        $mesaRespuesta = Http::acceptJson()->withToken($token)->withHeaders(['apikey' => $key])
                            ->connectTimeout(5)->timeout(15)->get($url . '/rest/v1/mesas', [
                                'id' => 'eq.' . $sesion['mesa_id'],
                                'select' => 'id,nombre,capacidad,zona,estado',
                                'limit' => '1',
                            ]);
                        if ($mesaRespuesta->successful()) {
                            $mesa = ($mesaRespuesta->json() ?? [])[0] ?? null;
                        } else {
                            $error = 'No se pudieron consultar los datos de tu mesa.';
                        }

                        $pedidosRespuesta = Http::acceptJson()->withToken($token)->withHeaders(['apikey' => $key])
                            ->connectTimeout(5)->timeout(15)->get($url . '/rest/v1/pedidos', [
                                'cliente_id' => 'eq.' . $usuarioId,
                                'sesion_id' => 'eq.' . $sesion['id'],
                                'select' => 'id,estado,total,observaciones,creado_en',
                                'order' => 'creado_en.desc',
                                'limit' => '100',
                            ]);
                        if ($pedidosRespuesta->successful()) {
                            $pedidos = $pedidosRespuesta->json() ?? [];
                            $pedidoIds = array_values(array_filter(array_column($pedidos, 'id')));
                            if ($pedidoIds) {
                                $detallesRespuesta = Http::acceptJson()->withToken($token)->withHeaders(['apikey' => $key])
                                    ->connectTimeout(5)->timeout(15)->get($url . '/rest/v1/detalle_pedido', [
                                        'pedido_id' => 'in.(' . implode(',', $pedidoIds) . ')',
                                        'select' => 'id,pedido_id,nombre_producto,cantidad,precio_unitario,subtotal',
                                    ]);
                                if ($detallesRespuesta->successful()) {
                                    $porPedido = [];
                                    foreach ($detallesRespuesta->json() ?? [] as $detalle) $porPedido[$detalle['pedido_id']][] = $detalle;
                                    foreach ($pedidos as &$pedido) $pedido['productos'] = $porPedido[$pedido['id']] ?? [];
                                    unset($pedido);
                                } else {
                                    $error = 'Se cargaron los pedidos, pero no se pudieron consultar sus productos.';
                                }
                            }
                        } else {
                            logger()->warning('No se pudieron consultar pedidos de la sesión del cliente', ['status' => $pedidosRespuesta->status()]);
                            $error = 'Se consultó tu sesión, pero no se pudieron cargar los pedidos.';
                        }
                    }
                }
            } catch (ConnectionException $e) {
                report($e);
                $error = 'No fue posible conectar con el restaurante. Inténtalo nuevamente.';
            }
        }

        return view('cliente.sesion.show', compact('sesion', 'mesa', 'pedidos', 'error'));
    }
}
