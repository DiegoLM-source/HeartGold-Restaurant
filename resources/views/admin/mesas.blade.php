@extends('layouts.admin')

@section('title', 'Mesas | Administración')

@section('content')
    <section class="admin-hero">
        <div>
            <span class="admin-eyebrow">ESPACIO DEL RESTAURANTE</span>
            <h1>Organiza tus mesas<br><em>desde administración.</em></h1>
            <p>Crea mesas, define su ubicación y mantén actualizado el plano del restaurante.</p>
        </div>
        <div class="admin-hero-art" aria-hidden="true"><span class="admin-art-sun"></span><span class="admin-art-plate"><i></i><b></b><small></small></span><span class="admin-art-leaf leaf-one"></span><span class="admin-art-leaf leaf-two"></span></div>
    </section>

    @if (session('success'))<div class="admin-alert admin-alert-success" role="status">{{ session('success') }}</div>@endif
    @if (session('error') || $error)<div class="admin-alert admin-alert-error" role="alert">{{ session('error') ?? $error }}</div>@endif
    @if ($errors->any())<div class="admin-alert admin-alert-error" role="alert"><ul>@foreach ($errors->all() as $errorItem)<li>{{ $errorItem }}</li>@endforeach</ul></div>@endif

    <section class="admin-section-heading">
        <div><span class="admin-eyebrow">DISTRIBUCIÓN</span><h2>Mesas</h2><p>La posición se mide en píxeles dentro del plano.</p></div>
        <span class="admin-section-count">{{ count($mesas) }} registradas</span>
    </section>
    @include('admin.partials.plano-mesas', [
        'mesasPlano' => $mesas,
        'borrador' => [
            'nombre' => old('nombre', 'Mesa nueva'),
            'capacidad' => old('capacidad', 4),
            'forma' => old('forma', 'cuadrada'),
            'pos_x' => old('pos_x', 30),
            'pos_y' => old('pos_y', 30),
            'ancho' => old('ancho', 100),
            'alto' => old('alto', 80),
            'rotacion' => old('rotacion', 0),
        ],
    ])
    <section class="admin-management-grid admin-tables-grid">
        <form class="admin-form-card" method="POST" action="{{ route('admin.mesas.store') }}" data-table-form>
            @csrf
            <div class="admin-card-title"><span class="admin-card-symbol">▦</span><div><h3>Nueva mesa</h3><p>Define el nombre, capacidad y ubicación.</p></div></div>
            <label>Nombre *<input name="nombre" value="{{ old('nombre') }}" maxlength="100" placeholder="Ej. Mesa 8" required></label>
            <div class="admin-form-row">
                <label>Capacidad *<input type="number" name="capacidad" min="1" max="1000" value="{{ old('capacidad', 4) }}" required></label>
                <label>Forma<select name="forma" required><option value="cuadrada" @selected(old('forma', 'cuadrada') === 'cuadrada')>Cuadrada</option><option value="circular" @selected(old('forma') === 'circular')>Circular</option></select></label>
            </div>
            <label>Zona<input name="zona" value="{{ old('zona') }}" maxlength="100" placeholder="Salón principal"></label>
            <div class="admin-form-row">
                <label>Posición X<input type="number" name="pos_x" min="0" max="10000" value="{{ old('pos_x', 30) }}" required></label>
                <label>Posición Y<input type="number" name="pos_y" min="0" max="10000" value="{{ old('pos_y', 30) }}" required></label>
            </div>
            <div class="admin-form-row">
                <label>Ancho<input type="number" name="ancho" min="45" max="1000" value="{{ old('ancho', 100) }}" required></label>
                <label>Alto<input type="number" name="alto" min="45" max="1000" value="{{ old('alto', 80) }}" required></label>
            </div>
            <label>Rotación (grados)<input type="number" name="rotacion" min="-360" max="360" value="{{ old('rotacion', 0) }}" required></label>
            <button class="admin-submit" type="submit">Crear mesa <span>→</span></button>
        </form>

        <div class="admin-list-card">
            <div class="admin-list-heading"><div><h3>Plano registrado</h3><p>Mesas activas e inactivas</p></div><span>{{ count($mesas) }}</span></div>
            <div class="admin-record-list">
                @forelse ($mesas as $mesa)
                    <article class="admin-record admin-table-record">
                        <span class="admin-record-thumb">▦</span>
                        <div class="admin-record-copy"><strong>{{ $mesa['nombre'] }}</strong><small>{{ $mesa['zona'] ?: 'Sin zona' }} · {{ $mesa['capacidad'] }} puestos · {{ $mesa['forma'] }} · {{ ucfirst($mesa['estado']) }}</small></div>
                        <span class="admin-status {{ !empty($mesa['activa']) ? 'status-active' : 'status-inactive' }}">{{ !empty($mesa['activa']) ? 'Activa' : 'Archivada' }}</span>
                        <div class="admin-record-actions">
                            <a class="admin-icon-action edit-action" href="{{ route('admin.mesas.edit', $mesa['id']) }}" aria-label="Editar {{ $mesa['nombre'] }}" title="Editar"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m14.5 5.5 4 4M4 20l4.2-.9L19.4 7.9a2.1 2.1 0 0 0-3-3L5.2 16.1 4 20Z"/></svg></a>
                            <form method="POST" action="{{ route('admin.mesas.estado', $mesa['id']) }}">@csrf @method('PATCH')<input type="hidden" name="activo" value="{{ !empty($mesa['activa']) ? 0 : 1 }}"><button class="admin-icon-action archive-action" type="submit" aria-label="{{ !empty($mesa['activa']) ? 'Archivar' : 'Activar' }} {{ $mesa['nombre'] }}" title="{{ !empty($mesa['activa']) ? 'Archivar' : 'Activar' }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3.5 7h17v13h-17zM2.5 4h19v3h-19zM9 11h6"/></svg></button></form>
                        </div>
                    </article>
                @empty
                    <div class="admin-empty"><span>▦</span><strong>Aún no hay mesas</strong><small>Crea la primera para organizar el plano.</small></div>
                @endforelse
            </div>
        </div>
    </section>
    <footer class="admin-footer">La Mesa <span>·</span> Administración del restaurante</footer>
@endsection
