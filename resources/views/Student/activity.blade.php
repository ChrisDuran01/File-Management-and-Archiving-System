{{-- One approved activity on its own page (StudentController::activity):
     what it is for and where its budget goes, per category. Same look as an
     announcement's page. Never the names, signatures or signed copy. --}}
@extends('Student.home')

@section('content')
@php
    $logo = $siteSettings->logo_path ? asset('storage/'.$siteSettings->logo_path) : asset('images/SG-logo.png');
    $home = route('student.dashboard');
    $peso = fn ($amount) => \App\Models\ActivityProposal::peso((float) $amount);
    $paragraphs = fn (?string $text) => array_values(array_filter(array_map('trim', preg_split('/\R+/', (string) $text))));
    $tag = $activity->studentTag();
    $total = $activity->budgetTotal();
    $byCategory = $activity->budgetByCategory();
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
        <a href="{{ $home }}#activities" class="back-link"><i class="bi bi-arrow-left" aria-hidden="true"></i> All activities</a>

        <article>
            <p class="eyebrow">Activity</p>
            <h1 class="article-title">{{ $activity->title }}</h1>
            <p class="article-meta">
                <span>{{ $activity->dateLabel() }}</span>
                @if($tag)
                    <span class="chip {{ ['Done' => 'chip-done', 'Cancelled' => 'chip-off'][$tag] ?? '' }}">{{ $tag }}</span>
                @endif
                @if($activity->school_year)<span>SY {{ str_replace('-', '–', $activity->school_year) }}</span>@endif
            </p>
            @if($activity->theme)
                <p class="act-theme"><span>Theme</span> {{ $activity->theme }}</p>
            @endif

            @if($activity->objectives_intro || ! empty($activity->objectives))
                <h2 class="article-h2">Objectives</h2>
                <div class="an-body act-text">
                    @foreach($paragraphs($activity->objectives_intro) as $paragraph)
                        <p>{{ $paragraph }}</p>
                    @endforeach
                    @if(! empty($activity->objectives))
                        <ol>
                            @foreach($activity->objectives as $objective)
                                <li>{{ $objective }}</li>
                            @endforeach
                        </ol>
                    @endif
                </div>
            @endif

            <h2 class="article-h2">Budget</h2>
            <div class="budget-total">
                <span>Total budget</span>
                <b>{{ $peso($total) }}</b>
            </div>
            @if($byCategory)
                <table class="budget-table">
                    <caption class="sr-only">Budget per category</caption>
                    <thead>
                        <tr><th scope="col">Category</th><th scope="col" class="num">Amount</th></tr>
                    </thead>
                    <tbody>
                        @foreach($byCategory as $category => $amount)
                            <tr>
                                <th scope="row">
                                    {{ $category }}
                                    <span class="budget-bar" aria-hidden="true"><span style="width: {{ $total > 0 ? max(2, round($amount / $total * 100)) : 0 }}%"></span></span>
                                </th>
                                <td class="num">
                                    {{ $peso($amount) }}
                                    @if($total > 0)<small>{{ round($amount / $total * 100) }}%</small>@endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
            <p class="budget-note">The approved budget in the activity's proposal, not the amount actually spent.</p>

            <div class="article-actions">
                <a href="{{ $seo['url'] }}" class="btn btn-primary" data-share data-title="{{ $activity->title }}">
                    <i class="bi bi-share" aria-hidden="true"></i> <span data-share-label aria-live="polite">Share</span>
                </a>
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

<style>
    .sr-only { position: absolute; width: 1px; height: 1px; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; }
    .chip-done { background: var(--green-soft); color: var(--green-ink); }
    .chip-off { background: var(--tint); color: var(--text); }

    .act-theme { margin-top: 14px; color: var(--text); font-size: 1.05rem; font-style: italic; overflow-wrap: anywhere; }
    .act-theme span {
        margin-right: 6px; color: var(--green-ink-2); font-size: .75rem; font-style: normal; font-weight: 700;
        letter-spacing: .12em; text-transform: uppercase;
    }
    .article-h2 { margin-top: 36px; color: var(--ink); font-family: var(--head); font-size: 1.3rem; font-weight: 700; line-height: 1.3; }
    .act-text { margin-top: 12px; }

    .budget-total {
        display: flex; flex-wrap: wrap; align-items: baseline; justify-content: space-between; gap: 4px 16px;
        margin-top: 14px; padding: 18px 20px; border-radius: 14px; background: var(--green-soft);
    }
    .budget-total span { color: var(--green-ink); font-size: .9rem; font-weight: 600; }
    .budget-total b { color: var(--green-ink); font-family: var(--head); font-size: 1.6rem; font-weight: 700; }

    .budget-table { width: 100%; margin-top: 10px; border-collapse: collapse; }
    .budget-table thead th {
        padding: 10px 4px; border-bottom: 1px solid var(--line-2); color: var(--faint);
        font-size: .75rem; font-weight: 700; letter-spacing: .08em; text-align: left; text-transform: uppercase;
    }
    .budget-table tbody th, .budget-table td { padding: 12px 4px; border-bottom: 1px solid var(--line); vertical-align: top; }
    .budget-table tbody th { color: var(--ink); font-weight: 600; text-align: left; overflow-wrap: anywhere; }
    .budget-table .num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; color: var(--ink); }
    .budget-table .num small { display: block; color: var(--faint); font-size: .78rem; }
    .budget-bar { display: block; height: 6px; margin-top: 8px; border-radius: 3px; background: var(--tint); overflow: hidden; }
    .budget-bar span { display: block; height: 100%; border-radius: 3px; background: var(--green-3); }
    .budget-note { margin-top: 12px; color: var(--faint); font-size: .86rem; }
</style>

<script>
(function () {
    'use strict';

    // Anonymous usage statistics, as on the student page: one visit.
    // Nothing at all if the browser asks not to be tracked.
    if (! (navigator.globalPrivacyControl === true || navigator.doNotTrack === '1' || window.doNotTrack === '1')) {
        const today = new Date().toLocaleDateString('en-CA');
        let lastDay = null, before = false;
        try {
            lastDay = localStorage.getItem('sg_visit_day');
            before = localStorage.getItem('sg_visited') === '1';
            localStorage.setItem('sg_visit_day', today);
            localStorage.setItem('sg_visited', '1');
        } catch (e) { /* private mode: still counted, just not as returning */ }

        const body = JSON.stringify({ events: [{ type: 'visit', first_today: lastDay !== today, first_ever: ! before }] });
        const endpoint = @json(route('engagement.track'));
        if (! (navigator.sendBeacon && navigator.sendBeacon(endpoint, body))) {
            fetch(endpoint, { method: 'POST', body, keepalive: true }).catch(() => {});
        }
    }
})();
</script>
@include('Student.partials.share-script')
@endsection
