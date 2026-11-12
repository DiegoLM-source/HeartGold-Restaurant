<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Administración | La Mesa')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="admin-body">
    <div class="admin-shell">
        <aside class="admin-sidebar">
            <a class="admin-brand" href="{{ route('admin.dashboard') }}">
                <span class="admin-brand-mark">M</span>
                <span><strong>La Mesa</strong><small>Administración</small></span>
            </a>

            <span class="admin-nav-caption">GESTIÓN DEL RESTAURANTE</span>
            <nav class="admin-nav" aria-label="Navegación de administración">
                <a href="#resumen" class="admin-nav-link active"><span>⌂</span> Resumen</a>
                <a href="#platillos" class="admin-nav-link"><span>✦</span> Platillos</a>
                <a href="#ingredientes" class="admin-nav-link"><span>◌</span> Ingredientes</a>
                <a href="#categorias" class="admin-nav-link"><span>▦</span> Categorías</a>
            </nav>

            <div class="admin-sidebar-bottom">
                <div class="admin-account">
                    <span class="admin-account-avatar">{{ strtoupper(substr(session('usuario.full_name') ?? session('usuario.fullname') ?? 'A', 0, 1)) }}</span>
                    <span><strong>{{ session('usuario.full_name') ?? session('usuario.fullname') ?? 'Administrador' }}</strong><small>Administrador</small></span>
                </div>
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button class="admin-logout" type="submit">↪ <span>Cerrar sesión</span></button>
                </form>
            </div>
        </aside>

        <main class="admin-main">
            <header class="admin-topbar">
                <span class="admin-breadcrumb">Restaurante <b>/</b> Administración</span>
                <div class="admin-topbar-actions">
                    <span class="admin-live"><i></i> Panel activo</span>
                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button class="admin-topbar-logout" type="submit" aria-label="Cerrar sesión">↪</button>
                    </form>
                </div>
            </header>
            <div class="admin-content">@yield('content')</div>
        </main>
    </div>
</body>
</html>
