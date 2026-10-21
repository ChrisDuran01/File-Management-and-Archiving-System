@extends($layout ?? 'Admin.home')
@section('title', $folders->name)
@section('content')

@include('Admin.partials.theme')

{{-- Read by the topbar search (partials/header-search.blade.php) so
     results from this folder surface first, without narrowing the search
     itself to only this folder. --}}
<script>window.currentFolderId = {{ $folders->id }};</script>

<style>
    * { box-sizing: border-box; }

    body { background: var(--surface); font-family: 'Inter', sans-serif; color: var(--text-1); }

    /* ── Page header ─────────────────────────────── */
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
    /* Sortable column headings - the button inherits the heading's look */
    .sort-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 0;
        border: none;
        background: none;
        font: inherit;
        color: inherit;
        text-transform: inherit;
        letter-spacing: inherit;
        cursor: pointer;
    }
    .sort-btn i { font-size: .72rem; opacity: .5; }
    .sort-btn:hover { color: var(--text-1); }
    .file-table th[aria-sort="ascending"] .sort-btn,
    .file-table th[aria-sort="descending"] .sort-btn { color: var(--primary); }
    .file-table th[aria-sort="ascending"] .sort-btn i,
    .file-table th[aria-sort="descending"] .sort-btn i { opacity: 1; }

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
    .file-icon.xlsx { background: #e9f5ee; color: #22c55e; }
    .file-icon.img  { background: #fdf4ff; color: #a855f7; }
    .file-icon.default { background: var(--primary-dim); color: var(--primary); }

    .file-name-text { font-weight: 500; color: var(--text-1); }
    a.file-name-link { text-decoration: none; }
    a.file-name-link:hover { color: var(--text-1); text-decoration: underline; }
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
    background: #058028;
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
.fab-mini.upload { background: #058028; }
.fab-mini.folder { background: #046322; }

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
    color: var(--text-3);
    font-size: 14px;
    cursor: pointer;
    transition: border-color 0.2s, background 0.2s;
}
.upload-zone:hover,
.upload-zone.drag-over { border-color: #058028; background: #f0f7ff; }
.upload-zone i { font-size: 2rem; color: #058028; margin-bottom: 8px; display: block; }

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

{{-- Skeleton placeholder, shown until the real content below is revealed --}}
<div class="skel-shell" aria-hidden="true">
    <div class="skel skel-text" style="width:150px; margin-bottom:1rem;"></div>

    <div class="page-header">
        <h1>
            <div class="skel skel-circle" style="width:20px; height:20px; display:inline-block; margin-right:8px; vertical-align:middle;"></div>
            <div class="skel skel-text" style="width:160px; display:inline-block; vertical-align:middle;"></div>
        </h1>
    </div>

    <div class="skel skel-text" style="width:70px; margin-bottom:20px;"></div>

    <div class="stats-bar">
        <div class="skel" style="width:90px; height:26px; border-radius:50px;"></div>
        <div class="skel" style="width:130px; height:26px; border-radius:50px;"></div>
    </div>

    <div class="file-card">
        <table class="file-table">
            <thead>
                <tr>
                    <th><div class="skel skel-text" style="width:80px;"></div></th>
                    <th><div class="skel skel-text" style="width:70px;"></div></th>
                    <th><div class="skel skel-text" style="width:50px;"></div></th>
                </tr>
            </thead>
            <tbody>
                @for ($i = 0; $i < 6; $i++)
                <tr>
                    <td>
                        <div class="file-name-cell">
                            <div class="skel" style="width:36px; height:36px; border-radius:8px;"></div>
                            <div class="skel skel-text" style="width:140px;"></div>
                        </div>
                    </td>
                    <td><div class="skel skel-text" style="width:110px;"></div></td>
                    <td><div class="skel skel-text" style="width:60px;"></div></td>
                </tr>
                @endfor
            </tbody>
        </table>
    </div>
</div>

<div class="skel-real">
@php
    // Folders kept under a past school year are opened from that year's page
    $fromHistory = $folders->is_archived && $folders->school_year;
@endphp
@include('Admin.partials.back-link', [
    'backTo'    => $fromHistory ? route('folders.history', $folders->school_year) : route('folders.index'),
    'backLabel' => $fromHistory ? 'School Year '.$folders->school_year : 'Folders',
])

{{-- ── Header ─────────────────────────────────────────── --}}
<div class="page-header">
    <h1><i class="fas fa-folder-open me-2" style="color:var(--primary);"></i>{{ $folders->name }}</h1>
</div>

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
    @if($pendingCount = $files->where('is_pending_approval', true)->count())
    <span class="stat-chip" style="color:var(--warning);">
        <i class="bi bi-hourglass-split" style="color:inherit;"></i>
        {{ $pendingCount }} subject for approval
    </span>
    @endif
</div>

{{-- ── File table ──────────────────────────────────────── --}}
<div class="file-card">
    <table class="file-table" id="fileTable">
        <thead>
            <tr>
                <th data-sort="name" aria-sort="none">
                    <button type="button" class="sort-btn">File Name <i class="fas fa-sort" aria-hidden="true"></i></button>
                </th>
                <th data-sort="modified" aria-sort="none">
                    <button type="button" class="sort-btn">Modified <i class="fas fa-sort" aria-hidden="true"></i></button>
                </th>
                <th data-sort="size" aria-sort="none">
                    <button type="button" class="sort-btn">Size <i class="fas fa-sort" aria-hidden="true"></i></button>
                </th>
                <th></th>
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
            {{-- data-* are what the column sorting below reads --}}
            <tr data-name="{{ $file->filename }}"
                data-modified="{{ $file->updated_at->timestamp }}"
                data-size="{{ (int) $file->size }}">

                <td  onclick="window.location='{{ route('files.preview', $file->id) }}'">
                    <div class="file-name-cell">
                        <div class="file-icon {{ $iconClass }}" aria-hidden="true">
                            <i class="fas {{ $iconName }}"></i>
                        </div>
                        {{-- A real link, so the file can be opened from the keyboard
                             too (the row's onclick only serves the mouse). --}}
                        <a href="{{ route('files.preview', $file->id) }}" class="file-name-text file-name-link">{{ $name }}</a>
                        <span class="file-ext-badge">{{ $ext }}</span>
                        @include('Admin.partials.approval-badge', ['file' => $file])
                    </div>
                </td>
                <td  onclick="window.location='{{ route('files.preview', $file->id) }}'" class="file-date">{{ $file->updated_at->format('M d, Y · h:i A') }}</td>
                <td  onclick="window.location='{{ route('files.preview', $file->id) }}'" class="file-size">{{ number_format($file->size / 1024, 2) }} KB</td>

                 {{-- ✅ ACTION MENU --}}
    <td style="text-align: right; position: relative; width: 50px; vertical-align: middle;">
    <div class="dropdown folder-menu-btn" style="display: inline-block;">
                <button type="button" class="btn btn-sm" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false"
                        aria-label="More actions for {{ $file->filename }}"
                        onclick="event.stopPropagation()">
                    <i class="bi bi-three-dots-vertical" aria-hidden="true"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm"
                    style="border-radius:10px; font-size:13px; min-width:130px;">
                    <li>
    <a class="dropdown-item py-2" href="{{ route('files.download', $file->id) }}">
        <i class="bi bi-folder2-open me-2 text-primary"></i> Download
    </a>
</li>
                    {{-- File::isShareableBy() for a file in this folder --}}
                    @if(! $folders->is_restricted || $folders->manageable)
                    <li>
    <button type="button" class="dropdown-item py-2" data-share-file
            data-share-name="{{ $file->filename }}"
            data-share-url="{{ route('share-links.index', $file->id) }}">
        <i class="bi bi-link-45deg me-2 text-success"></i> Share link
    </button>
</li>
                    @endif
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
                    {{-- Only the folder's creator or a SuperAdmin may declare a file
                         approved (FileController::setApproval) - everyone else
                         just sees the label. --}}
                    @if(! $file->is_pending_approval || $folders->manageable)
                    <li>
    <form action="{{ route('file.setApproval', $file->id) }}" method="POST"
          @if(! $file->is_pending_approval && $file->is_public)
          data-confirm-title="Mark this file as subject for approval?"
          data-confirm="“{{ $file->filename }}” is public. It will be taken off the student page until it is marked as approved."
          data-confirm-ok="Mark it"
          data-confirm-tone="warning"
          @endif>
        @csrf
        <input type="hidden" name="pending" value="{{ $file->is_pending_approval ? 0 : 1 }}">

        <button type="submit" class="dropdown-item py-2">
            @if($file->is_pending_approval)
                <i class="bi bi-patch-check me-2 text-success"></i> Mark as approved
            @else
                <i class="bi bi-hourglass-split me-2 text-secondary"></i> Mark as subject for approval
            @endif
        </button>
    </form>
</li>
                    @endif
                    <li><hr class="dropdown-divider my-1"></li>
                    <li>
    <form action="{{ route('files.destroy', $file->id) }}" method="POST"
          data-confirm-title="Move this file to Trash?"
          data-confirm="“{{ $file->filename }}” can be restored from Trash within {{ config('trash.retention_days') }} days."
          data-confirm-ok="Move to Trash">
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
                <td colspan="4">
                    <div class="empty-state">
                        <i class="fas fa-folder-open"></i>
                        <p>No files yet. Click the <strong>+</strong> button to upload one.</p>
                    </div>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>
</div>

@forelse($files as $file)
<div class="modal fade" id="accessModal{{ $file->id }}" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">{{ $file->is_public ? 'Make private' : 'Make public' }}: {{ $file->filename }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        @if($file->is_public)
            <p>Do you want to make this file <strong>Private</strong>? Only you will have access.</p>
        @elseif($file->is_pending_approval)
            <p class="mb-0">This file is still <strong>subject for approval</strong>, so it can't be shared with students yet. Mark it as approved first, then make it public.</p>
        @else
            <p>Do you want to make this file <strong>Public</strong>? Anyone with the link can access it.</p>
            @unless($folders->is_restricted)
                <p class="small text-muted mb-0">It will be listed on the student page, and students who turned on notifications will get an alert.</p>
            @endunless
        @endif
      </div>

      <div class="modal-footer">
        <form action="{{ route('file.toggleAccess', $file->id) }}" method="POST"
              onsubmit="this.querySelector('button[type=submit]').disabled = true;">
            @csrf

            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                Cancel
            </button>

            @unless(! $file->is_public && $file->is_pending_approval)
            <button type="submit" class="btn btn-warning">
                {{ $file->is_public ? 'Make Private' : 'Make Public' }}
            </button>
            @endunless
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
            <span class="fab-label" aria-hidden="true">Upload files</span>
            <button type="button" class="fab-mini upload" aria-label="Upload files"
                    data-bs-toggle="modal" data-bs-target="#uploadModal"
                    onclick="closeFab()">
                <i class="fas fa-upload" aria-hidden="true"></i>
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
    <button type="button" class="fab-main" id="fabMain" onclick="toggleFab()"
            aria-label="Add" aria-expanded="false" aria-controls="fabOptUpload">
        <i class="fas fa-plus" aria-hidden="true"></i>
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
                        <i class="fas fa-upload me-2 text-primary" aria-hidden="true"></i>Upload Files
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">

                    {{-- role/tabindex + the keydown handler in setupDragDrop()
                         make the drop zone work from the keyboard too. --}}
                    <div class="upload-zone"
                         id="uploadZone"
                         role="button" tabindex="0"
                         aria-label="Choose files to upload, or drag and drop them here"
                         onclick="document.getElementById('fileInput').click()">

                        <i class="fas fa-cloud-upload-alt" aria-hidden="true"></i>
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
                         aria-live="polite"
                         style="font-size:12px; color:#666;">
                    </div>

                    <div class="form-check mt-3">
                        <input type="checkbox" class="form-check-input" id="scanAsDocument" name="scan_as_document" value="1">
                        <label class="form-check-label" for="scanAsDocument" style="font-size:13px;">
                            Scan as document <span class="text-muted">(convert a photo of a hardcopy into a searchable PDF)</span>
                        </label>
                    </div>

                    <div class="form-check mt-2">
                        <input type="checkbox" class="form-check-input" id="pendingApproval" name="pending_approval" value="1">
                        <label class="form-check-label" for="pendingApproval" style="font-size:13px;">
                            Still subject for approval <span class="text-muted">(label it as not yet official)</span>
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
            <span><i class="fas fa-cloud-upload-alt me-1" style="color:#058028;"></i> Uploading</span>
            <button type="button" id="uploadToastClose" style="border:none; background:none; color:var(--text-2); cursor:pointer; font-size:16px; line-height:1;" aria-label="Close">&times;</button>
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
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
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
                <label class="form-label" for="newFileName{{ $file->id }}">New name</label>
                <input type="text"
                       name="new_name"
                       id="newFileName{{ $file->id }}"
                       class="form-control"
                       value="{{ $nameWithoutExt }}"
                       required>
            </div>

            <div class="modal-footer px-0 pb-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    Cancel
                </button>

                <button type="submit" class="btn btn-primary">
                    Save name
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
.upload-toast-row .row-status { color:var(--text-3); white-space:nowrap; flex-shrink:0; }
.upload-toast-row .row-track { height:6px; border-radius:4px; background:#eee; overflow:hidden; }
.upload-toast-row .row-bar { height:100%; width:0%; background:#058028; transition:width .15s ease; }
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
    border: none; background: none; color: var(--text-2); cursor: pointer;
    font-size: 11px; padding: 0; flex-shrink: 0; line-height: 1;
}
.upload-toast-row .row-cancel-btn:hover { color: #c0392b; }
</style>
<script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
<script>
let fabOpen = false;

function toggleFab() {
    fabOpen = !fabOpen;
    document.getElementById('fabMain').classList.toggle('open', fabOpen);
    document.getElementById('fabMain').setAttribute('aria-expanded', fabOpen ? 'true' : 'false');
    document.querySelectorAll('.fab-option').forEach(o => o.classList.toggle('show', fabOpen));
}

function closeFab() {
    fabOpen = false;
    document.getElementById('fabMain').classList.remove('open');
    document.getElementById('fabMain').setAttribute('aria-expanded', 'false');
    document.querySelectorAll('.fab-option').forEach(o => o.classList.remove('show'));
}

document.addEventListener('click', function(e) {
    if (fabOpen && !document.getElementById('fabContainer').contains(e.target)) {
        closeFab();
    }
});

// Column sorting. Every file in the folder is already on the page, so a
// click just reorders the rows in place - first click ascending, second
// descending. The choice is remembered in this browser so it survives the
// reload after an upload, rename or delete.
(function setupFileSort() {
    const table = document.getElementById('fileTable');
    if (!table) return;

    const tbody = table.tBodies[0];
    const headers = Array.from(table.querySelectorAll('th[data-sort]'));
    const STORAGE_KEY = 'folderFileSort';

    const compare = {
        name: (a, b) => a.dataset.name.localeCompare(b.dataset.name, undefined, { numeric: true, sensitivity: 'base' }),
        modified: (a, b) => Number(a.dataset.modified) - Number(b.dataset.modified),
        size: (a, b) => Number(a.dataset.size) - Number(b.dataset.size),
    };

    function sortBy(key, direction) {
        const rows = Array.from(tbody.querySelectorAll('tr[data-name]'));
        const sign = direction === 'descending' ? -1 : 1;

        rows.sort((a, b) => sign * compare[key](a, b)).forEach(row => tbody.appendChild(row));

        headers.forEach(th => {
            const active = th.dataset.sort === key;
            th.setAttribute('aria-sort', active ? direction : 'none');
            th.querySelector('i').className = 'fas ' + (! active ? 'fa-sort' : direction === 'ascending' ? 'fa-sort-up' : 'fa-sort-down');
        });
    }

    headers.forEach(th => {
        th.querySelector('.sort-btn').addEventListener('click', function () {
            const direction = th.getAttribute('aria-sort') === 'ascending' ? 'descending' : 'ascending';
            sortBy(th.dataset.sort, direction);
            try { localStorage.setItem(STORAGE_KEY, JSON.stringify({ key: th.dataset.sort, direction: direction })); } catch (e) {}
        });
    });

    try {
        const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) || 'null');
        if (saved && compare[saved.key]) sortBy(saved.key, saved.direction === 'descending' ? 'descending' : 'ascending');
    } catch (e) {}
})();

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

    // Enter or Space on the focused zone opens the file picker, like a button.
    zone.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            input.click();
        }
    });
})('uploadZone', 'fileInput');

@include('Admin.partials.upload-script')
</script>

@include('partials.share-link')
@endsection
