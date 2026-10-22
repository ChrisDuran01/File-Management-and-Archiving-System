{{-- Shared by the Activity Proposals pages (list, form, one proposal).
     Built on the tokens in Admin/partials/theme, so it follows dark mode. --}}
<style>
    * { box-sizing:border-box; }
    body { background:var(--surface); font-family:'Inter',sans-serif; color:var(--text-1); }

    .item-meta { font-size:.76rem; color:var(--text-3); }
    .num { text-align:right; font-variant-numeric:tabular-nums; white-space:nowrap; }
    .table-scroll { overflow-x:auto; }
    .btn-sm { padding:6px 12px; font-size:.8rem; }
    .prop-actions { display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
    .prop-actions form { display:inline; margin:0; }

    .prop-input, .prop-filters input, .prop-filters select {
        border:1.5px solid var(--border); border-radius:var(--radius-sm); padding:9px 11px;
        font-size:.87rem; font-family:inherit; background:var(--card); color:var(--text-1);
    }
    .prop-input:focus, .prop-filters input:focus, .prop-filters select:focus {
        border-color:var(--primary); box-shadow:0 0 0 3px var(--primary-dim); outline:none;
    }

    /* List page */
    .prop-filters { display:flex; flex-wrap:wrap; gap:10px; padding:14px 16px; }
    .prop-filters input[type=search] { flex:1 1 260px; min-width:0; }
    .prop-name { font-weight:600; text-decoration:none; }
    .prop-table td { vertical-align:top; }
    .prop-table .actions { text-align:right; white-space:nowrap; }

    /* Form */
    .prop-form { max-width:1000px; }
    .prop-form .card-panel h2 { font-size:1rem; font-weight:700; margin:0 0 12px; color:var(--text-1); }
    .prop-form .card-panel h2 label { font-size:inherit; font-weight:inherit; color:inherit; margin:0; }
    .prop-fields { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:14px 16px; }
    .prop-fields .full { grid-column:1 / -1; }
    .prop-fields label, .prop-fields .field-label { display:block; font-size:.74rem; font-weight:600; color:var(--text-2); margin-bottom:5px; }
    .prop-fields .hint, .budget-readout .hint { font-weight:400; color:var(--text-3); font-size:.78rem; }
    .budget-readout { margin:0; padding:9px 0; font-weight:600; font-variant-numeric:tabular-nums; }
    textarea.cell-text { resize:vertical; min-height:38px; line-height:1.4; overflow:hidden; }
    .sig-group h3 { font-size:.85rem; font-weight:700; color:var(--text-1); margin:16px 0 6px; }
    .sig-group:first-of-type h3 { margin-top:4px; }
    .prop-input { width:100%; }
    textarea.prop-input { resize:vertical; min-height:110px; line-height:1.5; }
    .prop-list { margin:0 0 12px; padding-left:22px; display:flex; flex-direction:column; gap:8px; }
    .prop-list li::marker { color:var(--text-3); }
    .prop-row { display:flex; gap:8px; align-items:center; }
    .row-remove {
        flex-shrink:0; width:36px; height:36px; border:1.5px solid var(--border); border-radius:var(--radius-sm);
        background:var(--card); color:var(--text-3); cursor:pointer;
    }
    .row-remove:hover { color:var(--danger); border-color:var(--danger); }
    .grid-table { width:100%; border-collapse:collapse; margin-bottom:12px; }
    .grid-table th {
        text-align:left; font-size:.7rem; text-transform:uppercase; letter-spacing:.05em;
        color:var(--text-3); font-weight:700; padding:6px; white-space:nowrap;
    }
    .grid-table td { padding:4px 6px; vertical-align:middle; }
    .grid-table .prop-input { padding:7px 9px; }
    .grid-table tfoot td { font-weight:700; border-top:1.5px solid var(--border); padding:10px 6px; }
    .budget-note { font-size:.8rem; color:var(--text-3); margin:-4px 0 12px; }
    .is-invalid { border-color:var(--danger) !important; }
    .field-error { color:var(--danger); font-size:.76rem; margin-top:4px; }
    .form-actions { display:flex; flex-wrap:wrap; gap:10px; justify-content:flex-end; margin-bottom:24px; }

    /* One proposal */
    .prop-doc { max-width:1000px; }
    .prop-doc section { margin-top:24px; }
    .prop-doc h2 { font-size:.95rem; font-weight:700; margin:0 0 8px; color:var(--text-1); }
    .prop-facts { display:grid; grid-template-columns:160px minmax(0, 1fr); gap:6px 14px; margin:0; }
    .prop-facts dt { color:var(--text-3); font-size:.8rem; font-weight:600; }
    .prop-facts dd { margin:0; }
    .prop-text { white-space:pre-line; margin:0; line-height:1.6; }
    .prop-empty { color:var(--text-3); margin:0; }
    .prop-doc ol { margin:0; padding-left:22px; display:flex; flex-direction:column; gap:4px; }
    .read-table { width:100%; border-collapse:collapse; font-size:.86rem; }
    .read-table th {
        text-align:left; font-size:.7rem; text-transform:uppercase; letter-spacing:.05em;
        color:var(--text-3); font-weight:700; padding:8px 10px; border-bottom:1.5px solid var(--border); white-space:nowrap;
    }
    .read-table td { padding:8px 10px; border-bottom:1px solid var(--border); vertical-align:top; }
    .read-table tfoot td { font-weight:700; border-bottom:none; border-top:1.5px solid var(--border); }
    .cat-totals { margin-top:14px; max-width:420px; }

    @media (max-width: 640px) {
        .prop-fields { grid-template-columns:1fr; }
        .prop-facts { grid-template-columns:1fr; gap:2px; }
        .prop-facts dd { margin-bottom:8px; }
    }
</style>
