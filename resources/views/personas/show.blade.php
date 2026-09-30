@extends('layouts.app')

@section('titulo', $persona->nombre_completo)

@section('contenido')
    <a href="{{ route('personas.index') }}" class="text-decoration-none text-muted small">
        <i class="bi bi-arrow-left"></i> Volver
    </a>

    <div class="card-soft p-4 mt-3 mb-4">
        <div class="d-flex flex-wrap align-items-center gap-3 justify-content-between">
            <div class="d-flex align-items-center gap-3">
                <div class="avatar-circle" style="width:64px;height:64px;font-size:1.4rem;">{{ $persona->iniciales() }}</div>
                <div>
                    <h1 class="h4 mb-1 page-title">{{ $persona->nombre_completo }}</h1>
                    <div class="text-muted small">
                        <i class="bi bi-card-text"></i> CURP: {{ $persona->curp ?? 'N/D' }}
                        @if ($persona->fecha_nacimiento)
                            &middot; <i class="bi bi-cake2"></i> {{ $persona->fecha_nacimiento->format('d/m/Y') }}
                        @endif
                        @if ($persona->entidad_nacimiento)
                            &middot; <i class="bi bi-geo-alt"></i> {{ $persona->entidad_nacimiento }}
                        @endif
                    </div>
                </div>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('documentos.create', ['curp' => $persona->curp, 'nombre_completo' => $persona->nombre_completo]) }}"
                   class="btn btn-accent btn-sm">
                    <i class="bi bi-cloud-upload"></i> Agregar documento
                </a>
                <form method="POST" action="{{ route('personas.destroy', $persona) }}"
                      onsubmit="return confirm('¿Eliminar a {{ addslashes($persona->nombre_completo) }} y todos sus documentos? Esta acción no se puede deshacer.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger btn-sm">
                        <i class="bi bi-trash3"></i>
                    </button>
                </form>
            </div>
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
                                <span class="badge badge-faltante rounded-pill px-3 py-2">
                                    <i class="bi bi-exclamation-lg"></i> Faltante
                                </span>
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
                            @php $meta = \App\Models\Persona::META_DOCUMENTO[$doc->tipo_documento]; @endphp
                            <div class="d-flex align-items-center justify-content-between border rounded-3 px-3 py-2"
                                 style="border-color: var(--color-border) !important;">
                                <a href="{{ \Illuminate\Support\Facades\Storage::url($doc->ruta_archivo) }}" target="_blank"
                                   class="d-flex align-items-center gap-3 text-decoration-none text-dark flex-grow-1">
                                    <span class="doc-pill completo"><i class="bi {{ $meta['icon'] }}"></i></span>
                                    <div>
                                        <div class="fw-semibold">{{ $meta['label'] }}</div>
                                        <div class="text-muted small">
                                            {{ $doc->numero_documento ?? 'Sin número' }} &middot;
                                            {{ $doc->fecha_carga->format('d/m/Y H:i') }}
                                        </div>
                                    </div>
                                </a>
                                <div class="d-flex align-items-center gap-2">
                                    <a href="{{ \Illuminate\Support\Facades\Storage::url($doc->ruta_archivo) }}" target="_blank"
                                       class="text-muted" title="Ver">
                                        <i class="bi bi-box-arrow-up-right"></i>
                                    </a>
                                    <form method="POST" action="{{ route('documentos.destroy', $doc) }}"
                                          onsubmit="return confirm('¿Eliminar este documento ({{ $meta['label'] }})?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-link text-danger p-0" title="Eliminar">
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
