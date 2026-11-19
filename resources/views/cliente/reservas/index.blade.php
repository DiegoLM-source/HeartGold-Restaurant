@extends('layouts.cliente')

@section('title', 'Mis reservas | Restaurante')
@section('heading', 'Mis reservas')

@section('content')
<div class="reservas-page">
    <section class="reservas-hero" aria-labelledby="reservas-title">
        <div class="reservas-hero-copy">
            <span class="cliente-eyebrow">TU PRÓXIMA VISITA</span>
            <h2 id="reservas-title">Un buen plan empieza aquí</h2>
            <p>Consulta tus reservas, revisa los detalles de tu visita o elige una fecha para volver.</p>
        </div>
        <a class="reservas-add-button" href="{{ route('cliente.reservas.create') }}">
            <span class="reservas-add-icon" aria-hidden="true">+</span>
            <span><strong>Agregar una reserva</strong><small>Elige fecha, hora y mesa</small></span>
            <span class="reservas-button-arrow" aria-hidden="true">→</span>
        </a>
    </section>

    @if ($error)<div class="cliente-alert cliente-alert-error" role="alert">{{ $error }}</div>@endif
    @if (session('success'))<div class="cliente-alert cliente-alert-success" role="status">{{ session('success') }}</div>@endif
    @if ($errors->has('reserva'))<div class="cliente-alert cliente-alert-error" role="alert">{{ $errors->first('reserva') }}</div>@endif

    <div class="reservas-section-heading">
        <div><span class="cliente-eyebrow">TU HISTORIAL</span><h3>Reservas de tu cuenta</h3></div>
        <span class="reservas-count">{{ count($reservas) }} {{ count($reservas) === 1 ? 'reserva' : 'reservas' }}</span>
    </div>

    <div class="reservas-grid">
        @forelse ($reservas as $reserva)
            @php
                $estado = $reserva['estado'] ?? 'sin estado';
                $estadoClase = match ($estado) {
                    'confirmada' => 'is-confirmada',
                    'cancelada', 'rechazada' => 'is-cancelada',
                    'completada' => 'is-completada',
                    default => 'is-pendiente',
                };
            @endphp
            <article class="reserva-card">
                <header class="reserva-card-header">
                    <span class="reserva-card-label"><span class="reserva-card-icon" aria-hidden="true">◷</span> Reserva #{{ substr($reserva['id'], 0, 8) }}</span>
                    <span class="reserva-status {{ $estadoClase }}">{{ str_replace('_', ' ', ucfirst($estado)) }}</span>
                </header>

                <div class="reserva-date-block">
                    <span class="reserva-detail-label">FECHA Y HORA</span>
                    <strong>{{ !empty($reserva['fecha_hora_inicio']) ? \Illuminate\Support\Carbon::parse($reserva['fecha_hora_inicio'])->locale('es')->setTimezone('America/Bogota')->translatedFormat('l, d \d\e F') : 'Fecha no disponible' }}</strong>
                    @if (!empty($reserva['fecha_hora_inicio']))
                        <span class="reserva-time">{{ \Illuminate\Support\Carbon::parse($reserva['fecha_hora_inicio'])->setTimezone('America/Bogota')->format('g:i A') }}</span>
                    @endif
                </div>

                <div class="reserva-details">
                    <div><span class="reserva-detail-label">PERSONAS</span><strong>{{ $reserva['personas'] ?? '—' }} {{ ($reserva['personas'] ?? 0) == 1 ? 'persona' : 'personas' }}</strong></div>
                    <div><span class="reserva-detail-label">MESA</span><strong>{{ $reserva['mesa']['nombre'] ?? 'Por asignar' }}</strong></div>
                    @if (!empty($reserva['mesa']['zona']))
                        <div><span class="reserva-detail-label">ZONA</span><strong>{{ $reserva['mesa']['zona'] }}</strong></div>
                    @endif
                </div>

                @if (!empty($reserva['observaciones']))
                    <p class="reserva-notes"><span>Nota</span>{{ $reserva['observaciones'] }}</p>
                @endif

                @if (in_array($estado, ['pendiente', 'confirmada'], true))
                    <footer class="reserva-card-footer">
                        <span>Puedes cancelar esta reserva si cambian tus planes.</span>
                        <form method="POST" action="{{ route('cliente.reservas.cancelar', $reserva['id']) }}" onsubmit="return confirm('¿Quieres cancelar esta reserva?')">
                            @csrf
                            <button class="reserva-cancel-button" type="submit">Cancelar</button>
                        </form>
                    </footer>
                @endif
            </article>
        @empty
            <div class="reservas-empty-state">
                <div class="reservas-empty-icon" aria-hidden="true">◷</div>
                <span class="cliente-eyebrow">AÚN NO HAY RESERVAS</span>
                <h3>Tu próxima visita comienza con un plan</h3>
                <p>Cuando solicites una reserva, aquí podrás consultar su fecha, horario y estado.</p>
                <a class="reservas-add-button reservas-empty-button" href="{{ route('cliente.reservas.create') }}">
                    <span class="reservas-add-icon" aria-hidden="true">+</span>
                    <span><strong>Agregar una reserva</strong><small>Solicita tu primera mesa</small></span>
                    <span class="reservas-button-arrow" aria-hidden="true">→</span>
                </a>
            </div>
        @endforelse
    </div>
</div>
@endsection
