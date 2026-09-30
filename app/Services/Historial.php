<?php

namespace App\Services;

use App\Models\HistorialAcceso;

/**
 * Bitácora de auditoría: quién hizo qué y desde qué IP. Se llama desde los
 * controladores después de cada acción importante.
 */
class Historial
{
    public static function registrar(string $accion, string $descripcion, ?int $userId = null): void
    {
        HistorialAcceso::create([
            'user_id' => $userId ?? auth()->id(),
            'accion' => $accion,
            'descripcion' => $descripcion,
            'ip' => request()->ip(),
        ]);
    }
}
