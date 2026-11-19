@extends('layouts.admin')

@section('title', 'Personal | Administración')

@section('content')
    <section class="admin-hero" id="personal-resumen">
        <div>
            <span class="admin-eyebrow">EQUIPO DEL RESTAURANTE</span>
            <h1>Personas que hacen<br><em>posible el servicio.</em></h1>
            <p>Consulta las cuentas con rol de trabajador registradas en el restaurante.</p>
        </div>
        <div class="admin-hero-art" aria-hidden="true">
            <span class="admin-art-sun"></span>
            <span class="admin-art-plate"><i></i><b></b><small></small></span>
            <span class="admin-art-leaf leaf-one"></span>
            <span class="admin-art-leaf leaf-two"></span>
        </div>
    </section>

    @if (session('success'))
        <div class="admin-alert admin-alert-success" role="status">{{ session('success') }}</div>
    @endif
    @if (session('error') || $error)
        <div class="admin-alert admin-alert-error" role="alert">{{ session('error') ?? $error }}</div>
    @endif

    <section class="admin-stats" aria-label="Resumen del personal">
        <article class="admin-stat-card"><span class="admin-stat-icon icon-olive">♙</span><div><small>Trabajadores registrados</small><strong>{{ count($personal) }}</strong></div><span class="admin-stat-note">cuentas</span></article>
    </section>

    <section class="admin-section-heading" id="personal">
        <div><span class="admin-eyebrow">EQUIPO</span><h2>Personal</h2><p>Usuarios cuyo perfil tiene el rol de trabajador.</p></div>
        <span class="admin-section-count">{{ count($personal) }} total</span>
    </section>
    <section class="admin-list-card">
        <div class="admin-list-heading"><div><h3>Equipo registrado</h3><p>Los datos se consultan desde los perfiles de Supabase.</p></div><span>{{ count($personal) }}</span></div>
        <div class="admin-record-list">
            @forelse ($personal as $miembro)
                <article class="admin-record">
                    <span class="admin-record-thumb">{{ mb_strtoupper(mb_substr($miembro['full_name'] ?? 'T', 0, 1)) }}</span>
                    <div class="admin-record-copy"><strong>{{ $miembro['full_name'] ?? 'Nombre no disponible' }}</strong><small>ID de perfil: {{ $miembro['id'] }}</small></div>
                    <span class="admin-status status-active">Trabajador</span>
                </article>
            @empty
                <div class="admin-empty"><span>♙</span><strong>No hay trabajadores para mostrar</strong><small>Cuando existan perfiles con rol de trabajador, aparecerán aquí.</small></div>
            @endforelse
        </div>
    </section>
    <footer class="admin-footer">La Mesa <span>·</span> Administración del restaurante</footer>
@endsection
