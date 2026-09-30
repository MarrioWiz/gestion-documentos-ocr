<?php

namespace App\Http\Controllers;

use App\Models\Documento;
use App\Models\Persona;
use App\Services\Historial;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PersonaController extends Controller
{
    public function index(Request $request): View
    {
        $personas = Persona::with('documentos')
            ->orderBy('nombre_completo')
            ->get();

        return view('personas.index', [
            'personas' => $personas,
            'filtro' => $request->query('filtro'),
        ]);
    }

    public function show(Persona $persona): View
    {
        $persona->load('documentos.subidoPor');

        return view('personas.show', [
            'persona' => $persona,
            'faltantes' => $persona->documentosFaltantes(),
        ]);
    }

    public function destroy(Persona $persona): RedirectResponse
    {
        Storage::disk(Documento::DISCO)->deleteDirectory("documentos/{$persona->id}");
        $persona->delete();

        Historial::registrar('eliminacion', "Eliminó a {$persona->nombre_completo} ({$persona->curp}) y todos sus documentos");

        return redirect()
            ->route('personas.index')
            ->with('success', 'Persona y todos sus documentos fueron eliminados.');
    }

    /**
     * Reporte CSV (se abre directo en Excel) con el estado del expediente de
     * cada persona: qué documentos tiene, cuáles le faltan y su % de avance.
     */
    public function reporte(): StreamedResponse
    {
        Historial::registrar('reporte', 'Descargó el reporte de expedientes');

        return response()->streamDownload(function () {
            $salida = fopen('php://output', 'w');
            // BOM para que Excel reconozca los acentos (UTF-8).
            fwrite($salida, "\xEF\xBB\xBF");

            fputcsv($salida, [
                'CURP', 'Nombre', 'Fecha de nacimiento', 'Entidad de nacimiento',
                ...array_map([Persona::class, 'etiquetaTipo'], Persona::TIPOS_DOCUMENTO),
                'Avance (%)', 'Documentos faltantes',
            ]);

            Persona::with('documentos')->orderBy('nombre_completo')->chunk(200, function ($personas) use ($salida) {
                foreach ($personas as $persona) {
                    $tipos = $persona->documentos->pluck('tipo_documento')->all();

                    fputcsv($salida, [
                        $persona->curp,
                        $persona->nombre_completo,
                        $persona->fecha_nacimiento?->format('d/m/Y'),
                        $persona->entidad_nacimiento,
                        ...array_map(fn (string $tipo) => in_array($tipo, $tipos, true) ? 'Sí' : 'Falta', Persona::TIPOS_DOCUMENTO),
                        $persona->porcentajeCompleto(),
                        implode(', ', array_map([Persona::class, 'etiquetaTipo'], $persona->documentosFaltantes())),
                    ]);
                }
            });

            fclose($salida);
        }, 'reporte-expedientes-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
