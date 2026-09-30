<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistorialAcceso extends Model
{
    protected $table = 'historial_accesos';

    const CREATED_AT = 'fecha';
    const UPDATED_AT = null;

    public const ACCIONES = [
        'login' => ['label' => 'Inicio de sesión', 'icon' => 'bi-box-arrow-in-right'],
        'logout' => ['label' => 'Cierre de sesión', 'icon' => 'bi-box-arrow-left'],
        'login_fallido' => ['label' => 'Intento fallido', 'icon' => 'bi-shield-exclamation'],
        'subida' => ['label' => 'Subida', 'icon' => 'bi-cloud-upload'],
        'reemplazo' => ['label' => 'Reemplazo', 'icon' => 'bi-arrow-repeat'],
        'consulta' => ['label' => 'Consulta de archivo', 'icon' => 'bi-eye'],
        'eliminacion' => ['label' => 'Eliminación', 'icon' => 'bi-trash3'],
        'usuarios' => ['label' => 'Gestión de usuarios', 'icon' => 'bi-people'],
        'reporte' => ['label' => 'Reporte', 'icon' => 'bi-file-earmark-spreadsheet'],
    ];

    protected $fillable = [
        'user_id',
        'accion',
        'descripcion',
        'ip',
    ];

    protected $casts = [
        'fecha' => 'datetime',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
