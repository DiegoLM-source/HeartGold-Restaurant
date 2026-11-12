@extends('layouts.trabajador')

@section('title', 'Pedidos | Restaurante')
@section('heading', 'Pedidos')

@section('content')
<section class="orders-workspace">

    <header class="orders-header">
        <div>
            <h2>Pedidos</h2>
            <p>Consulta y supervisa los pedidos del restaurante.</p>
        </div>

        <button type="button" class="orders-refresh" onclick="window.location.reload()">
            ↻ Actualizar
        </button>
    </header>

    @if (!empty($error))
        <div class="orders-alert orders-alert-error">
            {{ $error }}
        </div>
    @endif

    @if (session('success'))
        <div class="orders-alert orders-alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="orders-alert orders-alert-error">
            {{ session('error') }}
        </div>
    @endif

    <div class="orders-toolbar">
        <div class="orders-filters">
            <button type="button" class="order-filter active" data-order-filter="todos">
                Todos
            </button>

            <button type="button" class="order-filter" data-order-filter="pendiente">
                Pendientes
            </button>

            <button type="button" class="order-filter" data-order-filter="confirmado">
                Confirmados
            </button>

            <button type="button" class="order-filter" data-order-filter="en_preparacion">
                En preparación
            </button>

            <button type="button" class="order-filter" data-order-filter="listo">
                Listos
            </button>

            <button type="button" class="order-filter" data-order-filter="entregado">
                Entregados
            </button>

            <button type="button" class="order-filter" data-order-filter="cancelado">
                Cancelados
            </button>

            <button type="button" class="order-filter" data-order-filter="rechazado">
                Rechazados
            </button>
        </div>


        <div class="orders-search">
            <input
                type="search"
                id="order-search"
                placeholder="Buscar pedido, mesa o cliente..."
                autocomplete="off"
            >
        </div>
    </div>

    <div class="orders-content" id="orders-container">

        @if (empty($pedidos))
            <div class="orders-empty">
                <span class="orders-empty-icon">▤</span>
                <h3>No hay pedidos registrados</h3>
                <p>Cuando los clientes realicen pedidos, aparecerán en esta sección.</p>
            </div>
        @else

            <div class="orders-list">

                @foreach ($pedidos as $pedido)
                    @php
                        $estado = $pedido['estado'];

                        $estados = [
                            'pendiente' => 'Pendiente',
                            'confirmado' => 'Confirmado',
                            'en_preparacion' => 'En preparación',
                            'listo' => 'Listo',
                            'entregado' => 'Entregado',
                            'cancelado' => 'Cancelado',
                            'rechazado' => 'Rechazado',
                        ];

                        $fecha = $pedido['creado_en']
                            ? \Carbon\Carbon::parse($pedido['creado_en'])
                                ->timezone(config('app.timezone'))
                                ->format('d/m/Y H:i')
                            : 'Fecha no disponible';

                        $mesa = $pedido['mesa']['nombre'] ?? 'Sin mesa';
                        $cliente = $pedido['cliente']['full_name'] ?? 'Cliente no disponible';
                    @endphp

                    <article
                        class="order-card"
                        data-order-card
                        data-order-state="{{ $estado }}"
                        data-order-search="{{ strtolower($pedido['id'] . ' ' . $mesa . ' ' . $cliente) }}"
                    >

                        <div class="order-card-main">

                            <div class="order-card-info">
                                <div class="order-card-title">
                                    <span class="order-number">
                                        Pedido #{{ strtoupper(substr($pedido['id'], 0, 8)) }}
                                    </span>

                                    <span class="order-status status-{{ $estado }}">
                                        {{ $estados[$estado] ?? ucfirst($estado) }}
                                    </span>
                                </div>

                                <div class="order-card-meta">
                                    <span>▦ {{ $mesa }}</span>
                                    <span>♙ {{ $cliente }}</span>
                                    <span>◷ {{ $fecha }}</span>
                                </div>
                            </div>

                            <div class="order-card-total">
                                <span>Total</span>
                                <strong>
                                    ${{ number_format($pedido['total'], 0, ',', '.') }}
                                </strong>
                            </div>

                        </div>

                        <div class="order-products">
                            <div class="order-products-heading">
                                Productos del pedido
                            </div>

                            @forelse ($pedido['productos'] as $producto)
                                <div class="order-product-row">
                                    <div>
                                        <strong>{{ $producto['nombre_producto'] }}</strong>
                                        <span>
                                            {{ $producto['cantidad'] }} ×
                                            ${{ number_format($producto['precio_unitario'], 0, ',', '.') }}
                                        </span>
                                    </div>

                                    <strong>
                                        ${{ number_format($producto['subtotal'], 0, ',', '.') }}
                                    </strong>
                                </div>
                            @empty
                                <p class="order-no-products">
                                    No hay productos registrados en este pedido.
                                </p>
                            @endforelse
                        </div>

                        @if (!empty($pedido['observaciones']))
                            <div class="order-observations">
                                <strong>Observaciones:</strong>
                                {{ $pedido['observaciones'] }}
                            </div>
                        @endif

                        @if (in_array($estado, ['pendiente', 'confirmado', 'en_preparacion', 'listo']))
                            <div class="order-actions">

                                @if ($estado === 'pendiente')
                                    <form
                                        method="POST"
                                        action="{{ route('trabajador.pedidos.actualizar-estado', $pedido['id']) }}"
                                    >
                                        @csrf
                                        <input type="hidden" name="estado" value="confirmado">
                                        <button type="submit" class="order-action-btn order-action-primary">
                                            Confirmar pedido
                                        </button>
                                    </form>

                                    <form
                                        method="POST"
                                        action="{{ route('trabajador.pedidos.actualizar-estado', $pedido['id']) }}"
                                        onsubmit="return confirm('¿Deseas cancelar este pedido?');"
                                    >
                                        @csrf
                                        <input type="hidden" name="estado" value="cancelado">
                                        <button type="submit" class="order-action-btn order-action-danger">
                                            Cancelar
                                        </button>
                                    </form>

                                    <form
                                        method="POST"
                                        action="{{ route('trabajador.pedidos.actualizar-estado', $pedido['id']) }}"
                                        onsubmit="return confirm('¿Deseas rechazar este pedido?');"
                                    >
                                        @csrf
                                        <input type="hidden" name="estado" value="rechazado">
                                        <button type="submit" class="order-action-btn order-action-secondary">
                                            Rechazar
                                        </button>
                                    </form>

                                @elseif ($estado === 'confirmado')
                                    <form method="POST" action="{{ route('trabajador.pedidos.actualizar-estado', $pedido['id']) }}">
                                        @csrf
                                        <input type="hidden" name="estado" value="en_preparacion">
                                        <button type="submit" class="order-action-btn order-action-primary">
                                            Iniciar preparación
                                        </button>
                                    </form>

                                @elseif ($estado === 'en_preparacion')
                                    <form method="POST" action="{{ route('trabajador.pedidos.actualizar-estado', $pedido['id']) }}">
                                        @csrf
                                        <input type="hidden" name="estado" value="listo">
                                        <button type="submit" class="order-action-btn order-action-primary">
                                            Marcar como listo
                                        </button>
                                    </form>

                                @elseif ($estado === 'listo')
                                    <form method="POST" action="{{ route('trabajador.pedidos.actualizar-estado', $pedido['id']) }}">
                                        @csrf
                                        <input type="hidden" name="estado" value="entregado">
                                        <button type="submit" class="order-action-btn order-action-primary">
                                            Marcar como entregado
                                        </button>
                                    </form>
                                @endif

                            </div>
                        @endif


                    </article>
                @endforeach

            </div>

        @endif
    </div>

</section>
@endsection
