@extends('layouts.trabajador')

@section('title', 'Inicio')
@section('heading', 'Inicio')

@section('content')
    <div class="worker-welcome">
        <div>
            <span class="eyebrow">PANEL DE OPERACIONES</span>
            <h2>¡Bienvenido, {{ session('usuario.full_name') ?: 'trabajador' }}!</h2>
            <p>Desde aquí podrás supervisar y gestionar las actividades del restaurante.</p>
        </div>
        <div class="welcome-decoration" aria-hidden="true">✦</div>
    </div>

    <div class="section-heading">
        <div><h2>Accesos rápidos</h2><p>Selecciona el área que necesitas gestionar.</p></div>
    </div>

    <div class="worker-shortcuts">
        @php
            $accesos = [
                ['mesas', 'Mesas', 'Consulta el plano y las sesiones de atención.', '▦'],
                ['pedidos', 'Pedidos', 'Confirma pedidos y actualiza su preparación.', '▤'],
                ['reservas', 'Reservas', 'Revisa las próximas reservas.', '▣'],
                ['inventario', 'Inventario', 'Consulta ingredientes y existencias.', '▥'],
                ['ventas', 'Ventas y pagos', 'Registra ventas y pagos de pedidos.', '$'],
                ['historial', 'Historial', 'Consulta la actividad anterior.', '◷'],
            ];
        @endphp
        @foreach ($accesos as [$slug, $nombre, $descripcion, $icono])
            <a class="shortcut-card" href="{{ route('trabajador.seccion', $slug) }}">
                <span class="shortcut-icon" aria-hidden="true">{{ $icono }}</span>
                <span class="shortcut-copy"><strong>{{ $nombre }}</strong><small>{{ $descripcion }}</small></span>
                <span class="shortcut-arrow" aria-hidden="true">→</span>
            </a>
        @endforeach
    </div>

    <div class="worker-note">
        <strong>Configuración inicial</strong>
        <p>La navegación y las pantallas base ya están preparadas. Los indicadores y operaciones se conectarán a Supabase al implementar cada módulo.</p>
    </div>
@endsection
