<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Todos los usuarios usan la contraseña "password" (la define UserFactory).
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Administrador',
            'email' => 'admin@tienda.com',
            'rol' => User::ROL_ADMIN,
        ]);

        User::factory()->create([
            'name' => 'Cliente Uno',
            'email' => 'cliente1@tienda.com',
            'rol' => User::ROL_CLIENTE,
        ]);

        User::factory()->create([
            'name' => 'Cliente Dos',
            'email' => 'cliente2@tienda.com',
            'rol' => User::ROL_CLIENTE,
        ]);
    }
}
