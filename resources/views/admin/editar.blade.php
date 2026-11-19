@extends('layouts.admin')

@php
    $etiquetas = ['producto' => 'platillo', 'categoria' => 'categoría', 'ingrediente' => 'ingrediente', 'mesa' => 'mesa'];
    $rutas = ['producto' => 'productos', 'categoria' => 'categorias', 'ingrediente' => 'ingredientes', 'mesa' => 'mesas'];
    $titulo = ucfirst($etiquetas[$tipo] ?? 'registro');
@endphp

@section('title', 'Editar ' . $titulo . ' | Administración')

@section('content')
    <section class="admin-section-heading admin-edit-heading">
        <div><span class="admin-eyebrow">GESTIÓN DEL RESTAURANTE</span><h2>Editar {{ $etiquetas[$tipo] ?? 'registro' }}</h2><p>Actualiza los datos y guarda los cambios.</p></div>
        <a class="admin-mini-action" href="{{ $tipo === 'mesa' ? route('admin.mesas') : route('admin.dashboard') }}">Volver</a>
    </section>
    @if (session('error'))<div class="admin-alert admin-alert-error" role="alert">{{ session('error') }}</div>@endif
    @if ($errors->any())<div class="admin-alert admin-alert-error" role="alert"><ul>@foreach ($errors->all() as $errorItem)<li>{{ $errorItem }}</li>@endforeach</ul></div>@endif

    @if ($tipo === 'mesa')
        @include('admin.partials.plano-mesas', [
            'mesasPlano' => $mesasPlano,
            'borrador' => [
                'id' => $registro['id'],
                'nombre' => old('nombre', $registro['nombre']),
                'capacidad' => old('capacidad', $registro['capacidad']),
                'forma' => old('forma', $registro['forma']),
                'pos_x' => old('pos_x', $registro['pos_x']),
                'pos_y' => old('pos_y', $registro['pos_y']),
                'ancho' => old('ancho', $registro['ancho']),
                'alto' => old('alto', $registro['alto']),
                'rotacion' => old('rotacion', $registro['rotacion']),
            ],
        ])
    @endif

    <form class="admin-form-card admin-edit-form" method="POST" action="{{ route('admin.' . $rutas[$tipo] . '.update', $registro['id']) }}" @if ($tipo === 'mesa') data-table-form @endif>
        @csrf
        @method('PATCH')
        @if ($tipo === 'producto')
            <label>Nombre del platillo *<input name="nombre" value="{{ old('nombre', $registro['nombre']) }}" maxlength="150" required></label>
            <label>Categoría<select name="categoria_id"><option value="">Sin categoría</option>@foreach ($categorias as $categoria)<option value="{{ $categoria['id'] }}" @selected(old('categoria_id', $registro['categoria_id'] ?? '') === $categoria['id'])>{{ $categoria['nombre'] }}{{ empty($categoria['activa']) ? ' (inactiva)' : '' }}</option>@endforeach</select></label>
            <label>Descripción<textarea name="descripcion" rows="3" maxlength="1500">{{ old('descripcion', $registro['descripcion'] ?? '') }}</textarea></label>
            <div class="admin-form-row"><label>Precio (COP) *<input type="number" name="precio" min="0" step="1" value="{{ old('precio', $registro['precio']) }}" required></label><label>Preparación (minutos)<input type="number" name="tiempo_preparacion_min" min="1" max="1440" value="{{ old('tiempo_preparacion_min', $registro['tiempo_preparacion_min'] ?? '') }}"></label></div>
            <label>URL de imagen<input type="url" name="imagen_url" maxlength="2000" value="{{ old('imagen_url', $registro['imagen_url'] ?? '') }}"></label>
            <div class="admin-recipe-editor"><div class="admin-recipe-heading"><div><strong>Receta por porción *</strong><small>Al guardar se sustituirán las líneas actuales de la receta.</small></div><button type="button" class="admin-add-recipe" data-add-recipe>+ Agregar ingrediente</button></div>
                <div class="admin-recipe-lines" data-recipe-lines>
                    @foreach (old('receta', $receta ?: [[]]) as $index => $linea)
                        <div class="admin-recipe-line" data-recipe-line>
                            <label>Ingrediente<select name="receta[{{ $index }}][ingrediente_id]" required><option value="">Selecciona</option>@foreach ($ingredientes as $ingrediente)<option value="{{ $ingrediente['id'] }}" @selected(($linea['ingrediente_id'] ?? '') === $ingrediente['id'])>{{ $ingrediente['nombre'] }} · {{ $ingrediente['unidad'] }}{{ empty($ingrediente['activo']) ? ' (inactivo)' : '' }}</option>@endforeach</select></label>
                            <label>Cantidad<input type="number" name="receta[{{ $index }}][cantidad]" min="0.001" step="any" value="{{ $linea['cantidad'] ?? '' }}" required></label>
                            <button type="button" class="admin-remove-recipe" data-remove-recipe aria-label="Quitar ingrediente">×</button>
                        </div>
                    @endforeach
                </div>
            </div>
        @elseif ($tipo === 'categoria')
            <label>Nombre *<input name="nombre" value="{{ old('nombre', $registro['nombre']) }}" maxlength="100" required></label>
            <label>Descripción<textarea name="descripcion" rows="3" maxlength="500">{{ old('descripcion', $registro['descripcion'] ?? '') }}</textarea></label>
            <label>Orden<input type="number" name="orden" min="0" value="{{ old('orden', $registro['orden']) }}" required></label>
        @elseif ($tipo === 'ingrediente')
            <label>Nombre *<input name="nombre" value="{{ old('nombre', $registro['nombre']) }}" maxlength="150" required></label>
            <div class="admin-form-row"><label>Unidad<select name="unidad" required><option value="g" @selected(old('unidad', $registro['unidad']) === 'g')>g · gramos</option><option value="ml" @selected(old('unidad', $registro['unidad']) === 'ml')>ml · mililitros</option><option value="unidad" @selected(old('unidad', $registro['unidad']) === 'unidad')>unidad</option></select></label><label>Tipo<input name="tipo" value="{{ old('tipo', $registro['tipo'] ?? '') }}" maxlength="80"></label></div>
            <label>Descripción<textarea name="descripcion" rows="3" maxlength="1000">{{ old('descripcion', $registro['descripcion'] ?? '') }}</textarea></label>
            <label>Stock mínimo<input type="number" name="stock_minimo" min="0" step="0.001" value="{{ old('stock_minimo', $registro['stock_minimo']) }}" required></label>
        @else
            <label>Nombre *<input name="nombre" value="{{ old('nombre', $registro['nombre']) }}" maxlength="100" required></label>
            <div class="admin-form-row"><label>Capacidad *<input type="number" name="capacidad" min="1" value="{{ old('capacidad', $registro['capacidad']) }}" required></label><label>Forma<select name="forma"><option value="cuadrada" @selected(old('forma', $registro['forma']) === 'cuadrada')>Cuadrada</option><option value="circular" @selected(old('forma', $registro['forma']) === 'circular')>Circular</option></select></label></div>
            <label>Zona<input name="zona" value="{{ old('zona', $registro['zona'] ?? '') }}" maxlength="100"></label>
            <div class="admin-form-row"><label>Posición X<input type="number" name="pos_x" min="0" value="{{ old('pos_x', $registro['pos_x']) }}" required></label><label>Posición Y<input type="number" name="pos_y" min="0" value="{{ old('pos_y', $registro['pos_y']) }}" required></label></div>
            <div class="admin-form-row"><label>Ancho<input type="number" name="ancho" min="45" value="{{ old('ancho', $registro['ancho']) }}" required></label><label>Alto<input type="number" name="alto" min="45" value="{{ old('alto', $registro['alto']) }}" required></label></div>
            <label>Rotación<input type="number" name="rotacion" min="-360" max="360" value="{{ old('rotacion', $registro['rotacion']) }}" required></label>
        @endif
        <div class="admin-edit-actions"><button class="admin-submit" type="submit">Guardar cambios <span>→</span></button><a class="admin-cancel-link" href="{{ $tipo === 'mesa' ? route('admin.mesas') : route('admin.dashboard') }}">Cancelar</a></div>
    </form>
    <footer class="admin-footer">La Mesa <span>·</span> Administración del restaurante</footer>
@endsection
