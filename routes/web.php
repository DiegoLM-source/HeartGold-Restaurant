
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Cliente\AccesoController;
use App\Http\Controllers\Trabajador\DashboardController;
use App\Http\Controllers\Administrador\DashboardController as AdministradorDashboardController;

// Página principal.
Route::get('/', function () {
    if (!session()->has('usuario')) {
        return redirect()->route('login');
    }

    return match (session('usuario.role')) {
        'admin' => redirect()->route('admin.dashboard'),
        'staff' => redirect()->route('trabajador.dashboard'),
        'customer' => redirect()->route('cliente.dashboard'),
        default => redirect()->route('login'),
    };
});

// Inicio de sesión y registro.
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])
        ->name('login');

    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('login.submit');

    Route::get('/registro', [AuthController::class, 'showRegister'])
        ->name('register');

    Route::post('/registro', [AuthController::class, 'register'])
        ->middleware('throttle:5,1')
        ->name('register.submit');
});

// Cierre de sesión.
Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth.session')
    ->name('logout');

// Acceso público mediante el QR permanente de una mesa.
Route::get('/mesa/{codigo}', [AccesoController::class, 'mostrarMesa'])
    ->name('mesa.qr');

// Solicitud de acceso: solamente clientes autenticados.
Route::post('/mesa/{codigo}/solicitar', [
    AccesoController::class,
    'solicitarAcceso'
])
    ->middleware('rol:customer')
    ->name('mesa.solicitar');

// Módulos del trabajador.
Route::middleware('rol:staff')
    ->prefix('trabajador')
    ->name('trabajador.')
    ->group(function () {
        Route::get('/', [DashboardController::class, 'index'])
            ->name('dashboard');

        Route::get('/mesas/{mesa}/detalle', [
            DashboardController::class,
            'detalleMesa'
        ])->name('mesas.detalle');

        Route::get('/{seccion}', [
            DashboardController::class,
            'seccion'
        ])
            ->whereIn('seccion', [
                'mesas',
                'pedidos',
                'reservas',
                'inventario',
                'ventas',
                'historial'
            ])
            ->name('seccion');

        Route::post('/inventario/registrar-lote', [
            DashboardController::class,
            'registrarLote'
        ])->name('inventario.registrar-lote');

        Route::post('/inventario/ajustar-existencias', [
            DashboardController::class,
            'ajustarExistencias'
        ])->name('inventario.ajustar-existencias');

        Route::post('/pedidos/{pedido}/estado', [
            DashboardController::class,
            'actualizarEstadoPedido'
        ])->name('pedidos.actualizar-estado');

        Route::post('/reservas/{reserva}/estado', [
            DashboardController::class,
            'actualizarEstadoReserva'
        ])->name('reservas.actualizar-estado');

        Route::post('/solicitudes-mesa/{solicitud}/resolver', [
            DashboardController::class,
            'resolverSolicitudMesa'
        ])->name('solicitudes-mesa.resolver');
    });

// Módulos del administrador.
Route::middleware('rol:admin')
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', [
            AdministradorDashboardController::class,
            'index'
        ])->name('dashboard');
    });

// Rutas anteriores del cliente.
require __DIR__.'/cliente.php';
