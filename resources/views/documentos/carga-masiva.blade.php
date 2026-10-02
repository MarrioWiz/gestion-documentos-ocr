@extends('layouts.app')

@section('titulo', 'Carga masiva')

@section('contenido')
    <div class="eyebrow mb-1">Automático</div>
    <h1 class="h3 page-title mb-1">Carga masiva de documentos</h1>
    <p class="text-muted mb-4" style="max-width: 760px;">
        Sube varios archivos a la vez (por ejemplo la INE, la CURP y el acta de una o varias personas).
        El sistema lee cada uno por OCR, detecta su tipo y la CURP de la persona:
        si la persona <strong>ya existe</strong> y le falta ese documento, se lo agrega;
        si <strong>no existe</strong>, la registra; si ya lo tenía, lo omite; y si no puede identificarlo con seguridad, te lo marca para completarlo a mano.
    </p>

    <div id="dropzone-masivo" class="dropzone mb-4" role="button" tabindex="0" aria-label="Seleccionar archivos">
        <i class="bi bi-cloud-arrow-up d-block mb-2"></i>
        <p class="mb-1 fw-semibold">Arrastra varios archivos aquí</p>
        <p class="text-muted small mb-0">o haz clic para seleccionarlos &middot; JPG, PNG, WEBP o PDF, máx. 10MB cada uno</p>
        <input type="file" id="input-masivo" class="d-none" multiple accept=".jpg,.jpeg,.png,.webp,.pdf">
    </div>

    <div id="resumen" class="row row-cols-2 row-cols-md-4 g-3 mb-4" hidden>
        @foreach ([
            ['id' => 'total', 'texto' => 'Archivos', 'clase' => ''],
            ['id' => 'guardado', 'texto' => 'Guardados', 'clase' => 'text-success'],
            ['id' => 'omitido', 'texto' => 'Ya existían', 'clase' => 'text-warning'],
            ['id' => 'revision', 'texto' => 'Requieren revisión', 'clase' => 'text-danger'],
        ] as $contador)
            <div class="col">
                <div class="card-soft p-3 text-center">
                    <div class="stat-valor fs-3 {{ $contador['clase'] }}" id="contador-{{ $contador['id'] }}">0</div>
                    <div class="text-muted small">{{ $contador['texto'] }}</div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card-soft p-0 overflow-hidden" id="tabla-wrap" hidden>
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr>
                        <th class="ps-4">Archivo</th>
                        <th>Estado</th>
                        <th>Persona</th>
                        <th>Tipo</th>
                        <th class="pe-4">Detalle</th>
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
    const totales = { total: 0, guardado: 0, omitido: 0, revision: 0 };
    const etiquetas = @json(collect(\App\Models\Persona::META_DOCUMENTO)->map(fn ($m) => $m['label']));
    const urlPersona = @json(url('/personas')) + '/';
    const urlCrear = @json(route('documentos.create'));

    dropzoneMasivo.addEventListener('click', () => inputMasivo.click());
    dropzoneMasivo.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            inputMasivo.click();
        }
    });

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
            this.value = '';
        }
    });

    function actualizarContadores() {
        resumen.hidden = false;
        Object.keys(totales).forEach(clave => {
            document.getElementById('contador-' + clave).textContent = totales[clave];
        });
    }

    function badge(status) {
        const mapa = {
            guardado: '<span class="badge badge-completo rounded-pill px-3 py-2"><i class="bi bi-check-lg"></i> Guardado</span>',
            omitido: '<span class="badge badge-aviso rounded-pill px-3 py-2"><i class="bi bi-info-circle"></i> Ya existía</span>',
            revision: '<span class="badge badge-faltante rounded-pill px-3 py-2"><i class="bi bi-exclamation-lg"></i> Revisar</span>',
        };
        return mapa[status] || escaparHtml(status);
    }

    function etiquetaTipo(tipo) {
        return tipo ? escaparHtml(etiquetas[tipo] || tipo) : '-';
    }

    function pintarFila(celdas, data) {
        celdas[1].innerHTML = badge(data.status);
        celdas[3].innerHTML = etiquetaTipo(data.tipo_documento);

        if (data.status === 'guardado') {
            celdas[2].innerHTML = `<a href="${urlPersona}${Number(data.persona_id)}">${escaparHtml(data.persona_nombre)}</a>`
                + (data.persona_nueva ? ' <span class="badge badge-info ms-1">nueva</span>' : '');
            celdas[4].innerHTML = `
                <div class="small">${escaparHtml(data.mensaje)}</div>
                <div class="d-flex align-items-center gap-2 mt-1" style="min-width: 160px;">
                    <div class="avance flex-grow-1"><span style="width: ${Number(data.porcentaje)}%"></span></div>
                    <span class="small text-muted">${Number(data.porcentaje)}%</span>
                </div>
                ${data.faltantes && data.faltantes.length ? `<div class="small text-muted mt-1">Le falta: ${escaparHtml(data.faltantes.join(', '))}</div>` : '<div class="small text-success mt-1"><i class="bi bi-patch-check"></i> Expediente completo</div>'}`;
        } else if (data.status === 'omitido') {
            celdas[2].innerHTML = data.persona_id
                ? `<a href="${urlPersona}${Number(data.persona_id)}">${escaparHtml(data.persona_nombre)}</a>`
                : escaparHtml(data.persona_nombre || '-');
            celdas[4].innerHTML = `<span class="text-muted small">${escaparHtml(data.mensaje)}</span>`;
        } else {
            celdas[2].innerHTML = `<span class="font-monospace small">${escaparHtml(data.curp || '-')}</span>`;
            const params = new URLSearchParams();
            if (data.curp) params.set('curp', data.curp);
            if (data.nombre_completo) params.set('nombre_completo', data.nombre_completo);
            if (data.tipo_documento) params.set('tipo_documento', data.tipo_documento);
            celdas[4].innerHTML = `
                <div class="small text-muted mb-1">${escaparHtml(data.mensaje)}</div>
                <a href="${urlCrear}?${params.toString()}" class="btn btn-sm btn-ghost">
                    <i class="bi bi-pencil-square"></i> Completar manualmente
                </a>`;
        }
    }

    // Una sola fila de espera para TODA la página: si se sueltan archivos
    // mientras otros se procesan, se forman al final en vez de procesarse en
    // paralelo. Así nunca se registran a la vez dos documentos de la misma
    // persona nueva, y el OCR (que usa mucho CPU) no satura el servidor.
    const cola = [];
    let procesando = false;

    function procesarArchivos(archivos) {
        tablaWrap.hidden = false;
        Array.from(archivos).forEach(archivo => {
            const fila = document.createElement('tr');
            fila.innerHTML = `
                <td class="ps-4 small text-break">${escaparHtml(archivo.name)}</td>
                <td><span class="text-muted small"><i class="bi bi-hourglass-split"></i> En cola</span></td>
                <td>-</td><td>-</td><td class="pe-4"></td>`;
            tablaResultados.appendChild(fila);
            cola.push([archivo, fila]);
        });

        if (!procesando) {
            vaciarCola();
        }
    }

    async function vaciarCola() {
        procesando = true;
        const token = document.querySelector('meta[name="csrf-token"]').content;

        while (cola.length) {
            const [archivo, fila] = cola.shift();
            const celdas = fila.querySelectorAll('td');
            celdas[1].innerHTML = '<span class="spinner-border spinner-border-sm text-info"></span> <span class="small">Analizando...</span>';

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

                if (!respuesta.ok) {
                    data.status = 'revision';
                    // 422 = validación (formato/tamaño): ese mensaje sí es útil.
                    // Cualquier otro error se muestra genérico, sin detalles técnicos.
                    data.mensaje = respuesta.status === 422 && data.message
                        ? data.message
                        : 'No se pudo procesar el archivo. Intenta de nuevo.';
                }

                totales.total++;
                totales[data.status] = (totales[data.status] || 0) + 1;
                actualizarContadores();
                pintarFila(celdas, data);
            } catch (e) {
                totales.total++;
                totales.revision++;
                actualizarContadores();
                celdas[1].innerHTML = badge('revision');
                celdas[4].innerHTML = '<span class="text-danger small">Error de conexión.</span>';
            }
        }

        procesando = false;
    }
</script>
@endpush
