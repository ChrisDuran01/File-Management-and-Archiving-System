@extends($layout ?? 'Admin.home')
@section('title', 'Import Activity Proposal')
@section('content')

@include('Admin.partials.theme')

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

@include('Admin.proposals.partials.styles')

<div class="skel-real">

@include('Admin.partials.back-link', ['backTo' => route('proposals.index'), 'backLabel' => 'Activity Proposals'])

<div class="page-header">
    <div>
        <h1>Import a proposal from Word</h1>
        <p class="sub">For a Detailed Activity Proposal written in Word before the system, e.g. last year's.</p>
    </div>
</div>

<div class="card-panel prop-form" style="max-width:720px;">
    <ol class="import-steps">
        <li>Choose the proposal's Word file (.docx).</li>
        <li>The system reads the Title, Theme, Date, Proponent, parts I to V, the budget table and the signatories, and fills in a new proposal.</li>
        <li>Check every part. Budget rows whose Total Price doesn't equal Quantity × Unit Price are marked.</li>
        <li>Click Save proposal. Nothing is saved before that, and the Word file itself is not kept.</li>
    </ol>

    <form method="POST" action="{{ route('proposals.import.read') }}" enctype="multipart/form-data">
        @csrf
        <div class="prop-fields">
            <div class="full">
                <label for="importFile">Word file (.docx)</label>
                <input type="file" id="importFile" name="file" accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                       class="prop-input @error('file') is-invalid @enderror" required>
                @error('file') <div class="field-error">{{ $message }}</div> @enderror
            </div>
        </div>
        <div class="form-actions" style="margin:16px 0 0;">
            <a href="{{ route('proposals.index') }}" class="btn-flat ghost">Cancel</a>
            <button type="submit" class="btn-flat"><i class="fas fa-file-import me-1" aria-hidden="true"></i> Read the file</button>
        </div>
    </form>

    <p class="item-meta" style="margin:12px 0 0;">For a .doc file, open it in Word and use Save As → Word Document (.docx). A scanned paper proposal can't be read yet.</p>
</div>

</div>

@endsection
