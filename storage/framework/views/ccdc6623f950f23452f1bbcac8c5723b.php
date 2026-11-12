


<?php $__env->startSection('title', 'Escanear QR | Restaurante'); ?>
<?php $__env->startSection('heading', 'Identificar mesa'); ?>

<?php $__env->startSection('content'); ?>

<div class="qr-page">

    <div class="qr-intro">
        <span class="cliente-eyebrow">ACCESO AL RESTAURANTE</span>
        <h2>Escanea el código QR de tu mesa</h2>
        <p>
            Apunta la cámara hacia el código QR que se encuentra en tu mesa
            para acceder a tu sesión.
        </p>
    </div>

    <div class="qr-card">

        <div class="qr-card-heading">
            <div class="qr-icon">▦</div>
            <div>
                <h3>Escáner de mesa</h3>
                <p>Ubica el código QR dentro del recuadro.</p>
            </div>
        </div>

        <div id="qr-reader"></div>

        <p id="qr-message" class="qr-message">
            Presiona iniciar para activar la cámara.
        </p>

        <button type="button" id="qr-start" class="qr-button">
            Activar cámara
        </button>

        <button type="button" id="qr-stop" class="qr-button qr-button-secondary" hidden>
            Detener cámara
        </button>

        <div class="qr-divider">
            <span>O ingresa el token manualmente</span>
        </div>

        <form action="<?php echo e(route('cliente.mesa.unirse')); ?>" method="POST">
            <?php echo csrf_field(); ?>

            <label for="qr-token">Código de la sesión</label>

            <input
                type="text"
                name="token"
                id="qr-token"
                value="<?php echo e(old('token')); ?>"
                placeholder="Pega aquí el token QR"
                required
            >

            <?php $__errorArgs = ['token'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                <span class="qr-error"><?php echo e($message); ?></span>
            <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>

            <?php $__errorArgs = ['qr'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                <span class="qr-error"><?php echo e($message); ?></span>
            <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>

            <button type="submit" class="qr-button">
                Unirme a la mesa
            </button>
        </form>

    </div>
</div>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.cliente', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\restaurante-laravel\restaurante-trabajador-base\resources\views/cliente/mesa/escanear.blade.php ENDPATH**/ ?>