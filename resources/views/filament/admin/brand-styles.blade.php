{{-- Filament admin chrome → align with Tempahlah cream/orange palette --}}
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
<link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
<style>
    :root {
        --brand-bg: #ffffff;
        --brand-bg-elev: #fafaf7;
        --brand-bg-sunk: #f5f4f0;
        --brand-bg-tint: #eaeef3;
        --brand-ink: #0f1928;
        --brand-ink-2: #3b4757;
        --brand-ink-3: #6b7787;
        --brand-line: #e6ebf1;
        --brand-line-2: #d3dae3;
        --brand-primary: #2596c6;
        --brand-primary-hover: #1f7eaf;
        --brand-primary-deep: #1a6a96;
        --brand-primary-tint: #e2f1f8;
    }

    /* Apply brand font everywhere in the panel (mode-agnostic) */
    .fi-body, body.fi-panel-admin {
        font-family: "Geist", ui-sans-serif, system-ui, -apple-system, sans-serif !important;
    }

    /* All cream-mode overrides below are scoped to html:not(.dark) so Filament's
       built-in dark theme renders untouched when the user toggles dark mode. */

    /* === Auth (login) page only — full-bleed cream background with soft glow === */
    html:not(.dark) body.fi-simple-layout {
        background:
            radial-gradient(1200px 600px at 85% -10%, rgba(37,150,198,0.18) 0%, transparent 60%),
            radial-gradient(900px 500px at -10% 110%, rgba(44,184,196,0.14) 0%, transparent 55%),
            var(--brand-bg) !important;
        color: var(--brand-ink);
    }

    /* Brand wordmark/logo block above the auth card */
    body.fi-simple-layout .fi-logo,
    body.fi-simple-layout .fi-simple-page-header {
        margin-bottom: 8px !important;
    }

    /* Auth page — center card */
    html:not(.dark) body.fi-simple-layout .fi-simple-main {
        max-width: 460px !important;
        background: var(--brand-bg-elev) !important;
        border: 1px solid var(--brand-line) !important;
        border-radius: 20px !important;
        box-shadow:
            0 24px 64px -24px rgba(80,50,20,0.20),
            0 2px 6px rgba(80,50,20,0.06) !important;
        padding: 32px !important;
    }

    /* Heading inside auth card */
    html:not(.dark) body.fi-simple-layout .fi-simple-main h1,
    html:not(.dark) body.fi-simple-layout .fi-simple-main .fi-header-heading {
        color: var(--brand-ink) !important;
        font-family: "Geist", ui-sans-serif, system-ui, sans-serif !important;
        font-weight: 700 !important;
        letter-spacing: -0.02em !important;
    }

    /* Form inputs — cream-friendly */
    html:not(.dark) body.fi-simple-layout .fi-input-wrp {
        background: var(--brand-bg-elev) !important;
        border-color: var(--brand-line-2) !important;
        border-radius: 12px !important;
    }
    html:not(.dark) body.fi-simple-layout .fi-input-wrp:focus-within {
        border-color: var(--brand-primary) !important;
        box-shadow: 0 0 0 3px rgba(37,150,198,0.18) !important;
    }
    html:not(.dark) body.fi-simple-layout .fi-input,
    html:not(.dark) body.fi-simple-layout input[type="email"],
    html:not(.dark) body.fi-simple-layout input[type="password"],
    html:not(.dark) body.fi-simple-layout input[type="text"] {
        color: var(--brand-ink) !important;
        background: transparent !important;
    }
    html:not(.dark) body.fi-simple-layout label {
        color: var(--brand-ink-2) !important;
        font-weight: 600 !important;
    }

    /* Primary submit button → orange gradient (kept in both modes — it's brand) */
    body.fi-simple-layout .fi-btn-color-primary,
    body.fi-simple-layout button[type="submit"].fi-btn {
        background: linear-gradient(180deg, var(--brand-primary) 0%, var(--brand-primary-hover) 100%) !important;
        border: 0 !important;
        color: #ffffff !important;
        border-radius: 12px !important;
        height: 48px !important;
        font-weight: 700 !important;
        letter-spacing: 0.005em !important;
        box-shadow: 0 6px 18px -4px rgba(37,150,198,0.5) !important;
    }
    body.fi-simple-layout .fi-btn-color-primary:hover,
    body.fi-simple-layout button[type="submit"].fi-btn:hover {
        background: var(--brand-primary-deep) !important;
    }

    /* Remember-me checkbox accent */
    body.fi-simple-layout input[type="checkbox"] {
        accent-color: var(--brand-primary) !important;
    }

    /* Link colors (light mode only) */
    html:not(.dark) body.fi-simple-layout a {
        color: var(--brand-primary-deep) !important;
        font-weight: 500 !important;
        text-decoration: none !important;
    }
    html:not(.dark) body.fi-simple-layout a:hover {
        color: var(--brand-primary) !important;
        text-decoration: underline !important;
    }

    /* Footer / "powered by" muted */
    html:not(.dark) body.fi-simple-layout .fi-simple-footer {
        color: var(--brand-ink-3) !important;
    }

    /* === Branded login intro panel injected via render hook === */
    .hms-login-intro {
        text-align: center;
        margin: -8px 0 18px;
    }
    .hms-login-intro-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 5px 12px;
        background: var(--brand-primary-tint);
        color: var(--brand-primary-deep);
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        margin-bottom: 14px;
    }
    html.dark .hms-login-intro-pill {
        background: rgba(37,150,198,0.18);
        color: #93d5ee;
    }
    .hms-login-intro-title {
        font-family: "Geist", ui-sans-serif, system-ui, sans-serif;
        font-size: 28px;
        font-weight: 700;
        letter-spacing: -0.02em;
        color: var(--brand-ink);
        margin: 0 0 6px;
        line-height: 1.1;
    }
    html.dark .hms-login-intro-title {
        color: #f4ecdf;
    }
    .hms-login-intro-sub {
        font-size: 13.5px;
        color: var(--brand-ink-3);
        margin: 0 0 8px;
        line-height: 1.5;
    }
    html.dark .hms-login-intro-sub {
        color: #a89788;
    }
    .hms-login-intro-divider {
        height: 1px;
        background: linear-gradient(90deg, transparent, var(--brand-line) 30%, var(--brand-line) 70%, transparent);
        margin: 18px 0 4px;
    }
    html.dark .hms-login-intro-divider {
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.10) 30%, rgba(255,255,255,0.10) 70%, transparent);
    }

    /* Hide the default Filament heading on auth page (we replace it via render hook) */
    body.fi-simple-layout .fi-simple-main > .fi-section-content > .fi-header,
    body.fi-simple-layout .fi-simple-main .fi-simple-main-heading {
        display: none !important;
    }

    /* === BM/EN locale toggle in the Filament topbar === */
    .hms-locale-toggle {
        display: inline-flex;
        align-items: center;
        gap: 2px;
        padding: 2px;
        border: 1px solid var(--brand-line-2);
        border-radius: 8px;
        background: var(--brand-bg-sunk);
        margin-right: 6px;
    }
    html.dark .hms-locale-toggle {
        border-color: rgba(255,255,255,0.10);
        background: rgba(255,255,255,0.04);
    }
    .hms-locale-toggle-pill {
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 600;
        text-decoration: none;
        color: var(--brand-ink-3);
        letter-spacing: 0.02em;
        line-height: 1;
        transition: background 120ms, color 120ms;
    }
    html.dark .hms-locale-toggle-pill {
        color: #8a98a8;
    }
    .hms-locale-toggle-pill:hover {
        color: var(--brand-ink-2);
        text-decoration: none !important;
    }
    html.dark .hms-locale-toggle-pill:hover {
        color: #e6edf4;
    }
    .hms-locale-toggle-pill.is-active {
        background: var(--brand-bg-elev);
        color: var(--brand-primary-deep);
        box-shadow: 0 1px 2px rgba(15,25,40,0.06), 0 1px 0 rgba(15,25,40,0.04);
    }
    html.dark .hms-locale-toggle-pill.is-active {
        background: rgba(37,150,198,0.16);
        color: #93d5ee;
        box-shadow: none;
    }

    /* === Authenticated panel (sidebar + topbar) — light-mode only === */
    html:not(.dark) body.fi-panel-admin:not(.fi-simple-layout) {
        background: var(--brand-bg) !important;
    }
    html:not(.dark) body.fi-panel-admin .fi-sidebar {
        background: var(--brand-bg-elev) !important;
        border-right: 1px solid var(--brand-line) !important;
    }
    html:not(.dark) body.fi-panel-admin .fi-topbar {
        background: rgba(255,255,255,0.92) !important;
        backdrop-filter: blur(20px) saturate(180%) !important;
        -webkit-backdrop-filter: blur(20px) saturate(180%) !important;
        border-bottom: 1px solid var(--brand-line) !important;
    }
</style>
