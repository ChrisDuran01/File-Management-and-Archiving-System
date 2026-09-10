@extends($layout ?? 'Admin.home')
@section('content')

@include('Admin.partials.theme')

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

<style>
    * { box-sizing:border-box; }
    body { background:var(--surface); font-family:'Inter',sans-serif; color:var(--text-1); }

    .page-header h2 { font-size:1.35rem; font-weight:600; letter-spacing:-.3px; margin:0 0 4px; }
    .page-header .sub { font-size:.85rem; color:var(--text-2); margin:0 0 20px; }

    .card-panel {
        background:var(--card); border:1px solid var(--border); border-radius:var(--radius);
        box-shadow:var(--shadow-sm); padding:16px 18px; margin-bottom:18px;
    }
    .section-title { font-size:.78rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:var(--text-3); margin:0 0 4px; padding:10px 14px 0; }

    .trash-table { width:100%; border-collapse:collapse; font-size:.86rem; }
    .trash-table th { text-align:left; font-size:.7rem; text-transform:uppercase; letter-spacing:.05em; color:var(--text-3); font-weight:700; padding:11px 14px; border-bottom:1.5px solid var(--border); }
    .trash-table td { padding:11px 14px; border-bottom:1px solid var(--border); }
    .trash-table tr:last-child td { border-bottom:none; }
    .item-name { font-weight:600; }
    .item-meta { font-size:.76rem; color:var(--text-3); }

    .btn-flat { background:var(--brand); color:#fff; border:none; border-radius:var(--radius-sm); padding:7px 14px; font-size:.8rem; font-weight:600; cursor:pointer; text-decoration:none; display:inline-block; }
    .btn-flat.ghost { background:var(--card); color:var(--text-2); border:1.5px solid var(--border); }
    .btn-flat.danger { background:var(--danger-dim); color:var(--danger); border:1.5px solid #fecaca; }
    .btn-flat.danger-solid { background:var(--danger); color:#fff; }
    .actions form { display:inline; }

    .type-icon { width:32px; height:32px; border-radius:8px; display:inline-flex; align-items:center; justify-content:center; font-size:13px; margin-right:10px; }
    .type-icon.folder   { background:#fffbeb; color:#f59e0b; }
    .type-icon.file     { background:#e9f5ee; color:#058028; }
    .type-icon.document { background:#ede9fe; color:var(--brand); }

    .empty-state { text-align:center; color:var(--text-3); padding:48px 16px; }
    .empty-state i { font-size:2.2rem; display:block; margin-bottom:10px; opacity:.5; }

    /* ===== DARK MODE ===== */
    [data-theme="dark"] .type-icon.folder   { background:var(--warning-dim); color:var(--warning); }
    [data-theme="dark"] .type-icon.file     { background:var(--primary-dim); color:var(--primary); }
    [data-theme="dark"] .type-icon.document { background:#201f33; color:#a5a3e0; }
</style>

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
@if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        {{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@php $total = $folders->count() + $files->count() + $documents->count(); @endphp

<div class="page-header d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
        <h2><i class="fas fa-trash-alt me-2" style="color:var(--brand);font-size:1.1rem;"></i>Trash</h2>
        <p class="sub">
            Deleted items are kept here for {{ $retentionDays }} days, then removed permanently.
            {{ $total }} item(s) in the Trash.
        </p>
    </div>
    @if ($isSuperAdmin && $total > 0)
        <form method="POST" action="{{ route('trash.empty') }}"
              onsubmit="return confirm('Permanently delete ALL {{ $total }} item(s) in the Trash? This cannot be undone.')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn-flat danger-solid"><i class="fas fa-fire me-1"></i> Empty Trash</button>
        </form>
    @endif
</div>

@if ($total === 0)
    <div class="card-panel">
        <div class="empty-state">
            <i class="fas fa-trash-alt"></i>
            <p style="margin:0;">The Trash is empty.</p>
        </div>
    </div>
@else

    @if ($folders->count())
    <div class="card-panel" style="padding:6px 4px 4px;">
        <p class="section-title">Folders ({{ $folders->count() }}) — restoring a folder brings back everything inside it</p>
        <table class="trash-table">
            <thead>
                <tr><th>Name</th><th>School year</th><th>Deleted</th><th style="text-align:right;">Actions</th></tr>
            </thead>
            <tbody>
                @foreach ($folders as $folder)
                <tr>
                    <td>
                        <span class="type-icon folder"><i class="fas fa-folder"></i></span>
                        <span class="item-name">{{ $folder->name }}</span>
                    </td>
                    <td class="item-meta">{{ $folder->school_year ?? 'Current' }}</td>
                    <td class="item-meta">{{ $folder->deleted_at->diffForHumans() }}</td>
                    <td class="actions" style="text-align:right;">
                        <form method="POST" action="{{ route('trash.restore', ['folder', $folder->id]) }}">
                            @csrf
                            <button type="submit" class="btn-flat ghost"><i class="fas fa-undo me-1"></i> Restore</button>
                        </form>
                        @if ($isSuperAdmin)
                        <form method="POST" action="{{ route('trash.destroy', ['folder', $folder->id]) }}"
                              onsubmit="return confirm('Permanently delete folder \'{{ $folder->name }}\' and everything inside it? This cannot be undone.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-flat danger"><i class="fas fa-times me-1"></i> Delete forever</button>
                        </form>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    @if ($files->count())
    <div class="card-panel" style="padding:6px 4px 4px;">
        <p class="section-title">Files ({{ $files->count() }})</p>
        <table class="trash-table">
            <thead>
                <tr><th>Name</th><th>Folder</th><th>Deleted</th><th style="text-align:right;">Actions</th></tr>
            </thead>
            <tbody>
                @foreach ($files as $file)
                <tr>
                    <td>
                        <span class="type-icon file"><i class="fas fa-file"></i></span>
                        <span class="item-name">{{ $file->filename }}</span>
                    </td>
                    <td class="item-meta">{{ $file->folder->name ?? 'Root' }}</td>
                    <td class="item-meta">{{ $file->deleted_at->diffForHumans() }}</td>
                    <td class="actions" style="text-align:right;">
                        <form method="POST" action="{{ route('trash.restore', ['file', $file->id]) }}">
                            @csrf
                            <button type="submit" class="btn-flat ghost"><i class="fas fa-undo me-1"></i> Restore</button>
                        </form>
                        @if ($isSuperAdmin)
                        <form method="POST" action="{{ route('trash.destroy', ['file', $file->id]) }}"
                              onsubmit="return confirm('Permanently delete file \'{{ $file->filename }}\'? This cannot be undone.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-flat danger"><i class="fas fa-times me-1"></i> Delete forever</button>
                        </form>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    @if ($documents->count())
    <div class="card-panel" style="padding:6px 4px 4px;">
        <p class="section-title">Documents ({{ $documents->count() }}) — restoring a document brings back its PDF file too</p>
        <table class="trash-table">
            <thead>
                <tr><th>Title</th><th>Type</th><th>Folder</th><th>Deleted</th><th style="text-align:right;">Actions</th></tr>
            </thead>
            <tbody>
                @foreach ($documents as $document)
                <tr>
                    <td>
                        <span class="type-icon document"><i class="fas fa-file-lines"></i></span>
                        <span class="item-name">{{ $document->title }}</span>
                    </td>
                    <td class="item-meta">{{ $document->document_type ?? '—' }}</td>
                    <td class="item-meta">{{ $document->folder->name ?? '—' }}</td>
                    <td class="item-meta">{{ $document->deleted_at->diffForHumans() }}</td>
                    <td class="actions" style="text-align:right;">
                        <form method="POST" action="{{ route('trash.restore', ['document', $document->id]) }}">
                            @csrf
                            <button type="submit" class="btn-flat ghost"><i class="fas fa-undo me-1"></i> Restore</button>
                        </form>
                        @if ($isSuperAdmin)
                        <form method="POST" action="{{ route('trash.destroy', ['document', $document->id]) }}"
                              onsubmit="return confirm('Permanently delete document \'{{ $document->title }}\' and its PDF? This cannot be undone.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-flat danger"><i class="fas fa-times me-1"></i> Delete forever</button>
                        </form>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

@endif

@endsection
