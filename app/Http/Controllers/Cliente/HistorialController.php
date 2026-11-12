<?php

namespace App\Http\Controllers\Cliente;

use App\Http\Controllers\Controller;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class HistorialController extends Controller
{
    public function index()
    {
        $sesiones = [];
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
                    ->get($url . '/rest/v1/sesiones', [
                        'cliente_id' => 'eq.' . $usuarioId,
                        'select' => 'id,mesa_id,iniciada_en,finalizada_en,activa',
                        'order' => 'iniciada_en.desc',
                        'limit' => '100',
                    ]);

                if (!$respuesta->successful()) {
                    logger()->error('Error consultando historial de sesiones del cliente', ['status' => $respuesta->status()]);
                    $error = 'No se pudo consultar tu historial.';
                } else {
                    $sesiones = $respuesta->json() ?? [];
                    $mesaIds = array_values(array_unique(array_filter(array_column($sesiones, 'mesa_id'))));

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
                            foreach ($sesiones as &$sesion) {
                                $sesion['mesa'] = $mesas[$sesion['mesa_id'] ?? ''] ?? null;
                            }
                            unset($sesion);
                        } else {
                            logger()->warning('No se pudieron consultar las mesas del historial del cliente', ['status' => $mesasRespuesta->status()]);
                            $error = 'Se consultó tu historial, pero algunos datos de mesa no están disponibles.';
                        }
                    }

                    $pedidosRespuesta = Http::acceptJson()->withToken($token)
                        ->withHeaders(['apikey' => $key])->connectTimeout(5)->timeout(15)
                        ->get($url . '/rest/v1/pedidos', [
                            'cliente_id' => 'eq.' . $usuarioId,
                            'select' => 'id,sesion_id,mesa_id,estado,total,observaciones,creado_en',
                            'order' => 'creado_en.desc',
                            'limit' => '100',
                        ]);
                    if (!$pedidosRespuesta->successful()) {
                        logger()->warning('No se pudieron consultar pedidos del historial del cliente', ['status' => $pedidosRespuesta->status()]);
                        $error = 'Se consultaron las visitas, pero no se pudieron cargar los pedidos.';
                    } else {
                        $pedidos = $pedidosRespuesta->json() ?? [];
                        $pedidoIds = array_values(array_filter(array_column($pedidos, 'id')));
                        if ($pedidoIds) {
                            $mesaIds = array_values(array_unique(array_filter(array_column($pedidos, 'mesa_id'))));
                            $mesasPorId = [];
                            if ($mesaIds) {
                                $mesasPedidosRespuesta = Http::acceptJson()->withToken($token)
                                    ->withHeaders(['apikey' => $key])->connectTimeout(5)->timeout(15)
                                    ->get($url . '/rest/v1/mesas', [
                                        'id' => 'in.(' . implode(',', $mesaIds) . ')',
                                        'select' => 'id,nombre,zona',
                                    ]);
                                if ($mesasPedidosRespuesta->successful()) {
                                    foreach ($mesasPedidosRespuesta->json() ?? [] as $mesa) $mesasPorId[$mesa['id']] = $mesa;
                                }
                            }
                            $detallesRespuesta = Http::acceptJson()->withToken($token)
                                ->withHeaders(['apikey' => $key])->connectTimeout(5)->timeout(15)
                                ->get($url . '/rest/v1/detalle_pedido', [
                                    'pedido_id' => 'in.(' . implode(',', $pedidoIds) . ')',
                                    'select' => 'id,pedido_id,nombre_producto,cantidad,precio_unitario,subtotal',
                                ]);
                            $detallesPorPedido = [];
                            if ($detallesRespuesta->successful()) {
                                foreach ($detallesRespuesta->json() ?? [] as $detalle) $detallesPorPedido[$detalle['pedido_id']][] = $detalle;
                            }
                            $ventasRespuesta = Http::acceptJson()->withToken($token)
                                ->withHeaders(['apikey' => $key])->connectTimeout(5)->timeout(15)
                                ->get($url . '/rest/v1/ventas', [
                                    'pedido_id' => 'in.(' . implode(',', $pedidoIds) . ')',
                                    'select' => 'id,pedido_id,total,estado',
                                ]);
                            $ventasPorPedido = [];
                            $ventaIds = [];
                            if ($ventasRespuesta->successful()) {
                                foreach ($ventasRespuesta->json() ?? [] as $venta) {
                                    $ventasPorPedido[$venta['pedido_id']] = $venta;
                                    $ventaIds[] = $venta['id'];
                                }
                            }
                            $pagosPorVenta = [];
                            $metodos = [];
                            if ($ventaIds) {
                                $pagosRespuesta = Http::acceptJson()->withToken($token)
                                    ->withHeaders(['apikey' => $key])->connectTimeout(5)->timeout(15)
                                    ->get($url . '/rest/v1/pagos', [
                                        'venta_id' => 'in.(' . implode(',', $ventaIds) . ')',
                                        'select' => 'id,venta_id,metodo_pago_id,monto,referencia,estado,creado_en',
                                        'order' => 'creado_en.desc',
                                    ]);
                                if ($pagosRespuesta->successful()) {
                                    $pagos = $pagosRespuesta->json() ?? [];
                                    foreach ($pagos as $pago) $pagosPorVenta[$pago['venta_id']][] = $pago;
                                    $metodoIds = array_values(array_unique(array_filter(array_column($pagos, 'metodo_pago_id'))));
                                    if ($metodoIds) {
                                        $metodosRespuesta = Http::acceptJson()->withToken($token)
                                            ->withHeaders(['apikey' => $key])->connectTimeout(5)->timeout(15)
                                            ->get($url . '/rest/v1/metodos_pago', [
                                                'id' => 'in.(' . implode(',', $metodoIds) . ')',
                                                'select' => 'id,nombre',
                                            ]);
                                        if ($metodosRespuesta->successful()) foreach ($metodosRespuesta->json() ?? [] as $metodo) $metodos[$metodo['id']] = $metodo['nombre'];
                                    }
                                }
                            }
                            foreach ($pedidos as &$pedido) {
                                $pedido['mesa'] = $mesasPorId[$pedido['mesa_id'] ?? ''] ?? null;
                                $pedido['productos'] = $detallesPorPedido[$pedido['id']] ?? [];
                                $pedido['venta'] = $ventasPorPedido[$pedido['id']] ?? null;
                                if ($pedido['venta']) {
                                    $pedido['venta']['pagos'] = $pagosPorVenta[$pedido['venta']['id']] ?? [];
                                    foreach ($pedido['venta']['pagos'] as &$pago) $pago['nombre_metodo'] = $metodos[$pago['metodo_pago_id']] ?? 'Método de pago';
                                    unset($pago);
                                }
                            }
                            unset($pedido);
                        }
                    }
                }
            } catch (ConnectionException $e) {
                report($e);
                $error = 'No fue posible conectar con el restaurante. Inténtalo nuevamente.';
            }
        }

        return view('cliente.historial.index', compact('sesiones', 'pedidos', 'error'));
    }
}
