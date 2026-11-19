
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
            <aside class="login-feature" aria-label="Bienvenida">
                <div class="login-feature-pattern" aria-hidden="true"></div>

                <div class="login-brand">
                    <span class="login-brand-mark" aria-hidden="true">
                        <svg viewBox="0 0 40 40" role="img">
                            <path d="M12 9.5h16v4H12zM15 13.5h10l-1.4 13h-7.2zM12.5 26.5h15v4h-15z"/>
                        </svg>
                    </span>
                    <span>
                        <strong>La Mesa</strong>
                        <small>Restaurante</small>
                    </span>
                </div>

                <div class="login-feature-copy">
                    <span class="login-eyebrow">Buen servicio, mejor experiencia</span>
                    <h2>Tu mesa<br>te está esperando.</h2>
                    <p>Gestiona pedidos, reservas y la experiencia de tu restaurante desde un solo lugar.</p>
                </div>

                <div class="login-feature-footer">
                    <span class="login-feature-dot"></span>
                    <span>Atención personalizada</span>
                </div>
            </aside>

            <div class="login-form-panel">
                <div class="login-form-heading">
                    <span class="login-form-kicker">Bienvenido de nuevo</span>
                    <h1>Iniciar sesión</h1>
                    <p>Ingresa tus datos para continuar.</p>
                </div>

                @if ($errors->any())
                    <div class="login-error" role="alert">
                        <svg viewBox="0 0 20 20" aria-hidden="true">
                            <path d="M10 2.2a7.8 7.8 0 1 0 0 15.6 7.8 7.8 0 0 0 0-15.6Zm-.85 4h1.7v5.2h-1.7V6.2Zm0 6.7h1.7v1.7h-1.7v-1.7Z"/>
                        </svg>
                        <span>{{ $errors->first() }}</span>
                    </div>
                @endif

                @if (session('status'))
                    <div class="login-success" role="status">
                        {{ session('status') }}
                    </div>
                @endif

                <form
                    method="POST"
                    action="{{ route('login.submit', request()->only('mesa')) }}"
                    class="login-form"
                >
                    @csrf

                    <div class="form-group">
                        <label for="email">Correo electrónico</label>
                        <div class="login-input-wrap">
                            <svg viewBox="0 0 20 20" aria-hidden="true">
                                <path d="M3 4h14a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1Zm.4 2.1 6.6 5.1 6.6-5.1V5.7L10 10.8 3.4 5.7v.4Z"/>
                            </svg>
                            <input
                                type="email"
                                id="email"
                                name="email"
                                value="{{ old('email') }}"
                                placeholder="tu@email.com"
                                autocomplete="email"
                                required
                            >
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="password">Contraseña</label>
                        <div class="login-input-wrap">
                            <svg viewBox="0 0 20 20" aria-hidden="true">
                                <path d="M5.7 8V6.3a4.3 4.3 0 0 1 8.6 0V8h1A1.7 1.7 0 0 1 17 9.7v6A1.7 1.7 0 0 1 15.3 17H4.7A1.7 1.7 0 0 1 3 15.3v-6A1.7 1.7 0 0 1 4.7 8h1Zm1.7 0h5.2V6.3a2.6 2.6 0 0 0-5.2 0V8ZM10 10.7a1.2 1.2 0 0 0-.6 2.2v1.4h1.2v-1.4a1.2 1.2 0 0 0-.6-2.2Z"/>
                            </svg>
                            <input
                                type="password"
                                id="password"
                                name="password"
                                placeholder="Ingresa tu contraseña"
                                autocomplete="current-password"
                                required
                            >
                        </div>
                    </div>

                    <button type="submit" class="login-button">
                        <span>Ingresar</span>
                        <svg viewBox="0 0 20 20" aria-hidden="true">
                            <path d="M4 10h10.7l-3.4-3.4 1.4-1.4 5.8 5.8-5.8 5.8-1.4-1.4 3.4-3.4H4v-2Z"/>
                        </svg>
                    </button>
                </form>

                <div class="register-login-link">
                    <span>¿Todavía no tienes una cuenta?</span>
                    <a href="{{ route('register', request()->only('mesa')) }}">
                        Crear cuenta
                    </a>
                </div>
            </div>

        </section>

    </main>

</body>
</html>
