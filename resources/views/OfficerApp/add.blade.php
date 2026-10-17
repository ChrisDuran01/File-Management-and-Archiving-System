@extends('OfficerApp.layout')

@section('title', 'Add a file · QSU SG Officers')

@section('bar')
<header class="app-bar has-back">
    <a href="{{ route('app.folder', $folder->id) }}" class="icon-btn" aria-label="Back to {{ $folder->name }}">
        @include('OfficerApp.partials.icon', ['name' => 'back', 'size' => 22])
    </a>
    <div class="app-bar-text">
        <div class="app-bar-title">Add a file</div>
        <div class="app-bar-sub">To {{ $folder->name }}</div>
    </div>
</header>
@endsection

@section('content')

@if ($errors->any())
    <div class="notice red" role="alert">
        <div class="notice-text">{{ $errors->first() }}</div>
    </div>
@endif

{{-- The same upload the website does (FileController::store), posted from
     two inputs: one opens the camera, the other the phone's file picker.
     app.js lists what was chosen and drives the Upload button. --}}
<form id="addForm" method="POST" action="{{ route('app.folder.store', $folder->id) }}" enctype="multipart/form-data" data-add-form style="display: contents">
    @csrf

    <input type="file" name="files[]" accept="image/*" capture="environment" data-camera hidden>
    <input type="file" name="files[]" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.gif,.webp" data-picker hidden>

    <div class="grid-2">
        <button type="button" class="choice" data-take>
            <span class="chip folder">@include('OfficerApp.partials.icon', ['name' => 'camera', 'size' => 22])</span>
            <span>
                <span class="choice-title">Take a photo</span>
                <span class="choice-sub">Of a paper document</span>
            </span>
        </button>
        <button type="button" class="choice" data-choose>
            <span class="chip folder">@include('OfficerApp.partials.icon', ['name' => 'file', 'size' => 22])</span>
            <span>
                <span class="choice-title">Choose a file</span>
                <span class="choice-sub">PDF, Office or image</span>
            </span>
        </button>
    </div>

    <section class="section" data-chosen-section hidden>
        <div class="section-head">
            <h2 class="section-title">Ready to upload</h2>
            <button type="button" class="section-link" data-clear>Clear</button>
        </div>
        <div class="card list" data-chosen></div>
    </section>

    <div class="card list">
        <label class="toggle-row">
            <span>
                <span class="check-title">Scan as document</span>
                <span class="check-sub">Turn a photo of a paper document into a searchable PDF.</span>
            </span>
            <input type="checkbox" class="switch" name="scan_as_document" value="1">
        </label>
        <label class="toggle-row">
            <span>
                <span class="check-title">Still subject for approval</span>
                <span class="check-sub">Label it as not yet official.</span>
            </span>
            <input type="checkbox" class="switch" name="pending_approval" value="1">
        </label>
    </div>
</form>

@endsection

@section('footer')
    <button type="submit" form="addForm" class="btn primary block" data-upload disabled><span>Upload</span></button>
@endsection
