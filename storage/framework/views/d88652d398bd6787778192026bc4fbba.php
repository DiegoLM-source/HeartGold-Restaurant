
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">

    <title><?php echo $__env->yieldContent('title', 'Restaurante'); ?></title>

    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
</head>
<body class="cliente-body">

<div class="cliente-shell">

    <div class="cliente-overlay" data-cliente-overlay></div>

    <aside class="cliente-sidebar" data-cliente-sidebar>

        <div class="cliente-brand">
            <div class="cliente-brand-icon">R</div>

            <div class="cliente-brand-text">
                <strong>Restaurante</strong>
                <span>Experiencia del cliente</span>
            </div>
        </div>

        <nav class="cliente-nav">
            <span class="cliente-nav-label">MI ESPACIO</span>

            <a href="<?php echo e(route('cliente.dashboard')); ?>"
               class="cliente-nav-link <?php echo e(request()->routeIs('cliente.dashboard') ? 'active' : ''); ?>">
                <span>⌂</span>
                <span>Inicio</span>
            </a>

            <span class="cliente-nav-label">RESTAURANTE</span>

            <a href="#" class="cliente-nav-link disabled" aria-disabled="true">
                <span>▦</span>
                <span>Menú</span>
            </a>

            <a href="#" class="cliente-nav-link disabled" aria-disabled="true">
                <span>▤</span>
                <span>Mis pedidos</span>
            </a>

            <a href="#" class="cliente-nav-link disabled" aria-disabled="true">
                <span>◷</span>
                <span>Mi sesión</span>
            </a>

            <a href="#" class="cliente-nav-link disabled" aria-disabled="true">
                <span>▣</span>
                <span>Reservas</span>
            </a>

            <span class="cliente-nav-label">MI CUENTA</span>

            <a href="#" class="cliente-nav-link disabled" aria-disabled="true">
                <span>▧</span>
                <span>Historial</span>
            </a>

            <a href="#" class="cliente-nav-link disabled" aria-disabled="true">
                <span>⚙</span>
                <span>Mi perfil</span>
            </a>
        </nav>

        <div class="cliente-sidebar-footer">
            <div class="cliente-user">
                <div class="cliente-avatar">
                    <?php echo e(strtoupper(substr(session('usuario.full_name') ?? session('usuario.fullname') ?? 'C', 0, 1))); ?>

                </div>

                <div class="cliente-user-info">
                    <strong>
                        <?php echo e(session('usuario.full_name') ?? session('usuario.fullname') ?? 'Cliente'); ?>

                    </strong>
                    <span>Cliente</span>
                </div>
            </div>

            <form action="<?php echo e(route('logout')); ?>" method="POST">
                <?php echo csrf_field(); ?>

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
                        aria-label="Abrir menú">
                    ☰
                </button>

                <div>
                    <span class="cliente-topbar-subtitle">RESTAURANTE</span>
                    <h1><?php echo $__env->yieldContent('heading', 'Inicio'); ?></h1>
                </div>
            </div>

            <div class="cliente-topbar-user">
                <span class="cliente-status-dot"></span>
                <span>Mi cuenta</span>
            </div>
        </header>

        <section class="cliente-content">
            <?php echo $__env->yieldContent('content'); ?>
        </section>

    </main>
</div>

</body>
</html>
<?php /**PATH C:\restaurante-laravel\restaurante-trabajador-base\resources\views/layouts/cliente.blade.php ENDPATH**/ ?>