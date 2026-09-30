<?php

namespace App\Http\Controllers;

use App\Models\Documento;
use App\Models\HistorialAcceso;
use App\Models\Persona;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $personas = Persona::with('documentos')->get();
        $totalTipos = count(Persona::TIPOS_DOCUMENTO);

        $porTipo = Documento::selectRaw('tipo_documento, count(*) as total')
            ->groupBy('tipo_documento')
            ->pluck('total', 'tipo_documento');

        // Últimos 14 días, incluidos los días sin cargas (en cero).
        $cargasPorDia = Documento::where('fecha_carga', '>=', now()->subDays(13)->startOfDay())
            ->get(['fecha_carga'])
            ->countBy(fn (Documento $d) => $d->fecha_carga->format('Y-m-d'));
        $actividad = collect(range(13, 0))->mapWithKeys(function (int $diasAtras) use ($cargasPorDia) {
            $dia = now()->subDays($diasAtras)->format('Y-m-d');

            return [$dia => $cargasPorDia[$dia] ?? 0];
        });

        return view('dashboard', [
            'totalPersonas' => $personas->count(),
            'totalDocumentos' => $personas->sum(fn (Persona $p) => $p->documentos->count()),
            'completos' => $personas->filter(fn (Persona $p) => $p->porcentajeCompleto() === 100)->count(),
            'avancePromedio' => $personas->isEmpty() ? 0 : (int) round($personas->avg(fn (Persona $p) => $p->porcentajeCompleto())),
            'porTipo' => collect(Persona::TIPOS_DOCUMENTO)->mapWithKeys(fn (string $t) => [$t => (int) ($porTipo[$t] ?? 0)]),
            'actividad' => $actividad,
            'masIncompletas' => $personas->sortBy(fn (Persona $p) => $p->porcentajeCompleto())->take(5),
            'recientes' => HistorialAcceso::with('usuario')->whereIn('accion', ['subida', 'reemplazo', 'eliminacion'])->latest('fecha')->take(6)->get(),
            'totalTipos' => $totalTipos,
        ]);
    }
}
