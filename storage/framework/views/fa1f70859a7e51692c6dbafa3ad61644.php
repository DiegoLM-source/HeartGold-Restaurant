
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">

    <title><?php echo $__env->yieldContent('title', 'Panel del trabajador'); ?></title>

    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
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

            <a href="<?php echo e(route('trabajador.dashboard')); ?>"
               class="worker-nav-link <?php echo e(request()->routeIs('trabajador.dashboard') ? 'active' : ''); ?>">
                <span class="nav-symbol">⌂</span>
                <span class="nav-label">Inicio</span>
            </a>

            <?php $__currentLoopData = [
                'mesas' => ['▦', 'Mesas'],
                'pedidos' => ['▤', 'Pedidos'],
                'reservas' => ['▣', 'Reservas'],
                'inventario' => ['▥', 'Inventario'],
                'ventas' => ['$', 'Ventas y pagos'],
                'historial' => ['◷', 'Historial']
            ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $seccion => $datos): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <a href="<?php echo e(route('trabajador.seccion', ['seccion' => $seccion])); ?>"
                   class="worker-nav-link <?php echo e(request()->segment(2) === $seccion ? 'active' : ''); ?>">
                    <span class="nav-symbol"><?php echo e($datos[0]); ?></span>
                    <span class="nav-label"><?php echo e($datos[1]); ?></span>
                </a>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </nav>

        <div class="worker-sidebar-bottom">
            <div class="worker-user">
                <div class="user-avatar">
                    <?php echo e(strtoupper(substr(session('usuario.full_name') ?? 'T', 0, 1))); ?>

                </div>
                <div class="user-copy">
                    <strong><?php echo e(session('usuario.full_name') ?? 'Trabajador'); ?></strong>
                    <small>Personal</small>
                </div>
            </div>

            <form action="<?php echo e(route('logout')); ?>" method="POST">
                <?php echo csrf_field(); ?>
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

                <h1><?php echo $__env->yieldContent('heading', 'Inicio'); ?></h1>
            </div>

            <div class="topbar-user">
                <span class="status-dot"></span>
                <?php echo e(session('usuario.full_name') ?? 'Trabajador'); ?>

            </div>
        </header>

        <section class="worker-content">
            <?php echo $__env->yieldContent('content'); ?>
        </section>

    </main>

</div>

</body>
</html>
<?php /**PATH C:\restaurante-laravel\restaurante\resources\views/layouts/trabajador.blade.php ENDPATH**/ ?>