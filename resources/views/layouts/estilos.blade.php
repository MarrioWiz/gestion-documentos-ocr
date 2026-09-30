<style>
    :root {
        --color-bg: #060a14;
        --color-surface: rgba(17, 25, 44, .72);
        --color-surface-solida: #0f172a;
        --color-surface-alta: rgba(30, 41, 66, .7);
        --color-ink: #e2e8f0;
        --color-ink-soft: #94a3b8;
        --color-ink-muted: #64748b;
        --color-primary: #f1f5f9;
        --color-accent: #22d3ee;
        --color-accent-2: #a78bfa;
        --color-accent-ink: #04111a;
        --color-success: #34d399;
        --color-success-bg: rgba(52, 211, 153, .12);
        --color-danger: #fb7185;
        --color-danger-bg: rgba(251, 113, 133, .12);
        --color-warning: #fbbf24;
        --color-warning-bg: rgba(251, 191, 36, .12);
        --color-border: rgba(148, 163, 184, .14);
        --color-border-glow: rgba(34, 211, 238, .35);
        --bs-body-bg: var(--color-bg);
        --bs-body-color: var(--color-ink);
        --bs-secondary-color: var(--color-ink-soft);
        --bs-border-color: var(--color-border);
        --bs-link-color-rgb: 34, 211, 238;
        --bs-link-hover-color-rgb: 103, 232, 249;
    }

    * { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
    .bi { font-family: bootstrap-icons !important; }

    body {
        background: var(--color-bg);
        color: var(--color-ink);
        min-height: 100vh;
    }

    /* Malla de luz de fondo: dos halos difusos + retícula tenue. */
    .fondo-malla {
        position: fixed;
        inset: 0;
        z-index: -1;
        background:
            radial-gradient(600px circle at 10% 0%, rgba(34, 211, 238, .13), transparent 60%),
            radial-gradient(700px circle at 95% 20%, rgba(167, 139, 250, .14), transparent 60%),
            linear-gradient(rgba(148, 163, 184, .05) 1px, transparent 1px) 0 0 / 44px 44px,
            linear-gradient(90deg, rgba(148, 163, 184, .05) 1px, transparent 1px) 0 0 / 44px 44px,
            var(--color-bg);
    }

    h1, h2, h3, .page-title, .navbar-brand, .stat-valor { font-family: 'Space Grotesk', 'Inter', sans-serif !important; }
    .text-muted { color: var(--color-ink-soft) !important; }

    .text-gradiente {
        background: linear-gradient(90deg, var(--color-accent), var(--color-accent-2));
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
    }

    .navbar-gestion {
        background: rgba(6, 10, 20, .7);
        backdrop-filter: blur(14px);
        -webkit-backdrop-filter: blur(14px);
        border-bottom: 1px solid var(--color-border);
    }
    .navbar-gestion .navbar-brand { font-weight: 700; letter-spacing: .3px; color: #fff; }
    .navbar-gestion .nav-link { color: var(--color-ink-soft); border-radius: 10px; padding: .45rem .8rem !important; }
    .navbar-gestion .nav-link:hover { color: #fff; background: rgba(148, 163, 184, .08); }
    .navbar-gestion .nav-link.active { color: var(--color-accent); background: rgba(34, 211, 238, .1); }

    .logo-orb {
        width: 34px; height: 34px;
        border-radius: 10px;
        display: inline-flex; align-items: center; justify-content: center;
        background: linear-gradient(135deg, var(--color-accent), var(--color-accent-2));
        color: var(--color-accent-ink);
        box-shadow: 0 0 22px rgba(34, 211, 238, .45);
    }

    .btn-accent {
        background: linear-gradient(135deg, var(--color-accent), #38bdf8);
        border: 0;
        color: var(--color-accent-ink);
        font-weight: 600;
        box-shadow: 0 0 18px rgba(34, 211, 238, .25);
    }
    .btn-accent:hover, .btn-accent:focus { color: var(--color-accent-ink); filter: brightness(1.1); box-shadow: 0 0 26px rgba(34, 211, 238, .45); }
    .btn-ghost {
        background: rgba(148, 163, 184, .08);
        border: 1px solid var(--color-border);
        color: var(--color-ink);
    }
    .btn-ghost:hover, .btn-ghost.show { background: rgba(148, 163, 184, .16); color: #fff; border-color: var(--color-border-glow); }

    .card-soft {
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: 16px;
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        box-shadow: 0 10px 30px rgba(0, 0, 0, .25), inset 0 1px 0 rgba(255, 255, 255, .04);
    }

    .persona-card { display: block; text-decoration: none; color: inherit; height: 100%; }
    .persona-card .card-soft { transition: transform .15s ease, border-color .15s ease, box-shadow .15s ease; }
    .persona-card:hover .card-soft {
        transform: translateY(-3px);
        border-color: var(--color-border-glow);
        box-shadow: 0 0 0 1px rgba(34, 211, 238, .15), 0 12px 30px rgba(34, 211, 238, .12);
    }

    .avatar-circle {
        width: 46px; height: 46px;
        border-radius: 14px;
        background: linear-gradient(135deg, rgba(34, 211, 238, .2), rgba(167, 139, 250, .25));
        border: 1px solid var(--color-border-glow);
        color: #e0f2fe;
        display: flex; align-items: center; justify-content: center;
        font-weight: 700; font-size: 1rem; flex-shrink: 0;
    }

    .doc-pill {
        width: 30px; height: 30px;
        border-radius: 9px;
        display: inline-flex; align-items: center; justify-content: center;
        font-size: .85rem;
    }
    .doc-pill.completo { background: var(--color-success-bg); color: var(--color-success); box-shadow: 0 0 12px rgba(52, 211, 153, .15); }
    .doc-pill.faltante { background: rgba(148, 163, 184, .08); color: var(--color-ink-muted); border: 1px dashed rgba(148, 163, 184, .3); }

    .badge-completo { background: var(--color-success-bg); color: var(--color-success); font-weight: 600; }
    .badge-faltante { background: var(--color-danger-bg); color: var(--color-danger); font-weight: 600; }
    .badge-aviso { background: var(--color-warning-bg); color: var(--color-warning); font-weight: 600; }
    .badge-info { background: rgba(34, 211, 238, .12); color: var(--color-accent); font-weight: 600; }

    /* Barra de avance del expediente. */
    .avance { height: 6px; border-radius: 99px; background: rgba(148, 163, 184, .12); overflow: hidden; }
    .avance > span {
        display: block; height: 100%; border-radius: 99px;
        background: linear-gradient(90deg, var(--color-accent), var(--color-accent-2));
        box-shadow: 0 0 10px rgba(34, 211, 238, .5);
    }

    .dropzone {
        border: 1.5px dashed rgba(34, 211, 238, .35);
        border-radius: 16px;
        background: rgba(34, 211, 238, .03);
        padding: 2.5rem 1.5rem;
        text-align: center;
        cursor: pointer;
        transition: border-color .15s ease, background .15s ease, box-shadow .15s ease;
    }
    .dropzone:hover, .dropzone.dragover {
        border-color: var(--color-accent);
        background: rgba(34, 211, 238, .08);
        box-shadow: 0 0 30px rgba(34, 211, 238, .15) inset;
    }
    .dropzone i { font-size: 2.2rem; color: var(--color-accent); }

    .page-title { font-weight: 700; color: var(--color-primary); letter-spacing: -.3px; }
    .eyebrow { font-size: .72rem; text-transform: uppercase; letter-spacing: .14em; color: var(--color-accent); font-weight: 600; }

    .form-control, .form-select {
        background-color: rgba(15, 23, 42, .7);
        border-color: var(--color-border);
        color: var(--color-ink);
    }
    .form-control:focus, .form-select:focus {
        background-color: rgba(15, 23, 42, .9);
        border-color: var(--color-accent);
        box-shadow: 0 0 0 .2rem rgba(34, 211, 238, .18);
        color: #fff;
    }
    .form-control::placeholder { color: var(--color-ink-muted); }
    .input-group-text { background: rgba(15, 23, 42, .7); border-color: var(--color-border); color: var(--color-ink-soft); }

    .table { --bs-table-bg: transparent; --bs-table-border-color: var(--color-border); color: var(--color-ink); }
    .table thead th { font-size: .72rem; text-transform: uppercase; letter-spacing: .08em; color: var(--color-ink-soft); font-weight: 600; }

    .toast { background: var(--color-surface-solida); color: var(--color-ink); border: 1px solid var(--color-border) !important; box-shadow: 0 10px 30px rgba(0,0,0,.4); }
    .toast-container { z-index: 1080; }
    .dropdown-menu { background: var(--color-surface-solida); border-color: var(--color-border); }
    .modal-content { background: var(--color-surface-solida); border: 1px solid var(--color-border); }

    #global-search { max-width: 340px; }

    pre.texto-ocr { background: rgba(2, 6, 23, .7); color: #a5f3fc; border: 1px solid var(--color-border); max-height: 260px; overflow: auto; }

    /* Tarjetas de estadística del panel. */
    .stat-valor { font-size: 2rem; font-weight: 700; line-height: 1; color: #fff; }
    .stat-icono {
        width: 42px; height: 42px; border-radius: 12px;
        display: inline-flex; align-items: center; justify-content: center;
        background: rgba(34, 211, 238, .1); color: var(--color-accent); font-size: 1.15rem;
    }

    /* Gráficas: una sola serie, un solo tono; barras delgadas con extremo redondeado. */
    .grafica-barras { display: flex; flex-direction: column; gap: .7rem; }
    .grafica-barras .fila { display: grid; grid-template-columns: 150px 1fr 36px; align-items: center; gap: .75rem; font-size: .88rem; }
    .grafica-barras .pista { height: 10px; border-radius: 4px; background: rgba(148, 163, 184, .08); position: relative; }
    .grafica-barras .barra { position: absolute; left: 0; top: 0; bottom: 0; border-radius: 0 4px 4px 0; background: var(--color-accent); min-width: 2px; transition: filter .12s; }
    .grafica-barras .fila:hover .barra { filter: brightness(1.25); box-shadow: 0 0 12px rgba(34, 211, 238, .5); }
    .grafica-barras .valor { text-align: right; color: var(--color-ink); font-variant-numeric: tabular-nums; }

    .grafica-columnas { display: flex; align-items: flex-end; gap: 2px; height: 140px; border-bottom: 1px solid var(--color-border); }
    .grafica-columnas .col-dia { flex: 1; height: 100%; display: flex; align-items: flex-end; position: relative; cursor: default; }
    .grafica-columnas .col-dia > span { display: block; width: 100%; border-radius: 4px 4px 0 0; background: var(--color-accent); min-height: 2px; opacity: .85; }
    .grafica-columnas .col-dia:hover > span { opacity: 1; box-shadow: 0 0 12px rgba(34, 211, 238, .5); }
    .tooltip-grafica {
        position: absolute; bottom: calc(100% + 6px); left: 50%; transform: translateX(-50%);
        background: var(--color-surface-solida); border: 1px solid var(--color-border); border-radius: 8px;
        padding: .3rem .55rem; font-size: .75rem; white-space: nowrap; color: var(--color-ink);
        pointer-events: none; opacity: 0; transition: opacity .1s; z-index: 5;
    }
    .col-dia:hover .tooltip-grafica { opacity: 1; }

    @media (max-width: 576px) {
        .grafica-barras .fila { grid-template-columns: 110px 1fr 30px; }
    }
</style>
