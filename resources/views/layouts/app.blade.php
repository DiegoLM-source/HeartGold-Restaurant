
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Panel del trabajador')</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>

    <div class="dashboard-container">

        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>Restaurante</h2>
                <span>Panel de trabajador</span>
            </div>

            <nav class="sidebar-nav">
                <a href="{{ route('trabajador.dashboard') }}" class="nav-link">
                    Dashboard
                </a>

                <a href="#" class="nav-link">Mesas</a>
                <a href="#" class="nav-link">Pedidos</a>
                <a href="#" class="nav-link">Ventas</a>
                <a href="#" class="nav-link">Inventario</a>
                <a href="#" class="nav-link">Comprobantes</a>
                <a href="#" class="nav-link">Pagos</a>
            </nav>

            <div class="sidebar-footer">
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="logout-button">
                        Cerrar sesión
                    </button>
                </form>
            </div>
        </aside>

        <main class="main-content">
            <header class="topbar">
                <div>
                    <h3>@yield('heading', 'Dashboard')</h3>
                    <p>Panel de gestión del restaurante</p>
                </div>

                <div class="user-info">
                    <span>{{ session('usuario.fullname') ?? 'Trabajador' }}</span>
                </div>
            </header>

            <section class="content">
                @yield('content')
            </section>
        </main>

    </div>

</body>
</html>
