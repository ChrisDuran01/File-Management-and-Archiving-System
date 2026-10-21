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

<style>
    /* The student page's look (Student/stuDashboard.blade.php), cut down to
       what one announcement needs. Same tokens, so the two read as one site. */
    :root {
        --green: #0f3d1f;
        --green-2: #145a2c;
        --green-3: #1d7a3e;
        --green-dark: #0a2a15;
        --gold: #f4b400;
        --green-soft: #e7f3ea;
        --green-light: #9ad9ab;
        --ink: #10231a;
        --text: #34443b;
        --muted: #617067;
        --faint: #647269;
        --tint: #f2f7f3;
        --line: #e0e8e2;
        --line-2: #cdd8d0;
        --head: 'Poppins', 'Inter', system-ui, sans-serif;
        --radius: 16px;
        --radius-control: 4px;  /* same small corners as the student page */
        --radius-tag: 3px;
        --header-h: 72px;
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html { -webkit-text-size-adjust: 100%; }
    body {
        display: flex; flex-direction: column; min-height: 100vh; min-height: 100dvh;
        background: #fff; color: var(--text);
        font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
        font-size: 16px; line-height: 1.6; -webkit-font-smoothing: antialiased; overflow-x: clip;
    }
    img { max-width: 100%; display: block; }
    a { color: inherit; }
    a, button { -webkit-tap-highlight-color: transparent; touch-action: manipulation; }
    :focus-visible { outline: 3px solid var(--green-3); outline-offset: 2px; }
    .site-footer :focus-visible { outline-color: var(--gold); }
    main { flex: 1; }
    main:focus { outline: none; }

    .skip-link {
        position: fixed; top: 12px; left: 12px; z-index: 300; padding: 10px 18px; border-radius: var(--radius-control);
        background: var(--green); color: #fff; font-weight: 600; text-decoration: none;
        transform: translateY(-200%); transition: transform .2s;
    }
    .skip-link:focus { transform: none; }

    .wrap {
        width: 100%; max-width: 1180px; margin: 0 auto;
        padding-left: max(20px, env(safe-area-inset-left));
        padding-right: max(20px, env(safe-area-inset-right));
    }

    .site-header {
        position: sticky; top: 0; z-index: 100; height: var(--header-h);
        background: rgba(255, 255, 255, .97); box-shadow: 0 1px 0 var(--line);
    }
    .header-inner { height: 100%; display: flex; align-items: center; }
    .brand { display: inline-flex; align-items: center; gap: 12px; min-width: 0; text-decoration: none; }
    .brand-logo { width: 42px; height: 42px; flex-shrink: 0; border-radius: 50%; object-fit: cover; background: #fff; box-shadow: 0 0 0 2px var(--green-soft); }
    .brand-text { display: flex; flex-direction: column; min-width: 0; line-height: 1.2; }
    .brand-text strong { color: var(--green); font-family: var(--head); font-size: 1rem; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .brand-text small { color: var(--muted); font-size: .7rem; font-weight: 600; letter-spacing: .12em; text-transform: uppercase; }

    .article-wrap { max-width: 780px; padding-top: 28px; padding-bottom: 72px; }
    .back-link {
        display: inline-flex; align-items: center; gap: 8px; min-height: 44px; margin-bottom: 18px;
        color: var(--green-3); font-size: .92rem; font-weight: 600; text-decoration: none;
    }
    .back-link:hover { color: var(--green); }
    .back-link .bi { transition: transform .15s; }
    .back-link:hover .bi { transform: translateX(-3px); }

    .eyebrow {
        display: inline-flex; align-items: center; gap: 10px;
        color: var(--green-3); font-size: .78rem; font-weight: 700; letter-spacing: .14em; text-transform: uppercase;
    }
    .eyebrow::before { content: ''; width: 26px; height: 3px; border-radius: 3px; background: var(--gold); }
    .article-title {
        margin-top: 10px; color: var(--ink); font-family: var(--head); font-size: clamp(1.6rem, 4.5vw, 2.3rem);
        font-weight: 700; letter-spacing: -.015em; line-height: 1.2; overflow-wrap: anywhere;
    }
    .article-meta { display: flex; align-items: center; flex-wrap: wrap; gap: 10px; margin-top: 12px; color: var(--faint); font-size: .9rem; }
    .chip {
        display: inline-flex; align-items: center; padding: 2px 10px; border-radius: var(--radius-tag);
        background: var(--gold); color: var(--ink); font-size: .68rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase;
    }

    /* One photo full width; two side by side; three or more: the first wide, the rest in pairs. */
    .article-photos { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 26px; }
    .article-photos.count-1 { grid-template-columns: 1fr; }
    .article-photos.count-3 .article-photo:first-child { grid-column: 1 / -1; }
    .article-photo { display: block; overflow: hidden; border-radius: var(--radius); background: var(--tint); }
    .article-photo img { width: 100%; height: 100%; aspect-ratio: 4 / 3; object-fit: cover; transition: transform .25s; }
    .article-photos.count-1 img, .article-photos.count-3 .article-photo:first-child img { aspect-ratio: auto; max-height: 70vh; object-fit: contain; }
    .article-photo:hover img { transform: scale(1.02); }

    .an-body { margin-top: 26px; color: var(--text); font-size: 1.05rem; line-height: 1.75; overflow-wrap: anywhere; }
    .an-body :is(p, ul, ol, blockquote, h1, h2, h3) + :is(p, ul, ol, blockquote, h1, h2, h3) { margin-top: .8em; }
    .an-body :is(ul, ol) { padding-left: 1.3em; }
    .an-body blockquote { padding-left: 14px; border-left: 3px solid var(--green-soft); font-style: italic; }
    .an-body :is(h1, h2, h3) { color: var(--ink); font-size: 1.1em; font-weight: 700; line-height: 1.35; }
    .an-body a { color: var(--green-3); text-underline-offset: 3px; }
    .an-body :is(img, video, iframe) { max-width: 100%; height: auto; }
    .an-body table { display: block; max-width: 100%; overflow-x: auto; }

    .article-actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 32px; padding-top: 24px; border-top: 1px solid var(--line); }
    .btn {
        display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-width: 0; max-width: 100%;
        min-height: 48px; padding: 0 24px; border: 2px solid transparent; border-radius: var(--radius-control);
        font-size: .95rem; font-weight: 600; text-decoration: none; transition: background-color .15s, border-color .15s;
    }
    .btn-primary { background: var(--green); color: #fff; }
    .btn-primary:hover { background: var(--green-2); }
    .btn-soft { background: #fff; border-color: var(--line-2); color: var(--green); }
    .btn-soft:hover { border-color: var(--green-3); background: var(--tint); }
    .attach-name { min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    @media (max-width: 520px) {
        .article-actions .btn { flex: 1 1 100%; }
    }

    .site-footer { background: var(--green-dark); color: rgba(255, 255, 255, .6); font-size: .84rem; }
    .footer-row {
        display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px 20px;
        padding-top: 22px; padding-bottom: max(22px, env(safe-area-inset-bottom));
    }
    .footer-row a { display: inline-flex; align-items: center; min-height: 44px; text-decoration: none; }
    .footer-row a:hover { color: #fff; }

    @media (prefers-reduced-motion: reduce) {
        *, *::before, *::after { transition: none !important; }
    }
</style>

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
