<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDocumentoRequest;
use App\Models\Documento;
use App\Models\Persona;
use App\Services\DocumentoOcrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DocumentoController extends Controller
{
    public function create(Request $request): View
    {
        return view('documentos.create', [
            'curpPrellenada' => $request->get('curp'),
            'nombrePrellenado' => $request->get('nombre_completo'),
        ]);
    }

    public function ocr(Request $request, DocumentoOcrService $ocr): JsonResponse
    {
        $request->validate([
            'archivo' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
        ]);

        $archivo = $request->file('archivo');
        $extension = strtolower($archivo->getClientOriginalExtension());

        if (! $ocr->soportaOcr($extension)) {
            return response()->json([
                'ok' => false,
                'mensaje' => 'Ese formato de archivo no se puede procesar con OCR. Llena los datos manualmente.',
            ]);
        }

        try {
            $resultado = $ocr->extraer($archivo->getRealPath());
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'ok' => false,
                'mensaje' => 'No se pudo procesar el archivo con OCR. Llena los datos manualmente.',
            ]);
        }

        if (! empty($resultado['error'])) {
            return response()->json([
                'ok' => false,
                'mensaje' => $resultado['error'],
            ]);
        }

        return response()->json([
            'ok' => true,
            'texto' => $resultado['texto'],
            'curp' => $resultado['curp'],
            'nombre_completo' => $resultado['nombre_completo'],
            'numero_documento' => $resultado['numero_documento'],
            'fecha_nacimiento' => $resultado['fecha_nacimiento'],
            'entidad_nacimiento' => $resultado['entidad_nacimiento'],
        ]);
    }

    public function cargaMasivaForm(): View
    {
        return view('documentos.carga-masiva');
    }

    /**
     * Procesa UN archivo de la carga masiva: hace OCR, adivina CURP y tipo
     * de documento, y si tiene suficiente confianza lo guarda directo.
     * Si la persona ya tiene ese tipo de documento, lo omite (no lo pisa
     * automáticamente; para reemplazar se usa la subida individual).
     * Si no logra detectar CURP o tipo, no guarda nada y regresa lo que sí
     * pudo extraer para que se complete a mano.
     */
    public function cargaMasiva(Request $request, DocumentoOcrService $ocr): JsonResponse
    {
        $request->validate([
            'archivo' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
        ]);

        $archivo = $request->file('archivo');
        $nombreArchivo = $archivo->getClientOriginalName();

        if (! $ocr->soportaOcr(strtolower($archivo->getClientOriginalExtension()))) {
            return response()->json([
                'status' => 'revision',
                'archivo' => $nombreArchivo,
                'mensaje' => 'Formato no soportado para OCR automático.',
            ]);
        }

        try {
            $resultado = $ocr->extraer($archivo->getRealPath());
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'status' => 'revision',
                'archivo' => $nombreArchivo,
                'mensaje' => 'No se pudo procesar el archivo con OCR.',
            ]);
        }

        if (! empty($resultado['error'])) {
            return response()->json([
                'status' => 'revision',
                'archivo' => $nombreArchivo,
                'mensaje' => $resultado['error'],
            ]);
        }

        $curp = $resultado['curp'];
        $tipo = $resultado['tipo_documento'];

        if (! $curp || ! $tipo) {
            return response()->json([
                'status' => 'revision',
                'archivo' => $nombreArchivo,
                'mensaje' => ! $curp
                    ? 'No se pudo leer una CURP válida en el documento.'
                    : 'No se pudo identificar el tipo de documento.',
                'curp' => $curp,
                'nombre_completo' => $resultado['nombre_completo'],
                'numero_documento' => $resultado['numero_documento'],
                'tipo_documento' => $tipo,
            ]);
        }

        $persona = Persona::where('curp', $curp)->first();

        if ($persona && $persona->documentos()->where('tipo_documento', $tipo)->exists()) {
            return response()->json([
                'status' => 'omitido',
                'archivo' => $nombreArchivo,
                'curp' => $curp,
                'persona_nombre' => $persona->nombre_completo,
                'tipo_documento' => $tipo,
                'mensaje' => 'Esta persona ya tiene este tipo de documento; no se modificó.',
            ]);
        }

        $esNueva = ! $persona;
        if ($esNueva) {
            $persona = Persona::create([
                'curp' => $curp,
                'nombre_completo' => $resultado['nombre_completo'] ?? 'Sin nombre (completar manualmente)',
                'fecha_nacimiento' => $resultado['fecha_nacimiento'] ?? null,
                'entidad_nacimiento' => $resultado['entidad_nacimiento'] ?? null,
            ]);
        }

        $rutaArchivo = $archivo->store("uploads/{$persona->id}", 'public');

        Documento::create([
            'persona_id' => $persona->id,
            'tipo_documento' => $tipo,
            'numero_documento' => $resultado['numero_documento'] ?? null,
            'ruta_archivo' => $rutaArchivo,
            'texto_extraido' => $resultado['texto'] ?? null,
        ]);

        return response()->json([
            'status' => 'guardado',
            'archivo' => $nombreArchivo,
            'curp' => $curp,
            'persona_id' => $persona->id,
            'persona_nombre' => $persona->nombre_completo,
            'persona_nueva' => $esNueva,
            'tipo_documento' => $tipo,
        ]);
    }

    public function store(StoreDocumentoRequest $request): RedirectResponse
    {
        $datos = $request->validated();

        $persona = Persona::firstOrNew(['curp' => $datos['curp']]);
        $esNueva = ! $persona->exists;
        $nombreDistinto = false;

        if ($esNueva) {
            $persona->nombre_completo = $datos['nombre_completo'];
            if (! empty($datos['fecha_nacimiento'])) {
                $persona->fecha_nacimiento = $datos['fecha_nacimiento'];
            }
            if (! empty($datos['entidad_nacimiento'])) {
                $persona->entidad_nacimiento = $datos['entidad_nacimiento'];
            }
            $persona->save();
        } elseif (mb_strtoupper(trim($datos['nombre_completo'])) !== mb_strtoupper(trim($persona->nombre_completo ?? ''))) {
            // No sobrescribimos el nombre ya guardado con el que se acaba
            // de escribir: si no coincide, es más probable que sea un
            // error de captura (o CURP repetida por coincidencia) que un
            // cambio de nombre real, así que solo avisamos.
            $nombreDistinto = true;
        }

        $documentoExistente = $persona->documentos()
            ->where('tipo_documento', $datos['tipo_documento'])
            ->first();

        if ($documentoExistente) {
            // Guardamos el archivo nuevo en una carpeta temporal mientras
            // se confirma el reemplazo, para no pedirle al usuario que
            // vuelva a seleccionarlo.
            $token = (string) Str::uuid();
            $rutaTemporal = $request->file('archivo')->store('temp', 'local');

            session()->put('confirmar_reemplazo', [
                'token' => $token,
                'ruta_temporal' => $rutaTemporal,
                'persona_id' => $persona->id,
                'tipo_documento' => $datos['tipo_documento'],
                'numero_documento' => $datos['numero_documento'] ?? null,
                'texto_extraido' => $datos['texto_extraido'] ?? null,
                'nombre_original' => $request->file('archivo')->getClientOriginalName(),
            ]);

            $mensaje = 'Esta persona ya tiene un documento de tipo "'.$datos['tipo_documento'].'" registrado.';
            if ($nombreDistinto) {
                $mensaje .= ' Además, el nombre que escribiste no coincide con "'.$persona->nombre_completo.'" (registrado); no se modificó.';
            }

            return redirect()
                ->route('documentos.create')
                ->withInput()
                ->with('warning', $mensaje);
        }

        $rutaArchivo = $request->file('archivo')->store("uploads/{$persona->id}", 'public');

        Documento::create([
            'persona_id' => $persona->id,
            'tipo_documento' => $datos['tipo_documento'],
            'numero_documento' => $datos['numero_documento'] ?? null,
            'ruta_archivo' => $rutaArchivo,
            'texto_extraido' => $datos['texto_extraido'] ?? null,
        ]);

        $mensaje = $esNueva
            ? 'Persona nueva creada y documento agregado correctamente.'
            : 'Documento agregado correctamente.';

        if ($nombreDistinto) {
            $mensaje .= ' El nombre que escribiste no coincide con "'.$persona->nombre_completo.'" (registrado); no se modificó.';
        }

        return redirect()
            ->route('personas.show', $persona)
            ->with('success', $mensaje);
    }

    public function confirmarReemplazo(Request $request): RedirectResponse
    {
        $pendiente = $request->session()->get('confirmar_reemplazo');

        if (! $pendiente) {
            return redirect()->route('documentos.create')
                ->with('warning', 'No hay ningún reemplazo pendiente.');
        }

        $documento = Documento::where('persona_id', $pendiente['persona_id'])
            ->where('tipo_documento', $pendiente['tipo_documento'])
            ->firstOrFail();

        // Elimina el archivo anterior y mueve el temporal a su ubicación final.
        Storage::disk('public')->delete($documento->ruta_archivo);

        $extension = pathinfo($pendiente['nombre_original'], PATHINFO_EXTENSION);
        $rutaFinal = "uploads/{$pendiente['persona_id']}/".uniqid('doc_').'.'.$extension;

        Storage::disk('public')->put(
            $rutaFinal,
            Storage::disk('local')->get($pendiente['ruta_temporal'])
        );
        Storage::disk('local')->delete($pendiente['ruta_temporal']);

        $documento->update([
            'ruta_archivo' => $rutaFinal,
            'numero_documento' => $pendiente['numero_documento'],
            'texto_extraido' => $pendiente['texto_extraido'] ?? null,
            'fecha_carga' => now(),
        ]);

        $request->session()->forget('confirmar_reemplazo');

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

    public function destroy(Documento $documento): RedirectResponse
    {
        $personaId = $documento->persona_id;

        Storage::disk('public')->delete($documento->ruta_archivo);
        $documento->delete();

        return redirect()
            ->route('personas.show', $personaId)
            ->with('success', 'Documento eliminado correctamente.');
    }
}
