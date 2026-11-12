@extends('layouts.trabajador')

@section('title', 'Historial')
@section('heading', 'Historial')

@section('content')
<section class="worker-welcome">
    <div><span class="eyebrow">ACTIVIDAD REGISTRADA</span><h2>Historial del restaurante</h2><p>Pedidos, reservas, sesiones y movimientos de inventario del periodo.</p></div>
    <div class="welcome-decoration" aria-hidden="true">↻</div>
</section>

<nav class="worker-period-filter" aria-label="Filtrar historial por periodo">
    @foreach (['dia' => 'Hoy', 'semana' => 'Esta semana', 'mes' => 'Este mes', 'tres_meses' => 'Últimos 3 meses'] as $valor => $etiqueta)
        <a href="{{ route('trabajador.seccion', ['seccion' => 'historial', 'periodo' => $valor]) }}" class="{{ $periodo === $valor ? 'active' : '' }}" @if ($periodo === $valor) aria-current="page" @endif>{{ $etiqueta }}</a>
    @endforeach
</nav>

@if ($error)<div class="worker-dashboard-alert" role="alert">{{ $error }}</div>@endif

<section class="worker-data-panel">
    <div class="worker-panel-heading"><div><h2>Actividad</h2><p>{{ $inicio->format('d/m/Y') }} – {{ $fin->subSecond()->format('d/m/Y') }}</p></div><span class="worker-record-count">{{ count($eventos) }} registros</span></div>
    @if (count($eventos))
        <div class="worker-table-scroll"><table class="worker-data-table">
            <thead><tr><th>Fecha</th><th>Tipo</th><th>Referencia</th><th>Detalle</th><th>Estado</th></tr></thead>
            <tbody>
                @foreach ($eventos as $evento)
                    <tr>
                        <td>{{ !empty($evento['fecha']) ? \Illuminate\Support\Carbon::parse($evento['fecha'])->setTimezone('America/Bogota')->format('d/m/Y H:i') : '—' }}</td>
                        <td>{{ $evento['tipo'] }}</td>
                        <td>{{ $evento['id'] ? '#' . substr($evento['id'], 0, 8) : '—' }}</td>
                        <td>{{ $evento['detalle'] ?: '—' }}</td>
                        <td>{{ $evento['estado'] ? str_replace('_', ' ', ucfirst($evento['estado'])) : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table></div>
    @else
        <div class="worker-empty-state"><h3>No hay actividad en este periodo</h3><p>Cuando existan operaciones registradas, aparecerán aquí.</p></div>
    @endif
</section>
@endsection
