@extends('layouts.cliente')

@section('title', 'Solicitar reserva | Restaurante')
@section('heading', 'Solicitar reserva')

@section('content')
<div class="cliente-page-heading">
    <div><span class="cliente-eyebrow">PLANEA TU VISITA</span><h2>Solicitar una reserva</h2><p>Supabase validará el horario y la disponibilidad antes de registrar la solicitud.</p></div>
    <a class="cliente-action-link" href="{{ route('cliente.reservas.index') }}">Ver mis reservas</a>
</div>

@if ($error)<div class="cliente-alert cliente-alert-error">{{ $error }}</div>@endif
@if ($errors->has('reserva'))<div class="cliente-alert cliente-alert-error">{{ $errors->first('reserva') }}</div>@endif

<form method="POST" action="{{ route('cliente.reservas.store') }}" class="cliente-profile-form cliente-reservation-form" data-reservation-form data-disable-on-submit>
    @csrf
    <label for="fecha_hora">Fecha y hora</label>
    <input id="fecha_hora" type="datetime-local" name="fecha_hora" min="{{ \Carbon\CarbonImmutable::now('America/Bogota')->format('Y-m-d\TH:i') }}" value="{{ old('fecha_hora') }}" required>
    @error('fecha_hora')<span class="cliente-field-error">{{ $message }}</span>@enderror

    <label for="personas">Cantidad de personas</label>
    <input id="personas" type="number" name="personas" min="1" max="2147483647" value="{{ old('personas', 2) }}" required>
    @error('personas')<span class="cliente-field-error">{{ $message }}</span>@enderror

    <label for="duracion_minutos">Duración (minutos)</label>
    <input id="duracion_minutos" type="number" name="duracion_minutos" min="1" max="2147483647" value="{{ old('duracion_minutos', 120) }}" required>
    @error('duracion_minutos')<span class="cliente-field-error">{{ $message }}</span>@enderror

    <label for="mesa_id">Mesa (opcional)</label>
    <select id="mesa_id" name="mesa_id">
        <option value="">Asignar automáticamente según disponibilidad</option>
        @foreach ($mesas as $mesa)
            <option value="{{ $mesa['id'] }}" @selected(old('mesa_id') === $mesa['id'])>{{ $mesa['nombre'] }} · {{ $mesa['capacidad'] }} personas · {{ $mesa['zona'] }}</option>
        @endforeach
    </select>
    @error('mesa_id')<span class="cliente-field-error">{{ $message }}</span>@enderror

    <label for="observaciones">Observaciones</label>
    <textarea id="observaciones" name="observaciones" rows="4" maxlength="1000">{{ old('observaciones') }}</textarea>
    @error('observaciones')<span class="cliente-field-error">{{ $message }}</span>@enderror

    <button type="submit">Enviar solicitud</button>
</form>
@endsection
