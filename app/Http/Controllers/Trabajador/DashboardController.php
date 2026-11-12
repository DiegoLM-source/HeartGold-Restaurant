<?php

namespace App\Http\Controllers\Trabajador;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Carbon\CarbonImmutable;

class DashboardController extends Controller
{
    public function index(): View
    {
        $estadisticas = [
            'mesas_libres' => null,
            'mesas_ocupadas' => null,
            'pedidos_pendientes' => null,
            'reservas_pendientes' => null,
        ];
        $error = null;
        $token = session('supabase_access_token');
        $url = rtrim((string) config('services.supabase.url'), '/');
        $key = config('services.supabase.key');

        if (!$token || !$url || !$key) {
            $error = 'No se pudo validar la sesión con Supabase. Cierra sesión e inicia nuevamente.';
        } else {
            try {
                $consultas = [
                    'mesas_libres' => ['mesas', ['select' => 'id', 'activa' => 'eq.true', 'estado' => 'eq.libre']],
                    'mesas_ocupadas' => ['mesas', ['select' => 'id', 'activa' => 'eq.true', 'estado' => 'eq.ocupada']],
                    'pedidos_pendientes' => ['pedidos', ['select' => 'id', 'estado' => 'eq.pendiente']],
                    'reservas_pendientes' => ['reservas', ['select' => 'id', 'estado' => 'eq.pendiente']],
                ];
                foreach ($consultas as $indicador => [$tabla, $filtros]) {
                    $respuesta = Http::acceptJson()->withToken($token)
                        ->withHeaders([
                            'apikey' => $key,
                            'Prefer' => 'count=exact',
                            'Range' => '0-0',
                        ])->connectTimeout(5)->timeout(15)
                        ->get($url . '/rest/v1/' . $tabla, $filtros);
                    if (!$respuesta->successful()) {
                        logger()->error('Error consultando indicadores del trabajador', [
                            'indicador' => $indicador,
                            'tabla' => $tabla,
                            'status' => $respuesta->status(),
                        ]);
                        $error = 'No se pudieron consultar todos los indicadores del restaurante.';
                        break;
                    }
                    $rango = $respuesta->header('Content-Range');
                    if (!$rango || !preg_match('~/([0-9]+|\*)$~', $rango, $coincidencia) || $coincidencia[1] === '*') {
                        $error = 'Supabase no devolvió el conteo de registros para el panel.';
                        break;
                    }
                    $estadisticas[$indicador] = (int) $coincidencia[1];
                }
            } catch (ConnectionException $e) {
                report($e);
                $error = 'No fue posible conectar con Supabase. Inténtalo nuevamente.';
            }
        }

        return view('trabajador.dashboard', compact('estadisticas', 'error'));
    }

    public function seccion(Request $request, string $seccion): View
    {
        $secciones = [
            'mesas' => [
                'titulo' => 'Mesas',
                'descripcion' => 'Consulta la distribución y disponibilidad de las mesas del restaurante.'
            ],
            'pedidos' => [
                'titulo' => 'Pedidos',
                'descripcion' => 'Revisa, confirma y actualiza los pedidos de las sesiones activas.'
            ],
            'reservas' => [
                'titulo' => 'Reservas',
                'descripcion' => 'Consulta y gestiona las reservas de los clientes.'
            ],
            'inventario' => [
                'titulo' => 'Inventario',
                'descripcion' => 'Consulta existencias, registra lotes y realiza ajustes autorizados.'
            ],
            'ventas' => [
                'titulo' => 'Ventas y pagos',
                'descripcion' => 'Registra ventas y pagos asociados a los pedidos entregados.'
            ],
            'historial' => [
                'titulo' => 'Historial',
                'descripcion' => 'Consulta la actividad y los movimientos anteriores del restaurante.'
            ],
        ];

        abort_unless(array_key_exists($seccion, $secciones), 404);

        if ($seccion === 'mesas') {
            return $this->mostrarMesas();
        }

        if ($seccion === 'pedidos') {
            return $this->mostrarPedidos();
        }

        if ($seccion === 'reservas') {
            return $this->mostrarReservas();
        }

        if ($seccion === 'inventario') {
            return $this->mostrarInventario();
        }

        $periodo = $this->normalizarPeriodo($request->query('periodo'));

        if ($seccion === 'ventas') {
            return $this->mostrarVentas($periodo);
        }

        if ($seccion === 'historial') {
            return $this->mostrarHistorial($periodo);
        }

        return view('trabajador.seccion', [
            'seccionActual' => $seccion,
            'seccion' => $secciones[$seccion],
        ]);
    }

    private function normalizarPeriodo(?string $periodo): string
    {
        return in_array($periodo, ['dia', 'semana', 'mes', 'tres_meses'], true)
            ? $periodo
            : 'mes';
    }

    private function limitesPeriodo(string $periodo): array
    {
        $ahora = CarbonImmutable::now('America/Bogota');

        return match ($periodo) {
            'dia' => [$ahora->startOfDay(), $ahora->startOfDay()->addDay()],
            'semana' => [$ahora->startOfWeek(), $ahora->startOfWeek()->addWeek()],
            'tres_meses' => [$ahora->startOfMonth()->subMonths(2), $ahora->startOfMonth()->addMonth()],
            default => [$ahora->startOfMonth(), $ahora->startOfMonth()->addMonth()],
        };
    }

    private function consultarSupabase(string $tabla, array $filtros)
    {
        $request = Http::acceptJson()
            ->withToken(session('supabase_access_token'))
            ->withHeaders(['apikey' => config('services.supabase.key')])
            ->connectTimeout(5)
            ->timeout(15);
        $endpoint = rtrim((string) config('services.supabase.url'), '/') . '/rest/v1/' . $tabla;
        $respuesta = $request->get($endpoint, $filtros);

        if ($respuesta->status() === 401 && $this->renovarTokenSupabase()) {
            return Http::acceptJson()
                ->withToken(session('supabase_access_token'))
                ->withHeaders(['apikey' => config('services.supabase.key')])
                ->connectTimeout(5)
                ->timeout(15)
                ->get($endpoint, $filtros);
        }

        return $respuesta;
    }

    private function ejecutarRpc(string $funcion, array $datos)
    {
        $url = rtrim((string) config('services.supabase.url'), '/');
        $key = config('services.supabase.key');
        $enviar = fn () => Http::acceptJson()
            ->withToken(session('supabase_access_token'))
            ->withHeaders(['apikey' => $key])
            ->connectTimeout(5)
            ->timeout(15)
            ->post($url . '/rest/v1/rpc/' . $funcion, $datos);

        $respuesta = $enviar();
        if ($respuesta->status() === 401 && $this->renovarTokenSupabase()) {
            $respuesta = $enviar();
        }

        return $respuesta;
    }

    private function mostrarVentas(string $periodo): View
    {
        [$inicio, $fin] = $this->limitesPeriodo($periodo);
        $pedidos = [];
        $pedidosSinVenta = [];
        $ventas = [];
        $metodosPago = [];
        $sesionesActivas = [];
        $error = null;

        if (!session('supabase_access_token') || !config('services.supabase.url') || !config('services.supabase.key')) {
            $error = 'No se pudo validar la sesión con Supabase. Cierra sesión e inicia nuevamente.';
        } else {
            try {
                $respuesta = $this->consultarSupabase('pedidos', [
                    'select' => 'id,cliente_id,mesa_id,estado,subtotal,total,creado_en',
                    'estado' => 'eq.entregado',
                    'creado_en' => 'gte.' . $inicio->toIso8601String(),
                    'and' => '(creado_en.lt.' . $fin->toIso8601String() . ')',
                    'order' => 'creado_en.desc',
                    'limit' => '500',
                ]);

                if (!$respuesta->successful()) {
                    logger()->error('Error consultando pedidos entregados para ventas', ['status' => $respuesta->status()]);
                    $error = 'No se pudieron consultar los pedidos entregados para este periodo.';
                } else {
                    $pedidos = $respuesta->json() ?? [];
                    $pedidoIds = array_values(array_filter(array_column($pedidos, 'id')));
                    $ventaPorPedido = [];
                    $ventaIds = [];
                    $pagosPorVenta = [];
                    $metodos = [];

                    if ($pedidoIds) {
                        $respuestaVentas = $this->consultarSupabase('ventas', [
                            'select' => 'id,pedido_id,trabajador_id,subtotal,total,estado',
                            'pedido_id' => 'in.(' . implode(',', $pedidoIds) . ')',
                            'order' => 'id.desc',
                            'limit' => '500',
                        ]);
                        if (!$respuestaVentas->successful()) {
                            logger()->error('Error consultando ventas del periodo', ['status' => $respuestaVentas->status()]);
                            $error = 'No se pudieron consultar las ventas registradas.';
                        } else {
                            $ventas = $respuestaVentas->json() ?? [];
                            foreach ($ventas as $venta) {
                                $ventaPorPedido[$venta['pedido_id']] = $venta;
                                $ventaIds[] = $venta['id'];
                            }
                            $pedidosSinVenta = array_values(array_filter(
                                $pedidos,
                                fn ($pedido) => !isset($ventaPorPedido[$pedido['id']])
                            ));
                        }

                        if ($ventaIds) {
                            $respuestaPagos = $this->consultarSupabase('pagos', [
                                'select' => 'id,venta_id,metodo_pago_id,monto,referencia,estado,registrado_por,creado_en',
                                'venta_id' => 'in.(' . implode(',', $ventaIds) . ')',
                                'order' => 'creado_en.desc',
                                'limit' => '1000',
                            ]);
                            if ($respuestaPagos->successful()) {
                                foreach ($respuestaPagos->json() ?? [] as $pago) {
                                    $pagosPorVenta[$pago['venta_id']][] = $pago;
                                }
                                $metodoIds = array_values(array_unique(array_filter(array_column($respuestaPagos->json() ?? [], 'metodo_pago_id'))));
                                if ($metodoIds) {
                                    $respuestaMetodos = $this->consultarSupabase('metodos_pago', [
                                        'select' => 'id,nombre,activo',
                                        'id' => 'in.(' . implode(',', $metodoIds) . ')',
                                    ]);
                                    if ($respuestaMetodos->successful()) {
                                        foreach ($respuestaMetodos->json() ?? [] as $metodo) $metodos[$metodo['id']] = $metodo['nombre'];
                                    }
                                }
                            } else {
                                logger()->error('Error consultando pagos del periodo', ['status' => $respuestaPagos->status()]);
                                $error = 'Se cargaron las ventas, pero no fue posible consultar sus pagos.';
                            }
                        }
                    }

                    $mesaIds = array_values(array_unique(array_filter(array_column($pedidos, 'mesa_id'))));
                    $clienteIds = array_values(array_unique(array_filter(array_column($pedidos, 'cliente_id'))));
                    $mesas = [];
                    $clientes = [];

                    if ($mesaIds) {
                        $mesasRespuesta = $this->consultarSupabase('mesas', [
                            'select' => 'id,nombre,zona',
                            'id' => 'in.(' . implode(',', $mesaIds) . ')',
                        ]);
                        if ($mesasRespuesta->successful()) {
                            foreach ($mesasRespuesta->json() ?? [] as $mesa) $mesas[$mesa['id']] = $mesa;
                        }
                    }
                    if ($clienteIds) {
                        $clientesRespuesta = $this->consultarSupabase('profiles', [
                            'select' => 'id,full_name',
                            'id' => 'in.(' . implode(',', $clienteIds) . ')',
                        ]);
                        if ($clientesRespuesta->successful()) {
                            foreach ($clientesRespuesta->json() ?? [] as $cliente) $clientes[$cliente['id']] = $cliente;
                        }
                    }
                    foreach ($pedidos as &$pedido) {
                        $pedido['mesa'] = $mesas[$pedido['mesa_id'] ?? ''] ?? null;
                        $pedido['cliente'] = $clientes[$pedido['cliente_id'] ?? ''] ?? null;
                        $pedido['venta'] = $ventaPorPedido[$pedido['id']] ?? null;
                        if ($pedido['venta']) {
                            $ventaId = $pedido['venta']['id'];
                            $pedido['venta']['pagos'] = $pagosPorVenta[$ventaId] ?? [];
                            $pedido['venta']['total_pagado'] = array_sum(array_map(
                                fn ($pago) => ($pago['estado'] ?? null) === 'pagado' ? (int) $pago['monto'] : 0,
                                $pedido['venta']['pagos']
                            ));
                            $pedido['venta']['saldo'] = max(0, (int) $pedido['venta']['total'] - $pedido['venta']['total_pagado']);
                            foreach ($pedido['venta']['pagos'] as &$pago) {
                                $pago['nombre_metodo'] = $metodos[$pago['metodo_pago_id']] ?? 'Método no disponible';
                            }
                            unset($pago);
                        }
                    }
                    unset($pedido);
                }

                $respuestaMetodosActivos = $this->consultarSupabase('metodos_pago', [
                    'select' => 'id,nombre,descripcion,activo',
                    'activo' => 'eq.true',
                    'order' => 'nombre.asc',
                ]);
                if ($respuestaMetodosActivos->successful()) {
                    $metodosPago = $respuestaMetodosActivos->json() ?? [];
                } else {
                    logger()->warning('No se pudieron consultar métodos de pago activos', ['status' => $respuestaMetodosActivos->status()]);
                    $error = 'No se pudieron consultar los métodos de pago disponibles.';
                }

                $respuestaSesiones = $this->consultarSupabase('sesiones', [
                    'select' => 'id,mesa_id,cliente_id,iniciada_en,activa',
                    'activa' => 'eq.true',
                    'order' => 'iniciada_en.asc',
                    'limit' => '100',
                ]);
                if ($respuestaSesiones->successful()) {
                    $sesionesActivas = $respuestaSesiones->json() ?? [];
                    $mesaIds = array_values(array_unique(array_filter(array_column($sesionesActivas, 'mesa_id'))));
                    if ($mesaIds) {
                        $respuestaMesasActivas = $this->consultarSupabase('mesas', [
                            'select' => 'id,nombre,zona',
                            'id' => 'in.(' . implode(',', $mesaIds) . ')',
                        ]);
                        $mesasActivas = [];
                        if ($respuestaMesasActivas->successful()) {
                            foreach ($respuestaMesasActivas->json() ?? [] as $mesa) $mesasActivas[$mesa['id']] = $mesa;
                        }
                        foreach ($sesionesActivas as &$sesion) $sesion['mesa'] = $mesasActivas[$sesion['mesa_id']] ?? null;
                        unset($sesion);
                    }
                } else {
                    logger()->warning('No se pudieron consultar sesiones abiertas para el cierre', ['status' => $respuestaSesiones->status()]);
                }
            } catch (ConnectionException $e) {
                report($e);
                $error = 'No fue posible conectar con Supabase. Inténtalo nuevamente.';
            }
        }

        $totalVentas = array_sum(array_map(fn ($venta) => ($venta['estado'] ?? null) !== 'anulado' ? (int) $venta['total'] : 0, $ventas));
        $totalPagado = array_sum(array_map(
            fn ($pedido) => $pedido['venta']['total_pagado'] ?? 0,
            $pedidos
        ));

        return view('trabajador.ventas', compact('pedidos', 'pedidosSinVenta', 'ventas', 'sesionesActivas', 'metodosPago', 'error', 'periodo', 'totalVentas', 'totalPagado', 'inicio', 'fin'));
    }

    public function registrarVenta(Request $request)
    {
        $datos = $request->validate(['pedido_id' => ['required', 'uuid']]);
        $respuesta = $this->ejecutarRpc('registrar_venta', [
            'p_pedido_id' => $datos['pedido_id'],
        ]);

        if (!$respuesta->successful()) {
            logger()->warning('RPC registrar_venta rechazó la operación', ['status' => $respuesta->status()]);
            return back()->with('error', $this->mensajeErrorRpc($respuesta, 'No se pudo registrar la venta. Comprueba que el pedido esté entregado y no tenga una venta previa.'));
        }

        return back()->with('success', 'La venta se registró correctamente.');
    }

    public function registrarPago(Request $request, string $venta)
    {
        abort_unless(Str::isUuid($venta), 404);
        $datos = $request->validate([
            'metodo_pago_id' => ['required', 'uuid'],
            'monto' => ['required', 'integer', 'min:1'],
            'referencia' => ['nullable', 'string', 'max:255'],
        ]);
        $respuesta = $this->ejecutarRpc('registrar_pago', [
            'p_venta_id' => $venta,
            'p_metodo_pago_id' => $datos['metodo_pago_id'],
            'p_monto' => $datos['monto'],
            'p_referencia' => $datos['referencia'] ?? null,
        ]);

        if (!$respuesta->successful()) {
            logger()->warning('RPC registrar_pago rechazó la operación', ['status' => $respuesta->status(), 'venta_id' => $venta]);
            return back()->with('error', $this->mensajeErrorRpc($respuesta, 'No se pudo registrar el pago. Verifica el saldo pendiente y el método elegido.'));
        }

        return back()->with('success', 'El pago se registró correctamente.');
    }

    public function cerrarSesion(string $sesion)
    {
        abort_unless(Str::isUuid($sesion), 404);
        $respuesta = $this->ejecutarRpc('cerrar_sesion', [
            'p_sesion_id' => $sesion,
        ]);

        if (!$respuesta->successful()) {
            logger()->warning('RPC cerrar_sesion rechazó la operación', ['status' => $respuesta->status(), 'sesion_id' => $sesion]);
            return back()->with('error', $this->mensajeErrorRpc($respuesta, 'No se pudo cerrar la sesión. Revisa que todos los pedidos, ventas y pagos estén finalizados.'));
        }

        return back()->with('success', 'La sesión se cerró correctamente.');
    }

    private function mensajeErrorRpc($respuesta, string $alternativo): string
    {
        $mensaje = $respuesta->json('message') ?? $respuesta->json('msg');
        if (!is_string($mensaje) || trim($mensaje) === '' || strlen($mensaje) > 240) {
            return $alternativo;
        }

        return $mensaje;
    }

    private function mostrarHistorial(string $periodo): View
    {
        [$inicio, $fin] = $this->limitesPeriodo($periodo);
        $eventos = [];
        $error = null;

        if (!session('supabase_access_token') || !config('services.supabase.url') || !config('services.supabase.key')) {
            $error = 'No se pudo validar la sesión con Supabase. Cierra sesión e inicia nuevamente.';
        } else {
            $consultas = [
                ['pedidos', 'creado_en', 'id,estado,total,creado_en'],
                ['reservas', 'creado_en', 'id,estado,personas,creado_en'],
                ['movimientos_inventario', 'creado_en', 'id,ingrediente_id,tipo,cantidad,motivo,creado_en'],
                ['sesiones', 'iniciada_en', 'id,mesa_id,cliente_id,iniciada_en,activa'],
            ];
            $pedidoFechaPorId = [];
            try {
                foreach ($consultas as [$tabla, $columnaFecha, $select]) {
                    $respuesta = $this->consultarSupabase($tabla, [
                        'select' => $select,
                        $columnaFecha => 'gte.' . $inicio->toIso8601String(),
                        'and' => '(' . $columnaFecha . '.lt.' . $fin->toIso8601String() . ')',
                        'order' => $columnaFecha . '.desc',
                        'limit' => '250',
                    ]);
                    if (!$respuesta->successful()) {
                        logger()->error('Error consultando historial del trabajador', ['tabla' => $tabla, 'status' => $respuesta->status()]);
                        $error = 'No se pudo cargar todo el historial para este periodo.';
                        break;
                    }

                    foreach ($respuesta->json() ?? [] as $registro) {
                        $fecha = $registro[$columnaFecha] ?? null;
                        if ($tabla === 'pedidos') {
                            $titulo = 'Pedido';
                            $detalle = 'Total registrado: $' . number_format((float) ($registro['total'] ?? 0), 0, ',', '.');
                            $estado = $registro['estado'] ?? '';
                            $pedidoFechaPorId[$registro['id']] = $fecha;
                        } elseif ($tabla === 'reservas') {
                            $titulo = 'Reserva';
                            $detalle = (int) ($registro['personas'] ?? 0) . ' personas';
                            $estado = $registro['estado'] ?? '';
                        } elseif ($tabla === 'movimientos_inventario') {
                            $titulo = 'Movimiento de inventario';
                            $detalle = trim(($registro['tipo'] ?? 'Movimiento') . ' · ' . ($registro['cantidad'] ?? '') . ' · ' . ($registro['motivo'] ?? ''));
                            $estado = '';
                        } else {
                            $titulo = 'Sesión de mesa';
                            $detalle = !empty($registro['activa']) ? 'Sesión activa' : 'Sesión finalizada';
                            $estado = '';
                        }
                        $eventos[] = [
                            'id' => $registro['id'] ?? '',
                            'tipo' => $titulo,
                            'detalle' => $detalle,
                            'estado' => $estado,
                            'fecha' => $fecha,
                        ];
                    }
                }

                if ($pedidoFechaPorId) {
                    $ventasRespuesta = $this->consultarSupabase('ventas', [
                        'select' => 'id,pedido_id,total,estado',
                        'pedido_id' => 'in.(' . implode(',', array_keys($pedidoFechaPorId)) . ')',
                        'limit' => '1000',
                    ]);
                    if ($ventasRespuesta->successful()) {
                        $ventaIds = [];
                        $ventasPorId = [];
                        foreach ($ventasRespuesta->json() ?? [] as $venta) {
                            $ventaIds[] = $venta['id'];
                            $ventasPorId[$venta['id']] = $venta;
                            $eventos[] = [
                                'id' => $venta['id'],
                                'tipo' => 'Venta asociada al pedido',
                                'detalle' => 'Pedido #' . substr($venta['pedido_id'], 0, 8) . ' · Total $' . number_format((float) $venta['total'], 0, ',', '.') . ' · fecha del pedido',
                                'estado' => $venta['estado'],
                                'fecha' => $pedidoFechaPorId[$venta['pedido_id']] ?? null,
                            ];
                        }

                        if ($ventaIds) {
                            $pagosRespuesta = $this->consultarSupabase('pagos', [
                                'select' => 'id,venta_id,metodo_pago_id,monto,referencia,estado,creado_en',
                                'venta_id' => 'in.(' . implode(',', $ventaIds) . ')',
                                'creado_en' => 'gte.' . $inicio->toIso8601String(),
                                'and' => '(creado_en.lt.' . $fin->toIso8601String() . ')',
                                'order' => 'creado_en.desc',
                                'limit' => '1000',
                            ]);
                            if ($pagosRespuesta->successful()) {
                                $pagos = $pagosRespuesta->json() ?? [];
                                $metodoIds = array_values(array_unique(array_filter(array_column($pagos, 'metodo_pago_id'))));
                                $nombresMetodo = [];
                                if ($metodoIds) {
                                    $metodosRespuesta = $this->consultarSupabase('metodos_pago', [
                                        'select' => 'id,nombre',
                                        'id' => 'in.(' . implode(',', $metodoIds) . ')',
                                    ]);
                                    if ($metodosRespuesta->successful()) {
                                        foreach ($metodosRespuesta->json() ?? [] as $metodo) $nombresMetodo[$metodo['id']] = $metodo['nombre'];
                                    }
                                }
                                foreach ($pagos as $pago) {
                                    $eventos[] = [
                                        'id' => $pago['id'],
                                        'tipo' => 'Pago registrado',
                                        'detalle' => ($nombresMetodo[$pago['metodo_pago_id']] ?? 'Método de pago') . ' · $' . number_format((int) $pago['monto'], 0, ',', '.') . (!empty($pago['referencia']) ? ' · ' . $pago['referencia'] : ''),
                                        'estado' => $pago['estado'],
                                        'fecha' => $pago['creado_en'],
                                    ];
                                }
                            } else {
                                $error = 'Se cargó parte del historial, pero no se pudieron consultar los pagos.';
                            }
                        }
                    } else {
                        $error = 'Se cargó parte del historial, pero no se pudieron consultar las ventas.';
                    }
                }
                usort($eventos, fn ($a, $b) => strcmp((string) $b['fecha'], (string) $a['fecha']));
            } catch (ConnectionException $e) {
                report($e);
                $error = 'No fue posible conectar con Supabase. Inténtalo nuevamente.';
            }
        }

        return view('trabajador.historial', compact('eventos', 'error', 'periodo', 'inicio', 'fin'));
    }

    
    private function mostrarMesas(): View
    {
        $token = session('supabase_access_token');
        $url = rtrim(config('services.supabase.url'), '/');
        $key = config('services.supabase.key');

        if (!$token || !$url || !$key) {
            return view('trabajador.mesas', [
                'mesas' => [],
                'solicitudes' => [],
                'error' => 'No se pudo obtener la sesión de Supabase. Cierra sesión e inicia nuevamente.',
            ]);
        }

        $headers = [
            'apikey' => $key,
        ];

        try {
            // Consultar las mesas activas.
            $respuesta = Http::acceptJson()
                ->withToken($token)
                ->withHeaders($headers)
                ->connectTimeout(5)
                ->timeout(15)
                ->get($url . '/rest/v1/mesas', [
                    'select' => 'id,nombre,capacidad,forma,zona,pos_x,pos_y,ancho,alto,rotacion,estado,activa',
                    'activa' => 'eq.true',
                    'order' => 'nombre.asc',
                ]);

            if (!$respuesta->successful()) {
                logger()->error('Error consultando mesas en Supabase', [
                    'status' => $respuesta->status(),
                ]);

                return view('trabajador.mesas', [
                    'mesas' => [],
                    'solicitudes' => [],
                    'error' => 'No se pudieron consultar las mesas. Verifica los permisos de acceso.',
                ]);
            }

            $mesas = $respuesta->json() ?? [];

            // Consultar las solicitudes pendientes.
            $solicitudesRespuesta = Http::acceptJson()
                ->withToken($token)
                ->withHeaders($headers)
                ->connectTimeout(5)
                ->timeout(15)
                ->get($url . '/rest/v1/solicitudes_mesa', [
                    'select' => 'id,mesa_id,cliente_id,estado,creado_en',
                    'estado' => 'eq.pendiente',
                    'order' => 'creado_en.asc',
                ]);

            if (!$solicitudesRespuesta->successful()) {
                logger()->error('Error consultando solicitudes de mesa', [
                    'status' => $solicitudesRespuesta->status(),
                ]);

                return view('trabajador.mesas', [
                    'mesas' => $mesas,
                    'solicitudes' => [],
                    'error' => null,
                    'errorSolicitudes' => 'No se pudieron cargar las solicitudes pendientes.',
                ]);
            }

            $solicitudes = $solicitudesRespuesta->json() ?? [];

            // Obtener los identificadores únicos.
            $clienteIds = array_unique(array_column($solicitudes, 'cliente_id'));
            $mesaIds = array_unique(array_column($solicitudes, 'mesa_id'));

            $clientes = [];
            $nombresMesas = [];

            // Consultar los nombres de los clientes.
            if (count($clienteIds) > 0) {
                $clientesRespuesta = Http::acceptJson()
                    ->withToken($token)
                    ->withHeaders($headers)
                    ->timeout(15)
                    ->get($url . '/rest/v1/profiles', [
                        'select' => 'id,full_name',
                        'id' => 'in.(' . implode(',', $clienteIds) . ')',
                    ]);

                if ($clientesRespuesta->successful()) {
                    foreach ($clientesRespuesta->json() ?? [] as $cliente) {
                        $clientes[$cliente['id']] = $cliente['full_name'] ?? 'Cliente';
                    }
                }
            }

            // Consultar los nombres de las mesas solicitadas.
            if (count($mesaIds) > 0) {
                $nombresMesasRespuesta = Http::acceptJson()
                    ->withToken($token)
                    ->withHeaders($headers)
                    ->timeout(15)
                    ->get($url . '/rest/v1/mesas', [
                        'select' => 'id,nombre',
                        'id' => 'in.(' . implode(',', $mesaIds) . ')',
                    ]);

                if ($nombresMesasRespuesta->successful()) {
                    foreach ($nombresMesasRespuesta->json() ?? [] as $mesa) {
                        $nombresMesas[$mesa['id']] = $mesa['nombre'];
                    }
                }
            }

            // Completar los datos para mostrarlos en Blade.
            foreach ($solicitudes as &$solicitud) {
                $solicitud['nombre_cliente'] =
                    $clientes[$solicitud['cliente_id']] ?? 'Cliente';

                $solicitud['nombre_mesa'] =
                    $nombresMesas[$solicitud['mesa_id']] ?? 'Mesa';
            }

            unset($solicitud);

            return view('trabajador.mesas', [
                'mesas' => $mesas,
                'solicitudes' => $solicitudes,
                'error' => null,
                'errorSolicitudes' => null,
            ]);

        } catch (ConnectionException $e) {
            report($e);

            return view('trabajador.mesas', [
                'mesas' => [],
                'solicitudes' => [],
                'error' => 'No fue posible conectar con Supabase. Inténtalo nuevamente.',
            ]);
        }
    }


    public function resolverSolicitudMesa(Request $request, string $solicitud)
    {
        $datos = $request->validate([
            'aceptar' => ['required', 'boolean'],
        ]);

        $token = session('supabase_access_token');
        $url = rtrim(config('services.supabase.url'), '/');
        $key = config('services.supabase.key');

        if (!$token || !$url || !$key) {
            return back()->withErrors([
                'solicitudes' => 'Tu sesión ha expirado. Inicia sesión nuevamente.',
            ]);
        }

        if (!\Illuminate\Support\Str::isUuid($solicitud)) {
            abort(404);
        }

        try {
            $respuesta = Http::acceptJson()
                ->withToken($token)
                ->withHeaders([
                    'apikey' => $key,
                ])
                ->connectTimeout(5)
                ->timeout(15)
                ->post($url . '/rest/v1/rpc/resolver_solicitud_mesa', [
                    'p_solicitud_id' => $solicitud,
                    'p_aceptar' => (bool) $datos['aceptar'],
                ]);

        } catch (ConnectionException $e) {
            report($e);

            return back()->withErrors([
                'solicitudes' => 'No se pudo conectar con el restaurante.',
            ]);
        }

        if (!$respuesta->successful()) {
            logger()->error('Error resolviendo solicitud de mesa', [
                'status' => $respuesta->status(),
                'respuesta' => $respuesta->json(),
            ]);

            return back()->withErrors([
                'solicitudes' => $respuesta->json('message')
                    ?? 'No se pudo resolver la solicitud. Puede que ya haya sido atendida.',
            ]);
        }

        $resultado = $respuesta->json();

        $estado = $resultado[0]['estado'] ?? null;

        if ($estado === 'aceptada') {
            return back()->with(
                'success',
                'Solicitud aceptada. La sesión de la mesa ha sido creada.'
            );
        }

        if ($estado === 'rechazada') {
            return back()->with(
                'success',
                'Solicitud rechazada. No se creó ninguna sesión.'
            );
        }

        return back()->with(
            'success',
            'La solicitud fue procesada.'
        );
    }


    private function mostrarPedidos(): View
    {
        $token = session('supabase_access_token');
        $url = rtrim(config('services.supabase.url'), '/');
        $key = config('services.supabase.key');

        $vista = [
            'pedidos' => [],
            'error' => null,
        ];

        if (!$token || !$url || !$key) {
            $vista['error'] = 'No se pudo obtener la sesión de Supabase. Cierra sesión e inicia nuevamente.';

            return view('trabajador.pedidos', $vista);
        }

        $consultar = function (string $tabla, array $filtros) use ($url, $key, &$token) {
            $respuesta = Http::acceptJson()
                ->withToken($token)
                ->withHeaders(['apikey' => $key])
                ->connectTimeout(5)
                ->timeout(15)
                ->get($url . '/rest/v1/' . $tabla, $filtros);

            if ($respuesta->status() === 401) {
                if ($this->renovarTokenSupabase()) {
                    $token = session('supabase_access_token');

                    $respuesta = Http::acceptJson()
                        ->withToken($token)
                        ->withHeaders(['apikey' => $key])
                        ->connectTimeout(5)
                        ->timeout(15)
                        ->get($url . '/rest/v1/' . $tabla, $filtros);
                }
            }

            return $respuesta;
        };

        try {
            $respuestaPedidos = $consultar('pedidos', [
                'select' => 'id,cliente_id,trabajador_id,mesa_id,sesion_id,estado,subtotal,total,observaciones,creado_en',
                'order' => 'creado_en.desc',
                'limit' => '100',
            ]);

            if (!$respuestaPedidos->successful()) {
                logger()->error('Error consultando pedidos en Supabase', [
                    'status' => $respuestaPedidos->status(),
                ]);

                $vista['error'] = 'No se pudieron consultar los pedidos. Verifica los permisos de acceso.';

                return view('trabajador.pedidos', $vista);
            }

            $pedidos = $respuestaPedidos->json() ?? [];

            if (empty($pedidos)) {
                return view('trabajador.pedidos', $vista);
            }

            $mesaIds = array_unique(array_filter(array_column($pedidos, 'mesa_id')));
            $clienteIds = array_unique(array_filter(array_column($pedidos, 'cliente_id')));
            $pedidoIds = array_unique(array_filter(array_column($pedidos, 'id')));

            $mesas = [];
            $clientes = [];
            $detalles = [];

            if (!empty($mesaIds)) {
                $respuestaMesas = $consultar('mesas', [
                    'select' => 'id,nombre,zona',
                    'id' => 'in.(' . implode(',', $mesaIds) . ')',
                ]);

                if (!$respuestaMesas->successful()) {
                    logger()->error('Error consultando mesas de pedidos', [
                        'status' => $respuestaMesas->status(),
                    ]);

                    $vista['error'] = 'No se pudieron consultar las mesas de los pedidos.';

                    return view('trabajador.pedidos', $vista);
                }

                $mesas = $respuestaMesas->json() ?? [];
            }

            if (!empty($clienteIds)) {
                $respuestaClientes = $consultar('profiles', [
                    'select' => 'id,full_name',
                    'id' => 'in.(' . implode(',', $clienteIds) . ')',
                ]);

                if (!$respuestaClientes->successful()) {
                    logger()->error('Error consultando clientes de pedidos', [
                        'status' => $respuestaClientes->status(),
                    ]);

                    $vista['error'] = 'No se pudieron consultar los clientes de los pedidos.';

                    return view('trabajador.pedidos', $vista);
                }

                $clientes = $respuestaClientes->json() ?? [];
            }

            if (!empty($pedidoIds)) {
                $respuestaDetalles = $consultar('detalle_pedido', [
                    'select' => 'id,pedido_id,nombre_producto,cantidad,precio_unitario,subtotal',
                    'pedido_id' => 'in.(' . implode(',', $pedidoIds) . ')',
                    'order' => 'nombre_producto.asc',
                ]);

                if (!$respuestaDetalles->successful()) {
                    logger()->error('Error consultando detalles de pedidos', [
                        'status' => $respuestaDetalles->status(),
                    ]);

                    $vista['error'] = 'No se pudieron consultar los productos de los pedidos.';

                    return view('trabajador.pedidos', $vista);
                }

                $detalles = $respuestaDetalles->json() ?? [];
            }

            $mesasPorId = [];

            foreach ($mesas as $mesa) {
                $mesasPorId[$mesa['id']] = $mesa;
            }

            $clientesPorId = [];

            foreach ($clientes as $cliente) {
                $clientesPorId[$cliente['id']] = $cliente;
            }

            $detallesPorPedido = [];

            foreach ($detalles as $detalle) {
                $detallesPorPedido[$detalle['pedido_id']][] = $detalle;
            }

            foreach ($pedidos as &$pedido) {
                $pedido['mesa'] = $mesasPorId[$pedido['mesa_id'] ?? ''] ?? null;
                $pedido['cliente'] = $clientesPorId[$pedido['cliente_id'] ?? ''] ?? null;
                $pedido['productos'] = $detallesPorPedido[$pedido['id']] ?? [];
            }

            unset($pedido);

            $vista['pedidos'] = $pedidos;

        } catch (ConnectionException $e) {
            report($e);

            $vista['error'] = 'No fue posible conectar con Supabase. Inténtalo nuevamente.';
        }

        return view('trabajador.pedidos', $vista);
    }


    public function actualizarEstadoPedido(Request $request, string $pedido)
    {
        $datos = $request->validate([
            'estado' => [
                'required',
                'in:confirmado,en_preparacion,listo,entregado,cancelado,rechazado',
            ],
        ]);

        $token = session('supabase_access_token');
        $url = rtrim(config('services.supabase.url'), '/');
        $key = config('services.supabase.key');

        if (!$token || !$url || !$key) {
            return back()->with(
                'error',
                'No se pudo validar la sesión con Supabase. Cierra sesión e inicia nuevamente.'
            );
        }

        try {
            $respuesta = Http::acceptJson()
                ->withToken($token)
                ->withHeaders(['apikey' => $key])
                ->connectTimeout(5)
                ->timeout(15)
                ->post($url . '/rest/v1/rpc/gestionar_pedido', [
                    'p_pedido_id' => $pedido,
                    'p_nuevo_estado' => $datos['estado'],
                ]);

            if ($respuesta->status() === 401) {
                if ($this->renovarTokenSupabase()) {
                    $token = session('supabase_access_token');

                    $respuesta = Http::acceptJson()
                        ->withToken($token)
                        ->withHeaders(['apikey' => $key])
                        ->connectTimeout(5)
                        ->timeout(15)
                        ->post($url . '/rest/v1/rpc/gestionar_pedido', [
                            'p_pedido_id' => $pedido,
                            'p_nuevo_estado' => $datos['estado'],
                        ]);
                }
            }

            if (!$respuesta->successful()) {
                logger()->error('Error al actualizar estado del pedido en Supabase', [
                    'pedido_id' => $pedido,
                    'estado_solicitado' => $datos['estado'],
                    'status' => $respuesta->status(),
                    'respuesta' => $respuesta->body(),
                ]);

                return back()->with(
                    'error',
                    'No fue posible actualizar el pedido. Puede que su estado actual no permita esta acción.'
                );
            }

            $estadoReal = $respuesta->json();

            if ($datos['estado'] === 'confirmado' && $estadoReal === 'rechazado') {
                return back()->with(
                    'error',
                    'El pedido fue rechazado automáticamente porque contiene ingredientes deshabilitados.'
                );
            }

            return back()->with(
                'success',
                'El estado del pedido se actualizó correctamente.'
            );

        } catch (ConnectionException $e) {
            report($e);

            return back()->with(
                'error',
                'No fue posible conectar con Supabase. Inténtalo nuevamente.'
            );
        }
    }

    public function actualizarEstadoReserva(Request $request, string $reserva)
    {
        $datos = $request->validate([
            'estado' => [
                'required',
                'in:confirmada,rechazada,cancelada,completada,no_asistio',
            ],
        ]);

        $token = session('supabase_access_token');
        $url = rtrim(config('services.supabase.url'), '/');
        $key = config('services.supabase.key');

        if (!$token || !$url || !$key) {
            return back()->with(
                'error',
                'No se pudo validar la sesión con Supabase. Cierra sesión e inicia nuevamente.'
            );
        }

        try {
            $respuesta = Http::acceptJson()
                ->withToken($token)
                ->withHeaders(['apikey' => $key])
                ->connectTimeout(5)
                ->timeout(15)
                ->post($url . '/rest/v1/rpc/gestionar_reserva', [
                    'p_reserva_id' => $reserva,
                    'p_nuevo_estado' => $datos['estado'],
                ]);

            if ($respuesta->status() === 401) {
                if ($this->renovarTokenSupabase()) {
                    $token = session('supabase_access_token');

                    $respuesta = Http::acceptJson()
                        ->withToken($token)
                        ->withHeaders(['apikey' => $key])
                        ->connectTimeout(5)
                        ->timeout(15)
                        ->post($url . '/rest/v1/rpc/gestionar_reserva', [
                            'p_reserva_id' => $reserva,
                            'p_nuevo_estado' => $datos['estado'],
                        ]);
                }
            }

            if (!$respuesta->successful()) {
                logger()->error('Error al actualizar reserva en Supabase', [
                    'reserva_id' => $reserva,
                    'estado_solicitado' => $datos['estado'],
                    'status' => $respuesta->status(),
                    'respuesta' => $respuesta->body(),
                ]);

                return back()->with(
                    'error',
                    'No fue posible actualizar la reserva. Puede que su estado actual no permita esta acción.'
                );
            }

            return back()->with(
                'success',
                'La reserva se actualizó correctamente.'
            );

        } catch (ConnectionException $e) {
            report($e);

            return back()->with(
                'error',
                'No fue posible conectar con Supabase. Inténtalo nuevamente.'
            );
        }
    }

    private function mostrarReservas(): View
    {
        $token = session('supabase_access_token');
        $url = rtrim(config('services.supabase.url'), '/');
        $key = config('services.supabase.key');

        $vista = [
            'reservas' => [],
            'error' => null,
        ];

        if (!$token || !$url || !$key) {
            $vista['error'] = 'No se pudo obtener la sesión de Supabase. Cierra sesión e inicia nuevamente.';

            return view('trabajador.reservas', $vista);
        }

        $consultar = function (string $tabla, array $filtros) use ($url, $key, $token) {
            return Http::acceptJson()
                ->withToken($token)
                ->withHeaders(['apikey' => $key])
                ->connectTimeout(5)
                ->timeout(15)
                ->get($url . '/rest/v1/' . $tabla, $filtros);
        };

        try {
            $respuestaReservas = $consultar('reservas', [
                'select' => 'id,cliente_id,mesa_id,fecha_hora_inicio,fecha_hora_fin,personas,estado,observaciones,creado_en',
                'order' => 'fecha_hora_inicio.desc',
                'limit' => '100',
            ]);

            if (!$respuestaReservas->successful()) {
                logger()->error('Error consultando reservas en Supabase', [
                    'status' => $respuestaReservas->status(),
                ]);

                $vista['error'] = 'No se pudieron consultar las reservas. Verifica los permisos de acceso.';

                return view('trabajador.reservas', $vista);
            }

            $reservas = $respuestaReservas->json() ?? [];

            if (empty($reservas)) {
                return view('trabajador.reservas', $vista);
            }

            $clienteIds = array_unique(array_filter(
                array_column($reservas, 'cliente_id')
            ));

            $mesaIds = array_unique(array_filter(
                array_column($reservas, 'mesa_id')
            ));

            $clientes = [];
            $mesas = [];

            if (!empty($clienteIds)) {
                $respuestaClientes = $consultar('profiles', [
                    'select' => 'id,full_name',
                    'id' => 'in.(' . implode(',', $clienteIds) . ')',
                ]);

                if (!$respuestaClientes->successful()) {
                    logger()->error('Error consultando clientes de reservas', [
                        'status' => $respuestaClientes->status(),
                    ]);

                    $vista['error'] = 'No se pudieron consultar los clientes de las reservas.';

                    return view('trabajador.reservas', $vista);
                }

                $clientes = $respuestaClientes->json() ?? [];
            }

            if (!empty($mesaIds)) {
                $respuestaMesas = $consultar('mesas', [
                    'select' => 'id,nombre,zona,capacidad',
                    'id' => 'in.(' . implode(',', $mesaIds) . ')',
                ]);

                if (!$respuestaMesas->successful()) {
                    logger()->error('Error consultando mesas de reservas', [
                        'status' => $respuestaMesas->status(),
                    ]);

                    $vista['error'] = 'No se pudieron consultar las mesas de las reservas.';

                    return view('trabajador.reservas', $vista);
                }

                $mesas = $respuestaMesas->json() ?? [];
            }

            $clientesPorId = [];

            foreach ($clientes as $cliente) {
                $clientesPorId[$cliente['id']] = $cliente;
            }

            $mesasPorId = [];

            foreach ($mesas as $mesa) {
                $mesasPorId[$mesa['id']] = $mesa;
            }

            foreach ($reservas as &$reserva) {
                $reserva['cliente'] = $clientesPorId[$reserva['cliente_id']] ?? null;
                $reserva['mesa'] = $mesasPorId[$reserva['mesa_id'] ?? ''] ?? null;
            }

            unset($reserva);

            $vista['reservas'] = $reservas;

        } catch (ConnectionException $e) {
            report($e);

            $vista['error'] = 'No fue posible conectar con Supabase. Inténtalo nuevamente.';
        }

        return view('trabajador.reservas', $vista);
    }

    private function renovarTokenSupabase(): bool
    {
        $url = rtrim(config('services.supabase.url'), '/');
        $key = config('services.supabase.key');
        $refreshToken = session('supabase_refresh_token');

        if (!$url || !$key || !$refreshToken) {
            return false;
        }

        $respuesta = Http::acceptJson()
            ->withHeaders([
                'apikey' => $key,
            ])
            ->connectTimeout(5)
            ->timeout(15)
            ->post($url . '/auth/v1/token?grant_type=refresh_token', [
                'refresh_token' => $refreshToken,
            ]);

        if (!$respuesta->successful()) {
            return false;
        }

        $datos = $respuesta->json();

        if (empty($datos['access_token']) || empty($datos['refresh_token'])) {
            return false;
        }

        session([
            'supabase_access_token' => $datos['access_token'],
            'supabase_refresh_token' => $datos['refresh_token'],
        ]);

        return true;
    }

    private function mostrarInventario(): View
    {
        $token = session('supabase_access_token');
        $url = rtrim(config('services.supabase.url'), '/');
        $key = config('services.supabase.key');

        $vista = [
            'ingredientes' => [],
            'lotes' => [],
            'movimientos' => [],
            'proveedores' => [],
            'trabajadores' => [],
            'error' => null,
        ];

        if (!$token || !$url || !$key) {
            $vista['error'] = 'No se pudo obtener la sesión de Supabase. Cierra sesión e inicia nuevamente.';

            return view('trabajador.inventario', $vista);
        }

        $consultar = function (string $tabla, array $filtros) use ($url, $key, &$token) {
            return Http::acceptJson()
                ->withToken($token)
                ->withHeaders(['apikey' => $key])
                ->connectTimeout(5)
                ->timeout(15)
                ->get($url . '/rest/v1/' . $tabla, $filtros);
        };

        try {
            $consultas = [
                'ingredientes' => [
                    'select' => 'id,nombre,descripcion,tipo,unidad,stock_minimo,activo',
                    'order' => 'nombre.asc',
                    'limit' => '200',
                ],
                'lotes' => [
                    'select' => 'id,ingrediente_id,proveedor_id,codigo_lote,cantidad_inicial,cantidad_actual,costo_total,fecha_ingreso,fecha_vencimiento,creado_en',
                    'order' => 'fecha_ingreso.desc',
                    'limit' => '200',
                ],
                'movimientos' => [
                    'select' => 'id,ingrediente_id,lote_id,trabajador_id,tipo,cantidad,costo_unitario,motivo,pedido_id,creado_en',
                    'order' => 'creado_en.desc',
                    'limit' => '200',
                ],
            ];

            foreach ($consultas as $tabla => $filtros) {
                $tablaReal = match ($tabla) {
                    'ingredientes' => 'ingredientes',
                    'lotes' => 'lotes_ingrediente',
                    'movimientos' => 'movimientos_inventario',
                };

                $respuesta = $consultar($tablaReal, $filtros);

                if ($respuesta->status() === 401) {
                    if ($this->renovarTokenSupabase()) {
                        $token = session('supabase_access_token');
                        $respuesta = $consultar($tablaReal, $filtros);
                    }
                }

                if (!$respuesta->successful()) {
                    $vista['error'] = 'No se pudo consultar toda la información del inventario. Verifica los permisos de acceso.';

                    logger()->error('Error consultando inventario en Supabase', [
                        'tabla' => $tabla,
                        'status' => $respuesta->status(),
                        'respuesta' => $respuesta->json() ?? $respuesta->body(),
                    ]);

                    return view('trabajador.inventario', $vista);
                }

                $vista[$tabla] = $respuesta->json() ?? [];
            }

            $respuestaProveedores = $consultar('proveedores', [
                'select' => 'id,nombre,activo',
                'activo' => 'eq.true',
                'order' => 'nombre.asc',
                'limit' => '200',
            ]);

            if ($respuestaProveedores->successful()) {
                $vista['proveedores'] = $respuestaProveedores->json() ?? [];
            } else {
                logger()->warning('No se pudieron consultar los proveedores', [
                    'status' => $respuestaProveedores->status(),
                ]);
            }

            $ingredientesPorId = [];

            foreach ($vista['ingredientes'] as $ingrediente) {
                $ingredientesPorId[$ingrediente['id']] = $ingrediente;
            }

            $proveedoresPorId = [];

            foreach ($vista['proveedores'] as $proveedor) {
                $proveedoresPorId[$proveedor['id']] = $proveedor;
            }

            $trabajadorIds = array_unique(array_filter(
                array_column($vista['movimientos'], 'trabajador_id')
            ));

            if (!empty($trabajadorIds)) {
                $respuestaTrabajadores = $consultar('profiles', [
                    'select' => 'id,full_name',
                    'id' => 'in.(' . implode(',', $trabajadorIds) . ')',
                ]);

                if ($respuestaTrabajadores->successful()) {
                    $vista['trabajadores'] = $respuestaTrabajadores->json() ?? [];
                }
            }

            $trabajadoresPorId = [];

            foreach ($vista['trabajadores'] as $trabajador) {
                $trabajadoresPorId[$trabajador['id']] = $trabajador;
            }

            foreach ($vista['lotes'] as &$lote) {
                $lote['ingrediente'] =
                    $ingredientesPorId[$lote['ingrediente_id']] ?? null;

                $lote['proveedor'] =
                    $proveedoresPorId[$lote['proveedor_id'] ?? ''] ?? null;
            }

            unset($lote);

            foreach ($vista['movimientos'] as &$movimiento) {
                $movimiento['ingrediente'] =
                    $ingredientesPorId[$movimiento['ingrediente_id']] ?? null;

                $movimiento['trabajador'] =
                    $trabajadoresPorId[$movimiento['trabajador_id'] ?? ''] ?? null;
            }

            unset($movimiento);

        } catch (ConnectionException $e) {
            report($e);
            $vista['error'] = 'No fue posible conectar con Supabase. Inténtalo nuevamente.';
        }

        return view('trabajador.inventario', $vista);
    }

    public function registrarLote(Request $request)
    {
        $datos = $request->validate([
            'ingrediente_id' => ['required', 'uuid'],
            'cantidad' => ['required', 'numeric', 'gt:0'],
            'costo_total' => ['required', 'integer', 'min:0'],
            'proveedor_id' => ['nullable', 'uuid'],
            'codigo_lote' => ['nullable', 'string', 'max:100'],
            'fecha_ingreso' => ['required', 'date'],
            'fecha_vencimiento' => ['nullable', 'date', 'after_or_equal:fecha_ingreso'],
        ]);

        $token = session('supabase_access_token');
        $url = rtrim(config('services.supabase.url'), '/');
        $key = config('services.supabase.key');

        if (!$token || !$url || !$key) {
            return back()->with(
                'error',
                'No se pudo validar la sesión con Supabase.'
            )->withInput();
        }

        try {
            $respuesta = Http::acceptJson()
                ->withToken($token)
                ->withHeaders(['apikey' => $key])
                ->connectTimeout(5)
                ->timeout(15)
                ->post($url . '/rest/v1/rpc/registrar_lote', [
                    'p_ingrediente_id' => $datos['ingrediente_id'],
                    'p_cantidad' => $datos['cantidad'],
                    'p_costo_total' => $datos['costo_total'],
                    'p_proveedor_id' => $datos['proveedor_id'] ?? null,
                    'p_codigo_lote' => $datos['codigo_lote'] ?? null,
                    'p_fecha_ingreso' => $datos['fecha_ingreso'],
                    'p_fecha_vencimiento' => $datos['fecha_vencimiento'] ?? null,
                ]);

            if (!$respuesta->successful()) {
                logger()->error('Error al registrar lote en Supabase', [
                    'status' => $respuesta->status(),
                    'respuesta' => $respuesta->body(),
                ]);

                return back()->with(
                    'error',
                    'No fue posible registrar el lote. Verifica los datos e inténtalo nuevamente.'
                )->withInput();
            }

            return redirect()
                ->route('trabajador.seccion', ['seccion' => 'inventario'])
                ->with('success', 'El lote se registró correctamente.');

        } catch (ConnectionException $e) {
            report($e);

            return back()->with(
                'error',
                'No se pudo establecer conexión con Supabase.'
            )->withInput();
        }
    }

    public function ajustarExistencias(Request $request)
    {
        $datos = $request->validate([
            'lote_id' => ['required', 'uuid'],
            'cantidad' => ['required', 'numeric', 'not_in:0'],
            'motivo' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $token = session('supabase_access_token');
        $url = rtrim(config('services.supabase.url'), '/');
        $key = config('services.supabase.key');

        if (!$token || !$url || !$key) {
            return back()->with(
                'error',
                'No se pudo validar la sesión con Supabase.'
            )->withInput();
        }

        try {
            $respuesta = Http::acceptJson()
                ->withToken($token)
                ->withHeaders(['apikey' => $key])
                ->connectTimeout(5)
                ->timeout(15)
                ->post($url . '/rest/v1/rpc/ajustar_inventario', [
                    'p_lote_id' => $datos['lote_id'],
                    'p_cantidad' => $datos['cantidad'],
                    'p_motivo' => $datos['motivo'],
                ]);

            if (!$respuesta->successful()) {
                logger()->error('Error al ajustar inventario en Supabase', [
                    'status' => $respuesta->status(),
                    'respuesta' => $respuesta->body(),
                ]);

                return back()->with(
                    'error',
                    'No fue posible ajustar las existencias. Verifica los datos e inténtalo nuevamente.'
                )->withInput();
            }

            return redirect()
                ->route('trabajador.seccion', ['seccion' => 'inventario'])
                ->with('success', 'Las existencias se ajustaron correctamente.');

        } catch (ConnectionException $e) {
            report($e);

            return back()->with(
                'error',
                'No se pudo establecer conexión con Supabase.'
            )->withInput();
        }
    }


    public function detalleMesa(string $mesa): JsonResponse
    {
        if (!Str::isUuid($mesa)) {
            return response()->json([
                'error' => 'Identificador de mesa inválido.'
            ], 400);
        }

        $token = session('supabase_access_token');
        $url = rtrim(config('services.supabase.url'), '/');
        $key = config('services.supabase.key');

        if (!$token || !$url || !$key) {
            return response()->json([
                'error' => 'La sesión de Supabase no está disponible.'
            ], 401);
        }

        $consultar = function (string $tabla, array $filtros) use ($url, $key, $token) {
            return Http::acceptJson()
                ->withToken($token)
                ->withHeaders(['apikey' => $key])
                ->connectTimeout(5)
                ->timeout(15)
                ->get($url . '/rest/v1/' . $tabla, $filtros);
        };

        try {
            $respuestaMesa = $consultar('mesas', [
                'id' => 'eq.' . $mesa,
                'select' => 'id,nombre,capacidad,forma,zona,estado,activa',
            ]);

            if (!$respuestaMesa->successful()) {
                logger()->error('Error consultando detalle de mesa', [
                    'status' => $respuestaMesa->status(),
                ]);

                return response()->json([
                    'error' => 'No fue posible consultar la mesa.'
                ], 502);
            }

            $mesas = $respuestaMesa->json() ?? [];

            if (count($mesas) === 0) {
                return response()->json([
                    'error' => 'La mesa no existe o no está disponible.'
                ], 404);
            }

            $datosMesa = $mesas[0];

            $resultado = [
                'mesa' => $datosMesa,
                'sesion' => null,
                'cliente' => null,
                'pedidos' => [],
                'total_pedidos' => 0,
            ];

            $respuestaSesion = $consultar('sesiones', [
                'mesa_id' => 'eq.' . $mesa,
                'activa' => 'eq.true',
                'select' => 'id,mesa_id,cliente_id,iniciada_en,activa',
                'limit' => '1',
            ]);

            if (!$respuestaSesion->successful()) {
                logger()->error('Error consultando sesión de mesa', [
                    'status' => $respuestaSesion->status(),
                ]);

                return response()->json([
                    'error' => 'No fue posible consultar la sesión de la mesa.'
                ], 502);
            }

            $sesiones = $respuestaSesion->json() ?? [];

            if (count($sesiones) === 0) {
                return response()->json($resultado);
            }

            $sesion = $sesiones[0];
            $resultado['sesion'] = $sesion;

            if (!empty($sesion['cliente_id'])) {
                $respuestaCliente = $consultar('profiles', [
                    'id' => 'eq.' . $sesion['cliente_id'],
                    'select' => 'id,full_name',
                    'limit' => '1',
                ]);

                if ($respuestaCliente->successful()) {
                    $clientes = $respuestaCliente->json() ?? [];
                    $resultado['cliente'] = $clientes[0] ?? null;
                }
            }

            $respuestaPedidos = $consultar('pedidos', [
                'sesion_id' => 'eq.' . $sesion['id'],
                'select' => 'id,cliente_id,trabajador_id,estado,subtotal,total,observaciones,creado_en',
                'order' => 'creado_en.desc',
            ]);

            if (!$respuestaPedidos->successful()) {
                logger()->error('Error consultando pedidos de mesa', [
                    'status' => $respuestaPedidos->status(),
                ]);

                return response()->json([
                    'error' => 'No fue posible consultar los pedidos de la sesión.'
                ], 502);
            }

            $pedidos = $respuestaPedidos->json() ?? [];

            foreach ($pedidos as &$pedido) {
                $respuestaDetalles = $consultar('detalle_pedido', [
                    'pedido_id' => 'eq.' . $pedido['id'],
                    'select' => 'id,nombre_producto,cantidad,precio_unitario,subtotal',
                ]);

                if (!$respuestaDetalles->successful()) {
                    logger()->error('Error consultando detalles de pedido', [
                        'status' => $respuestaDetalles->status(),
                    ]);

                    return response()->json([
                        'error' => 'No fue posible consultar los productos de los pedidos.'
                    ], 502);
                }

                $pedido['productos'] = $respuestaDetalles->json() ?? [];
            }

            unset($pedido);

            $resultado['pedidos'] = $pedidos;
            $resultado['total_pedidos'] = array_sum(
                array_column($pedidos, 'total')
            );

            return response()->json($resultado);

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            report($e);

            return response()->json([
                'error' => 'No fue posible conectar con Supabase.'
            ], 503);
        }
    }

}
