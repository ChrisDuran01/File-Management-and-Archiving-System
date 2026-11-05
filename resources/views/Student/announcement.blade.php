{{-- One announcement on its own page (StudentController::announcement): the
     address students share and search engines list. Same look as the student
     page, which shows the same post as a card and a pop-up. --}}
@extends('Student.home')

@section('content')
@php
    $logo = $siteSettings->logo_path ? asset('storage/'.$siteSettings->logo_path) : asset('images/SG-logo.png');
    $home = route('student.dashboard');
    $photos = $announcement->images;
@endphp

<a href="#main" class="skip-link">Skip to content</a>

<header class="site-header">
    <div class="wrap header-inner">
        <a href="{{ $home }}" class="brand">
            <img src="{{ $logo }}" alt="" class="brand-logo" width="42" height="42">
            <span class="brand-text">
                <strong>QSU Student Government</strong>
                <small>Diffun Campus</small>
            </span>
        </a>
        @include('Student.partials.theme-toggle')
    </div>
</header>

<main id="main" tabindex="-1">
    <div class="wrap article-wrap">
        <a href="{{ $home }}#announcements" class="back-link"><i class="bi bi-arrow-left" aria-hidden="true"></i> All announcements</a>

        <article>
            <p class="eyebrow">Announcement</p>
            <h1 class="article-title">{{ $announcement->title }}</h1>
            <p class="article-meta">
                <time datetime="{{ $announcement->created_at->toIso8601String() }}">{{ $announcement->created_at->format('F j, Y') }}</time>
                @if($announcement->created_at->diffInHours(now()) < 72)<span class="chip">New</span>@endif
            </p>

            @if($photos->isNotEmpty())
                <div class="article-photos count-{{ min($photos->count(), 3) }}">
                    @foreach($photos as $photo)
                        @php $size = @getimagesize(Storage::disk('public')->path($photo->path)); @endphp
                        <a href="{{ route('announcements.image', $photo->id) }}" class="article-photo" target="_blank" rel="noopener">
                            <img src="{{ route('announcements.image', $photo->id) }}"
                                 alt="Photo {{ $loop->iteration }} of {{ $photos->count() }} for {{ $announcement->title }}"
                                 @if($size) width="{{ $size[0] }}" height="{{ $size[1] }}" @endif
                                 @unless($loop->first) loading="lazy" @endunless decoding="async">
                        </a>
                    @endforeach
                </div>
            @endif

            <div class="an-body">{!! $announcement->body !!}</div>

            <div class="article-actions">
                <a href="{{ $seo['url'] }}" class="btn btn-primary" data-share data-title="{{ $announcement->title }}">
                    <i class="bi bi-share" aria-hidden="true"></i> <span data-share-label aria-live="polite">Share</span>
                </a>
                @if($announcement->attachment_path)
                    <a href="{{ route('announcements.download', $announcement->id) }}" class="btn btn-soft" data-attachment>
                        <i class="bi bi-paperclip" aria-hidden="true"></i>
                        <span class="attach-name">{{ $announcement->attachment_original_name ?? 'Attachment' }}</span>
                    </a>
                @endif
            </div>
        </article>
    </div>
</main>

<footer class="site-footer">
    <div class="wrap footer-row">
        <span>&copy; {{ date('Y') }} QSU Student Government · Diffun Campus</span>
        <a href="{{ route('privacy.notice') }}">Privacy notice</a>
    </div>
</footer>

@include('Student.partials.article-styles')

<script>
(function () {
    'use strict';

    // Anonymous usage statistics, as on the student page: one visit, and
    // this announcement counted as opened. Nothing at all if the browser
    // asks not to be tracked.
    if (! (navigator.globalPrivacyControl === true || navigator.doNotTrack === '1' || window.doNotTrack === '1')) {
        const today = new Date().toLocaleDateString('en-CA');
        let lastDay = null, before = false;
        try {
            lastDay = localStorage.getItem('sg_visit_day');
            before = localStorage.getItem('sg_visited') === '1';
            localStorage.setItem('sg_visit_day', today);
            localStorage.setItem('sg_visited', '1');
        } catch (e) { /* private mode: still counted, just not as returning */ }

        const endpoint = @json(route('engagement.track'));
        const send = events => {
            const body = JSON.stringify({ events });
            if (! (navigator.sendBeacon && navigator.sendBeacon(endpoint, body))) {
                fetch(endpoint, { method: 'POST', body, keepalive: true }).catch(() => {});
            }
        };
        send([
            { type: 'visit', first_today: lastDay !== today, first_ever: ! before },
            { type: 'announcement_open', id: {{ $announcement->id }} },
        ]);

        const attach = document.querySelector('[data-attachment]');
        if (attach) attach.addEventListener('click', () => send([{ type: 'attachment_download', id: {{ $announcement->id }} }]));
    }
})();
</script>
@include('Student.partials.share-script')
@endsection
