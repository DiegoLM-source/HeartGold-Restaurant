@extends('layouts.admin')

@section('title', 'Administración | La Mesa')

@section('content')
    <section class="admin-hero" id="resumen">
        <div>
            <span class="admin-eyebrow">CENTRO DE CONTROL</span>
            <h1>Todo listo para<br><em>crear algo delicioso.</em></h1>
            <p>Administra tu carta e ingredientes desde un solo lugar.</p>
            <a class="admin-primary-link" href="#platillos">Agregar un platillo <span>↗</span></a>
        </div>
        <div class="admin-hero-art" aria-hidden="true">
            <span class="admin-art-sun"></span>
            <span class="admin-art-plate"><i></i><b></b><small></small></span>
            <span class="admin-art-leaf leaf-one"></span>
            <span class="admin-art-leaf leaf-two"></span>
        </div>
    </section>

    @if (session('success'))
        <div class="admin-alert admin-alert-success" role="status">{{ session('success') }}</div>
    @endif
    @if (session('error') || $error)
        <div class="admin-alert admin-alert-error" role="alert">{{ session('error') ?? $error }}</div>
    @endif
    @if ($errors->any())
        <div class="admin-alert admin-alert-error" role="alert">
            <strong>Revisa estos campos:</strong>
            <ul>@foreach ($errors->all() as $errorItem)<li>{{ $errorItem }}</li>@endforeach</ul>
        </div>
    @endif

    <section class="admin-stats" aria-label="Resumen del catálogo">
        <article class="admin-stat-card"><span class="admin-stat-icon icon-olive">✦</span><div><small>Platillos en carta</small><strong>{{ count($productos) }}</strong></div><span class="admin-stat-note">registrados</span></article>
        <article class="admin-stat-card"><span class="admin-stat-icon icon-rose">◌</span><div><small>Ingredientes</small><strong>{{ count($ingredientes) }}</strong></div><span class="admin-stat-note">en catálogo</span></article>
        <article class="admin-stat-card"><span class="admin-stat-icon icon-gold">▦</span><div><small>Categorías</small><strong>{{ count($categorias) }}</strong></div><span class="admin-stat-note">organizadas</span></article>
    </section>

    <section class="admin-section-heading" id="platillos">
        <div><span class="admin-eyebrow">CARTA</span><h2>Platillos</h2><p>Crea productos y asígnalos a una categoría. El coste por porción usa los lotes de compra registrados.</p></div>
        <span class="admin-section-count">{{ count($productos) }} total</span>
    </section>
    <section class="admin-management-grid">
        <form class="admin-form-card" method="POST" action="{{ route('admin.productos.store') }}">
            @csrf
            <div class="admin-card-title"><span class="admin-card-symbol">✦</span><div><h3>Nuevo platillo</h3><p>Los campos con * son obligatorios.</p></div></div>
            <label>Nombre del platillo *<input name="nombre" value="{{ old('nombre') }}" maxlength="150" placeholder="Ej. Pasta de la casa" required></label>
            <label>Categoría
                <select name="categoria_id"><option value="">Sin categoría</option>@foreach ($categorias as $categoria)<option value="{{ $categoria['id'] }}" @selected(old('categoria_id') === $categoria['id'])>{{ $categoria['nombre'] }}</option>@endforeach</select>
            </label>
            <label>Descripción<textarea name="descripcion" rows="3" maxlength="1500" placeholder="Ingredientes principales o una descripción breve">{{ old('descripcion') }}</textarea></label>
            <div class="admin-form-row">
                <label>Precio (COP) *<input type="number" name="precio" min="0" max="2147483647" step="1" value="{{ old('precio') }}" placeholder="28000" required></label>
                <label>Preparación (minutos)<input type="number" name="tiempo_preparacion_min" min="1" max="1440" step="1" value="{{ old('tiempo_preparacion_min') }}" placeholder="20"></label>
            </div>
            <label>URL de la imagen<input type="url" name="imagen_url" value="{{ old('imagen_url') }}" maxlength="2000" placeholder="https://..."></label>
            <div class="admin-recipe-editor">
                <div class="admin-recipe-heading"><div><strong>Receta del platillo *</strong><small>Indica cuánto de cada ingrediente lleva una porción.</small></div><button type="button" class="admin-add-recipe" data-add-recipe>+ Agregar ingrediente</button></div>
                <div class="admin-recipe-lines" data-recipe-lines>
                    @foreach (old('receta', [[]]) as $index => $linea)
                        <div class="admin-recipe-line" data-recipe-line>
                            <label>Ingrediente
                                <select name="receta[{{ $index }}][ingrediente_id]" required>
                                    <option value="">Selecciona</option>
                                    @foreach ($ingredientes as $ingrediente)
                                        @if (!empty($ingrediente['activo']))
                                            <option value="{{ $ingrediente['id'] }}" @selected(($linea['ingrediente_id'] ?? '') === $ingrediente['id'])>{{ $ingrediente['nombre'] }} · {{ $ingrediente['unidad'] }}</option>
                                        @endif
                                    @endforeach
                                </select>
                            </label>
                            <label>Cantidad por porción<input type="number" name="receta[{{ $index }}][cantidad]" min="0.001" max="100000000" step="any" value="{{ $linea['cantidad'] ?? '' }}" placeholder="Ej. 150" required></label>
                            <button type="button" class="admin-remove-recipe" data-remove-recipe aria-label="Quitar ingrediente">×</button>
                        </div>
                    @endforeach
                </div>
            </div>
            <button class="admin-submit" type="submit">Crear platillo <span>→</span></button>
            <p class="admin-form-footnote">La unidad de cada cantidad corresponde a la unidad configurada en el ingrediente. Para vender, también debe haber existencias en inventario.</p>
        </form>

        <div class="admin-list-card">
            <div class="admin-list-heading"><div><h3>En tu carta</h3><p>Catálogo de platillos</p></div><span>{{ count($productos) }}</span></div>
            <div class="admin-record-list">
                @forelse ($productos as $producto)
                    @php($categoriaProducto = collect($categorias)->firstWhere('id', $producto['categoria_id'] ?? null))
                    @php($costoPorcionProducto = $costoPlatillos[$producto['id']] ?? 0)
                    @php($margenPorcionProducto = (int) $producto['precio'] - $costoPorcionProducto)
                    <article class="admin-record">
                        <span class="admin-record-thumb">{{ mb_strtoupper(mb_substr($producto['nombre'], 0, 1)) }}</span>
                        <div class="admin-record-copy"><strong>{{ $producto['nombre'] }}</strong><small>{{ $categoriaProducto['nombre'] ?? 'Sin categoría' }} · {{ !empty($producto['activo']) ? 'Activo' : 'Inactivo' }}@if (!empty($producto['tiempo_preparacion_min'])) · {{ $producto['tiempo_preparacion_min'] }} min @endif</small>
                            @if (!empty($recetasProductos[$producto['id']]))
                                <small>Receta:
                                    @foreach ($recetasProductos[$producto['id']] as $lineaReceta)
                                        {{ $lineaReceta['cantidad'] }} {{ $lineaReceta['unidad'] }} {{ $lineaReceta['nombre'] }}
                                        @if (isset($lineaReceta['costo_linea']))
                                            · ${{ number_format($lineaReceta['costo_linea'], 0, ',', '.') }}
                                        @endif
                                        @if (!$loop->last) · @endif
                                    @endforeach
                                </small>
                                @if ($costoDisponiblePlatillos[$producto['id']] ?? false)
                                    <small class="admin-product-cost"><strong>Coste por porción: ${{ number_format($costoPorcionProducto, 0, ',', '.') }}</strong><span>Coste promedio ponderado de compras</span></small>
                                    <small class="admin-product-margin">Margen bruto estimado: ${{ number_format($margenPorcionProducto, 0, ',', '.') }}@if ((int) $producto['precio'] > 0) · {{ number_format($margenPorcionProducto / (int) $producto['precio'] * 100, 1, ',', '.') }}% del precio @endif</small>
                                @else
                                    <small class="admin-recipe-missing">Registra lotes con coste para calcular el coste completo de este platillo.</small>
                                @endif
                            @else
                                <small class="admin-recipe-missing">Sin receta configurada</small>
                            @endif
                        </div>
                        <strong class="admin-record-price">${{ number_format((int) $producto['precio'], 0, ',', '.') }}</strong>
                        <div class="admin-record-actions">
                            <a class="admin-mini-action admin-product-action edit-action" href="{{ route('admin.productos.edit', $producto['id']) }}" aria-label="Editar {{ $producto['nombre'] }}" title="Editar"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m14.5 5.5 4 4M4 20l4.2-.9L19.4 7.9a2.1 2.1 0 0 0-3-3L5.2 16.1 4 20Z"/></svg><span>Editar</span></a>
                            <form method="POST" action="{{ route('admin.productos.estado', $producto['id']) }}">@csrf @method('PATCH')<input type="hidden" name="activo" value="{{ !empty($producto['activo']) ? 0 : 1 }}"><button class="admin-mini-action admin-product-action archive-action" type="submit" aria-label="{{ !empty($producto['activo']) ? 'Archivar' : 'Activar' }} {{ $producto['nombre'] }}" title="{{ !empty($producto['activo']) ? 'Archivar' : 'Activar' }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3.5 7h17v13h-17zM2.5 4h19v3h-19zM9 11h6"/></svg><span>{{ !empty($producto['activo']) ? 'Archivar' : 'Activar' }}</span></button></form>
                        </div>
                    </article>
                @empty
                    <div class="admin-empty"><span>✦</span><strong>Aún no hay platillos</strong><small>El primero que agregues aparecerá aquí.</small></div>
                @endforelse
            </div>
        </div>
    </section>

    <section class="admin-section-heading" id="ingredientes">
        <div><span class="admin-eyebrow">INVENTARIO</span><h2>Ingredientes</h2><p>Registra la materia prima y su unidad de medida.</p></div>
        <span class="admin-section-count">{{ count($ingredientes) }} total</span>
    </section>
    <section class="admin-management-grid">
        <form class="admin-form-card" method="POST" action="{{ route('admin.ingredientes.store') }}">
            @csrf
            <div class="admin-card-title"><span class="admin-card-symbol card-symbol-rose">◌</span><div><h3>Nuevo ingrediente</h3><p>Define la unidad que usarás en inventario.</p></div></div>
            <label>Nombre del ingrediente *<input name="nombre" value="{{ old('nombre') }}" maxlength="150" placeholder="Ej. Queso mozzarella" required></label>
            <div class="admin-form-row">
                <label>Unidad de medida *<select name="unidad" required><option value="">Selecciona</option><option value="g" @selected(old('unidad') === 'g')>g · gramos</option><option value="ml" @selected(old('unidad') === 'ml')>ml · mililitros</option><option value="unidad" @selected(old('unidad') === 'unidad')>unidad</option></select></label>
                <label>Tipo<input name="tipo" value="{{ old('tipo') }}" maxlength="80" placeholder="Lácteo, verdura…"></label>
            </div>
            <label>Descripción<textarea name="descripcion" rows="2" maxlength="1000" placeholder="Notas opcionales">{{ old('descripcion') }}</textarea></label>
            <label>Alerta de stock mínimo *<input type="number" name="stock_minimo" min="0" step="0.001" value="{{ old('stock_minimo', '0') }}" required></label>
            <button class="admin-submit submit-rose" type="submit">Crear ingrediente <span>→</span></button>
            <p class="admin-form-footnote">Para sumar existencias, registra luego un lote en el módulo de inventario.</p>
        </form>

        <div class="admin-list-card">
            <div class="admin-list-heading"><div><h3>Ingredientes registrados</h3><p>Catálogo para recetas e inventario</p></div><span>{{ count($ingredientes) }}</span></div>
            <div class="admin-record-list">
                @forelse ($ingredientes as $ingrediente)
                    <article class="admin-record">
                        <span class="admin-record-thumb record-thumb-rose">◌</span>
                        <div class="admin-record-copy"><strong>{{ $ingrediente['nombre'] }}</strong><small>{{ $ingrediente['tipo'] ?: 'Ingrediente' }} · {{ $ingrediente['unidad'] }} · mínimo {{ $ingrediente['stock_minimo'] }}@if (isset($costosIngrediente[$ingrediente['id']])) · ${{ number_format($costosIngrediente[$ingrediente['id']], 2, ',', '.') }}/{{ $ingrediente['unidad'] }}@else · Sin coste de compra @endif</small></div>
                        <span class="admin-status {{ !empty($ingrediente['activo']) ? 'status-active' : 'status-inactive' }}">{{ !empty($ingrediente['activo']) ? 'Activo' : 'Inactivo' }}</span>
                        <div class="admin-record-actions"><a class="admin-icon-action edit-action" href="{{ route('admin.ingredientes.edit', $ingrediente['id']) }}" aria-label="Editar {{ $ingrediente['nombre'] }}" title="Editar"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m14.5 5.5 4 4M4 20l4.2-.9L19.4 7.9a2.1 2.1 0 0 0-3-3L5.2 16.1 4 20Z"/></svg></a><form method="POST" action="{{ route('admin.ingredientes.estado', $ingrediente['id']) }}">@csrf @method('PATCH')<input type="hidden" name="activo" value="{{ !empty($ingrediente['activo']) ? 0 : 1 }}"><button class="admin-icon-action archive-action" type="submit" aria-label="{{ !empty($ingrediente['activo']) ? 'Archivar' : 'Activar' }} {{ $ingrediente['nombre'] }}" title="{{ !empty($ingrediente['activo']) ? 'Archivar' : 'Activar' }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3.5 7h17v13h-17zM2.5 4h19v3h-19zM9 11h6"/></svg></button></form></div>
                    </article>
                @empty
                    <div class="admin-empty"><span>◌</span><strong>Sin ingredientes todavía</strong><small>Agrega ingredientes para preparar tus platillos.</small></div>
                @endforelse
            </div>
        </div>
    </section>

    <section class="admin-section-heading" id="categorias">
        <div><span class="admin-eyebrow">ORGANIZACIÓN</span><h2>Categorías</h2><p>Ordena la carta para que sea más fácil de explorar.</p></div>
        <span class="admin-section-count">{{ count($categorias) }} total</span>
    </section>
    <section class="admin-category-section">
        <form class="admin-category-form" method="POST" action="{{ route('admin.categorias.store') }}">
            @csrf
            <h3>Crear categoría</h3>
            <label>Nombre *<input name="nombre" value="{{ old('nombre') }}" maxlength="100" placeholder="Ej. Postres" required></label>
            <label>Descripción<input name="descripcion" value="{{ old('descripcion') }}" maxlength="500" placeholder="Opcional"></label>
            <label>Orden<input type="number" name="orden" min="0" value="{{ old('orden', '0') }}" required></label>
            <button class="admin-submit" type="submit">Agregar categoría <span>→</span></button>
        </form>
        <div class="admin-category-list">
            @forelse ($categorias as $categoria)
                <div class="admin-category-chip"><span>▦</span><strong>{{ $categoria['nombre'] }}</strong><small>{{ !empty($categoria['activa']) ? 'Activa' : 'Inactiva' }}</small><a class="admin-icon-action edit-action" href="{{ route('admin.categorias.edit', $categoria['id']) }}" aria-label="Editar {{ $categoria['nombre'] }}" title="Editar"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m14.5 5.5 4 4M4 20l4.2-.9L19.4 7.9a2.1 2.1 0 0 0-3-3L5.2 16.1 4 20Z"/></svg></a><form method="POST" action="{{ route('admin.categorias.estado', $categoria['id']) }}">@csrf @method('PATCH')<input type="hidden" name="activo" value="{{ !empty($categoria['activa']) ? 0 : 1 }}"><button class="admin-icon-action archive-action" type="submit" aria-label="{{ !empty($categoria['activa']) ? 'Archivar' : 'Activar' }} {{ $categoria['nombre'] }}" title="{{ !empty($categoria['activa']) ? 'Archivar' : 'Activar' }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3.5 7h17v13h-17zM2.5 4h19v3h-19zM9 11h6"/></svg></button></form></div>
            @empty
                <div class="admin-empty"><strong>No hay categorías creadas.</strong><small>Crea una para organizar los platillos.</small></div>
            @endforelse
        </div>
    </section>
    <footer class="admin-footer">La Mesa <span>·</span> Administración del restaurante</footer>
@endsection
