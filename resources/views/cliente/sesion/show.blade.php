@extends('layouts.cliente')

@section('title', 'Mi sesión | Restaurante')
@section('heading', 'Mi sesión')

@section('content')
<div class="cliente-page-heading">
    <div><span class="cliente-eyebrow">VISITA ACTUAL</span><h2>Tu sesión</h2><p>Consulta la mesa y los pedidos de tu visita activa.</p></div>
</div>

@if ($error)<div class="cliente-alert cliente-alert-error">{{ $error }}</div>@endif

@if ($sesion)
    <section class="cliente-active-session">
        <div class="cliente-session-heading"><div><span class="cliente-eyebrow">MESA ASIGNADA</span><h2>{{ $mesa['nombre'] ?? 'Mesa' }}</h2></div><span class="cliente-session-badge">Sesión activa</span></div>
        @if ($mesa)
            <div class="cliente-session-details">
                <div><span>Capacidad</span><strong>{{ $mesa['capacidad'] }} personas</strong></div>
                <div><span>Zona</span><strong>{{ $mesa['zona'] ?? 'Sin especificar' }}</strong></div>
                <div><span>Inicio</span><strong>{{ !empty($sesion['iniciada_en']) ? \Illuminate\Support\Carbon::parse($sesion['iniciada_en'])->setTimezone('America/Bogota')->format('d/m/Y H:i') : '—' }}</strong></div>
            </div>
        @endif
    </section>

    <section class="cliente-order-list">
        <div class="cliente-page-heading"><div><span class="cliente-eyebrow">CONSUMO</span><h2>Pedidos de esta sesión</h2></div><a class="cliente-action-link" href="{{ route('cliente.pedidos.index') }}">Ver historial de pedidos</a></div>
        @forelse ($pedidos as $pedido)
            <article class="cliente-order-card">
                <header><div><span class="cliente-eyebrow">PEDIDO</span><h3>#{{ substr($pedido['id'], 0, 8) }}</h3></div><span class="cliente-order-state">{{ str_replace('_', ' ', ucfirst($pedido['estado'] ?? 'sin estado')) }}</span></header>
                <p class="cliente-order-meta">{{ !empty($pedido['creado_en']) ? \Illuminate\Support\Carbon::parse($pedido['creado_en'])->setTimezone('America/Bogota')->format('d/m/Y H:i') : 'Fecha no disponible' }}</p>
                @if (!empty($pedido['observaciones']))<p class="cliente-order-notes">{{ $pedido['observaciones'] }}</p>@endif
                <ul>@forelse ($pedido['productos'] ?? [] as $producto)<li><span>{{ $producto['cantidad'] }} × {{ $producto['nombre_producto'] }}</span><strong>${{ number_format((int) $producto['subtotal'], 0, ',', '.') }}</strong></li>@empty<li class="cliente-muted">Detalle de productos no disponible.</li>@endforelse</ul>
                <footer><span>Total registrado</span><strong>${{ number_format((float) ($pedido['total'] ?? 0), 0, ',', '.') }}</strong></footer>
            </article>
        @empty
            <div class="cliente-empty-state"><h3>Aún no hay pedidos en esta sesión</h3><p>Cuando tengas pedidos, aparecerán aquí.</p></div>
        @endforelse
    </section>
@elseif (!$error)
    <div class="cliente-empty-state"><h3>No tienes una sesión activa</h3><p>Escanea el código QR permanente de una mesa y solicita acceso al personal.</p></div>
@endif
@endsection
