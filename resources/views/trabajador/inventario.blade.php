
@extends('layouts.trabajador')

@section('title', 'Inventario | Restaurante')
@section('heading', 'Inventario')

@section('content')

@php
    $stockPorIngrediente = [];

    foreach ($lotes as $lote) {
        $idIngrediente = $lote['ingrediente_id'];

        $stockPorIngrediente[$idIngrediente] =
            ($stockPorIngrediente[$idIngrediente] ?? 0)
            + (float) $lote['cantidad_actual'];
    }

    $ingredientesBajos = 0;
    $lotesVencidos = 0;
    $lotesPorVencer = 0;

    foreach ($ingredientes as $ingrediente) {
        $stock = $stockPorIngrediente[$ingrediente['id']] ?? 0;

        if ($stock <= (float) $ingrediente['stock_minimo']) {
            $ingredientesBajos++;
        }
    }

    foreach ($lotes as $lote) {
        if ($lote['fecha_vencimiento']) {
            $vencimiento = \Carbon\Carbon::parse(
                $lote['fecha_vencimiento']
            )->startOfDay();

            if ($vencimiento->lt(now()->startOfDay())) {
                $lotesVencidos++;
            } elseif ($vencimiento->lte(now()->addDays(7)->startOfDay())) {
                $lotesPorVencer++;
            }
        }
    }

    $totalIngredientes = count($ingredientes);
    $totalLotes = count($lotes);
    $totalMovimientos = count($movimientos);
@endphp

<section class="inventory-workspace">

    <header class="inventory-header">
        <div>
            <h2>Inventario</h2>
            <p>Consulta las existencias, lotes y movimientos de ingredientes.</p>
        </div>

        
        <div class="inventory-header-actions">

            <button
                type="button"
                class="inventory-action-button"
                data-open-inventory-modal="modal-registrar-lote"
            >
                + Registrar lote
            </button>

            <button
                type="button"
                class="inventory-action-button inventory-action-secondary"
                data-open-inventory-modal="modal-ajustar-stock"
            >
                Ajustar existencias
            </button>

            <button
                type="button"
                class="inventory-refresh"
                onclick="window.location.reload()"
            >
                ↻ Actualizar
            </button>

        </div>

    </header>

    {{-- MODAL: REGISTRAR LOTE --}}

    <div
        class="inventory-modal"
        id="modal-registrar-lote"
        aria-hidden="true"
        hidden
    >
        <div class="inventory-modal-content">

            <div class="inventory-modal-header">
                <div>
                    <h3>Registrar nuevo lote</h3>
                    <p>Registra el ingreso de ingredientes al inventario.</p>
                </div>

                <button
                    type="button"
                    class="inventory-modal-close"
                    data-close-inventory-modal
                    aria-label="Cerrar"
                >
                    &times;
                </button>
            </div>

            <form
                action="{{ route('trabajador.inventario.registrar-lote') }}"
                method="POST"
                class="inventory-form"
            >
                @csrf

                <div class="inventory-form-group">
                    <label for="ingrediente_id">Ingrediente</label>

                    <select
                        name="ingrediente_id"
                        id="ingrediente_id"
                        required
                    >
                        <option value="">Selecciona un ingrediente</option>

                        @foreach ($ingredientes as $ingrediente)
                            @if ($ingrediente['activo'])
                                <option
                                    value="{{ $ingrediente['id'] }}"
                                    @selected(old('ingrediente_id') == $ingrediente['id'])
                                >
                                    {{ $ingrediente['nombre'] }}
                                    ({{ $ingrediente['unidad'] }})
                                </option>
                            @endif
                        @endforeach
                    </select>
                </div>

                <div class="inventory-form-grid">

                    <div class="inventory-form-group">
                        <label for="cantidad">Cantidad ingresada</label>

                        <input
                            type="number"
                            name="cantidad"
                            id="cantidad"
                            min="0.001"
                            step="any"
                            value="{{ old('cantidad') }}"
                            placeholder="Ej. 10"
                            required
                        >
                    </div>

                    <div class="inventory-form-group">
                        <label for="costo_total">Costo total (COP)</label>

                        <input
                            type="number"
                            name="costo_total"
                            id="costo_total"
                            min="0"
                            step="1"
                            value="{{ old('costo_total', 0) }}"
                            placeholder="Ej. 50000"
                            required
                        >
                    </div>

                </div>

                <div class="inventory-form-group">
                    <label for="proveedor_id">Proveedor</label>

                    <select name="proveedor_id" id="proveedor_id">
                        <option value="">Sin proveedor</option>

                        @foreach ($proveedores as $proveedor)
                            <option
                                value="{{ $proveedor['id'] }}"
                                @selected(old('proveedor_id') == $proveedor['id'])
                            >
                                {{ $proveedor['nombre'] }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="inventory-form-group">
                    <label for="codigo_lote">Código del lote</label>

                    <input
                        type="text"
                        name="codigo_lote"
                        id="codigo_lote"
                        maxlength="100"
                        value="{{ old('codigo_lote') }}"
                        placeholder="Ej. LOTE-2026-001"
                    >
                </div>

                <div class="inventory-form-grid">

                    <div class="inventory-form-group">
                        <label for="fecha_ingreso">Fecha de ingreso</label>

                        <input
                            type="date"
                            name="fecha_ingreso"
                            id="fecha_ingreso"
                            value="{{ old('fecha_ingreso', now()->toDateString()) }}"
                            required
                        >
                    </div>

                    <div class="inventory-form-group">
                        <label for="fecha_vencimiento">Fecha de vencimiento</label>

                        <input
                            type="date"
                            name="fecha_vencimiento"
                            id="fecha_vencimiento"
                            value="{{ old('fecha_vencimiento') }}"
                        >

                        <small>Deja este campo vacío si no tiene vencimiento.</small>
                    </div>

                </div>

                <div class="inventory-modal-actions">
                    <button
                        type="button"
                        class="inventory-button-cancel"
                        data-close-inventory-modal
                    >
                        Cancelar
                    </button>

                    <button type="submit" class="inventory-button-submit">
                        Registrar lote
                    </button>
                </div>

            </form>

        </div>
    </div>


    {{-- MODAL: AJUSTAR EXISTENCIAS --}}

    <div
        class="inventory-modal"
        id="modal-ajustar-stock"
        aria-hidden="true"
        hidden
    >
        <div class="inventory-modal-content">

            <div class="inventory-modal-header">
                <div>
                    <h3>Ajustar existencias</h3>
                    <p>Corrige la cantidad disponible de un lote.</p>
                </div>

                <button
                    type="button"
                    class="inventory-modal-close"
                    data-close-inventory-modal
                    aria-label="Cerrar"
                >
                    &times;
                </button>
            </div>

            <form
                action="{{ route('trabajador.inventario.ajustar-existencias') }}"
                method="POST"
                class="inventory-form"
            >
                @csrf

                <div class="inventory-form-group">
                    <label for="lote_id">Seleccionar lote</label>

                    <select name="lote_id" id="lote_id" required>
                        <option value="">Selecciona un lote</option>

                        @foreach ($lotes as $lote)
                            <option
                                value="{{ $lote['id'] }}"
                                @selected(old('lote_id') == $lote['id'])
                            >
                                {{ $lote['ingrediente']['nombre'] ?? 'Ingrediente no disponible' }}
                                -
                                {{ $lote['codigo_lote'] ?: 'Sin código' }}
                                (Stock: {{ $lote['cantidad_actual'] }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="inventory-form-group">
                    <label for="cantidad_ajuste">Cantidad del ajuste</label>

                    <input
                        type="number"
                        name="cantidad"
                        id="cantidad_ajuste"
                        step="any"
                        value="{{ old('cantidad') }}"
                        placeholder="Ej. 5 o -2"
                        required
                    >

                    <small>
                        Utiliza un número positivo para aumentar o uno negativo
                        para disminuir las existencias.
                    </small>
                </div>

                <div class="inventory-form-group">
                    <label for="motivo">Motivo del ajuste</label>

                    <textarea
                        name="motivo"
                        id="motivo"
                        rows="3"
                        maxlength="500"
                        placeholder="Explica por qué se realiza este ajuste..."
                        required
                    >{{ old('motivo') }}</textarea>
                </div>

                <div class="inventory-modal-actions">
                    <button
                        type="button"
                        class="inventory-button-cancel"
                        data-close-inventory-modal
                    >
                        Cancelar
                    </button>

                    <button type="submit" class="inventory-button-submit">
                        Guardar ajuste
                    </button>
                </div>

            </form>

        </div>
    </div>

    @if (!empty($error))
        <div class="inventory-alert">
            {{ $error }}
        </div>
    @endif

    {{-- Indicadores de inventario --}}

    <div class="inventory-indicators">

        <article class="inventory-indicator">
            <span class="inventory-indicator-label">Ingredientes</span>
            <strong>{{ $totalIngredientes }}</strong>
            <span class="inventory-indicator-detail">Registrados</span>
        </article>

        <article class="inventory-indicator">
            <span class="inventory-indicator-label">Stock bajo</span>
            <strong>{{ $ingredientesBajos }}</strong>
            <span class="inventory-indicator-detail">En mínimo o por debajo</span>
        </article>

        <article class="inventory-indicator">
            <span class="inventory-indicator-label">Lotes vencidos</span>
            <strong>{{ $lotesVencidos }}</strong>
            <span class="inventory-indicator-detail">Requieren atención</span>
        </article>

        <article class="inventory-indicator">
            <span class="inventory-indicator-label">Por vencer</span>
            <strong>{{ $lotesPorVencer }}</strong>
            <span class="inventory-indicator-detail">Próximos 7 días</span>
        </article>

    </div>

    {{-- Alertas --}}

    @if ($ingredientesBajos > 0 || $lotesVencidos > 0 || $lotesPorVencer > 0)
        <div class="inventory-notices">

            @if ($ingredientesBajos > 0)
                <div class="inventory-notice notice-warning">
                    <strong>{{ $ingredientesBajos }} ingrediente(s) con stock bajo.</strong>
                    Revisa las existencias disponibles.
                </div>
            @endif

            @if ($lotesVencidos > 0)
                <div class="inventory-notice notice-danger">
                    <strong>{{ $lotesVencidos }} lote(s) vencido(s).</strong>
                    No deben utilizarse en nuevos pedidos.
                </div>
            @endif

            @if ($lotesPorVencer > 0)
                <div class="inventory-notice notice-warning">
                    <strong>{{ $lotesPorVencer }} lote(s) próximos a vencer.</strong>
                    Revisa sus fechas de vencimiento.
                </div>
            @endif

        </div>
    @endif

    {{-- Navegación interna --}}

    <nav class="inventory-tabs" aria-label="Secciones de inventario">

        <button
            type="button"
            class="inventory-tab active"
            data-inventory-tab="ingredientes"
        >
            Ingredientes
        </button>

        <button
            type="button"
            class="inventory-tab"
            data-inventory-tab="lotes"
        >
            Lotes
        </button>

        <button
            type="button"
            class="inventory-tab"
            data-inventory-tab="movimientos"
        >
            Movimientos
        </button>

    </nav>

    {{-- INGREDIENTES --}}

    <section
        class="inventory-panel active"
        data-inventory-panel="ingredientes"
    >

        <div class="inventory-panel-header">
            <div>
                <h3>Ingredientes registrados</h3>
                <p>Consulta las existencias y niveles mínimos.</p>
            </div>

            <span class="inventory-count">
                {{ $totalIngredientes }} ingredientes
            </span>
        </div>

        <div class="inventory-table-wrapper">

            <table class="inventory-table">
                <thead>
                    <tr>
                        <th>Ingrediente</th>
                        <th>Tipo</th>
                        <th>Stock disponible</th>
                        <th>Stock mínimo</th>
                        <th>Estado</th>
                    </tr>
                </thead>

                <tbody>

                    @forelse ($ingredientes as $ingrediente)
                        @php
                            $stock = $stockPorIngrediente[$ingrediente['id']] ?? 0;
                            $minimo = (float) $ingrediente['stock_minimo'];

                            $stockBajo = $stock <= $minimo;
                        @endphp

                        <tr>
                            <td>
                                <div class="inventory-product-name">
                                    {{ $ingrediente['nombre'] }}
                                </div>

                                @if (!empty($ingrediente['descripcion']))
                                    <span class="inventory-secondary">
                                        {{ $ingrediente['descripcion'] }}
                                    </span>
                                @endif
                            </td>

                            <td>
                                {{ $ingrediente['tipo'] ?: 'Sin categoría' }}
                            </td>

                            <td>
                                <strong>
                                    {{ number_format($stock, 2, ',', '.') }}
                                    {{ $ingrediente['unidad'] }}
                                </strong>
                            </td>

                            <td>
                                {{ number_format($minimo, 2, ',', '.') }}
                                {{ $ingrediente['unidad'] }}
                            </td>

                            <td>
                                @if (!$ingrediente['activo'])
                                    <span class="inventory-badge badge-inactive">
                                        Inactivo
                                    </span>
                                @elseif ($stockBajo)
                                    <span class="inventory-badge badge-warning">
                                        Stock bajo
                                    </span>
                                @else
                                    <span class="inventory-badge badge-active">
                                        Disponible
                                    </span>
                                @endif
                            </td>
                        </tr>

                    @empty
                        <tr>
                            <td colspan="5" class="inventory-table-empty">
                                No hay ingredientes registrados.
                            </td>
                        </tr>
                    @endforelse

                </tbody>
            </table>

        </div>

    </section>

    {{-- LOTES --}}

    <section
        class="inventory-panel"
        data-inventory-panel="lotes"
        hidden
    >

        <div class="inventory-panel-header">
            <div>
                <h3>Lotes registrados</h3>
                <p>Consulta cantidades, proveedores y vencimientos.</p>
            </div>

            <span class="inventory-count">
                {{ $totalLotes }} lotes
            </span>
        </div>

        <div class="inventory-table-wrapper">

            <table class="inventory-table">
                <thead>
                    <tr>
                        <th>Ingrediente</th>
                        <th>Código de lote</th>
                        <th>Proveedor</th>
                        <th>Cantidad actual</th>
                        <th>Ingreso</th>
                        <th>Vencimiento</th>
                    </tr>
                </thead>

                <tbody>

                    @forelse ($lotes as $lote)
                        @php
                            $vencimiento = $lote['fecha_vencimiento']
                                ? \Carbon\Carbon::parse($lote['fecha_vencimiento'])
                                : null;

                            $vencido = $vencimiento
                                && $vencimiento->lt(now()->startOfDay());

                            $proximoVencer = $vencimiento
                                && !$vencido
                                && $vencimiento->lte(now()->addDays(7)->startOfDay());
                        @endphp

                        <tr>
                            <td>
                                <strong>
                                    {{ $lote['ingrediente']['nombre'] ?? 'Ingrediente no disponible' }}
                                </strong>
                            </td>

                            <td>
                                {{ $lote['codigo_lote'] ?: 'Sin código' }}
                            </td>

                            <td>
                                {{ $lote['proveedor']['nombre'] ?? 'Sin proveedor' }}
                            </td>

                            <td>
                                {{ number_format((float) $lote['cantidad_actual'], 2, ',', '.') }}
                            </td>

                            <td>
                                {{ \Carbon\Carbon::parse($lote['fecha_ingreso'])->format('d/m/Y') }}
                            </td>

                            <td>
                                @if (!$vencimiento)
                                    <span class="inventory-badge badge-neutral">
                                        Sin fecha
                                    </span>
                                @elseif ($vencido)
                                    <span class="inventory-badge badge-danger">
                                        Vencido
                                    </span>
                                    <div class="inventory-secondary">
                                        {{ $vencimiento->format('d/m/Y') }}
                                    </div>
                                @elseif ($proximoVencer)
                                    <span class="inventory-badge badge-warning">
                                        Próximo a vencer
                                    </span>
                                    <div class="inventory-secondary">
                                        {{ $vencimiento->format('d/m/Y') }}
                                    </div>
                                @else
                                    {{ $vencimiento->format('d/m/Y') }}
                                @endif
                            </td>
                        </tr>

                    @empty
                        <tr>
                            <td colspan="6" class="inventory-table-empty">
                                No hay lotes registrados.
                            </td>
                        </tr>
                    @endforelse

                </tbody>
            </table>

        </div>

    </section>

    {{-- MOVIMIENTOS --}}

    <section
        class="inventory-panel"
        data-inventory-panel="movimientos"
        hidden
    >

        <div class="inventory-panel-header">
            <div>
                <h3>Historial de movimientos</h3>
                <p>Consulta las entradas, salidas y ajustes realizados.</p>
            </div>

            <span class="inventory-count">
                {{ $totalMovimientos }} movimientos
            </span>
        </div>

        <div class="inventory-table-wrapper">

            <table class="inventory-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Ingrediente</th>
                        <th>Tipo</th>
                        <th>Cantidad</th>
                        <th>Trabajador</th>
                        <th>Motivo</th>
                    </tr>
                </thead>

                <tbody>

                    @forelse ($movimientos as $movimiento)
                        <tr>
                            <td>
                                {{ \Carbon\Carbon::parse($movimiento['creado_en'])->timezone(config('app.timezone'))->format('d/m/Y h:i A') }}
                            </td>

                            <td>
                                {{ $movimiento['ingrediente']['nombre'] ?? 'No disponible' }}
                            </td>

                            <td>
                                @if ($movimiento['tipo'] === 'entrada')
                                    <span class="inventory-badge badge-active">
                                        Entrada
                                    </span>
                                @elseif ($movimiento['tipo'] === 'salida')
                                    <span class="inventory-badge badge-danger">
                                        Salida
                                    </span>
                                @else
                                    <span class="inventory-badge badge-warning">
                                        Ajuste
                                    </span>
                                @endif
                            </td>

                            <td>
                                {{ number_format((float) $movimiento['cantidad'], 2, ',', '.') }}
                            </td>

                            <td>
                                {{ $movimiento['trabajador']['full_name'] ?? 'No disponible' }}
                            </td>

                            <td>
                                {{ $movimiento['motivo'] ?: 'Sin motivo registrado' }}
                            </td>
                        </tr>

                    @empty
                        <tr>
                            <td colspan="6" class="inventory-table-empty">
                                No hay movimientos registrados.
                            </td>
                        </tr>
                    @endforelse

                </tbody>
            </table>

        </div>

    </section>

</section>

@endsection
