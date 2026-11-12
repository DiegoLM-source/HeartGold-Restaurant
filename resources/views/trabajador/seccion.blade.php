@extends('layouts.trabajador')

@section('title', $seccion['titulo'])
@section('heading', $seccion['titulo'])

@section('content')
    <div class="module-heading">
        <div>
            <span class="eyebrow">GESTIÓN DEL RESTAURANTE</span>
            <h2>{{ $seccion['titulo'] }}</h2>
            <p>{{ $seccion['descripcion'] }}</p>
        </div>
        <span class="module-mark" aria-hidden="true">
            @switch($seccionActual)
                @case('mesas') ▦ @break
                @case('pedidos') ▤ @break
                @case('reservas') ▣ @break
                @case('inventario') ▥ @break
                @case('ventas') $ @break
                @default ◷
            @endswitch
        </span>
    </div>

    <div class="worker-empty-state">
        <span class="empty-state-icon" aria-hidden="true">✧</span>
        <h3>Este módulo está preparado para desarrollarse</h3>
        <p>La pantalla y la navegación ya están creadas. El siguiente paso es conectar esta sección con las funciones y tablas de Supabase para mostrar información real y habilitar sus operaciones.</p>
        <a href="{{ route('trabajador.dashboard') }}" class="worker-primary-link">Volver al inicio</a>
    </div>
@endsection
