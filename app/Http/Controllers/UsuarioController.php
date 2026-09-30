<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Historial;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UsuarioController extends Controller
{
    public function index(): View
    {
        return view('usuarios.index', [
            'usuarios' => User::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', Password::min(8)],
            'es_admin' => ['nullable', 'boolean'],
        ]);

        $usuario = User::create($datos + ['es_admin' => false]);
        $usuario->forceFill(['es_admin' => $request->boolean('es_admin')])->save();

        Historial::registrar('usuarios', "Creó al usuario {$usuario->email}".($usuario->es_admin ? ' (administrador)' : ''));

        return back()->with('success', "Usuario {$usuario->name} creado.");
    }

    public function update(Request $request, User $usuario): RedirectResponse
    {
        $datos = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($usuario->id)],
            'password' => ['nullable', Password::min(8)],
            'es_admin' => ['nullable', 'boolean'],
        ]);

        // Un administrador no puede quitarse a sí mismo el rol: evitaría
        // quedarse sin nadie que pueda gestionar usuarios.
        $esAdmin = $usuario->is($request->user()) ? true : $request->boolean('es_admin');

        $usuario->fill(array_filter([
            'name' => $datos['name'],
            'email' => $datos['email'],
            'password' => $datos['password'] ?? null,
        ]));
        $usuario->es_admin = $esAdmin;
        $usuario->save();

        Historial::registrar('usuarios', "Editó al usuario {$usuario->email}");

        return back()->with('success', "Usuario {$usuario->name} actualizado.");
    }

    public function destroy(Request $request, User $usuario): RedirectResponse
    {
        if ($usuario->is($request->user())) {
            return back()->with('warning', 'No puedes eliminar tu propia cuenta.');
        }

        $usuario->delete();
        Historial::registrar('usuarios', "Eliminó al usuario {$usuario->email}");

        return back()->with('success', "Usuario {$usuario->name} eliminado.");
    }
}
