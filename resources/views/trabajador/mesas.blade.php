
@extends('layouts.trabajador')

@section('title', 'Mesas | Restaurante')
@section('heading', 'Mesas')

@section('content')
<div class="tables-page">

    {{-- Mensajes del sistema --}}
    @if(session('success'))
        <div class="tables-alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->has('solicitudes'))
        <div class="tables-alert tables-alert-error">
            {{ $errors->first('solicitudes') }}
        </div>
    @endif

    {{-- ==========================================
         SOLICITUDES DE ACCESO
    =========================================== --}}
    @if(isset($errorSolicitudes) && $errorSolicitudes)
        <div class="tables-alert tables-alert-error">
            {{ $errorSolicitudes }}
        </div>
    @endif

    <section class="mesa-requests-panel">

        <div class="mesa-requests-heading">
            <div>
                <span class="eyebrow">ATENCIÓN AL CLIENTE</span>
                <h2>Solicitudes de acceso</h2>
                <p>Solicitudes enviadas desde los códigos QR de las mesas.</p>
            </div>

            <span class="mesa-requests-count">
                {{ count($solicitudes ?? []) }}
                pendientes
            </span>
        </div>

        @forelse($solicitudes ?? [] as $solicitud)

            <article class="mesa-request-item">

                <div class="mesa-request-info">
                    <strong>
                        {{ $solicitud['nombre_cliente'] ?? 'Cliente' }}
                    </strong>

                    <span>
                        {{ $solicitud['nombre_mesa'] ?? 'Mesa sin identificar' }}
                    </span>

                    <small>
                        {{ \Carbon\Carbon::parse($solicitud['creado_en'])->format('d/m/Y H:i') }}
                    </small>
                </div>

                <div class="mesa-request-actions">

                    {{-- Aceptar solicitud --}}
                    <form method="POST"
                          action="{{ route('trabajador.solicitudes-mesa.resolver', ['solicitud' => $solicitud['id']]) }}">
                        @csrf

                        <input type="hidden" name="aceptar" value="1">

                        <button type="submit" class="mesa-request-accept">
                            Aceptar
                        </button>
                    </form>

                    {{-- Rechazar solicitud --}}
                    <form method="POST"
                          action="{{ route('trabajador.solicitudes-mesa.resolver', ['solicitud' => $solicitud['id']]) }}">
                        @csrf

                        <input type="hidden" name="aceptar" value="0">

                        <button type="submit" class="mesa-request-reject">
                            Rechazar
                        </button>
                    </form>

                </div>

            </article>

        @empty

            <div class="mesa-requests-empty">
                <span>✓</span>
                <p>No hay solicitudes de acceso pendientes.</p>
            </div>

        @endforelse

    </section>

    {{-- ==========================================
         PLANO DE MESAS
    =========================================== --}}

    @if($error)

        <div class="tables-alert tables-alert-error">
            <strong>No se pudieron cargar las mesas</strong>
            <p>{{ $error }}</p>
        </div>

    @else

        <div class="tables-workspace">

            <div class="tables-workspace-header">
                <div>
                    <span class="eyebrow">RESTAURANTE</span>
                    <h2>Plano de mesas</h2>
                    <p>Selecciona una mesa para consultar su información.</p>
                </div>

                <button type="button"
                        class="tables-refresh"
                        onclick="window.location.reload()">
                    ↻ Actualizar
                </button>
            </div>

            {{-- Leyenda --}}
            <div class="tables-legend">
                <span>
                    <i class="legend-dot free"></i>
                    Libre
                </span>

                <span>
                    <i class="legend-dot occupied"></i>
                    Ocupada
                </span>

                <span>
                    <i class="legend-dot reserved"></i>
                    Reservada
                </span>
            </div>

            @if(count($mesas ?? []) > 0)

                @php
                    $maxX = collect($mesas)->max(function ($mesa) {
                        return (float) $mesa['pos_x'] + max(45, (float) $mesa['ancho']);
                    });

                    $maxY = collect($mesas)->max(function ($mesa) {
                        return (float) $mesa['pos_y'] + max(45, (float) $mesa['alto']);
                    });

                    $canvasWidth = max(700, $maxX + 100);
                    $canvasHeight = max(450, $maxY + 100);
                @endphp

                <div class="tables-canvas-wrapper">

                    <div class="tables-canvas"
                         style="width: {{ $canvasWidth }}px; height: {{ $canvasHeight }}px;">

                        @foreach($mesas as $mesa)

                            @php
                                $estado = $mesa['estado'] ?? 'libre';

                                $claseEstado = match($estado) {
                                    'libre' => 'free',
                                    'ocupada' => 'occupied',
                                    'reservada' => 'reserved',
                                    default => 'inactive',
                                };

                                $forma = $mesa['forma'] ?? 'cuadrada';
                            @endphp

                            <button
                                type="button"
                                class="restaurant-table {{ $claseEstado }} {{ $forma === 'circular' ? 'round-table' : '' }}"
                                data-table-id="{{ $mesa['id'] }}"
                                style="
                                    left: {{ (float) $mesa['pos_x'] }}px;
                                    top: {{ (float) $mesa['pos_y'] }}px;
                                    width: {{ max(45, (float) $mesa['ancho']) }}px;
                                    height: {{ max(45, (float) $mesa['alto']) }}px;
                                    transform: rotate({{ (float) $mesa['rotacion'] }}deg);
                                "
                                aria-label="Consultar {{ $mesa['nombre'] }}, {{ $estado }}"
                                title="{{ $mesa['nombre'] }} - {{ ucfirst($estado) }}"
                            >
                                <strong>{{ $mesa['nombre'] }}</strong>
                                <span>{{ $mesa['capacidad'] }} personas</span>
                            </button>

                        @endforeach

                    </div>

                </div>

            @else

                <div class="tables-empty">
                    <span aria-hidden="true">▦</span>
                    <h3>No hay mesas registradas</h3>
                    <p>Cuando se registren mesas activas en el sistema, aparecerán aquí.</p>
                </div>

            @endif

        </div>

    @endif

    {{-- ==========================================
         MODAL DE INFORMACIÓN DE MESA
    =========================================== --}}

    <div class="table-modal-overlay"
         data-table-modal
         aria-hidden="true">

        <section class="table-modal-window"
                 role="dialog"
                 aria-modal="true"
                 aria-labelledby="table-modal-title">

            <div class="table-modal-header">

                <div>
                    <span class="eyebrow">DETALLE DE LA MESA</span>
                    <h2 id="table-modal-title">Información</h2>
                </div>

                <button type="button"
                        class="table-modal-close"
                        data-modal-close
                        aria-label="Cerrar ventana">
                    &times;
                </button>

            </div>

            <div class="table-modal-body">

                <div class="modal-loading" data-modal-loading hidden>
                    <span class="modal-spinner"></span>
                    <p>Cargando información de la mesa...</p>
                </div>

                <div class="modal-error"
                     data-modal-error
                     role="alert"></div>

                <div data-modal-content></div>

            </div>

            <div class="table-modal-footer">

                <button type="button"
                        class="modal-secondary-button"
                        data-modal-close>
                    Cerrar
                </button>

            </div>

        </section>

    </div>

</div>
@endsection
