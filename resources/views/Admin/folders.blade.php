@extends('Admin.home')
@section('content')

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

<style>
body { font-family: 'Segoe UI', sans-serif; }

/* Search Bar */
.search-bar { max-width: 480px; }
.search-bar .input-group {
    border-radius: 30px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    border: 1px solid #e0e0e0;
}
.search-bar .form-control {
    border: none;
    padding: 10px 20px;
    font-size: 14px;
    background: #fff;
}
.search-bar .form-control:focus { box-shadow: none; }
.search-bar .btn { border: none; background: #fff; color: #888; padding: 10px 18px; }
.search-bar .btn:hover { background: #f5f5f5; }

/* Folder Card — allow dropdown to overflow */
.folder-card {
    border-radius: 14px;
    border: 1px solid #ececec;
    background: #fff;
    transition: all 0.2s ease;
    position: relative;
    cursor: pointer;
    overflow: visible; /* FIX: was clipping the dropdown */
}
.folder-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.08);
    border-color: #d5d5d5;
}

/* Column stacking fix */
.col-xl-2,
.col-lg-2,
.col-md-3,
.col-sm-4,
.col-6 {
    position: relative;
    overflow: visible !important;
    z-index: 1;
}

.col-xl-2:hover,
.col-lg-2:hover,
.col-md-3:hover,
.col-sm-4:hover,
.col-6:hover {
    z-index: 50;
}

/* 3-dot menu */
.folder-menu-btn {
    position: absolute;
    top: 8px;
    right: 8px;
    opacity: 0;
    transition: opacity 0.15s;
    z-index: 10; /* FIX: sit above the card content */
}
.folder-card:hover .folder-menu-btn { opacity: 1; }
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
.folder-menu-btn .btn:hover { background: #f0f0f0; }

/* FIX: Ensure open dropdown floats above ALL sibling cards */
.folder-menu-btn .dropdown-menu.show {
    z-index: 9999 !important;
    position: absolute !important;
}

/* col wrappers must not clip children */
.col-xl-2, .col-lg-2, .col-md-3, .col-sm-4, .col-6 {
    overflow: visible !important;
}

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
    background: #0F6E56;
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
.fab-main:hover { background: darkgreen; }
.fab-main.open { transform: rotate(45deg); background: darkgreen; }

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

/* Modals */
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

/* Page header */
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
.page-title { font-size: 20px; font-weight: 600; color: #1a1a1a; margin: 0; }
.folder-count {
    font-size: 12px;
    background: #f4f4f4;
    border: 1px solid #e8e8e8;
    border-radius: 20px;
    padding: 3px 10px;
    color: #666;
}

/* ── Root Files ── */
.section-divider {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 2rem 0 1rem;
    color: #999;
    font-size: 13px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: .05em;
}
.section-divider::before,
.section-divider::after {
    content: '';
    flex: 1;
    height: 1px;
    background: #ececec;
}

.file-card {
    border-radius: 12px;
    border: 1px solid #ececec;
    background: #fff;
    transition: all 0.2s ease;
    position: relative;
    overflow: visible;
}
.file-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.07);
    border-color: #d5d5d5;
}

.file-menu-btn {
    position: absolute;
    top: 6px;
    right: 6px;
    opacity: 0;
    transition: opacity 0.15s;
    z-index: 10;
}
.file-card:hover .file-menu-btn { opacity: 1; }
.file-menu-btn .btn {
    width: 26px;
    height: 26px;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    background: rgba(255,255,255,0.9);
    border: 1px solid #e5e5e5;
    color: #666;
    font-size: 12px;
}
.file-menu-btn .btn:hover { background: #f0f0f0; }
.file-menu-btn .dropdown-menu.show {
    z-index: 1055 !important;
    position: absolute !important;
}

.file-icon-wrap {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    margin: 0 auto 8px;
}
.file-name {
    font-size: 12px;
    font-weight: 500;
    color: #333;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 100%;
}
.file-meta {
    font-size: 11px;
    color: #aaa;
    margin-top: 2px;
}
</style>


{{-- Page Header --}}
<div class="page-header">
    <h4 class="page-title">All Folders</h4>
    <span class="folder-count">{{ $folders->count() }} folders</span>
</div>


{{-- Add beside Search Bar --}}
<div class="d-flex gap-2 flex-wrap align-items-center mb-4">

    {{-- Search --}}
    <div class="search-bar flex-grow-1">
        <form action="{{ route('search') }}" method="GET">
            <div class="input-group">
                <input type="text" name="query" class="form-control"
                       placeholder="Search files or folders...">
                <button class="btn" type="submit">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </form>
    </div>

    <br>
    <br>

    {{-- School Year Filter --}}
    <!--<form action="{{ route('folders.index') }}" method="GET">
        <select name="term" class="form-select" onchange="this.form.submit()">
            <option value="">All Terms</option>
            <option value="2026-2027"
                {{ request('term') == '2026-2027' ? 'selected' : '' }}>
                SY 2026-2027
            </option>
            <option value="2025-2026"
                {{ request('term') == '2025-2026' ? 'selected' : '' }}>
                SY 2025-2026
            </option>
        </select>
    </form>

    {{-- Archive Button --}}
    <button class="btn btn-warning"
            data-bs-toggle="modal"
            data-bs-target="#archiveFolderModal">
        <i class="bi bi-archive me-1"></i> Archive Folders
    </button>

</div>-->

<br>

{{-- Folders Grid --}}
<div class="row g-3">
    @forelse($folders as $folder)
    <div class="col-xl-2 col-lg-2 col-md-3 col-sm-4 col-6">
        <div class="folder-card p-3 text-center">

            {{-- 3-dot Menu --}}
            <div class="dropdown folder-menu-btn">
                <button class="btn btn-sm" data-bs-toggle="dropdown" aria-expanded="false"
                        onclick="event.stopPropagation()">
                    <i class="bi bi-three-dots-vertical"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm"
                    style="border-radius:10px; font-size:13px; min-width:130px;">
                    <li>
                        <a class="dropdown-item py-2" href="{{ route('folders.show', $folder->id) }}">
                            <i class="bi bi-folder2-open me-2 text-primary"></i> Open
                        </a>
                    </li>
                    <li>
                        <button class="dropdown-item py-2"
                                data-bs-toggle="modal"
                                data-bs-target="#renameModal{{ $folder->id }}">
                            <i class="bi bi-pencil me-2 text-secondary"></i> Rename
                        </button>
                    </li>
                    <li>
    <form action="{{ route('folders.archive', $folder->id) }}" method="POST">
        @csrf
        <button type="submit" class="dropdown-item py-2 text-warning" onclick="return confirm('Archive this folder?')">
            <i class="bi bi-archive me-2"></i> Archive
        </button>
        
    </form>
</li>
                    <li><hr class="dropdown-divider my-1"></li>
                    <li>
                        <form action="{{ route('folders.destroy', $folder->id) }}" method="POST">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="dropdown-item py-2 text-danger">
                                <i class="bi bi-trash me-2"></i> Delete
                            </button>
                        </form>
                    </li>
                </ul>
            </div>

            {{-- Folder Icon & Name --}}
            <a href="{{ route('folders.show', $folder->id) }}" style="text-decoration:none; color:inherit;">
                <i class="bi bi-folder-fill text-warning" style="font-size:4rem;"></i>
                <div class="mt-2 text-truncate" style="font-size:13px; font-weight:500;">
                    {{ $folder->name }}
                </div>
            </a>

        </div>
    </div>

    {{-- ARCHIVE MULTIPLE FOLDERS MODAL --}}
<div class="modal fade" id="archiveFolderModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">

            <form action="{{ route('folders.archive.selected') }}" method="POST">
                @csrf

                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-archive me-2 text-warning"></i>
                        Archive Selected Folders
                    </h5>
                    <button type="button" class="btn-close"
                            data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">

                    <label class="fw-semibold mb-2">
                        Select folders to archive
                    </label>

                    <div style="max-height:260px; overflow-y:auto;"
                         class="border rounded p-2">

                        @foreach($folders as $folder)
                        <div class="form-check mb-2">
                            <input class="form-check-input"
                                   type="checkbox"
                                   name="folders[]"
                                   value="{{ $folder->id }}"
                                   id="folder{{ $folder->id }}">

                            <label class="form-check-label"
                                   for="folder{{ $folder->id }}">
                                <i class="bi bi-folder-fill text-warning me-1"></i>
                                {{ $folder->name }}
                            </label>
                        </div>
                        @endforeach

                    </div>

                    <div class="mt-3">
                        <label class="form-label">
                            Archive Name / ZIP File
                        </label>

                        <input type="text"
                               name="zip_name"
                               class="form-control"
                               placeholder="Example: SY-2026-2027-Archive.zip"
                               required>
                    </div>

                    <div class="mt-3">
                        <label class="form-label">
                            School Year Tag
                        </label>

                        <select name="school_year"
                                class="form-select">
                            <option value="2026-2027">2026-2027</option>
                            <option value="2025-2026">2025-2026</option>
                        </select>
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="button"
                            class="btn btn-light"
                            data-bs-dismiss="modal">
                        Cancel
                    </button>

                    <button type="submit"
                            class="btn btn-warning">
                        <i class="bi bi-archive me-1"></i>
                        Archive Now
                    </button>
                </div>

            </form>

        </div>
    </div>
</div>

@foreach($folders as $folder)
    {{-- Rename Modal --}}
    <div class="modal fade" id="renameModal{{ $folder->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width:380px;">
            <div class="modal-content">
                <form action="{{ route('folders.update', $folder->id) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Rename Folder</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="text" name="name" class="form-control"
                               value="{{ $folder->name }}" required autofocus style="border-radius:8px;">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" style="border-radius:8px;">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endforeach


    @empty
    <div class="col-12 text-center py-5 text-muted">
        <i class="bi bi-folder-x" style="font-size:4rem; opacity:0.3;"></i>
        <p class="mt-3">No folders yet. Click the <strong>+</strong> button to create one.</p>
    </div>
    @endforelse
</div>


{{-- ── ROOT FILES SECTION ── --}}
@if(isset($rootFiles) && $rootFiles->count())
<!--<div class="section-divider">Files in Root</div>-->

<br>



@elseif(isset($rootFiles) && $rootFiles->count() === 0)
{{-- Only show empty state if the variable is passed but empty --}}
<div class="section-divider">Files in Root</div>
<div class="text-center py-4 text-muted" style="font-size:13px;">
    <i class="bi bi-inbox" style="font-size:2.5rem; opacity:0.25; display:block; margin-bottom:8px;"></i>
    No files in root. Upload files without selecting a folder.
</div>
@endif


{{-- FLOATING ACTION BUTTON --}}
<div class="fab-container" id="fabContainer">
    <div class="fab-options">


        <div class="fab-option" id="fabOptFolder">
            <span class="fab-label">New folder</span>
            <button class="fab-mini folder"
                    data-bs-toggle="modal" data-bs-target="#addFolderModal"
                    onclick="closeFab()">
                <i class="bi bi-folder-plus"></i>
            </button>
        </div>

    </div>
    <button class="fab-main" id="fabMain" onclick="toggleFab()">
        <i class="fas fa-plus"></i>
    </button>
</div>


{{-- ADD FOLDER MODAL --}}
<div class="modal fade" id="addFolderModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:380px;">
        <div class="modal-content">
            <form action="{{ route('folders.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-folder-plus me-2 text-success"></i>New Folder</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label text-muted" style="font-size:13px;">Folder name</label>
                    <input type="text" name="folder_name" class="form-control"
                           placeholder="e.g. My Documents" required autofocus style="border-radius:8px;">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success" style="border-radius:8px;">
                        <i class="bi bi-check-lg me-1"></i> Create
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


{{-- UPLOAD FILES MODAL --}}
<div class="modal fade" id="uploadModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:420px;">
        <div class="modal-content">
            <form action="{{ route('files.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-upload me-2 text-primary"></i>Upload Files</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">

                    <div class="upload-zone" onclick="document.getElementById('fileInput').click()">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <div style="font-weight:500; color:#555; margin-bottom:4px;">Click to browse</div>
                        <div>or drag and drop files here</div>
                    </div>
                    <input type="file" id="fileInput" name="files[]" multiple hidden
                           onchange="showFileNames(this)">

                    <div id="fileNameList" class="mt-2" style="font-size:12px; color:#666;"></div>

                    @if($folders->count())
                    <div class="mt-3">
                        <label class="form-label text-muted" style="font-size:13px;">Upload to folder (optional)</label>
                        <select name="folder_id" class="form-select form-select-sm" style="border-radius:8px;">
                            <option value="">— Root —</option>
                            @foreach($folders as $folder)
                            <option value="{{ $folder->id }}">{{ $folder->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
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
