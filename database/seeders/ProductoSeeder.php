<?php

namespace Database\Seeders;

use App\Models\Producto;
use Illuminate\Database\Seeder;

class ProductoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $productos = [
            ['Laptop Lenovo IdeaPad 15', 'Laptop de 15.6" con Ryzen 5, 16 GB de RAM y SSD de 512 GB.', 12999.00, 15],
            ['Smartphone Samsung Galaxy A55', 'Teléfono con pantalla AMOLED de 6.6", 8 GB de RAM y 256 GB.', 8499.00, 30],
            ['Audífonos Sony WH-CH520', 'Audífonos Bluetooth inalámbricos con 50 horas de batería.', 1199.00, 50],
            ['Smart TV LG 50"', 'Pantalla 4K UHD con webOS y control por voz.', 9999.00, 10],
            ['Tablet Xiaomi Pad 6', 'Tablet de 11" a 144 Hz con 128 GB de almacenamiento.', 6299.00, 20],
            ['Mouse Logitech M185', 'Mouse inalámbrico de 2.4 GHz con receptor USB.', 249.00, 100],
            ['Teclado mecánico Redragon Kumara', 'Teclado mecánico compacto con retroiluminación LED.', 899.00, 40],
            ['Smartwatch Amazfit Bip 5', 'Reloj inteligente con GPS, pantalla de 1.91" y 10 días de batería.', 1499.00, 25],
        ];

        foreach ($productos as [$nombre, $descripcion, $precio, $stock]) {
            Producto::create(compact('nombre', 'descripcion', 'precio', 'stock'));
        }
    }
}
