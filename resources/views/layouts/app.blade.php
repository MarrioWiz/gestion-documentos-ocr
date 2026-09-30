<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('titulo', 'Gestión de Documentos') · DocuVault</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    @include('layouts.estilos')
</head>
<body>
    <div class="fondo-malla" aria-hidden="true"></div>

    <nav class="navbar navbar-expand-xl navbar-gestion sticky-top mb-4">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="{{ route('dashboard') }}">
                <span class="logo-orb"><i class="bi bi-shield-lock-fill"></i></span>
                <span>Docu<span class="text-gradiente">Vault</span></span>
            </a>
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#menu-principal" aria-label="Abrir menú">
                <i class="bi bi-list fs-3"></i>
            </button>
            <div class="collapse navbar-collapse" id="menu-principal">
                <ul class="navbar-nav me-auto ms-xl-4 gap-xl-1">
                    <li class="nav-item">
                        <a class="nav-link @if(request()->routeIs('dashboard')) active @endif" href="{{ route('dashboard') }}"><i class="bi bi-grid-1x2"></i> Panel</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link @if(request()->routeIs('personas.*')) active @endif" href="{{ route('personas.index') }}"><i class="bi bi-people"></i> Personas</a>
                    </li>
                    @if (auth()->user()?->es_admin)
                        <li class="nav-item">
                            <a class="nav-link @if(request()->routeIs('historial.*')) active @endif" href="{{ route('historial.index') }}"><i class="bi bi-activity"></i> Historial</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link @if(request()->routeIs('usuarios.*')) active @endif" href="{{ route('usuarios.index') }}"><i class="bi bi-person-gear"></i> Usuarios</a>
                        </li>
                    @endif
                </ul>
                <div class="d-flex flex-wrap align-items-center gap-2 py-2 py-xl-0">
                    <a class="btn btn-ghost btn-sm" href="{{ route('documentos.carga-masiva') }}">
                        <i class="bi bi-collection"></i> Carga masiva
                    </a>
                    <a class="btn btn-accent btn-sm" href="{{ route('documentos.create') }}">
                        <i class="bi bi-cloud-upload"></i> Subir documento
                    </a>
                    <div class="dropdown">
                        <button class="btn btn-ghost btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle"></i> {{ \Illuminate\Support\Str::of(auth()->user()->name)->before(' ') }}
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><span class="dropdown-item-text small text-muted">{{ auth()->user()->email }}<br>{{ auth()->user()->es_admin ? 'Administrador' : 'Capturista' }}</span></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button class="dropdown-item" type="submit"><i class="bi bi-box-arrow-right"></i> Cerrar sesión</button>
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <main class="container pb-5 position-relative">
        @yield('contenido')
    </main>

    <div class="toast-container position-fixed top-0 end-0 p-3" id="toast-container"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Escapa texto antes de meterlo en innerHTML (nombres de archivo,
        // nombres de personas y mensajes vienen de datos del usuario).
        function escaparHtml(texto) {
            const div = document.createElement('div');
            div.textContent = texto ?? '';
            return div.innerHTML;
        }

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
                el.className = 'toast align-items-center border-0 mb-2';
                el.setAttribute('role', 'alert');
                el.innerHTML = `
                    <div class="d-flex">
                        <div class="toast-body">
                            <i class="bi ${iconos[m.tipo]} ${colores[m.tipo]} me-2"></i>${escaparHtml(m.texto)}
                        </div>
                        <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Cerrar"></button>
                    </div>`;
                contenedor.appendChild(el);
                new bootstrap.Toast(el, { delay: 6000 }).show();
            });
        })();
    </script>
    @stack('scripts')
</body>
</html>
