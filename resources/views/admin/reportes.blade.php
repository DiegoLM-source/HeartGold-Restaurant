@extends('layouts.admin')

@section('title', 'Reportes | Administración')

@section('content')
    <section class="admin-hero admin-reports-hero">
        <div>
            <span class="admin-eyebrow">RESULTADOS DEL RESTAURANTE</span>
            <h1>Conoce el ritmo<br><em>de tu negocio.</em></h1>
            <p>Ventas, pagos, productos destacados e historial de pedidos de cada mesa.</p>
        </div>
        <div class="admin-hero-art" aria-hidden="true">
            <span class="admin-art-sun"></span>
            <span class="admin-art-plate"><i></i><b></b><small></small></span>
            <span class="admin-art-leaf leaf-one"></span>
            <span class="admin-art-leaf leaf-two"></span>
        </div>
    </section>

    @if (session('error') || $error)
        <div class="admin-alert admin-alert-error" role="alert">{{ session('error') ?? $error }}</div>
    @endif
    @if ($errors->any())
        <div class="admin-alert admin-alert-error" role="alert">
            <ul>@foreach ($errors->all() as $errorItem)<li>{{ $errorItem }}</li>@endforeach</ul>
        </div>
    @endif

    <section class="admin-report-filter">
        <form method="GET" action="{{ route('admin.reportes') }}">
            <label>Desde<input type="date" name="desde" value="{{ $desde }}" required></label>
            <label>Hasta<input type="date" name="hasta" value="{{ $hasta }}" required></label>
            <label>Mesa
                <select name="mesa_id">
                    <option value="">Todas las mesas</option>
                    @foreach ($mesas as $mesa)
                        <option value="{{ $mesa['id'] }}" @selected($mesaId === $mesa['id'])>{{ $mesa['nombre'] }}@if (!empty($mesa['zona'])) · {{ $mesa['zona'] }} @endif</option>
                    @endforeach
                </select>
            </label>
            <button class="admin-submit" type="submit">Aplicar periodo <span>→</span></button>
        </form>
        <a class="admin-report-download" href="{{ route('admin.reportes.pdf', ['desde' => $desde, 'hasta' => $hasta, 'mesa_id' => $mesaId]) }}">↓ Descargar PDF</a>
    </section>

    @unless ($error)
        <section class="admin-stats admin-report-stats" aria-label="Indicadores de ventas">
            <article class="admin-stat-card"><span class="admin-stat-icon icon-olive">$</span><div><small>Ventas registradas</small><strong>${{ number_format($ventas, 0, ',', '.') }}</strong></div><span class="admin-stat-note">{{ $ventasRegistradas }} ventas</span></article>
            <article class="admin-stat-card"><span class="admin-stat-icon icon-gold">↗</span><div><small>Pagos recibidos</small><strong>${{ number_format($pagosRecibidos, 0, ',', '.') }}</strong></div><span class="admin-stat-note">de estas ventas</span></article>
            <article class="admin-stat-card"><span class="admin-stat-icon icon-rose">◷</span><div><small>Saldo pendiente</small><strong>${{ number_format($saldoPendiente, 0, ',', '.') }}</strong></div><span class="admin-stat-note">por cobrar</span></article>
            <article class="admin-stat-card"><span class="admin-stat-icon icon-olive">✦</span><div><small>Ticket promedio</small><strong>${{ number_format($ticketPromedio, 0, ',', '.') }}</strong></div><span class="admin-stat-note">por venta</span></article>
        </section>

        <section class="admin-report-profit">
            <div><span class="admin-eyebrow">MARGEN DE PRODUCTO</span><h2>Utilidad bruta estimada</h2><p>Calculada con el costo actual de recetas e ingredientes; no representa un costo histórico.</p></div>
            <strong>@if ($gananciaEstimada !== null) ${{ number_format($gananciaEstimada, 0, ',', '.') }} @else Sin datos suficientes @endif</strong>
            <small>Cobertura de costos: {{ number_format($coberturaCostos, 0) }}%</small>
        </section>

        <section class="admin-report-grid">
            <article class="admin-list-card">
                <div class="admin-list-heading"><div><h3>Ventas por día</h3><p>Según fecha de creación de pedidos entregados</p></div><span>{{ count($ventasPorDia) }} días</span></div>
                <div class="admin-report-list">
                    @php($maxVentaDia = max(1, max(array_column($ventasPorDia, 'monto') ?: [1])))
                    @forelse ($ventasPorDia as $dia)
                        <div class="admin-report-bar-row"><span>{{ \Illuminate\Support\Carbon::parse($dia['fecha'])->format('d/m') }}</span><div><i style="width: {{ max(2, $dia['monto'] / $maxVentaDia * 100) }}%"></i></div><strong>${{ number_format($dia['monto'], 0, ',', '.') }}</strong></div>
                    @empty
                        <p class="admin-report-empty">No hay ventas registradas para este periodo.</p>
                    @endforelse
                </div>
            </article>
            <article class="admin-list-card">
                <div class="admin-list-heading"><div><h3>Pagos por método</h3><p>Pagos confirmados de estas ventas</p></div></div>
                <div class="admin-report-list">
                    @forelse ($ventasPorMetodo as $metodo)
                        <div class="admin-report-data-row"><span>{{ $metodo['nombre'] }}</span><strong>${{ number_format($metodo['monto'], 0, ',', '.') }}</strong></div>
                    @empty
                        <p class="admin-report-empty">No hay pagos confirmados en este periodo.</p>
                    @endforelse
                </div>
            </article>
        </section>

        <section class="admin-section-heading">
            <div><span class="admin-eyebrow">PREFERENCIAS</span><h2>Productos más vendidos</h2><p>Ordenados por unidades y con el subtotal vendido del periodo.</p></div>
            <span class="admin-section-count">Top {{ count($productosMasVendidos) }}</span>
        </section>
        <section class="admin-list-card">
            <div class="admin-list-heading"><div><h3>Platillos destacados</h3><p>Solo pedidos con una venta activa</p></div></div>
            <div class="admin-report-list">
                @forelse ($productosMasVendidos as $producto)
                    <div class="admin-report-data-row"><span>{{ $loop->iteration }}. {{ $producto['nombre'] }} <small>{{ number_format($producto['cantidad'], 0, ',', '.') }} unidades</small></span><strong>${{ number_format($producto['subtotal'], 0, ',', '.') }}</strong></div>
                @empty
                    <p class="admin-report-empty">No hay detalle de productos para mostrar.</p>
                @endforelse
            </div>
        </section>
        <section class="admin-section-heading">
            <div>
                <span class="admin-eyebrow">SERVICIO POR MESA</span>
                <h2>{{ $mesaSeleccionada ? 'Historial de ' . $mesaSeleccionada['nombre'] : 'Mesas e historial de pedidos' }}</h2>
                <p>Consulta los pedidos, estados, productos y pagos del periodo seleccionado.</p>
            </div>
            <span class="admin-section-count">{{ count($reporteMesas) }} mesas</span>
        </section>
        <section class="admin-mesa-reports" aria-label="Reportes e historial por mesa">
            @forelse ($reporteMesas as $mesaReporte)
                <details class="admin-mesa-history" @if ($mesaId === $mesaReporte['id']) open @endif>
                    <summary>
                        <span class="admin-mesa-name"><strong>{{ $mesaReporte['nombre'] }}</strong><small>{{ $mesaReporte['zona'] ?: 'Sin zona' }} · {{ $mesaReporte['pedidosRegistrados'] }} pedidos</small></span>
                        <span><small>Ventas</small><strong>${{ number_format($mesaReporte['ventas'], 0, ',', '.') }}</strong></span>
                        <span><small>Pagos recibidos</small><strong>${{ number_format($mesaReporte['pagosRecibidos'], 0, ',', '.') }}</strong></span>
                        <span><small>Saldo pendiente</small><strong>${{ number_format($mesaReporte['saldoPendiente'], 0, ',', '.') }}</strong></span>
                        <span class="admin-mesa-expand" aria-hidden="true">⌄</span>
                    </summary>
                    <div class="admin-mesa-orders">
                        @forelse ($mesaReporte['historial'] as $pedido)
                            <article class="admin-mesa-order">
                                <header>
                                    <div><strong>Pedido #{{ substr($pedido['id'], 0, 8) }}</strong><small>{{ !empty($pedido['fecha']) ? \Illuminate\Support\Carbon::parse($pedido['fecha'])->setTimezone('America/Bogota')->format('d/m/Y H:i') : 'Fecha no disponible' }} · {{ str_replace('_', ' ', ucfirst($pedido['estado'])) }}</small></div>
                                    <strong>${{ number_format($pedido['total'], 0, ',', '.') }}</strong>
                                </header>
                                @if (!empty($pedido['productos']))
                                    <ul>
                                        @foreach ($pedido['productos'] as $producto)
                                            <li>{{ number_format((float) ($producto['cantidad'] ?? 0), 0, ',', '.') }} × {{ $producto['nombre_producto'] ?? 'Producto sin nombre' }} <span>${{ number_format((float) ($producto['subtotal'] ?? 0), 0, ',', '.') }}</span></li>
                                        @endforeach
                                    </ul>
                                @else
                                    <p class="admin-report-empty">Este pedido no tiene productos detallados.</p>
                                @endif
                                <small class="admin-mesa-payment">
                                    @if ($pedido['venta'])
                                        Pagado: ${{ number_format($pedido['pagosRecibidos'], 0, ',', '.') }} · Saldo: ${{ number_format(max(0, (float) $pedido['venta']['total'] - $pedido['pagosRecibidos']), 0, ',', '.') }}
                                    @else
                                        Sin venta registrada
                                    @endif
                                    @if (!empty($pedido['sesion_id'])) · Sesión #{{ substr($pedido['sesion_id'], 0, 8) }} @endif
                                </small>
                                @if (!empty($pedido['observaciones']))<p class="admin-mesa-notes">Nota: {{ $pedido['observaciones'] }}</p>@endif
                            </article>
                        @empty
                            <p class="admin-report-empty">No hay pedidos para esta mesa en el periodo.</p>
                        @endforelse
                    </div>
                </details>
            @empty
                <div class="admin-list-card"><p class="admin-report-empty">No hay mesas registradas para mostrar.</p></div>
            @endforelse
        </section>
        <p class="admin-report-disclaimer">El periodo agrupa los pedidos entregados según su fecha de creación. Las ventas excluyen registros anulados y los pagos solo cuentan si están confirmados. La utilidad se estima con recetas y costos actuales disponibles y puede diferir del resultado contable.</p>
    @endunless
    <footer class="admin-footer">La Mesa <span>·</span> Administración del restaurante</footer>
@endsection
