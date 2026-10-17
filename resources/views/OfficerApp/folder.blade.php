@extends('OfficerApp.layout', ['tab' => 'folders'])

@section('title', $folder->name . ' · QSU SG Officers')

@section('bar')
<header class="app-bar has-back">
    <a href="{{ $folder->is_archived && $folder->school_year ? route('app.year', $folder->school_year) : route('app.folders') }}" class="icon-btn" aria-label="Back to Folders">
        @include('OfficerApp.partials.icon', ['name' => 'back', 'size' => 22])
    </a>
    <div class="app-bar-text">
        <div class="app-bar-title">{{ $folder->name }}</div>
        <div class="app-bar-sub">{{ $files->count() }} {{ Str::plural('file', $files->count()) }}{{ $folder->is_archived ? ' · Read-only' : '' }}</div>
    </div>
</header>
@endsection

@section('content')

@if ($files->isEmpty())
    <div class="empty"><strong>This folder is empty</strong>{{ $folder->is_archived ? 'Nothing was stored in it.' : 'Tap Add file to put something in it.' }}</div>
@else
    <div class="card list">
        @foreach ($files as $f)
            @include('OfficerApp.partials.file-row', ['f' => $f])
        @endforeach
    </div>
@endif

@unless ($folder->is_archived)
    <a href="{{ route('app.folder.add', $folder->id) }}" class="fab">
        @include('OfficerApp.partials.icon', ['name' => 'plus', 'size' => 20, 'stroke' => 2])
        <span>Add file</span>
    </a>
@endunless

@endsection
