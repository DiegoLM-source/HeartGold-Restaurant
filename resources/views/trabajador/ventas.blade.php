@extends('layouts.trabajador')

@section('title', 'Ventas y pagos')
@section('heading', 'Ventas y pagos')

@section('content')
<section class="worker-welcome">
    <div><span class="eyebrow">OPERACIÓN DEL RESTAURANTE</span><h2>Ventas y pagos</h2><p>Registra ventas de pedidos entregados, consulta pagos y revisa sesiones abiertas.</p></div>
    <div class="welcome-decoration" aria-hidden="true">$</div>
</section>

<nav class="worker-period-filter" aria-label="Filtrar ventas por periodo">
    @foreach (['dia' => 'Hoy', 'semana' => 'Esta semana', 'mes' => 'Este mes', 'tres_meses' => 'Últimos 3 meses'] as $valor => $etiqueta)
        <a href="{{ route('trabajador.seccion', ['seccion' => 'ventas', 'periodo' => $valor]) }}" class="{{ $periodo === $valor ? 'active' : '' }}" @if ($periodo === $valor) aria-current="page" @endif>{{ $etiqueta }}</a>
    @endforeach
</nav>

@if (session('success'))<div class="worker-flash worker-flash-success">{{ session('success') }}</div>@endif
@if (session('error'))<div class="worker-flash worker-flash-error">{{ session('error') }}</div>@endif
@if ($error)<div class="worker-dashboard-alert" role="alert">{{ $error }}</div>@endif

<section class="worker-metrics worker-sales-metrics">
    <article><span>Ventas registradas</span><strong>${{ number_format($totalVentas, 0, ',', '.') }}</strong></article>
    <article><span>Pagos recibidos</span><strong>${{ number_format($totalPagado, 0, ',', '.') }}</strong></article>
    <article><span>Pedidos entregados del periodo</span><strong>{{ count($pedidos) }}</strong></article>
    <article><span>Periodo consultado</span><strong class="worker-period-value">{{ $inicio->format('d/m/Y') }} – {{ $fin->subSecond()->format('d/m/Y') }}</strong></article>
</section>

<section class="worker-data-panel worker-sales-panel">
    <div class="worker-panel-heading"><div><h2>Pedidos entregados y ventas</h2><p>El registro de venta y los pagos se procesan mediante las funciones autorizadas de Supabase.</p></div></div>
    @if (count($pedidos))
        @foreach ($pedidos as $pedido)
            @php($venta = $pedido['venta'] ?? null)
            <article class="worker-sale-card">
                <header class="worker-sale-heading">
                    <div><span class="eyebrow">PEDIDO ENTREGADO</span><h3>#{{ substr($pedido['id'], 0, 8) }}</h3></div>
                    <span class="worker-sale-state {{ $venta ? 'is-registered' : 'is-pending' }}">{{ $venta ? str_replace('_', ' ', ucfirst($venta['estado'])) : 'Sin venta registrada' }}</span>
                </header>
                <div class="worker-sale-meta">
                    <span>{{ !empty($pedido['creado_en']) ? \Illuminate\Support\Carbon::parse($pedido['creado_en'])->setTimezone('America/Bogota')->format('d/m/Y H:i') : 'Fecha no disponible' }} · fecha del pedido</span>
                    <span>{{ $pedido['cliente']['full_name'] ?? 'Cliente' }}</span>
                    <span>{{ $pedido['mesa']['nombre'] ?? 'Sin mesa' }}</span>
                    <strong>${{ number_format((float) ($venta['total'] ?? $pedido['total'] ?? $pedido['subtotal'] ?? 0), 0, ',', '.') }}</strong>
                </div>

                @if (!$venta)
                    <form method="POST" action="{{ route('trabajador.ventas.registrar') }}" class="worker-inline-form">
                        @csrf<input type="hidden" name="pedido_id" value="{{ $pedido['id'] }}">
                        <button type="submit" class="worker-primary-link">Registrar venta</button>
                    </form>
                @else
                    <div class="worker-payment-history">
                        <h4>Pagos</h4>
                        @forelse ($venta['pagos'] ?? [] as $pago)
                            <div class="worker-payment-row">
                                <span>{{ !empty($pago['creado_en']) ? \Illuminate\Support\Carbon::parse($pago['creado_en'])->setTimezone('America/Bogota')->format('d/m/Y H:i') : 'Fecha no disponible' }}</span>
                                <span>{{ $pago['nombre_metodo'] }}</span>
                                <span>{{ ucfirst($pago['estado']) }}</span>
                                <strong>${{ number_format((int) $pago['monto'], 0, ',', '.') }}</strong>
                                @if (!empty($pago['referencia']))<small>Ref. {{ $pago['referencia'] }}</small>@endif
                            </div>
                        @empty
                            <p class="worker-muted">No hay pagos registrados para esta venta.</p>
                        @endforelse
                    </div>

                    @if (!in_array($venta['estado'], ['pagado', 'anulado'], true) && $venta['saldo'] > 0)
                        <form method="POST" action="{{ route('trabajador.ventas.registrar-pago', $venta['id']) }}" class="worker-payment-form">
                            @csrf
                            <label>Método de pago
                                <select name="metodo_pago_id" required>
                                    <option value="">Selecciona un método</option>
                                    @foreach ($metodosPago as $metodo)<option value="{{ $metodo['id'] }}">{{ $metodo['nombre'] }}</option>@endforeach
                                </select>
                            </label>
                            <label>Monto pendiente ($)
                                <input type="number" name="monto" min="1" max="{{ $venta['saldo'] }}" step="1" value="{{ $venta['saldo'] }}" required>
                            </label>
                            <label>Referencia <span>(opcional)</span>
                                <input type="text" name="referencia" maxlength="255">
                            </label>
                            <button type="submit" class="worker-primary-link" @disabled(empty($metodosPago))>Registrar pago</button>
                        </form>
                        <p class="worker-payment-balance">Saldo pendiente: <strong>${{ number_format($venta['saldo'], 0, ',', '.') }}</strong></p>
                    @endif
                @endif
            </article>
        @endforeach
    @else
        <div class="worker-empty-state"><h3>No hay pedidos entregados en este periodo</h3><p>Prueba con otro intervalo para consultar ventas registradas.</p></div>
    @endif
</section>

<section class="worker-data-panel worker-open-sessions">
    <div class="worker-panel-heading"><div><h2>Sesiones abiertas</h2><p>Supabase solo cerrará la sesión si los pedidos y todas las ventas están completos y pagados.</p></div></div>
    @forelse ($sesionesActivas as $sesion)
        <article class="worker-session-row">
            <div><strong>{{ $sesion['mesa']['nombre'] ?? 'Mesa' }}</strong><span>{{ $sesion['mesa']['zona'] ?? '' }} · Iniciada {{ !empty($sesion['iniciada_en']) ? \Illuminate\Support\Carbon::parse($sesion['iniciada_en'])->setTimezone('America/Bogota')->format('d/m/Y H:i') : '—' }}</span></div>
            <form method="POST" action="{{ route('trabajador.sesiones.cerrar', $sesion['id']) }}">
                @csrf<button type="submit" class="worker-secondary-button">Cerrar sesión</button>
            </form>
        </article>
    @empty
        <div class="worker-empty-inline">No hay sesiones abiertas.</div>
    @endforelse
</section>
@endsection
