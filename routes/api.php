<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PedidoController;
use App\Http\Controllers\ProductoController;
use Illuminate\Support\Facades\Route;

// Todas las rutas pasan por la API key y por el límite general "throttle:api" (bootstrap/app.php).
// Login y crear pedidos lo sustituyen por uno propio y más estricto.

// Públicas.
Route::post('/login', [AuthController::class, 'login'])
    ->withoutMiddleware('throttle:api')
    ->middleware('throttle:login');
Route::get('/productos', [ProductoController::class, 'index']);

// Privadas: exigen un JWT válido.
Route::middleware('jwt')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/perfil', [AuthController::class, 'perfil']);

    Route::post('/productos', [ProductoController::class, 'store'])->middleware('rol:admin');

    Route::get('/pedidos/{pedido}', [PedidoController::class, 'show'])->where('pedido', '[0-9]{1,18}');
    Route::post('/pedidos', [PedidoController::class, 'store'])
        ->withoutMiddleware('throttle:api')
        ->middleware('throttle:pedidos');
});
