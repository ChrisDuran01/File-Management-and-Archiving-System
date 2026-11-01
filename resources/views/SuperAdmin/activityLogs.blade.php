@extends('SuperAdmin.homeSuperAdmin')
@section('title', 'Activity Logs')
@section('content')

@include('Admin.partials.theme')

<style>
    * { box-sizing: border-box; }
    body { background: var(--surface); font-family: 'Inter', sans-serif; color: var(--text-1); }

    /* ── Card ── */
    /* Not overflow: hidden - the period menus open past the card header. */
    .al-card {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        box-shadow: var(--shadow-sm);
    }

    /* ── Card Header / Filters ── */
    .al-card-header {
        padding: 0.875rem 1.25rem;
        border-bottom: 1px solid var(--border);
        border-radius: var(--radius) var(--radius) 0 0;
        background: #f9fafb;
        display: grid;
        gap: 10px;
    }
    .al-filter-row {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }
    .al-filter-row + .al-filter-row { gap: 8px 16px; }
    .al-filter-field { display: inline-flex; align-items: center; gap: 8px; }
    .al-select {
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        padding: 5px 10px;
        font-size: 14px;
        font-family: 'Inter', sans-serif;
        background: var(--card);
        color: var(--text-1);
        max-width: 260px;
    }
    .al-select:focus { border-color: var(--primary); outline: none; }
    .al-period-note { margin: 0; font-size: 12px; color: var(--text-3); }

    /* Period: one segmented control, as on Reports. School years and custom
       dates open small menus, so the row stays short. */
    .period { display: inline-flex; flex-wrap: wrap; border: 1px solid var(--border); border-radius: 8px; background: var(--card); }
    .period > * + * { border-left: 1px solid var(--border); }
    .period > a,
    .period summary {
        display: inline-flex; align-items: center; gap: 6px; height: 30px; padding: 0 12px;
        font-size: 12px; font-weight: 600; color: var(--text-2); text-decoration: none;
        white-space: nowrap; cursor: pointer; list-style: none; user-select: none;
    }
    .period summary::-webkit-details-marker { display: none; }
    .period summary i { font-size: 9px; opacity: .7; }
    .period > a:hover,
    .period summary:hover { color: var(--text-1); background: var(--surface); }
    .period > a.on,
    .period > details.on > summary { background: var(--primary-fill); color: #fff; }
    .period > :first-child, .period > :first-child > summary { border-radius: 7px 0 0 7px; }
    .period > :last-child, .period > :last-child > summary { border-radius: 0 7px 7px 0; }
    .period-menu { position: relative; display: flex; }
    .period-menu .menu {
        position: absolute; top: calc(100% + 6px); left: -1px; z-index: 30; min-width: 190px; padding: 6px;
        border: 1px solid var(--border); border-radius: 10px; background: var(--card); box-shadow: var(--shadow-md);
    }
    .period-menu:last-child .menu { left: auto; right: -1px; }
    .period-menu .menu a {
        display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 7px 10px;
        border-radius: 6px; font-size: 13px; color: var(--text-1); text-decoration: none;
    }
    .period-menu .menu a:hover { background: var(--surface); }
    .period-menu .menu a.on { color: var(--primary); font-weight: 600; }
    .period-menu .menu a small { font-size: 11px; color: var(--text-3); font-weight: 400; }
    .menu-form { display: grid; gap: 10px; min-width: 230px; }
    .menu-form label { display: grid; gap: 4px; font-size: 11px; font-weight: 600; color: var(--text-2); }
    .al-filter-label {
        font-size: 12px;
        font-weight: 800;
        color: var(--text-2);
        white-space: nowrap;
    }
    .al-date-input {
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        padding: 5px 10px;
        font-size: 14px;
        font-family: 'Inter', sans-serif;
        background: var(--card);
        color: var(--text-1);
        outline: none;
        transition: border-color .15s;
    }
    .al-date-input:focus { border-color: var(--primary); }
    .al-filter-btn {
        background: var(--primary-fill);
        border: none;
        border-radius: var(--radius-sm);
        color: #fff;
        padding: 5px 14px;
        font-size: 12px;
        font-weight: 500;
        font-family: 'Inter', sans-serif;
        cursor: pointer;
        transition: background .15s;
        white-space: nowrap;
    }
    .al-filter-btn:hover { background: #046322; }
    .al-clear-btn {
        background: none;
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        color: var(--text-2);
        padding: 5px 10px;
        font-size: 14px;
        font-family: 'Inter', sans-serif;
        cursor: pointer;
        transition: all .15s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
    }
    .al-clear-btn:hover { border-color: var(--primary); color: var(--primary); }
    .al-clear-btn[hidden] { display: none; }

    /* ── Search ── */
    .al-search-wrap {
        padding: 0.75rem 1.25rem;
        border-bottom: 1px solid var(--border);
    }
    .al-search-inner { position: relative; }
    .al-search-inner input {
        width: 100%;
        border: 1px solid var(--border);
        border-radius: 50px;
        padding: 7px 14px 7px 34px;
        font-size: 14px;
        font-family: 'Inter', sans-serif;
        background: var(--card);
        color: var(--text-1);
        outline: none;
        transition: border-color .15s, box-shadow .15s;
    }
    .al-search-inner input:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(5,128,40,.15);
    }
    .al-search-icon {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        width: 13px;
        height: 13px;
        color: var(--text-3);
        pointer-events: none;
    }

    /* ── Stats Chips ── */
    .al-stats {
        display: flex;
        gap: 8px;
        padding: 0.75rem 1.25rem;
        border-bottom: 1px solid var(--border);
        flex-wrap: wrap;
    }
    .al-chip {
        display: flex;
        align-items: center;
        gap: 5px;
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 50px;
        padding: 3px 10px;
        font-size: 11px;
        color: var(--text-2);
        box-shadow: var(--shadow-sm);
    }
    .al-chip i { font-size: 10px; color: var(--text-3); }

    /* ── Log Body ── */
    .al-body {
        max-height: 600px;
        overflow-y: auto;
    }
    .al-log-item {
        display: flex;
        gap: 12px;
        padding: 0.875rem 1.25rem;
        border-bottom: 1px solid var(--border);
        transition: background .1s;
        align-items: flex-start;
    }
    .al-log-item:last-child { border-bottom: none; }
    .al-log-item:hover { background: var(--primary-dim); }

    /* ── Timeline ── */
    .al-timeline-col {
        display: flex;
        flex-direction: column;
        align-items: center;
        flex-shrink: 0;
    }
    .al-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: var(--primary);
        margin-top: 5px;
        flex-shrink: 0;
    }
    .al-dot.upload  { background: #185FA5; }
    .al-dot.delete  { background: #A32D2D; }
    .al-dot.rename  { background: #854F0B; }
    .al-dot.access  { background: #058028; }
    .al-dot.login   { background: #046322; }
    .al-line {
        width: 1.5px;
        flex: 1;
        background: var(--border);
        margin-top: 4px;
        min-height: 16px;
    }

    /* ── Log Content ── */
    .al-content { flex: 1; min-width: 0; }
    .al-meta {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 3px;
        flex-wrap: wrap;
    }
    .al-time {
        font-size: 13px;
        color: var(--text-2);
        font-family: 'DM Mono', monospace;
    }
    .al-ip {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 13px;
        font-family: 'DM Mono', monospace;
        color: var(--text-2);
        background: #f9fafb;
        border: 1px solid var(--border);
        border-radius: 4px;
        padding: 1px 7px;
    }
    .al-ip i { font-size: 10px; opacity: .6; }
    .al-text {
        font-size: 15px;
        color: var(--text-1);
        line-height: 1.45;
    }
    .al-text b { font-weight: 500; }
    .al-detail { color: var(--text-2); }

    /* ── Badges ── */
    .al-badge {
        display: inline-flex;
        align-items: center;
        margin-left: 6px;
        padding: 1px 7px;
        border-radius: 4px;
        font-size: 10px;
        font-weight: 500;
        vertical-align: middle;
        text-transform: uppercase;
        letter-spacing: .04em;
    }
    .al-badge.upload { background: #E6F1FB; color: #0C447C; }
    .al-badge.delete { background: #FCEBEB; color: #791F1F; }
    .al-badge.rename { background: #FAEEDA; color: #633806; }
    .al-badge.access { background: #E1F5EE; color: #085041; }
    .al-badge.login  { background: #EEEDFE; color: #046322; }

    /* ── Empty State ── */
    .al-empty {
        text-align: center;
        padding: 3rem 1rem;
        color: var(--text-3);
    }
    .al-empty i { font-size: 2rem; margin-bottom: 12px; display: block; }
    .al-empty p { margin: 0; font-size: .9rem; }

    /* ── Infinite scroll: status line, Load more, Back to top ── */
    #alSentinel { height: 1px; }
    .al-status {
        margin: 0;
        padding: .5rem 1rem 0;
        text-align: center;
        font-size: 12.5px;
        color: var(--text-3);
    }
    .al-status:empty { display: none; }
    .al-more-wrap { text-align: center; padding: .75rem 1rem 1rem; }
    .al-more-wrap:has(.al-more-btn[hidden]) { display: none; }
    .al-more-btn {
        border: 1px solid var(--border);
        border-radius: 50px;
        background: var(--card);
        color: var(--text-1);
        font-size: 13px;
        font-weight: 500;
        padding: .45rem 1.25rem;
        cursor: pointer;
    }
    .al-more-btn:hover { border-color: var(--primary); color: var(--primary); }
    .al-top-btn {
        position: fixed;
        right: 1.5rem;
        bottom: 5.5rem; /* above the help assistant's button */
        z-index: 1080;
        width: 42px;
        height: 42px;
        border-radius: 50%;
        border: 1px solid var(--border);
        background: var(--card);
        color: var(--text-1);
        box-shadow: var(--shadow-sm);
        display: grid;
        place-items: center;
        cursor: pointer;
    }
    .al-top-btn[hidden] { display: none; }
    .al-top-btn:hover { border-color: var(--primary); color: var(--primary); }
    .al-top-btn svg { width: 16px; height: 16px; }

    /* ===== DARK MODE ===== */
    [data-theme="dark"] .al-card-header,
    [data-theme="dark"] .al-ip { background: var(--surface); }
    [data-theme="dark"] .al-badge.upload { background: #16233a; color: #8fc2f0; }
    [data-theme="dark"] .al-badge.delete { background: var(--danger-dim); color: #fca5a5; }
    [data-theme="dark"] .al-badge.rename { background: var(--warning-dim); color: #e3b877; }
    [data-theme="dark"] .al-badge.access { background: var(--success-dim); color: var(--success-text); }
    [data-theme="dark"] .al-badge.login  { background: #201f33; color: #a5a3e0; }
</style>

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

{{-- Skeleton placeholder, shown until the real content below is revealed --}}
<div class="skel-shell" aria-hidden="true">
    <div class="page-header">
        <div class="skel skel-text" style="width:130px; height:22px;"></div>
    </div>

    <div class="al-card">
        <div class="al-card-header">
            <div class="skel" style="width:100%; height:32px; border-radius:6px;"></div>
            <div class="skel" style="width:60%; height:32px; border-radius:6px;"></div>
        </div>
        <div class="al-search-wrap">
            <div class="skel" style="width:100%; height:34px; border-radius:50px;"></div>
        </div>
        <div class="al-stats">
            @for ($i = 0; $i < 3; $i++)
            <div class="skel" style="width:90px; height:22px; border-radius:50px;"></div>
            @endfor
        </div>
        <div class="al-body">
            @for ($i = 0; $i < 6; $i++)
            <div class="al-log-item">
                <div class="al-timeline-col">
                    <div class="skel skel-circle" style="width:8px; height:8px;"></div>
                </div>
                <div class="al-content">
                    <div class="skel skel-text" style="width:180px; margin-bottom:6px;"></div>
                    <div class="skel skel-text" style="width:320px;"></div>
                </div>
            </div>
            @endfor
        </div>
    </div>
</div>

<div class="skel-real">

{{-- ── Page Header ── --}}
<div class="page-header">
    <h1>
        <div class="page-title-icon">
            <svg viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect x="2" y="2" width="12" height="12" rx="2" stroke="#058028" stroke-width="1.2"/>
                <line x1="5" y1="6" x2="11" y2="6" stroke="#058028" stroke-width="1.2" stroke-linecap="round"/>
                <line x1="5" y1="9" x2="9" y2="9" stroke="#058028" stroke-width="1.2" stroke-linecap="round"/>
            </svg>
        </div>
        Activity Logs
    </h1>
</div>

{{-- ── Main Card ── --}}
<div class="al-card">

    {{-- ── Filter Header: period (as on Reports), officer, type ── --}}
    @php
        // The other filters, carried along when the period changes. The
        // script keeps the links current as they change without a reload.
        $kept = array_filter(request()->only(['user', 'type', 'q']), 'filled');
        $periodUrl = fn (array $choice) => route('activity.logs', $choice + $kept);
        $syName = fn ($sy) => 'SY ' . str_replace('-', '–', $sy);
        $currentSy = \App\Support\SchoolYear::current();
        $day = fn ($date) => $date->format('M j, Y');
        $periodNote = match (true) {
            $period === 'all'  => 'All dates',
            $from && $to       => $from->eq($to) ? $day($from) : $day($from) . ' – ' . $day($to),
            (bool) $from       => 'From ' . $day($from),
            default            => 'Up to ' . $day($to),
        };
        $officerNames = $logUsers->reject(fn ($name) => str_contains($name, '@'));
        $typedEmails  = $logUsers->filter(fn ($name) => str_contains($name, '@'));
    @endphp
    <div class="al-card-header" id="alFilters" data-period-set="{{ $period === 'all' ? '0' : '1' }}">
        <div class="al-filter-row">
            <span class="al-filter-label" id="alPeriodLabel">Period</span>
            <div class="period" role="group" aria-labelledby="alPeriodLabel">
                <a href="{{ $periodUrl([]) }}" data-period="" class="{{ $period === 'all' ? 'on' : '' }}" @if($period === 'all') aria-current="true" @endif>All time</a>
                @foreach (\App\Http\Controllers\SuperAdminController::LOG_PERIODS as $key => $label)
                    <a href="{{ $periodUrl(['range' => $key]) }}" data-period="range={{ $key }}" class="{{ $period === $key ? 'on' : '' }}" @if($period === $key) aria-current="true" @endif>{{ $label }}</a>
                @endforeach
                <details class="period-menu {{ $period === 'sy' ? 'on' : '' }}">
                    <summary>{{ $period === 'sy' ? $syName(request('sy')) : 'School year' }} <i class="fas fa-chevron-down" aria-hidden="true"></i></summary>
                    <div class="menu">
                        @foreach ($schoolYears as $sy)
                            <a href="{{ $periodUrl(['sy' => $sy]) }}" data-period="sy={{ $sy }}" class="{{ request('sy') === $sy ? 'on' : '' }}" @if(request('sy') === $sy) aria-current="true" @endif>
                                {{ $syName($sy) }} @if($sy === $currentSy)<small>current</small>@endif
                            </a>
                        @endforeach
                    </div>
                </details>
                <details class="period-menu {{ $period === 'custom' ? 'on' : '' }}">
                    <summary>{{ $period === 'custom' ? $periodNote : 'Custom' }} <i class="fas fa-chevron-down" aria-hidden="true"></i></summary>
                    <form method="GET" action="{{ route('activity.logs') }}" class="menu menu-form" id="alCustomForm">
                        @foreach ($kept as $name => $value)
                            <input type="hidden" name="{{ $name }}" value="{{ $value }}" data-kept>
                        @endforeach
                        <label>From <input type="date" class="al-date-input" name="from_date" value="{{ $period === 'custom' ? $from?->format('Y-m-d') : '' }}" max="{{ now()->format('Y-m-d') }}"></label>
                        <label>To <input type="date" class="al-date-input" name="to_date" value="{{ $period === 'custom' ? $to?->format('Y-m-d') : '' }}" max="{{ now()->format('Y-m-d') }}"></label>
                        <button type="submit" class="al-filter-btn">Show this period</button>
                    </form>
                </details>
            </div>
        </div>

        {{-- Officer and Type reload the list in place (the script); the
             form is for Enter in the search box and for no JavaScript. --}}
        <form method="GET" action="{{ route('activity.logs') }}" class="al-filter-row" id="alFilterForm">
            @foreach (array_filter(request()->only(['range', 'sy', 'from_date', 'to_date']), 'filled') as $name => $value)
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endforeach

            <span class="al-filter-field">
                <label class="al-filter-label" for="alUser">Officer</label>
                <select class="al-select" id="alUser" name="user">
                    <option value="">All officers</option>
                    @if ($officerNames->isNotEmpty())
                        <optgroup label="Officers">
                            @foreach ($officerNames as $name)
                                <option value="{{ $name }}" @selected(request('user') === $name)>{{ $name }}</option>
                            @endforeach
                        </optgroup>
                    @endif
                    @if ($typedEmails->isNotEmpty())
                        <optgroup label="Emails typed at sign-in">
                            @foreach ($typedEmails as $name)
                                <option value="{{ $name }}" @selected(request('user') === $name)>{{ $name }}</option>
                            @endforeach
                        </optgroup>
                    @endif
                </select>
            </span>

            <span class="al-filter-field">
                <label class="al-filter-label" for="alType">Type</label>
                <select class="al-select" id="alType" name="type">
                    <option value="">All types</option>
                    @foreach ($types as $key => $label)
                        <option value="{{ $key }}" @selected(request('type') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </span>

            <noscript><button type="submit" class="al-filter-btn">Apply filters</button></noscript>

            <a href="{{ route('activity.logs') }}" class="al-clear-btn" id="alClear" @if($period === 'all' && ! $kept) hidden @endif>Clear filters</a>
        </form>

        <p class="al-period-note">{{ $periodNote }}</p>
    </div>

    {{-- ── Search ── --}}
    <div class="al-search-wrap">
        <div class="al-search-inner">
            <svg class="al-search-icon" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <circle cx="6.5" cy="6.5" r="4.5" stroke="currentColor" stroke-width="1.3"/>
                <line x1="10" y1="10" x2="14" y2="14" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/>
            </svg>
            {{-- Part of the date form too, so changing dates keeps the search. --}}
            <input type="search"
                   id="searchInput"
                   name="q"
                   form="alFilterForm"
                   aria-label="Search activity by user, action or IP address"
                   value="{{ request('q') }}"
                   placeholder="Search by user, action, or IP address…"
                   autocomplete="off">
        </div>
    </div>

    {{-- ── Stats Chips (everything that matches, not just what is loaded) ── --}}
    <div class="al-stats">
        <span class="al-chip">
            <i class="fas fa-list"></i>
            <span id="alStatEvents">{{ number_format($stats['events']) }} {{ Str::plural('event', $stats['events']) }}</span>
        </span>
        <span class="al-chip">
            <i class="fas fa-user"></i>
            <span id="alStatUsers">{{ number_format($stats['users']) }} {{ Str::plural('user', $stats['users']) }}</span>
        </span>
        <span class="al-chip">
            <i class="fas fa-globe"></i>
            {{-- Not Str::plural(): it keeps the capitals and gives "IPS". --}}
            <span id="alStatIps">{{ number_format($stats['ips']) }} unique IP{{ $stats['ips'] === 1 ? '' : 's' }}</span>
        </span>
    </div>

    {{-- ── Log Timeline: newest first; older batches load while scrolling ── --}}
    <div class="al-body">
        <div id="alList" data-next="{{ $next }}" data-has-more="{{ $hasMore ? '1' : '0' }}">
            @include('SuperAdmin.partials.activity-log-rows', ['logs' => $logs, 'hasMore' => $hasMore, 'firstLoad' => true])
        </div>

        <div id="alSentinel" aria-hidden="true"></div>
        <p class="al-status" id="alStatus" role="status" aria-live="polite">
            @if(! $hasMore && $logs->isNotEmpty())
                You've reached the oldest activity.
            @endif
        </p>
        <div class="al-more-wrap">
            <button type="button" class="al-more-btn" id="alMore" @if(! $hasMore) hidden @endif>Load older activity</button>
        </div>
    </div>

</div>

</div>

<button type="button" class="al-top-btn" id="alTop" aria-label="Back to top" hidden>
    <svg viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <path d="M8 13V3M8 3L3.5 7.5M8 3l4.5 4.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
</button>

<script>
// Period menus: one open at a time; a click elsewhere or Escape closes them.
(function () {
    const menus = Array.from(document.querySelectorAll('.period-menu'));
    menus.forEach(menu => menu.addEventListener('toggle', () => {
        if (menu.open) menus.forEach(other => { if (other !== menu) other.open = false; });
    }));
    document.addEventListener('click', e => menus.forEach(menu => { if (menu.open && ! menu.contains(e.target)) menu.open = false; }));
    document.addEventListener('keydown', e => {
        if (e.key !== 'Escape') return;
        menus.filter(menu => menu.open).forEach(menu => { menu.open = false; menu.querySelector('summary').focus(); });
    });
})();

// Infinite scroll for Activity Logs: the next 50 older entries load as the
// end of the list comes near. "Load more" is the same action for keyboards
// and for a retry after a failed load. Search, Officer and Type run on the
// server (they have to reach entries not loaded yet) and start the list over.
(function () {
    const url      = @json(route('activity.logs'));
    const list     = document.getElementById('alList');
    const sentinel = document.getElementById('alSentinel');
    const status   = document.getElementById('alStatus');
    const more     = document.getElementById('alMore');
    const search   = document.getElementById('searchInput');
    const top      = document.getElementById('alTop');
    const filters  = document.getElementById('alFilters');
    const user     = document.getElementById('alUser');
    const type     = document.getElementById('alType');
    const clear    = document.getElementById('alClear');
    const custom   = document.getElementById('alCustomForm');

    let next    = list.dataset.next;
    let hasMore = list.dataset.hasMore === '1';
    let busy    = false;
    let request = 0; // a newer search makes older replies stale

    // The filters set on the page right now, apart from the period.
    function picked() {
        const values = { user: user.value, type: type.value, q: search.value.trim() };
        Object.keys(values).forEach(key => { if (! values[key]) delete values[key]; });
        return values;
    }

    function query(extra) {
        const params = new URLSearchParams(window.location.search); // keeps the period
        ['before', 'q', 'user', 'type'].forEach(key => params.delete(key));
        Object.entries(Object.assign(picked(), extra)).forEach(([key, value]) => params.set(key, value));
        return params;
    }

    // The period links and the Custom form carry the other filters along.
    function syncFilters() {
        const kept = picked();
        filters.querySelectorAll('a[data-period]').forEach(link => {
            const params = new URLSearchParams(link.dataset.period);
            Object.entries(kept).forEach(([key, value]) => params.set(key, value));
            link.href = url + (params.toString() ? '?' + params : '');
        });
        custom.querySelectorAll('[data-kept]').forEach(input => input.remove());
        Object.entries(kept).forEach(([key, value]) => {
            const input = Object.assign(document.createElement('input'), { type: 'hidden', name: key, value: value });
            input.dataset.kept = '';
            custom.prepend(input);
        });
        clear.hidden = filters.dataset.periodSet !== '1' && Object.keys(kept).length === 0;
    }

    function refilter() {
        history.replaceState(null, '', '?' + query({}));
        syncFilters();
        load(true);
    }

    function plural(n, word) {
        return n.toLocaleString() + ' ' + word + (n === 1 ? '' : 's');
    }

    function showStats(stats) {
        document.getElementById('alStatEvents').textContent = plural(stats.events, 'event');
        document.getElementById('alStatUsers').textContent  = plural(stats.users, 'user');
        document.getElementById('alStatIps').textContent    = stats.ips.toLocaleString() + ' unique IP' + (stats.ips === 1 ? '' : 's');
    }

    async function load(fresh) {
        if (! fresh && (busy || ! hasMore)) return;

        const id = ++request;
        busy = true;
        more.hidden = true;
        status.textContent = 'Loading…';

        try {
            const res = await fetch(url + '?' + query(fresh ? {} : { before: next }), {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            });
            if (! res.ok) throw new Error('HTTP ' + res.status);
            const data = await res.json();
            if (id !== request) return;

            if (fresh) list.innerHTML = '';
            list.insertAdjacentHTML('beforeend', data.html);
            if (data.stats) showStats(data.stats);

            next    = data.next;
            hasMore = data.hasMore;
            more.textContent = 'Load older activity';
            more.hidden = ! hasMore;
            status.textContent = (! hasMore && list.querySelector('.log-row')) ? "You've reached the oldest activity." : '';
        } catch (e) {
            if (id !== request) return;
            status.textContent = "Couldn't load more activity.";
            more.textContent = 'Try again';
            more.hidden = false;
        } finally {
            if (id === request) {
                busy = false;
                fillScreen();
            }
        }
    }

    // On a tall screen one batch may not reach the bottom, and the
    // observer only fires when visibility changes - so keep going.
    function fillScreen() {
        if (hasMore && ! busy && sentinel.getBoundingClientRect().top < window.innerHeight + 600) {
            load(false);
        }
    }

    // Measured against whatever scrolls the page (window.appScroll in the
    // layout), so the next rows still load 600px before they are reached.
    new IntersectionObserver(entries => {
        if (entries.some(entry => entry.isIntersecting)) load(false);
    }, { root: window.appScroll ? appScroll.panel() : null, rootMargin: '600px 0px' }).observe(sentinel);

    more.addEventListener('click', () => load(false));

    let typing;
    search.addEventListener('input', () => {
        clearTimeout(typing);
        typing = setTimeout(refilter, 300);
    });
    user.addEventListener('change', refilter);
    type.addEventListener('change', refilter);

    appScroll.on(() => {
        top.hidden = appScroll.y() < 800;
    });
    top.addEventListener('click', () => appScroll.toTop());

    fillScreen();
})();
</script>

@endsection