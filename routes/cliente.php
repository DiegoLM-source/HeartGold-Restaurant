
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Cliente\DashboardController;
use App\Http\Controllers\Cliente\MesaController;

Route::prefix('cliente')
    ->name('cliente.')
    ->middleware('rol:customer')
    ->group(function () {

        Route::get('/', [DashboardController::class, 'index'])
            ->name('dashboard');

        // Flujo anterior de escaneo.
        // Se conserva mientras verificamos el nuevo QR permanente.
        Route::get('/mesa/escanear', [MesaController::class, 'escanear'])
            ->name('mesa.escanear');

        Route::post('/mesa/unirse', [MesaController::class, 'unirse'])
            ->name('mesa.unirse');

        Route::get('/mesa/confirmada', [MesaController::class, 'confirmada'])
            ->name('mesa.confirmada');
    });
