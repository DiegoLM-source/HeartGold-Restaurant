@extends('layouts.cliente')

@section('title', 'Mis pedidos | Restaurante')
@section('heading', 'Mis pedidos')

@section('content')
<div class="cliente-page-heading">
    <div><span class="cliente-eyebrow">MI EXPERIENCIA</span><h2>Mis pedidos</h2><p>Consulta el estado y el detalle de tus pedidos.</p></div>
    <a class="cliente-action-link" href="{{ route('cliente.dashboard') }}">Volver al inicio</a>
</div>

@if ($error)
    <div class="cliente-alert cliente-alert-error">{{ $error }}</div>
@endif
@if (session('success'))<div class="cliente-alert cliente-alert-success">{{ session('success') }}</div>@endif

<div class="cliente-order-list">
    @forelse ($pedidos as $pedido)
        <article class="cliente-order-card">
            <header>
                <div><span class="cliente-eyebrow">PEDIDO</span><h3>#{{ substr($pedido['id'], 0, 8) }}</h3></div>
                <span class="cliente-order-state">{{ str_replace('_', ' ', ucfirst($pedido['estado'] ?? 'sin estado')) }}</span>
            </header>
            <p class="cliente-order-meta">
                {{ !empty($pedido['creado_en']) ? \Illuminate\Support\Carbon::parse($pedido['creado_en'])->setTimezone('America/Bogota')->format('d/m/Y H:i') : 'Fecha no disponible' }}
                @if (!empty($pedido['nombre_mesa'])) · {{ $pedido['nombre_mesa'] }} @endif
            </p>
            @if (!empty($pedido['observaciones']))<p class="cliente-order-notes">{{ $pedido['observaciones'] }}</p>@endif
            <ul>
                @forelse ($pedido['productos'] ?? [] as $producto)
                    <li><span>{{ $producto['cantidad'] }} × {{ $producto['nombre_producto'] }}</span><strong>${{ number_format((float) ($producto['subtotal'] ?? 0), 0, ',', '.') }}</strong></li>
                @empty
                    <li class="cliente-muted">No hay detalle disponible para este pedido.</li>
                @endforelse
            </ul>
            <footer><span>Total</span><strong>${{ number_format((float) ($pedido['total'] ?? $pedido['subtotal'] ?? 0), 0, ',', '.') }}</strong></footer>
        </article>
    @empty
        <div class="cliente-empty-state"><h3>Aún no tienes pedidos</h3><p>Los pedidos asociados a tu cuenta aparecerán aquí.</p></div>
    @endforelse
</div>
@endsection
