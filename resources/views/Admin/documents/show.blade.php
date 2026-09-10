@extends($layout ?? 'Admin.home')
@section('content')

@include('Admin.partials.theme')

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

<style>
    * { box-sizing:border-box; }
    body { background:var(--surface); font-family:'Inter',sans-serif; color:var(--text-1); }

    .d-head { display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px; margin-bottom:16px; }
    .d-head h2 { font-size:1.3rem; font-weight:600; margin:0 0 3px; }
    .d-head .sub { font-size:.8rem; color:var(--text-3); margin:0; }
    .btn-flat { background:var(--brand); color:#fff; border:none; border-radius:var(--radius-sm); padding:9px 16px; font-size:.84rem; font-weight:600; text-decoration:none; cursor:pointer; }
    .btn-flat.ghost { background:var(--card); color:var(--text-2); border:1.5px solid var(--border); }
    .btn-flat.danger { background:var(--card); color:#b91c1c; border:1.5px solid #f3c9c9; }

    .d-grid { display:grid; grid-template-columns:340px 1fr; gap:20px; align-items:start; }
    @media (max-width:900px) { .d-grid { grid-template-columns:1fr; } }

    .panel { background:var(--card); border:1px solid var(--border); border-radius:var(--radius); box-shadow:var(--shadow-sm); padding:18px 20px; }
    .meta dl { margin:0; }
    .meta dt { font-size:.68rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:var(--text-3); margin-top:12px; }
    .meta dt:first-child { margin-top:0; }
    .meta dd { margin:2px 0 0; font-size:.9rem; }
    .prov { margin-top:16px; padding-top:12px; border-top:1px solid var(--border); font-size:.78rem; color:var(--text-3); }
    .pdf-frame { width:100%; height:80vh; border:1px solid var(--border); border-radius:var(--radius-sm); background:#fff; }
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

<div class="d-head">
    <div>
        <h2>{{ $document->title }}</h2>
        <p class="sub">Filed {{ optional($document->created_at)->format('M j, Y') }} by {{ $document->createdBy?->name ?? 'System' }}</p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a href="{{ route('documents.index') }}" class="btn-flat ghost">Back</a>
        @if (! $document->is_archived && $document->isManageableBy(auth()->user()))
            <a href="{{ route('documents.edit', $document) }}" class="btn-flat"><i class="fas fa-pen me-1"></i> Edit</a>
            <form method="POST" action="{{ route('documents.destroy', $document) }}"
                  onsubmit="return confirm('Delete this document and its PDF? This cannot be undone.');">
                @csrf @method('DELETE')
                <button type="submit" class="btn-flat danger"><i class="fas fa-trash me-1"></i> Delete</button>
            </form>
        @endif
    </div>
</div>

<div class="d-grid">
    <div class="panel meta">
        <dl>
            <dt>Type</dt><dd>{{ $document->document_type ?: '—' }}</dd>
            <dt>Reference no.</dt><dd>{{ $document->reference_no ?: '—' }}</dd>
            <dt>Document date</dt><dd>{{ optional($document->document_date)->format('F j, Y') ?: '—' }}</dd>
            <dt>Date received</dt><dd>{{ optional($document->date_received)->format('F j, Y') ?: '—' }}</dd>
            <dt>From / sender</dt><dd>{{ $document->sender ?: '—' }}</dd>
            <dt>To / recipient</dt><dd>{{ $document->recipient ?: '—' }}</dd>
            <dt>Status</dt><dd>{{ str_replace('_', ' ', $document->status) }}</dd>
            <dt>Folder</dt><dd><a href="{{ route('folders.show', $document->folder_id) }}">{{ $document->folder?->name ?: '—' }}</a></dd>
            @if ($document->notes)
                <dt>Notes</dt><dd style="white-space:pre-wrap;">{{ $document->notes }}</dd>
            @endif
        </dl>
        <div class="prov">
            @if ($document->scan_batch_id)
                Split from scan batch #{{ $document->scan_batch_id }}, pages {{ $document->start_page }}–{{ $document->end_page }}.
            @endif
            @if ($document->file)
                <br>File: {{ $document->file->filename }}
                <a href="{{ route('files.download', $document->file_id) }}">download</a>
            @endif
        </div>
    </div>

    <div class="panel" style="padding:12px;">
        @if ($signedUrl)
            <iframe src="{{ $signedUrl }}" class="pdf-frame" title="{{ $document->title }}"></iframe>
        @else
            <p style="color:var(--text-3);padding:40px;text-align:center;">The PDF for this document isn't available right now.</p>
        @endif
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

@endsection
