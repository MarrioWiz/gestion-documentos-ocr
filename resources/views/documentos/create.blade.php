@extends('layouts.app')

@section('titulo', 'Subir documento')

@section('contenido')
    <h1 class="h4 page-title mb-4">Subir documento</h1>

    @if (session('confirmar_reemplazo'))
        @php $pendiente = session('confirmar_reemplazo'); @endphp
        <div class="card-soft border-warning-subtle p-3 mb-4" style="border: 1px solid #fbbf24 !important;">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-exclamation-triangle-fill text-warning fs-5"></i>
                    <div>
                        Ya existe un documento tipo <strong>{{ \App\Models\Persona::META_DOCUMENTO[$pendiente['tipo_documento']]['label'] }}</strong>
                        para esta persona. ¿Reemplazarlo por "{{ $pendiente['nombre_original'] }}"?
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <form method="POST" action="{{ route('documentos.confirmar-reemplazo') }}">
                        @csrf
                        <button class="btn btn-sm btn-danger" type="submit">Sí, reemplazar</button>
                    </form>
                    <form method="POST" action="{{ route('documentos.cancelar-reemplazo') }}">
                        @csrf
                        <button class="btn btn-sm btn-outline-secondary" type="submit">Cancelar</button>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card-soft p-4">
                <form method="POST" action="{{ route('documentos.store') }}" enctype="multipart/form-data" id="form-documento">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label fw-semibold">CURP</label>
                        <input type="text" name="curp" maxlength="18"
                               class="form-control text-uppercase @error('curp') is-invalid @enderror"
                               value="{{ old('curp', $curpPrellenada) }}" required>
                        @error('curp') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <div class="form-text">Se usa para buscar si la persona ya existe.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nombre completo</label>
                        <input type="text" name="nombre_completo"
                               class="form-control @error('nombre_completo') is-invalid @enderror"
                               value="{{ old('nombre_completo', $nombrePrellenado) }}" required>
                        @error('nombre_completo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Fecha de nacimiento (opcional)</label>
                        <input type="date" name="fecha_nacimiento"
                               class="form-control @error('fecha_nacimiento') is-invalid @enderror"
                               value="{{ old('fecha_nacimiento') }}">
                        @error('fecha_nacimiento') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Entidad de nacimiento (opcional)</label>
                        <select name="entidad_nacimiento" class="form-select @error('entidad_nacimiento') is-invalid @enderror">
                            <option value="">-- Selecciona --</option>
                            @foreach (\App\Services\DocumentoOcrService::ENTIDADES_CURP as $entidad)
                                <option value="{{ $entidad }}" @selected(old('entidad_nacimiento') === $entidad)>{{ $entidad }}</option>
                            @endforeach
                        </select>
                        @error('entidad_nacimiento') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <div class="form-text">Se deduce de la CURP (posiciones 12-13); revisa que coincida.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Tipo de documento</label>
                        <select name="tipo_documento" class="form-select @error('tipo_documento') is-invalid @enderror" required>
                            <option value="">-- Selecciona --</option>
                            @foreach (\App\Models\Persona::TIPOS_DOCUMENTO as $tipo)
                                <option value="{{ $tipo }}" @selected(old('tipo_documento') === $tipo)>
                                    {{ \App\Models\Persona::META_DOCUMENTO[$tipo]['label'] }}
                                </option>
                            @endforeach
                        </select>
                        @error('tipo_documento') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Número de documento (opcional)</label>
                        <input type="text" name="numero_documento"
                               class="form-control @error('numero_documento') is-invalid @enderror"
                               value="{{ old('numero_documento') }}">
                        @error('numero_documento') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <input type="file" id="archivo" name="archivo" accept=".jpg,.jpeg,.png,.pdf" class="d-none">
                    <input type="hidden" name="texto_extraido" id="texto_extraido">

                    <div class="d-flex justify-content-between align-items-center mt-4">
                        <button type="button" id="btn-ocr" class="btn btn-outline-secondary btn-sm" disabled>
                            <i class="bi bi-magic"></i> Extraer datos con OCR
                        </button>
                        <button type="submit" class="btn btn-accent">
                            <i class="bi bi-check-lg"></i> Guardar
                        </button>
                    </div>
                    <div id="ocr-estado" class="text-muted small mt-2"></div>
                    <details id="ocr-texto-crudo" class="small mt-2" hidden>
                        <summary class="text-muted">Ver texto que leyó el OCR</summary>
                        <pre id="ocr-texto-crudo-contenido" class="small bg-light p-2 rounded mt-1" style="white-space: pre-wrap;"></pre>
                    </details>
                    @error('archivo') <div class="text-danger small mt-2">{{ $message }}</div> @enderror
                </form>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card-soft p-4 h-100">
                <h2 class="h6 mb-3">Archivo</h2>

                <div id="dropzone" class="dropzone">
                    <div id="dropzone-vacio">
                        <i class="bi bi-cloud-arrow-up d-block mb-2"></i>
                        <p class="mb-1 fw-semibold">Arrastra tu archivo aquí</p>
                        <p class="text-muted small mb-0">o haz clic para seleccionarlo &middot; JPG, PNG o PDF, máx. 10MB</p>
                    </div>
                    <div id="dropzone-preview" class="d-none">
                        <img id="preview-imagen" class="d-none rounded-3 mb-2" style="max-height: 220px; max-width: 100%;">
                        <i id="preview-icono-pdf" class="bi bi-file-earmark-pdf-fill d-none" style="font-size: 3rem; color: #dc2626;"></i>
                        <p id="preview-nombre" class="fw-semibold mb-0 mt-2 text-break"></p>
                        <p id="preview-peso" class="text-muted small mb-0"></p>
                        <button type="button" id="btn-quitar" class="btn btn-sm btn-outline-danger mt-2">
                            <i class="bi bi-x-lg"></i> Quitar
                        </button>
                    </div>
                </div>
                <p id="error-archivo" class="text-danger small mt-2 mb-0" hidden>
                    <i class="bi bi-exclamation-circle"></i> Selecciona un archivo antes de guardar.
                </p>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    const inputArchivo = document.getElementById('archivo');
    const dropzone = document.getElementById('dropzone');
    const dropzoneVacio = document.getElementById('dropzone-vacio');
    const dropzonePreview = document.getElementById('dropzone-preview');
    const previewImagen = document.getElementById('preview-imagen');
    const previewIconoPdf = document.getElementById('preview-icono-pdf');
    const previewNombre = document.getElementById('preview-nombre');
    const previewPeso = document.getElementById('preview-peso');
    const btnOcr = document.getElementById('btn-ocr');
    const btnQuitar = document.getElementById('btn-quitar');
    const ocrEstado = document.getElementById('ocr-estado');
    const ocrTextoCrudo = document.getElementById('ocr-texto-crudo');
    const ocrTextoCrudoContenido = document.getElementById('ocr-texto-crudo-contenido');
    const formDocumento = document.getElementById('form-documento');
    const errorArchivo = document.getElementById('error-archivo');

    formDocumento.addEventListener('submit', function (e) {
        if (!inputArchivo.files.length) {
            e.preventDefault();
            errorArchivo.hidden = false;
            dropzone.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });

    function formatoPeso(bytes) {
        return bytes > 1024 * 1024
            ? (bytes / (1024 * 1024)).toFixed(1) + ' MB'
            : (bytes / 1024).toFixed(0) + ' KB';
    }

    function mostrarArchivo(file) {
        if (!file) return;

        dropzoneVacio.classList.add('d-none');
        dropzonePreview.classList.remove('d-none');
        previewNombre.textContent = file.name;
        previewPeso.textContent = formatoPeso(file.size);
        btnOcr.disabled = false;
        ocrEstado.textContent = '';
        errorArchivo.hidden = true;

        if (file.type.startsWith('image/')) {
            previewImagen.src = URL.createObjectURL(file);
            previewImagen.classList.remove('d-none');
            previewIconoPdf.classList.add('d-none');
        } else {
            previewImagen.classList.add('d-none');
            previewIconoPdf.classList.remove('d-none');
        }
    }

    dropzone.addEventListener('click', function (e) {
        if (e.target.closest('#btn-quitar')) return;
        inputArchivo.click();
    });

    inputArchivo.addEventListener('change', function () {
        mostrarArchivo(this.files[0]);
    });

    ['dragenter', 'dragover'].forEach(evento => {
        dropzone.addEventListener(evento, function (e) {
            e.preventDefault();
            dropzone.classList.add('dragover');
        });
    });

    ['dragleave', 'drop'].forEach(evento => {
        dropzone.addEventListener(evento, function (e) {
            e.preventDefault();
            dropzone.classList.remove('dragover');
        });
    });

    dropzone.addEventListener('drop', function (e) {
        const archivos = e.dataTransfer.files;
        if (archivos.length) {
            inputArchivo.files = archivos;
            mostrarArchivo(archivos[0]);
        }
    });

    btnQuitar.addEventListener('click', function (e) {
        e.stopPropagation();
        inputArchivo.value = '';
        btnOcr.disabled = true;
        dropzoneVacio.classList.remove('d-none');
        dropzonePreview.classList.add('d-none');
        ocrEstado.textContent = '';
    });

    btnOcr.addEventListener('click', async function () {
        if (!inputArchivo.files.length) {
            ocrEstado.textContent = 'Primero selecciona un archivo.';
            return;
        }

        const boton = this;
        const textoOriginal = boton.innerHTML;
        boton.disabled = true;
        boton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Procesando OCR...';
        ocrEstado.textContent = 'Esto puede tardar unos segundos.';

        const formData = new FormData();
        formData.append('archivo', inputArchivo.files[0]);
        formData.append('_token', document.querySelector('input[name="_token"]').value);

        try {
            const respuesta = await fetch('{{ route('documentos.ocr') }}', {
                method: 'POST',
                body: formData,
                headers: { 'Accept': 'application/json' },
            });
            const data = await respuesta.json();

            if (!data.ok) {
                ocrEstado.textContent = data.mensaje || 'No se pudo procesar el archivo.';
                return;
            }

            const campos = [
                ['curp', data.curp],
                ['nombre_completo', data.nombre_completo],
                ['numero_documento', data.numero_documento],
                ['fecha_nacimiento', data.fecha_nacimiento],
                ['entidad_nacimiento', data.entidad_nacimiento],
            ];

            let encontrados = 0;
            campos.forEach(([nombre, valor]) => {
                if (valor) {
                    document.querySelector(`[name="${nombre}"]`).value = valor;
                    encontrados++;
                }
            });
            document.getElementById('texto_extraido').value = data.texto || '';

            if (data.texto) {
                ocrTextoCrudoContenido.textContent = data.texto;
                ocrTextoCrudo.hidden = false;
            } else {
                ocrTextoCrudo.hidden = true;
            }

            ocrEstado.innerHTML = encontrados > 0
                ? '<i class="bi bi-check-circle-fill text-success"></i> Datos extraídos. Revísalos y corrígelos antes de guardar.'
                : '<i class="bi bi-exclamation-triangle-fill text-warning"></i> No se detectó ningún dato en la imagen. Completa los campos manualmente (revisa que la foto esté nítida y completa, y revisa abajo el texto que sí logró leer el OCR).';
        } catch (e) {
            ocrEstado.textContent = 'Error al conectar con el servidor.';
        } finally {
            boton.disabled = false;
            boton.innerHTML = textoOriginal;
        }
    });
</script>
@endpush
