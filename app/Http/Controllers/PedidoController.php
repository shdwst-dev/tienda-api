<?php

namespace App\Http\Controllers;

use App\Models\Pedido;
use App\Models\Producto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PedidoController extends Controller
{
    /**
     * Un cliente recibe solo sus pedidos; el admin recibe todos, con los datos del cliente.
     * El filtro va dentro de la consulta (no hay ningún id que el cliente pueda cambiar).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $pedidos = Pedido::with($user->esAdmin() ? ['items.producto:id,nombre', 'user:id,name,email'] : ['items.producto:id,nombre'])
            ->when(! $user->esAdmin(), fn ($consulta) => $consulta->where('user_id', $user->id))
            ->latest('id')
            ->get();

        return response()->json($pedidos);
    }

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
            'direccion_envio' => ['required', 'string', 'min:5', 'max:255'],
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

            $pedido = Pedido::create([
                'user_id' => $userId,
                'estado' => Pedido::ESTADO_PENDIENTE,
                'direccion_envio' => $datos['direccion_envio'],
            ]);
            $total = 0;

            foreach ($datos['items'] as $item) {
                $producto = $productos[$item['producto_id']];

                if ($producto->stock < $item['cantidad']) {
                    throw ValidationException::withMessages([
                        'items' => "No hay suficientes piezas de \"{$producto->nombre}\" (disponibles: {$producto->stock}).",
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

    /**
     * Solo admin (la ruta lleva rol:admin). Un pedido cancelado ya no se puede reactivar y,
     * al cancelar, las piezas regresan al inventario.
     */
    public function actualizarEstado(Request $request, Pedido $pedido): JsonResponse
    {
        $datos = $request->validate([
            'estado' => ['required', Rule::in(Pedido::ESTADOS)],
        ]);

        $actualizado = DB::transaction(function () use ($pedido, $datos) {
            // Se bloquea el pedido para que dos cambios simultáneos no devuelvan el stock dos veces.
            $actual = Pedido::with('items')->lockForUpdate()->findOrFail($pedido->id);

            if ($actual->estado === Pedido::ESTADO_CANCELADO && $datos['estado'] !== Pedido::ESTADO_CANCELADO) {
                throw ValidationException::withMessages([
                    'estado' => 'Un pedido cancelado ya no se puede modificar.',
                ]);
            }

            if ($datos['estado'] === Pedido::ESTADO_CANCELADO && $actual->estado !== Pedido::ESTADO_CANCELADO) {
                foreach ($actual->items as $item) {
                    Producto::whereKey($item->producto_id)->increment('stock', $item->cantidad);
                }
            }

            $actual->update(['estado' => $datos['estado']]);

            return $actual;
        });

        return response()->json($actualizado->load('items.producto:id,nombre', 'user:id,name,email'));
    }
}
