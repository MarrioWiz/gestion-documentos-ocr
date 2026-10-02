<?php

namespace App\Services;

use App\Models\Documento;
use App\Models\Persona;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Reglas para decidir a quién pertenece un documento y si se guarda:
 *
 *  - La persona se identifica por su CURP (es única por persona).
 *  - Si la persona ya existe y NO tiene ese tipo de documento, se le agrega
 *    automáticamente y se completan los datos que le falten.
 *  - Si ya lo tiene, se omite (reemplazar se hace a mano, con confirmación).
 *  - Si el mismo archivo exacto ya se había subido (misma huella SHA-256),
 *    se omite aunque el OCR lo lea distinto.
 *  - Una persona NUEVA solo se crea si la CURP pasa el dígito verificador:
 *    así un error de OCR en un solo carácter no crea un duplicado.
 */
class RegistroDocumentos
{
    /**
     * @param  array  $ocr  resultado de DocumentoOcrService::extraer
     * @return array{status: string, mensaje: string, archivo: string}&array<string, mixed>
     */
    public function registrarAutomatico(UploadedFile $archivo, array $ocr): array
    {
        $base = ['archivo' => $archivo->getClientOriginalName()];
        $hash = hash_file('sha256', $archivo->getRealPath());

        if ($repetido = Documento::with('persona')->where('archivo_hash', $hash)->first()) {
            return $base + [
                'status' => 'omitido',
                'mensaje' => 'Este mismo archivo ya estaba cargado como '.Persona::etiquetaTipo($repetido->tipo_documento).'.',
                'persona_id' => $repetido->persona_id,
                'persona_nombre' => $repetido->persona->nombre_completo,
                'tipo_documento' => $repetido->tipo_documento,
            ];
        }

        $revision = $base + [
            'status' => 'revision',
            'curp' => $ocr['curp'] ?? null,
            'nombre_completo' => $ocr['nombre_completo'] ?? null,
            'numero_documento' => $ocr['numero_documento'] ?? null,
            'tipo_documento' => $ocr['tipo_documento'] ?? null,
        ];

        if (! empty($ocr['error'])) {
            return $revision + ['mensaje' => $ocr['error']];
        }

        if (empty($ocr['tipo_documento'])) {
            return $revision + ['mensaje' => 'No se pudo identificar el tipo de documento.'];
        }

        $notas = [];

        if (empty($ocr['curp'])) {
            // Sin CURP legible (p. ej. una licencia con la foto borrosa): se
            // busca en el texto el nombre de cada persona registrada,
            // validándolo contra SU CURP. Solo se asigna si cuadra una sola.
            $persona = $this->personaIdentificadaPorNombre($ocr);

            if (! $persona) {
                $parecida = $this->personaPorNombre($ocr['nombre_completo'] ?? null);

                // array_merge (y no "+") para que la sugerencia SÍ reemplace
                // la CURP/nombre vacíos que traía $revision.
                return array_merge($revision, [
                    'mensaje' => 'No se pudo leer una CURP válida en el documento ni reconocer el nombre de alguna persona registrada.'
                        .($parecida ? " Posiblemente es de {$parecida->nombre_completo}." : ''),
                    // En la carga masiva se reintenta al final de la tanda: para
                    // entonces otro documento (CURP, INE, acta) ya pudo registrar
                    // a la persona y su nombre ya se podrá reconocer.
                    'reintentable' => true,
                    'sugerencia_persona_id' => $parecida?->id,
                    'curp' => $parecida?->curp,
                    'nombre_completo' => $parecida?->nombre_completo ?? $revision['nombre_completo'],
                ]);
            }

            $notas[] = 'La CURP no se leyó, pero el nombre del documento coincide con la CURP registrada de esta persona.';
        } else {
            $persona = $this->personaPorCurp($ocr['curp']);
        }

        if (! $persona && ! $ocr['curp_verificada']) {
            $persona = $this->personaConCurpParecida($ocr['curp'], $ocr['nombre_completo'] ?? null);

            if ($persona) {
                $notas[] = "La CURP se leyó como {$ocr['curp']} (un carácter distinto); se asoció a {$persona->curp}.";
            } elseif ($persona = $this->personaIdentificadaPorNombre($ocr)) {
                // Una CURP que no pasa el verificador y no se parece a ninguna
                // es basura del OCR (foto borrosa): vale lo mismo que no tener
                // CURP, así que se identifica a la persona por su nombre.
                $notas[] = "La CURP se leyó mal ({$ocr['curp']}), pero el nombre del documento coincide con la CURP registrada de esta persona.";
            } else {
                return $revision + [
                    'mensaje' => "La CURP leída ({$ocr['curp']}) no pasa el dígito verificador; puede tener un error de lectura. Revísala antes de crear a la persona.",
                    'reintentable' => true,
                ];
            }
        }

        $yaLoTenia = fn (Persona $p) => $base + [
            'status' => 'omitido',
            'mensaje' => 'Esta persona ya tiene '.Persona::etiquetaTipo($ocr['tipo_documento']).'; no se modificó.',
            'curp' => $p->curp,
            'persona_id' => $p->id,
            'persona_nombre' => $p->nombre_completo,
            'tipo_documento' => $ocr['tipo_documento'],
        ];

        if ($persona && $this->yaTieneTipo($persona, $ocr['tipo_documento'])) {
            return $yaLoTenia($persona);
        }

        $esNueva = false;
        $curpPersona = $persona->curp ?? $ocr['curp'];

        // Un nombre que no cuadra con la CURP suele ser otra cosa mal leída
        // (en una INE borrosa salió el domicilio). No se guarda: la persona
        // queda "Sin nombre" y otro documento (CURP, acta) lo completará.
        if (! ($ocr['nombre_verificado'] ?? true)) {
            $ocr['nombre_completo'] = null;
        }

        // Fecha y entidad salidas de una CURP mal leída no completan el expediente.
        if (! ($ocr['fecha_confiable'] ?? true)) {
            $ocr['fecha_nacimiento'] = null;
        }
        if (! ($ocr['curp_verificada'] ?? true)) {
            $ocr['entidad_nacimiento'] = null;
        }

        try {
            $documento = DB::transaction(function () use (&$persona, &$notas, &$esNueva, $archivo, $ocr, $hash) {
                if (! $persona) {
                    // createOrFirst y no create: si se suben a la vez dos
                    // documentos de la misma persona nueva (p. ej. su CURP y su
                    // acta), el que llega segundo usa a la persona que creó el
                    // primero en lugar de chocar con la CURP única.
                    $persona = Persona::createOrFirst(['curp' => $ocr['curp']], [
                        'nombre_completo' => $ocr['nombre_completo'] ?? Persona::NOMBRE_PENDIENTE,
                        'fecha_nacimiento' => $ocr['fecha_nacimiento'] ?? null,
                        'entidad_nacimiento' => $ocr['entidad_nacimiento'] ?? null,
                    ]);
                    $esNueva = $persona->wasRecentlyCreated;
                }

                if (! $esNueva && $completados = $persona->completarDatosFaltantes($ocr)) {
                    $notas[] = 'Se completó: '.implode(', ', $completados).'.';
                }

                return $this->guardar($persona, $archivo, [
                    'tipo_documento' => $ocr['tipo_documento'],
                    'numero_documento' => $ocr['numero_documento'] ?? null,
                    'texto_extraido' => $ocr['texto'] ?? null,
                ], $hash);
            });
        } catch (UniqueConstraintViolationException $e) {
            // Otra carga simultánea guardó ese mismo tipo de documento para
            // esta persona entre nuestra revisión y el guardado.
            $existente = Persona::where('curp', $curpPersona)->first();

            return $existente ? $yaLoTenia($existente) : $revision + ['mensaje' => 'Otro archivo de esta persona se estaba guardando al mismo tiempo. Vuelve a subir este.'];
        }

        $persona->load('documentos');
        $etiqueta = Persona::etiquetaTipo($documento->tipo_documento);

        Historial::registrar('subida', "Carga masiva: {$etiqueta} de {$persona->nombre_completo} ({$persona->curp})".($esNueva ? ' — persona nueva' : ''));

        return $base + [
            'status' => 'guardado',
            'mensaje' => ($esNueva ? 'Persona nueva registrada.' : "Se agregó {$etiqueta} al expediente existente.")
                .($notas ? ' '.implode(' ', $notas) : ''),
            'curp' => $persona->curp,
            'persona_id' => $persona->id,
            'persona_nombre' => $persona->nombre_completo,
            'persona_nueva' => $esNueva,
            'tipo_documento' => $documento->tipo_documento,
            'porcentaje' => $persona->porcentajeCompleto(),
            'faltantes' => array_map([Persona::class, 'etiquetaTipo'], $persona->documentosFaltantes()),
        ];
    }

    /**
     * Mueve el archivo al disco privado y crea el registro del documento.
     *
     * @param  array{tipo_documento: string, numero_documento?: ?string, texto_extraido?: ?string}  $datos
     */
    public function guardar(Persona $persona, UploadedFile $archivo, array $datos, ?string $hash = null): Documento
    {
        $extension = strtolower($archivo->getClientOriginalExtension()) ?: 'bin';
        $ruta = $archivo->storeAs("documentos/{$persona->id}", Str::uuid().'.'.$extension, Documento::DISCO);

        try {
            return Documento::create([
                'persona_id' => $persona->id,
                'tipo_documento' => $datos['tipo_documento'],
                'numero_documento' => $datos['numero_documento'] ?? null,
                'ruta_archivo' => $ruta,
                'nombre_original' => $archivo->getClientOriginalName(),
                'archivo_hash' => $hash ?? hash_file('sha256', $archivo->getRealPath()),
                'texto_extraido' => $datos['texto_extraido'] ?? null,
                'subido_por' => auth()->id(),
            ]);
        } catch (\Throwable $e) {
            // Sin registro en la base no debe quedar un archivo huérfano.
            Storage::disk(Documento::DISCO)->delete($ruta);

            throw $e;
        }
    }

    /**
     * Persona registrada cuyo nombre aparece en el texto del documento y
     * cuadra letra por letra con su CURP (ver NombreEnCurp). Si el documento
     * trae fecha de nacimiento, también debe coincidir. Solo devuelve una
     * persona si es la ÚNICA que cuadra.
     */
    private function personaIdentificadaPorNombre(array $ocr): ?Persona
    {
        $texto = $ocr['texto_completo'] ?? $ocr['texto'] ?? '';

        if (trim($texto) === '') {
            return null;
        }

        // Solo se descarta por fecha si es confiable (impresa o de CURP
        // verificada): la de una CURP mal leída descartaba a la persona correcta.
        $fecha = ($ocr['fecha_confiable'] ?? true) ? ($ocr['fecha_nacimiento'] ?? null) : null;

        $candidatas = Persona::whereNotNull('curp')->get()
            ->filter(fn (Persona $persona) => empty($fecha) || $persona->fecha_nacimiento === null
                || $persona->fecha_nacimiento->toDateString() === $fecha)
            ->map(fn (Persona $persona) => ['persona' => $persona, 'puntaje' => NombreEnCurp::puntajeEnTexto($texto, $persona->curp)])
            ->filter(fn (array $c) => $c['puntaje'] >= Curp::COINCIDENCIAS_MINIMAS_NOMBRE)
            ->sortByDesc('puntaje')
            ->values();

        // Gana la que MEJOR cuadra, y solo si nadie más empata con ella: con
        // gemelos (mismos apellidos y fecha) el nombre de uno cuadra 6 de 7
        // con la CURP del otro, pero 7 de 7 solo con la suya.
        if ($candidatas->isEmpty() || ($candidatas->count() > 1 && $candidatas[0]['puntaje'] === $candidatas[1]['puntaje'])) {
            return null;
        }

        return $candidatas[0]['persona'];
    }

    protected function personaPorCurp(string $curp): ?Persona
    {
        return Persona::where('curp', $curp)->first();
    }

    protected function yaTieneTipo(Persona $persona, string $tipo): bool
    {
        return $persona->documentos()->where('tipo_documento', $tipo)->exists();
    }

    public function archivoRepetido(UploadedFile $archivo): ?Documento
    {
        return Documento::with('persona')->where('archivo_hash', hash_file('sha256', $archivo->getRealPath()))->first();
    }

    /**
     * Persona existente cuya CURP difiere en UN solo carácter de la leída
     * (típico error de OCR). Si hay nombre leído, además debe parecerse.
     */
    private function personaConCurpParecida(string $curp, ?string $nombre): ?Persona
    {
        $candidatas = Persona::whereNotNull('curp')
            ->where('curp', 'like', substr($curp, 0, 4).'%')
            ->orWhere('curp', 'like', '%'.substr($curp, -6))
            ->get()
            ->filter(fn (Persona $p) => levenshtein($p->curp, $curp) === 1);

        if ($candidatas->count() !== 1) {
            return null;
        }

        $persona = $candidatas->first();

        return $nombre === null || self::similitudNombres($nombre, $persona->nombre_completo) >= 70 ? $persona : null;
    }

    /**
     * Sin CURP no se registra nada automáticamente, pero se sugiere la
     * persona cuyo nombre se parezca mucho, para completarlo a mano.
     */
    private function personaPorNombre(?string $nombre): ?Persona
    {
        if ($nombre === null || str_word_count($nombre) < 2) {
            return null;
        }

        return Persona::all()
            ->map(fn (Persona $p) => [$p, self::similitudNombres($nombre, $p->nombre_completo ?? '')])
            ->filter(fn (array $par) => $par[1] >= 85)
            ->sortByDesc(fn (array $par) => $par[1])
            ->first()[0] ?? null;
    }

    /**
     * Porcentaje de parecido entre dos nombres sin importar el orden de las
     * palabras (la INE pone apellidos primero; otros documentos, el nombre).
     */
    public static function similitudNombres(string $a, string $b): float
    {
        $normalizar = function (string $nombre): string {
            $palabras = preg_split('/\s+/', trim(Curp::sinAcentos($nombre)));
            sort($palabras);

            return implode(' ', $palabras);
        };

        similar_text($normalizar($a), $normalizar($b), $porcentaje);

        return $porcentaje;
    }
}
