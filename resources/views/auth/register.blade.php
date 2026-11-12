
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Crear cuenta | Restaurante</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="login-page">

    <main class="login-container">

        <section class="login-card">

            <h1>Restaurante</h1>

            <p class="login-subtitle">
                Crea tu cuenta para disfrutar de nuestro menú.
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
                action="{{ route('register.submit', request()->only('mesa')) }}"
            >
                @csrf

                <div class="form-group">
                    <label for="full_name">Nombre completo</label>

                    <input
                        type="text"
                        id="full_name"
                        name="full_name"
                        value="{{ old('full_name') }}"
                        placeholder="Ingresa tu nombre completo"
                        autocomplete="name"
                        maxlength="150"
                        required
                    >
                </div>

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
                    <label for="phone">Número de teléfono</label>

                    <input
                        type="tel"
                        id="phone"
                        name="phone"
                        value="{{ old('phone') }}"
                        placeholder="Ingresa tu número de teléfono"
                        autocomplete="tel"
                        maxlength="20"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="password">Contraseña</label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Mínimo 8 caracteres"
                        autocomplete="new-password"
                        minlength="8"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="password_confirmation">
                        Confirmar contraseña
                    </label>

                    <input
                        type="password"
                        id="password_confirmation"
                        name="password_confirmation"
                        placeholder="Repite tu contraseña"
                        autocomplete="new-password"
                        minlength="8"
                        required
                    >
                </div>

                <button type="submit" class="login-button">
                    Crear cuenta
                </button>

            </form>

            <div class="register-login-link">
                <span>¿Ya tienes una cuenta?</span>

                <a href="{{ route('login', request()->only('mesa')) }}">
                    Iniciar sesión
                </a>
            </div>

        </section>

    </main>

</body>
</html>
