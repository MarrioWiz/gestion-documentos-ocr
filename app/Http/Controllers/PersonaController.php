<?php

namespace App\Http\Controllers;

use App\Models\Persona;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PersonaController extends Controller
{
    public function index(): View
    {
        $personas = Persona::with('documentos')
            ->orderBy('nombre_completo')
            ->get();

        return view('personas.index', compact('personas'));
    }

    public function show(Persona $persona): View
    {
        $persona->load('documentos');

        return view('personas.show', [
            'persona' => $persona,
            'faltantes' => $persona->documentosFaltantes(),
        ]);
    }

    public function destroy(Persona $persona): RedirectResponse
    {
        Storage::disk('public')->deleteDirectory("uploads/{$persona->id}");
        $persona->delete();

        return redirect()
            ->route('personas.index')
            ->with('success', 'Persona y todos sus documentos fueron eliminados.');
    }
}
