
@extends('layouts.cliente')

@section('title', 'Inicio | Restaurante')
@section('heading', 'Bienvenido')

@section('content')

@if ($error)
    <div class="cliente-alert cliente-alert-error">
        {{ $error }}
    </div>
@endif

{{-- Bienvenida --}}
<div class="cliente-welcome">
    <div>
        <span class="cliente-eyebrow">TU EXPERIENCIA COMIENZA AQUÍ</span>

        <h2>
            ¡Hola,
            {{ session('usuario.full_name') ?? session('usuario.fullname') ?? 'bienvenido' }}!
        </h2>

        <p>
            Explora nuestro menú, realiza tus pedidos y consulta tus reservas
            desde un solo lugar.
        </p>
    </div>

    <div class="cliente-welcome-icon">
        ♨
    </div>
</div>

{{-- Información de la mesa --}}
@if ($sesion && $mesa)

    <section class="cliente-active-session">

        <div class="cliente-session-heading">
            <div>
                <span class="cliente-eyebrow">TU VISITA ACTUAL</span>
                <h2>Estás en {{ $mesa['nombre'] }}</h2>
            </div>

            <span class="cliente-session-badge">
                Sesión activa
            </span>
        </div>

        <div class="cliente-session-details">

            <div>
                <span>Mesa</span>
                <strong>{{ $mesa['nombre'] }}</strong>
            </div>

            <div>
                <span>Capacidad</span>
                <strong>{{ $mesa['capacidad'] }} personas</strong>
            </div>

            <div>
                <span>Zona</span>
                <strong>{{ $mesa['zona'] ?? 'No especificada' }}</strong>
            </div>

        </div>

        <p>
            Tu acceso ha sido autorizado por el personal.
            Ya puedes consultar el menú y realizar tus pedidos.
        </p>

    </section>

@elseif (!$error)

    <section class="cliente-no-session">
        <div class="cliente-shortcut-icon">♧</div>

        <div>
            <h3>No tienes una mesa activa</h3>
            <p>
                Cuando escanees el código QR de una mesa y el personal
                autorice tu solicitud, aquí aparecerá tu visita actual.
            </p>
        </div>
    </section>

@endif

{{-- Accesos rápidos --}}
<div class="cliente-section-heading">
    <div>
        <span class="cliente-eyebrow">ACCESOS RÁPIDOS</span>
        <h2>¿Qué deseas hacer?</h2>
    </div>
</div>

<div class="cliente-shortcuts">

    <article class="cliente-shortcut-card">
        <div class="cliente-shortcut-icon">☷</div>
        <h3>Explorar menú</h3>
        <p>
            Consulta los productos y descubre lo que tenemos para ofrecerte.
        </p>

        @if ($sesion && $mesa)
            <span class="cliente-coming-soon">Disponible próximamente</span>
        @else
            <span class="cliente-coming-soon">Necesitas una mesa activa</span>
        @endif
    </article>

    <article class="cliente-shortcut-card">
        <div class="cliente-shortcut-icon">▤</div>
        <h3>Mis pedidos</h3>
        <p>
            Consulta los pedidos que has realizado y sus estados.
        </p>
        <span class="cliente-coming-soon">Próximamente</span>
    </article>

    <article class="cliente-shortcut-card">
        <div class="cliente-shortcut-icon">▣</div>
        <h3>Reservar mesa</h3>
        <p>
            Organiza tu próxima visita seleccionando fecha y cantidad de personas.
        </p>
        <span class="cliente-coming-soon">Próximamente</span>
    </article>

    <article class="cliente-shortcut-card">
        <div class="cliente-shortcut-icon">◷</div>
        <h3>Mi historial</h3>
        <p>
            Consulta tus sesiones, consumos y comprobantes anteriores.
        </p>
        <span class="cliente-coming-soon">Próximamente</span>
    </article>

</div>

@endsection

