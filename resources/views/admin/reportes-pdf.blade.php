<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 32px 38px; }
        body { color: #29251f; font-family: "DejaVu Sans", sans-serif; font-size: 10px; }
        h1, h2, p { margin: 0; }
        h1 { color: #3d4932; font-size: 22px; }
        h2 { margin: 20px 0 8px; color: #3d4932; font-size: 13px; }
        .muted { margin-top: 6px; color: #817b70; }
        .summary { width: 100%; margin-top: 20px; border-collapse: collapse; }
        .summary td { width: 25%; padding: 10px; border: 1px solid #e9e5da; vertical-align: top; }
        .summary span { display: block; color: #817b70; font-size: 8px; }
        .summary strong { display: block; margin-top: 5px; font-size: 13px; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th, table.data td { padding: 7px; border-bottom: 1px solid #e9e5da; text-align: left; }
        table.data th { background: #f5f3ec; color: #817b70; font-size: 8px; }
        table.data td:last-child, table.data th:last-child { text-align: right; }
        .note { margin-top: 18px; padding: 10px; background: #f5f3ec; color: #5b584e; line-height: 1.5; }
    </style>
</head>
<body>
    <h1>Reporte de ventas y mesas</h1>
    <p class="muted">La Mesa · Periodo del {{ \Illuminate\Support\Carbon::parse($desde)->format('d/m/Y') }} al {{ \Illuminate\Support\Carbon::parse($hasta)->format('d/m/Y') }} · {{ $mesaSeleccionada ? $mesaSeleccionada['nombre'] : 'Todas las mesas' }}</p>

    <table class="summary">
        <tr>
            <td><span>Ventas registradas</span><strong>${{ number_format($ventas, 0, ',', '.') }}</strong><span>{{ $ventasRegistradas }} ventas</span></td>
            <td><span>Pagos recibidos</span><strong>${{ number_format($pagosRecibidos, 0, ',', '.') }}</strong></td>
            <td><span>Saldo pendiente</span><strong>${{ number_format($saldoPendiente, 0, ',', '.') }}</strong></td>
            <td><span>Ticket promedio</span><strong>${{ number_format($ticketPromedio, 0, ',', '.') }}</strong></td>
        </tr>
    </table>

    <h2>Utilidad bruta estimada</h2>
    <p>{{ $gananciaEstimada !== null ? '$' . number_format($gananciaEstimada, 0, ',', '.') : 'Sin datos suficientes para estimarla' }} · Cobertura de costos: {{ number_format($coberturaCostos, 0) }}%</p>

    <h2>Ventas por día</h2>
    <table class="data">
        <thead><tr><th>Fecha</th><th>Monto</th></tr></thead>
        <tbody>
            @forelse ($ventasPorDia as $dia)
                <tr><td>{{ \Illuminate\Support\Carbon::parse($dia['fecha'])->format('d/m/Y') }}</td><td>${{ number_format($dia['monto'], 0, ',', '.') }}</td></tr>
            @empty
                <tr><td colspan="2">No hay ventas registradas para este periodo.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Pagos por método</h2>
    <table class="data">
        <thead><tr><th>Método de pago</th><th>Monto recibido</th></tr></thead>
        <tbody>
            @forelse ($ventasPorMetodo as $metodo)
                <tr><td>{{ $metodo['nombre'] }}</td><td>${{ number_format($metodo['monto'], 0, ',', '.') }}</td></tr>
            @empty
                <tr><td colspan="2">No hay pagos confirmados en este periodo.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Productos más vendidos</h2>
    <table class="data">
        <thead><tr><th>Producto</th><th>Unidades</th><th>Subtotal</th></tr></thead>
        <tbody>
            @forelse ($productosMasVendidos as $producto)
                <tr><td>{{ $producto['nombre'] }}</td><td>{{ number_format($producto['cantidad'], 0, ',', '.') }}</td><td>${{ number_format($producto['subtotal'], 0, ',', '.') }}</td></tr>
            @empty
                <tr><td colspan="3">No hay detalle de productos para mostrar.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Resumen por mesa</h2>
    <table class="data">
        <thead><tr><th>Mesa</th><th>Pedidos</th><th>Ventas</th><th>Pagos recibidos</th><th>Saldo</th></tr></thead>
        <tbody>
            @forelse ($reporteMesas as $mesaReporte)
                <tr>
                    <td>{{ $mesaReporte['nombre'] }}@if ($mesaReporte['zona']) · {{ $mesaReporte['zona'] }} @endif</td>
                    <td>{{ $mesaReporte['pedidosRegistrados'] }}</td>
                    <td>${{ number_format($mesaReporte['ventas'], 0, ',', '.') }}</td>
                    <td>${{ number_format($mesaReporte['pagosRecibidos'], 0, ',', '.') }}</td>
                    <td>${{ number_format($mesaReporte['saldoPendiente'], 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="5">No hay mesas registradas para mostrar.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Historial de pedidos por mesa</h2>
    @forelse ($reporteMesas as $mesaReporte)
        <h2>{{ $mesaReporte['nombre'] }} · {{ $mesaReporte['pedidosRegistrados'] }} pedidos</h2>
        <table class="data">
            <thead><tr><th>Fecha / pedido</th><th>Estado y productos</th><th>Pago</th><th>Total</th></tr></thead>
            <tbody>
                @forelse ($mesaReporte['historial'] as $pedido)
                    <tr>
                        <td>{{ !empty($pedido['fecha']) ? \Illuminate\Support\Carbon::parse($pedido['fecha'])->setTimezone('America/Bogota')->format('d/m/Y H:i') : '—' }}<br>#{{ substr($pedido['id'], 0, 8) }}</td>
                        <td>{{ str_replace('_', ' ', ucfirst($pedido['estado'])) }}<br>
                            @forelse ($pedido['productos'] as $producto)
                                {{ number_format((float) ($producto['cantidad'] ?? 0), 0, ',', '.') }} × {{ $producto['nombre_producto'] ?? 'Producto sin nombre' }}@if (!$loop->last)<br>@endif
                            @empty
                                Sin productos detallados
                            @endforelse
                        </td>
                        <td>Recibido: ${{ number_format($pedido['pagosRecibidos'], 0, ',', '.') }}</td>
                        <td>${{ number_format($pedido['total'], 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4">No hay pedidos para esta mesa en el periodo.</td></tr>
                @endforelse
            </tbody>
        </table>
    @empty
        <p>No hay mesas registradas para mostrar.</p>
    @endforelse

    <p class="note">El periodo agrupa los pedidos entregados según su fecha de creación. Las ventas excluyen registros anulados. Los pagos indicados corresponden a esas ventas y solo cuentan si están confirmados. La utilidad se estima con recetas y costos actuales disponibles (cobertura {{ number_format($coberturaCostos, 0) }}%); puede diferir del resultado contable.</p>
</body>
</html>
