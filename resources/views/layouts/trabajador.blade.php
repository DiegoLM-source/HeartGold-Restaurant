
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Panel del trabajador')</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="worker-body">

<div class="worker-shell">

    <div class="sidebar-backdrop" data-sidebar-overlay></div>

    <aside class="worker-sidebar" data-sidebar>

        <div class="worker-brand">
            <span class="brand-mark">R</span>
            <div class="brand-copy">
                <strong>Restaurante</strong>
                <small>Panel de trabajador</small>
            </div>

            <button type="button" class="sidebar-icon-button mobile-close"
                    data-sidebar-close aria-label="Cerrar menú">×</button>
        </div>

        <nav class="worker-nav">
            <span class="sidebar-caption">PRINCIPAL</span>

            <a href="{{ route('trabajador.dashboard') }}"
               class="worker-nav-link {{ request()->routeIs('trabajador.dashboard') ? 'active' : '' }}">
                <span class="nav-symbol">⌂</span>
                <span class="nav-label">Inicio</span>
            </a>

            @foreach ([
                'mesas' => ['▦', 'Mesas'],
                'pedidos' => ['▤', 'Pedidos'],
                'reservas' => ['▣', 'Reservas'],
                'inventario' => ['▥', 'Inventario'],
                'ventas' => ['$', 'Ventas y pagos'],
                'historial' => ['◷', 'Historial']
            ] as $seccion => $datos)
                <a href="{{ route('trabajador.seccion', ['seccion' => $seccion]) }}"
                   class="worker-nav-link {{ request()->segment(2) === $seccion ? 'active' : '' }}">
                    <span class="nav-symbol">{{ $datos[0] }}</span>
                    <span class="nav-label">{{ $datos[1] }}</span>
                </a>
            @endforeach
        </nav>

        <div class="worker-sidebar-bottom">
            <div class="worker-user">
                <div class="user-avatar">
                    {{ strtoupper(substr(session('usuario.full_name') ?? 'T', 0, 1)) }}
                </div>
                <div class="user-copy">
                    <strong>{{ session('usuario.full_name') ?? 'Trabajador' }}</strong>
                    <small>Personal</small>
                </div>
            </div>

            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" class="worker-logout">
                    <span class="nav-symbol">↪</span>
                    <span class="nav-label">Cerrar sesión</span>
                </button>
            </form>
        </div>

    </aside>

    <main class="worker-main">

        <header class="worker-topbar">
            <div class="topbar-leading">
                <button type="button"
                        class="sidebar-icon-button sidebar-toggle"
                        data-sidebar-toggle
                        aria-label="Abrir menú">☰</button>

                <h1>@yield('heading', 'Inicio')</h1>
            </div>

            <div class="topbar-user">
                <span class="status-dot"></span>
                {{ session('usuario.full_name') ?? 'Trabajador' }}
            </div>
        </header>

        <section class="worker-content">
            @yield('content')
        </section>

    </main>

</div>

</body>
</html>
