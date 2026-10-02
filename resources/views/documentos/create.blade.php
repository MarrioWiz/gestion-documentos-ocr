@extends('layouts.app')

@section('titulo', 'Subir documento')

@section('contenido')
    <div class="eyebrow mb-1">Captura asistida</div>
    <h1 class="h3 page-title mb-4">Subir documento</h1>

    @if (session('confirmar_reemplazo'))
        @php $pendiente = session('confirmar_reemplazo'); @endphp
        <div class="card-soft p-3 mb-4" style="border-color: rgba(251, 191, 36, .5);">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-exclamation-triangle-fill text-warning fs-5"></i>
                    <div>
                        Ya existe un documento tipo <strong>{{ \App\Models\Persona::etiquetaTipo($pendiente['tipo_documento']) }}</strong>
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
                        <button class="btn btn-sm btn-ghost" type="submit">Cancelar</button>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-lg-5 order-lg-2">
            <div class="card-soft p-4 h-100">
                <h2 class="h6 mb-3"><span class="text-gradiente fw-bold">1.</span> Archivo</h2>

                <div id="dropzone" class="dropzone" role="button" tabindex="0" aria-label="Seleccionar archivo">
                    <div id="dropzone-vacio">
                        <i class="bi bi-cloud-arrow-up d-block mb-2"></i>
                        <p class="mb-1 fw-semibold">Arrastra tu archivo aquí</p>
                        <p class="text-muted small mb-0">o haz clic para seleccionarlo &middot; JPG, PNG, WEBP o PDF, máx. 10MB</p>
                    </div>
                    <div id="dropzone-preview" class="d-none">
                        <img id="preview-imagen" class="d-none rounded-3 mb-2" style="max-height: 220px; max-width: 100%;" alt="Vista previa">
                        <i id="preview-icono-pdf" class="bi bi-file-earmark-pdf-fill d-none" style="font-size: 3rem; color: var(--color-danger);"></i>
                        <p id="preview-nombre" class="fw-semibold mb-0 mt-2 text-break"></p>
                        <p id="preview-peso" class="text-muted small mb-0"></p>
                        <button type="button" id="btn-quitar" class="btn btn-sm btn-ghost mt-2">
                            <i class="bi bi-x-lg"></i> Quitar
                        </button>
                    </div>
                </div>
                <p id="error-archivo" class="text-danger small mt-2 mb-0" hidden>
                    <i class="bi bi-exclamation-circle"></i> Selecciona un archivo antes de guardar.
                </p>

                <button type="button" id="btn-ocr" class="btn btn-accent w-100 mt-3" disabled>
                    <i class="bi bi-magic"></i> Extraer datos con OCR
                </button>
                <div id="ocr-estado" class="text-muted small mt-2" aria-live="polite"></div>
                <div id="aviso-persona" class="small mt-3" hidden></div>
                <details id="ocr-texto-crudo" class="small mt-3" hidden>
                    <summary class="text-muted">Ver texto que leyó el OCR</summary>
                    <pre id="ocr-texto-crudo-contenido" class="small p-2 rounded mt-1 texto-ocr" style="white-space: pre-wrap;"></pre>
                </details>
            </div>
        </div>

        <div class="col-lg-7 order-lg-1">
            <div class="card-soft p-4">
                <h2 class="h6 mb-3"><span class="text-gradiente fw-bold">2.</span> Revisa los datos</h2>
                <form method="POST" action="{{ route('documentos.store') }}" enctype="multipart/form-data" id="form-documento">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="curp">CURP <span id="curp-verificada" class="badge badge-completo ms-1" hidden><i class="bi bi-patch-check"></i> dígito verificador correcto</span></label>
                        <input type="text" id="curp" name="curp" maxlength="18"
                               class="form-control text-uppercase font-monospace @error('curp') is-invalid @enderror"
                               value="{{ old('curp', $curpPrellenada) }}" required>
                        @error('curp') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <div class="form-text">Se usa para saber si la persona ya existe: si existe, el documento se agrega a su expediente.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="nombre_completo">Nombre completo</label>
                        <input type="text" id="nombre_completo" name="nombre_completo"
                               class="form-control text-uppercase @error('nombre_completo') is-invalid @enderror"
                               value="{{ old('nombre_completo', $nombrePrellenado) }}" required>
                        @error('nombre_completo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold" for="fecha_nacimiento">Fecha de nacimiento</label>
                            <input type="date" id="fecha_nacimiento" name="fecha_nacimiento"
                                   class="form-control @error('fecha_nacimiento') is-invalid @enderror"
                                   value="{{ old('fecha_nacimiento') }}">
                            @error('fecha_nacimiento') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold" for="entidad_nacimiento">Entidad de nacimiento</label>
                            <select id="entidad_nacimiento" name="entidad_nacimiento" class="form-select @error('entidad_nacimiento') is-invalid @enderror">
                                <option value="">-- Selecciona --</option>
                                @foreach (\App\Services\Curp::ENTIDADES as $entidad)
                                    <option value="{{ $entidad }}" @selected(old('entidad_nacimiento') === $entidad)>{{ $entidad }}</option>
                                @endforeach
                            </select>
                            @error('entidad_nacimiento') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="form-text mt-1">Si las dejas vacías se deducen de la CURP.</div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold" for="tipo_documento">Tipo de documento</label>
                            <select id="tipo_documento" name="tipo_documento" class="form-select @error('tipo_documento') is-invalid @enderror" required>
                                <option value="">-- Selecciona --</option>
                                @foreach (\App\Models\Persona::TIPOS_DOCUMENTO as $tipo)
                                    <option value="{{ $tipo }}" @selected(old('tipo_documento', $tipoPrellenado) === $tipo)>
                                        {{ \App\Models\Persona::etiquetaTipo($tipo) }}
                                    </option>
                                @endforeach
                            </select>
                            @error('tipo_documento') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold" for="numero_documento">Número de documento</label>
                            <input type="text" id="numero_documento" name="numero_documento"
                                   class="form-control font-monospace @error('numero_documento') is-invalid @enderror"
                                   value="{{ old('numero_documento') }}">
                            @error('numero_documento') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <input type="file" id="archivo" name="archivo" accept=".jpg,.jpeg,.png,.webp,.pdf" class="d-none">
                    <input type="hidden" name="texto_extraido" id="texto_extraido">

                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-accent px-4">
                            <i class="bi bi-check-lg"></i> Guardar
                        </button>
                    </div>
                    @error('archivo') <div class="text-danger small mt-2">{{ $message }}</div> @enderror
                </form>
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
    const avisoPersona = document.getElementById('aviso-persona');
    const curpVerificada = document.getElementById('curp-verificada');

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
        ocrEstado.textContent = 'Listo. Pulsa "Extraer datos con OCR" para llenar el formulario automáticamente.';
        errorArchivo.hidden = true;
        avisoPersona.hidden = true;

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
    dropzone.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            inputArchivo.click();
        }
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
        avisoPersona.hidden = true;
    });

    btnOcr.addEventListener('click', async function () {
        if (!inputArchivo.files.length) {
            ocrEstado.textContent = 'Primero selecciona un archivo.';
            return;
        }

        const boton = this;
        const textoOriginal = boton.innerHTML;
        boton.disabled = true;
        boton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Analizando documento...';
        ocrEstado.textContent = 'Esto puede tardar unos segundos (si la foto es difícil, se hacen varias lecturas).';
        avisoPersona.hidden = true;

        const formData = new FormData();
        formData.append('archivo', inputArchivo.files[0]);
        formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);

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

            // Si el formulario ya tenía la CURP de alguien (p. ej. se llegó
            // desde "Agregar documento" de un expediente) y el archivo es de
            // otra persona, se avisa y el formulario pasa a la persona dueña
            // del documento: nunca se mezcla un documento en otro expediente.
            const curpAntes = document.querySelector('[name="curp"]').value.trim().toUpperCase();
            const esDeOtraPersona = data.curp && curpAntes.length === 18 && curpAntes !== data.curp;

            const campos = [
                ['curp', data.curp],
                ['nombre_completo', data.nombre_completo],
                ['numero_documento', data.numero_documento],
                ['fecha_nacimiento', data.fecha_nacimiento],
                ['entidad_nacimiento', data.entidad_nacimiento],
                ['tipo_documento', data.tipo_documento],
            ];

            let encontrados = 0;
            campos.forEach(([nombre, valor]) => {
                if (valor) {
                    document.querySelector(`[name="${nombre}"]`).value = valor;
                    encontrados++;
                }
            });
            document.getElementById('texto_extraido').value = data.texto || '';
            curpVerificada.hidden = !data.curp_verificada;

            if (data.texto) {
                ocrTextoCrudoContenido.textContent = data.texto;
                ocrTextoCrudo.hidden = false;
            } else {
                ocrTextoCrudo.hidden = true;
            }

            if (esDeOtraPersona) {
                avisoPersona.className = 'small mt-3 p-3 rounded-3 badge-faltante';
                avisoPersona.innerHTML = `<i class="bi bi-person-exclamation"></i> <strong>Este documento es de otra persona.</strong>
                    <div class="mt-1">Tenías la CURP <span class="font-monospace">${escaparHtml(curpAntes)}</span>, pero el documento es de
                    <strong>${escaparHtml(data.persona_existente ? data.persona_existente.nombre : (data.nombre_completo || 'otra persona'))}</strong>
                    (<span class="font-monospace">${escaparHtml(data.curp)}</span>).</div>
                    <div class="mt-1">Cambié el formulario a su dueño; si guardas, se agregará a <strong>su</strong> expediente.</div>`;
                avisoPersona.hidden = false;
            } else if (data.persona_existente) {
                const p = data.persona_existente;
                avisoPersona.className = 'small mt-3 p-3 rounded-3 ' + (p.ya_tiene_tipo ? 'badge-aviso' : 'badge-info');
                avisoPersona.innerHTML = p.ya_tiene_tipo
                    ? `<i class="bi bi-info-circle"></i> <strong>${escaparHtml(p.nombre)}</strong> ya tiene este documento. Si guardas, te preguntaremos si quieres reemplazarlo.`
                    : `<i class="bi bi-person-check"></i> <strong>${escaparHtml(p.nombre)}</strong> ya está registrada. Este documento se agregará a su expediente.`
                        + (p.faltantes.length ? `<div class="mt-1 text-muted">Le faltan: ${escaparHtml(p.faltantes.join(', '))}</div>` : '');
                avisoPersona.hidden = false;
            }

            ocrEstado.innerHTML = encontrados > 0
                ? '<i class="bi bi-check-circle-fill text-success"></i> Datos extraídos. Revísalos y corrígelos antes de guardar.'
                : '<i class="bi bi-exclamation-triangle-fill text-warning"></i> No se detectó ningún dato. Completa los campos manualmente (revisa que la foto esté nítida y completa, y revisa abajo el texto que sí logró leer el OCR).';
        } catch (e) {
            ocrEstado.textContent = 'Error al conectar con el servidor.';
        } finally {
            boton.disabled = false;
            boton.innerHTML = textoOriginal;
        }
    });
</script>
@endpush
