{{--
    Shared design system for every admin/superadmin page.

    Include as the FIRST thing inside @section('content'); page <style>
    blocks come after and may add page-specific rules, but must reference
    these tokens instead of hardcoding colors/spacing. One accent only:
    the SG green (#058028). Red is reserved for danger, everything else is
    neutral gray. Spacing sits on a 4px scale: 24px page gutter, 20px card
    padding, 16px card gap, 12x14px table cells.
--}}
<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap');

    :root {
        /* surfaces */
        --surface: #f7f8fc;
        --card: #ffffff;
        --border: #e8eaf0;

        /* text - every level is at least 4.5:1 (WCAG AA) on --card,
           --surface and --primary-dim; --text-3 is the lightest gray
           allowed for anything a person has to read */
        --text-1: #111827;
        --text-2: #4b5563;
        --text-3: #646b78;

        /* the one accent */
        --primary: #058028;
        --primary-dark: #046322;
        --primary-dim: #e9f5ee;
        --brand: #058028;

        /* Backgrounds of filled controls that carry white text (buttons,
           active pills, the user's chat bubble). Kept apart from --primary /
           --danger because in dark mode those are bright for use as TEXT
           on near-black, and white text on them fails contrast. */
        --primary-fill: #058028;
        --primary-fill-hover: #046322;
        --danger-fill: #c81e1e;

        /* semantic (danger is the only other saturated color) */
        --danger: #c81e1e;   --danger-dim: #fef2f2;
        --red: #c81e1e;      --red-dim: #fef2f2;
        --success: #058028;  --success-dim: #e9f5ee;  --success-text: #046322;
        --warning: #b45309;  --warning-dim: #fffbeb;
        --amber: #b45309;    --amber-dim: #fffbeb;
        --green: #058028;    --green-dim: #e9f5ee;
        --blue: #058028;     --blue-dim: #e9f5ee;

        /* shape + elevation */
        --radius: 12px;
        --radius-sm: 8px;
        --shadow-sm: 0 1px 3px rgba(0,0,0,.06), 0 1px 2px rgba(0,0,0,.04);
        --shadow-md: 0 4px 12px rgba(0,0,0,.07);
        --shadow-lg: 0 8px 24px rgba(15,23,42,.10);
    }

    * { box-sizing: border-box; }

    body {
        background: var(--surface);
        font-family: 'Inter', sans-serif;
        color: var(--text-1);
    }

    /* Reserve room at the bottom of every page so content never scrolls
       underneath the floating help-chat bubble (bottom-right, every page)
       or - on pages that also have the green floating-action button
       (Folders, folder view) - the FAB stacked above it. Without this the
       last row of a long file/folder list can render directly under
       those fixed-position buttons. */
    body { padding-bottom: 5.5rem; }
    body:has(.fab-container) { padding-bottom: 10rem; }

    /* ===== Page header =====
       One shared contract for every Admin/SuperAdmin page's title block:
       <div class="page-header">
           <div><h1>Title</h1><p class="sub">Optional one-line description</p></div>
           {{-- optional action buttons/links --}}
       </div>
       Previously every page redeclared this with its own values (some as
       an h2, some h4, some a bare .page-title span, spacing anywhere from
       -15px to 24px) - centralizing it here is what makes every page's
       header look and space the same without each one hand-maintaining it.
       The title is the page's one <h1> (its main heading for screen
       readers); the page's 'title' section uses the same words, so its
       browser tab matches. */
    .page-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 20px;
    }
    .page-header h1 {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 1.35rem;
        font-weight: 600;
        letter-spacing: -.3px;
        margin: 0 0 4px;
        color: var(--text-1);
    }
    .page-header .sub { font-size: .85rem; color: var(--text-2); margin: 0; }

    /* Optional icon chip inside a page-header h1, e.g.
       <h1><div class="page-title-icon"><svg>...</svg></div> Dashboard</h1> */
    .page-title-icon {
        width: 32px;
        height: 32px;
        border-radius: var(--radius-sm);
        background: var(--primary-dim);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .page-title-icon svg { width: 16px; height: 16px; }
    .page-header .as-of { font-size: 13px; color: var(--text-2); font-family: 'DM Mono', monospace; }

    /* ===== Back link =====
       First thing in a page's content, above the .page-header - see
       Admin/partials/back-link.blade.php. */
    .page-back {
        display: inline-flex;
        align-items: center;
        align-self: flex-start;
        gap: 6px;
        font-size: 13px;
        color: var(--text-2);
        text-decoration: none;
        margin-bottom: 1rem;
        transition: color .15s;
    }
    .page-back:hover { color: var(--primary); }

    /* ===== Cards ===== */
    .card-panel {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        box-shadow: var(--shadow-sm);
        padding: 20px;
        margin-bottom: 16px;
    }

    /* ===== Buttons ===== */
    .btn-flat {
        background: var(--primary-fill); color: #fff; border: none;
        border-radius: var(--radius-sm); padding: 9px 18px;
        font-size: .85rem; font-weight: 600; cursor: pointer;
        text-decoration: none; display: inline-block;
        transition: background .15s;
    }
    .btn-flat:hover { background: var(--primary-fill-hover); color: #fff; }
    .btn-flat.ghost { background: var(--card); color: var(--text-2); border: 1.5px solid var(--border); }
    .btn-flat.ghost:hover { background: var(--surface); color: var(--text-1); }
    .btn-flat.danger { background: var(--danger-dim); color: var(--danger); border: 1.5px solid #fecaca; }
    .btn-flat.danger-solid { background: var(--danger-fill); color: #fff; }

    /* ===== Generic data table ===== */
    .data-table { width: 100%; border-collapse: collapse; font-size: .86rem; }
    .data-table th {
        text-align: left; font-size: .7rem; text-transform: uppercase;
        letter-spacing: .05em; color: var(--text-3); font-weight: 700;
        padding: 12px 14px; border-bottom: 1.5px solid var(--border);
    }
    .data-table td { padding: 12px 14px; border-bottom: 1px solid var(--border); }
    .data-table tr:last-child td { border-bottom: none; }
    .data-table tbody tr:hover { background: var(--primary-dim); }

    /* ===== Chips ===== */
    .chip {
        font-size: .68rem; font-weight: 700; padding: 2px 9px;
        border-radius: 20px; background: var(--primary-dim); color: var(--primary-dark);
        display: inline-block;
    }
    .chip.neutral { background: #f1f5f9; color: #526071; }
    .chip.danger { background: var(--danger-dim); color: var(--danger); }
    .chip.warning { background: var(--warning-dim); color: var(--warning); }
    a.chip { text-decoration: none; }
    a.chip:hover { text-decoration: underline; }

    /* Upload toast: a line under a file that duplicates one already saved */
    .upload-toast-row .row-note { font-size: 11.5px; line-height: 1.4; color: var(--warning); margin-top: 6px; }

    /* ===== Forms ===== */
    .form-control:focus, .form-select:focus,
    input.themed:focus, select.themed:focus, textarea.themed:focus {
        border-color: var(--primary) !important;
        box-shadow: 0 0 0 3px var(--primary-dim) !important;
        outline: none;
    }

    /* ===== Empty state ===== */
    .empty-state { text-align: center; color: var(--text-3); padding: 48px 16px; }
    .empty-state i { font-size: 2.2rem; display: block; margin-bottom: 10px; opacity: .5; }

    /* =====================================================================
       SKELETON LOADING
       Per-page pattern: put placeholder markup shaped like the real page in
       a .skel-shell block, and wrap the page's actual content in a sibling
       .skel-real block (hidden by default). The script at the bottom of
       this partial swaps them shortly after the page paints. Content here
       is already server-rendered - this delay is purely perceptual, to
       soften the flash of a fully-built page appearing instantly and give
       every page a consistent loading feel.
       ===================================================================== */
    .skel-real { display: none; }

    .skel {
        position: relative;
        display: block;
        overflow: hidden;
        background: var(--border);
        border-radius: 6px;
    }
    .skel::after {
        content: '';
        position: absolute;
        inset: 0;
        transform: translateX(-100%);
        background: linear-gradient(90deg, transparent, rgba(255,255,255,.6), transparent);
        animation: skel-shimmer 1.4s ease-in-out infinite;
    }
    [data-theme="dark"] .skel::after {
        background: linear-gradient(90deg, transparent, rgba(255,255,255,.07), transparent);
    }
    @keyframes skel-shimmer { to { transform: translateX(100%); } }

    .skel-text { height: 13px; border-radius: 4px; margin: 0; }
    .skel-circle { border-radius: 50%; }

    /* Bootstrap accents that should follow the theme */
    .btn-primary { background-color: var(--primary-fill); border-color: var(--primary-fill); }
    .btn-primary:hover, .btn-primary:focus, .btn-primary:active { background-color: var(--primary-fill-hover) !important; border-color: var(--primary-fill-hover) !important; }
    .text-primary { color: var(--primary) !important; }
    /* Bootstrap's yellow (#ffc107) is 1.6:1 on white - unreadable as text
       ("Archive" and "Make Public" in the file menus use it). */
    .text-warning { color: var(--warning) !important; }

    /* Keyboard focus is always visible - several custom buttons reset
       outline to none, which left keyboard users with no position. */
    :focus-visible { outline: 2px solid var(--primary); outline-offset: 2px; }

    /* Text only screen readers hear, e.g. a file's name added to a
       repeated "Restore" button. Same as Bootstrap's .visually-hidden. */
    .sr-only {
        position: absolute !important; width: 1px !important; height: 1px !important;
        padding: 0 !important; margin: -1px !important; overflow: hidden !important;
        clip: rect(0, 0, 0, 0) !important; white-space: nowrap !important; border: 0 !important;
    }
    a { color: var(--primary); }
    a:hover { color: var(--primary-dark); }

    /* =====================================================================
       DARK MODE
       Toggled by data-theme="dark" on <html> (see partials/theme-mode +
       partials/theme-toggle). This block only re-points the tokens above and
       the handful of layout colours that were hard-coded white/light-grey;
       every page built on these tokens follows automatically. Bootstrap's own
       components come from data-bs-theme="dark", set alongside data-theme.
       ===================================================================== */
    :root[data-theme="dark"] {
        color-scheme: dark;

        /* surfaces - warm near-black neutrals (matches the portfolio palette) */
        --surface: #0a0a0a;
        --card: #161615;
        --border: #2c2b28;

        /* text */
        --text-1: #ededec;
        --text-2: #a1a09a;
        --text-3: #8a8984;

        /* the one accent - kept clean on the warm black */
        --primary: #35b14a;
        --primary-dark: #45c25a;
        --primary-dim: #13251a;
        --brand: #35b14a;

        /* deeper fills so white button text stays readable (5:1+) */
        --primary-fill: #1e7b34;
        --primary-fill-hover: #1a7230;
        --danger-fill: #c62828;

        /* semantics */
        --danger: #f75b4e;   --danger-dim: #241311;
        --red: #f75b4e;      --red-dim: #241311;
        --success: #35b14a;  --success-dim: #13251a;  --success-text: #55d06a;
        --warning: #d99a3e;  --warning-dim: #241d10;
        --amber: #d99a3e;    --amber-dim: #241d10;
        --green: #35b14a;    --green-dim: #13251a;
        --blue: #35b14a;     --blue-dim: #13251a;

        /* elevation - barely-there on near-black, keeps it smooth */
        --shadow-sm: 0 1px 2px rgba(0,0,0,.6);
        --shadow-md: 0 4px 14px rgba(0,0,0,.55);
        --shadow-lg: 0 10px 30px rgba(0,0,0,.6);

        /* layout tokens defined in Admin/home + SuperAdmin/homeSuperAdmin :root */
        --first-color: #35b14a;
        --first-color-dark: #55d06a;   /* active-link text - must be light here */
        --accent-dim: #13251a;
        --sidebar-bg: #111110;
        --sidebar-border: #262523;
        --nav-text: #a1a09a;
        --nav-text-strong: #ededec;
        --nav-hover-bg: #1e1e1c;
        --white-color: #0a0a0a;
    }

    /* --- layout chrome: spots that hard-coded #fff / light grey --- */
    [data-theme="dark"] body { background: var(--surface); color: var(--text-1); }
    [data-theme="dark"] .header { background-color: var(--card); }
    [data-theme="dark"] .l-navbar { background: var(--sidebar-bg); }
    [data-theme="dark"] .nav_logo-icon { background: var(--card); }

    [data-theme="dark"] .notif-panel { background: var(--card); border-color: var(--border); }
    [data-theme="dark"] .notif-item { border-bottom-color: var(--border); }
    [data-theme="dark"] .notif-item.unread { background: var(--primary-dim); }
    [data-theme="dark"] .notif-empty,
    [data-theme="dark"] .notif-when { color: var(--text-3); }

    [data-theme="dark"] .user-dropdown { background: var(--card); border-color: var(--border); }
    [data-theme="dark"] .user-dropdown a { color: var(--text-1); }
    [data-theme="dark"] .user-dropdown a:hover { background: var(--nav-hover-bg); color: var(--primary); }
    [data-theme="dark"] .user-dropdown a.signout { color: var(--danger); border-top-color: var(--border); }
    [data-theme="dark"] .user-dropdown a.signout:hover { background: var(--danger-dim); color: var(--danger); }

    /* --- safety net for pages that still reach for Bootstrap utilities /
           components directly instead of the tokens above --- */
    [data-theme="dark"] .bg-white { background-color: var(--card) !important; }
    [data-theme="dark"] .bg-light { background-color: var(--surface) !important; }
    [data-theme="dark"] .text-dark { color: var(--text-1) !important; }
    [data-theme="dark"] .text-muted,
    [data-theme="dark"] .text-secondary { color: var(--text-2) !important; }
    [data-theme="dark"] .card { background-color: var(--card); border-color: var(--border); color: var(--text-1); }
    [data-theme="dark"] .border,
    [data-theme="dark"] .border-top,
    [data-theme="dark"] .border-bottom,
    [data-theme="dark"] .border-start,
    [data-theme="dark"] .border-end { border-color: var(--border) !important; }
    [data-theme="dark"] hr { border-top-color: var(--border); }

    [data-theme="dark"] .modal-content { background-color: var(--card); color: var(--text-1); }
    [data-theme="dark"] .modal-header,
    [data-theme="dark"] .modal-footer { border-color: var(--border); }
    [data-theme="dark"] .btn-close { filter: invert(1) grayscale(100%) brightness(200%); }

    [data-theme="dark"] .table {
        --bs-table-color: var(--text-1);
        --bs-table-bg: transparent;
        --bs-table-border-color: var(--border);
        --bs-table-striped-color: var(--text-1);
        --bs-table-striped-bg: rgba(255,255,255,.03);
        --bs-table-hover-color: var(--text-1);
        --bs-table-hover-bg: var(--primary-dim);
        color: var(--text-1);
    }

    [data-theme="dark"] .form-control,
    [data-theme="dark"] .form-select {
        background-color: var(--surface);
        border-color: var(--border);
        color: var(--text-1);
    }
    [data-theme="dark"] .form-control::placeholder { color: var(--text-3); }
    [data-theme="dark"] .form-control:disabled,
    [data-theme="dark"] .form-control[readonly] { background-color: var(--card); }
    [data-theme="dark"] .input-group-text { background-color: var(--card); border-color: var(--border); color: var(--text-2); }

    [data-theme="dark"] .dropdown-menu { background-color: var(--card); border-color: var(--border); }
    [data-theme="dark"] .dropdown-item { color: var(--text-1); }
    [data-theme="dark"] .dropdown-item:hover,
    [data-theme="dark"] .dropdown-item:focus { background-color: var(--nav-hover-bg); color: var(--text-1); }
    [data-theme="dark"] .list-group-item { background-color: var(--card); border-color: var(--border); color: var(--text-1); }
    [data-theme="dark"] .page-link { background-color: var(--card); border-color: var(--border); color: var(--text-1); }
    [data-theme="dark"] .page-item.disabled .page-link { background-color: var(--card); color: var(--text-3); }

    [data-theme="dark"] .btn-flat.ghost { background: var(--card); }
    [data-theme="dark"] .chip.neutral { background: #26251f; color: #a1a09a; }

    /* =====================================================================
       SHARED PAGE-LEVEL OVERRIDES
       Several admin pages were built from copy-pasted <style> blocks and share
       the same class names with hard-coded whites / light greys. Re-pointing
       them here once keeps every one of those pages dark without editing each.
       Page-unique classes are still handled in that page's own <style>.
       ===================================================================== */

    /* light "paper" surfaces -> card */
    [data-theme="dark"] .file-header-card,
    [data-theme="dark"] .preview-card,
    [data-theme="dark"] .preview-shell,
    [data-theme="dark"] .audio-icon-ring,
    [data-theme="dark"] .card-dropdown,
    [data-theme="dark"] .year-dropdown-menu,
    [data-theme="dark"] .fab-label,
    [data-theme="dark"] .search-box,
    [data-theme="dark"] .tb-btn,
    [data-theme="dark"] .back-btn {
        background: var(--card);
        border-color: var(--border);
        color: var(--text-1);
    }

    /* even-lighter fills (table heads, code panes, empty states) -> surface */
    [data-theme="dark"] .file-table thead,
    [data-theme="dark"] .preview-text-wrap,
    [data-theme="dark"] .preview-none,
    [data-theme="dark"] .folder-count {
        background: var(--surface) !important;
        border-color: var(--border);
    }

    /* muted link / helper text that hard-coded a dark grey */
    [data-theme="dark"] .back-link,
    [data-theme="dark"] .text-viewer,
    [data-theme="dark"] .card-title,
    [data-theme="dark"] .file-name,
    [data-theme="dark"] .file-name-text,
    [data-theme="dark"] .modal-body,
    [data-theme="dark"] .modal-body strong { color: var(--text-1); }
    [data-theme="dark"] .back-link { color: var(--text-2); }
    [data-theme="dark"] .back-link:hover { color: var(--primary); }
    [data-theme="dark"] .preview-none p,
    [data-theme="dark"] .file-meta { color: var(--text-3); }

    /* hover-reveal 3-dot buttons on cards */
    [data-theme="dark"] .folder-menu-btn .btn,
    [data-theme="dark"] .file-menu-btn .btn,
    [data-theme="dark"] .card-menu-btn {
        background: rgba(0,0,0,.35);
        border-color: var(--border);
        color: var(--text-2);
    }
    [data-theme="dark"] .folder-menu-btn .btn:hover,
    [data-theme="dark"] .file-menu-btn .btn:hover,
    [data-theme="dark"] .card-menu-btn:hover { background: var(--nav-hover-bg); color: var(--text-1); }

    [data-theme="dark"] .card-dropdown-item { color: var(--text-1); }
    [data-theme="dark"] .card-dropdown-item:hover { background: var(--nav-hover-bg); }
    [data-theme="dark"] .tb-btn:hover { background: var(--nav-hover-bg); border-color: var(--text-3); color: var(--text-1); }

    /* upload drop zone */
    [data-theme="dark"] .upload-zone { border-color: var(--border); color: var(--text-2); }
    [data-theme="dark"] .upload-zone:hover,
    [data-theme="dark"] .upload-zone.drag-over { border-color: var(--primary); background: var(--primary-dim); }

    /* modal chrome */
    [data-theme="dark"] .modal-header,
    [data-theme="dark"] .modal-footer { border-color: var(--border); }

    /* search rows / file rows */
    [data-theme="dark"] .file-row { border-bottom-color: var(--border); color: var(--text-1); }
    [data-theme="dark"] .file-row:hover { background: var(--nav-hover-bg); color: var(--text-1); }
    [data-theme="dark"] .search-box input { background: var(--card); color: var(--text-1); }
    [data-theme="dark"] .search-box button { color: var(--text-2); }

    /* pale category tints on file-type icons -> readable dark tints */
    [data-theme="dark"] .file-icon.pdf  { background: #2a1618; color: #f87171; }
    [data-theme="dark"] .file-icon.docx { background: #16233a; color: #60a5fa; }
    [data-theme="dark"] .file-icon.xlsx { background: var(--primary-dim); color: var(--primary); }
    [data-theme="dark"] .file-icon.img  { background: #241a33; color: #c084fc; }

    /* corner upload-progress toast (inline-styled white box) */
    [data-theme="dark"] #uploadToast > div { background: var(--card) !important; border-color: var(--border) !important; }
    [data-theme="dark"] #uploadToast > div > div:first-child { border-bottom-color: var(--border) !important; color: var(--text-1); }
    [data-theme="dark"] .upload-toast-row { border-bottom-color: var(--border) !important; }
    [data-theme="dark"] .upload-toast-row .row-top { color: var(--text-1); }
    [data-theme="dark"] .upload-toast-row .row-track { background: var(--nav-hover-bg); }

    /* smooth the theme flip instead of a hard snap */
    [data-theme] body,
    [data-theme] .header,
    [data-theme] .l-navbar,
    [data-theme] .card-panel,
    [data-theme] .card { transition: background-color .25s ease, border-color .25s ease, color .25s ease; }

    /* Smooth the flip for everything else too, so nothing pops when the
       theme switches - per-element transitions above (and any component's
       own hover/focus transition, which wins on specificity) still apply. */
    [data-theme] * {
        transition: background-color .35s ease, border-color .35s ease,
                    color .35s ease, box-shadow .35s ease, fill .35s ease, stroke .35s ease;
    }

    /* ===================================================================
       THEME-FLIP WIPE ANIMATION
       Drives the diagonal reveal on the View Transitions API snapshot -
       see partials/theme-toggle.blade.php for the JS that starts it and
       picks the corner (top-left -> dark, bottom-right -> light).
       ===================================================================== */
    ::view-transition-group(root) { animation-duration: .6s; }
    ::view-transition-old(root),
    ::view-transition-new(root) {
        animation: none;
        mix-blend-mode: normal;
    }
    ::view-transition-old(root) { z-index: 1; }
    ::view-transition-new(root) { z-index: 2; }

    /* While the wipe's "new" snapshot is being captured, freeze every CSS
       transition so it grabs the settled end colours instead of a half
       -finished cross-fade; theme-toggle.blade.php lifts this the instant
       the snapshot is taken. */
    :root.theme-flip-lock,
    :root.theme-flip-lock * { transition: none !important; }
</style>

<script>
    // Swap every .skel-shell/.skel-real pair on the page once it's painted.
    // A short fixed delay rather than 0ms so the skeleton is actually
    // visible instead of flashing for a frame - content is already
    // server-rendered, so there's nothing to actually wait for.
    document.addEventListener('DOMContentLoaded', function () {
        setTimeout(function () {
            document.querySelectorAll('.skel-shell').forEach(function (el) { el.remove(); });
            document.querySelectorAll('.skel-real').forEach(function (el) { el.classList.remove('skel-real'); });
        }, 400);
    });
</script>
