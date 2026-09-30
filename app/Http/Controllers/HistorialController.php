<?php

namespace App\Http\Controllers;

use App\Models\HistorialAcceso;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class HistorialController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate(['accion' => ['nullable', Rule::in(array_keys(HistorialAcceso::ACCIONES))]]);
        $accion = $request->query('accion');

        return view('historial.index', [
            'registros' => HistorialAcceso::with('usuario')
                ->when($accion, fn ($query) => $query->where('accion', $accion))
                ->latest('fecha')
                ->latest('id')
                ->paginate(25)
                ->withQueryString(),
            'accion' => $accion,
        ]);
    }
}
