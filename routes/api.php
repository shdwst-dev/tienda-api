<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PedidoController;
use App\Http\Controllers\ProductoController;
use Illuminate\Support\Facades\Route;

// Públicas (siguen exigiendo la API key, que va en todo el grupo api).
Route::post('/login', [AuthController::class, 'login']);
Route::get('/productos', [ProductoController::class, 'index']);

// Privadas: exigen un JWT válido.
Route::middleware('jwt')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/perfil', [AuthController::class, 'perfil']);

    Route::post('/productos', [ProductoController::class, 'store'])->middleware('rol:admin');

    Route::get('/pedidos/{pedido}', [PedidoController::class, 'show'])->where('pedido', '[0-9]{1,18}');
    Route::post('/pedidos', [PedidoController::class, 'store']);
});
