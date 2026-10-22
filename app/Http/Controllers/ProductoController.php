<?php

namespace App\Http\Controllers;

use App\Models\PedidoProducto;
use App\Models\Producto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductoController extends Controller
{
    /**
     * Catálogo, con búsqueda por nombre o descripción (?buscar=) y filtro por categoría (?categoria=).
     */
    public function index(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'buscar' => ['nullable', 'string', 'max:100'],
            'categoria' => ['nullable', 'string', 'max:100'],
        ]);

        $productos = Producto::query()
            ->when($filtros['categoria'] ?? null, fn ($consulta, $categoria) => $consulta->where('categoria', $categoria))
            ->when($filtros['buscar'] ?? null, function ($consulta, $buscar) {
                // Se escapan % y _ para que la búsqueda sea literal.
                $patron = '%'.addcslashes($buscar, '%_\\').'%';

                $consulta->where(fn ($q) => $q->whereLike('nombre', $patron)->orWhereLike('descripcion', $patron));
            })
            ->orderBy('id')
            ->get();

        return response()->json($productos);
    }

    /**
     * Solo admin (la ruta lleva el middleware rol:admin).
     */
    public function store(Request $request): JsonResponse
    {
        return response()->json(Producto::create($request->validate($this->reglas())), 201);
    }

    /**
     * Solo admin.
     */
    public function update(Request $request, Producto $producto): JsonResponse
    {
        $producto->update($request->validate($this->reglas()));

        return response()->json($producto);
    }

    /**
     * Solo admin. Un producto que ya aparece en pedidos no se puede borrar (se perdería el historial).
     */
    public function destroy(Producto $producto): JsonResponse
    {
        if (PedidoProducto::where('producto_id', $producto->id)->exists()) {
            return response()->json(['message' => 'No se puede eliminar: el producto ya aparece en pedidos.'], 409);
        }

        $producto->delete();

        return response()->json(['message' => 'Producto eliminado.']);
    }

    /**
     * @return array<string, list<string>>
     */
    private function reglas(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'categoria' => ['required', 'string', 'max:100'],
            'precio' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'stock' => ['required', 'integer', 'min:0', 'max:1000000'],
        ];
    }
}
