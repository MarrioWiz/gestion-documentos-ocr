@extends('layouts.app')

@section('titulo', 'Personas')

@section('contenido')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h4 page-title mb-1">Personas registradas</h1>
            <p class="text-muted mb-0 small">{{ $personas->count() }} persona(s) en el sistema</p>
        </div>
        <div class="input-group" id="global-search">
            <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
            <input type="text" id="buscador" class="form-control border-start-0 ps-0"
                   placeholder="Buscar por nombre o CURP...">
        </div>
    </div>

    @if ($personas->isEmpty())
        <div class="card-soft text-center py-5">
            <i class="bi bi-people fs-1 text-muted mb-2"></i>
            <p class="text-muted mb-3">No hay personas registradas todavía.</p>
            <a href="{{ route('documentos.create') }}" class="btn btn-accent mx-auto" style="width: fit-content;">
                <i class="bi bi-cloud-upload"></i> Subir el primer documento
            </a>
        </div>
    @endif

    <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-3" id="lista-personas">
        @foreach ($personas as $persona)
            <div class="col persona-item" data-nombre="{{ mb_strtolower($persona->nombre_completo) }}" data-curp="{{ mb_strtolower($persona->curp) }}">
                <a href="{{ route('personas.show', $persona) }}" class="persona-card">
                    <div class="card-soft p-3 h-100">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="avatar-circle">{{ $persona->iniciales() }}</div>
                            <div class="overflow-hidden">
                                <div class="fw-semibold text-truncate">{{ $persona->nombre_completo }}</div>
                                <div class="text-muted small text-truncate">{{ $persona->curp ?? 'Sin CURP' }}</div>
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            @foreach (\App\Models\Persona::TIPOS_DOCUMENTO as $tipo)
                                @php
                                    $tieneDoc = $persona->documentos->firstWhere('tipo_documento', $tipo);
                                    $meta = \App\Models\Persona::META_DOCUMENTO[$tipo];
                                @endphp
                                <span class="doc-pill {{ $tieneDoc ? 'completo' : 'faltante' }}"
                                      title="{{ $meta['label'] }}: {{ $tieneDoc ? 'completo' : 'faltante' }}">
                                    <i class="bi {{ $meta['icon'] }}"></i>
                                </span>
                            @endforeach
                        </div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>

    <p id="sin-resultados" class="text-muted text-center py-5" hidden>
        <i class="bi bi-search"></i> No se encontraron personas con ese criterio.
    </p>
@endsection

@push('scripts')
<script>
    const buscador = document.getElementById('buscador');
    const items = document.querySelectorAll('.persona-item');
    const sinResultados = document.getElementById('sin-resultados');

    buscador.addEventListener('input', function () {
        const termino = this.value.trim().toLowerCase();
        let visibles = 0;

        items.forEach(function (item) {
            const coincide = item.dataset.nombre.includes(termino) || item.dataset.curp.includes(termino);
            item.hidden = !coincide;
            if (coincide) visibles++;
        });

        sinResultados.hidden = visibles !== 0 || items.length === 0;
    });
</script>
@endpush
