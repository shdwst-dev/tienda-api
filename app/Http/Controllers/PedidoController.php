<?php

namespace App\Http\Controllers;

use App\Models\Pedido;
use App\Models\Producto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PedidoController extends Controller
{
    /**
     * VULNERABLE (v1): BOLA/IDOR. Devuelve cualquier pedido por su id sin revisar
     * a quién pertenece.
     */
    public function show(Pedido $pedido): JsonResponse
    {
        return response()->json($pedido->load('items.producto'));
    }

    /**
     * VULNERABLE (v1): confía en el user_id que manda el cliente en el body,
     * así que se pueden crear pedidos a nombre de otra persona.
     */
    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.producto_id' => ['required', 'integer', 'distinct', 'exists:productos,id'],
            'items.*.cantidad' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $pedido = DB::transaction(function () use ($datos) {
            $productos = Producto::whereIn('id', array_column($datos['items'], 'producto_id'))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $pedido = Pedido::create(['user_id' => $datos['user_id'], 'estado' => 'pendiente']);
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
