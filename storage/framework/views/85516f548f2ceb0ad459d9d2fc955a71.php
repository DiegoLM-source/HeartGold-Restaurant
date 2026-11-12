<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Iniciar sesión | Restaurante</title>

    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
</head>
<body class="login-page">

    <main class="login-container">
        <section class="login-card">

            <h1>Restaurante</h1>
            <p class="login-subtitle">Inicia sesión para continuar</p>

            <?php if($errors->any()): ?>
                <div class="login-error">
                    <?php echo e($errors->first()); ?>

                </div>
            <?php endif; ?>

            <form method="POST" action="<?php echo e(route('login.submit')); ?>">
                <?php echo csrf_field(); ?>

                <div class="form-group">
                    <label for="email">Correo electrónico</label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="<?php echo e(old('email')); ?>"
                        placeholder="Ingresa tu correo"
                        autocomplete="email"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="password">Contraseña</label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Ingresa tu contraseña"
                        autocomplete="current-password"
                        required
                    >
                </div>

                <button type="submit" class="login-button">
                    Iniciar sesión
                </button>
            </form>

        </section>
    </main>

</body>
</html><?php /**PATH C:\restaurante-laravel\restaurante-trabajador-base\resources\views/auth/login.blade.php ENDPATH**/ ?>