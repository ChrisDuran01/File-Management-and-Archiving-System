@extends('home')
@section('content')
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
    body { font-family: 'Segoe UI', sans-serif; }

    /* ── Page Header ── */
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
    }
    .page-title {
        font-size: 20px;
        font-weight: 600;
        color: #1a1a1a;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }
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

    /* ── Alerts ── */
    .alert-custom {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 16px;
        border-radius: 10px;
        font-size: 13px;
        margin-bottom: 1rem;
        border: 1px solid;
    }
    .alert-custom.success {
        background: #eaf3de;
        color: #27500a;
        border-color: #c0dd97;
    }
    .alert-custom.danger {
        background: #fcebeb;
        color: #791f1f;
        border-color: #f7c1c1;
    }
    .alert-custom .alert-close {
        margin-left: auto;
        cursor: pointer;
        opacity: 0.5;
        font-size: 16px;
        line-height: 1;
        background: none;
        border: none;
        color: inherit;
    }
    .alert-custom .alert-close:hover { opacity: 1; }

    /* ── Search Bar ── */
    .search-wrap { max-width: 460px; margin-bottom: 1.5rem; }
    .search-box {
        display: flex;
        align-items: center;
        border-radius: 30px;
        border: 1px solid #e0e0e0;
        background: #fff;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    }
    .search-box input {
        flex: 1;
        border: none;
        background: transparent;
        padding: 10px 18px;
        font-size: 14px;
        color: #333;
        outline: none;
    }
    .search-box button {
        border: none;
        background: transparent;
        padding: 10px 16px;
        cursor: pointer;
        color: #888;
        display: flex;
        align-items: center;
    }
    .search-box button:hover { color: #444; }

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
        color: #534AB7;
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
        color: #888;
        font-size: 15px;
        transition: background 0.12s;
        opacity: 0;
        transition: opacity 0.15s, background 0.12s;
    }
    .archive-card:hover .card-menu-btn { opacity: 1; }
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
        color: #999;
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
        color: #aaa;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    /* ── Empty State ── */
    .empty-state {
        grid-column: 1 / -1;
        text-align: center;
        padding: 4rem 1rem;
        color: #bbb;
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
        .page-header { flex-wrap: wrap; gap: 10px; }
    }
</style>

<body>
<div class="container-fluid">

    {{-- Page Header --}}
    <div class="page-header">
        <h4 class="page-title">
            Archives
            <span class="archive-count" id="archiveCount">{{ $archives->count() }} archives</span>
        </h4>
        <a href="{{ route('folders.index') }}" class="back-btn">
            <i class="fas fa-folder" style="font-size:13px;"></i> Back to Folders
        </a>
    </div>

    {{-- Flash Messages --}}
    @if(session('success'))
        <div class="alert-custom success" id="successAlert">
            <i class="fas fa-check-circle"></i>
            {{ session('success') }}
            <button class="alert-close" onclick="this.closest('.alert-custom').remove()">&times;</button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert-custom danger" id="errorAlert">
            <i class="fas fa-exclamation-circle"></i>
            {{ session('error') }}
            <button class="alert-close" onclick="this.closest('.alert-custom').remove()">&times;</button>
        </div>
    @endif

    {{-- Search Bar --}}
    <div class="search-wrap">
        <div class="search-box">
            <input type="text" id="searchInput" placeholder="Search archives..." oninput="filterCards()">
            <button type="button"><i class="fas fa-search"></i></button>
        </div>
    </div>

    {{-- Archive Card Grid --}}
    <div class="archive-grid" id="archiveGrid">

        @forelse($archives as $archive)
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
                    <button class="card-menu-btn" onclick="toggleDropdown(this)" type="button">
                        <i class="fas fa-ellipsis-v"></i>
                    </button>
                    <div class="card-dropdown">
                        {{-- Download --}}
                        <a href="{{ route('archives.download', $archive->id) }}"
                           class="card-dropdown-item">
                            <i class="fas fa-download" style="font-size:12px;color:#378ADD;"></i> Download
                        </a>
                        {{-- Restore --}}
                        <button type="button"
                                class="card-dropdown-item warn restore-trigger"
                                data-id="{{ $archive->id }}"
                                data-name="{{ $archive->folder_name }}">
                            <i class="fas fa-undo-alt" style="font-size:12px;"></i> Restore
                        </button>
                        <div class="card-dropdown-divider"></div>
                        {{-- Delete --}}
                        <button type="button"
                                class="card-dropdown-item danger delete-trigger"
                                data-id="{{ $archive->id }}"
                                data-name="{{ $archive->zip_name }}">
                            <i class="fas fa-trash" style="font-size:12px;"></i> Delete
                        </button>
                    </div>
                </div>
            </div>

            {{-- Card Body --}}
            <div class="card-folder-name">{{ $archive->folder_name }}</div>
            <div class="card-zip-name">
                <i class="fas fa-file-archive"></i>{{ $archive->zip_name }}
            </div>

            {{-- Card Footer --}}
            <div class="card-footer-row">
                @if($archive->status == 'archived')
                    <span class="status-badge badge-archived">
                        <span class="dot"></span> Archived
                    </span>
                @else
                    <span class="status-badge badge-pending">
                        <span class="dot"></span> {{ ucfirst($archive->status) }}
                    </span>
                @endif
                <span class="card-date">
                    <i class="far fa-clock" style="font-size:10px;"></i>
                    {{ $archive->archived_at ? $archive->archived_at->format('Y-m-d H:i') : 'N/A' }}
                </span>
            </div>

        </div>
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
                        <i class="fas fa-undo-alt"></i> Confirm Restore
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
                        <i class="fas fa-trash"></i> Confirm Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    /* ── Dropdown toggle ── */
    function toggleDropdown(btn) {
        const menu = btn.nextElementSibling;
        document.querySelectorAll('.card-dropdown.open').forEach(m => {
            if (m !== menu) m.classList.remove('open');
        });
        menu.classList.toggle('open');
    }

    document.addEventListener('click', function(e) {
        if (!e.target.closest('.card-menu-wrap')) {
            document.querySelectorAll('.card-dropdown.open').forEach(m => m.classList.remove('open'));
        }
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

    /* ── Search / filter ── */
    function filterCards() {
        const q = document.getElementById('searchInput').value.toLowerCase().trim();
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

    /* ── Auto-dismiss alerts after 5s ── */
    setTimeout(() => {
        document.querySelectorAll('.alert-custom').forEach(el => {
            el.style.transition = 'opacity 0.4s';
            el.style.opacity = '0';
            setTimeout(() => el.remove(), 400);
        });
    }, 5000);
</script>
</body>
</html>
@endsection