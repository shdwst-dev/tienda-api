<?php

namespace Database\Seeders;

use App\Models\Pedido;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Database\Seeder;

class PedidoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Cada cliente recibe 2 pedidos: cliente1 -> pedidos 1 y 2, cliente2 -> pedidos 3 y 4.
     */
    public function run(): void
    {
        $productos = Producto::all()->keyBy('nombre');

        $pedidosPorCliente = [
            'cliente1@tienda.com' => [
                ['enviado', 'Av. Universidad 123, Col. Centro, Querétaro, Qro.', [['Laptop Lenovo IdeaPad 15', 1], ['Mouse Logitech M185', 1]]],
                ['pendiente', 'Av. Universidad 123, Col. Centro, Querétaro, Qro.', [['Audífonos Sony WH-CH520', 2]]],
            ],
            'cliente2@tienda.com' => [
                ['pagado', 'Calle Hidalgo 45, Col. Jurica, Querétaro, Qro.', [['Smartphone Samsung Galaxy A55', 1]]],
                ['cancelado', 'Calle Hidalgo 45, Col. Jurica, Querétaro, Qro.', [['Teclado mecánico Redragon Kumara', 1], ['Mouse Logitech M185', 2]]],
            ],
        ];

        foreach ($pedidosPorCliente as $email => $pedidos) {
            $user = User::where('email', $email)->firstOrFail();

            foreach ($pedidos as [$estado, $direccion, $lineas]) {
                $pedido = Pedido::create(['user_id' => $user->id, 'estado' => $estado, 'direccion_envio' => $direccion]);
                $total = 0;

                foreach ($lineas as [$nombre, $cantidad]) {
                    $producto = $productos[$nombre];

                    $pedido->items()->create([
                        'producto_id' => $producto->id,
                        'cantidad' => $cantidad,
                        'precio_unitario' => $producto->precio,
                    ]);

                    $total += $producto->precio * $cantidad;
                }

                $pedido->update(['total' => $total]);
            }
        }
    }
}
