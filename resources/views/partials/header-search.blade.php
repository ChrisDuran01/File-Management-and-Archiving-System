{{--
    Global topbar search — included in the Admin and SuperAdmin header
    chrome (Admin/home.blade.php, SuperAdmin/homeSuperAdmin.blade.php) so
    it's available from every page, not just the folders list.

    Reuses the existing SearchController routes as-is: no new backend
    logic here, and no access-control change. performSearch() already
    filters every result through each model's isAccessibleBy($user), which
    already knows the difference between a regular Admin (blocked from
    someone else's restricted folder) and a SuperAdmin (sees everything) -
    that's the same rule every other page in the app relies on, so a
    regular Admin searching from the topbar still can't see results they
    couldn't already open directly, and a SuperAdmin still sees everything,
    exactly as before.
--}}
<style>
    .header-search { position: relative; width: 420px; flex-shrink: 1; min-width: 0; }
    .header-search .header-search-field {
        position: relative;
        display: flex;
        align-items: center;
    }
    .header-search input {
        width: 100%;
        height: 42px;
        border-radius: 21px;
        border: 1px solid var(--sidebar-border, #e8eaf0);
        background: var(--nav-hover-bg, #f4f6f9);
        padding: 0 14px 0 42px;
        font-size: 14.5px;
        color: var(--nav-text-strong, #0f172a);
        transition: background .15s, border-color .15s, width .15s;
    }
    .header-search input::placeholder { color: var(--nav-text, #64748b); }
    .header-search input:focus {
        outline: none;
        background: #fff;
        border-color: var(--first-color, #058028);
    }
    .header-search-icon {
        position: absolute;
        left: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--nav-text, #64748b);
        font-size: 17px;
        pointer-events: none;
    }
    .header-search-dropdown {
        display: none;
        position: absolute;
        top: calc(100% + 8px);
        left: 0;
        width: 420px;
        max-width: 80vw;
        max-height: 70vh;
        overflow-y: auto;
        background: #fff;
        border: 1px solid #e8eaf0;
        border-radius: 12px;
        box-shadow: 0 12px 32px rgba(15,23,42,.14);
        z-index: 1200;
    }
    .header-search-dropdown.show { display: block; }

    /* Result rows - same classes searchLive.blade.php renders, so this
       dropdown looks right no matter which page's topbar fetched it
       (previously these rules only existed inside Admin/folders.blade.php,
       so the same dropdown markup rendered unstyled everywhere else). */
    .header-search-dropdown .live-search-list { padding: 6px; }
    .header-search-dropdown .live-search-row {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 9px 10px;
        border-radius: 10px;
        text-decoration: none;
        color: #1a1a1a;
    }
    .header-search-dropdown .live-search-row:hover { background: #f5f6fa; }
    .header-search-dropdown .live-search-icon {
        width: 34px;
        height: 34px;
        border-radius: 9px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        flex-shrink: 0;
    }
    .header-search-dropdown .live-search-icon.folder  { background: #fffbeb; color: #f59e0b; }
    .header-search-dropdown .live-search-icon.file    { background: #e9f5ee; color: #058028; }
    .header-search-dropdown .live-search-icon.archive { background: #f4f4f5; color: #71717a; }
    .header-search-dropdown .live-search-text { min-width: 0; display: flex; flex-direction: column; }
    .header-search-dropdown .live-search-name {
        font-size: 13px;
        font-weight: 500;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .header-search-dropdown .live-search-meta { font-size: 11px; color: #646b78; }
    .header-search-dropdown .live-search-snippet {
        font-size: 11px;
        color: #6b7280;
        margin-top: 2px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .header-search-dropdown .live-search-snippet mark { background: #fde68a; border-radius: 2px; padding: 0 1px; }
    .header-search-dropdown .live-search-viewall {
        display: block;
        text-align: center;
        padding: 10px;
        font-size: 12px;
        font-weight: 600;
        color: #058028;
        text-decoration: none;
        border-top: 1px solid #f0f0f0;
    }
    .header-search-dropdown .live-search-viewall:hover { background: #f5f6fa; }
    .header-search-dropdown .live-search-empty {
        padding: 20px 16px;
        text-align: center;
        font-size: 12px;
        color: #646b78;
    }

    @media (max-width: 860px) {
        .header-search { display: none; }
    }

    [data-theme="dark"] .header-search input { background: var(--nav-hover-bg); border-color: var(--border); color: var(--text-1); }
    [data-theme="dark"] .header-search input::placeholder { color: var(--text-3); }
    [data-theme="dark"] .header-search input:focus { background: var(--card); border-color: var(--primary); }
    [data-theme="dark"] .header-search-dropdown { background: var(--card); border-color: var(--border); }
    [data-theme="dark"] .header-search-dropdown .live-search-row { color: var(--text-1); }
    [data-theme="dark"] .header-search-dropdown .live-search-row:hover { background: var(--nav-hover-bg); }
    [data-theme="dark"] .header-search-dropdown .live-search-icon.folder  { background: var(--warning-dim); color: var(--warning); }
    [data-theme="dark"] .header-search-dropdown .live-search-icon.file    { background: var(--primary-dim); color: var(--primary); }
    [data-theme="dark"] .header-search-dropdown .live-search-icon.archive { background: var(--nav-hover-bg); color: var(--text-2); }
    [data-theme="dark"] .header-search-dropdown .live-search-meta,
    [data-theme="dark"] .header-search-dropdown .live-search-empty { color: var(--text-3); }
    [data-theme="dark"] .header-search-dropdown .live-search-snippet { color: var(--text-2); }
    [data-theme="dark"] .header-search-dropdown .live-search-viewall { color: var(--primary); border-top-color: var(--border); }
    [data-theme="dark"] .header-search-dropdown .live-search-viewall:hover { background: var(--nav-hover-bg); }
</style>

<div class="header-search">
    <form class="header-search-field" action="{{ route('search') }}" method="GET" id="topbarSearchForm" autocomplete="off" role="search">
        <i class='bx bx-search header-search-icon' aria-hidden="true"></i>
        {{-- The placeholder disappears once typing starts, so the field has
             a real name of its own. --}}
        <input type="text" name="query" id="topbarSearchInput" placeholder="Search folders, files, documents…"
               aria-label="Search folders, files and documents" aria-controls="topbarSearchDropdown">
    </form>
    <div id="topbarSearchDropdown" class="header-search-dropdown"></div>
</div>

<script>
(function () {
    const input    = document.getElementById('topbarSearchInput');
    const dropdown = document.getElementById('topbarSearchDropdown');
    const liveUrl  = "{{ route('search.live') }}";

    let debounceTimer = null;
    let activeRequest  = null;

    function hideDropdown() {
        dropdown.classList.remove('show');
    }

    function runSearch(query) {
        if (activeRequest) {
            activeRequest.abort();
        }

        const controller = new AbortController();
        activeRequest = controller;

        // Set by the current page when it has one - tells the backend to
        // surface this folder's own results first (window.currentFolderId,
        // e.g. showFolder.blade.php) or to lead the dropdown with a
        // particular result section (window.searchContext, e.g. the
        // Archives page) - still a system-wide search either way.
        const folderParam  = window.currentFolderId ? `&folder_id=${encodeURIComponent(window.currentFolderId)}` : '';
        const contextParam = window.searchContext ? `&context=${encodeURIComponent(window.searchContext)}` : '';

        fetch(`${liveUrl}?query=${encodeURIComponent(query)}${folderParam}${contextParam}`, {
            signal: controller.signal,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(res => res.text())
            .then(html => {
                dropdown.innerHTML = html;
                dropdown.classList.add('show');
            })
            .catch(err => {
                if (err.name !== 'AbortError') hideDropdown();
            });
    }

    input.addEventListener('input', function () {
        const query = input.value.trim();

        // Pages with their own on-screen list (e.g. Admin/archives.blade.php)
        // expose a global filterCards(query) that instantly narrows what's
        // already rendered - runs on every keystroke, no debounce needed
        // since it's a pure client-side DOM pass, not a network request.
        if (typeof window.filterCards === 'function') {
            window.filterCards(query);
        }

        clearTimeout(debounceTimer);

        if (query.length < 2) {
            // Also stop a search already on its way, or its results would
            // pop up after the box was cleared.
            if (activeRequest) {
                activeRequest.abort();
                activeRequest = null;
            }
            hideDropdown();
            return;
        }

        debounceTimer = setTimeout(() => runSearch(query), 300);
    });

    input.addEventListener('focus', function () {
        if (input.value.trim().length >= 2 && dropdown.innerHTML.trim() !== '') {
            dropdown.classList.add('show');
        }
    });

    document.addEventListener('click', function (e) {
        if (!dropdown.contains(e.target) && e.target !== input) {
            hideDropdown();
        }
    });

    input.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') hideDropdown();
    });
})();
</script>
