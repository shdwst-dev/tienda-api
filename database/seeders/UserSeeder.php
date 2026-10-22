<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Todos los usuarios usan la contraseña "password". No usa UserFactory porque necesita Faker,
     * que es una dependencia de desarrollo y no está con `composer install --no-dev`.
     */
    public function run(): void
    {
        $usuarios = [
            ['Administrador', 'admin@tienda.com', User::ROL_ADMIN],
            ['Cliente Uno', 'cliente1@tienda.com', User::ROL_CLIENTE],
            ['Cliente Dos', 'cliente2@tienda.com', User::ROL_CLIENTE],
        ];

        foreach ($usuarios as [$name, $email, $rol]) {
            // forceCreate porque "rol" no es asignable en masa; el cast "hashed" de User cifra la contraseña.
            User::forceCreate([
                'name' => $name,
                'email' => $email,
                'password' => 'password',
                'rol' => $rol,
                'email_verified_at' => now(),
            ]);
        }
    }
}
