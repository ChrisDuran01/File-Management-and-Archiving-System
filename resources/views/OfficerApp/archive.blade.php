@extends('OfficerApp.layout', ['tab' => 'search'])

@section('title', $archive->folder_name . ' · QSU SG Officers')

@section('bar')
<header class="app-bar has-back">
    <a href="{{ route('app.search') }}" class="icon-btn" aria-label="Back to Search">@include('OfficerApp.partials.icon', ['name' => 'back', 'size' => 22])</a>
    <div class="app-bar-text">
        <div class="app-bar-title">{{ $archive->folder_name }}</div>
        <div class="app-bar-sub">Archived {{ $archive->archived_at?->format('M j, Y') }} · View only</div>
    </div>
</header>
@endsection

@section('content')

@if (empty($filenames))
    <div class="empty"><strong>No files recorded for this archive</strong>It was made before the system listed what is inside.</div>
@else
    <div class="card list">
        @foreach ($filenames as $filename)
            @php $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION)); @endphp
            <a href="{{ route('app.archive.file', ['id' => $archive->id, 'filename' => $filename]) }}" class="row">
                <span class="chip {{ \App\Http\Controllers\OfficerAppController::toneOf($ext) }}">{{ $ext ?: 'file' }}</span>
                <span class="row-text">
                    <span class="row-name">{{ $filename }}</span>
                </span>
            </a>
        @endforeach
    </div>
@endif

@endsection
