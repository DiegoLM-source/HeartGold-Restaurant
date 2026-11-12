<?php

namespace App\Http\Controllers\Cliente;

use App\Http\Controllers\Controller;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class PedidoController extends Controller
{
    public function index()
    {
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
                $respuesta = Http::acceptJson()->withToken($token)
                    ->withHeaders(['apikey' => $key])->connectTimeout(5)->timeout(15)
                    ->get($url . '/rest/v1/pedidos', [
                        'cliente_id' => 'eq.' . $usuarioId,
                        'select' => 'id,sesion_id,mesa_id,estado,subtotal,total,observaciones,creado_en',
                        'order' => 'creado_en.desc',
                        'limit' => '100',
                    ]);

                if (!$respuesta->successful()) {
                    logger()->error('Error consultando pedidos propios del cliente', ['status' => $respuesta->status()]);
                    $error = 'No se pudieron consultar tus pedidos.';
                } else {
                    $pedidos = $respuesta->json() ?? [];
                    $pedidoIds = array_values(array_filter(array_column($pedidos, 'id')));
                    $mesaIds = array_values(array_unique(array_filter(array_column($pedidos, 'mesa_id'))));

                    if ($pedidoIds) {
                        $detallesRespuesta = Http::acceptJson()->withToken($token)
                            ->withHeaders(['apikey' => $key])->connectTimeout(5)->timeout(15)
                            ->get($url . '/rest/v1/detalle_pedido', [
                                'pedido_id' => 'in.(' . implode(',', $pedidoIds) . ')',
                                'select' => 'id,pedido_id,nombre_producto,cantidad,precio_unitario,subtotal',
                                'order' => 'nombre_producto.asc',
                            ]);

                        if (!$detallesRespuesta->successful()) {
                            logger()->error('Error consultando detalles de pedidos del cliente', ['status' => $detallesRespuesta->status()]);
                            $error = 'Se consultaron tus pedidos, pero no fue posible cargar sus productos.';
                        } else {
                            $porPedido = [];
                            foreach ($detallesRespuesta->json() ?? [] as $detalle) {
                                $porPedido[$detalle['pedido_id']][] = $detalle;
                            }
                            foreach ($pedidos as &$pedido) {
                                $pedido['productos'] = $porPedido[$pedido['id']] ?? [];
                            }
                            unset($pedido);
                        }
                    }

                    if ($mesaIds) {
                        $mesasRespuesta = Http::acceptJson()->withToken($token)
                            ->withHeaders(['apikey' => $key])->connectTimeout(5)->timeout(15)
                            ->get($url . '/rest/v1/mesas', [
                                'id' => 'in.(' . implode(',', $mesaIds) . ')',
                                'select' => 'id,nombre',
                            ]);

                        if ($mesasRespuesta->successful()) {
                            $mesas = [];
                            foreach ($mesasRespuesta->json() ?? [] as $mesa) {
                                $mesas[$mesa['id']] = $mesa['nombre'];
                            }
                            foreach ($pedidos as &$pedido) {
                                $pedido['nombre_mesa'] = $mesas[$pedido['mesa_id'] ?? ''] ?? null;
                            }
                            unset($pedido);
                        } else {
                            logger()->warning('No se pudieron consultar las mesas de pedidos del cliente', ['status' => $mesasRespuesta->status()]);
                            $error = 'Se consultaron tus pedidos, pero algunos datos de mesa no están disponibles.';
                        }
                    }
                }
            } catch (ConnectionException $e) {
                report($e);
                $error = 'No fue posible conectar con el restaurante. Inténtalo nuevamente.';
            }
        }

        return view('cliente.pedidos.index', compact('pedidos', 'error'));
    }
}
