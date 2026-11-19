@extends('layouts.cliente')

@section('title', 'Solicitar reserva | Restaurante')
@section('heading', 'Solicitar reserva')

@section('content')
<div class="reservas-page">
    <div class="reservas-form-heading">
        <a class="reservas-back-link" href="{{ route('cliente.reservas.index') }}"><span aria-hidden="true">←</span> Mis reservas</a>
        <span class="cliente-eyebrow">PLANEA TU VISITA</span>
        <h2>Reserva tu mesa</h2>
        <p>Cuéntanos cuándo vienes y prepararemos todo para recibirte.</p>
    </div>

    @if ($error)<div class="cliente-alert cliente-alert-error" role="alert">{{ $error }}</div>@endif
    @if ($errors->has('reserva'))<div class="cliente-alert cliente-alert-error" role="alert">{{ $errors->first('reserva') }}</div>@endif

    <form method="POST" action="{{ route('cliente.reservas.store') }}" class="reservas-form" data-reservation-form data-disable-on-submit>
        @csrf
        <div class="reservas-form-section-heading"><span>01</span><div><h3>Detalles de la visita</h3><p>Selecciona la fecha, hora y tamaño de tu grupo.</p></div></div>

        <div class="reservas-form-grid">
            <div class="reservas-field reservas-field-wide">
                <label for="fecha_hora">Fecha y hora <span>*</span></label>
                <input id="fecha_hora" type="datetime-local" name="fecha_hora" min="{{ \Carbon\CarbonImmutable::now('America/Bogota')->format('Y-m-d\TH:i') }}" value="{{ old('fecha_hora') }}" required>
                @error('fecha_hora')<span class="cliente-field-error">{{ $message }}</span>@enderror
            </div>

            <div class="reservas-field">
                <label for="personas">Cantidad de personas <span>*</span></label>
                <input id="personas" type="number" name="personas" min="1" max="2147483647" value="{{ old('personas', 2) }}" required>
                @error('personas')<span class="cliente-field-error">{{ $message }}</span>@enderror
            </div>

            <div class="reservas-field">
                <label for="duracion_minutos">Duración estimada <span>*</span></label>
                <div class="reservas-input-suffix"><input id="duracion_minutos" type="number" name="duracion_minutos" min="1" max="2147483647" value="{{ old('duracion_minutos', 120) }}" required><span>min</span></div>
                @error('duracion_minutos')<span class="cliente-field-error">{{ $message }}</span>@enderror
            </div>

            <div class="reservas-field reservas-field-wide">
                <label for="mesa_id">Preferencia de mesa <span class="reservas-optional">Opcional</span></label>
                <select id="mesa_id" name="mesa_id">
                    <option value="">Asignar automáticamente según disponibilidad</option>
                    @foreach ($mesas as $mesa)
                        <option value="{{ $mesa['id'] }}" @selected(old('mesa_id') === $mesa['id'])>{{ $mesa['nombre'] }} · {{ $mesa['capacidad'] }} personas · {{ $mesa['zona'] }}</option>
                    @endforeach
                </select>
                @error('mesa_id')<span class="cliente-field-error">{{ $message }}</span>@enderror
                <small>Si no eliges una mesa, asignaremos la más adecuada según disponibilidad.</small>
            </div>

            <div class="reservas-field reservas-field-wide">
                <label for="observaciones">¿Algo que debamos saber? <span class="reservas-optional">Opcional</span></label>
                <textarea id="observaciones" name="observaciones" rows="4" maxlength="1000" placeholder="Cuéntanos si tienes alguna solicitud especial…">{{ old('observaciones') }}</textarea>
                @error('observaciones')<span class="cliente-field-error">{{ $message }}</span>@enderror
            </div>
        </div>

        <div class="reservas-form-footer">
            <p>Los campos marcados con <span>*</span> son obligatorios.</p>
            <button type="submit"><span>Enviar solicitud</span><span aria-hidden="true">→</span></button>
        </div>
    </form>
</div>
@endsection
