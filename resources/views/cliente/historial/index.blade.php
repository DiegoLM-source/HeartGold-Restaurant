@extends('layouts.cliente')

@section('title', 'Mi historial | Restaurante')
@section('heading', 'Mi historial')

@section('content')
<div class="cliente-page-heading">
    <div><span class="cliente-eyebrow">MI CUENTA</span><h2>Visitas anteriores</h2><p>Consulta las sesiones vinculadas a tu cuenta.</p></div>
    <a class="cliente-action-link" href="{{ route('cliente.pedidos.index') }}">Ver mis pedidos</a>
</div>

@if ($error)<div class="cliente-alert cliente-alert-error">{{ $error }}</div>@endif

<div class="cliente-order-list">
    @forelse ($sesiones as $sesion)
        <article class="cliente-order-card">
            <header>
                <div><span class="cliente-eyebrow">VISITA</span><h3>{{ $sesion['mesa']['nombre'] ?? 'Mesa' }}</h3></div>
                <span class="cliente-order-state">{{ !empty($sesion['activa']) ? 'Activa' : 'Finalizada' }}</span>
            </header>
            <p class="cliente-order-meta">
                {{ !empty($sesion['iniciada_en']) ? \Illuminate\Support\Carbon::parse($sesion['iniciada_en'])->setTimezone('America/Bogota')->format('d/m/Y H:i') : 'Fecha no disponible' }}
                @if (!empty($sesion['mesa']['zona'])) · {{ $sesion['mesa']['zona'] }} @endif
                @if (!empty($sesion['finalizada_en'])) · Finalizada {{ \Illuminate\Support\Carbon::parse($sesion['finalizada_en'])->setTimezone('America/Bogota')->format('d/m/Y H:i') }} @endif
            </p>
        </article>
    @empty
        <div class="cliente-empty-state"><h3>Aún no tienes visitas anteriores</h3><p>Las sesiones asociadas a tu cuenta aparecerán aquí.</p></div>
    @endforelse
</div>

<div class="cliente-page-heading cliente-history-orders-heading">
    <div><span class="cliente-eyebrow">CONSUMOS</span><h2>Pedidos y pagos anteriores</h2></div>
    <a class="cliente-action-link" href="{{ route('cliente.pedidos.index') }}">Ver todos mis pedidos</a>
</div>
<div class="cliente-order-list">
    @forelse ($pedidos as $pedido)
        <article class="cliente-order-card">
            <header><div><span class="cliente-eyebrow">PEDIDO</span><h3>#{{ substr($pedido['id'], 0, 8) }}</h3></div><span class="cliente-order-state">{{ str_replace('_', ' ', ucfirst($pedido['estado'] ?? 'sin estado')) }}</span></header>
            <p class="cliente-order-meta">{{ !empty($pedido['creado_en']) ? \Illuminate\Support\Carbon::parse($pedido['creado_en'])->setTimezone('America/Bogota')->format('d/m/Y H:i') : 'Fecha no disponible' }} @if (!empty($pedido['mesa']['nombre'])) · {{ $pedido['mesa']['nombre'] }} @endif</p>
            <ul>@forelse ($pedido['productos'] ?? [] as $producto)<li><span>{{ $producto['cantidad'] }} × {{ $producto['nombre_producto'] }}</span><strong>${{ number_format((int) $producto['subtotal'], 0, ',', '.') }}</strong></li>@empty<li class="cliente-muted">Detalle de productos no disponible.</li>@endforelse</ul>
            <footer><span>Total del pedido</span><strong>${{ number_format((int) $pedido['total'], 0, ',', '.') }}</strong></footer>
            @if (!empty($pedido['venta']))
                <div class="cliente-order-payments"><strong>Venta · {{ ucfirst($pedido['venta']['estado']) }}</strong>
                    @forelse ($pedido['venta']['pagos'] ?? [] as $pago)
                        <p>{{ $pago['nombre_metodo'] }} · {{ ucfirst($pago['estado']) }} · ${{ number_format((int) $pago['monto'], 0, ',', '.') }} @if (!empty($pago['creado_en'])) · {{ \Illuminate\Support\Carbon::parse($pago['creado_en'])->setTimezone('America/Bogota')->format('d/m/Y H:i') }} @endif</p>
                    @empty
                        <p>Sin pagos registrados.</p>
                    @endforelse
                </div>
            @endif
        </article>
    @empty
        <div class="cliente-empty-state"><h3>Aún no tienes pedidos anteriores</h3><p>Los pedidos y sus pagos aparecerán aquí.</p></div>
    @endforelse
</div>
@endsection
