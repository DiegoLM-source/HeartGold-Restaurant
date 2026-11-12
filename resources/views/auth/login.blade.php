
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Iniciar sesión | Restaurante</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="login-page">

    <main class="login-container">

        <section class="login-card">

            <h1>Restaurante</h1>

            <p class="login-subtitle">
                Inicia sesión para continuar.
            </p>

            @if ($errors->any())
                <div class="login-error">
                    {{ $errors->first() }}
                </div>
            @endif

            @if (session('status'))
                <div class="login-success">
                    {{ session('status') }}
                </div>
            @endif

            <form
                method="POST"
                action="{{ route('login.submit', request()->only('mesa')) }}"
            >
                @csrf

                <div class="form-group">
                    <label for="email">Correo electrónico</label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="{{ old('email') }}"
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

            <div class="register-login-link">
                <span>¿Todavía no tienes una cuenta?</span>

                <a href="{{ route('register', request()->only('mesa')) }}">
                    Crear cuenta
                </a>
            </div>

        </section>

    </main>

</body>
</html>
