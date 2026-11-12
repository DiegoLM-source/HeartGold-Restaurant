@extends('layouts.cliente')

@section('title', 'Carrito | Restaurante')
@section('heading', 'Mi carrito')

@section('content')
<div class="cliente-page-heading">
    <div><span class="cliente-eyebrow">TU PEDIDO</span><h2>Mi carrito</h2><p>Revisa productos, cantidades y total antes de enviarlo.</p></div>
    <a class="cliente-action-link" href="{{ route('cliente.menu.index') }}">Seguir explorando el menú</a>
</div>

@if (session('success'))<div class="cliente-alert cliente-alert-success">{{ session('success') }}</div>@endif
@if (session('error'))<div class="cliente-alert cliente-alert-error">{{ session('error') }}</div>@endif
@if ($error)<div class="cliente-alert cliente-alert-error">{{ $error }}</div>@endif

@if (!$sesionActiva)<div class="cliente-menu-session-note">El pedido solo se puede enviar después de que el personal autorice una sesión de mesa.</div>@endif

<div class="cliente-cart-layout">
    <section class="cliente-cart-items">
        @forelse ($productos as $producto)
            <article class="cliente-cart-item {{ $producto['disponible'] ? '' : 'is-unavailable' }}">
                <div class="cliente-cart-item-copy">
                    <h3>{{ $producto['nombre'] }}</h3>
                    <span>${{ number_format((int) $producto['precio'], 0, ',', '.') }} por unidad</span>
                    @if (!$producto['disponible'])<strong class="cliente-field-error">Disponibilidad menor a la cantidad o producto inactivo.</strong>@endif
                </div>
                <form method="POST" action="{{ route('cliente.carrito.actualizar', $producto['id']) }}" class="cliente-cart-quantity-form">
                    @csrf @method('PUT')
                    <label for="cantidad-{{ $producto['id'] }}">Cantidad</label>
                    <input id="cantidad-{{ $producto['id'] }}" type="number" name="cantidad" min="1" max="2147483647" value="{{ $producto['cantidad'] }}" required>
                    <button type="submit">Actualizar</button>
                </form>
                <strong class="cliente-cart-line-total">${{ number_format((int) $producto['subtotal'], 0, ',', '.') }}</strong>
                <form method="POST" action="{{ route('cliente.carrito.eliminar', $producto['id']) }}">
                    @csrf @method('DELETE')<button class="cliente-cart-remove" type="submit">Quitar</button>
                </form>
            </article>
        @empty
            <div class="cliente-empty-state"><h3>Tu carrito está vacío</h3><p>Explora el menú y agrega productos disponibles.</p><a class="cliente-action-link" href="{{ route('cliente.menu.index') }}">Ver menú</a></div>
        @endforelse
    </section>

    @if ($productos)
        <aside class="cliente-cart-summary">
            <h3>Resumen</h3>
            <div><span>Productos</span><strong>{{ array_sum(array_column($productos, 'cantidad')) }}</strong></div>
            <div class="cliente-cart-total"><span>Total</span><strong>${{ number_format($subtotal, 0, ',', '.') }}</strong></div>
            <form method="POST" action="{{ route('cliente.carrito.enviar') }}" data-disable-on-submit>
                @csrf
                <label for="observaciones">Observaciones</label>
                <textarea id="observaciones" name="observaciones" rows="3" maxlength="1000">{{ old('observaciones') }}</textarea>
                @error('observaciones')<span class="cliente-field-error">{{ $message }}</span>@enderror
                @error('producto_id')<span class="cliente-field-error">{{ $message }}</span>@enderror
                @error('cantidad')<span class="cliente-field-error">{{ $message }}</span>@enderror
                <button type="submit" @disabled(!$sesionActiva)>Enviar pedido</button>
            </form>
        </aside>
    @endif
</div>
@endsection
