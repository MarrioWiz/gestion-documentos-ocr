<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Documento extends Model
{
    const CREATED_AT = 'fecha_carga';
    const UPDATED_AT = null;

    // Los archivos se guardan en el disco privado (storage/app/private) y
    // solo se sirven a través de DocumentoController::archivo, con sesión.
    public const DISCO = 'local';

    protected $fillable = [
        'persona_id',
        'tipo_documento',
        'numero_documento',
        'ruta_archivo',
        'nombre_original',
        'archivo_hash',
        'texto_extraido',
        'subido_por',
    ];

    protected $casts = [
        'fecha_carga' => 'datetime',
    ];

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }

    public function subidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subido_por');
    }

    public function esImagen(): bool
    {
        return in_array(strtolower(pathinfo($this->ruta_archivo, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp'], true);
    }
}
