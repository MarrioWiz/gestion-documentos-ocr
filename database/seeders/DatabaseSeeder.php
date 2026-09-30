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
            ['email' => env('ADMIN_EMAIL', 'admin@gestion.test')],
            [
                'name' => env('ADMIN_NAME', 'Administrador'),
                'password' => env('ADMIN_PASSWORD', 'Admin12345'),
            ],
        );

        $admin->forceFill(['es_admin' => true])->save();

        $this->command?->info("Administrador listo: {$admin->email}");
    }
}
