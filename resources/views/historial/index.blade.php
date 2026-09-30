@extends('layouts.app')

@section('titulo', 'Historial')

@section('contenido')
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
        <div>
            <div class="eyebrow mb-1">Auditoría</div>
            <h1 class="h3 page-title mb-1">Historial de accesos</h1>
            <p class="text-muted small mb-0">Quién entró, qué subió, consultó o eliminó, y desde qué IP.</p>
        </div>
        <form method="GET" class="d-flex gap-2">
            <select name="accion" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Filtrar por acción">
                <option value="">Todas las acciones</option>
                @foreach (\App\Models\HistorialAcceso::ACCIONES as $clave => $meta)
                    <option value="{{ $clave }}" @selected($accion === $clave)>{{ $meta['label'] }}</option>
                @endforeach
            </select>
        </form>
    </div>

    <div class="card-soft p-0 overflow-hidden">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr>
                        <th class="ps-4">Fecha</th>
                        <th>Usuario</th>
                        <th>Acción</th>
                        <th>Descripción</th>
                        <th class="pe-4">IP</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($registros as $registro)
                        @php $meta = \App\Models\HistorialAcceso::ACCIONES[$registro->accion] ?? ['label' => $registro->accion, 'icon' => 'bi-dot']; @endphp
                        <tr>
                            <td class="ps-4 small text-nowrap">{{ $registro->fecha->format('d/m/Y H:i:s') }}</td>
                            <td class="small">{{ $registro->usuario?->name ?? '—' }}</td>
                            <td>
                                <span class="badge {{ in_array($registro->accion, ['eliminacion', 'login_fallido']) ? 'badge-faltante' : 'badge-info' }} rounded-pill px-3 py-2">
                                    <i class="bi {{ $meta['icon'] }}"></i> {{ $meta['label'] }}
                                </span>
                            </td>
                            <td class="small">{{ $registro->descripcion }}</td>
                            <td class="pe-4 small font-monospace text-muted">{{ $registro->ip }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-5">Sin registros.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        {{ $registros->links('pagination::bootstrap-5') }}
    </div>
@endsection
