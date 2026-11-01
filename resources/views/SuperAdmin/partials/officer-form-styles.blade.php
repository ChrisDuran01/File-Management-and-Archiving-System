{{-- Styles shared by Add Officer and Edit Officer: the officer form card,
     its fields, footer buttons and validation messages. --}}
<style>
    * { box-sizing: border-box; }
    body {
        background: var(--surface);
        font-family: 'Inter', sans-serif;
        color: var(--text-1);
    }

    /* ── Back link ── */
    .back-link {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 15px;
        color: var(--text-2);
        text-decoration: none;
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        padding: 6px 12px;
        transition: all .15s;
        background: var(--card);
    }
    .back-link:hover { border-color: var(--primary); color: var(--primary); }
    .back-link i { font-size: 13px; }

    /* ── Card ── */
    .al-card {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        overflow: hidden;
        box-shadow: var(--shadow-sm);
        max-width: 640px;
    }

    .al-card-header {
        padding: 0.875rem 1.25rem;
        border-bottom: 1px solid var(--border);
        background: #f9fafb;
    }
    .al-card-title {
        font-size: 15px;
        font-weight: 500;
        color: var(--text-1);
        display: flex;
        align-items: center;
        gap: 7px;
        margin: 0;
    }
    .al-card-title-dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: var(--primary);
        flex-shrink: 0;
    }

    /* ── Form body ── */
    .form-body {
        padding: 1.5rem 1.25rem;
        display: flex;
        flex-direction: column;
        gap: 1.25rem;
    }

    /* ── Field ── */
    .field-group {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }
    .field-label {
        font-size: 14px;
        font-weight: 500;
        color: var(--text-1);
        display: flex;
        align-items: center;
        gap: 5px;
    }
    .field-label i {
        font-size: 10px;
        color: var(--text-3);
    }
    .field-required {
        color: var(--red);
        font-size: 13px;
        margin-left: 1px;
    }

    .field-input,
    .field-select {
        width: 100%;
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        padding: 8px 12px;
        font-size: 15px;
        font-family: 'Inter', sans-serif;
        color: var(--text-1);
        background: var(--card);
        outline: none;
        transition: border-color .15s, box-shadow .15s;
        appearance: none;
        -webkit-appearance: none;
    }
    .field-input::placeholder { color: var(--text-3); }
    .field-input:focus,
    .field-select:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(5,128,40,.15);
    }

    /* mono inputs */
    .field-input.mono {
        font-family: 'DM Mono', monospace;
        font-size: 14px;
    }

    /* select wrapper for custom arrow */
    .select-wrap { position: relative; }
    .select-wrap::after {
        content: '';
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        width: 0; height: 0;
        border-left: 4px solid transparent;
        border-right: 4px solid transparent;
        border-top: 5px solid var(--text-3);
        pointer-events: none;
    }
    .select-wrap .field-select { padding-right: 32px; cursor: pointer; }

    /* password wrapper */
    .input-wrap { position: relative; }
    .input-wrap .field-input { padding-right: 38px; }
    .toggle-pw {
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        cursor: pointer;
        color: var(--text-3);
        font-size: 14px;
        padding: 2px 4px;
        transition: color .15s;
        display: flex;
        align-items: center;
    }
    .toggle-pw:hover { color: var(--primary); }

    /* field hint */
    .field-hint {
        font-size: 12px;
        color: var(--text-3);
        display: flex;
        align-items: center;
        gap: 4px;
        font-family: 'DM Mono', monospace;
    }
    .field-hint i { font-size: 8px; }

    /* ── Divider ── */
    .form-divider {
        height: 1px;
        background: var(--border);
        margin: 0 -1.25rem;
    }
    .divider-label {
        font-size: 12px;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: var(--text-3);
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .divider-label::before,
    .divider-label::after {
        content: '';
        flex: 1;
        height: 1px;
        background: var(--border);
    }

    /* ── Two-col row ── */
    .field-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }
    @media (max-width: 520px) {
        .field-row { grid-template-columns: 1fr; }
    }

    /* ── Footer ── */
    .form-footer {
        padding: 1rem 1.25rem;
        border-top: 1px solid var(--border);
        background: #f9fafb;
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 8px;
    }
    .btn-cancel {
        background: none;
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        color: var(--text-2);
        padding: 7px 16px;
        font-size: 15px;
        font-family: 'Inter', sans-serif;
        cursor: pointer;
        transition: all .15s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .btn-cancel:hover { border-color: var(--primary); color: var(--primary); }
    .btn-save {
        background: var(--primary-fill);
        border: none;
        border-radius: var(--radius-sm);
        color: #fff;
        padding: 7px 20px;
        font-size: 15px;
        font-weight: 500;
        font-family: 'Inter', sans-serif;
        cursor: pointer;
        transition: background .15s;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .btn-save:hover { background: #046322; }

    /* ── Validation errors ── */
    .field-error {
        font-size: 13px;
        color: var(--red);
        display: flex;
        align-items: center;
        gap: 4px;
    }
    .field-error i { font-size: 12px; }
    .field-input.is-invalid,
    .field-select.is-invalid {
        border-color: var(--red);
        box-shadow: 0 0 0 3px rgba(163,45,45,.08);
    }

    /* ── Edit Officer: a locked field, a second card, a short note ── */
    .field-input[readonly] {
        background: var(--surface);
        color: var(--text-2);
        cursor: not-allowed;
    }
    .al-card + .al-card { margin-top: 1.25rem; }
    .form-note {
        margin: 0;
        font-size: 14px;
        line-height: 1.5;
        color: var(--text-2);
    }

    /* ===== DARK MODE ===== */
    [data-theme="dark"] .al-card-header,
    [data-theme="dark"] .form-footer { background: var(--surface); }
</style>

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
