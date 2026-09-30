<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión · DocuVault</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    @include('layouts.estilos')
    <style>
        .login-wrap { min-height: 100vh; display: grid; place-items: center; padding: 1.5rem 1rem; }
        .login-card { width: 100%; max-width: 420px; }
        .escaneo {
            height: 2px; margin: 0 -2rem 1.75rem;
            background: linear-gradient(90deg, transparent, var(--color-accent), var(--color-accent-2), transparent);
            animation: escanear 3s ease-in-out infinite;
        }
        @keyframes escanear { 0%, 100% { opacity: .35; transform: scaleX(.6); } 50% { opacity: 1; transform: scaleX(1); } }
        @media (prefers-reduced-motion: reduce) { .escaneo { animation: none; } }
    </style>
</head>
<body>
    <div class="fondo-malla" aria-hidden="true"></div>

    <div class="login-wrap">
        <div class="login-card">
            <div class="text-center mb-4">
                <span class="logo-orb mb-3" style="width:56px;height:56px;font-size:1.5rem;border-radius:16px;"><i class="bi bi-shield-lock-fill"></i></span>
                <h1 class="h3 page-title mb-1">Docu<span class="text-gradiente">Vault</span></h1>
                <p class="text-muted small mb-0">Expedientes de identidad con extracción inteligente por OCR</p>
            </div>

            <div class="card-soft p-4 p-sm-5 overflow-hidden">
                <div class="escaneo"></div>

                <form method="POST" action="{{ route('login.intentar') }}" novalidate>
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-semibold small" for="email">Correo</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                            <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus
                                   class="form-control @error('email') is-invalid @enderror" autocomplete="username">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small" for="password">Contraseña</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-key"></i></span>
                            <input type="password" id="password" name="password" required
                                   class="form-control @error('email') is-invalid @enderror" autocomplete="current-password">
                        </div>
                    </div>

                    @error('email')
                        <div class="alert alert-danger py-2 small"><i class="bi bi-x-circle"></i> {{ $message }}</div>
                    @enderror

                    <div class="form-check mb-4">
                        <input class="form-check-input" type="checkbox" name="recordar" id="recordar">
                        <label class="form-check-label small text-muted" for="recordar">Mantener la sesión iniciada</label>
                    </div>

                    <button type="submit" class="btn btn-accent w-100 py-2">
                        <i class="bi bi-box-arrow-in-right"></i> Entrar
                    </button>
                </form>
            </div>

            @if (app()->environment('local', 'testing'))
                {{-- Solo en modo local/pruebas: en producción esto no se muestra. --}}
                <div class="card-soft p-3 mt-3 small" style="border-color: rgba(251, 191, 36, .45);">
                    <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                        <span class="fw-semibold text-warning"><i class="bi bi-person-badge"></i> Cuenta de prueba</span>
                        <span class="badge badge-aviso">solo para pruebas</span>
                    </div>
                    <div class="text-muted">Correo: <span class="font-monospace text-reset">{{ config('app.cuenta_demo.email') }}</span></div>
                    <div class="text-muted mb-2">Contraseña: <span class="font-monospace text-reset">{{ config('app.cuenta_demo.password') }}</span></div>
                    <button type="button" id="usar-demo" class="btn btn-ghost btn-sm w-100">
                        <i class="bi bi-magic"></i> Llenar con la cuenta de prueba
                    </button>
                </div>
                <script>
                    document.getElementById('usar-demo').addEventListener('click', function () {
                        document.getElementById('email').value = @json(config('app.cuenta_demo.email'));
                        document.getElementById('password').value = @json(config('app.cuenta_demo.password'));
                    });
                </script>
            @endif

            <p class="text-center text-muted small mt-4 mb-0">
                <i class="bi bi-lock"></i> Acceso solo con cuenta autorizada. Los archivos nunca son públicos.
            </p>
        </div>
    </div>
</body>
</html>
