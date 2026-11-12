
@extends('layouts.trabajador')

@section('title', 'Reservas | Restaurante')
@section('heading', 'Reservas')

@section('content')
<section class="reservations-workspace">

    <header class="reservations-header">
        <div>
            <h2>Reservas</h2>
            <p>Consulta y gestiona las reservas de los clientes.</p>
        </div>

        <button type="button" class="reservations-refresh"
            onclick="window.location.reload()">
            ↻ Actualizar
        </button>
    </header>

        @php
            $totalReservas = count($reservas ?? []);

            $pendientes = count(array_filter($reservas ?? [], fn($r) =>
                ($r['estado'] ?? '') === 'pendiente'
            ));

            $confirmadas = count(array_filter($reservas ?? [], fn($r) =>
                ($r['estado'] ?? '') === 'confirmada'
            ));

            $reservasHoy = count(array_filter($reservas ?? [], fn($r) =>
                \Carbon\Carbon::parse($r['fecha_hora_inicio'])
                    ->isToday()
            ));
        @endphp

        <div class="reservations-summary">

            <div class="reservation-summary-card">
                <div class="reservation-summary-icon">▣</div>
                <div class="reservation-summary-info">
                    <span>Total de reservas</span>
                    <strong>{{ $totalReservas }}</strong>
                </div>
            </div>

            <div class="reservation-summary-card">
                <div class="reservation-summary-icon">◷</div>
                <div class="reservation-summary-info">
                    <span>Pendientes</span>
                    <strong>{{ $pendientes }}</strong>
                </div>
            </div>

            <div class="reservation-summary-card">
                <div class="reservation-summary-icon">✓</div>
                <div class="reservation-summary-info">
                    <span>Confirmadas</span>
                    <strong>{{ $confirmadas }}</strong>
                </div>
            </div>

            <div class="reservation-summary-card">
                <div class="reservation-summary-icon">▦</div>
                <div class="reservation-summary-info">
                    <span>Reservas de hoy</span>
                    <strong>{{ $reservasHoy }}</strong>
                </div>
            </div>

        </div>

    <div class="reservations-toolbar">

        <div class="reservations-filters">
            <button type="button" class="reservation-filter active"
                data-reservation-filter="todos">
                Todas
            </button>

            <button type="button" class="reservation-filter"
                data-reservation-filter="pendiente">
                Pendientes
            </button>

            <button type="button" class="reservation-filter"
                data-reservation-filter="confirmada">
                Confirmadas
            </button>

            <button type="button" class="reservation-filter"
                data-reservation-filter="cancelada">
                Canceladas
            </button>

            <button type="button" class="reservation-filter"
                data-reservation-filter="rechazada">
                Rechazadas
            </button>

            <button type="button" class="reservation-filter"
                data-reservation-filter="completada">
                Completadas
            </button>

            <button type="button" class="reservation-filter"
                data-reservation-filter="no_asistio">
                No asistió
            </button>
        </div>

        <div class="reservations-search">
            <input
                type="search"
                id="reservation-search"
                placeholder="Buscar cliente o mesa..."
                autocomplete="off"
            >
        </div>

    </div>

    <div class="reservations-content" id="reservations-container">

        @if (!empty($error))
            <div class="reservations-alert reservations-alert-error">
                {{ $error }}
            </div>
        @endif

        @if (session('success'))
            <div class="reservations-alert reservations-alert-success">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="reservations-alert reservations-alert-error">
                {{ session('error') }}
            </div>
        @endif

        @if (empty($reservas))
            <div class="reservations-empty">
                <span class="reservations-empty-icon">▣</span>
                <h3>No hay reservas registradas</h3>
                <p>Cuando los clientes realicen reservas, aparecerán en esta sección.</p>
            </div>
        @else

            <div class="reservations-list">

                @foreach ($reservas as $reserva)
                    @php
                        $estado = $reserva['estado'];

                        $estados = [
                            'pendiente' => 'Pendiente',
                            'confirmada' => 'Confirmada',
                            'cancelada' => 'Cancelada',
                            'rechazada' => 'Rechazada',
                            'completada' => 'Completada',
                            'no_asistio' => 'No asistió',
                        ];

                        $fechaInicio = \Carbon\Carbon::parse(
                            $reserva['fecha_hora_inicio']
                        )->timezone(config('app.timezone'));

                        $fechaFin = \Carbon\Carbon::parse(
                            $reserva['fecha_hora_fin']
                        )->timezone(config('app.timezone'));

                        $cliente = $reserva['cliente']['full_name']
                            ?? 'Cliente no disponible';

                        $mesa = $reserva['mesa']['nombre'] ?? 'Sin asignar';
                    @endphp

                    <article
                        class="reservation-card"
                        data-reservation-card
                        data-reservation-state="{{ $estado }}"
                        data-reservation-search="{{ strtolower($cliente . ' ' . $mesa) }}"
                    >

                        <div class="reservation-card-main">

                            <div class="reservation-info">

                                <div class="reservation-card-title">
                                    <span class="reservation-client">
                                        {{ $cliente }}
                                    </span>

                                    <span class="reservation-status status-{{ $estado }}">
                                        {{ $estados[$estado] ?? $estado }}
                                    </span>
                                </div>

                                <div class="reservation-details">

                                    <div class="reservation-detail">
                                        <span class="reservation-detail-label">Mesa asignada</span>
                                        <span class="reservation-detail-value">▦ {{ $mesa }}</span>
                                    </div>

                                    <div class="reservation-detail">
                                        <span class="reservation-detail-label">Personas</span>
                                        <span class="reservation-detail-value">♙ {{ $reserva['personas'] }}</span>
                                    </div>

                                    <div class="reservation-detail">
                                        <span class="reservation-detail-label">Fecha</span>
                                        <span class="reservation-detail-value">◷ {{ $fechaInicio->format('d/m/Y') }}</span>
                                    </div>

                                    <div class="reservation-detail">
                                        <span class="reservation-detail-label">Horario</span>
                                        <span class="reservation-detail-value">
                                            {{ $fechaInicio->format('h:i A') }} – {{ $fechaFin->format('h:i A') }}
                                        </span>
                                    </div>

                                </div>

                            </div>

                        </div>

                        @if (in_array($estado, ['pendiente', 'confirmada']))
                            <div class="reservation-actions">

                                @if ($estado === 'pendiente')
                                    <form method="POST"
                                        action="{{ route('trabajador.reservas.actualizar-estado', $reserva['id']) }}">
                                        @csrf
                                        <input type="hidden" name="estado" value="confirmada">
                                        <button type="submit" class="reservation-action-btn reservation-action-primary">
                                            Confirmar reserva
                                        </button>
                                    </form>

                                    <form method="POST"
                                        action="{{ route('trabajador.reservas.actualizar-estado', $reserva['id']) }}"
                                        onsubmit="return confirm('¿Deseas rechazar esta reserva?');">
                                        @csrf
                                        <input type="hidden" name="estado" value="rechazada">
                                        <button type="submit" class="reservation-action-btn reservation-action-danger">
                                            Rechazar
                                        </button>
                                    </form>

                                    <form method="POST"
                                        action="{{ route('trabajador.reservas.actualizar-estado', $reserva['id']) }}"
                                        onsubmit="return confirm('¿Deseas cancelar esta reserva?');">
                                        @csrf
                                        <input type="hidden" name="estado" value="cancelada">
                                        <button type="submit" class="reservation-action-btn reservation-action-secondary">
                                            Cancelar
                                        </button>
                                    </form>

                                @elseif ($estado === 'confirmada')
                                    <form method="POST"
                                        action="{{ route('trabajador.reservas.actualizar-estado', $reserva['id']) }}">
                                        @csrf
                                        <input type="hidden" name="estado" value="completada">
                                        <button type="submit" class="reservation-action-btn reservation-action-primary">
                                            Completar reserva
                                        </button>
                                    </form>

                                    <form method="POST"
                                        action="{{ route('trabajador.reservas.actualizar-estado', $reserva['id']) }}"
                                        onsubmit="return confirm('¿Deseas marcar esta reserva como no asistió?');">
                                        @csrf
                                        <input type="hidden" name="estado" value="no_asistio">
                                        <button type="submit" class="reservation-action-btn reservation-action-danger">
                                            No asistió
                                        </button>
                                    </form>

                                    <form method="POST"
                                        action="{{ route('trabajador.reservas.actualizar-estado', $reserva['id']) }}"
                                        onsubmit="return confirm('¿Deseas cancelar esta reserva?');">
                                        @csrf
                                        <input type="hidden" name="estado" value="cancelada">
                                        <button type="submit" class="reservation-action-btn reservation-action-secondary">
                                            Cancelar
                                        </button>
                                    </form>
                                @endif

                            </div>
                        @endif

                    </article>

                @endforeach

            </div>

            <div class="reservations-filter-empty" hidden>
                <h3>No se encontraron reservas</h3>
                <p>No hay reservas que coincidan con el filtro o la búsqueda.</p>
            </div>
            
        @endif

    </div>

</section>
@endsection
