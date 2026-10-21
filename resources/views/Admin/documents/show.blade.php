@extends($layout ?? 'Admin.home')
@section('title', $document->title)
@section('content')

@include('Admin.partials.theme')

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

<style>
    * { box-sizing:border-box; }
    body { background:var(--surface); font-family:'Inter',sans-serif; color:var(--text-1); }

    .btn-flat { background:var(--primary-fill); color:#fff; border:none; border-radius:var(--radius-sm); padding:9px 16px; font-size:.84rem; font-weight:600; text-decoration:none; cursor:pointer; }
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

{{-- Skeleton placeholder, shown until the real content below is revealed --}}
<div class="skel-shell" aria-hidden="true">
    <div class="page-header">
        <div>
            <div class="skel skel-text" style="width:220px; height:22px; margin-bottom:8px;"></div>
            <div class="skel skel-text" style="width:260px;"></div>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <div class="skel" style="width:70px; height:36px; border-radius:8px;"></div>
            <div class="skel" style="width:70px; height:36px; border-radius:8px;"></div>
            <div class="skel" style="width:80px; height:36px; border-radius:8px;"></div>
        </div>
    </div>

    <div class="d-grid">
        <div class="panel">
            @for ($i = 0; $i < 7; $i++)
            <div class="skel skel-text" style="width:60px; height:10px; margin-top:12px;"></div>
            <div class="skel skel-text" style="width:80%; margin-top:6px;"></div>
            @endfor
        </div>
        <div class="panel" style="padding:12px;">
            <div class="skel" style="width:100%; height:80vh; border-radius:8px;"></div>
        </div>
    </div>
</div>

<div class="skel-real">

<div class="page-header">
    <div>
        <h1>{{ $document->title }}</h1>
        <p class="sub">Filed {{ optional($document->created_at)->format('M j, Y') }} by {{ $document->createdBy?->name ?? 'System' }}</p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a href="{{ route('documents.index') }}" class="btn-flat ghost">Back to documents</a>
        @if (! $document->is_archived && $document->isManageableBy(auth()->user()))
            <a href="{{ route('documents.edit', $document) }}" class="btn-flat"><i class="fas fa-pen me-1" aria-hidden="true"></i> Edit details</a>
            <form method="POST" action="{{ route('documents.destroy', $document) }}"
                  data-confirm-title="Move this document to Trash?"
                  data-confirm="The document and its PDF can be restored from Trash within {{ config('trash.retention_days') }} days."
                  data-confirm-ok="Move to Trash">
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
                @include('Admin.partials.approval-badge', ['file' => $document->file])
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

</div>

<script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>

@endsection
