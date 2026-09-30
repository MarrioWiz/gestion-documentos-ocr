@extends('layouts.app')

@section('titulo', 'Personas')

@section('contenido')
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
        <div>
            <div class="eyebrow mb-1">Expedientes</div>
            <h1 class="h3 page-title mb-1">Personas registradas</h1>
            <p class="text-muted mb-0 small">{{ $personas->count() }} persona(s) en el sistema</p>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <div class="btn-group btn-group-sm" role="group" aria-label="Filtrar expedientes">
                <input type="radio" class="btn-check" name="filtro" id="filtro-todos" value="" @checked($filtro !== 'incompletos' && $filtro !== 'completos')>
                <label class="btn btn-ghost" for="filtro-todos">Todos</label>
                <input type="radio" class="btn-check" name="filtro" id="filtro-incompletos" value="incompletos" @checked($filtro === 'incompletos')>
                <label class="btn btn-ghost" for="filtro-incompletos">Incompletos</label>
                <input type="radio" class="btn-check" name="filtro" id="filtro-completos" value="completos" @checked($filtro === 'completos')>
                <label class="btn btn-ghost" for="filtro-completos">Completos</label>
            </div>
            <div class="input-group input-group-sm" id="global-search">
                <span class="input-group-text border-end-0"><i class="bi bi-search"></i></span>
                <input type="text" id="buscador" class="form-control border-start-0 ps-0" placeholder="Buscar por nombre o CURP..." aria-label="Buscar por nombre o CURP">
            </div>
            <a href="{{ route('personas.reporte') }}" class="btn btn-ghost btn-sm" title="Reporte en Excel">
                <i class="bi bi-file-earmark-spreadsheet"></i> Reporte
            </a>
        </div>
    </div>

    @if ($personas->isEmpty())
        <div class="card-soft text-center py-5">
            <i class="bi bi-people fs-1 text-muted mb-2"></i>
            <p class="text-muted mb-3">No hay personas registradas todavía.</p>
            <a href="{{ route('documentos.carga-masiva') }}" class="btn btn-accent mx-auto" style="width: fit-content;">
                <i class="bi bi-cloud-upload"></i> Subir los primeros documentos
            </a>
        </div>
    @endif

    <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-3" id="lista-personas">
        @foreach ($personas as $persona)
            @php $avance = $persona->porcentajeCompleto(); @endphp
            <div class="col persona-item" data-nombre="{{ mb_strtolower($persona->nombre_completo) }}" data-curp="{{ mb_strtolower($persona->curp) }}" data-avance="{{ $avance }}">
                <a href="{{ route('personas.show', $persona) }}" class="persona-card">
                    <div class="card-soft p-3 h-100">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="avatar-circle">{{ $persona->iniciales() }}</div>
                            <div class="overflow-hidden flex-grow-1">
                                <div class="fw-semibold text-truncate">{{ $persona->nombre_completo }}</div>
                                <div class="text-muted small text-truncate font-monospace">{{ $persona->curp ?? 'Sin CURP' }}</div>
                            </div>
                        </div>
                        <div class="d-flex gap-2 mb-3">
                            @foreach (\App\Models\Persona::TIPOS_DOCUMENTO as $tipo)
                                @php
                                    $tieneDoc = $persona->documentos->firstWhere('tipo_documento', $tipo);
                                    $meta = \App\Models\Persona::META_DOCUMENTO[$tipo];
                                @endphp
                                <span class="doc-pill {{ $tieneDoc ? 'completo' : 'faltante' }}"
                                      title="{{ $meta['label'] }}: {{ $tieneDoc ? 'cargado' : 'falta' }}">
                                    <i class="bi {{ $meta['icon'] }}"></i>
                                </span>
                            @endforeach
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <div class="avance flex-grow-1"><span style="width: {{ $avance }}%"></span></div>
                            <span class="small text-muted" style="font-variant-numeric: tabular-nums;">{{ $avance }}%</span>
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
    const filtros = document.querySelectorAll('input[name="filtro"]');

    function aplicarFiltros() {
        const termino = buscador.value.trim().toLowerCase();
        const filtro = document.querySelector('input[name="filtro"]:checked')?.value || '';
        let visibles = 0;

        items.forEach(function (item) {
            const avance = Number(item.dataset.avance);
            const coincideTexto = item.dataset.nombre.includes(termino) || item.dataset.curp.includes(termino);
            const coincideFiltro = filtro === '' || (filtro === 'completos' ? avance === 100 : avance < 100);
            const visible = coincideTexto && coincideFiltro;
            item.hidden = !visible;
            if (visible) visibles++;
        });

        sinResultados.hidden = visibles !== 0 || items.length === 0;
    }

    buscador.addEventListener('input', aplicarFiltros);
    filtros.forEach(f => f.addEventListener('change', aplicarFiltros));
    aplicarFiltros();
</script>
@endpush
