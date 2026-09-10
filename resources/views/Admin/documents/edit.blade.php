@extends($layout ?? 'Admin.home')
@section('content')

@include('Admin.partials.theme')

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

<style>
    * { box-sizing:border-box; }
    body { background:var(--surface); font-family:'Inter',sans-serif; color:var(--text-1); }

    .page-header h2 { font-size:1.3rem; font-weight:600; margin:0 0 16px; }
    .panel { background:var(--card); border:1px solid var(--border); border-radius:var(--radius); box-shadow:var(--shadow-sm); padding:20px 22px; max-width:720px; }
    .fields { display:grid; grid-template-columns:1fr 1fr; gap:14px 16px; }
    .fields .full { grid-column:1 / -1; }
    .fields label { display:block; font-size:.74rem; font-weight:600; color:var(--text-2); margin-bottom:5px; }
    .fields input, .fields select, .fields textarea {
        width:100%; border:1.5px solid var(--border); border-radius:var(--radius-sm); padding:9px 11px; font-size:.87rem; font-family:inherit;
    }
    .fields input:focus, .fields select:focus, .fields textarea:focus { border-color:var(--primary); box-shadow:0 0 0 3px var(--primary-dim); outline:none; }
    .fields textarea { resize:vertical; min-height:70px; }
    .actions { margin-top:18px; display:flex; gap:10px; }
    .btn-flat { background:var(--brand); color:#fff; border:none; border-radius:var(--radius-sm); padding:10px 20px; font-size:.87rem; font-weight:600; cursor:pointer; text-decoration:none; }
    .btn-flat.ghost { background:var(--card); color:var(--text-2); border:1.5px solid var(--border); }
</style>

@if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        {{ $errors->first() }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="page-header"><h2><i class="fas fa-pen me-2" style="color:var(--brand);"></i>Edit document</h2></div>

<div class="panel">
    <form method="POST" action="{{ route('documents.update', $document) }}">
        @csrf @method('PUT')
        <div class="fields">
            <div class="full"><label>Title / subject *</label><input name="title" value="{{ old('title', $document->title) }}" required></div>
            <div><label>Document type</label>
                <select name="document_type">
                    <option value="">—</option>
                    @foreach ($documentTypes as $t)
                        <option value="{{ $t }}" @selected(old('document_type', $document->document_type) === $t)>{{ $t }}</option>
                    @endforeach
                </select>
            </div>
            <div><label>Reference no.</label><input name="reference_no" value="{{ old('reference_no', $document->reference_no) }}"></div>
            <div><label>Document date</label><input type="date" name="document_date" value="{{ old('document_date', optional($document->document_date)->format('Y-m-d')) }}"></div>
            <div><label>Date received *</label><input type="date" name="date_received" value="{{ old('date_received', optional($document->date_received)->format('Y-m-d')) }}" required></div>
            <div><label>From / sender</label><input name="sender" value="{{ old('sender', $document->sender) }}"></div>
            <div><label>To / recipient</label><input name="recipient" value="{{ old('recipient', $document->recipient) }}"></div>
            <div><label>Status</label>
                <select name="status">
                    @foreach ($statuses as $s)
                        <option value="{{ $s }}" @selected(old('status', $document->status) === $s)>{{ str_replace('_', ' ', $s) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="full"><label>Notes</label><textarea name="notes">{{ old('notes', $document->notes) }}</textarea></div>
        </div>
        <div class="actions">
            <button type="submit" class="btn-flat">Save changes</button>
            <a href="{{ route('documents.show', $document) }}" class="btn-flat ghost">Cancel</a>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

@endsection
