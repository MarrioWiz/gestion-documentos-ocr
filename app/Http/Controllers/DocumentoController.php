<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDocumentoRequest;
use App\Models\Documento;
use App\Models\Persona;
use App\Services\Curp;
use App\Services\DocumentoOcrService;
use App\Services\Historial;
use App\Services\RegistroDocumentos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocumentoController extends Controller
{
    public function __construct(
        private readonly DocumentoOcrService $ocr,
        private readonly RegistroDocumentos $registro,
    ) {}

    public function create(Request $request): View
    {
        return view('documentos.create', [
            'curpPrellenada' => $request->get('curp'),
            'nombrePrellenado' => $request->get('nombre_completo'),
            'tipoPrellenado' => $request->get('tipo_documento'),
        ]);
    }

    public function ocr(Request $request): JsonResponse
    {
        $request->validate([
            'archivo' => ['required', 'file', 'mimes:'.StoreDocumentoRequest::MIMES, 'max:10240'],
        ]);

        // Una foto difícil puede requerir varias pasadas de OCR.
        set_time_limit(180);

        $resultado = $this->leerArchivo($request->file('archivo'));

        if ($resultado === null) {
            return response()->json([
                'ok' => false,
                'mensaje' => 'No se pudo procesar el archivo con OCR. Llena los datos manualmente.',
            ]);
        }

        if (! empty($resultado['error'])) {
            return response()->json(['ok' => false, 'mensaje' => $resultado['error']]);
        }

        $persona = $resultado['curp'] ? Persona::with('documentos')->where('curp', $resultado['curp'])->first() : null;

        return response()->json([
            'ok' => true,
            'texto' => $resultado['texto'],
            'curp' => $resultado['curp'],
            'curp_verificada' => $resultado['curp_verificada'],
            'nombre_completo' => $persona?->nombre_completo ?? $resultado['nombre_completo'],
            'numero_documento' => $resultado['numero_documento'],
            'fecha_nacimiento' => $resultado['fecha_nacimiento'],
            'entidad_nacimiento' => $resultado['entidad_nacimiento'],
            'tipo_documento' => $resultado['tipo_documento'],
            'persona_existente' => $persona ? [
                'nombre' => $persona->nombre_completo,
                'faltantes' => array_map([Persona::class, 'etiquetaTipo'], $persona->documentosFaltantes()),
                'ya_tiene_tipo' => $resultado['tipo_documento'] !== null && ! in_array($resultado['tipo_documento'], $persona->documentosFaltantes(), true),
            ] : null,
        ]);
    }

    public function cargaMasivaForm(): View
    {
        return view('documentos.carga-masiva');
    }

    /**
     * Procesa UN archivo de la carga masiva (el navegador los manda uno por
     * uno): OCR + reglas de RegistroDocumentos. Guarda automáticamente lo
     * que puede identificar con seguridad y regresa lo demás para revisión.
     */
    public function cargaMasiva(Request $request): JsonResponse
    {
        $request->validate([
            'archivo' => ['required', 'file', 'mimes:'.StoreDocumentoRequest::MIMES, 'max:10240'],
        ]);

        set_time_limit(180);
        $archivo = $request->file('archivo');

        try {
            $resultado = $this->ocr->extraer($archivo->getRealPath());
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'status' => 'revision',
                'archivo' => $archivo->getClientOriginalName(),
                'mensaje' => 'No se pudo procesar el archivo con OCR.',
            ]);
        }

        try {
            return response()->json($this->registro->registrarAutomatico($archivo, $resultado));
        } catch (\Throwable $e) {
            // El detalle técnico queda en el log; al usuario nunca se le
            // muestra un error de base de datos.
            report($e);

            return response()->json([
                'status' => 'revision',
                'archivo' => $archivo->getClientOriginalName(),
                'curp' => $resultado['curp'] ?? null,
                'nombre_completo' => $resultado['nombre_completo'] ?? null,
                'tipo_documento' => $resultado['tipo_documento'] ?? null,
                'mensaje' => 'No se pudo guardar este archivo. Intenta subirlo de nuevo o complétalo manualmente.',
            ]);
        }
    }

    public function store(StoreDocumentoRequest $request): RedirectResponse
    {
        $datos = $request->validated();

        if ($repetido = $this->registro->archivoRepetido($request->file('archivo'))) {
            return redirect()
                ->route('documentos.create')
                ->withInput()
                ->with('warning', 'Este mismo archivo ya está cargado como '.Persona::etiquetaTipo($repetido->tipo_documento).' de '.$repetido->persona->nombre_completo.'.');
        }

        // No se confía solo en lo que dice el formulario: se vuelve a leer el
        // archivo (rápido, porque la lectura de "Extraer datos" quedó en
        // caché) y se bloquea si el documento es de otra persona o de otro tipo.
        set_time_limit(180);
        if ($problema = $this->documentoNoCorresponde($datos, $this->leerArchivo($request->file('archivo')))) {
            return redirect()
                ->route('documentos.create')
                ->withInput()
                ->with('warning', $problema);
        }

        $persona = Persona::firstOrNew(['curp' => $datos['curp']]);
        $esNueva = ! $persona->exists;
        $nombreDistinto = false;

        if ($esNueva) {
            $persona->fill([
                'nombre_completo' => $datos['nombre_completo'],
                'fecha_nacimiento' => $datos['fecha_nacimiento'] ?? Curp::fechaNacimiento($datos['curp']),
                'entidad_nacimiento' => $datos['entidad_nacimiento'] ?? Curp::entidad($datos['curp']),
            ])->save();
        } else {
            // No sobrescribimos el nombre ya guardado con el que se acaba
            // de escribir: si no coincide, es más probable que sea un
            // error de captura (o CURP repetida por coincidencia) que un
            // cambio de nombre real, así que solo avisamos.
            $nombreDistinto = RegistroDocumentos::similitudNombres($datos['nombre_completo'], $persona->nombre_completo ?? '') < 90;
            $persona->completarDatosFaltantes($datos);
        }

        $documentoExistente = $persona->documentos()
            ->where('tipo_documento', $datos['tipo_documento'])
            ->first();

        if ($documentoExistente) {
            // Guardamos el archivo nuevo en una carpeta temporal mientras
            // se confirma el reemplazo, para no pedirle al usuario que
            // vuelva a seleccionarlo.
            $rutaTemporal = $request->file('archivo')->store('temp', 'local');

            session()->put('confirmar_reemplazo', [
                'token' => (string) Str::uuid(),
                'ruta_temporal' => $rutaTemporal,
                'persona_id' => $persona->id,
                'tipo_documento' => $datos['tipo_documento'],
                'numero_documento' => $datos['numero_documento'] ?? null,
                'texto_extraido' => $datos['texto_extraido'] ?? null,
                'nombre_original' => $request->file('archivo')->getClientOriginalName(),
            ]);

            $mensaje = 'Esta persona ya tiene un documento de tipo "'.Persona::etiquetaTipo($datos['tipo_documento']).'" registrado.';
            if ($nombreDistinto) {
                $mensaje .= ' Además, el nombre que escribiste no coincide con "'.$persona->nombre_completo.'" (registrado); no se modificó.';
            }

            return redirect()
                ->route('documentos.create')
                ->withInput()
                ->with('warning', $mensaje);
        }

        $documento = $this->registro->guardar($persona, $request->file('archivo'), $datos);

        Historial::registrar('subida', 'Subida individual: '.Persona::etiquetaTipo($documento->tipo_documento)." de {$persona->nombre_completo} ({$persona->curp})");

        $mensaje = $esNueva
            ? 'Persona nueva creada y documento agregado correctamente.'
            : 'Documento agregado al expediente existente.';

        if ($nombreDistinto) {
            $mensaje .= ' El nombre que escribiste no coincide con "'.$persona->nombre_completo.'" (registrado); no se modificó.';
        }

        return redirect()
            ->route('personas.show', $persona)
            ->with('success', $mensaje);
    }

    /**
     * OCR del archivo, guardado en caché por su huella SHA-256: si el mismo
     * archivo se lee al pulsar "Extraer datos" y otra vez al guardar, el
     * segundo análisis es instantáneo. Devuelve null si el OCR falló.
     */
    private function leerArchivo(UploadedFile $archivo): ?array
    {
        try {
            return Cache::remember(
                'ocr:'.hash_file('sha256', $archivo->getRealPath()),
                now()->addHours(2),
                fn () => $this->ocr->extraer($archivo->getRealPath())
            );
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Compara lo que se capturó en el formulario con lo que realmente dice
     * el archivo. Devuelve el motivo para no guardarlo, o null si cuadra (o
     * si el OCR no pudo leer lo suficiente para decidir).
     *
     * @param  array{curp: string, tipo_documento: string}  $datos
     */
    private function documentoNoCorresponde(array $datos, ?array $lectura): ?string
    {
        if ($lectura === null || ! empty($lectura['error'])) {
            return null;
        }

        $curpDocumento = $lectura['curp'] ?? null;

        // Una CURP que no pasa el verificador y difiere en un solo carácter
        // es casi seguro un error de lectura del OCR, no otra persona.
        $esErrorDeLectura = $curpDocumento !== null && ! $lectura['curp_verificada'] && levenshtein($curpDocumento, $datos['curp']) <= 1;

        if ($curpDocumento !== null && $curpDocumento !== $datos['curp'] && ! $esErrorDeLectura) {
            $dueno = Persona::where('curp', $curpDocumento)->first();
            $deQuien = $dueno ? "{$dueno->nombre_completo} (CURP {$curpDocumento})" : "otra persona (CURP {$curpDocumento})";

            return "Este documento es de {$deQuien}, no de la CURP {$datos['curp']} que está en el formulario. No se guardó: súbelo al expediente de su dueño.";
        }

        $tipoDocumento = $lectura['tipo_documento'] ?? null;

        if ($tipoDocumento !== null && $tipoDocumento !== $datos['tipo_documento'] && ($lectura['puntaje_tipo'] ?? 0) >= 0.6) {
            return 'El archivo es '.Persona::etiquetaTipo($tipoDocumento).', pero lo marcaste como '.Persona::etiquetaTipo($datos['tipo_documento']).'. Corrige el tipo de documento antes de guardar.';
        }

        return null;
    }

    public function confirmarReemplazo(Request $request): RedirectResponse
    {
        $pendiente = $request->session()->get('confirmar_reemplazo');

        if (! $pendiente || ! Storage::disk('local')->exists($pendiente['ruta_temporal'])) {
            return redirect()->route('documentos.create')
                ->with('warning', 'No hay ningún reemplazo pendiente.');
        }

        $documento = Documento::with('persona')
            ->where('persona_id', $pendiente['persona_id'])
            ->where('tipo_documento', $pendiente['tipo_documento'])
            ->firstOrFail();

        // Elimina el archivo anterior y mueve el temporal a su ubicación final.
        Storage::disk(Documento::DISCO)->delete($documento->ruta_archivo);

        $extension = strtolower(pathinfo($pendiente['nombre_original'], PATHINFO_EXTENSION));
        $rutaFinal = "documentos/{$pendiente['persona_id']}/".Str::uuid().'.'.$extension;
        Storage::disk(Documento::DISCO)->move($pendiente['ruta_temporal'], $rutaFinal);

        $documento->update([
            'ruta_archivo' => $rutaFinal,
            'nombre_original' => $pendiente['nombre_original'],
            'archivo_hash' => hash_file('sha256', Storage::disk(Documento::DISCO)->path($rutaFinal)),
            'numero_documento' => $pendiente['numero_documento'],
            'texto_extraido' => $pendiente['texto_extraido'] ?? null,
            'subido_por' => auth()->id(),
        ]);

        $request->session()->forget('confirmar_reemplazo');

        Historial::registrar('reemplazo', 'Reemplazo de '.Persona::etiquetaTipo($documento->tipo_documento)." de {$documento->persona->nombre_completo}");

        return redirect()
            ->route('personas.show', $pendiente['persona_id'])
            ->with('success', 'Documento reemplazado correctamente.');
    }

    public function cancelarReemplazo(Request $request): RedirectResponse
    {
        $pendiente = $request->session()->get('confirmar_reemplazo');

        if ($pendiente) {
            Storage::disk('local')->delete($pendiente['ruta_temporal']);
            $request->session()->forget('confirmar_reemplazo');
        }

        return redirect()->route('documentos.create');
    }

    /**
     * Sirve el archivo desde el disco privado: solo con sesión iniciada y
     * dejando rastro en el historial.
     */
    public function archivo(Request $request, Documento $documento): BinaryFileResponse
    {
        $disco = Storage::disk(Documento::DISCO);
        abort_unless($disco->exists($documento->ruta_archivo), 404);

        // Las miniaturas de la ficha se cargan solas; solo se registra
        // cuando alguien abre el documento a propósito.
        $request->boolean('miniatura') || Historial::registrar('consulta', 'Consultó '.Persona::etiquetaTipo($documento->tipo_documento)." de {$documento->persona->nombre_completo}");

        $nombre = $documento->nombre_original ?? basename($documento->ruta_archivo);
        $respuesta = response()->file($disco->path($documento->ruta_archivo), ['X-Content-Type-Options' => 'nosniff']);
        $respuesta->setContentDisposition('inline', str_replace(['/', '\\'], '_', $nombre), preg_replace('/[^A-Za-z0-9._-]/', '_', Str::ascii($nombre)));

        return $respuesta;
    }

    public function destroy(Documento $documento): RedirectResponse
    {
        $personaId = $documento->persona_id;

        Storage::disk(Documento::DISCO)->delete($documento->ruta_archivo);
        $documento->delete();

        Historial::registrar('eliminacion', 'Eliminó '.Persona::etiquetaTipo($documento->tipo_documento)." de {$documento->persona->nombre_completo}");

        return redirect()
            ->route('personas.show', $personaId)
            ->with('success', 'Documento eliminado correctamente.');
    }
}
