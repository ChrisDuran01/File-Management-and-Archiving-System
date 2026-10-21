{{--
    Home is kept to three things: search, the folders, and what was opened
    last. The account (sign out, install) has its own screen behind the
    initial in the top bar; offline files have their own tab.
--}}
@extends('OfficerApp.layout', ['tab' => 'home'])

@section('title', 'QSU SG Officers')

@section('bar')
<header class="app-bar">
    <a href="{{ route('app.account') }}" class="avatar" aria-label="Your account">{{ Str::upper(Str::substr(Auth::user()->name, 0, 1)) }}</a>
    <div class="app-bar-text" style="margin-left: 4px">
        <div class="app-bar-kicker">Signed in as</div>
        <div class="app-bar-title">{{ Str::before(Auth::user()->name, ' ') }}</div>
    </div>
    <a href="{{ route('app.notifications') }}" class="icon-btn raised" aria-label="Notifications{{ $unread ? ', ' . $unread . ' unread' : '' }}">
        @include('OfficerApp.partials.icon', ['name' => 'bell', 'size' => 22])
        @if ($unread) <span class="dot"></span> @endif
    </a>
</header>
@endsection

@section('content')

{{-- One prompt at most, and only when it applies - app.js shows it.
     Phones that can't tell Wi-Fi from mobile data are asked once about
     automatic saving; otherwise a phone that can install the app is
     offered it. --}}
<div class="notice" data-auto-ask hidden>
    <div class="notice-title">Save files on this phone automatically?</div>
    <div class="notice-text">This school year's files will open without a connection. It can use mobile data when you are not on Wi-Fi.</div>
    <div class="notice-actions">
        <button type="button" class="btn primary small" data-auto-yes>Save automatically</button>
        <button type="button" class="btn text small" data-auto-no>Not now</button>
    </div>
</div>

<div class="notice" data-install hidden>
    <div class="notice-title">Install this app on your phone</div>
    <div class="notice-text" data-install-ios hidden>Tap the Share button, then “Add to Home Screen”.</div>
    <div class="notice-actions">
        <button type="button" class="btn primary small" data-install-btn hidden>Install</button>
        <button type="button" class="btn text small" data-install-dismiss>Not now</button>
    </div>
</div>

<form method="GET" action="{{ route('app.search') }}" role="search">
    <label class="search-field">
        @include('OfficerApp.partials.icon', ['name' => 'search'])
        <span class="sr-only">Search</span>
        <input type="search" name="query" placeholder="Search files and folders" enterkeyhint="search">
    </label>
</form>

<section class="section">
    <div class="section-head">
        <h2 class="section-title">Folders</h2>
        <a href="{{ route('app.folders') }}" class="section-link">See all</a>
    </div>

    @if ($folders->isEmpty())
        <div class="card empty"><strong>No folders yet</strong>Folders are created on the website.</div>
    @else
        <div class="scroller">
            @foreach ($folders as $folder)
                @if ($folder->accessible)
                    <a href="{{ route('app.folder', $folder->id) }}" class="card folder-card">
                        <span class="folder-card-top">
                            <span class="chip folder">@include('OfficerApp.partials.icon', ['name' => 'folder'])</span>
                            @if ($folder->is_restricted)
                                @include('OfficerApp.partials.icon', ['name' => 'lock', 'size' => 18])
                            @endif
                        </span>
                        <span>
                            <span class="folder-card-name">{{ $folder->name }}</span>
                            <span class="folder-card-meta">{{ $folder->file_count }} {{ Str::plural('file', $folder->file_count) }}</span>
                        </span>
                    </a>
                @else
                    <div class="card folder-card is-locked">
                        <span class="folder-card-top">
                            <span class="chip">@include('OfficerApp.partials.icon', ['name' => 'folder'])</span>
                            @include('OfficerApp.partials.icon', ['name' => 'lock', 'size' => 18])
                        </span>
                        <span>
                            <span class="folder-card-name">{{ $folder->name }}</span>
                            <span class="folder-card-meta">Restricted · No access</span>
                        </span>
                    </div>
                @endif
            @endforeach
        </div>
    @endif
</section>

@if ($recent->isNotEmpty())
<section class="section">
    <h2 class="section-title">Recently opened</h2>
    <div class="card list">
        @foreach ($recent as $f)
            @include('OfficerApp.partials.file-row', ['f' => $f, 'meta' => $f['folder']])
        @endforeach
    </div>
</section>
@endif

@endsection
