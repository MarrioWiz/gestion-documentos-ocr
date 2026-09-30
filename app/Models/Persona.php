<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Persona extends Model
{
    const CREATED_AT = 'fecha_registro';

    const UPDATED_AT = 'fecha_actualizacion';

    // Nombre provisional cuando un documento trae CURP pero no se pudo leer
    // el nombre; se reemplaza en cuanto otro documento lo traiga.
    public const NOMBRE_PENDIENTE = 'Sin nombre (completar manualmente)';

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
        'curp',
        'acta_nacimiento',
        'pasaporte',
        'cartilla_militar',
        'licencia_conducir',
    ];

    public const META_DOCUMENTO = [
        'ine' => ['label' => 'INE', 'icon' => 'bi-person-vcard-fill'],
        'curp' => ['label' => 'CURP', 'icon' => 'bi-file-earmark-text-fill'],
        'acta_nacimiento' => ['label' => 'Acta de nacimiento', 'icon' => 'bi-journal-text'],
        'pasaporte' => ['label' => 'Pasaporte', 'icon' => 'bi-airplane-fill'],
        'cartilla_militar' => ['label' => 'Cartilla militar', 'icon' => 'bi-shield-fill'],
        'licencia_conducir' => ['label' => 'Licencia de conducir', 'icon' => 'bi-car-front-fill'],
    ];

    public static function etiquetaTipo(?string $tipo): string
    {
        return self::META_DOCUMENTO[$tipo]['label'] ?? (string) $tipo;
    }

    public function documentosFaltantes(): array
    {
        $existentes = $this->documentos->pluck('tipo_documento')->all();

        return array_values(array_diff(self::TIPOS_DOCUMENTO, $existentes));
    }

    /**
     * Porcentaje del expediente cubierto (tipos distintos cargados sobre el
     * total de tipos del catálogo).
     */
    public function porcentajeCompleto(): int
    {
        $cargados = count(array_intersect(self::TIPOS_DOCUMENTO, $this->documentos->pluck('tipo_documento')->all()));

        return (int) round($cargados * 100 / count(self::TIPOS_DOCUMENTO));
    }

    /**
     * Completa (sin sobrescribir nunca) los datos que a la persona le
     * faltan con los que trae un documento nuevo.
     *
     * @param  array{nombre_completo?: ?string, fecha_nacimiento?: ?string, entidad_nacimiento?: ?string}  $datos
     * @return string[] campos que se completaron
     */
    public function completarDatosFaltantes(array $datos): array
    {
        $completados = [];

        if (in_array($this->nombre_completo, [null, '', self::NOMBRE_PENDIENTE], true) && ! empty($datos['nombre_completo'])) {
            $this->nombre_completo = $datos['nombre_completo'];
            $completados[] = 'nombre';
        }

        if ($this->fecha_nacimiento === null && ! empty($datos['fecha_nacimiento'])) {
            $this->fecha_nacimiento = $datos['fecha_nacimiento'];
            $completados[] = 'fecha de nacimiento';
        }

        if (empty($this->entidad_nacimiento) && ! empty($datos['entidad_nacimiento'])) {
            $this->entidad_nacimiento = $datos['entidad_nacimiento'];
            $completados[] = 'entidad de nacimiento';
        }

        if ($completados !== []) {
            $this->save();
        }

        return $completados;
    }

    public function iniciales(): string
    {
        $palabras = preg_split('/\s+/', trim($this->nombre_completo ?? ''));
        $palabras = array_filter($palabras);
        $iniciales = array_map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)), array_slice($palabras, 0, 2));

        return implode('', $iniciales) ?: '?';
    }
}
