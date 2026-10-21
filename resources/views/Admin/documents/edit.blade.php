@extends($layout ?? 'Admin.home')
@section('title', 'Edit document')
@section('content')

@include('Admin.partials.theme')

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

<style>
    * { box-sizing:border-box; }
    body { background:var(--surface); font-family:'Inter',sans-serif; color:var(--text-1); }

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
    .btn-flat { background:var(--primary-fill); color:#fff; border:none; border-radius:var(--radius-sm); padding:10px 20px; font-size:.87rem; font-weight:600; cursor:pointer; text-decoration:none; }
    .btn-flat.ghost { background:var(--card); color:var(--text-2); border:1.5px solid var(--border); }
</style>

{{-- Skeleton placeholder, shown until the real content below is revealed --}}
<div class="skel-shell" aria-hidden="true">
    <div class="skel skel-text" style="width:150px; margin-bottom:1rem;"></div>

    <div class="page-header"><div class="skel skel-text" style="width:200px; height:22px;"></div></div>

    <div class="panel">
        <div class="fields">
            <div class="full">
                <div class="skel skel-text" style="width:120px; margin-bottom:5px;"></div>
                <div class="skel" style="width:100%; height:38px; border-radius:6px;"></div>
            </div>
            @for ($i = 0; $i < 6; $i++)
            <div>
                <div class="skel skel-text" style="width:100px; margin-bottom:5px;"></div>
                <div class="skel" style="width:100%; height:38px; border-radius:6px;"></div>
            </div>
            @endfor
            <div class="full">
                <div class="skel skel-text" style="width:70px; margin-bottom:5px;"></div>
                <div class="skel" style="width:100%; height:70px; border-radius:6px;"></div>
            </div>
        </div>
        <div style="margin-top:18px; display:flex; gap:10px;">
            <div class="skel" style="width:130px; height:40px; border-radius:6px;"></div>
            <div class="skel" style="width:90px; height:40px; border-radius:6px;"></div>
        </div>
    </div>
</div>

<div class="skel-real">

@include('Admin.partials.back-link', ['backTo' => route('documents.show', $document), 'backLabel' => 'Document'])

<div class="page-header"><h1><i class="fas fa-pen me-2" style="color:var(--brand);"></i>Edit document</h1></div>

<div class="panel">
    <form method="POST" action="{{ route('documents.update', $document) }}">
        @csrf @method('PUT')
        <div class="fields">
            <div class="full"><label for="docTitle">Title / subject *</label><input name="title" id="docTitle" value="{{ old('title', $document->title) }}" required></div>
            <div><label for="docTypeSel">Document type</label>
                <select name="document_type" id="docTypeSel">
                    <option value="">—</option>
                    @foreach ($documentTypes as $t)
                        <option value="{{ $t }}" @selected(old('document_type', $document->document_type) === $t)>{{ $t }}</option>
                    @endforeach
                </select>
            </div>
            <div><label for="docRef">Reference no.</label><input name="reference_no" id="docRef" value="{{ old('reference_no', $document->reference_no) }}"></div>
            <div><label for="docDate">Document date</label><input type="date" name="document_date" id="docDate" value="{{ old('document_date', optional($document->document_date)->format('Y-m-d')) }}"></div>
            <div><label for="docReceived">Date received *</label><input type="date" name="date_received" id="docReceived" value="{{ old('date_received', optional($document->date_received)->format('Y-m-d')) }}" required></div>
            <div><label for="docSender">From / sender</label><input name="sender" id="docSender" value="{{ old('sender', $document->sender) }}"></div>
            <div><label for="docRecipient">To / recipient</label><input name="recipient" id="docRecipient" value="{{ old('recipient', $document->recipient) }}"></div>
            <div><label for="docStatus">Status</label>
                <select name="status" id="docStatus">
                    @foreach ($statuses as $s)
                        <option value="{{ $s }}" @selected(old('status', $document->status) === $s)>{{ str_replace('_', ' ', $s) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="full"><label for="docNotes">Notes</label><textarea name="notes" id="docNotes">{{ old('notes', $document->notes) }}</textarea></div>
        </div>
        <div class="actions">
            <button type="submit" class="btn-flat">Save changes</button>
            <a href="{{ route('documents.show', $document) }}" class="btn-flat ghost">Cancel</a>
        </div>
    </form>
</div>

</div>

<script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>

@endsection
