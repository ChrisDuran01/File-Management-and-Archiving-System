{{-- Who is signed in, and the few things that are about the app itself rather than the files. --}}
@extends('OfficerApp.layout', ['tab' => 'home'])

@section('title', 'Your account · QSU SG Officers')

@section('bar')
<header class="app-bar has-back">
    <a href="{{ route('app.home') }}" class="icon-btn" aria-label="Back to Home">@include('OfficerApp.partials.icon', ['name' => 'back', 'size' => 22])</a>
    <div class="app-bar-text">
        <div class="app-bar-title">Your account</div>
    </div>
</header>
@endsection

@section('content')

<div class="card profile">
    <span class="avatar large">{{ Str::upper(Str::substr($user->name, 0, 1)) }}</span>
    <div>
        <div class="profile-name">{{ $user->name }}</div>
        <div class="profile-sub">{{ $position ? $position . ' · ' : '' }}{{ $user->email }}</div>
    </div>
</div>

<div class="card list">
    <a href="{{ route('app.offline') }}" class="row">
        <span class="row-text">
            <span class="row-name">Offline files</span>
            <span class="row-meta" data-offline-count>Saved on this phone</span>
        </span>
        <span class="row-end">@include('OfficerApp.partials.icon', ['name' => 'chevron', 'size' => 18])</span>
    </a>

    {{-- Shown by app.js when this phone can install the app and has not yet. --}}
    <div class="row" data-install data-install-always hidden>
        <span class="row-text">
            <span class="row-name">Install this app</span>
            <span class="row-meta" data-install-ios hidden>Tap the Share button, then “Add to Home Screen”.</span>
        </span>
        <button type="button" class="btn primary small" data-install-btn hidden>Install</button>
    </div>
</div>

<form method="POST" action="{{ route('logout') }}" data-signout>
    @csrf
    <input type="hidden" name="from_app" value="1">
    <button type="submit" class="btn block">Sign out</button>
</form>

<p class="muted-line" style="justify-content: center; text-align: center">Managing officers, reports, backups and archives is done on the website, on a computer.</p>

@endsection
