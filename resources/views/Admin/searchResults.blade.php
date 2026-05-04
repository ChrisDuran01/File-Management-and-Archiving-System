@extends('Admin.home')
@section('content')

<style>
    @import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap');

    :root {
        --surface:     #f7f8fc;
        --card:        #ffffff;
        --border:      #e8eaf0;
        --primary:     #2563eb;
        --primary-dim: #eff4ff;
        --warning:     #f59e0b;
        --warning-dim: #fffbeb;
        --text-1:      #111827;
        --text-2:      #6b7280;
        --text-3:      #9ca3af;
        --shadow-sm:   0 1px 3px rgba(0,0,0,.06), 0 1px 2px rgba(0,0,0,.04);
        --shadow-md:   0 4px 16px rgba(0,0,0,.08);
        --shadow-lg:   0 8px 32px rgba(0,0,0,.14);
        --radius:      12px;
        --radius-sm:   8px;
    }

    * { box-sizing: border-box; }
    body { background: var(--surface); font-family: 'DM Sans', sans-serif; color: var(--text-1); }

    /* ── Page header ─────────────────────────────── */
    .page-header { margin-bottom: 6px; }
    .page-header h2 {
        font-size: 1.35rem;
        font-weight: 600;
        letter-spacing: -.3px;
        margin: 0 0 4px;
    }
    .page-header .sub {
        font-size: .85rem;
        color: var(--text-2);
        margin: 0;
    }
    .page-header .sub strong { color: var(--text-1); font-weight: 600; }

    /* ── Stats chips ─────────────────────────────── */
    .stats-bar {
        display: flex;
        gap: 10px;
        margin: 16px 0 24px;
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
    .stat-chip i { font-size: .75rem; color: var(--text-3); }

    /* ── Section label ────────────────────────────── */
    .section-label {
        font-size: .72rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .07em;
        color: var(--text-3);
        margin-bottom: 12px;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .section-label::after {
        content: '';
        flex: 1;
        height: 1px;
        background: var(--border);
    }

    /* ── Grid ─────────────────────────────────────── */
    .results-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 12px;
    }

    /* ── Result card ──────────────────────────────── */
    .result-card {
        background: var(--card);
        border: 1.5px solid var(--border);
        border-radius: var(--radius);
        padding: 20px 14px 16px;
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        text-decoration: none;
        color: var(--text-1);
        box-shadow: var(--shadow-sm);
        transition: transform .18s cubic-bezier(.34,1.56,.64,1), box-shadow .18s ease, border-color .18s;
        cursor: pointer;
        position: relative;
        overflow: hidden;
    }
    .result-card::before {
        content: '';
        position: absolute;
        inset: 0;
        opacity: 0;
        transition: opacity .18s;
        border-radius: calc(var(--radius) - 1px);
    }
    .result-card.folder::before { background: var(--warning-dim); }
    .result-card.file::before   { background: var(--primary-dim); }
    .result-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-lg); }
    .result-card:hover::before { opacity: 1; }
    .result-card.folder:hover { border-color: var(--warning); }
    .result-card.file:hover   { border-color: var(--primary); }

    /* Icon wrapper */
    .card-icon {
        width: 56px;
        height: 56px;
        border-radius: var(--radius);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.6rem;
        margin-bottom: 10px;
        position: relative;
        z-index: 1;
        flex-shrink: 0;
    }
    .card-icon.folder { background: var(--warning-dim); color: var(--warning); }
    .card-icon.pdf    { background: #fef2f2; color: #ef4444; }
    .card-icon.docx   { background: #eff6ff; color: #3b82f6; }
    .card-icon.xlsx   { background: #f0fdf4; color: #22c55e; }
    .card-icon.img    { background: #fdf4ff; color: #a855f7; }
    .card-icon.file   { background: var(--primary-dim); color: var(--primary); }

    .card-name {
        font-size: .82rem;
        font-weight: 500;
        color: var(--text-1);
        word-break: break-word;
        line-height: 1.35;
        max-width: 100%;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        position: relative;
        z-index: 1;
    }
    .card-ext {
        font-family: 'DM Mono', monospace;
        font-size: .68rem;
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 4px;
        padding: 1px 5px;
        color: var(--text-3);
        text-transform: uppercase;
        margin-top: 6px;
        position: relative;
        z-index: 1;
    }

    /* ── Empty state ──────────────────────────────── */
    .empty-state {
        text-align: center;
        padding: 64px 24px;
        background: var(--card);
        border: 1.5px dashed var(--border);
        border-radius: var(--radius);
        color: var(--text-3);
    }
    .empty-state i { font-size: 2.5rem; margin-bottom: 12px; display: block; }
    .empty-state p { margin: 0; font-size: .9rem; }
    .empty-state strong { color: var(--text-2); }
</style>

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

@php
    $results = collect()
        ->merge($folders->map(fn($f) => ['type' => 'folder', 'data' => $f]))
        ->merge($files->map(fn($f)   => ['type' => 'file',   'data' => $f]));

    $folderItems = $results->where('type', 'folder');
    $fileItems   = $results->where('type', 'file');
@endphp
{{-- ── Back Button ─────────────────────────────────── --}}

    <a href="javascript:history.back()" class="back-link">
        <i class="fas fa-arrow-left"></i> Back
    </a>
    <br>
    <br>

{{-- ── Header ──────────────────────────────────────────── --}}
<div class="page-header">
    <h2><i class="fas fa-search me-2" style="color:var(--primary);font-size:1.1rem;"></i>Search Results</h2>
    <p class="sub">Results for <strong>"{{ $query }}"</strong></p>
</div>



{{-- ── Stats ───────────────────────────────────────────── --}}
<div class="stats-bar">
    <span class="stat-chip"><i class="fas fa-layer-group"></i>{{ $results->count() }} {{ Str::plural('result', $results->count()) }}</span>
    @if($folderItems->count())
    <span class="stat-chip"><i class="fas fa-folder"></i>{{ $folderItems->count() }} {{ Str::plural('folder', $folderItems->count()) }}</span>
    @endif
    @if($fileItems->count())
    <span class="stat-chip"><i class="fas fa-file"></i>{{ $fileItems->count() }} {{ Str::plural('file', $fileItems->count()) }}</span>
    @endif
</div>

@if($results->isEmpty())

    {{-- ── Empty state ─────────────────────────────────── --}}
    <div class="empty-state">
        <i class="fas fa-search"></i>
        <p>No results found for <strong>"{{ $query }}"</strong>.<br>Try a different keyword.</p>
    </div>

@else

    {{-- ── Folders section ─────────────────────────────── --}}
    @if($folderItems->count())
    <div class="section-label"><i class="fas fa-folder"></i> Folders</div>
    <div class="results-grid mb-4">
        @foreach($folderItems as $item)
        <a href="{{ route('folders.show', $item['data']->id) }}" class="result-card folder">
            <div class="card-icon folder"><i class="fas fa-folder"></i></div>
            <span class="card-name">{{ $item['data']->name }}</span>
        </a>
        @endforeach
    </div>
    @endif

    {{-- ── Files section ────────────────────────────────── --}}
@if($fileItems->count())
<div class="section-label"><i class="fas fa-file-alt"></i> Files</div>

<div class="results-grid">
    @foreach($fileItems as $item)

    @php
        $file = $item['data'];

        $ext = strtolower(pathinfo($file->filename, PATHINFO_EXTENSION));
        $name = pathinfo($file->filename, PATHINFO_FILENAME);

        $iconClass = match(true) {
            $ext === 'pdf' => 'pdf',
            in_array($ext, ['doc','docx']) => 'docx',
            in_array($ext, ['xls','xlsx','csv']) => 'xlsx',
            in_array($ext, ['jpg','jpeg','png','gif','webp','svg']) => 'img',
            default => 'file',
        };

        $iconName = match($iconClass) {
            'pdf'  => 'fa-file-pdf',
            'docx' => 'fa-file-word',
            'xlsx' => 'fa-file-excel',
            'img'  => 'fa-file-image',
            default => 'fa-file-alt',
        };
    @endphp

    <a href="{{ route('files.preview', $file->id) }}" class="result-card file">

        <div class="card-icon {{ $iconClass }}">
            <i class="fas {{ $iconName }}"></i>
        </div>

        <span class="card-name">{{ $name }}</span>
        <span class="card-ext">{{ $ext }}</span>

        {{-- ✅ Folder display --}}
        <small style="color:#888; display:block; margin-top:4px;">
            📁 {{ $file->folder->name ?? 'Root' }}
        </small>

    </a>

    @endforeach
</div>
@endif

@endif

@endsection