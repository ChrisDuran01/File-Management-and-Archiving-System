@extends('OfficerApp.layout', ['tab' => 'search'])

@section('title', 'Search · QSU SG Officers')

@section('bar')
<header class="app-bar">
    <div class="app-bar-text">
        <div class="app-bar-title">Search</div>
    </div>
</header>
@endsection

@section('content')

<form method="GET" action="{{ route('app.search') }}" role="search">
    <label class="search-field">
        @include('OfficerApp.partials.icon', ['name' => 'search'])
        <span class="sr-only">Search</span>
        <input type="search" name="query" value="{{ $query }}" placeholder="A name, or words inside a document" enterkeyhint="search" @if ($query === '') autofocus @endif>
    </label>
</form>

@php $total = $folders->count() + $files->count() + $archives->count() + $archivedFiles->count(); @endphp

@if ($query === '')
    <div class="empty"><strong>Search everything you can open</strong>A file or folder name, a reference number, or words that appear inside a document. Archives are included.</div>
@elseif ($total === 0)
    <div class="empty"><strong>Nothing found for “{{ $query }}”</strong>Try a different word.</div>
@endif

@if ($folders->isNotEmpty())
<section class="section">
    <h2 class="section-title">Folders</h2>
    <div class="card list">
        @foreach ($folders as $folder)
            <a href="{{ route('app.folder', $folder->id) }}" class="row">
                <span class="chip folder">@include('OfficerApp.partials.icon', ['name' => 'folder'])</span>
                <span class="row-text">
                    <span class="row-name">{{ $folder->name }}</span>
                    @if ($folder->is_archived && $folder->school_year)
                        <span class="row-meta">School year {{ $folder->school_year }}</span>
                    @endif
                </span>
                <span class="row-end">@include('OfficerApp.partials.icon', ['name' => 'chevron', 'size' => 18])</span>
            </a>
        @endforeach
    </div>
</section>
@endif

@if ($files->isNotEmpty())
<section class="section">
    <h2 class="section-title">Files</h2>
    <div class="card list">
        @foreach ($files as $f)
            @include('OfficerApp.partials.file-row', ['f' => $f, 'meta' => $f['folder'] . ($f['why'] ? ' · ' . $f['why'] : '')])
        @endforeach
    </div>
</section>
@endif

@if ($archivedFiles->isNotEmpty())
<section class="section">
    <h2 class="section-title">Files in archived folders</h2>
    <div class="card list">
        @foreach ($archivedFiles as $hit)
            @php
                $ext = strtolower(pathinfo($hit->filename, PATHINFO_EXTENSION));
                $why = match (true) {
                    (bool) $hit->matched_in_content     => 'Found in document text',
                    (bool) $hit->matched_in_document    => 'Matched document details',
                    (bool) $hit->matched_in_school_year => 'Matched school year',
                    default                             => null,
                };
                $highlighted = ! empty($hit->ocr_snippet)
                    ? preg_replace('/(' . preg_quote(e($query), '/') . ')/i', '<mark>$1</mark>', e($hit->ocr_snippet))
                    : null;
            @endphp
            <a href="{{ route('app.archive.file', ['id' => $hit->archive_id, 'filename' => $hit->filename]) }}" class="row">
                <span class="chip {{ \App\Http\Controllers\OfficerAppController::toneOf($ext) }}">{{ $ext ?: 'file' }}</span>
                <span class="row-text">
                    <span class="row-name">{{ $hit->filename }}</span>
                    <span class="row-meta">{{ $hit->archive->folder_name }}{{ $why ? ' · ' . $why : '' }}</span>
                    @if ($highlighted)
                        <span class="row-snippet">{!! $highlighted !!}</span>
                    @endif
                </span>
            </a>
        @endforeach
    </div>
</section>
@endif

@if ($archives->isNotEmpty())
<section class="section">
    <h2 class="section-title">Archived folders</h2>
    <div class="card list">
        @foreach ($archives as $archive)
            <a href="{{ route('app.archive', $archive->id) }}" class="row">
                <span class="chip">@include('OfficerApp.partials.icon', ['name' => 'archive'])</span>
                <span class="row-text">
                    <span class="row-name">{{ $archive->folder_name }}</span>
                    @if ($archive->school_year)
                        <span class="row-meta">School year {{ $archive->school_year }}</span>
                    @endif
                </span>
                <span class="row-end">@include('OfficerApp.partials.icon', ['name' => 'chevron', 'size' => 18])</span>
            </a>
        @endforeach
    </div>
</section>
@endif

@endsection
