
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Cliente\DashboardController;
use App\Http\Controllers\Cliente\MesaController;
use App\Http\Controllers\Cliente\PedidoController;
use App\Http\Controllers\Cliente\PerfilController;
use App\Http\Controllers\Cliente\ReservaController;
use App\Http\Controllers\Cliente\HistorialController;
use App\Http\Controllers\Cliente\SesionController;
use App\Http\Controllers\Cliente\MenuController;
use App\Http\Controllers\Cliente\CarritoController;

Route::prefix('cliente')
    ->name('cliente.')
    ->middleware('rol:customer')
    ->group(function () {

        Route::get('/', [DashboardController::class, 'index'])
            ->name('dashboard');

        Route::get('/pedidos', [PedidoController::class, 'index'])
            ->name('pedidos.index');

        Route::get('/menu', [MenuController::class, 'index'])
            ->name('menu.index');
        Route::get('/carrito', [CarritoController::class, 'index'])
            ->name('carrito.index');
        Route::post('/carrito/agregar', [CarritoController::class, 'agregar'])
            ->name('carrito.agregar');
        Route::put('/carrito/{producto}', [CarritoController::class, 'actualizar'])
            ->name('carrito.actualizar');
        Route::delete('/carrito/{producto}', [CarritoController::class, 'eliminar'])
            ->name('carrito.eliminar');
        Route::post('/carrito/enviar', [CarritoController::class, 'enviar'])
            ->middleware('throttle:5,1')
            ->name('carrito.enviar');

        Route::get('/perfil', [PerfilController::class, 'edit'])
            ->name('perfil.edit');
        Route::put('/perfil', [PerfilController::class, 'update'])
            ->name('perfil.update');

        Route::get('/reservas', [ReservaController::class, 'index'])
            ->name('reservas.index');
        Route::get('/reservas/crear', [ReservaController::class, 'create'])
            ->name('reservas.create');
        Route::post('/reservas', [ReservaController::class, 'store'])
            ->middleware('throttle:5,1')
            ->name('reservas.store');
        Route::post('/reservas/{reserva}/cancelar', [ReservaController::class, 'cancelar'])
            ->name('reservas.cancelar');

        Route::get('/historial', [HistorialController::class, 'index'])
            ->name('historial.index');

        Route::get('/sesion', [SesionController::class, 'show'])
            ->name('sesion.show');

        // Flujo anterior de escaneo.
        // Se conserva mientras verificamos el nuevo QR permanente.
        Route::get('/mesa/escanear', [MesaController::class, 'escanear'])
            ->name('mesa.escanear');

        Route::post('/mesa/unirse', [MesaController::class, 'unirse'])
            ->name('mesa.unirse');

        Route::get('/mesa/confirmada', [MesaController::class, 'confirmada'])
            ->name('mesa.confirmada');
    });
