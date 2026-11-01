{{-- Styles shared by the Archives page (school years) and one archived
     school year's page (its folders): cards, menus, fixity rows, buttons. --}}

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

<style>
    .archive-count {
        font-size: 12px;
        background: #f4f4f4;
        border: 1px solid #e8e8e8;
        border-radius: 20px;
        padding: 3px 10px;
        color: #666;
        font-weight: 400;
    }
    .back-btn {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 8px 16px;
        border-radius: 10px;
        border: 1px solid #e0e0e0;
        background: #fff;
        color: #555;
        font-size: 13px;
        font-weight: 500;
        text-decoration: none;
        transition: background 0.15s, border-color 0.15s;
    }
    .back-btn:hover {
        background: #f5f5f5;
        border-color: #ccc;
        color: #333;
    }

    /* ── Archive Card Grid ── */
    .archive-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 14px;
    }

    .archive-card {
        background: #fff;
        border: 1px solid #ececec;
        border-radius: 14px;
        padding: 1rem 1.25rem;
        position: relative;
        overflow: visible;
        transition: border-color 0.15s, box-shadow 0.2s;
    }
    .archive-card:hover {
        border-color: #d5d5d5;
        box-shadow: 0 6px 20px rgba(0,0,0,0.07);
    }

    /* ── Card Top Row ── */
    .card-top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        margin-bottom: 12px;
    }
    .folder-icon-wrap {
        width: 42px;
        height: 42px;
        border-radius: 10px;
        background: #EEEDFE;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        color: #058028;
    }

    /* ── 3-dot Menu ── */
    .card-menu-wrap {
        position: relative;
    }
    .card-menu-btn {
        width: 30px;
        height: 30px;
        border-radius: 7px;
        border: 1px solid #e5e5e5;
        background: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        color: var(--text-2);
        font-size: 15px;
        transition: background 0.12s;
        opacity: 0;
        transition: opacity 0.15s, background 0.12s;
    }
    .archive-card:hover .card-menu-btn { opacity: 1; }
    /* Also when the keyboard is on the card or its menu, and always on
       touch screens, which have no hover. */
    .archive-card:focus-within .card-menu-btn { opacity: 1; }
    @media (hover: none) { .card-menu-btn { opacity: 1; } }
    .card-menu-btn:hover { background: #f0f0f0; color: #444; }

    .card-dropdown {
        position: absolute;
        top: 34px;
        right: 0;
        background: #fff;
        border: 1px solid #e5e5e5;
        border-radius: 10px;
        padding: 4px;
        min-width: 150px;
        z-index: 9999;
        display: none;
        box-shadow: 0 4px 16px rgba(0,0,0,0.1);
    }
    .card-dropdown.open { display: block; }
    .card-dropdown-item {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 10px;
        font-size: 13px;
        border-radius: 7px;
        cursor: pointer;
        color: #333;
        text-decoration: none;
        white-space: nowrap;
        background: none;
        border: none;
        width: 100%;
        text-align: left;
    }
    .card-dropdown-item:hover { background: #f5f5f5; }
    .card-dropdown-item.warn { color: #854F0B; }
    .card-dropdown-item.danger { color: #A32D2D; }
    .card-dropdown-divider {
        height: 1px;
        background: #f0f0f0;
        margin: 4px 0;
    }

    /* ── Card Body ── */
    .card-folder-name {
        font-size: 14px;
        font-weight: 600;
        color: #1a1a1a;
        margin-bottom: 3px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .card-zip-name {
        font-size: 12px;
        color: var(--text-3);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        margin-bottom: 12px;
    }
    .card-zip-name i { margin-right: 4px; }

    /* ── Card Footer ── */
    .card-footer-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-top: 1px solid #f0f0f0;
        padding-top: 10px;
        margin-top: 4px;
    }
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 500;
    }
    .status-badge .dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        display: inline-block;
    }
    .badge-archived { background: #eaf3de; color: #27500a; }
    .badge-archived .dot { background: #3b6d11; }
    .badge-pending { background: #faeeda; color: #633806; }
    .badge-pending .dot { background: #ba7517; }

    .card-date {
        font-size: 11px;
        color: var(--text-3);
        display: flex;
        align-items: center;
        gap: 4px;
    }

    /* ── Fixity indicator ──
       Set by the weekly archives:verify-fixity check (see routes/console.php)
       against the SHA-256 recorded when the archive was created. */
    .fixity-row {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 11px;
        font-weight: 500;
        padding-top: 8px;
        margin-top: 8px;
        border-top: 1px solid #f5f5f5;
    }
    .fixity-row svg { flex-shrink: 0; }
    .fixity-row .fx-label {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .fixity-ok { color: #27500a; }
    .fixity-failed { color: #791f1f; }
    .fixity-none { color: var(--text-3); }

    /* ── Empty State ── */
    .empty-state {
        grid-column: 1 / -1;
        text-align: center;
        padding: 4rem 1rem;
        color: var(--text-3);
    }
    .empty-state i { font-size: 3.5rem; display: block; margin-bottom: 12px; opacity: 0.3; }
    .empty-state p { font-size: 14px; }

    /* ── Modals ── */
    .modal-content {
        border-radius: 16px;
        border: none;
        box-shadow: 0 12px 40px rgba(0,0,0,0.12);
    }
    .modal-header {
        border-bottom: 1px solid #f0f0f0;
        padding: 1.1rem 1.5rem;
    }
    .modal-title {
        font-weight: 500;
        font-size: 15px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .modal-body { padding: 1.25rem 1.5rem; font-size: 13px; color: #666; line-height: 1.7; }
    .modal-body p { margin-bottom: 6px; }
    .modal-body ul { margin: 6px 0 0 18px; }
    .modal-body strong { color: #1a1a1a; font-weight: 500; }
    .modal-footer { border-top: 1px solid #f0f0f0; padding: 1rem 1.5rem; }

    .btn-action {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 16px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 500;
        cursor: pointer;
        border: 1px solid;
        transition: opacity 0.15s;
    }
    .btn-action:hover { opacity: 0.85; }
    .btn-action.secondary {
        background: #f5f5f5;
        border-color: #e0e0e0;
        color: #555;
    }
    .btn-action.warn {
        background: #faeeda;
        border-color: #fac775;
        color: #633806;
    }
    .btn-action.danger {
        background: #fcebeb;
        border-color: #f7c1c1;
        color: #791f1f;
    }

    @media (max-width: 576px) {
        .archive-grid { grid-template-columns: 1fr; }
    }

    /* ── School years: the retention note, sections, waiting list, year cards ── */
    .archive-rule { font-size: 14px; line-height: 1.55; color: var(--text-2); margin: -4px 0 22px; max-width: 760px; }
    .archive-section { margin-bottom: 28px; }
    .section-title { font-size: 15px; font-weight: 600; color: var(--text-1); margin: 0 0 10px; display: flex; align-items: center; gap: 8px; }
    .waiting-list { border: 1px solid var(--border); border-radius: 14px; background: var(--card); }
    .waiting-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 16px; flex-wrap: wrap; }
    .waiting-row + .waiting-row { border-top: 1px solid var(--border); }
    .waiting-row form { margin: 0; }
    .waiting-sy { font-size: 14px; font-weight: 600; color: var(--text-1); }
    .waiting-meta { font-size: 13px; color: var(--text-2); }
    a.sy-card { display: block; color: inherit; text-decoration: none; }
    a.sy-card:focus-visible { outline: 2px solid var(--primary); outline-offset: 2px; }

    /* ===== DARK MODE (page-unique classes; shared ones live in partials/theme) ===== */
    [data-theme="dark"] .archive-count { background: var(--nav-hover-bg); border-color: var(--border); color: var(--text-2); }
    [data-theme="dark"] .back-btn:hover { background: var(--nav-hover-bg); border-color: var(--text-3); color: var(--text-1); }
    [data-theme="dark"] .archive-card { background: var(--card); border-color: var(--border); }
    [data-theme="dark"] .archive-card:hover { border-color: var(--text-3); }
    [data-theme="dark"] .folder-icon-wrap { background: var(--primary-dim); color: var(--primary); }
    [data-theme="dark"] .card-dropdown-divider { background: var(--border); }
    [data-theme="dark"] .card-folder-name { color: var(--text-1); }
    [data-theme="dark"] .card-zip-name,
    [data-theme="dark"] .card-date { color: var(--text-3); }
    [data-theme="dark"] .card-footer-row { border-top-color: var(--border); }
    [data-theme="dark"] .fixity-row { border-top-color: var(--border); }
    [data-theme="dark"] .fixity-ok { color: var(--success-text); }
    [data-theme="dark"] .fixity-failed { color: #fca5a5; }
    [data-theme="dark"] .fixity-none { color: var(--text-3); }
    [data-theme="dark"] .empty-state { color: var(--text-3); }
    [data-theme="dark"] .badge-archived { background: var(--success-dim); color: var(--success-text); }
    [data-theme="dark"] .badge-pending  { background: var(--warning-dim); color: #e3b877; }
    [data-theme="dark"] .btn-action.secondary { background: var(--nav-hover-bg); border-color: var(--border); color: var(--text-1); }
    [data-theme="dark"] .btn-action.warn   { background: var(--warning-dim); border-color: #7a5a1e; color: #e3b877; }
    [data-theme="dark"] .btn-action.danger { background: var(--danger-dim); border-color: #7f2a2a; color: #fca5a5; }
</style>
