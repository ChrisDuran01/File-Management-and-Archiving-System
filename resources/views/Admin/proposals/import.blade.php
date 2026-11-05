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
        <h1>Import a proposal from a file</h1>
        <p class="sub">For a Detailed Activity Proposal made before the system, e.g. last year's: its Word file, a PDF, or a scan or photo of the paper.</p>
    </div>
</div>

<div class="card-panel prop-form" style="max-width:720px;">
    <ol class="import-steps">
        <li>Choose the proposal's Word file (.docx), PDF, or a photo of the paper (JPG or PNG).</li>
        <li>The system reads the Title, Theme, Date, Proponent, parts I to V, the schedule and budget tables and the signatories, and fills in a new proposal.</li>
        <li>Check every part. Budget rows whose Total Price doesn't equal Quantity × Unit Price are marked.</li>
        <li>Click Save proposal. Nothing is saved before that, and the file itself is not kept.</li>
    </ol>

    {{-- Reading a scan takes a while: the button says so and can't be pressed twice. --}}
    <form method="POST" action="{{ route('proposals.import.read') }}" enctype="multipart/form-data"
          onsubmit="const b = this.querySelector('button[type=submit]'); b.disabled = true; b.textContent = 'Reading the file…';">
        @csrf
        <div class="prop-fields">
            <div class="full">
                <label for="importFile">Word file, PDF or photo</label>
                <input type="file" id="importFile" name="file"
                       accept=".docx,.pdf,.jpg,.jpeg,.png,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/pdf,image/jpeg,image/png"
                       class="prop-input @error('file') is-invalid @enderror" required>
                @error('file') <div class="field-error">{{ $message }}</div> @enderror
            </div>
        </div>
        <div class="form-actions" style="margin:16px 0 0;">
            <a href="{{ route('proposals.index') }}" class="btn-flat ghost">Cancel</a>
            <button type="submit" class="btn-flat"><i class="fas fa-file-import me-1" aria-hidden="true"></i> Read the file</button>
        </div>
    </form>

    <ul class="item-meta" style="margin:14px 0 0; padding-left:18px;">
        <li>A Word file is read best. A PDF saved from Word comes next.</li>
        <li>A scan or photo is read letter by letter and can take up to a minute. Take it straight on, in good light, with the whole page in view, and check the numbers it fills in.</li>
        <li>A proposal on several pages: scan them into one PDF (most phone scanner apps can), or import the page with the budget.</li>
        <li>For a .doc file, open it in Word and use Save As → Word Document (.docx).</li>
    </ul>
</div>

</div>

@endsection
