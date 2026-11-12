


<?php $__env->startSection('title', 'Inicio | Restaurante'); ?>
<?php $__env->startSection('heading', 'Bienvenido'); ?>

<?php $__env->startSection('content'); ?>

<div class="cliente-welcome">
    <div>
        <span class="cliente-eyebrow">TU EXPERIENCIA COMIENZA AQUÍ</span>

        <h2>
            ¡Hola,
            <?php echo e(session('usuario.full_name') ?? session('usuario.fullname') ?? 'bienvenido'); ?>!
        </h2>

        <p>
            Explora nuestro menú, realiza tus pedidos y consulta tus reservas
            desde un solo lugar.
        </p>
    </div>

    <div class="cliente-welcome-icon">
        ♨
    </div>
</div>

<div class="cliente-section-heading">
    <div>
        <span class="cliente-eyebrow">ACCESOS RÁPIDOS</span>
        <h2>¿Qué deseas hacer?</h2>
    </div>
</div>

<div class="cliente-shortcuts">

    <article class="cliente-shortcut-card">
        <div class="cliente-shortcut-icon">☷</div>
        <h3>Explorar menú</h3>
        <p>Consulta los productos y descubre lo que tenemos para ofrecerte.</p>
        <span class="cliente-coming-soon">Próximamente</span>
    </article>

    <article class="cliente-shortcut-card">
        <div class="cliente-shortcut-icon">▤</div>
        <h3>Mis pedidos</h3>
        <p>Consulta los pedidos que has realizado y sus estados.</p>
        <span class="cliente-coming-soon">Próximamente</span>
    </article>

    <article class="cliente-shortcut-card">
        <div class="cliente-shortcut-icon">▣</div>
        <h3>Reservar mesa</h3>
        <p>Organiza tu próxima visita seleccionando fecha y cantidad de personas.</p>
        <span class="cliente-coming-soon">Próximamente</span>
    </article>

    <article class="cliente-shortcut-card">
        <div class="cliente-shortcut-icon">◷</div>
        <h3>Mi historial</h3>
        <p>Consulta tus sesiones, consumos y comprobantes anteriores.</p>
        <span class="cliente-coming-soon">Próximamente</span>
    </article>

</div>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.cliente', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\restaurante-laravel\restaurante\resources\views/cliente/dashboard.blade.php ENDPATH**/ ?>