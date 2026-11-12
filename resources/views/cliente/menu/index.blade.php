@extends('layouts.cliente')

@section('title', 'Menú | Restaurante')
@section('heading', 'Menú')

@section('content')
<div class="cliente-page-heading">
    <div><span class="cliente-eyebrow">CARTA DEL RESTAURANTE</span><h2>Nuestro menú</h2><p>Productos, precios y disponibilidad actualizados desde el restaurante.</p></div>
    <a class="cliente-action-link" href="{{ route('cliente.carrito.index') }}">Ver carrito</a>
</div>

@if ($error)<div class="cliente-alert cliente-alert-error">{{ $error }}</div>@endif

<form method="GET" action="{{ route('cliente.menu.index') }}" class="cliente-menu-filter">
    <label for="buscar">Buscar producto</label>
    <input id="buscar" name="buscar" value="{{ $busqueda }}" maxlength="100" placeholder="Nombre del producto">
    <label for="categoria">Categoría</label>
    <select id="categoria" name="categoria">
        <option value="">Todas</option>
        @foreach ($categorias as $categoria)
            <option value="{{ $categoria['id'] }}" @selected($categoriaSeleccionada === $categoria['id'])>{{ $categoria['nombre'] }}</option>
        @endforeach
    </select>
    <button type="submit">Filtrar</button>
</form>

@if (!$sesionActiva)
    <div class="cliente-menu-session-note">Para agregar productos al pedido, primero escanea el QR de una mesa y espera la autorización del personal.</div>
@endif

<div class="cliente-product-grid">
    @forelse ($productos as $producto)
        <article class="cliente-product-card {{ $producto['disponible'] ? '' : 'is-unavailable' }}">
            @if (!empty($producto['imagen_url']))<img src="{{ $producto['imagen_url'] }}" alt="{{ $producto['nombre'] }}" loading="lazy">@endif
            <div class="cliente-product-body">
                @if (!empty($producto['categoria']['nombre']))<span class="cliente-product-category">{{ $producto['categoria']['nombre'] }}</span>@endif
                <h3>{{ $producto['nombre'] }}</h3>
                @if (!empty($producto['descripcion']))<p>{{ $producto['descripcion'] }}</p>@endif
                <div class="cliente-product-footer"><strong>${{ number_format((int) $producto['precio'], 0, ',', '.') }}</strong><span class="{{ $producto['disponible'] ? 'is-available' : 'is-unavailable-label' }}">{{ $producto['disponible'] ? 'Disponible · ' . $producto['porciones'] . ' porciones' : 'No disponible' }}</span></div>
                @if ($sesionActiva && $producto['disponible'])
                    <form method="POST" action="{{ route('cliente.carrito.agregar') }}" class="cliente-add-cart-form" data-disable-on-submit>
                        @csrf<input type="hidden" name="producto_id" value="{{ $producto['id'] }}">
                        <label>Cantidad <input type="number" name="cantidad" value="1" min="1" max="{{ min(2147483647, (int) $producto['porciones']) }}" required></label>
                        <button type="submit">Agregar</button>
                    </form>
                @endif
            </div>
        </article>
    @empty
        <div class="cliente-empty-state"><h3>No hay productos para mostrar</h3><p>Prueba otra búsqueda o categoría.</p></div>
    @endforelse
</div>
@endsection
