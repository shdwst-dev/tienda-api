<?php

namespace App\Http\Controllers;

use App\Models\Pedido;
use App\Models\Producto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PedidoController extends Controller
{
    /**
     * BOLA/IDOR: PedidoPolicy::view solo deja pasar al dueño del pedido o a un admin (403 en otro caso).
     */
    public function show(Pedido $pedido): JsonResponse
    {
        Gate::authorize('view', $pedido);

        return response()->json($pedido->load('items.producto'));
    }

    /**
     * El pedido siempre se crea a nombre del dueño del token: cualquier user_id del body se ignora.
     */
    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.producto_id' => ['required', 'integer', 'distinct', 'exists:productos,id'],
            'items.*.cantidad' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $userId = $request->user()->id;

        $pedido = DB::transaction(function () use ($datos, $userId) {
            $productos = Producto::whereIn('id', array_column($datos['items'], 'producto_id'))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $pedido = Pedido::create(['user_id' => $userId, 'estado' => 'pendiente']);
            $total = 0;

            foreach ($datos['items'] as $item) {
                $producto = $productos[$item['producto_id']];

                if ($producto->stock < $item['cantidad']) {
                    throw ValidationException::withMessages([
                        'items' => "Stock insuficiente de \"{$producto->nombre}\" (disponibles: {$producto->stock}).",
                    ]);
                }

                $producto->decrement('stock', $item['cantidad']);

                $pedido->items()->create([
                    'producto_id' => $producto->id,
                    'cantidad' => $item['cantidad'],
                    'precio_unitario' => $producto->precio,
                ]);

                $total += $producto->precio * $item['cantidad'];
            }

            $pedido->update(['total' => round($total, 2)]);

            return $pedido;
        });

        return response()->json($pedido->load('items.producto'), 201);
    }
}
