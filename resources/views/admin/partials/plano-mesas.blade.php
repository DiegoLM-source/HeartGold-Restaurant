@php
    $borrador = $borrador ?? null;
    $mesasActivasPlano = collect($mesasPlano ?? [])->filter(fn ($mesa) => !empty($mesa['activa']) && (!isset($borrador['id']) || $mesa['id'] !== $borrador['id']));
    $posicionX = (float) ($borrador['pos_x'] ?? 30);
    $posicionY = (float) ($borrador['pos_y'] ?? 30);
    $anchoBorrador = max(45, (float) ($borrador['ancho'] ?? 100));
    $altoBorrador = max(45, (float) ($borrador['alto'] ?? 80));
    $maxX = max((float) ($mesasActivasPlano->max(fn ($mesa) => (float) ($mesa['pos_x'] ?? 0) + max(45, (float) ($mesa['ancho'] ?? 100))) ?? 0), $borrador ? $posicionX + $anchoBorrador : 0);
    $maxY = max((float) ($mesasActivasPlano->max(fn ($mesa) => (float) ($mesa['pos_y'] ?? 0) + max(45, (float) ($mesa['alto'] ?? 80))) ?? 0), $borrador ? $posicionY + $altoBorrador : 0);
    $anchoPlano = max(700, $maxX + 100);
    $altoPlano = max(420, $maxY + 100);
@endphp

<section class="admin-floorplan-card">
    <div class="admin-floorplan-heading">
        <div><span class="admin-eyebrow">DISTRIBUCIÓN DEL RESTAURANTE</span><h2>Plano de mesas</h2><p>Selecciona una mesa para editarla. Las coordenadas se expresan en píxeles.</p></div>
        <div class="admin-floorplan-legend" aria-label="Estados de mesas">
            <span><i class="free-dot"></i>Libre</span><span><i class="occupied-dot"></i>Ocupada</span><span><i class="reserved-dot"></i>Reservada</span>
        </div>
    </div>
    <div class="admin-floorplan-viewport" data-table-preview>
        <div class="admin-floorplan" data-table-preview-canvas style="width: {{ $anchoPlano }}px;height: {{ $altoPlano }}px">
            @foreach ($mesasActivasPlano as $mesaPlano)
                @php($estadoMesa = $mesaPlano['estado'] ?? 'libre')
                <a class="admin-floorplan-table state-{{ $estadoMesa }} {{ ($mesaPlano['forma'] ?? '') === 'circular' ? 'is-round' : '' }}" href="{{ route('admin.mesas.edit', $mesaPlano['id']) }}" style="left: {{ (float) ($mesaPlano['pos_x'] ?? 0) }}px;top: {{ (float) ($mesaPlano['pos_y'] ?? 0) }}px;width: {{ max(45, (float) ($mesaPlano['ancho'] ?? 100)) }}px;height: {{ max(45, (float) ($mesaPlano['alto'] ?? 80)) }}px;transform: rotate({{ (float) ($mesaPlano['rotacion'] ?? 0) }}deg)" title="{{ $mesaPlano['nombre'] }} · {{ ucfirst($estadoMesa) }} · editar">
                    <strong>{{ $mesaPlano['nombre'] }}</strong><span>{{ $mesaPlano['capacidad'] }} puestos</span>
                </a>
            @endforeach
            @if ($borrador)
                <div class="admin-floorplan-table state-draft {{ ($borrador['forma'] ?? 'cuadrada') === 'circular' ? 'is-round' : '' }}" data-table-preview-marker style="left: {{ $posicionX }}px;top: {{ $posicionY }}px;width: {{ $anchoBorrador }}px;height: {{ $altoBorrador }}px;transform: rotate({{ (float) ($borrador['rotacion'] ?? 0) }}deg)">
                    <strong data-preview-name>{{ $borrador['nombre'] ?: 'Mesa nueva' }}</strong><span><b data-preview-capacity>{{ $borrador['capacidad'] ?: 4 }}</b> puestos</span>
                </div>
            @endif
        </div>
    </div>
    @if ($borrador)
        <small class="admin-floorplan-coordinates">Vista previa <span>·</span> X: <b data-preview-x>{{ $posicionX }}</b> px <span>·</span> Y: <b data-preview-y>{{ $posicionY }}</b> px</small>
    @else
        <small class="admin-floorplan-coordinates">{{ $mesasActivasPlano->count() }} mesas activas en el plano</small>
    @endif
</section>
