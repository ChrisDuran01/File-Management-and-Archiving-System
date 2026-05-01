@extends('home')
@section('content')

<style>
    @import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap');

    :root {
        --surface:     #f7f8fc;
        --card:        #ffffff;
        --border:      #e8eaf0;
        --primary:     #2563eb;
        --primary-dim: #eff4ff;
        --success:     #16a34a;
        --success-dim: #f0fdf4;
        --text-1:      #111827;
        --text-2:      #6b7280;
        --text-3:      #9ca3af;
        --shadow-sm:   0 1px 3px rgba(0,0,0,.06), 0 1px 2px rgba(0,0,0,.04);
        --shadow-md:   0 4px 16px rgba(0,0,0,.08);
        --shadow-lg:   0 8px 32px rgba(0,0,0,.12);
        --radius:      12px;
        --radius-sm:   8px;
    }

    * { box-sizing: border-box; }

    body { background: var(--surface); font-family: 'DM Sans', sans-serif; color: var(--text-1); }

    /* ── Page header ─────────────────────────────── */
    .page-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }
    .page-header h4 {
        font-size: 1.35rem;
        font-weight: 600;
        letter-spacing: -.3px;
        margin: 0;
        color: var(--text-1);
    }

    /* ── Breadcrumb ───────────────────────────────── */
    .breadcrumb {
        background: none;
        padding: 0;
        margin-bottom: 20px;
        font-size: .82rem;
        gap: 4px;
    }
    .breadcrumb-item a {
        color: var(--text-2);
        text-decoration: none;
        transition: color .15s;
    }
    .breadcrumb-item a:hover { color: var(--primary); }
    .breadcrumb-item.active { color: var(--text-3); }
    .breadcrumb-item + .breadcrumb-item::before { color: var(--border); }

    /* ── Search ───────────────────────────────────── */
    .search-wrap {
        margin-bottom: 24px;
    }
    .search-inner {
        position: relative;
        max-width: 460px;
    }
    .search-inner .search-icon {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-3);
        font-size: .9rem;
        pointer-events: none;
    }
    .search-inner input {
        width: 100%;
        border: 1.5px solid var(--border);
        border-radius: 50px;
        padding: 9px 42px 9px 38px;
        font-size: .88rem;
        font-family: 'DM Sans', sans-serif;
        background: var(--card);
        color: var(--text-1);
        box-shadow: var(--shadow-sm);
        outline: none;
        transition: border-color .2s, box-shadow .2s;
    }
    .search-inner input:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(37,99,235,.12);
    }
    .search-inner button {
        position: absolute;
        right: 6px;
        top: 50%;
        transform: translateY(-50%);
        background: var(--primary);
        border: none;
        border-radius: 50px;
        color: #fff;
        padding: 5px 14px;
        font-size: .8rem;
        cursor: pointer;
        transition: background .15s;
    }
    .search-inner button:hover { background: #1d4ed8; }

    /* ── Stats bar ────────────────────────────────── */
    .stats-bar {
        display: flex;
        gap: 12px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }
    .stat-chip {
        display: flex;
        align-items: center;
        gap: 6px;
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 50px;
        padding: 5px 14px;
        font-size: .78rem;
        color: var(--text-2);
        box-shadow: var(--shadow-sm);
    }
    .stat-chip i { color: var(--text-3); font-size: .75rem; }

    /* ── File Table ───────────────────────────────── */
    .file-card {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        overflow: hidden;
        box-shadow: var(--shadow-sm);
    }
    .file-table {
        width: 100%;
        border-collapse: collapse;
    }
    .file-table thead {
        background: #f9fafb;
        border-bottom: 1px solid var(--border);
    }
    .file-table thead th {
        padding: 11px 16px;
        font-size: .74rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: var(--text-3);
        white-space: nowrap;
    }
    .file-table tbody tr {
        border-bottom: 1px solid var(--border);
        cursor: pointer;
        transition: background .12s;
    }
    .file-table tbody tr:last-child { border-bottom: none; }
    .file-table tbody tr:hover { background: var(--primary-dim); }

    .file-table td {
        padding: 13px 16px;
        font-size: .875rem;
        vertical-align: middle;
    }

    .file-name-cell {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .file-icon {
        width: 36px;
        height: 36px;
        border-radius: var(--radius-sm);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: .9rem;
    }
    .file-icon.pdf  { background: #fef2f2; color: #ef4444; }
    .file-icon.docx { background: #eff6ff; color: #3b82f6; }
    .file-icon.xlsx { background: #f0fdf4; color: #22c55e; }
    .file-icon.img  { background: #fdf4ff; color: #a855f7; }
    .file-icon.default { background: var(--primary-dim); color: var(--primary); }

    .file-name-text { font-weight: 500; color: var(--text-1); }
    .file-ext-badge {
        font-family: 'DM Mono', monospace;
        font-size: .7rem;
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 4px;
        padding: 1px 5px;
        color: var(--text-2);
        text-transform: uppercase;
    }
    .file-date { color: var(--text-2); font-size: .82rem; }
    .file-size {
        font-family: 'DM Mono', monospace;
        font-size: .8rem;
        color: var(--text-2);
    }

    .empty-state {
        text-align: center;
        padding: 56px 24px;
        color: var(--text-3);
    }
    .empty-state i { font-size: 2.5rem; margin-bottom: 12px; display: block; }
    .empty-state p { margin: 0; font-size: .9rem; }

    /* FAB */
.fab-container {
    position: fixed;
    bottom: 2rem;
    right: 2rem;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 12px;
    z-index: 1050;
    pointer-events: none;
}

.fab-container * {
    pointer-events: auto;
}
.fab-main {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: #534AB7;
    border: none;
    color: #fff;
    font-size: 22px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 16px rgba(83,74,183,0.35);
    cursor: pointer;
    transition: transform 0.25s ease, background 0.2s;
}
.fab-main:hover { background: #3C3489; }
.fab-main.open { transform: rotate(45deg); background: #3C3489; }

.fab-options { display: flex; flex-direction: column; align-items: flex-end; gap: 10px; }

.fab-option {
    display: flex;
    align-items: center;
    gap: 10px;
    opacity: 0;
    transform: translateY(10px) scale(0.95);
    pointer-events: none;
    transition: opacity 0.2s ease, transform 0.2s ease;
}
.fab-option.show { opacity: 1; transform: translateY(0) scale(1); pointer-events: all; }
.fab-option:nth-child(1) { transition-delay: 0.06s; }
.fab-option:nth-child(2) { transition-delay: 0.02s; }

.fab-label {
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 5px 14px;
    font-size: 13px;
    font-weight: 500;
    color: #333;
    white-space: nowrap;
    box-shadow: 0 2px 8px rgba(0,0,0,0.07);
}

.fab-mini {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 17px;
    color: #fff;
    cursor: pointer;
    transition: transform 0.15s;
    box-shadow: 0 3px 10px rgba(0,0,0,0.15);
}
.fab-mini:hover { transform: scale(1.1); }
.fab-mini.upload { background: #378ADD; }
.fab-mini.folder { background: #1D9E75; }

.modal-content { border-radius: 16px; border: none; box-shadow: 0 12px 40px rgba(0,0,0,0.12); }
.modal-header { border-bottom: 1px solid #f0f0f0; padding: 1.25rem 1.5rem; }
.modal-footer { border-top: 1px solid #f0f0f0; padding: 1rem 1.5rem; }
.modal-body { padding: 1.25rem 1.5rem; }
.modal-title { font-weight: 500; font-size: 16px; }

.upload-zone {
    border: 2px dashed #d0d0d0;
    border-radius: 12px;
    padding: 2.5rem 1rem;
    text-align: center;
    color: #888;
    font-size: 14px;
    cursor: pointer;
    transition: border-color 0.2s, background 0.2s;
}
.upload-zone:hover { border-color: #378ADD; background: #f0f7ff; }
.upload-zone i { font-size: 2rem; color: #378ADD; margin-bottom: 8px; display: block; }


</style>

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

{{-- ── Header ─────────────────────────────────────────── --}}
<div class="page-header">
    <h4><i class="fas fa-folder-open me-2" style="color:var(--primary);"></i>{{ $folders->name }}</h4>
</div>

{{-- ── Breadcrumb ──────────────────────────────────────── --}}
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('folders.index') }}"><i class="fas fa-home me-1"></i>All Folders</a></li>
        @php
            $current     = $folders;
            $breadcrumbs = [];
            while ($current) { $breadcrumbs[] = $current; $current = $current->parent; }
            $breadcrumbs = array_reverse($breadcrumbs);
        @endphp
        @foreach($breadcrumbs as $crumb)
            <li class="breadcrumb-item">
                <a href="{{ route('folders.show', $crumb->id) }}">{{ $crumb->name }}</a>
            </li>
        @endforeach
    </ol>
</nav>

{{-- ── Search ──────────────────────────────────────────── --}}
<!--<div class="search-wrap">
    <div class="search-inner">
        <form action="{{ route('search') }}" method="GET" style="display:contents;">
            <i class="fas fa-search search-icon"></i>
            <input type="text" name="query" placeholder="Search files or folders…" required>
            <button type="submit">Search</button>
        </form>
    </div>
</div>-->

{{-- ── Stats chips ─────────────────────────────────────── --}}
<div class="stats-bar">
    <span class="stat-chip">
        <i class="fas fa-file"></i>
        {{ $files->count() }} {{ Str::plural('file', $files->count()) }}
    </span>
    @if($files->count())
    <span class="stat-chip">
        <i class="fas fa-hdd"></i>
        {{ number_format($files->sum('size') / 1024, 1) }} KB total
    </span>
    @endif
</div>

{{-- ── File table ──────────────────────────────────────── --}}
<div class="file-card">
    <table class="file-table">
        <thead>
            <tr>
                <th>File Name</th>
                <th>Modified</th>
                <th>Size</th>
            </tr>
        </thead>
        <tbody>
            @forelse($files as $file)
            @php
                $ext  = strtolower(pathinfo($file->filename, PATHINFO_EXTENSION));
                $name = pathinfo($file->filename, PATHINFO_FILENAME);
                $iconClass = match(true) {
                    $ext === 'pdf'                      => 'pdf',
                    in_array($ext, ['doc','docx'])      => 'docx',
                    in_array($ext, ['xls','xlsx','csv'])=> 'xlsx',
                    in_array($ext, ['jpg','jpeg','png','gif','webp','svg']) => 'img',
                    default => 'default'
                };
                $iconName = match($iconClass) {
                    'pdf'   => 'fa-file-pdf',
                    'docx'  => 'fa-file-word',
                    'xlsx'  => 'fa-file-excel',
                    'img'   => 'fa-file-image',
                    default => 'fa-file-alt',
                };
            @endphp
            <tr>
                <td  onclick="window.location='{{ route('files.preview', $file->id) }}'">
                    <div class="file-name-cell">
                        <div class="file-icon {{ $iconClass }}">
                            <i class="fas {{ $iconName }}"></i>
                        </div>
                        <span class="file-name-text">{{ $name }}</span>
                        <span class="file-ext-badge">{{ $ext }}</span>
                    </div>
                </td>
                <td  onclick="window.location='{{ route('files.preview', $file->id) }}'" class="file-date">{{ $file->created_at->format('M d, Y · h:i A') }}</td>
                <td  onclick="window.location='{{ route('files.preview', $file->id) }}'"class="file-size">{{ number_format($file->size / 1024, 2) }} KB</td>

                 {{-- ✅ ACTION MENU --}}
    <td style="text-align:right; position:relative;">
        <div class="dropdown">
            <button class="btn btn-sm btn-light" data-bs-toggle="dropdown" style="border-radius:8px;">
                <i class="fas fa-ellipsis-v"></i>
            </button>

            <ul class="dropdown-menu dropdown-menu-end">

                {{-- Rename --}}
                <li>
                    <a class="dropdown-item" href="#">
                        <i class="fas fa-edit me-2 text-primary"></i> Rename
                    </a>
                </li>

                {{-- Download --}}
                <li>
                    <a class="dropdown-item"
                       href=">
                        <i class="fas fa-download me-2 text-success"></i> Download
                    </a>
                </li>

                {{-- Manage Access --}}
                <li>
                    <a class="dropdown-item" href="#">
                        <i class="fas fa-user-shield me-2 text-warning"></i> Manage Access
                    </a>
                </li>

            </ul>
        </div>
    </td>
            </tr>
            @empty
            <tr>
                <td colspan="3">
                    <div class="empty-state">
                        <i class="fas fa-folder-open"></i>
                        <p>This folder is empty. Upload a file to get started.</p>
                    </div>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>


{{-- FLOATING ACTION BUTTON --}}
<div class="fab-container" id="fabContainer">
    <div class="fab-options">

        <div class="fab-option" id="fabOptUpload">
            <span class="fab-label">Upload files</span>
            <button class="fab-mini upload"
                    data-bs-toggle="modal" data-bs-target="#uploadModal"
                    onclick="closeFab()">
                <i class="fas fa-upload"></i>
            </button>
        </div>

        <!--<div class="fab-option" id="fabOptFolder">
            <span class="fab-label">New folder</span>
            <button class="fab-mini folder"
                    data-bs-toggle="modal" data-bs-target="#addFolderModal"
                    onclick="closeFab()">
                <i class="bi bi-folder-plus"></i>
            </button>
        </div>-->

    </div>
    <button class="fab-main" id="fabMain" onclick="toggleFab()">
        <i class="fas fa-plus"></i>
    </button>
</div>

{{-- UPLOAD FILES MODAL --}}
<div class="modal fade" id="uploadModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:420px;">
        <div class="modal-content">
            <form action="{{ route('files.store') }}" method="POST" enctype="multipart/form-data">
                @csrf

                <!-- Folder ID for Controller -->
                <input type="hidden" name="folder_id" value="{{ $folders->id }}">

                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-upload me-2 text-primary"></i>Upload Files
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">

                    <div class="upload-zone" 
                         id="uploadZone"
                         onclick="document.getElementById('fileInput').click()">

                        <i class="fas fa-cloud-upload-alt"></i>
                        <div style="font-weight:500; color:#555; margin-bottom:4px;">
                            Click to browse
                        </div>
                        <div>or drag and drop files here</div>
                    </div>

                    <input type="file"
                           id="fileInput"
                           name="files[]"
                           multiple
                           hidden
                           onchange="showFileNames(this)">

                    <div id="fileNameList"
                         class="mt-2"
                         style="font-size:12px; color:#666;">
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                        Cancel
                    </button>

                    <button type="submit" class="btn btn-primary" style="border-radius:8px;">
                        <i class="fas fa-upload me-1"></i> Upload
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
let fabOpen = false;

function toggleFab() {
    fabOpen = !fabOpen;
    document.getElementById('fabMain').classList.toggle('open', fabOpen);
    document.querySelectorAll('.fab-option').forEach(o => o.classList.toggle('show', fabOpen));
}

function closeFab() {
    fabOpen = false;
    document.getElementById('fabMain').classList.remove('open');
    document.querySelectorAll('.fab-option').forEach(o => o.classList.remove('show'));
}

document.addEventListener('click', function(e) {
    if (fabOpen && !document.getElementById('fabContainer').contains(e.target)) {
        closeFab();
    }
});

function showFileNames(input) {
    const list = document.getElementById('fileNameList');
    if (!input.files.length) { list.innerHTML = ''; return; }
    const names = Array.from(input.files).map(f =>
        `<div style="padding:3px 0; border-bottom:1px solid #f0f0f0;">
            <i class="bi bi-file-earmark me-1"></i>${f.name}
            <span class="text-muted ms-1">(${(f.size / 1024).toFixed(1)} KB)</span>
         </div>`
    ).join('');
    list.innerHTML = `<div style="max-height:120px; overflow-y:auto; border:1px solid #eee;
                           border-radius:8px; padding:6px 10px; margin-top:6px;">${names}</div>`;
}
</script>
@endsection