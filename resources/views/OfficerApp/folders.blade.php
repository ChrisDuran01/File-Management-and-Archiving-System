@extends('OfficerApp.layout', ['tab' => 'folders'])

@section('title', ($year ? 'School year ' . $year : 'Folders') . ' · QSU SG Officers')

@section('bar')
@if ($year)
<header class="app-bar has-back">
    <a href="{{ route('app.folders') }}" class="icon-btn" aria-label="Back to Folders">@include('OfficerApp.partials.icon', ['name' => 'back', 'size' => 22])</a>
    <div class="app-bar-text">
        <div class="app-bar-title">School year {{ $year }}</div>
        <div class="app-bar-sub">Read-only</div>
    </div>
</header>
@else
<header class="app-bar">
    <div class="app-bar-text">
        <div class="app-bar-title">Folders</div>
    </div>
</header>
@endif
@endsection

@section('content')

@if ($folders->isEmpty() && $rootFiles->isEmpty())
    <div class="empty"><strong>No folders here</strong>{{ $year ? 'Nothing was stored under this school year.' : 'Folders are created on the website.' }}</div>
@endif

@if ($folders->isNotEmpty())
<div class="card list">
    @foreach ($folders as $folder)
        @include('OfficerApp.partials.folder-row', ['folder' => $folder])
    @endforeach
</div>
@endif

@if ($rootFiles->isNotEmpty())
<section class="section">
    <h2 class="section-title">Files not in a folder</h2>
    <div class="card list">
        @foreach ($rootFiles as $f)
            @include('OfficerApp.partials.file-row', ['f' => $f])
        @endforeach
    </div>
</section>
@endif

@if ($pastYears->isNotEmpty())
<section class="section">
    <h2 class="section-title">Previous school years</h2>
    <div class="card list">
        @foreach ($pastYears as $past)
            <a href="{{ route('app.year', $past) }}" class="row">
                <span class="chip">@include('OfficerApp.partials.icon', ['name' => 'calendar'])</span>
                <span class="row-text">
                    <span class="row-name">School year {{ $past }}</span>
                </span>
                <span class="row-end">@include('OfficerApp.partials.icon', ['name' => 'chevron', 'size' => 18])</span>
            </a>
        @endforeach
    </div>
</section>
@endif

@endsection
