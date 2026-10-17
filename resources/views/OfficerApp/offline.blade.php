{{--
    The one screen that works with no connection. The service worker keeps
    a copy of this page and shows it in place of any screen it cannot load,
    so nothing here may depend on the server: the list is drawn by app.js
    from what the phone has saved.
--}}
@extends('OfficerApp.layout', ['tab' => 'offline'])

@section('title', 'Offline files · QSU SG Officers')

@section('bar')
<header class="app-bar">
    <div class="app-bar-text">
        <div class="app-bar-title">Offline files</div>
        <div class="app-bar-sub" data-offline-subtitle>Saved on this phone</div>
    </div>
</header>
@endsection

@section('content')
<div data-offline-screen style="display: contents">

    <div class="notice amber" role="status" data-offline-banner hidden>
        <div class="notice-title">You are offline</div>
        <div class="notice-text">The files below still open. Everything else needs a connection.</div>
    </div>

    <div class="notice amber" role="status" data-offline-expired hidden>
        <div class="notice-title">Saved files were removed</div>
        <div class="notice-text">This phone had not connected to the system for {{ config('officer_app.offline.expire_days') }} days. Sign in while online to save them again.</div>
    </div>

    <div class="notice" data-needs-secure hidden>
        <div class="notice-title">Offline files are not available here</div>
        <div class="notice-text">Keeping files on the phone needs a secure (https) connection to the system.</div>
    </div>

    <div class="card list" data-offline-list>
        <div class="empty">Loading…</div>
    </div>

    <section class="card list">
        <label class="toggle-row">
            <span>
                <span class="check-title">Save files automatically</span>
                <span class="check-sub">While the app is open, on Wi-Fi.</span>
            </span>
            <input type="checkbox" class="switch" data-auto-toggle>
        </label>

        <div class="muted-line padded">
            <span data-offline-status>Not checked yet.</span>
            <button type="button" class="btn text small" data-sync-now>Check now</button>
        </div>

        <details class="more padded">
            <summary>What gets saved?</summary>
            <p>This school year's files are saved automatically. Files in restricted folders, from previous school years, or larger than {{ config('officer_app.offline.auto_max_file_mb') }} MB are not: open one and tap Keep offline.</p>
            <p>Saved copies are removed when you sign out, when you no longer have access to a file, or after {{ config('officer_app.offline.expire_days') }} days without connecting.</p>
        </details>
    </section>

</div>
@endsection
