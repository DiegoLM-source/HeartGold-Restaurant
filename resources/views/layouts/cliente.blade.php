
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Restaurante')</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="cliente-body">

<div class="cliente-shell">

    <div class="cliente-overlay" data-cliente-overlay></div>

    <aside id="cliente-sidebar" class="cliente-sidebar" data-cliente-sidebar>

        <div class="cliente-brand">
            <div class="cliente-brand-icon">R</div>

            <div class="cliente-brand-text">
                <strong>Restaurante</strong>
                <span>Experiencia del cliente</span>
            </div>
        </div>

        <nav class="cliente-nav" aria-label="Navegación del cliente">
            <span class="cliente-nav-label">MI ESPACIO</span>

            <a href="{{ route('cliente.dashboard') }}"
               class="cliente-nav-link {{ request()->routeIs('cliente.dashboard') ? 'active' : '' }}">
                <span>⌂</span>
                <span>Inicio</span>
            </a>

            <span class="cliente-nav-label">RESTAURANTE</span>

            <a href="{{ route('cliente.menu.index') }}" class="cliente-nav-link {{ request()->routeIs('cliente.menu.*') ? 'active' : '' }}">
                <span>▦</span>
                <span>Menú</span>
            </a>

            <a href="{{ route('cliente.carrito.index') }}" class="cliente-nav-link {{ request()->routeIs('cliente.carrito.*') ? 'active' : '' }}">
                <span>▣</span>
                <span>Mi carrito</span>
            </a>

            <a href="{{ route('cliente.pedidos.index') }}" class="cliente-nav-link {{ request()->routeIs('cliente.pedidos.*') ? 'active' : '' }}">
                <span>▤</span>
                <span>Mis pedidos</span>
            </a>

            <a href="{{ route('cliente.sesion.show') }}" class="cliente-nav-link {{ request()->routeIs('cliente.sesion.*') ? 'active' : '' }}">
                <span>◷</span>
                <span>Mi sesión</span>
            </a>

            <a href="{{ route('cliente.reservas.index') }}" class="cliente-nav-link {{ request()->routeIs('cliente.reservas.*') ? 'active' : '' }}">
                <span>▣</span>
                <span>Reservas</span>
            </a>

            <span class="cliente-nav-label">MI CUENTA</span>

            <a href="{{ route('cliente.historial.index') }}" class="cliente-nav-link {{ request()->routeIs('cliente.historial.*') ? 'active' : '' }}">
                <span>▧</span>
                <span>Historial</span>
            </a>

            <a href="{{ route('cliente.perfil.edit') }}" class="cliente-nav-link {{ request()->routeIs('cliente.perfil.*') ? 'active' : '' }}">
                <span>⚙</span>
                <span>Mi perfil</span>
            </a>
        </nav>

        <div class="cliente-sidebar-footer">
            <div class="cliente-user">
                <div class="cliente-avatar">
                    {{ strtoupper(substr(session('usuario.full_name') ?? session('usuario.fullname') ?? 'C', 0, 1)) }}
                </div>

                <div class="cliente-user-info">
                    <strong>
                        {{ session('usuario.full_name') ?? session('usuario.fullname') ?? 'Cliente' }}
                    </strong>
                    <span>Cliente</span>
                </div>
            </div>

            <form action="{{ route('logout') }}" method="POST">
                @csrf

                <button type="submit" class="cliente-logout">
                    <span>↪</span>
                    Cerrar sesión
                </button>
            </form>
        </div>
    </aside>

    <main class="cliente-main">

        <header class="cliente-topbar">
            <div class="cliente-topbar-left">
                <button type="button"
                        class="cliente-menu-toggle"
                        data-cliente-toggle
                        aria-label="Abrir menú"
                        aria-expanded="false"
                        aria-controls="cliente-sidebar">
                    ☰
                </button>

                <div>
                    <span class="cliente-topbar-subtitle">RESTAURANTE</span>
                    <h1>@yield('heading', 'Inicio')</h1>
                </div>
            </div>

            <div class="cliente-topbar-user">
                <span class="cliente-status-dot"></span>
                <span>Mi cuenta</span>
            </div>
        </header>

        <section class="cliente-content">
            @yield('content')
        </section>

    </main>
</div>

</body>
</html>
