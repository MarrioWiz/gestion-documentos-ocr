<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Documento extends Model
{
    const CREATED_AT = 'fecha_carga';
    const UPDATED_AT = null;

    protected $fillable = [
        'persona_id',
        'tipo_documento',
        'numero_documento',
        'ruta_archivo',
        'texto_extraido',
    ];

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }
}
