@extends('layouts.cliente')

@section('title', 'Mis reservas | Restaurante')
@section('heading', 'Mis reservas')

@section('content')
<div class="cliente-page-heading">
    <div><span class="cliente-eyebrow">MI EXPERIENCIA</span><h2>Mis reservas</h2><p>Consulta las reservas vinculadas a tu cuenta.</p><a class="cliente-action-link" href="{{ route('cliente.reservas.create') }}">Solicitar una reserva</a></div>
</div>

@if ($error)<div class="cliente-alert cliente-alert-error">{{ $error }}</div>@endif
@if (session('success'))<div class="cliente-alert cliente-alert-success">{{ session('success') }}</div>@endif
@if ($errors->has('reserva'))<div class="cliente-alert cliente-alert-error">{{ $errors->first('reserva') }}</div>@endif

<div class="cliente-order-list">
    @forelse ($reservas as $reserva)
        <article class="cliente-order-card">
            <header>
                <div><span class="cliente-eyebrow">RESERVA</span><h3>#{{ substr($reserva['id'], 0, 8) }}</h3></div>
                <span class="cliente-order-state">{{ str_replace('_', ' ', ucfirst($reserva['estado'] ?? 'sin estado')) }}</span>
            </header>
            <p class="cliente-order-meta">
                {{ !empty($reserva['fecha_hora_inicio']) ? \Illuminate\Support\Carbon::parse($reserva['fecha_hora_inicio'])->setTimezone('America/Bogota')->format('d/m/Y H:i') : 'Fecha no disponible' }}
                · {{ $reserva['personas'] ?? '—' }} personas
                @if (!empty($reserva['mesa']['nombre'])) · {{ $reserva['mesa']['nombre'] }} @endif
                @if (!empty($reserva['mesa']['zona'])) · {{ $reserva['mesa']['zona'] }} @endif
            </p>
            @if (!empty($reserva['observaciones']))<p class="cliente-order-notes">{{ $reserva['observaciones'] }}</p>@endif
            @if (in_array($reserva['estado'] ?? '', ['pendiente', 'confirmada'], true))
                <form method="POST" action="{{ route('cliente.reservas.cancelar', $reserva['id']) }}">
                    @csrf
                    <button class="cliente-cancel-button" type="submit">Cancelar reserva</button>
                </form>
            @endif
        </article>
    @empty
        <div class="cliente-empty-state"><h3>Aún no tienes reservas</h3><p>Las reservas asociadas a tu cuenta aparecerán aquí.</p></div>
    @endforelse
</div>
@endsection
