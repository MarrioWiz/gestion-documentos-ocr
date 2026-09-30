@extends('layouts.app')

@section('titulo', 'Usuarios')

@section('contenido')
    <div class="eyebrow mb-1">Administración</div>
    <h1 class="h3 page-title mb-4">Usuarios del sistema</h1>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card-soft p-0 overflow-hidden">
                <div class="table-responsive">
                    <table class="table mb-0 align-middle">
                        <thead>
                            <tr>
                                <th class="ps-4">Nombre</th>
                                <th>Correo</th>
                                <th>Rol</th>
                                <th class="pe-4 text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($usuarios as $usuario)
                                <tr>
                                    <td class="ps-4">{{ $usuario->name }} @if ($usuario->is(auth()->user())) <span class="badge badge-info ms-1">tú</span> @endif</td>
                                    <td class="small">{{ $usuario->email }}</td>
                                    <td>
                                        <span class="badge {{ $usuario->es_admin ? 'badge-info' : 'badge-completo' }} rounded-pill px-3 py-2">
                                            {{ $usuario->es_admin ? 'Administrador' : 'Capturista' }}
                                        </span>
                                    </td>
                                    <td class="pe-4 text-end text-nowrap">
                                        <button class="btn btn-sm btn-ghost" data-bs-toggle="modal" data-bs-target="#editar-{{ $usuario->id }}" aria-label="Editar {{ $usuario->name }}">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        @unless ($usuario->is(auth()->user()))
                                            <form method="POST" action="{{ route('usuarios.destroy', $usuario) }}" class="d-inline"
                                                  onsubmit="return confirm(@js('¿Eliminar al usuario '.$usuario->name.'?'));">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-sm btn-ghost text-danger" aria-label="Eliminar {{ $usuario->name }}"><i class="bi bi-trash3"></i></button>
                                            </form>
                                        @endunless
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card-soft p-4">
                <h2 class="h6 mb-3">Nuevo usuario</h2>
                <form method="POST" action="{{ route('usuarios.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label small fw-semibold" for="nuevo-nombre">Nombre</label>
                        <input id="nuevo-nombre" name="name" class="form-control" value="{{ old('name') }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold" for="nuevo-email">Correo</label>
                        <input id="nuevo-email" type="email" name="email" class="form-control" value="{{ old('email') }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold" for="nuevo-password">Contraseña (mín. 8)</label>
                        <input id="nuevo-password" type="password" name="password" class="form-control" required autocomplete="new-password">
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="es_admin" value="1" id="nuevo-admin">
                        <label class="form-check-label small" for="nuevo-admin">Administrador (ve historial y gestiona usuarios)</label>
                    </div>
                    <button class="btn btn-accent w-100"><i class="bi bi-person-plus"></i> Crear usuario</button>
                </form>
            </div>
        </div>
    </div>

    @foreach ($usuarios as $usuario)
        <div class="modal fade" id="editar-{{ $usuario->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form method="POST" action="{{ route('usuarios.update', $usuario) }}" class="modal-content">
                    @csrf
                    @method('PUT')
                    <div class="modal-header border-0">
                        <h2 class="modal-title h6">Editar {{ $usuario->name }}</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Nombre</label>
                            <input name="name" class="form-control" value="{{ $usuario->name }}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Correo</label>
                            <input type="email" name="email" class="form-control" value="{{ $usuario->email }}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Nueva contraseña (déjala vacía para no cambiarla)</label>
                            <input type="password" name="password" class="form-control" autocomplete="new-password">
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="es_admin" value="1" id="admin-{{ $usuario->id }}" @checked($usuario->es_admin) @disabled($usuario->is(auth()->user()))>
                            <label class="form-check-label small" for="admin-{{ $usuario->id }}">Administrador</label>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancelar</button>
                        <button class="btn btn-accent">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    @endforeach
@endsection
