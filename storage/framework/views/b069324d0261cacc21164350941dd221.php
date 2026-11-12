<?php $__env->startSection('title', 'Inicio'); ?>
<?php $__env->startSection('heading', 'Inicio'); ?>

<?php $__env->startSection('content'); ?>
    <div class="worker-welcome">
        <div>
            <span class="eyebrow">PANEL DE OPERACIONES</span>
            <h2>¡Bienvenido, <?php echo e(session('usuario.full_name') ?: 'trabajador'); ?>!</h2>
            <p>Desde aquí podrás supervisar y gestionar las actividades del restaurante.</p>
        </div>
        <div class="welcome-decoration" aria-hidden="true">✦</div>
    </div>

    <div class="section-heading">
        <div><h2>Accesos rápidos</h2><p>Selecciona el área que necesitas gestionar.</p></div>
    </div>

    <div class="worker-shortcuts">
        <?php
            $accesos = [
                ['mesas', 'Mesas', 'Consulta el plano y las sesiones de atención.', '▦'],
                ['pedidos', 'Pedidos', 'Confirma pedidos y actualiza su preparación.', '▤'],
                ['reservas', 'Reservas', 'Revisa las próximas reservas.', '▣'],
                ['inventario', 'Inventario', 'Consulta ingredientes y existencias.', '▥'],
                ['ventas', 'Ventas y pagos', 'Registra ventas y pagos de pedidos.', '$'],
                ['historial', 'Historial', 'Consulta la actividad anterior.', '◷'],
            ];
        ?>
        <?php $__currentLoopData = $accesos; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$slug, $nombre, $descripcion, $icono]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <a class="shortcut-card" href="<?php echo e(route('trabajador.seccion', $slug)); ?>">
                <span class="shortcut-icon" aria-hidden="true"><?php echo e($icono); ?></span>
                <span class="shortcut-copy"><strong><?php echo e($nombre); ?></strong><small><?php echo e($descripcion); ?></small></span>
                <span class="shortcut-arrow" aria-hidden="true">→</span>
            </a>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>

    <div class="worker-note">
        <strong>Configuración inicial</strong>
        <p>La navegación y las pantallas base ya están preparadas. Los indicadores y operaciones se conectarán a Supabase al implementar cada módulo.</p>
    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.trabajador', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\restaurante-laravel\restaurante-trabajador-base\resources\views/trabajador/dashboard.blade.php ENDPATH**/ ?>