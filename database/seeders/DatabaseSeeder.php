<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Crea (o actualiza) la cuenta de administrador inicial. Correo y
     * contraseña se toman de ADMIN_EMAIL / ADMIN_PASSWORD en el .env.
     */
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => config('app.cuenta_demo.email')],
            [
                'name' => env('ADMIN_NAME', 'Administrador'),
                'password' => config('app.cuenta_demo.password'),
            ],
        );

        $admin->forceFill(['es_admin' => true])->save();

        $this->command?->info("Administrador listo: {$admin->email}");
    }
}
