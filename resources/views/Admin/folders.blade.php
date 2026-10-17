@extends($layout ?? 'Admin.home')
@section('content')

@include('Admin.partials.theme')

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

<style>
/* School year actions */
.btn-year {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    border-radius: 30px;
    font-size: 14px;
    font-weight: 600;
    white-space: nowrap;
    border: none;
    cursor: pointer;
    transition: transform 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
}
.btn-year i { font-size: 15px; }

.btn-year-prev {
    background: #fff;
    color: #555;
    border: 1px solid #e0e0e0;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}
.btn-year-prev:hover { background: #f5f5f5; border-color: #d5d5d5; color: #333; }
.btn-year-prev::after { margin-left: 4px; }

.year-dropdown-menu {
    border-radius: 12px;
    border: 1px solid #ececec;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    padding: 6px;
    min-width: 180px;
}
.year-dropdown-menu .dropdown-item {
    display: flex;
    align-items: center;
    gap: 8px;
    border-radius: 8px;
    padding: 8px 12px;
    font-size: 13.5px;
    color: #444;
}
.year-dropdown-menu .dropdown-item:hover { background: #fff8ec; color: #b9770e; }
.year-dropdown-menu .dropdown-item i { font-size: 12px; color: #d9a441; }

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
    background: #058028;
    border: none;
    color: #fff;
    font-size: 22px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 16px rgba(5,128,40,.25);
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
.fab-mini.upload { background: #058028; }
.fab-mini.folder { background: #046322; }

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
.upload-zone:hover,
.upload-zone.drag-over { border-color: #058028; background: #f0f7ff; }
.upload-zone i { font-size: 2rem; color: #058028; margin-bottom: 8px; display: block; }

/* Page header */
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

/* ===== DARK MODE — re-point this page's hard-coded light colours to tokens.
   Light mode is untouched; these only apply under data-theme="dark". ===== */
[data-theme="dark"] .btn-year-prev { background: var(--card); color: var(--text-1); border-color: var(--border); box-shadow: none; }
[data-theme="dark"] .btn-year-prev:hover { background: var(--nav-hover-bg); border-color: var(--text-3); color: var(--text-1); }
[data-theme="dark"] .year-dropdown-menu { border-color: var(--border); }
[data-theme="dark"] .year-dropdown-menu .dropdown-item { color: var(--text-1); }
[data-theme="dark"] .year-dropdown-menu .dropdown-item:hover { background: var(--warning-dim); color: var(--warning); }

[data-theme="dark"] .folder-card,
[data-theme="dark"] .file-card { background: var(--card); border-color: var(--border); }
[data-theme="dark"] .folder-card:hover,
[data-theme="dark"] .file-card:hover { border-color: var(--text-3); box-shadow: 0 8px 24px rgba(0,0,0,.5); }

[data-theme="dark"] .folder-menu-btn .btn,
[data-theme="dark"] .file-menu-btn .btn { background: rgba(0,0,0,.35); border-color: var(--border); color: var(--text-2); }
[data-theme="dark"] .folder-menu-btn .btn:hover,
[data-theme="dark"] .file-menu-btn .btn:hover { background: var(--nav-hover-bg); }

[data-theme="dark"] .fab-label { background: var(--card); border-color: var(--border); color: var(--text-1); }

[data-theme="dark"] .modal-header,
[data-theme="dark"] .modal-footer { border-color: var(--border); }

[data-theme="dark"] .upload-zone { border-color: var(--border); color: var(--text-2); }
[data-theme="dark"] .upload-zone:hover,
[data-theme="dark"] .upload-zone.drag-over { border-color: var(--primary); background: var(--primary-dim); }

[data-theme="dark"] .folder-count { background: var(--nav-hover-bg); border-color: var(--border); color: var(--text-2); }

[data-theme="dark"] .section-divider { color: var(--text-3); }
[data-theme="dark"] .section-divider::before,
[data-theme="dark"] .section-divider::after { background: var(--border); }

[data-theme="dark"] .file-name { color: var(--text-1); }
[data-theme="dark"] .file-meta { color: var(--text-3); }
</style>


{{-- Skeleton placeholder, shown until the real content below is revealed --}}
<div class="skel-shell" aria-hidden="true">
    <div class="page-header">
        <div class="skel skel-text" style="width:140px; height:22px;"></div>
        <div class="skel" style="width:190px; height:38px; border-radius:30px;"></div>
    </div>

    <div class="row g-3">
        @for ($i = 0; $i < 12; $i++)
        <div class="col-xl-2 col-lg-2 col-md-3 col-sm-4 col-6">
            <div class="folder-card p-3 text-center">
                <div class="skel" style="width:64px; height:64px; border-radius:12px; margin:0 auto 12px;"></div>
                <div class="skel skel-text" style="width:70%; margin:0 auto;"></div>
            </div>
        </div>
        @endfor
    </div>
</div>

{{-- Page Header --}}
<div class="skel-real">
<div class="page-header">
    <h2>All Folders</h2>

    {{-- Search now lives in the topbar (partials/header-search.blade.php),
         available on every Admin/SuperAdmin page instead of just this one. --}}
    <div class="d-flex gap-2 flex-wrap align-items-center">

        {{-- Previous School Years --}}
        @if(isset($pastSchoolYears) && $pastSchoolYears->count())
        <div class="dropdown">
            <button class="btn-year btn-year-prev dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-display="static">
                <i class="bi bi-clock-history"></i> Previous School Years
            </button>
            <ul class="dropdown-menu dropdown-menu-end year-dropdown-menu">
                @foreach($pastSchoolYears as $year)
                <li>
                    <a class="dropdown-item" href="{{ route('folders.history', $year) }}">
                        <i class="bi bi-folder2"></i> SY {{ $year }}
                    </a>
                </li>
                @endforeach
            </ul>
        </div>
        @endif

    </div>
</div>

{{-- Folders Grid --}}
<div class="row g-3">
    @forelse($folders as $folder)
    <div class="col-xl-2 col-lg-2 col-md-3 col-sm-4 col-6">
        <div class="folder-card p-3 text-center" style="{{ $folder->accessible ? '' : 'opacity:0.55;' }}">

            {{-- 3-dot Menu --}}
            <div class="dropdown folder-menu-btn">
                <button class="btn btn-sm" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false"
                        onclick="event.stopPropagation()">
                    <i class="bi bi-three-dots-vertical"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm"
                    style="border-radius:10px; font-size:13px; min-width:130px;">
                    @if($folder->accessible)
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
    <form action="{{ route('folders.archive', $folder->id) }}" method="POST"
          data-confirm-title="Archive this folder?"
          data-confirm="“{{ $folder->name }}” and its files are packed into a ZIP and leave the Folders page. You can restore it from Archives."
          data-confirm-ok="Archive" data-confirm-tone="warning">
        @csrf
        <button type="submit" class="dropdown-item py-2 text-warning">
            <i class="bi bi-archive me-2"></i> Archive
        </button>

    </form>
</li>
                    @else
                    <li><span class="dropdown-item py-2 text-muted disabled"><i class="bi bi-lock-fill me-2"></i> Restricted</span></li>
                    @endif
                    @if($folder->manageable)
                    <li>
                        <button class="dropdown-item py-2"
                                data-bs-toggle="modal"
                                data-bs-target="#accessModal{{ $folder->id }}">
                            <i class="bi bi-shield-lock me-2 text-secondary"></i> Manage access
                        </button>
                    </li>
                    @endif
                    @if($folder->accessible)
                    <li><hr class="dropdown-divider my-1"></li>
                    <li>
                        <form action="{{ route('folders.destroy', $folder->id) }}" method="POST"
                              data-confirm-title="Move this folder to Trash?"
                              data-confirm="“{{ $folder->name }}” and everything in it can be restored from Trash within {{ config('trash.retention_days') }} days."
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

            {{-- Folder Icon & Name --}}
            @if($folder->accessible)
            <a href="{{ route('folders.show', $folder->id) }}" style="text-decoration:none; color:inherit;">
                @include('Admin.partials.folder-icon', ['locked' => $folder->is_restricted])
                <div class="mt-2 text-truncate" style="font-size:13px; font-weight:500;">
                    {{ $folder->name }}
                </div>
            </a>
            @else
            <div style="cursor:not-allowed;" title="Restricted — you don't have access">
                @include('Admin.partials.folder-icon', ['locked' => true])
                <div class="mt-2 text-truncate" style="font-size:13px; font-weight:500;">
                    {{ $folder->name }}
                </div>
                <div class="text-muted" style="font-size:11px;">Restricted</div>
            </div>
            @endif

        </div>
    </div>
    @empty
    <div class="col-12 text-center py-5 text-muted">
        <i class="bi bi-folder-x" style="font-size:4rem; opacity:0.3;"></i>
        <p class="mt-3">No folders yet. Click the <strong>+</strong> button to create one.</p>
    </div>
    @endforelse
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

    {{-- Manage Access Modal (owner or SuperAdmin only) --}}
    @if($folder->manageable)
    <div class="modal fade" id="accessModal{{ $folder->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width:420px;">
            <div class="modal-content">
                <form action="{{ route('folders.updateAccess', $folder->id) }}" method="POST">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-shield-lock me-2"></i>Manage Access</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" role="switch"
                                   name="is_restricted" value="1" id="restrictSwitch{{ $folder->id }}"
                                   {{ $folder->is_restricted ? 'checked' : '' }}
                                   onchange="document.getElementById('accessList{{ $folder->id }}').style.display = this.checked ? 'block' : 'none';">
                            <label class="form-check-label" for="restrictSwitch{{ $folder->id }}">
                                Restrict this folder to specific officers
                            </label>
                        </div>

                        <div id="accessList{{ $folder->id }}" style="display:{{ $folder->is_restricted ? 'block' : 'none' }};">
                            <label class="form-label text-muted" style="font-size:13px;">
                                Who else can see this folder (besides you and SuperAdmin):
                            </label>
                            <div style="max-height:220px; overflow-y:auto;" class="border rounded p-2">
                                @forelse($officers as $officer)
                                    @if($officer->id !== $folder->created_by)
                                    <div class="form-check mb-1">
                                        <input class="form-check-input" type="checkbox"
                                               name="allowed_users[]" value="{{ $officer->id }}"
                                               id="allow{{ $folder->id }}_{{ $officer->id }}"
                                               {{ $folder->allowed_user_ids->contains($officer->id) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="allow{{ $folder->id }}_{{ $officer->id }}" style="font-size:13px;">
                                            {{ $officer->name }}
                                        </label>
                                    </div>
                                    @endif
                                @empty
                                    <p class="text-muted mb-0" style="font-size:13px;">No other officers yet.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" style="border-radius:8px;">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif
@endforeach



<br>






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

                    <div class="upload-zone" id="uploadZone" onclick="document.getElementById('fileInput').click()">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <div style="font-weight:500; color:#555; margin-bottom:4px;">Click to browse</div>
                        <div>or drag and drop files here</div>
                    </div>
                    <input type="file" id="fileInput" name="files[]" multiple hidden
                           accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.gif,.webp"
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

                    <div class="form-check mt-3">
                        <input type="checkbox" class="form-check-input" id="pendingApproval" name="pending_approval" value="1">
                        <label class="form-check-label" for="pendingApproval" style="font-size:13px;">
                            Still subject for approval <span class="text-muted">(label it as not yet official)</span>
                        </label>
                    </div>

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

{{-- UPLOAD PROGRESS TOAST (top right, survives the modal closing) --}}
<div id="uploadToast" style="display:none; position:fixed; top:20px; right:20px; width:320px; z-index:2000;">
    <div style="background:#fff; border:1px solid #e5e5e5; border-radius:12px; box-shadow:0 8px 30px rgba(0,0,0,0.15); overflow:hidden;">
        <div style="padding:10px 14px; border-bottom:1px solid #f0f0f0; font-weight:600; font-size:13px; display:flex; justify-content:space-between; align-items:center;">
            <span><i class="fas fa-cloud-upload-alt me-1" style="color:#058028;"></i> Uploading</span>
            <button type="button" id="uploadToastClose" style="border:none; background:none; color:#999; cursor:pointer; font-size:16px; line-height:1;" aria-label="Close">&times;</button>
        </div>
        <div id="uploadToastList" style="max-height:280px; overflow-y:auto;"></div>
    </div>
</div>


<style>
.upload-toast-row { padding: 10px 14px; border-bottom: 1px solid #f5f5f5; }
.upload-toast-row:last-child { border-bottom: none; }
.upload-toast-row .row-top { display:flex; justify-content:space-between; font-size:12px; color:#333; margin-bottom:4px; gap:8px; }
.upload-toast-row .row-name { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:200px; }
.upload-toast-row .row-status { color:#888; white-space:nowrap; flex-shrink:0; }
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
    border: none; background: none; color: #aaa; cursor: pointer;
    font-size: 11px; padding: 0; flex-shrink: 0; line-height: 1;
}
.upload-toast-row .row-cancel-btn:hover { color: #c0392b; }

[data-theme="dark"] #uploadToast > div { background: var(--card) !important; border-color: var(--border) !important; }
[data-theme="dark"] #uploadToast > div > div:first-child { border-bottom-color: var(--border) !important; color: var(--text-1); }
[data-theme="dark"] .upload-toast-row { border-bottom-color: var(--border); }
[data-theme="dark"] .upload-toast-row .row-top { color: var(--text-1); }
[data-theme="dark"] .upload-toast-row .row-track { background: var(--nav-hover-bg); }
</style>
<script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
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

// Search now lives in the topbar (partials/header-search.blade.php).

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
    const pendingCheckbox = form.querySelector('[name="pending_approval"]');

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
            if (pendingCheckbox && pendingCheckbox.checked) fd.append('pending_approval', '1');
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
                                pending_approval: (pendingCheckbox && pendingCheckbox.checked) ? 1 : 0,
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
