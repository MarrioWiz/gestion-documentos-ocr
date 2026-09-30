<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('titulo', 'Gestión de Documentos')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --color-bg: #f3f5f9;
            --color-surface: #ffffff;
            --color-ink: #1e293b;
            --color-ink-soft: #64748b;
            --color-primary: #1e2a4a;
            --color-accent: #2563eb;
            --color-success: #16a34a;
            --color-success-bg: #ecfdf3;
            --color-danger: #dc2626;
            --color-danger-bg: #fef2f2;
            --color-warning: #d97706;
            --color-warning-bg: #fffbeb;
            --color-border: #e5e9f0;
        }

        * { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }

        body {
            background: var(--color-bg);
            color: var(--color-ink);
        }

        .navbar-gestion {
            background: var(--color-primary);
            box-shadow: 0 1px 3px rgba(0,0,0,.08);
        }
        .navbar-gestion .navbar-brand {
            font-weight: 700;
            letter-spacing: .2px;
            color: #fff;
        }
        .navbar-gestion .navbar-brand i { color: #7fa8ff; }

        .btn-accent {
            background: var(--color-accent);
            border-color: var(--color-accent);
            color: #fff;
        }
        .btn-accent:hover { background: #1d4fd1; border-color: #1d4fd1; color: #fff; }

        .card-soft {
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: 14px;
            box-shadow: 0 1px 2px rgba(16, 24, 40, .04);
        }

        .persona-card {
            display: block;
            text-decoration: none;
            color: inherit;
            transition: transform .12s ease, box-shadow .12s ease;
            height: 100%;
        }
        .persona-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(16, 24, 40, .08);
        }

        .avatar-circle {
            width: 46px;
            height: 46px;
            border-radius: 50%;
            background: #e8edfb;
            color: var(--color-accent);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1rem;
            flex-shrink: 0;
        }

        .doc-pill {
            width: 30px;
            height: 30px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: .85rem;
        }
        .doc-pill.completo { background: var(--color-success-bg); color: var(--color-success); }
        .doc-pill.faltante { background: var(--color-danger-bg); color: var(--color-danger); }

        .badge-completo {
            background: var(--color-success-bg);
            color: var(--color-success);
            font-weight: 600;
        }
        .badge-faltante {
            background: var(--color-danger-bg);
            color: var(--color-danger);
            font-weight: 600;
        }

        .dropzone {
            border: 2px dashed #c7d0e0;
            border-radius: 14px;
            background: #fafbfd;
            padding: 2.5rem 1.5rem;
            text-align: center;
            cursor: pointer;
            transition: border-color .12s ease, background .12s ease;
        }
        .dropzone.dragover {
            border-color: var(--color-accent);
            background: #eef3ff;
        }
        .dropzone i { font-size: 2rem; color: var(--color-ink-soft); }

        .page-title { font-weight: 700; color: var(--color-primary); }

        .toast-container { z-index: 1080; }

        #global-search {
            max-width: 340px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-gestion mb-4">
        <div class="container">
            <a class="navbar-brand" href="{{ route('personas.index') }}">
                <i class="bi bi-folder2-open"></i> Gestión de Documentos
            </a>
            <div class="d-flex gap-2">
                <a class="btn btn-outline-light btn-sm" href="{{ route('documentos.carga-masiva') }}">
                    <i class="bi bi-collection"></i> Carga masiva
                </a>
                <a class="btn btn-accent btn-sm" href="{{ route('documentos.create') }}">
                    <i class="bi bi-cloud-upload"></i> Subir documento
                </a>
            </div>
        </div>
    </nav>

    <div class="container pb-5">
        @yield('contenido')
    </div>

    <div class="toast-container position-fixed top-0 end-0 p-3" id="toast-container"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (function () {
            const mensajes = [
                @if (session('success')) { tipo: 'success', texto: @json(session('success')) }, @endif
                @if (session('warning')) { tipo: 'warning', texto: @json(session('warning')) }, @endif
                @if ($errors->any()) { tipo: 'danger', texto: @json($errors->first()) }, @endif
            ];

            const iconos = { success: 'bi-check-circle-fill', warning: 'bi-exclamation-triangle-fill', danger: 'bi-x-circle-fill' };
            const colores = { success: 'text-success', warning: 'text-warning', danger: 'text-danger' };
            const contenedor = document.getElementById('toast-container');

            mensajes.forEach(function (m) {
                const el = document.createElement('div');
                el.className = 'toast align-items-center border-0 shadow-sm mb-2';
                el.setAttribute('role', 'alert');
                el.innerHTML = `
                    <div class="d-flex">
                        <div class="toast-body">
                            <i class="bi ${iconos[m.tipo]} ${colores[m.tipo]} me-2"></i>${m.texto}
                        </div>
                        <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button>
                    </div>`;
                contenedor.appendChild(el);
                new bootstrap.Toast(el, { delay: 5000 }).show();
            });
        })();
    </script>
    @stack('scripts')
</body>
</html>
