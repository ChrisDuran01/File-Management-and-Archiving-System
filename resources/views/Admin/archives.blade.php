@extends($layout ?? 'Admin.home')
@section('title', 'Archives')
@section('content')

@include('Admin.partials.theme')

{{-- Read by the topbar search (partials/header-search.blade.php) so the
     Archives section leads the dropdown here, without narrowing the
     search itself to only archives. --}}
<script>window.searchContext = 'archives';</script>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Archives</title>
</head>

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

<body>

{{-- Skeleton placeholder, shown until the real content below is revealed --}}
<div class="skel-shell" aria-hidden="true">
    <div class="page-header">
        <div class="skel skel-text" style="width:120px; height:22px;"></div>
        <div style="display:flex; gap:8px;">
            <div class="skel" style="width:220px; height:36px; border-radius:10px;"></div>
            <div class="skel" style="width:160px; height:36px; border-radius:10px;"></div>
        </div>
    </div>

    <div class="archive-grid">
        @for ($i = 0; $i < 8; $i++)
        <div class="archive-card">
            <div class="card-top">
                <div class="skel" style="width:42px; height:42px; border-radius:10px;"></div>
                <div class="skel" style="width:30px; height:30px; border-radius:7px;"></div>
            </div>
            <div class="skel skel-text" style="width:75%; margin-bottom:8px;"></div>
            <div class="skel skel-text" style="width:55%; margin-bottom:14px;"></div>
            <div class="card-footer-row">
                <div class="skel" style="width:70px; height:18px; border-radius:20px;"></div>
                <div class="skel skel-text" style="width:80px;"></div>
            </div>
        </div>
        @endfor
    </div>
</div>

<div class="container-fluid skel-real">

    {{-- Page Header --}}
    <div class="page-header">
        <h1>Archives</h1>
        <div class="d-flex align-items-center gap-2">
            <form action="{{ route('archives.runAutoArchive') }}" method="POST" class="d-flex align-items-center gap-2"
                  data-confirm-title="Run the auto-archive check?"
                  data-confirm="Every folder stored under a past school year that has not been opened for the number of years entered (3 if left blank) is packed into Archives and leaves the Folders page. They can be restored from Archives."
                  data-confirm-ok="Run check" data-confirm-tone="warning">
                @csrf
                <label for="staleYears" class="visually-hidden">Years of inactivity before auto-archiving</label>
                <input type="number" min="0" name="years" id="staleYears"
                       class="form-control form-control-sm" style="width:70px;"
                       placeholder="3" title="Years of inactivity before auto-archiving (default 3)">
                <button type="submit" class="back-btn" style="border:1px solid #e0e0e0; cursor:pointer;">
                    <i class="fas fa-clock-rotate-left" style="font-size:13px;"></i> Run Auto-Archive Check
                </button>
            </form>
            <a href="{{ route('folders.index') }}" class="back-btn">
                <i class="fas fa-folder" style="font-size:13px;"></i> Back to Folders
            </a>
        </div>
    </div>

    {{-- Search now lives in the topbar (partials/header-search.blade.php),
         which also drives this page's card filtering directly - see
         filterCards() below and header-search.blade.php's input handler. --}}

    {{-- Archive Card Grid --}}
<div class="archive-grid" id="archiveGrid">

    @forelse($archives as $archive)

        @if(is_null($archive->restored_at))

        <div class="archive-card"
             data-folder="{{ strtolower($archive->folder_name) }}"
             data-zip="{{ strtolower($archive->zip_name) }}"
             data-status="{{ strtolower($archive->status) }}">

            {{-- Card Top --}}
            <div class="card-top">
                <div class="folder-icon-wrap">
                    <i class="fas fa-folder"></i>
                </div>

                <div class="card-menu-wrap">
                    <button class="card-menu-btn" onclick="toggleDropdown(this)" type="button"
                            aria-label="More actions for {{ $archive->folder_name }}" aria-expanded="false">
                        <i class="fas fa-ellipsis-v" aria-hidden="true"></i>
                    </button>

                    <div class="card-dropdown">

                        {{-- Open / View contents --}}
                        <a href="{{ route('archives.show', $archive->id) }}"
                           class="card-dropdown-item">
                            <i class="fas fa-eye" style="font-size:12px;color:#058028;"></i>
                            Open (view only)
                        </a>

                        <div class="card-dropdown-divider"></div>

                        {{-- Download --}}
                        <a href="{{ route('archives.download', $archive->id) }}"
                           class="card-dropdown-item">
                            <i class="fas fa-download" style="font-size:12px;color:#058028;"></i>
                            Download
                        </a>

                        {{-- Restore --}}
                        <button type="button"
                                class="card-dropdown-item warn restore-trigger"
                                data-id="{{ $archive->id }}"
                                data-name="{{ $archive->folder_name }}">
                            <i class="fas fa-undo-alt" style="font-size:12px;"></i>
                            Restore
                        </button>

                        <div class="card-dropdown-divider"></div>

                        {{-- Delete --}}
                        <button type="button"
                                class="card-dropdown-item danger delete-trigger"
                                data-id="{{ $archive->id }}"
                                data-name="{{ $archive->zip_name }}">
                            <i class="fas fa-trash" style="font-size:12px;"></i>
                            Delete
                        </button>

                    </div>
                </div>
            </div>

            {{-- Card Body --}}
            <div class="card-folder-name">{{ $archive->folder_name }}</div>

            <div class="card-zip-name">
                <i class="fas fa-file-archive"></i>
                {{ $archive->zip_name }}
            </div>

            @if($archive->school_year)
            <div class="card-zip-name" style="margin-top:-8px;">
                <i class="far fa-calendar"></i>
                SY {{ $archive->school_year }}
            </div>
            @endif

            {{-- Card Footer --}}
            <div class="card-footer-row">

                <span class="status-badge badge-archived">
                    <span class="dot"></span> Archived
                </span>

                <span class="card-date">
                    <i class="far fa-clock" style="font-size:10px;"></i>
                    {{ $archive->archived_at ? $archive->archived_at->format('Y-m-d H:i') : 'N/A' }}
                </span>

            </div>

            {{-- Fixity indicator - set by the weekly archives:verify-fixity
                 check against the checksum recorded when this archive was
                 created (only applies to un-restored archives, which is the
                 only kind rendered in this loop). --}}
            @if($archive->fixity_status === 'ok')
                <div class="fixity-row fixity-ok">
                    <svg width="13" height="13" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="6.5" stroke="currentColor" stroke-width="1.3"/><path d="M5.2 8.2l1.8 1.8 3.6-3.8" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <span class="fx-label">Verified {{ $archive->fixity_checked_at?->diffForHumans() }}</span>
                </div>
            @elseif($archive->fixity_status === 'failed')
                <div class="fixity-row fixity-failed">
                    <svg width="13" height="13" viewBox="0 0 16 16" fill="none"><path d="M8 1.6l6.9 12a1 1 0 0 1-.87 1.5H1.97a1 1 0 0 1-.87-1.5l6.9-12a1 1 0 0 1 1.74 0z" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round"/><line x1="8" y1="6.3" x2="8" y2="9.4" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/><circle cx="8" cy="11.8" r="0.75" fill="currentColor"/></svg>
                    <span class="fx-label">Integrity check failed{{ $archive->fixity_checked_at ? ' — checked ' . $archive->fixity_checked_at->diffForHumans() : '' }}</span>
                </div>
            @else
                <div class="fixity-row fixity-none">
                    <svg width="13" height="13" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="6.5" stroke="currentColor" stroke-width="1.3"/><line x1="8" y1="5.3" x2="8" y2="8.6" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/><circle cx="8" cy="10.8" r="0.7" fill="currentColor"/></svg>
                    <span class="fx-label">Not yet verified</span>
                </div>
            @endif

        </div>

        @endif

    @empty

        <div class="empty-state">
            <i class="fas fa-archive"></i>
            <p>No archives found.</p>
        </div>

    @endforelse

</div>
</div>

{{-- ── Restore Modal ── --}}
<div class="modal fade" id="restoreModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:400px;">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-undo-alt" style="color:#BA7517;"></i> Restore Archive
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to restore this archive?</p>
                <p><strong>Folder:</strong> <span id="restoreFolderName"></span></p>
                <p style="margin-top:10px;">This will:</p>
                <ul>
                    <li>Recreate the folder in your main directory</li>
                    <li>Extract all files back to the folder</li>
                    <li>Remove the archive record</li>
                </ul>
            </div>
            <div class="modal-footer d-flex gap-2 justify-content-end">
                <form id="restoreForm" method="POST">
                    @csrf
                    <button type="button" class="btn-action secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-action warn">
                        <i class="fas fa-undo-alt" aria-hidden="true"></i> Restore folder
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- ── Delete Modal ── --}}
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:400px;">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-trash" style="color:#A32D2D;"></i> Delete Archive
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to permanently delete this archive?</p>
                <p><strong>Archive:</strong> <span id="deleteArchiveName"></span></p>
                <p style="color:#A32D2D; margin-top:8px; font-size:12px;">
                    <i class="fas fa-exclamation-triangle"></i> This action cannot be undone.
                </p>
            </div>
            <div class="modal-footer d-flex gap-2 justify-content-end">
                <form id="deleteForm" method="POST">
                    @csrf
                    @method('DELETE')
                    <button type="button" class="btn-action secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-action danger">
                        <i class="fas fa-trash" aria-hidden="true"></i> Delete archive
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    /* ── Dropdown toggle ── */
    // aria-expanded on each menu button follows its menu, so screen readers
    // hear "expanded"/"collapsed".
    function closeCardMenu(menu) {
        menu.classList.remove('open');
        menu.previousElementSibling?.setAttribute('aria-expanded', 'false');
    }

    function toggleDropdown(btn) {
        const menu = btn.nextElementSibling;
        document.querySelectorAll('.card-dropdown.open').forEach(m => {
            if (m !== menu) closeCardMenu(m);
        });
        menu.classList.toggle('open');
        btn.setAttribute('aria-expanded', menu.classList.contains('open') ? 'true' : 'false');
    }

    document.addEventListener('click', function(e) {
        if (!e.target.closest('.card-menu-wrap')) {
            document.querySelectorAll('.card-dropdown.open').forEach(closeCardMenu);
        }
    });

    // Escape closes an open card menu and returns focus to its button.
    document.addEventListener('keydown', function(e) {
        if (e.key !== 'Escape') return;
        document.querySelectorAll('.card-dropdown.open').forEach(m => {
            closeCardMenu(m);
            m.previousElementSibling?.focus();
        });
    });

    /* ── Restore trigger ── */
    document.querySelectorAll('.restore-trigger').forEach(btn => {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.card-dropdown.open').forEach(m => m.classList.remove('open'));
            document.getElementById('restoreFolderName').textContent = this.dataset.name;
            document.getElementById('restoreForm').action = `/archives/${this.dataset.id}/restore`;
            new bootstrap.Modal(document.getElementById('restoreModal')).show();
        });
    });

    /* ── Delete trigger ── */
    document.querySelectorAll('.delete-trigger').forEach(btn => {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.card-dropdown.open').forEach(m => m.classList.remove('open'));
            document.getElementById('deleteArchiveName').textContent = this.dataset.name;
            document.getElementById('deleteForm').action = `/archives/${this.dataset.id}`;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        });
    });

    /* ── Search / filter ──
       Called directly by the topbar search's input handler (see
       partials/header-search.blade.php), which is now this page's only
       visible search box - no separate local one to read from. */
    function filterCards(query) {
        const q = (query ?? '').toLowerCase().trim();
        const cards = document.querySelectorAll('.archive-card');
        let visible = 0;

        cards.forEach(card => {
            const match = !q ||
                card.dataset.folder.includes(q) ||
                card.dataset.zip.includes(q) ||
                card.dataset.status.includes(q);
            card.style.display = match ? '' : 'none';
            if (match) visible++;
        });

        document.getElementById('archiveCount').textContent =
            visible + ' archive' + (visible !== 1 ? 's' : '');
    }
</script>
</body>
</html>
@endsection
