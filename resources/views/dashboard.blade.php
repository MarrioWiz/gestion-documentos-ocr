@extends('layouts.app')

@section('titulo', 'Panel')

@section('contenido')
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
        <div>
            <div class="eyebrow mb-1">Panel de control</div>
            <h1 class="h3 page-title mb-0">Hola, {{ \Illuminate\Support\Str::of(auth()->user()->name)->before(' ') }}</h1>
        </div>
        <a href="{{ route('personas.reporte') }}" class="btn btn-ghost btn-sm">
            <i class="bi bi-file-earmark-spreadsheet"></i> Descargar reporte (Excel)
        </a>
    </div>

    <div class="row row-cols-2 row-cols-lg-4 g-3 mb-4">
        @foreach ([
            ['icono' => 'bi-people', 'valor' => $totalPersonas, 'texto' => 'Personas registradas'],
            ['icono' => 'bi-files', 'valor' => $totalDocumentos, 'texto' => 'Documentos cargados'],
            ['icono' => 'bi-patch-check', 'valor' => $completos, 'texto' => 'Expedientes completos'],
            ['icono' => 'bi-speedometer2', 'valor' => $avancePromedio.'%', 'texto' => 'Avance promedio'],
        ] as $stat)
            <div class="col">
                <div class="card-soft p-3 p-md-4 h-100">
                    <span class="stat-icono mb-3"><i class="bi {{ $stat['icono'] }}"></i></span>
                    <div class="stat-valor">{{ $stat['valor'] }}</div>
                    <div class="text-muted small mt-1">{{ $stat['texto'] }}</div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-7">
            <div class="card-soft p-4 h-100">
                <h2 class="h6 mb-1">Documentos por tipo</h2>
                <p class="text-muted small mb-4">Cuántas personas tienen cada documento en su expediente.</p>
                @php $maximo = max(1, $porTipo->max()); @endphp
                <div class="grafica-barras" role="table" aria-label="Documentos por tipo">
                    @foreach ($porTipo as $tipo => $total)
                        <div class="fila" role="row" title="{{ \App\Models\Persona::etiquetaTipo($tipo) }}: {{ $total }} de {{ $totalPersonas }} personas">
                            <span role="cell" class="text-truncate"><i class="bi {{ \App\Models\Persona::META_DOCUMENTO[$tipo]['icon'] }} text-muted me-1"></i>{{ \App\Models\Persona::etiquetaTipo($tipo) }}</span>
                            <span role="cell" class="pista"><span class="barra" style="width: {{ $total * 100 / $maximo }}%"></span></span>
                            <span role="cell" class="valor">{{ $total }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card-soft p-4 h-100">
                <h2 class="h6 mb-1">Actividad de carga</h2>
                <p class="text-muted small mb-4">Documentos subidos por día, últimos 14 días.</p>
                @php $maxDia = max(1, $actividad->max()); @endphp
                <div class="grafica-columnas" aria-label="Documentos subidos por día">
                    @foreach ($actividad as $dia => $total)
                        <div class="col-dia">
                            <span style="height: {{ $total * 100 / $maxDia }}%"></span>
                            <div class="tooltip-grafica">{{ \Illuminate\Support\Carbon::parse($dia)->translatedFormat('d M') }} · <strong>{{ $total }}</strong> doc.</div>
                        </div>
                    @endforeach
                </div>
                <div class="d-flex justify-content-between text-muted small mt-2">
                    <span>{{ \Illuminate\Support\Carbon::parse($actividad->keys()->first())->translatedFormat('d M') }}</span>
                    <span>Hoy</span>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card-soft p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h6 mb-0">Expedientes con más faltantes</h2>
                    <a href="{{ route('personas.index', ['filtro' => 'incompletos']) }}" class="small text-decoration-none">Ver todos</a>
                </div>
                @forelse ($masIncompletas as $persona)
                    <a href="{{ route('personas.show', $persona) }}" class="d-flex align-items-center gap-3 py-2 text-decoration-none text-reset border-bottom" style="border-color: var(--color-border) !important;">
                        <div class="avatar-circle" style="width:38px;height:38px;font-size:.85rem;">{{ $persona->iniciales() }}</div>
                        <div class="flex-grow-1 overflow-hidden">
                            <div class="fw-semibold text-truncate small">{{ $persona->nombre_completo }}</div>
                            <div class="avance mt-1"><span style="width: {{ $persona->porcentajeCompleto() }}%"></span></div>
                        </div>
                        <span class="small text-muted" style="font-variant-numeric: tabular-nums;">{{ $persona->porcentajeCompleto() }}%</span>
                    </a>
                @empty
                    <p class="text-muted small mb-0">Aún no hay personas registradas.</p>
                @endforelse
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card-soft p-4 h-100">
                <h2 class="h6 mb-3">Movimientos recientes</h2>
                @forelse ($recientes as $registro)
                    <div class="d-flex gap-3 py-2">
                        <span class="stat-icono" style="width:34px;height:34px;font-size:.95rem;"><i class="bi {{ \App\Models\HistorialAcceso::ACCIONES[$registro->accion]['icon'] ?? 'bi-dot' }}"></i></span>
                        <div class="small">
                            <div>{{ $registro->descripcion }}</div>
                            <div class="text-muted">{{ $registro->usuario?->name ?? 'Sistema' }} · {{ $registro->fecha->diffForHumans() }}</div>
                        </div>
                    </div>
                @empty
                    <p class="text-muted small mb-0">Sin movimientos todavía.</p>
                @endforelse
            </div>
        </div>
    </div>
@endsection
