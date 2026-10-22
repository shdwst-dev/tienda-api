<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PedidoController;
use App\Http\Controllers\ProductoController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
Route::get('/perfil', [AuthController::class, 'perfil']);

Route::get('/productos', [ProductoController::class, 'index']);
Route::post('/productos', [ProductoController::class, 'store']);

Route::get('/pedidos/{pedido}', [PedidoController::class, 'show'])->where('pedido', '[0-9]{1,18}');
Route::post('/pedidos', [PedidoController::class, 'store']);
