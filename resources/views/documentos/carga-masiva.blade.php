@extends('layouts.app')

@section('titulo', 'Carga masiva')

@section('contenido')
    <h1 class="h4 page-title mb-1">Carga masiva de documentos</h1>
    <p class="text-muted mb-4">
        Sube varios archivos a la vez. El sistema detecta por OCR la CURP y el tipo de cada documento:
        si la persona no lo tenía, lo agrega solo; si ya lo tenía, lo omite; si no logra identificarlo,
        te lo marca para completarlo a mano.
    </p>

    <div id="dropzone-masivo" class="dropzone mb-4">
        <i class="bi bi-cloud-arrow-up d-block mb-2"></i>
        <p class="mb-1 fw-semibold">Arrastra varios archivos aquí</p>
        <p class="text-muted small mb-0">o haz clic para seleccionarlos &middot; JPG, PNG o PDF, máx. 10MB cada uno</p>
        <input type="file" id="input-masivo" class="d-none" multiple accept=".jpg,.jpeg,.png,.pdf">
    </div>

    <div id="resumen" class="row row-cols-2 row-cols-md-4 g-3 mb-4" hidden>
        <div class="col">
            <div class="card-soft p-3 text-center">
                <div class="fs-4 fw-bold" id="contador-total">0</div>
                <div class="text-muted small">Archivos</div>
            </div>
        </div>
        <div class="col">
            <div class="card-soft p-3 text-center">
                <div class="fs-4 fw-bold text-success" id="contador-guardado">0</div>
                <div class="text-muted small">Guardados</div>
            </div>
        </div>
        <div class="col">
            <div class="card-soft p-3 text-center">
                <div class="fs-4 fw-bold text-warning" id="contador-omitido">0</div>
                <div class="text-muted small">Ya existían</div>
            </div>
        </div>
        <div class="col">
            <div class="card-soft p-3 text-center">
                <div class="fs-4 fw-bold text-danger" id="contador-revision">0</div>
                <div class="text-muted small">Requieren revisión</div>
            </div>
        </div>
    </div>

    <div class="card-soft p-0" id="tabla-wrap" hidden>
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Archivo</th>
                        <th>Estado</th>
                        <th>Persona</th>
                        <th>Tipo</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="tabla-resultados"></tbody>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    const inputMasivo = document.getElementById('input-masivo');
    const dropzoneMasivo = document.getElementById('dropzone-masivo');
    const tablaWrap = document.getElementById('tabla-wrap');
    const tablaResultados = document.getElementById('tabla-resultados');
    const resumen = document.getElementById('resumen');
    const contadores = {
        total: document.getElementById('contador-total'),
        guardado: document.getElementById('contador-guardado'),
        omitido: document.getElementById('contador-omitido'),
        revision: document.getElementById('contador-revision'),
    };
    const totales = { total: 0, guardado: 0, omitido: 0, revision: 0 };

    dropzoneMasivo.addEventListener('click', () => inputMasivo.click());

    ['dragenter', 'dragover'].forEach(evento => {
        dropzoneMasivo.addEventListener(evento, function (e) {
            e.preventDefault();
            dropzoneMasivo.classList.add('dragover');
        });
    });

    ['dragleave', 'drop'].forEach(evento => {
        dropzoneMasivo.addEventListener(evento, function (e) {
            e.preventDefault();
            dropzoneMasivo.classList.remove('dragover');
        });
    });

    dropzoneMasivo.addEventListener('drop', function (e) {
        if (e.dataTransfer.files.length) {
            procesarArchivos(e.dataTransfer.files);
        }
    });

    inputMasivo.addEventListener('change', function () {
        if (this.files.length) {
            procesarArchivos(this.files);
        }
    });

    function actualizarContadores() {
        resumen.hidden = false;
        contadores.total.textContent = totales.total;
        contadores.guardado.textContent = totales.guardado;
        contadores.omitido.textContent = totales.omitido;
        contadores.revision.textContent = totales.revision;
    }

    function badge(status) {
        const mapa = {
            guardado: '<span class="badge badge-completo rounded-pill px-3 py-2"><i class="bi bi-check-lg"></i> Guardado</span>',
            omitido: '<span class="badge bg-warning-subtle text-warning-emphasis rounded-pill px-3 py-2"><i class="bi bi-info-circle"></i> Ya existía</span>',
            revision: '<span class="badge badge-faltante rounded-pill px-3 py-2"><i class="bi bi-exclamation-lg"></i> Revisar</span>',
        };
        return mapa[status] || status;
    }

    async function procesarArchivos(archivos) {
        tablaWrap.hidden = false;
        const token = document.querySelector('meta[name="csrf-token"]')?.content
            ?? '{{ csrf_token() }}';

        for (const archivo of Array.from(archivos)) {
            const fila = document.createElement('tr');
            fila.innerHTML = `
                <td>${archivo.name}</td>
                <td><span class="spinner-border spinner-border-sm"></span> Procesando...</td>
                <td>-</td><td>-</td><td></td>`;
            tablaResultados.appendChild(fila);

            const formData = new FormData();
            formData.append('archivo', archivo);
            formData.append('_token', token);

            try {
                const respuesta = await fetch('{{ route('documentos.carga-masiva.procesar') }}', {
                    method: 'POST',
                    body: formData,
                    headers: { 'Accept': 'application/json' },
                });
                const data = await respuesta.json();

                totales.total++;
                totales[data.status] = (totales[data.status] || 0) + 1;
                actualizarContadores();

                const celdas = fila.querySelectorAll('td');
                celdas[1].innerHTML = badge(data.status);

                if (data.status === 'guardado') {
                    celdas[2].innerHTML = `<a href="/personas/${data.persona_id}">${data.persona_nombre}</a>${data.persona_nueva ? ' <span class="text-muted small">(nueva)</span>' : ''}`;
                    celdas[3].textContent = data.tipo_documento;
                } else if (data.status === 'omitido') {
                    celdas[2].textContent = data.persona_nombre;
                    celdas[3].textContent = data.tipo_documento;
                    celdas[4].innerHTML = `<span class="text-muted small">${data.mensaje}</span>`;
                } else {
                    celdas[2].textContent = data.curp || '-';
                    celdas[3].textContent = data.tipo_documento || '-';
                    const params = new URLSearchParams();
                    if (data.curp) params.set('curp', data.curp);
                    if (data.nombre_completo) params.set('nombre_completo', data.nombre_completo);
                    celdas[4].innerHTML = `
                        <div class="small text-muted mb-1">${data.mensaje}</div>
                        <a href="/documentos/crear?${params.toString()}" class="btn btn-sm btn-outline-secondary">
                            Completar manualmente
                        </a>`;
                }
            } catch (e) {
                totales.total++;
                totales.revision++;
                actualizarContadores();
                const celdas = fila.querySelectorAll('td');
                celdas[1].innerHTML = badge('revision');
                celdas[4].innerHTML = '<span class="text-danger small">Error de conexión.</span>';
            }
        }
    }
</script>
@endpush
