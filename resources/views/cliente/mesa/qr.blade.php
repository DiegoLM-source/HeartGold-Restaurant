
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Mesa {{ $mesa['nombre_mesa'] }} | Restaurante</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="mesa-qr-page">

    @php
        $estadoSolicitud = $solicitud['estado'] ?? null;
        $mesaLibre = ($mesa['estado'] ?? null) === 'libre';
        $esCliente = session('usuario.role') === 'customer';
    @endphp

    <main class="mesa-qr-container">

        <section class="mesa-qr-card">

            <div class="mesa-qr-brand">
                <span class="mesa-qr-brand-icon">R</span>
                <span>RESTAURANTE</span>
            </div>

            <div class="mesa-qr-content">

                <span class="mesa-qr-label">BIENVENIDO</span>

                <h1>{{ $mesa['nombre_mesa'] }}</h1>

                <p class="mesa-qr-description">
                    Has escaneado el código QR de esta mesa.
                    Desde aquí puedes solicitar atención para comenzar tu experiencia.
                </p>

                <div class="mesa-qr-info">
                    <div>
                        <span class="mesa-qr-info-label">Mesa</span>
                        <strong>{{ $mesa['nombre_mesa'] }}</strong>
                    </div>

                    <div>
                        <span class="mesa-qr-info-label">Capacidad</span>
                        <strong>{{ $mesa['capacidad'] }} personas</strong>
                    </div>
                </div>

                {{-- Mensajes generales --}}

                @if ($errors->has('mesa'))
                    <div class="mesa-qr-alert mesa-qr-alert-error">
                        {{ $errors->first('mesa') }}
                    </div>
                @endif

                @if (session('status'))
                    <div class="mesa-qr-alert mesa-qr-alert-success">
                        {{ session('status') }}
                    </div>
                @endif

                @if ($errorSolicitudConsulta)
                    <div class="mesa-qr-alert mesa-qr-alert-error">
                        {{ $errorSolicitudConsulta }}
                    </div>
                @endif

                {{-- Solicitud pendiente --}}

                @if ($estadoSolicitud === 'pendiente')

                    <div class="mesa-qr-status pending">
                        <span class="mesa-qr-status-dot"></span>

                        <div>
                            <strong>Solicitud pendiente</strong>
                            <p>
                                El personal está revisando tu solicitud.
                                Espera a que un trabajador autorice el acceso.
                            </p>
                        </div>
                    </div>

                {{-- Solicitud aceptada y sesión activa --}}

                @elseif ($estadoSolicitud === 'aceptada' && $sesionActiva)

                    <div class="mesa-qr-status accepted">
                        <span class="mesa-qr-status-dot"></span>

                        <div>
                            <strong>¡Solicitud aceptada!</strong>
                            <p>
                                El personal ha autorizado tu acceso.
                                Ya puedes continuar al menú.
                            </p>
                        </div>
                    </div>

                    <a href="{{ route('cliente.dashboard') }}" class="mesa-qr-button">
                        Continuar al menú
                    </a>

                {{-- Solicitud aceptada, pero sesión finalizada --}}

                @elseif ($estadoSolicitud === 'aceptada' && !$sesionActiva)

                    <div class="mesa-qr-status rejected">
                        <span class="mesa-qr-status-dot"></span>

                        <div>
                            <strong>La sesión anterior ha finalizado</strong>
                            <p>
                                Tu acceso anterior ya terminó.
                                Puedes solicitar una nueva sesión si la mesa está disponible.
                            </p>
                        </div>
                    </div>

                    @if ($esCliente && $puedeSolicitar)

                        <form method="POST" action="{{ route('mesa.solicitar', ['codigo' => $codigo]) }}">
                            @csrf

                            <button type="submit" class="mesa-qr-button">
                                Solicitar nuevo acceso
                            </button>
                        </form>

                    @endif

                {{-- Solicitud rechazada --}}

                @elseif ($estadoSolicitud === 'rechazada')

                    <div class="mesa-qr-status rejected">
                        <span class="mesa-qr-status-dot"></span>

                        <div>
                            <strong>Solicitud rechazada</strong>
                            <p>
                                El personal no pudo autorizar tu acceso.
                                Puedes consultar con un trabajador.
                            </p>
                        </div>
                    </div>

                    @if ($esCliente && $puedeSolicitar)

                        <form method="POST" action="{{ route('mesa.solicitar', ['codigo' => $codigo]) }}">
                            @csrf

                            <button type="submit" class="mesa-qr-button">
                                Volver a solicitar acceso
                            </button>
                        </form>

                    @endif

                {{-- Solicitud cancelada --}}

                @elseif ($estadoSolicitud === 'cancelada')

                    <div class="mesa-qr-status rejected">
                        <span class="mesa-qr-status-dot"></span>

                        <div>
                            <strong>Solicitud cancelada</strong>
                            <p>
                                Tu solicitud anterior fue cancelada.
                                Puedes solicitar acceso nuevamente si la mesa está disponible.
                            </p>
                        </div>
                    </div>

                    @if ($esCliente && $puedeSolicitar)

                        <form method="POST" action="{{ route('mesa.solicitar', ['codigo' => $codigo]) }}">
                            @csrf

                            <button type="submit" class="mesa-qr-button">
                                Solicitar acceso nuevamente
                            </button>
                        </form>

                    @endif

                {{-- Mesa libre y cliente sin solicitud --}}

                @elseif ($esCliente)

                    @if ($puedeSolicitar && !$errorSolicitudConsulta)

                        <form method="POST" action="{{ route('mesa.solicitar', ['codigo' => $codigo]) }}">
                            @csrf

                            <button type="submit" class="mesa-qr-button">
                                Solicitar acceso a la mesa
                            </button>
                        </form>

                    @elseif (!$mesaLibre)

                        <div class="mesa-qr-alert mesa-qr-alert-error">
                            Esta mesa no está disponible en este momento.
                        </div>

                    @endif

                {{-- Visitante sin iniciar sesión --}}

                @elseif (!session()->has('usuario'))

                    @if ($mesaLibre)

                        <div class="mesa-qr-auth">
                            <h2>Identifícate para continuar</h2>

                            <p>
                                Si ya tienes una cuenta, inicia sesión.
                                Si es tu primera visita, puedes registrarte.
                            </p>

                            <a
                                href="{{ route('register', ['mesa' => $codigo]) }}"
                                class="mesa-qr-button"
                            >
                                Crear una cuenta
                            </a>

                            <a
                                href="{{ route('login', ['mesa' => $codigo]) }}"
                                class="mesa-qr-login-link"
                            >
                                Ya tengo una cuenta
                            </a>
                        </div>

                    @else

                        <div class="mesa-qr-alert mesa-qr-alert-error">
                            Esta mesa no está disponible en este momento.
                        </div>

                    @endif

                {{-- Usuario con rol diferente a cliente --}}

                @else

                    <div class="mesa-qr-alert mesa-qr-alert-error">
                        Esta opción está disponible únicamente para clientes.
                    </div>

                @endif

            </div>

            <footer class="mesa-qr-footer">
                Gracias por visitarnos
            </footer>

        </section>

    </main>

</body>
</html>
