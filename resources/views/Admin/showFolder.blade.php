@extends($layout ?? 'Admin.home')
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
        overflow: visible;
        box-shadow: var(--shadow-sm);
        margin-bottom: 20px;
    }
    .file-table {
        width: 100%;
        border-collapse: collapse;
        overflow: visible;
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
        overflow:visible;
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
    background: #0F6E56;;
    border: none;
    color: #fff;
    font-size: 22px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 16px rgba(87, 212, 124, 0.35);
    cursor: pointer;
    transition: transform 0.25s ease, background 0.2s;
}
.fab-main:hover { background: darkgreen; }
.fab-main.open { transform: rotate(45deg); background:darkgreen; }

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
.upload-zone:hover,
.upload-zone.drag-over { border-color: #378ADD; background: #f0f7ff; }
.upload-zone i { font-size: 2rem; color: #378ADD; margin-bottom: 8px; display: block; }

/* 3-dot menu */
.folder-menu-btn {
    position: relative;
    display: inline-block;
    opacity: 1;
}

.folder-menu-btn .btn {
    width: 28px;
    height: 28px;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    background: rgba(255,255,255,0.9);
    border: 1px solid #e5e5e5;
    color: #666;
    font-size: 13px;
}

.folder-menu-btn .btn:hover {
    background: #f0f0f0;
}

/* Make the dropdown menu appear on the LEFT side of the three dots */
.dropdown-menu-start {
    right: 100% !important;
    left: auto !important;
    margin-right: 8px;
}

</style>

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

{{-- ── Header ─────────────────────────────────────────── --}}
<div class="page-header">
    <h4><i class="fas fa-folder-open me-2" style="color:var(--primary);"></i>{{ $folders->name }}</h4>
</div>

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        {{ $errors->first() }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if($folders->is_archived)
<div class="alert alert-warning d-flex align-items-center gap-2" style="border-radius:10px;">
    <i class="fas fa-lock"></i>
    <span>This folder is stored under school year <strong>{{ $folders->school_year }}</strong> and is read-only.</span>
</div>
@endif

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
        $ext === 'pdf'                       => 'pdf',
        in_array($ext, ['doc','docx'])       => 'docx',
        in_array($ext, ['xls','xlsx','csv']) => 'xlsx',
        in_array($ext, ['ppt','pptx'])       => 'ppt',   // ✅ ADDED PPT
        in_array($ext, ['jpg','jpeg','png','gif','webp','svg']) => 'img',
        default => 'default'
    };

    $iconName = match($iconClass) {
        'pdf'   => 'fa-file-pdf',
        'docx'  => 'fa-file-word',
        'xlsx'  => 'fa-file-excel',
        'ppt'   => 'fa-file-powerpoint', // ✅ ADDED PPT ICON
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
                <td  onclick="window.location='{{ route('files.preview', $file->id) }}'" class="file-date">{{ $file->updated_at->format('M d, Y · h:i A') }}</td>
                <td  onclick="window.location='{{ route('files.preview', $file->id) }}'" class="file-size">{{ number_format($file->size / 1024, 2) }} KB</td>

                 {{-- ✅ ACTION MENU --}}
    <td style="text-align: right; position: relative; width: 50px; vertical-align: middle;">
    <div class="dropdown folder-menu-btn" style="display: inline-block;">
                <button class="btn btn-sm" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false"
                        onclick="event.stopPropagation()">
                    <i class="bi bi-three-dots-vertical"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm"
                    style="border-radius:10px; font-size:13px; min-width:130px;">
                    <li>
    <a class="dropdown-item py-2" href="{{ route('files.download', $file->id) }}">
        <i class="bi bi-folder2-open me-2 text-primary"></i> Download
    </a>
</li>
                    @if(!$folders->is_archived)
                    <li>
    <button class="dropdown-item py-2"
            data-bs-toggle="modal"
            data-bs-target="#renameModal{{ $file->id }}">
        <i class="bi bi-pencil me-2 text-secondary"></i> Rename
    </button>
</li>
                    <li>
    <form action="" method="POST">
        @csrf
        <button type="button"
        class="dropdown-item py-2 text-warning"
        data-bs-toggle="modal"
        data-bs-target="#accessModal{{ $file->id }}">

    @if($file->is_public)
        <i class="bi bi-lock-open me-2"></i> Make Private
    @else
        <i class="bi bi-globe me-2"></i> Make Public
    @endif
</button>
    </form>
</li>
                    <li><hr class="dropdown-divider my-1"></li>
                    <li>
    <form action="{{ route('files.destroy', $file->id) }}" method="POST"
          onsubmit="return confirm('Are you sure you want to delete this file?')">
        @csrf
        @method('DELETE')

        <button type="submit" class="dropdown-item py-2 text-danger">
            <i class="bi bi-trash me-2"></i> Delete
        </button>
    </form>
</li>
                    @endif
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

@forelse($files as $file)
<div class="modal fade" id="accessModal{{ $file->id }}" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">File Access Control</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        @if($file->is_public)
            <p>Do you want to make this file <strong>Private</strong>? Only you will have access.</p>
        @else
            <p>Do you want to make this file <strong>Public</strong>? Anyone with the link can access it.</p>
        @endif
      </div>

      <div class="modal-footer">
        <form action="{{ route('file.toggleAccess', $file->id) }}" method="POST"
              onsubmit="this.querySelector('button[type=submit]').disabled = true;">
            @csrf

            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                Cancel
            </button>

            <button type="submit" class="btn btn-warning">
                {{ $file->is_public ? 'Make Private' : 'Make Public' }}
            </button>
        </form>
      </div>

    </div>
  </div>
</div>
@empty
    {{-- Optional: no files message --}}
@endforelse

@if(!$folders->is_archived)
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

@endif

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
                           accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.gif,.webp"
                           onchange="showFileNames(this)">

                    <div id="fileNameList"
                         class="mt-2"
                         style="font-size:12px; color:#666;">
                    </div>

                    <div class="form-check mt-3">
                        <input type="checkbox" class="form-check-input" id="scanAsDocument" name="scan_as_document" value="1">
                        <label class="form-check-label" for="scanAsDocument" style="font-size:13px;">
                            Scan as document <span class="text-muted">(convert a photo of a hardcopy into a searchable PDF)</span>
                        </label>
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

{{-- UPLOAD PROGRESS TOAST (top right, survives the modal closing) --}}
<div id="uploadToast" style="display:none; position:fixed; top:20px; right:20px; width:320px; z-index:2000;">
    <div style="background:#fff; border:1px solid #e5e5e5; border-radius:12px; box-shadow:0 8px 30px rgba(0,0,0,0.15); overflow:hidden;">
        <div style="padding:10px 14px; border-bottom:1px solid #f0f0f0; font-weight:600; font-size:13px; display:flex; justify-content:space-between; align-items:center;">
            <span><i class="fas fa-cloud-upload-alt me-1" style="color:#534AB7;"></i> Uploading</span>
            <button type="button" id="uploadToastClose" style="border:none; background:none; color:#999; cursor:pointer; font-size:16px; line-height:1;" aria-label="Close">&times;</button>
        </div>
        <div id="uploadToastList" style="max-height:280px; overflow-y:auto;"></div>
    </div>
</div>


@forelse($files as $file)
<div class="modal fade" id="renameModal{{ $file->id }}" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">

      {{-- Header --}}
      <div class="modal-header">
        <h5 class="modal-title">Rename File</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      @php
        $nameWithoutExt = pathinfo($file->filename, PATHINFO_FILENAME);
      @endphp

      {{-- Body --}}
      <div class="modal-body">
        <p class="text-muted mb-2">
            Current name:
            <strong>{{ $nameWithoutExt }}</strong>
        </p>

        <form action="{{ route('files.rename', $file->id) }}" method="POST"
              onsubmit="this.querySelector('button[type=submit]').disabled = true;">
            @csrf

            <div class="mb-3">
                <label class="form-label">New Name</label>
                <input type="text"
                       name="new_name"
                       class="form-control"
                       value="{{ $nameWithoutExt }}"
                       required>
            </div>

            <div class="modal-footer px-0 pb-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    Cancel
                </button>

                <button type="submit" class="btn btn-primary">
                    Rename
                </button>
            </div>
        </form>
      </div>

    </div>
  </div>
</div>

@empty
    {{-- Optional: no files message --}}
@endforelse

<style>
.upload-toast-row { padding: 10px 14px; border-bottom: 1px solid #f5f5f5; }
.upload-toast-row:last-child { border-bottom: none; }
.upload-toast-row .row-top { display:flex; justify-content:space-between; font-size:12px; color:#333; margin-bottom:4px; gap:8px; }
.upload-toast-row .row-name { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:200px; }
.upload-toast-row .row-status { color:#888; white-space:nowrap; flex-shrink:0; }
.upload-toast-row .row-track { height:6px; border-radius:4px; background:#eee; overflow:hidden; }
.upload-toast-row .row-bar { height:100%; width:0%; background:#534AB7; transition:width .15s ease; }
.upload-toast-row .row-bar.is-indeterminate {
    background-image: linear-gradient(45deg, rgba(255,255,255,.3) 25%, transparent 25%, transparent 50%, rgba(255,255,255,.3) 50%, rgba(255,255,255,.3) 75%, transparent 75%, transparent);
    background-size: 20px 20px;
    animation: upload-progress-stripes 1s linear infinite;
}
.upload-toast-row .row-bar.is-done { background: #2e8b57; }
.upload-toast-row .row-bar.is-failed { background: #c0392b; }
@keyframes upload-progress-stripes {
    from { background-position: 20px 0; }
    to   { background-position: 0 0; }
}
.upload-toast-row .row-cancel-btn {
    border: none; background: none; color: #aaa; cursor: pointer;
    font-size: 11px; padding: 0; flex-shrink: 0; line-height: 1;
}
.upload-toast-row .row-cancel-btn:hover { color: #c0392b; }
</style>
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

// Without this, dropping files anywhere the browser doesn't explicitly
// handle makes it navigate to/download the file instead of accepting it.
window.addEventListener('dragover', e => e.preventDefault());
window.addEventListener('drop', e => e.preventDefault());

(function setupDragDrop(zoneId, inputId) {
    const zone = document.getElementById(zoneId);
    const input = document.getElementById(inputId);
    if (!zone || !input) return;

    ['dragenter', 'dragover'].forEach(evt => {
        zone.addEventListener(evt, function (e) {
            e.preventDefault();
            e.stopPropagation();
            zone.classList.add('drag-over');
        });
    });

    ['dragleave', 'drop'].forEach(evt => {
        zone.addEventListener(evt, function (e) {
            e.preventDefault();
            e.stopPropagation();
            zone.classList.remove('drag-over');
        });
    });

    zone.addEventListener('drop', function (e) {
        if (e.dataTransfer.files && e.dataTransfer.files.length) {
            input.files = e.dataTransfer.files;
            showFileNames(input);
        }
    });
})('uploadZone', 'fileInput');

// Uploads go straight from the browser to cloud storage (a signed URL from
// prepareUpload()) instead of relaying the bytes through this server - one
// hop instead of two. The modal closes immediately and progress shows in a
// corner toast so the page stays usable while it runs. If the direct
// upload fails for any file (network issue, or the browser's connection to
// Supabase specifically being blocked), that file automatically falls back
// to the older server-relayed path (files.store) instead of failing.
(function setupDirectUpload(formSelector, modalId) {
    const form = document.querySelector(formSelector);
    const modalEl = document.getElementById(modalId);
    const toast = document.getElementById('uploadToast');
    const toastList = document.getElementById('uploadToastList');
    const toastClose = document.getElementById('uploadToastClose');
    if (!form || !modalEl || !toast || !toastList) return;

    const csrfToken = form.querySelector('[name="_token"]').value;
    const scanCheckbox = form.querySelector('[name="scan_as_document"]');

    if (toastClose) {
        toastClose.addEventListener('click', function () {
            toast.style.display = 'none';
        });
    }

    function addRow(name) {
        const row = document.createElement('div');
        row.className = 'upload-toast-row';
        row.innerHTML =
            '<div class="row-top">' +
                '<span class="row-name">' + name + '</span>' +
                '<span class="row-status">Preparing…</span>' +
                '<button type="button" class="row-cancel-btn" title="Cancel upload"><i class="fas fa-times"></i></button>' +
            '</div>' +
            '<div class="row-track"><div class="row-bar"></div></div>';
        toastList.appendChild(row);

        const ui = {
            status: row.querySelector('.row-status'),
            bar: row.querySelector('.row-bar'),
            cancelBtn: row.querySelector('.row-cancel-btn'),
            xhr: null,       // whichever transfer is currently in flight for this file
            cancelled: false,
        };

        ui.cancelBtn.addEventListener('click', function () {
            ui.cancelled = true;
            if (ui.xhr) ui.xhr.abort();
            ui.status.textContent = 'Cancelled';
            ui.bar.classList.remove('is-indeterminate');
            ui.bar.classList.add('is-failed');
            ui.cancelBtn.style.display = 'none';
        });

        return ui;
    }

    // Cancelling aborts whichever leg (cloud or local fallback) is actively
    // sending bytes right now - ui.xhr always points at the current one, so
    // one abort() reaches whichever is running.
    function directUploadToCloud(file, uploadUrl, ui, onProgress) {
        return new Promise(function (resolve, reject) {
            if (ui.cancelled) { reject(new Error('cancelled')); return; }

            const xhr = new XMLHttpRequest();
            ui.xhr = xhr;
            xhr.upload.addEventListener('progress', function (evt) {
                if (evt.lengthComputable) onProgress(Math.round((evt.loaded / evt.total) * 100));
            });
            xhr.addEventListener('load', function () {
                if (xhr.status >= 200 && xhr.status < 300) resolve();
                else reject(new Error('Cloud PUT failed with status ' + xhr.status));
            });
            xhr.addEventListener('error', function () { reject(new Error('Cloud PUT network error')); });
            xhr.addEventListener('abort', function () { reject(new Error('cancelled')); });
            xhr.open('PUT', uploadUrl, true);
            xhr.setRequestHeader('Content-Type', file.type || 'application/octet-stream');
            xhr.send(file);
        });
    }

    function fallbackServerUpload(file, folderId, ui) {
        if (ui.cancelled) return Promise.resolve();

        ui.status.textContent = 'Saving locally…';
        ui.bar.classList.remove('is-indeterminate');

        return new Promise(function (resolve) {
            const fd = new FormData();
            fd.append('files[]', file);
            if (folderId) fd.append('folder_id', folderId);
            if (scanCheckbox && scanCheckbox.checked) fd.append('scan_as_document', '1');
            fd.append('_token', csrfToken);

            const xhr = new XMLHttpRequest();
            ui.xhr = xhr;
            xhr.upload.addEventListener('progress', function (evt) {
                if (evt.lengthComputable && ! ui.cancelled) ui.bar.style.width = Math.round((evt.loaded / evt.total) * 100) + '%';
            });
            xhr.addEventListener('loadend', function () {
                if (ui.cancelled) { resolve(); return; }
                ui.status.textContent = 'Saved (fallback)';
                ui.bar.style.width = '100%';
                ui.bar.classList.add('is-done');
                ui.cancelBtn.style.display = 'none';
                resolve();
            });
            xhr.addEventListener('abort', function () { resolve(); });
            xhr.open('POST', "{{ route('files.store') }}", true);
            xhr.send(fd);
        });
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        const fileInput = form.querySelector('input[type="file"]');
        const files = Array.from(fileInput.files || []);
        if (! files.length) return;

        const folderIdField = form.querySelector('[name="folder_id"]');
        const folderId = folderIdField ? folderIdField.value : '';

        (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).hide();

        toast.style.display = 'block';
        toastList.innerHTML = '';
        const rows = files.map(function (file) { return { file: file, ui: addRow(file.name) }; });

        fetch("{{ route('files.prepareUpload') }}", {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body: JSON.stringify({ filenames: files.map(function (f) { return f.name; }), folder_id: folderId || null }),
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            const prepared = data.files || [];

            const tasks = rows.map(function (entry, index) {
                const prep = prepared[index];

                if (entry.ui.cancelled) return Promise.resolve();

                if (! prep || ! prep.uploadUrl) {
                    return fallbackServerUpload(entry.file, folderId, entry.ui);
                }

                entry.ui.status.textContent = '0%';

                return directUploadToCloud(entry.file, prep.uploadUrl, entry.ui, function (percent) {
                        if (entry.ui.cancelled) return;
                        entry.ui.bar.style.width = percent + '%';
                        entry.ui.status.textContent = percent + '%';
                    })
                    .then(function () {
                        if (entry.ui.cancelled) return;
                        entry.ui.status.textContent = 'Saving…';
                        entry.ui.bar.classList.add('is-indeterminate');

                        return fetch("{{ route('files.confirmUpload') }}", {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                            body: JSON.stringify({
                                filename: prep.newFileName,
                                cloudPath: prep.cloudPath,
                                folder_id: folderId || null,
                                size: entry.file.size,
                                scan_as_document: (scanCheckbox && scanCheckbox.checked) ? 1 : 0,
                            }),
                        });
                    })
                    .then(function (res) {
                        if (entry.ui.cancelled) return;
                        if (! res.ok) throw new Error('confirmUpload failed');
                        entry.ui.bar.classList.remove('is-indeterminate');
                        entry.ui.bar.classList.add('is-done');
                        entry.ui.status.textContent = 'Done ✓';
                        entry.ui.cancelBtn.style.display = 'none';
                    })
                    .catch(function () {
                        // Don't fall back to the server-relay path if the
                        // user cancelled on purpose - only on a genuine failure.
                        if (entry.ui.cancelled) return;
                        entry.ui.bar.classList.remove('is-indeterminate');
                        return fallbackServerUpload(entry.file, folderId, entry.ui);
                    });
            });

            return Promise.all(tasks);
        })
        .catch(function () {
            // prepareUpload itself failed (offline, etc.) - relay every
            // non-cancelled file through the server instead.
            return Promise.all(rows.map(function (entry) {
                if (entry.ui.cancelled) return Promise.resolve();
                return fallbackServerUpload(entry.file, folderId, entry.ui);
            }));
        })
        .finally(function () {
            setTimeout(function () { window.location.reload(); }, 1200);
        });
    });
})('#uploadModal form', 'uploadModal');
</script>
@endsection
