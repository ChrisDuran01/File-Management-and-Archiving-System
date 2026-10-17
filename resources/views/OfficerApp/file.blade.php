{{--
    One file: a live one ($file, with $meta from describe()) or one sealed
    inside an archive ($archived set, $file null - view only, as on the
    website). app.js draws the preview into [data-viewer] from $meta.
--}}
@extends('OfficerApp.layout')

@section('title', $meta['name'] . ' · QSU SG Officers')

@section('bar')
<header class="app-bar has-back">
    @if ($archived)
        <a href="{{ route('app.archive', $archived->id) }}" class="icon-btn" aria-label="Back to {{ $archived->folder_name }}">
    @elseif ($file->folder_id)
        <a href="{{ route('app.folder', $file->folder_id) }}" class="icon-btn" aria-label="Back to {{ $meta['folder'] }}">
    @else
        <a href="{{ route('app.folders') }}" class="icon-btn" aria-label="Back to Folders">
    @endif
        @include('OfficerApp.partials.icon', ['name' => 'back', 'size' => 22])
    </a>
    <div class="app-bar-text">
        <div class="app-bar-title">{{ $meta['name'] }}</div>
        <div class="app-bar-sub">{{ $meta['folder'] }}{{ $archived ? ' · Archived' : '' }}</div>
    </div>
    @unless ($archived)
        <button type="button" class="icon-btn" data-share-open aria-label="Share" aria-haspopup="dialog">
            @include('OfficerApp.partials.icon', ['name' => 'share', 'size' => 22])
        </button>
    @endunless
</header>
@endsection

@section('content')

@if ($file && $file->is_pending_approval)
    <div class="notice amber" role="status">
        <div class="notice-title">Subject for approval</div>
        <div class="notice-text">This file has not been approved yet, so it is not official.</div>
    </div>
@endif

<div class="section">
    <div class="viewer" data-viewer data-file="{{ json_encode($meta) }}">
        <div class="viewer-status"><strong>Opening…</strong></div>
    </div>
    <button type="button" class="btn text" data-open-elsewhere data-file="{{ json_encode($meta) }}">Send or open in another app</button>
</div>

<dl class="card details">
    @if ($archived)
        @if ($archived->school_year)
            <div><dt>School year</dt><dd>{{ $archived->school_year }}</dd></div>
        @endif
        <div><dt>Archived</dt><dd>{{ $archived->archived_at?->format('M j, Y') }}</dd></div>
    @else
        @if ($file->school_year)
            <div><dt>School year</dt><dd>{{ $file->school_year }}</dd></div>
        @endif
        <div><dt>Uploaded</dt><dd>{{ $meta['date'] }}{{ $file->officerTerm?->user ? ' by ' . $file->officerTerm->user->name : '' }}</dd></div>
        <div><dt>Size</dt><dd>{{ $meta['size_label'] }}</dd></div>
    @endif
</dl>

@unless ($archived)
{{-- Share sheet (app.js wireShare). A link: ShareLinkController, same as
     the website's Share dialog. The file: the phone's own share menu. --}}
<div class="sheet-scrim" data-share-sheet hidden>
    <div class="sheet" role="dialog" aria-modal="true" aria-labelledby="share-title"
         data-links="{{ route('share-links.index', $file->id) }}" data-file="{{ json_encode($meta) }}">
        <div class="sheet-head">
            <h2 class="sheet-title" id="share-title">Share</h2>
            <button type="button" class="icon-btn" data-share-close aria-label="Close">@include('OfficerApp.partials.icon', ['name' => 'close', 'size' => 22])</button>
        </div>

        <div class="notice green" role="status" data-share-status hidden><div class="notice-text"></div></div>

        <div data-share-choose>
            <div class="card list">
                <div class="share-option">
                    <button type="button" class="row" data-share-link @disabled(! ($canShare ?? false))>
                        <span class="chip folder">@include('OfficerApp.partials.icon', ['name' => 'link'])</span>
                        <span class="row-text">
                            <span class="row-name">Send a link</span>
                            <span class="row-meta">Opens without signing in. You can turn it off.</span>
                        </span>
                    </button>
                    @if ($canShare ?? false)
                        <div class="choice-chips" role="group" aria-label="Link works for">
                            @foreach (\App\Models\FileShareLink::DURATIONS as $days)
                                <button type="button" class="choice-chip" data-share-days="{{ $days }}" aria-pressed="{{ $days === \App\Models\FileShareLink::DEFAULT_DURATION ? 'true' : 'false' }}">{{ $days === 1 ? '1 day' : $days . ' days' }}</button>
                            @endforeach
                        </div>
                    @else
                        <p class="sheet-note">This file is in a restricted folder. Only the folder's creator or the Adviser/President can make a link for it.</p>
                    @endif
                </div>
                <button type="button" class="row" data-share-copy-file>
                    <span class="chip">@include('OfficerApp.partials.icon', ['name' => 'file'])</span>
                    <span class="row-text">
                        <span class="row-name">Send the file</span>
                        <span class="row-meta">A copy. It can't be taken back.</span>
                    </span>
                </button>
            </div>
        </div>

        <div class="section" data-share-made hidden>
            <div class="section-title">Your new link</div>
            <div class="card link-box" data-share-url></div>
            <div class="btn-row">
                <button type="button" class="btn primary" data-share-send>Share link</button>
                <button type="button" class="btn" data-share-copy>Copy</button>
            </div>
        </div>

        <div class="section" data-share-existing hidden>
            <div class="section-title">Links that work now</div>
            <div class="card list" data-share-list></div>
        </div>
    </div>
</div>
@endunless

@endsection

@section('footer')
    @if ($archived)
        <p class="note">Files inside an archive are view only.</p>
    @else
        <p class="note" data-needs-secure hidden>Keeping files offline needs a secure (https) connection.</p>
        <div class="btn-row">
            <button type="button" class="btn primary" data-keep data-file="{{ json_encode($meta) }}" aria-pressed="false">
                @include('OfficerApp.partials.icon', ['name' => 'check', 'stroke' => 2, 'attrs' => 'data-icon-on hidden'])
                <span data-keep-label>Keep offline</span>
            </button>
            <a href="{{ route('files.download', $file->id) }}" class="btn">Download</a>
        </div>
    @endif
@endsection
