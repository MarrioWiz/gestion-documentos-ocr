<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Persona extends Model
{
    const CREATED_AT = 'fecha_registro';

    const UPDATED_AT = 'fecha_actualizacion';

    protected $fillable = [
        'curp',
        'nombre_completo',
        'fecha_nacimiento',
        'entidad_nacimiento',
    ];

    protected $casts = [
        'fecha_nacimiento' => 'date',
    ];

    public function documentos(): HasMany
    {
        return $this->hasMany(Documento::class);
    }

    public const TIPOS_DOCUMENTO = [
        'ine',
        'pasaporte',
        'cartilla_militar',
        'curp',
        'licencia_conducir',
    ];

    public const META_DOCUMENTO = [
        'ine' => ['label' => 'INE', 'icon' => 'bi-person-vcard-fill'],
        'pasaporte' => ['label' => 'Pasaporte', 'icon' => 'bi-airplane-fill'],
        'cartilla_militar' => ['label' => 'Cartilla militar', 'icon' => 'bi-shield-fill'],
        'curp' => ['label' => 'CURP', 'icon' => 'bi-file-earmark-text-fill'],
        'licencia_conducir' => ['label' => 'Licencia de conducir', 'icon' => 'bi-car-front-fill'],
    ];

    public function documentosFaltantes(): array
    {
        $existentes = $this->documentos->pluck('tipo_documento')->all();

        return array_values(array_diff(self::TIPOS_DOCUMENTO, $existentes));
    }

    public function iniciales(): string
    {
        $palabras = preg_split('/\s+/', trim($this->nombre_completo ?? ''));
        $palabras = array_filter($palabras);
        $iniciales = array_map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)), array_slice($palabras, 0, 2));

        return implode('', $iniciales) ?: '?';
    }
}
