<?php

namespace App\Http\Controllers\Administrador;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class DashboardController extends Controller
{
    public function index()
    {
        $ingredientes = [];
        $categorias = [];
        $productos = [];
        $recetasProductos = [];
        $costosIngrediente = [];
        $costoPlatillos = [];
        $costoDisponiblePlatillos = [];
        $error = null;

        try {
            foreach ([
                'ingredientes' => ['select' => 'id,nombre,descripcion,tipo,unidad,stock_minimo,activo', 'order' => 'nombre.asc', 'limit' => '100'],
                'categorias' => ['select' => 'id,nombre,descripcion,orden,activa', 'order' => 'orden.asc,nombre.asc', 'limit' => '100'],
                'productos' => ['select' => 'id,categoria_id,nombre,descripcion,precio,imagen_url,tiempo_preparacion_min,activo', 'order' => 'nombre.asc', 'limit' => '100'],
            ] as $tabla => $filtros) {
                $respuesta = $this->supabase()->get($this->restUrl($tabla), $filtros);

                if (!$respuesta->successful()) {
                    logger()->error('No se pudo consultar el módulo de administración', [
                        'tabla' => $tabla,
                        'status' => $respuesta->status(),
                        'respuesta' => $respuesta->json() ?? $respuesta->body(),
                    ]);
                    $error = 'No fue posible cargar toda la información. Comprueba que la cuenta admin tenga permisos en Supabase.';
                    break;
                }

                $$tabla = $respuesta->json() ?? [];
            }

            $respuestaLotes = $this->supabase()->get($this->restUrl('lotes_ingrediente'), [
                'select' => 'ingrediente_id,cantidad_inicial,costo_total',
                'limit' => '1000',
            ]);

            if ($respuestaLotes->successful()) {
                $cantidadesCompradas = [];
                $costosComprados = [];
                foreach ($respuestaLotes->json() ?? [] as $lote) {
                    $ingredienteId = $lote['ingrediente_id'] ?? null;
                    $cantidad = (float) ($lote['cantidad_inicial'] ?? 0);
                    $costo = (float) ($lote['costo_total'] ?? 0);
                    if (!$ingredienteId || $cantidad <= 0) {
                        continue;
                    }
                    $cantidadesCompradas[$ingredienteId] = ($cantidadesCompradas[$ingredienteId] ?? 0) + $cantidad;
                    $costosComprados[$ingredienteId] = ($costosComprados[$ingredienteId] ?? 0) + $costo;
                }

                foreach ($cantidadesCompradas as $ingredienteId => $cantidad) {
                    $costosIngrediente[$ingredienteId] = $costosComprados[$ingredienteId] / $cantidad;
                }
            } else {
                logger()->warning('No se pudieron consultar los costes de compra de ingredientes', [
                    'status' => $respuestaLotes->status(),
                ]);
            }

            $productoIds = array_values(array_filter(array_column($productos, 'id')));
            if ($productoIds) {
                $respuestaRecetas = $this->supabase()->get($this->restUrl('recetas'), [
                    'select' => 'producto_id,ingrediente_id,cantidad',
                    'producto_id' => 'in.(' . implode(',', $productoIds) . ')',
                ]);

                if ($respuestaRecetas->successful()) {
                    $ingredientesPorId = [];
                    foreach ($ingredientes as $ingrediente) {
                        $ingredientesPorId[$ingrediente['id']] = $ingrediente;
                    }

                    foreach ($respuestaRecetas->json() ?? [] as $linea) {
                        $ingrediente = $ingredientesPorId[$linea['ingrediente_id']] ?? null;
                        $recetasProductos[$linea['producto_id']][] = [
                            'nombre' => $ingrediente['nombre'] ?? 'Ingrediente no disponible',
                            'cantidad' => $linea['cantidad'],
                            'unidad' => $ingrediente['unidad'] ?? '',
                            'costo_unitario' => $costosIngrediente[$linea['ingrediente_id']] ?? null,
                            'costo_linea' => isset($costosIngrediente[$linea['ingrediente_id']])
                                ? (float) $linea['cantidad'] * $costosIngrediente[$linea['ingrediente_id']]
                                : null,
                        ];
                        if (isset($costosIngrediente[$linea['ingrediente_id']])) {
                            $costoPlatillos[$linea['producto_id']] = ($costoPlatillos[$linea['producto_id']] ?? 0)
                                + (float) $linea['cantidad'] * $costosIngrediente[$linea['ingrediente_id']];
                        } else {
                            $costoDisponiblePlatillos[$linea['producto_id']] = false;
                        }
                    }
                    foreach ($productoIds as $productoId) {
                        $costoDisponiblePlatillos[$productoId] = $costoDisponiblePlatillos[$productoId] ?? true;
                    }
                } else {
                    logger()->warning('No se pudieron consultar las recetas en administración', [
                        'status' => $respuestaRecetas->status(),
                    ]);
                }
            }
        } catch (ConnectionException $exception) {
            report($exception);
            $error = 'No se pudo conectar con Supabase. Inténtalo de nuevo.';
        } catch (\RuntimeException $exception) {
            $error = $exception->getMessage();
        }

        return view('admin.dashboard', compact('ingredientes', 'categorias', 'productos', 'recetasProductos', 'costosIngrediente', 'costoPlatillos', 'costoDisponiblePlatillos', 'error'));
    }

    public function reportes(Request $request)
    {
        [$desde, $hasta, $inicio, $fin, $mesaId] = $this->rangoReporte($request);
        $error = null;
        $reporte = $this->reporteVacio($desde, $hasta, $mesaId);

        try {
            $reporte = $this->construirReporte($desde, $hasta, $inicio, $fin, $mesaId);
        } catch (ConnectionException $exception) {
            report($exception);
            $error = 'No fue posible conectar con Supabase. Inténtalo nuevamente.';
        } catch (\RuntimeException $exception) {
            $error = $exception->getMessage();
        }

        return view('admin.reportes', $reporte + ['error' => $error]);
    }

    public function exportarReportePdf(Request $request)
    {
        [$desde, $hasta, $inicio, $fin, $mesaId] = $this->rangoReporte($request);

        try {
            $reporte = $this->construirReporte($desde, $hasta, $inicio, $fin, $mesaId);
        } catch (ConnectionException $exception) {
            report($exception);
            return redirect()->route('admin.reportes', ['desde' => $desde, 'hasta' => $hasta, 'mesa_id' => $mesaId])
                ->with('error', 'No fue posible conectar con Supabase. Inténtalo nuevamente.');
        } catch (\RuntimeException $exception) {
            return redirect()->route('admin.reportes', ['desde' => $desde, 'hasta' => $hasta, 'mesa_id' => $mesaId])
                ->with('error', $exception->getMessage());
        }

        $options = new Options();
        $options->setDefaultFont('DejaVu Sans');
        $pdf = new Dompdf($options);
        $pdf->loadHtml(view('admin.reportes-pdf', $reporte)->render(), 'UTF-8');
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="reporte-ventas-' . $desde . '-' . $hasta . '.pdf"',
        ]);
    }

    private function rangoReporte(Request $request): array
    {
        $datos = $request->validate([
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'mesa_id' => ['nullable', 'uuid'],
        ]);
        $hoy = CarbonImmutable::now('America/Bogota')->startOfDay();
        $desde = CarbonImmutable::parse($datos['desde'] ?? $hoy->startOfMonth()->toDateString(), 'America/Bogota');
        $hasta = CarbonImmutable::parse($datos['hasta'] ?? $hoy->toDateString(), 'America/Bogota');

        if ($desde->greaterThan($hasta)) {
            throw ValidationException::withMessages([
                'hasta' => 'La fecha final debe ser igual o posterior a la fecha inicial.',
            ]);
        }

        if ($desde->diffInDays($hasta) > 365) {
            throw ValidationException::withMessages([
                'hasta' => 'El periodo máximo para un reporte es de 366 días.',
            ]);
        }

        return [
            $desde->toDateString(),
            $hasta->toDateString(),
            $desde->startOfDay(),
            $hasta->addDay()->startOfDay(),
            $datos['mesa_id'] ?? null,
        ];
    }

    private function reporteVacio(string $desde, string $hasta, ?string $mesaId): array
    {
        return [
            'desde' => $desde,
            'hasta' => $hasta,
            'mesaId' => $mesaId,
            'mesas' => [],
            'mesaSeleccionada' => null,
            'reporteMesas' => [],
            'ventas' => 0,
            'ventasRegistradas' => 0,
            'pagosRecibidos' => 0,
            'saldoPendiente' => 0,
            'ticketPromedio' => 0,
            'gananciaEstimada' => null,
            'coberturaCostos' => 0,
            'ventasPorDia' => [],
            'ventasPorMetodo' => [],
            'productosMasVendidos' => [],
        ];
    }

    private function construirReporte(string $desde, string $hasta, CarbonImmutable $inicio, CarbonImmutable $fin, ?string $mesaId = null): array
    {
        $mesas = $this->consultarReporte('mesas', [
            'select' => 'id,nombre,zona,activa',
            'order' => 'nombre.asc',
        ]);
        $pedidoFiltros = [
            'select' => 'id,mesa_id,sesion_id,estado,subtotal,total,observaciones,creado_en',
            'creado_en' => 'gte.' . $inicio->toIso8601String(),
            'and' => '(creado_en.lt.' . $fin->toIso8601String() . ')',
            'order' => 'creado_en.desc',
        ];
        if ($mesaId !== null) {
            $pedidoFiltros['mesa_id'] = 'eq.' . $mesaId;
        }

        $pedidos = $this->consultarReporte('pedidos', $pedidoFiltros);
        $pedidoPorId = [];
        foreach ($pedidos as $pedido) {
            if (!empty($pedido['id'])) {
                $pedidoPorId[$pedido['id']] = $pedido;
            }
        }
        $pedidosEntregadosIds = array_values(array_filter(array_map(
            fn (array $pedido) => ($pedido['estado'] ?? null) === 'entregado' ? ($pedido['id'] ?? null) : null,
            $pedidos
        )));
        $ventas = $this->consultarReportePorIds('ventas', 'pedido_id', $pedidosEntregadosIds, [
            'select' => 'id,pedido_id,subtotal,total,estado',
            'order' => 'id.asc',
        ]);
        $ventasValidas = array_values(array_filter(
            $ventas,
            fn (array $venta) => ($venta['estado'] ?? null) !== 'anulado'
        ));
        $ventaIds = array_values(array_filter(array_column($ventasValidas, 'id')));
        $pagos = $this->consultarReportePorIds('pagos', 'venta_id', $ventaIds, [
            'select' => 'id,venta_id,metodo_pago_id,monto,estado',
        ]);
        $pagosPagados = array_values(array_filter(
            $pagos,
            fn (array $pago) => ($pago['estado'] ?? null) === 'pagado'
        ));
        $metodoIds = array_values(array_unique(array_filter(array_column($pagosPagados, 'metodo_pago_id'))));
        $metodos = $this->consultarReportePorIds('metodos_pago', 'id', $metodoIds, [
            'select' => 'id,nombre',
        ]);
        $nombresMetodo = [];
        foreach ($metodos as $metodo) {
            $nombresMetodo[$metodo['id']] = $metodo['nombre'];
        }

        $pedidoIds = array_values(array_filter(array_column($pedidos, 'id')));
        $detalles = $this->consultarReportePorIds('detalle_pedido', 'pedido_id', $pedidoIds, [
            'select' => 'id,pedido_id,nombre_producto,cantidad,precio_unitario,subtotal',
        ]);
        $productosActuales = $this->consultarReporte('productos', [
            'select' => 'id,nombre',
        ]);
        $productoPorNombre = [];
        $nombresDuplicados = [];
        foreach ($productosActuales as $producto) {
            $nombre = mb_strtolower(trim($producto['nombre'] ?? ''), 'UTF-8');
            if ($nombre === '') {
                continue;
            }
            if (isset($productoPorNombre[$nombre])) {
                $nombresDuplicados[$nombre] = true;
            } else {
                $productoPorNombre[$nombre] = $producto['id'];
            }
        }
        foreach (array_keys($nombresDuplicados) as $nombre) {
            unset($productoPorNombre[$nombre]);
        }

        $productoIds = array_values(array_unique(array_filter(array_values($productoPorNombre))));
        $recetas = $this->consultarReportePorIds('recetas', 'producto_id', $productoIds, [
            'select' => 'producto_id,ingrediente_id,cantidad',
        ]);
        $lotes = $this->consultarReporte('lotes_ingrediente', [
            'select' => 'ingrediente_id,cantidad_inicial,costo_total',
        ]);
        $cantidadesIngrediente = [];
        $costosIngrediente = [];
        foreach ($lotes as $lote) {
            $ingredienteId = $lote['ingrediente_id'] ?? null;
            $cantidad = (float) ($lote['cantidad_inicial'] ?? 0);
            if (!$ingredienteId || $cantidad <= 0) {
                continue;
            }
            $cantidadesIngrediente[$ingredienteId] = ($cantidadesIngrediente[$ingredienteId] ?? 0) + $cantidad;
            $costosIngrediente[$ingredienteId] = ($costosIngrediente[$ingredienteId] ?? 0)
                + (float) ($lote['costo_total'] ?? 0);
        }
        foreach ($cantidadesIngrediente as $ingredienteId => $cantidad) {
            $costosIngrediente[$ingredienteId] /= $cantidad;
        }

        $costoPorProducto = [];
        $recetaCompleta = [];
        foreach ($recetas as $linea) {
            $productoId = $linea['producto_id'];
            $ingredienteId = $linea['ingrediente_id'];
            if (!isset($costosIngrediente[$ingredienteId])) {
                $recetaCompleta[$productoId] = false;
                continue;
            }
            $costoPorProducto[$productoId] = ($costoPorProducto[$productoId] ?? 0)
                + (float) $linea['cantidad'] * $costosIngrediente[$ingredienteId];
        }
        foreach (array_unique(array_column($recetas, 'producto_id')) as $productoId) {
            $recetaCompleta[$productoId] = $recetaCompleta[$productoId] ?? true;
        }

        $ventaPorPedido = [];
        $pagadoPorVenta = [];
        $pagosPorMetodo = [];
        foreach ($ventasValidas as $venta) {
            $ventaPorPedido[$venta['pedido_id']] = $venta;
        }
        foreach ($pagosPagados as $pago) {
            $monto = (float) ($pago['monto'] ?? 0);
            $pagadoPorVenta[$pago['venta_id']] = ($pagadoPorVenta[$pago['venta_id']] ?? 0) + $monto;
            $nombreMetodo = $nombresMetodo[$pago['metodo_pago_id'] ?? ''] ?? 'Sin método';
            $pagosPorMetodo[$nombreMetodo] = ($pagosPorMetodo[$nombreMetodo] ?? 0) + $monto;
        }

        $detallesPorPedido = [];
        foreach ($detalles as $detalle) {
            if (!empty($detalle['pedido_id'])) {
                $detallesPorPedido[$detalle['pedido_id']][] = $detalle;
            }
        }

        $totalVentas = 0;
        $totalPagado = 0;
        $saldoPendiente = 0;
        $ventasPorDia = [];
        $gananciaEstimada = 0;
        $ingresoConCosto = 0;
        $ingresoDetalles = 0;
        $productosVendidos = [];
        $reporteMesas = [];
        $mesaSeleccionada = null;
        foreach ($mesas as $mesa) {
            if (empty($mesa['id'])) {
                continue;
            }
            if ($mesaId === null || $mesaId === $mesa['id']) {
                $reporteMesas[$mesa['id']] = [
                    'id' => $mesa['id'],
                    'nombre' => $mesa['nombre'] ?? 'Mesa',
                    'zona' => $mesa['zona'] ?? '',
                    'pedidosRegistrados' => 0,
                    'ventasRegistradas' => 0,
                    'ventas' => 0,
                    'pagosRecibidos' => 0,
                    'saldoPendiente' => 0,
                    'historial' => [],
                ];
            }
            if ($mesaId === $mesa['id']) {
                $mesaSeleccionada = $mesa;
            }
        }

        foreach ($pedidos as $pedido) {
            $mesaPedidoId = $pedido['mesa_id'] ?? null;
            if (!$mesaPedidoId || !isset($reporteMesas[$mesaPedidoId])) {
                continue;
            }

            $venta = $ventaPorPedido[$pedido['id'] ?? ''] ?? null;
            $pagado = $venta ? ($pagadoPorVenta[$venta['id']] ?? 0) : 0;
            $totalVenta = $venta ? (float) ($venta['total'] ?? 0) : 0;
            $reporteMesas[$mesaPedidoId]['pedidosRegistrados']++;
            if ($venta) {
                $reporteMesas[$mesaPedidoId]['ventasRegistradas']++;
                $reporteMesas[$mesaPedidoId]['ventas'] += $totalVenta;
                $reporteMesas[$mesaPedidoId]['pagosRecibidos'] += $pagado;
                $reporteMesas[$mesaPedidoId]['saldoPendiente'] += max(0, $totalVenta - $pagado);
            }
            $reporteMesas[$mesaPedidoId]['historial'][] = [
                'id' => $pedido['id'] ?? '',
                'estado' => $pedido['estado'] ?? 'sin estado',
                'fecha' => $pedido['creado_en'] ?? null,
                'total' => (float) ($pedido['total'] ?? 0),
                'sesion_id' => $pedido['sesion_id'] ?? null,
                'observaciones' => $pedido['observaciones'] ?? null,
                'productos' => $detallesPorPedido[$pedido['id'] ?? ''] ?? [],
                'venta' => $venta,
                'pagosRecibidos' => $pagado,
            ];
        }

        foreach ($reporteMesas as &$mesaReporte) {
            usort($mesaReporte['historial'], fn (array $a, array $b) => strcmp($b['fecha'] ?? '', $a['fecha'] ?? ''));
        }
        unset($mesaReporte);

        foreach ($ventasValidas as $venta) {
            $total = (float) ($venta['total'] ?? 0);
            $totalVentas += $total;
            $pagado = $pagadoPorVenta[$venta['id']] ?? 0;
            $totalPagado += $pagado;
            $saldoPendiente += max(0, $total - $pagado);
            $pedido = $pedidoPorId[$venta['pedido_id']] ?? null;
            if ($pedido && !empty($pedido['creado_en'])) {
                $fecha = CarbonImmutable::parse($pedido['creado_en'])->setTimezone('America/Bogota')->toDateString();
                $ventasPorDia[$fecha] = ($ventasPorDia[$fecha] ?? 0) + $total;
            }
        }

        foreach ($detalles as $detalle) {
            $venta = $ventaPorPedido[$detalle['pedido_id'] ?? ''] ?? null;
            if (!$venta) {
                continue;
            }
            $nombre = trim($detalle['nombre_producto'] ?? 'Producto sin nombre');
            $cantidad = (float) ($detalle['cantidad'] ?? 0);
            $subtotal = (float) ($detalle['subtotal'] ?? 0);
            $productosVendidos[$nombre] = [
                'nombre' => $nombre,
                'cantidad' => ($productosVendidos[$nombre]['cantidad'] ?? 0) + $cantidad,
                'subtotal' => ($productosVendidos[$nombre]['subtotal'] ?? 0) + $subtotal,
            ];
            $ingresoDetalles += $subtotal;
            $nombreNormalizado = mb_strtolower($nombre, 'UTF-8');
            $productoId = $productoPorNombre[$nombreNormalizado] ?? null;
            if ($productoId && ($recetaCompleta[$productoId] ?? false)) {
                $ingresoConCosto += $subtotal;
                $gananciaEstimada += $subtotal - $cantidad * ($costoPorProducto[$productoId] ?? 0);
            }
        }

        usort($productosVendidos, fn (array $a, array $b) => $b['cantidad'] <=> $a['cantidad']);
        ksort($ventasPorDia);
        arsort($pagosPorMetodo);
        $coberturaCostos = $ingresoDetalles > 0 ? min(100, $ingresoConCosto / $ingresoDetalles * 100) : 0;

        return [
            'desde' => $desde,
            'hasta' => $hasta,
            'mesaId' => $mesaId,
            'mesas' => $mesas,
            'mesaSeleccionada' => $mesaSeleccionada,
            'reporteMesas' => array_values($reporteMesas),
            'ventas' => $totalVentas,
            'ventasRegistradas' => count($ventasValidas),
            'pagosRecibidos' => $totalPagado,
            'saldoPendiente' => $saldoPendiente,
            'ticketPromedio' => count($ventasValidas) ? $totalVentas / count($ventasValidas) : 0,
            'gananciaEstimada' => $ingresoConCosto > 0 ? $gananciaEstimada : null,
            'coberturaCostos' => $coberturaCostos,
            'ventasPorDia' => array_map(
                fn ($fecha, $monto) => ['fecha' => $fecha, 'monto' => $monto],
                array_keys($ventasPorDia),
                array_values($ventasPorDia)
            ),
            'ventasPorMetodo' => array_map(
                fn ($nombre, $monto) => ['nombre' => $nombre, 'monto' => $monto],
                array_keys($pagosPorMetodo),
                array_values($pagosPorMetodo)
            ),
            'productosMasVendidos' => array_slice(array_values($productosVendidos), 0, 10),
        ];
    }

    private function consultarReporte(string $tabla, array $filtros): array
    {
        $registros = [];
        $tamanoPagina = 500;

        for ($inicio = 0; ; $inicio += $tamanoPagina) {
            $respuesta = $this->supabase()->withHeaders([
                'Range' => $inicio . '-' . ($inicio + $tamanoPagina - 1),
            ])->get($this->restUrl($tabla), array_merge($filtros, ['limit' => (string) $tamanoPagina]));

            if (!$respuesta->successful()) {
                logger()->error('No se pudo consultar información para los reportes', [
                    'tabla' => $tabla,
                    'status' => $respuesta->status(),
                ]);
                throw new \RuntimeException('No fue posible consultar los datos del reporte en Supabase.');
            }

            $pagina = $respuesta->json();
            if (!is_array($pagina)) {
                throw new \RuntimeException('Supabase devolvió datos inválidos para el reporte.');
            }
            $registros = array_merge($registros, $pagina);
            if (count($pagina) < $tamanoPagina) {
                break;
            }
        }

        return $registros;
    }

    private function consultarReportePorIds(string $tabla, string $campo, array $ids, array $filtros): array
    {
        $registros = [];
        foreach (array_chunk(array_values(array_unique($ids)), 100) as $grupoIds) {
            $registros = array_merge(
                $registros,
                $this->consultarReporte($tabla, $filtros + [
                    $campo => 'in.(' . implode(',', $grupoIds) . ')',
                ])
            );
        }

        return $registros;
    }

    public function personal()
    {
        $personal = [];
        $error = null;

        try {
            $respuesta = $this->supabase()->get($this->restUrl('profiles'), [
                'select' => 'id,full_name,role',
                'role' => 'eq.staff',
                'order' => 'full_name.asc',
                'limit' => '500',
            ]);

            if ($respuesta->successful()) {
                $personal = $respuesta->json() ?? [];
            } else {
                logger()->error('No se pudo consultar el personal en administración', [
                    'status' => $respuesta->status(),
                    'respuesta' => $respuesta->json() ?? $respuesta->body(),
                ]);
                $error = 'No fue posible cargar el personal. Comprueba los permisos de lectura de profiles en Supabase.';
            }
        } catch (ConnectionException $exception) {
            report($exception);
            $error = 'No se pudo conectar con Supabase. Inténtalo de nuevo.';
        } catch (\RuntimeException $exception) {
            $error = $exception->getMessage();
        }

        return view('admin.personal', compact('personal', 'error'));
    }

    public function mesas()
    {
        $mesas = [];
        $error = null;
        try {
            $respuesta = $this->supabase()->get($this->restUrl('mesas'), [
                'select' => 'id,nombre,capacidad,forma,zona,pos_x,pos_y,ancho,alto,rotacion,estado,activa',
                'order' => 'nombre.asc',
                'limit' => '500',
            ]);
            if ($respuesta->successful()) {
                $mesas = $respuesta->json() ?? [];
            } else {
                $error = 'No se pudieron cargar las mesas. Comprueba los permisos de Supabase.';
            }
        } catch (ConnectionException $exception) {
            report($exception);
            $error = 'No se pudo conectar con Supabase.';
        } catch (\RuntimeException $exception) {
            $error = $exception->getMessage();
        }

        return view('admin.mesas', compact('mesas', 'error'));
    }

    public function storeMesa(Request $request)
    {
        $datos = $this->validarMesa($request);
        $datos += ['estado' => 'libre', 'activa' => true];
        return $this->insertar($request, 'mesas', $datos, 'Mesa creada correctamente.', 'admin.mesas');
    }

    public function editarMesa(string $mesa)
    {
        return $this->mostrarEdicion('mesa', 'mesas', $mesa, 'id,nombre,capacidad,forma,zona,pos_x,pos_y,ancho,alto,rotacion,estado,activa');
    }

    public function updateMesa(Request $request, string $mesa)
    {
        $datos = $this->validarMesa($request);
        return $this->actualizar($request, 'mesas', $mesa, $datos, 'Mesa actualizada.', 'admin.mesas');
    }

    public function estadoMesa(Request $request, string $mesa)
    {
        return $this->cambiarEstado($request, 'mesas', $mesa, 'activa', 'admin.mesas', 'estado');
    }

    public function editarProducto(string $producto)
    {
        return $this->mostrarEdicion('producto', 'productos', $producto, 'id,categoria_id,nombre,descripcion,precio,imagen_url,tiempo_preparacion_min,activo', true);
    }

    public function updateProducto(Request $request, string $producto)
    {
        $datos = $this->validarProducto($request);
        $anterior = null;
        $anteriorReceta = [];
        $restaurar = false;
        try {
            $anterior = $this->registroSupabase('productos', $producto, 'id,nombre,categoria_id,descripcion,precio,imagen_url,tiempo_preparacion_min,activo');
            $anteriorReceta = $this->recetaSupabase($producto);
            $productoActualizado = $this->supabase()->withHeaders(['Prefer' => 'return=representation'])->patch(
                $this->restUrl('productos') . '?id=eq.' . urlencode($producto),
                $this->camposProducto($datos)
            );
            if (!$productoActualizado->successful()) {
                return $this->errorPostgrest($request, $productoActualizado, 'productos');
            }
            if (empty($productoActualizado->json())) {
                return back()->withInput()->with('error', 'No se encontró el platillo o Supabase no permitió actualizarlo.');
            }

            $restaurar = true;
            $borrado = $this->supabase()->send('DELETE', $this->restUrl('recetas'), [
                'query' => $this->filtroIdProducto($producto),
            ]);
            if (!$borrado->successful()) {
                $this->restaurarProducto($producto, $anterior, $anteriorReceta);
                return back()->withInput()->with('error', 'No se pudo actualizar la receta. El platillo anterior se conservó.');
            }

            $respuestaReceta = $this->supabase()->post($this->restUrl('recetas'), $this->filasReceta($producto, $datos['receta']));
            if (!$respuestaReceta->successful()) {
                $this->restaurarProducto($producto, $anterior, $anteriorReceta);
                logger()->error('No se pudo guardar la receta editada', ['producto_id' => $producto, 'status' => $respuestaReceta->status()]);
                return back()->withInput()->with('error', 'No se guardó la nueva receta; se restauraron los datos anteriores.');
            }

            return redirect()->route('admin.dashboard')->with('success', 'Platillo y receta actualizados.');
        } catch (ConnectionException $exception) {
            report($exception);
            if ($restaurar && $anterior) {
                try {
                    $this->restaurarProducto($producto, $anterior, $anteriorReceta);
                } catch (ConnectionException $restauracionException) {
                    report($restauracionException);
                }
            }
            return back()->withInput()->with('error', 'No se pudo conectar con Supabase. Revisa el platillo antes de volver a enviarlo.');
        } catch (\RuntimeException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }
    }

    public function estadoProducto(Request $request, string $producto)
    {
        return $this->cambiarEstado($request, 'productos', $producto, 'activo', 'admin.dashboard');
    }

    public function editarCategoria(string $categoria)
    {
        return $this->mostrarEdicion('categoria', 'categorias', $categoria, 'id,nombre,descripcion,orden,activa');
    }

    public function updateCategoria(Request $request, string $categoria)
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'descripcion' => ['nullable', 'string', 'max:500'],
            'orden' => ['required', 'integer', 'min:0', 'max:10000'],
        ]);
        return $this->actualizar($request, 'categorias', $categoria, $datos, 'Categoría actualizada.', 'admin.dashboard');
    }

    public function estadoCategoria(Request $request, string $categoria)
    {
        return $this->cambiarEstado($request, 'categorias', $categoria, 'activa', 'admin.dashboard');
    }

    public function editarIngrediente(string $ingrediente)
    {
        return $this->mostrarEdicion('ingrediente', 'ingredientes', $ingrediente, 'id,nombre,descripcion,tipo,unidad,stock_minimo,activo');
    }

    public function updateIngrediente(Request $request, string $ingrediente)
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'tipo' => ['nullable', 'string', 'max:80'],
            'unidad' => ['required', 'in:g,ml,unidad'],
            'stock_minimo' => ['required', 'numeric', 'min:0', 'max:100000000'],
        ]);
        return $this->actualizar($request, 'ingredientes', $ingrediente, $datos, 'Ingrediente actualizado.', 'admin.dashboard');
    }

    public function estadoIngrediente(Request $request, string $ingrediente)
    {
        return $this->cambiarEstado($request, 'ingredientes', $ingrediente, 'activo', 'admin.dashboard');
    }

    public function storeIngrediente(Request $request)
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'tipo' => ['nullable', 'string', 'max:80'],
            'unidad' => ['required', 'in:g,ml,unidad'],
            'stock_minimo' => ['required', 'numeric', 'min:0', 'max:100000000'],
        ]);

        return $this->insertar($request, 'ingredientes', [
            ...$datos,
            'activo' => true,
        ], 'Ingrediente creado. Si debe aparecer en el inventario, registra también su primer lote.');
    }

    public function storeCategoria(Request $request)
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'descripcion' => ['nullable', 'string', 'max:500'],
            'orden' => ['required', 'integer', 'min:0', 'max:10000'],
        ]);

        return $this->insertar($request, 'categorias', [
            ...$datos,
            'activa' => true,
        ], 'Categoría creada.');
    }

    public function storeProducto(Request $request)
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'categoria_id' => ['nullable', 'uuid'],
            'descripcion' => ['nullable', 'string', 'max:1500'],
            'precio' => ['required', 'integer', 'min:0', 'max:2147483647'],
            'imagen_url' => ['nullable', 'url', 'max:2000'],
            'tiempo_preparacion_min' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'receta' => ['required', 'array', 'min:1', 'max:50'],
            'receta.*.ingrediente_id' => ['required', 'uuid', 'distinct'],
            'receta.*.cantidad' => ['required', 'numeric', 'gt:0', 'max:100000000'],
        ]);

        try {
            $productoRespuesta = $this->supabase()
                ->withHeaders(['Prefer' => 'return=representation'])
                ->post($this->restUrl('productos'), [
                    'nombre' => $datos['nombre'],
                    'categoria_id' => $datos['categoria_id'] ?? null,
                    'descripcion' => $datos['descripcion'] ?? null,
                    'precio' => $datos['precio'],
                    'imagen_url' => $datos['imagen_url'] ?? null,
                    'tiempo_preparacion_min' => $datos['tiempo_preparacion_min'] ?? null,
                    'activo' => true,
                ]);

            if (!$productoRespuesta->successful()) {
                return $this->errorPostgrest($request, $productoRespuesta, 'productos');
            }

            $productoId = data_get($productoRespuesta->json(), '0.id');
            if (!$productoId) {
                logger()->error('Supabase creó un producto sin devolver su UUID');
                return back()->withInput()->with('error', 'El platillo se creó, pero no se pudo obtener su identificador para guardar la receta. Contacta al administrador de la base de datos.');
            }

            $filasReceta = array_map(
                fn (array $linea) => [
                    'producto_id' => $productoId,
                    'ingrediente_id' => $linea['ingrediente_id'],
                    'cantidad' => $linea['cantidad'],
                ],
                $datos['receta']
            );

            $recetaRespuesta = $this->supabase()
                ->withHeaders(['Prefer' => 'return=minimal'])
                ->post($this->restUrl('recetas'), $filasReceta);

            if (!$recetaRespuesta->successful()) {
                logger()->error('Falló el registro de receta para un producto nuevo', [
                    'producto_id' => $productoId,
                    'status' => $recetaRespuesta->status(),
                    'respuesta' => $recetaRespuesta->json() ?? $recetaRespuesta->body(),
                ]);

                $limpieza = $this->supabase()->send('DELETE', $this->restUrl('productos'), [
                    'query' => ['id' => 'eq.' . $productoId],
                ]);

                $mensaje = $limpieza->successful()
                    ? 'No se guardó la receta y se deshizo la creación del platillo. Verifica sus ingredientes e inténtalo de nuevo.'
                    : 'El platillo se creó, pero la receta no se guardó y no se pudo deshacer el alta. Revisa el catálogo antes de volver a intentarlo.';

                return back()->withInput()->with('error', $mensaje);
            }

            return redirect()->route('admin.dashboard')->with(
                'success',
                'Platillo y receta creados. La disponibilidad se calcula con las existencias de sus ingredientes.'
            );
        } catch (ConnectionException $exception) {
            report($exception);
            return back()->withInput()->with('error', 'No se pudo conectar con Supabase. Comprueba si el platillo alcanzó a guardarse antes de volver a enviarlo.');
        } catch (\RuntimeException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }
    }

    private function errorPostgrest(Request $request, $respuesta, string $tabla)
    {
        logger()->error('No se pudo crear un registro desde administración', [
            'tabla' => $tabla,
            'status' => $respuesta->status(),
            'respuesta' => $respuesta->json() ?? $respuesta->body(),
        ]);

        $detalle = $respuesta->json('message') ?? $respuesta->json('hint');
        $codigo = $respuesta->json('code');
        if ($codigo === '23505') {
            $entidad = match ($tabla) {
                'categorias' => 'una categoría con ese nombre',
                'ingredientes' => 'un ingrediente con ese nombre',
                'productos' => 'un platillo con ese nombre',
                default => 'un registro con esos datos',
            };
            return back()->withInput()->with('error', 'Ya existe ' . $entidad . '.');
        }

        if ($codigo === '23503') {
            return back()->withInput()->with('error', 'La categoría o el ingrediente seleccionado ya no está disponible. Actualiza el panel e inténtalo de nuevo.');
        }

        $mensajeError = match ($respuesta->status()) {
            401, 403 => 'Supabase rechazó la operación. Verifica las políticas RLS para administradores.',
            404 => 'La tabla solicitada no está disponible en Supabase.',
            default => is_string($detalle) && $detalle !== ''
                ? 'No se pudo guardar: ' . $detalle
                : 'No se pudo guardar. Revisa los campos y los permisos de Supabase.',
        };

        return back()->withInput()->with('error', $mensajeError);
    }

    private function insertar(Request $request, string $tabla, array $datos, string $mensaje, string $ruta = 'admin.dashboard')
    {
        try {
            $respuesta = $this->supabase()
                ->withHeaders(['Prefer' => 'return=representation'])
                ->post($this->restUrl($tabla), $datos);

            if (!$respuesta->successful()) {
                return $this->errorPostgrest($request, $respuesta, $tabla);
            }

            return redirect()->route($ruta)->with('success', $mensaje);
        } catch (ConnectionException $exception) {
            report($exception);
            return back()->withInput()->with('error', 'No se pudo conectar con Supabase. Inténtalo de nuevo.');
        } catch (\RuntimeException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }
    }

    private function validarMesa(Request $request): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'capacidad' => ['required', 'integer', 'min:1', 'max:1000'],
            'forma' => ['required', 'in:cuadrada,circular'],
            'zona' => ['nullable', 'string', 'max:100'],
            'pos_x' => ['required', 'numeric', 'min:0', 'max:10000'],
            'pos_y' => ['required', 'numeric', 'min:0', 'max:10000'],
            'ancho' => ['required', 'numeric', 'min:45', 'max:1000'],
            'alto' => ['required', 'numeric', 'min:45', 'max:1000'],
            'rotacion' => ['required', 'numeric', 'min:-360', 'max:360'],
        ]);
    }

    private function validarProducto(Request $request): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'categoria_id' => ['nullable', 'uuid'],
            'descripcion' => ['nullable', 'string', 'max:1500'],
            'precio' => ['required', 'integer', 'min:0', 'max:2147483647'],
            'imagen_url' => ['nullable', 'url', 'max:2000'],
            'tiempo_preparacion_min' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'receta' => ['required', 'array', 'min:1', 'max:50'],
            'receta.*.ingrediente_id' => ['required', 'uuid', 'distinct'],
            'receta.*.cantidad' => ['required', 'numeric', 'gt:0', 'max:100000000'],
        ]);
    }

    private function mostrarEdicion(string $tipo, string $tabla, string $id, string $select, bool $conReceta = false)
    {
        try {
            $registro = $this->registroSupabase($tabla, $id, $select);
            $categorias = [];
            $ingredientes = [];
            $receta = [];
            $mesasPlano = [];
            if ($conReceta) {
                $categoriasRespuesta = $this->supabase()->get($this->restUrl('categorias'), [
                    'select' => 'id,nombre,activa', 'order' => 'orden.asc,nombre.asc', 'limit' => '500',
                ]);
                $ingredientesRespuesta = $this->supabase()->get($this->restUrl('ingredientes'), [
                    'select' => 'id,nombre,unidad,activo', 'order' => 'nombre.asc', 'limit' => '500',
                ]);
                if ($categoriasRespuesta->successful()) $categorias = $categoriasRespuesta->json() ?? [];
                if ($ingredientesRespuesta->successful()) $ingredientes = $ingredientesRespuesta->json() ?? [];
                $receta = $this->recetaSupabase($id);
            } elseif ($tipo === 'mesa') {
                $mesasRespuesta = $this->supabase()->get($this->restUrl('mesas'), [
                    'select' => 'id,nombre,capacidad,forma,zona,pos_x,pos_y,ancho,alto,rotacion,estado,activa',
                    'order' => 'nombre.asc',
                    'limit' => '500',
                ]);
                if (!$mesasRespuesta->successful()) {
                    throw new \RuntimeException('No fue posible cargar el plano de mesas para editar.');
                }
                $mesasPlano = $mesasRespuesta->json() ?? [];
            }

            return view('admin.editar', compact('tipo', 'registro', 'categorias', 'ingredientes', 'receta', 'mesasPlano'));
        } catch (ConnectionException $exception) {
            report($exception);
            return redirect()->route('admin.dashboard')->with('error', 'No se pudo conectar con Supabase.');
        } catch (\RuntimeException $exception) {
            return redirect()->route('admin.dashboard')->with('error', $exception->getMessage());
        }
    }

    private function registroSupabase(string $tabla, string $id, string $select): array
    {
        $respuesta = $this->supabase()->get($this->restUrl($tabla), [
            'select' => $select, 'id' => 'eq.' . $id, 'limit' => '1',
        ]);
        if (!$respuesta->successful()) {
            throw new \RuntimeException('No fue posible cargar el registro para editar. Comprueba los permisos de Supabase.');
        }
        $registro = ($respuesta->json() ?? [])[0] ?? null;
        abort_if(!$registro, 404);
        return $registro;
    }

    private function recetaSupabase(string $productoId): array
    {
        $respuesta = $this->supabase()->get($this->restUrl('recetas'), [
            'select' => 'ingrediente_id,cantidad',
            'producto_id' => 'eq.' . $productoId,
        ]);
        if (!$respuesta->successful()) {
            throw new \RuntimeException('No se pudo consultar la receta del platillo.');
        }
        return $respuesta->json() ?? [];
    }

    private function camposProducto(array $datos): array
    {
        return [
            'nombre' => $datos['nombre'],
            'categoria_id' => $datos['categoria_id'] ?? null,
            'descripcion' => $datos['descripcion'] ?? null,
            'precio' => $datos['precio'],
            'imagen_url' => $datos['imagen_url'] ?? null,
            'tiempo_preparacion_min' => $datos['tiempo_preparacion_min'] ?? null,
        ];
    }

    private function filasReceta(string $productoId, array $receta): array
    {
        return array_map(fn (array $linea) => [
            'producto_id' => $productoId,
            'ingrediente_id' => $linea['ingrediente_id'],
            'cantidad' => $linea['cantidad'],
        ], $receta);
    }

    private function restaurarProducto(string $id, array $producto, array $receta): void
    {
        $this->supabase()->patch($this->restUrl('productos') . '?id=eq.' . urlencode($id), [
            'nombre' => $producto['nombre'],
            'categoria_id' => $producto['categoria_id'] ?? null,
            'descripcion' => $producto['descripcion'] ?? null,
            'precio' => $producto['precio'],
            'imagen_url' => $producto['imagen_url'] ?? null,
            'tiempo_preparacion_min' => $producto['tiempo_preparacion_min'] ?? null,
        ]);
        $this->supabase()->send('DELETE', $this->restUrl('recetas'), [
            'query' => $this->filtroIdProducto($id),
        ]);
        if ($receta) {
            $this->supabase()->post($this->restUrl('recetas'), $this->filasReceta($id, $receta));
        }
    }

    private function filtroIdProducto(string $id): array
    {
        return ['producto_id' => 'eq.' . $id];
    }

    private function actualizar(Request $request, string $tabla, string $id, array $datos, string $mensaje, string $ruta)
    {
        try {
            $respuesta = $this->supabase()->withHeaders(['Prefer' => 'return=representation'])
                ->patch($this->restUrl($tabla) . '?id=eq.' . urlencode($id), $datos);
            if (!$respuesta->successful()) {
                return $this->errorPostgrest($request, $respuesta, $tabla);
            }
            if (empty($respuesta->json())) {
                return back()->withInput()->with('error', 'No se encontró el registro o Supabase no permitió actualizarlo.');
            }
            return redirect()->route($ruta)->with('success', $mensaje);
        } catch (ConnectionException $exception) {
            report($exception);
            return back()->withInput()->with('error', 'No se pudo conectar con Supabase.');
        } catch (\RuntimeException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }
    }

    private function cambiarEstado(Request $request, string $tabla, string $id, string $columna, string $ruta, ?string $bloqueoEstado = null)
    {
        $datos = $request->validate(['activo' => ['required', 'boolean']]);
        try {
            if ($bloqueoEstado) {
                $registro = $this->registroSupabase($tabla, $id, 'id,estado');
                if (!$datos['activo'] && ($registro['estado'] ?? '') !== 'libre') {
                    return back()->with('error', 'Solo puedes archivar una mesa cuando esté libre.');
                }
            }
            $respuesta = $this->supabase()->withHeaders(['Prefer' => 'return=representation'])
                ->patch($this->restUrl($tabla) . '?id=eq.' . urlencode($id), [
                $columna => (bool) $datos['activo'],
            ]);
            if (!$respuesta->successful()) {
                return $this->errorPostgrest($request, $respuesta, $tabla);
            }
            if (empty($respuesta->json())) {
                return back()->with('error', 'No se encontró el registro o Supabase no permitió actualizarlo.');
            }
            return redirect()->route($ruta)->with('success', $datos['activo'] ? 'Registro activado.' : 'Registro archivado.');
        } catch (ConnectionException $exception) {
            report($exception);
            return back()->with('error', 'No se pudo conectar con Supabase.');
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    private function supabase()
    {
        $token = session('supabase_access_token');
        $key = config('services.supabase.key');
        $baseUrl = rtrim((string) config('services.supabase.url'), '/');

        if (!$token || !$key || !filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            throw new \RuntimeException('La sesión de Supabase no está disponible. Cierra sesión e inicia nuevamente.');
        }

        return Http::acceptJson()
            ->withToken($token)
            ->withHeaders(['apikey' => $key])
            ->connectTimeout(5)
            ->timeout(15);
    }

    private function restBase(): string
    {
        return rtrim((string) config('services.supabase.url'), '/') . '/rest/v1/';
    }

    private function restUrl(string $path): string
    {
        return $this->restBase() . $path;
    }
}
