@extends('layouts.app')

@section('titulo', $persona->nombre_completo)

@section('contenido')
    <a href="{{ route('personas.index') }}" class="text-decoration-none text-muted small">
        <i class="bi bi-arrow-left"></i> Volver
    </a>

    @php $avance = $persona->porcentajeCompleto(); @endphp

    <div class="card-soft p-4 mt-3 mb-4">
        <div class="d-flex flex-wrap align-items-center gap-3 justify-content-between">
            <div class="d-flex align-items-center gap-3">
                <div class="avatar-circle" style="width:64px;height:64px;font-size:1.4rem;border-radius:18px;">{{ $persona->iniciales() }}</div>
                <div>
                    <h1 class="h4 mb-1 page-title">{{ $persona->nombre_completo }}</h1>
                    <div class="text-muted small d-flex flex-wrap gap-3">
                        <span><i class="bi bi-fingerprint"></i> <span class="font-monospace">{{ $persona->curp ?? 'N/D' }}</span></span>
                        @if ($persona->fecha_nacimiento)
                            <span><i class="bi bi-cake2"></i> {{ $persona->fecha_nacimiento->format('d/m/Y') }} ({{ $persona->fecha_nacimiento->age }} años)</span>
                        @endif
                        @if ($persona->entidad_nacimiento)
                            <span><i class="bi bi-geo-alt"></i> {{ $persona->entidad_nacimiento }}</span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('documentos.create', ['curp' => $persona->curp, 'nombre_completo' => $persona->nombre_completo, 'tipo_documento' => $faltantes[0] ?? null]) }}"
                   class="btn btn-accent btn-sm">
                    <i class="bi bi-cloud-upload"></i> Agregar documento
                </a>
                <form method="POST" action="{{ route('personas.destroy', $persona) }}"
                      onsubmit="return confirm(@js('¿Eliminar a '.$persona->nombre_completo.' y todos sus documentos? Esta acción no se puede deshacer.'));">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-ghost btn-sm text-danger" title="Eliminar persona" aria-label="Eliminar persona">
                        <i class="bi bi-trash3"></i>
                    </button>
                </form>
            </div>
        </div>
        <div class="d-flex align-items-center gap-3 mt-4">
            <span class="small text-muted text-nowrap">Expediente</span>
            <div class="avance flex-grow-1" style="height:8px;"><span style="width: {{ $avance }}%"></span></div>
            <span class="small fw-semibold" style="font-variant-numeric: tabular-nums;">{{ $avance }}%</span>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card-soft p-4 h-100">
                <h2 class="h6 mb-3">Checklist de documentos</h2>
                <div class="d-flex flex-column gap-2">
                    @foreach (\App\Models\Persona::TIPOS_DOCUMENTO as $tipo)
                        @php
                            $doc = $persona->documentos->firstWhere('tipo_documento', $tipo);
                            $meta = \App\Models\Persona::META_DOCUMENTO[$tipo];
                        @endphp
                        <div class="d-flex align-items-center justify-content-between border rounded-3 px-3 py-2"
                             style="border-color: var(--color-border) !important;">
                            <div class="d-flex align-items-center gap-2">
                                <span class="doc-pill {{ $doc ? 'completo' : 'faltante' }}">
                                    <i class="bi {{ $meta['icon'] }}"></i>
                                </span>
                                <span>{{ $meta['label'] }}</span>
                            </div>
                            @if ($doc)
                                <span class="badge badge-completo rounded-pill px-3 py-2">
                                    <i class="bi bi-check-lg"></i> Completo
                                </span>
                            @else
                                <a href="{{ route('documentos.create', ['curp' => $persona->curp, 'nombre_completo' => $persona->nombre_completo, 'tipo_documento' => $tipo]) }}"
                                   class="badge badge-faltante rounded-pill px-3 py-2 text-decoration-none" title="Subir {{ $meta['label'] }}">
                                    <i class="bi bi-plus-lg"></i> Faltante
                                </a>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card-soft p-4 h-100">
                <h2 class="h6 mb-3">Documentos cargados</h2>

                @if ($persona->documentos->isEmpty())
                    <p class="text-muted">Aún no hay documentos cargados.</p>
                @else
                    <div class="d-flex flex-column gap-2">
                        @foreach ($persona->documentos as $doc)
                            @php $meta = \App\Models\Persona::META_DOCUMENTO[$doc->tipo_documento] ?? ['label' => $doc->tipo_documento, 'icon' => 'bi-file-earmark']; @endphp
                            <div class="d-flex align-items-center justify-content-between border rounded-3 px-3 py-2 gap-2"
                                 style="border-color: var(--color-border) !important;">
                                <a href="{{ route('documentos.archivo', $doc) }}" target="_blank" rel="noopener"
                                   class="d-flex align-items-center gap-3 text-decoration-none text-reset flex-grow-1 overflow-hidden">
                                    @if ($doc->esImagen())
                                        <img src="{{ route('documentos.archivo', [$doc, 'miniatura' => 1]) }}" alt="{{ $meta['label'] }}" loading="lazy"
                                             class="rounded-2 flex-shrink-0" style="width:52px;height:36px;object-fit:cover;border:1px solid var(--color-border);">
                                    @else
                                        <span class="doc-pill completo flex-shrink-0"><i class="bi {{ $meta['icon'] }}"></i></span>
                                    @endif
                                    <div class="overflow-hidden">
                                        <div class="fw-semibold">{{ $meta['label'] }}</div>
                                        <div class="text-muted small text-truncate">
                                            <span class="font-monospace">{{ $doc->numero_documento ?? 'Sin número' }}</span> &middot;
                                            {{ $doc->fecha_carga->format('d/m/Y H:i') }}
                                            @if ($doc->subidoPor) &middot; {{ $doc->subidoPor->name }} @endif
                                        </div>
                                    </div>
                                </a>
                                <div class="d-flex align-items-center gap-3">
                                    <a href="{{ route('documentos.archivo', $doc) }}" target="_blank" rel="noopener" class="text-muted" title="Ver" aria-label="Ver {{ $meta['label'] }}">
                                        <i class="bi bi-box-arrow-up-right"></i>
                                    </a>
                                    <form method="POST" action="{{ route('documentos.destroy', $doc) }}"
                                          onsubmit="return confirm(@js('¿Eliminar este documento ('.$meta['label'].')?'));">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-link text-danger p-0" title="Eliminar" aria-label="Eliminar {{ $meta['label'] }}">
                                            <i class="bi bi-trash3"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
