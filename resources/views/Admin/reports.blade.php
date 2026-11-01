@extends($layout ?? 'Admin.home')
@section('title', 'Reports & Analytics')
@section('content')

@include('Admin.partials.theme')

<style>
    :root {
        --page-plane: var(--surface);
        --surface-1: var(--card);
        --text-primary: var(--text-1);
        --text-secondary: var(--text-2);
        --text-muted: var(--text-3);
        --gridline: var(--border);
        --seq-blue: var(--primary);
        --cat-1: #058028;
        --cat-2: #2f6fb5;
        --cat-3: #b08900;
        --cat-4: #7a8a99;
        --cat-5: #a4457a;
        --cat-6: #c2622d;
    }

    body { background: var(--page-plane); color: var(--text-primary); }

    .section-title { font-size: 12px; font-weight: 600; color: var(--text-secondary); letter-spacing: .06em; text-transform: uppercase; margin: 0 0 4px; }
    .section-sub { font-size: 12px; color: var(--text-muted); margin: 0 0 14px; }
    .card-panel { background: var(--surface-1); border: 1px solid var(--border); border-radius: 12px; padding: 18px 20px; margin-bottom: 16px; }
    .group-head { font-size: 13px; font-weight: 600; color: var(--text-primary); margin: 26px 0 10px; display: flex; align-items: center; gap: 8px; }
    .group-head i { color: var(--seq-blue); }

    /* Every panel has a short title, then the answer: the finding, so it
       is the line that stands out. The chart below is the evidence. No
       extra description lines - a definition goes in an (i) tooltip, and a
       panel that ignores the selected period says so in a small .scope tag. */
    .q { display: flex; align-items: center; flex-wrap: wrap; gap: 6px; font-size: 13px; font-weight: 600; color: var(--text-secondary); margin: 0 0 6px; line-height: 1.35; }
    .q.sm { font-size: 12px; }
    .a { font-size: 15px; color: var(--text-primary); margin: 0 0 14px; line-height: 1.5; }
    .a strong { font-weight: 700; }
    .q .scope { margin-left: auto; padding: 1px 7px; border: 1px solid var(--border); border-radius: 4px; font-size: 11px; font-weight: 500; color: var(--text-muted); white-space: nowrap; }
    .q .info { font-size: 12px; color: var(--text-muted); cursor: help; }
    /* A panel that needs someone to act: an icon before the question (no
       coloured edge - the user had it removed). */
    .tone-warning .q .flag { color: var(--warning); }
    .tone-critical .q .flag { color: #b3412f; }

    /* Tabs */
    .report-tabs { display: flex; gap: 4px; margin: 0 0 16px; border-bottom: 1px solid var(--border); }
    .report-tabs a {
        display: inline-flex; align-items: center; gap: 8px; padding: 10px 14px; margin-bottom: -1px;
        font-size: 13px; font-weight: 600; color: var(--text-secondary); text-decoration: none;
        border-bottom: 2px solid transparent;
    }
    .report-tabs a:hover { color: var(--text-primary); }
    .report-tabs a.on { color: var(--seq-blue); border-bottom-color: var(--seq-blue); }

    /* Period filter: one segmented control. School years and custom dates
       open small menus, so the bar stays a single quiet row. */
    .filter-bar { display: flex; flex-wrap: wrap; align-items: center; gap: 10px 12px; margin-bottom: 8px; }
    .filter-label { font-size: 11px; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: var(--text-muted); }
    .period { display: inline-flex; flex-wrap: wrap; border: 1px solid var(--border); border-radius: 8px; background: var(--surface-1); }
    .period > * + * { border-left: 1px solid var(--border); }
    .period > a,
    .period summary {
        display: inline-flex; align-items: center; gap: 6px; height: 32px; padding: 0 14px;
        font-size: 12px; font-weight: 600; color: var(--text-secondary); text-decoration: none;
        white-space: nowrap; cursor: pointer; list-style: none; user-select: none;
    }
    .period summary::-webkit-details-marker { display: none; }
    .period summary i { font-size: 9px; opacity: .7; }
    .period > a:hover,
    .period summary:hover { color: var(--text-primary); background: var(--page-plane); }
    .period > a.on,
    .period > details.on > summary { background: var(--seq-blue); color: #fff; }
    .period > :first-child, .period > :first-child > summary { border-radius: 7px 0 0 7px; }
    .period > :last-child, .period > :last-child > summary { border-radius: 0 7px 7px 0; }
    .period-menu { position: relative; display: flex; }
    .period-menu .menu {
        position: absolute; top: calc(100% + 6px); left: -1px; z-index: 30; min-width: 190px; padding: 6px;
        border: 1px solid var(--border); border-radius: 10px; background: var(--surface-1); box-shadow: var(--shadow-md);
    }
    .period-menu:last-child .menu { left: auto; right: -1px; }
    .period-menu .menu a {
        display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 7px 10px;
        border-radius: 6px; font-size: 13px; color: var(--text-primary); text-decoration: none;
    }
    .period-menu .menu a:hover { background: var(--page-plane); }
    .period-menu .menu a.on { color: var(--seq-blue); font-weight: 600; }
    .period-menu .menu a small { font-size: 11px; color: var(--text-muted); font-weight: 400; }
    .menu-form { display: grid; gap: 10px; min-width: 230px; }
    .menu-form label { display: grid; gap: 4px; font-size: 11px; font-weight: 600; color: var(--text-muted); }
    .menu-form input[type=date] { font-size: 13px; padding: 6px 8px; border: 1px solid var(--border); border-radius: 6px; background: var(--surface-1); color: var(--text-primary); }
    .filter-bar .btn-go,
    .filter-bar .btn-export {
        display: inline-flex; align-items: center; justify-content: center; gap: 6px; height: 32px; padding: 0 12px;
        font-size: 12px; font-weight: 600; border-radius: 8px; border: 1px solid var(--border);
        background: var(--surface-1); color: var(--text-primary); cursor: pointer; text-decoration: none;
    }
    .filter-bar .btn-go { background: var(--seq-blue); border-color: var(--seq-blue); color: #fff; }
    .filter-bar .btn-export { margin-left: auto; }
    .filter-bar .btn-export + .btn-export { margin-left: 0; }
    .filter-bar .btn-export:hover { background: var(--page-plane); }
    .period-note { font-size: 12px; color: var(--text-muted); margin: 0 0 18px; }

    /* KPI row */
    .metric-grid { display: grid; grid-template-columns: repeat(var(--kpis, 5), 1fr); gap: 12px; margin-bottom: 16px; }
    .metric-card { background: var(--surface-1); border: 1px solid var(--border); border-radius: 10px; padding: 16px 18px; }
    .metric-card .label { font-size: 12px; color: var(--text-muted); margin: 0 0 6px; }
    .metric-card .value { font-size: 26px; font-weight: 600; margin: 0; line-height: 1.1; font-variant-numeric: tabular-nums; color: var(--text-primary); }
    .metric-card .delta { font-size: 11px; margin-top: 4px; color: var(--text-muted); }
    .metric-card .delta.up { color: var(--success-text); }
    .metric-card .delta.down { color: #b3412f; }

    .row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    .row-2-wide { display: grid; grid-template-columns: 1.6fr 1fr; gap: 14px; }
    .row-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; }
    .stack { display: flex; flex-direction: column; }
    .stack .card-panel:last-child { flex: 1; }
    .chart-box { position: relative; width: 100%; height: 240px; }
    .chart-box.short { height: 200px; }

    /* Ranked bars - direct-labeled */
    .rank-row { display: flex; align-items: center; gap: 10px; margin-bottom: 11px; }
    .rank-row:last-child { margin-bottom: 0; }
    .rank-name { font-size: 13px; color: var(--text-primary); width: 150px; flex-shrink: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .rank-track { flex: 1; height: 8px; background: var(--gridline); border-radius: 4px; overflow: hidden; }
    .rank-fill { height: 100%; border-radius: 4px; }
    .rank-val { font-size: 12px; color: var(--text-secondary); min-width: 44px; text-align: right; font-variant-numeric: tabular-nums; flex-shrink: 0; }
    .rank-label { width: 46%; flex-shrink: 0; min-width: 0; }
    .rank-label .rank-name { display: block; width: auto; }
    .rank-meta { display: block; font-size: 11px; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

    /* Proportion bar */
    .prop-bar { display: flex; height: 22px; border-radius: 6px; overflow: hidden; margin-bottom: 12px; background: var(--gridline); }
    .prop-seg { height: 100%; }
    .prop-legend { display: flex; flex-wrap: wrap; gap: 14px; font-size: 12px; color: var(--text-secondary); }
    .prop-legend span.swatch { display: inline-block; width: 10px; height: 10px; border-radius: 2px; margin-right: 5px; vertical-align: -1px; }

    /* Mini stats inside a panel */
    .mini-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; margin-bottom: 16px; }
    .mini { border: 1px solid var(--border); border-radius: 8px; padding: 10px 12px; }
    .mini .k { font-size: 11px; color: var(--text-muted); margin: 0 0 3px; }
    .mini .v { font-size: 20px; font-weight: 600; margin: 0; font-variant-numeric: tabular-nums; }
    .mini .h { font-size: 11px; color: var(--text-muted); margin: 2px 0 0; }
    .stat-line { display: flex; justify-content: space-between; gap: 12px; font-size: 13px; padding: 7px 0; border-bottom: 1px solid var(--gridline); }
    .stat-line:last-child { border-bottom: none; }
    .stat-line .n { font-weight: 600; font-variant-numeric: tabular-nums; }
    .spike-row { align-items: flex-start; }
    .spike-row .spike-day { min-width: 0; font-weight: 500; }
    .spike-row .rank-meta { white-space: normal; font-size: 12px; font-weight: 400; margin-top: 2px; }
    .spike-row .n { white-space: nowrap; }
    .spike-row .n small { font-weight: 400; font-size: 12px; color: var(--text-muted); }

    .rtable { width: 100%; border-collapse: collapse; font-size: 13px; }
    .rtable th { font-size: 11px; color: var(--text-muted); text-transform: uppercase; letter-spacing: .05em; font-weight: 600; padding: 6px 10px; border-bottom: 1px solid var(--gridline); text-align: left; }
    .rtable td { padding: 9px 10px; border-bottom: 1px solid var(--gridline); vertical-align: middle; color: var(--text-primary); }
    .rtable tr:last-child td { border-bottom: none; }
    .rtable td.num, .rtable th.num { text-align: right; font-variant-numeric: tabular-nums; }

    .status-chip { display: inline-flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 600; padding: 3px 10px; border-radius: 20px; }
    .status-chip.ok { background: #e1f3e6; color: #17602b; }
    .status-chip.warning { background: #fff3d6; color: #7a5200; }
    .status-chip.critical { background: #fbe1e1; color: #7a1f1f; }
    .status-chip i { font-size: 9px; }

    .empty-note { font-size: 13px; color: var(--text-muted); padding: 8px 0; }
    .table-scroll { overflow-x: auto; }
    .rtable a { color: var(--text-primary); font-weight: 500; }

    /* Proposals: the Super Admin's price check settings, folded away */
    .price-settings { margin-top: 10px; font-size: 12.5px; }
    .price-settings summary { cursor: pointer; color: var(--seq-blue); font-weight: 600; width: fit-content; }
    .price-settings form { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 10px 14px; margin: 10px 0 6px; }
    .price-settings label { display: grid; gap: 4px; font-size: 11px; font-weight: 600; color: var(--text-muted); }
    .price-settings input, .price-settings select { font-size: 13px; padding: 6px 8px; border: 1px solid var(--border); border-radius: 6px; background: var(--surface-1); color: var(--text-primary); }
    .price-settings input { width: 80px; }
    .price-settings .btn-go { height: 32px; padding: 0 14px; font-size: 12px; font-weight: 600; border-radius: 8px; border: 1px solid var(--seq-blue); background: var(--seq-blue); color: #fff; cursor: pointer; }
    .price-settings .field-error { color: var(--danger); font-size: 12px; flex-basis: 100%; }

    /* A merged panel's second part: one quiet line under its chart or list. */
    .sub-line { display: flex; flex-wrap: wrap; gap: 4px 14px; margin: 12px 0 0; padding-top: 10px; border-top: 1px solid var(--gridline); font-size: 12.5px; color: var(--text-secondary); }
    .sub-line b, .sub-line strong { color: var(--text-primary); font-weight: 600; }
    .health-line { align-items: flex-start; }
    .health-line b { font-weight: 600; }
    .health-text { display: block; margin-top: 2px; font-size: 12.5px; color: var(--text-secondary); }
    .health-text strong { font-weight: 600; color: var(--text-primary); }

    /* Possible copies: the copy, then what it copies */
    .copy-row { display: flex; align-items: center; gap: 10px; padding: 9px 0; border-bottom: 1px solid var(--gridline); }
    .copy-row:last-of-type { border-bottom: none; }
    .copy-names { flex: 1; min-width: 0; }
    .copy-names .rank-name { display: block; width: auto; }
    .copy-names a { color: var(--text-primary); text-decoration: none; }
    .copy-names .rank-meta a { color: var(--text-secondary); }
    .copy-names a:hover { text-decoration: underline; }

    /* Student engagement */
    .eng-intro {
        display: flex; align-items: flex-start; gap: 10px; margin: 0 0 16px; padding: 12px 14px;
        border-radius: 10px; background: var(--primary-dim); color: var(--text-secondary); font-size: 12px; line-height: 1.5;
    }
    .eng-intro i { color: var(--seq-blue); margin-top: 2px; }

    /* Day x hour heatmap: darker = more visits. */
    .heat { display: grid; grid-template-columns: 34px repeat(24, minmax(0, 1fr)); gap: 3px; align-items: center; }
    .heat .hd { font-size: 10px; color: var(--text-muted); text-align: left; white-space: nowrap; overflow: visible; }
    .heat .dl { font-size: 11px; color: var(--text-secondary); }
    .heat .c { height: 22px; border-radius: 4px; background: var(--gridline); }
    .heat-legend { display: flex; align-items: center; gap: 4px; margin-top: 10px; font-size: 11px; color: var(--text-muted); }
    .heat-legend .c { width: 16px; height: 10px; border-radius: 3px; }

    @media (max-width: 1100px) {
        .metric-grid, .mini-grid { grid-template-columns: repeat(2, 1fr); }
        .row-2, .row-2-wide, .row-3 { grid-template-columns: 1fr; }
    }
</style>

{{-- Skeleton placeholder, shown until the real content below is revealed --}}
<div class="skel-shell" aria-hidden="true">
    <div class="page-header">
        <div>
            <div class="skel skel-text" style="width:200px; height:22px; margin-bottom:8px;"></div>
            <div class="skel skel-text" style="width:320px;"></div>
        </div>
    </div>
    <div class="metric-grid">
        @for ($i = 0; $i < 5; $i++)
        <div class="metric-card">
            <div class="skel skel-text" style="width:70%; margin-bottom:10px;"></div>
            <div class="skel skel-text" style="width:45%; height:26px; margin-bottom:8px;"></div>
            <div class="skel skel-text" style="width:60%;"></div>
        </div>
        @endfor
    </div>
    <div class="row-2-wide">
        <div class="card-panel"><div class="skel" style="width:100%; height:260px; border-radius:8px;"></div></div>
        <div class="card-panel"><div class="skel" style="width:100%; height:260px; border-radius:8px;"></div></div>
    </div>
</div>

@php
    $catColors = ['var(--cat-1)', 'var(--cat-2)', 'var(--cat-3)', 'var(--cat-4)', 'var(--cat-5)', 'var(--cat-6)', 'var(--cat-4)', 'var(--cat-3)'];
    // The current period, carried along when switching tabs.
    $periodParams = array_filter(request()->only(['range', 'from', 'to', 'sy']));
    $q = fn (array $extra) => route('reports.index', $extra + ['tab' => $tab]);
@endphp

<div class="container-fluid py-3 skel-real">
    <div class="page-header">
        <div>
            <h1>Reports &amp; Analytics</h1>        </div>
    </div>

    <nav class="report-tabs" aria-label="Report sections">
        <a href="{{ route('reports.index', $periodParams + ['tab' => 'records']) }}" class="{{ $tab === 'records' ? 'on' : '' }}" @if ($tab === 'records') aria-current="page" @endif>
            <i class="fas fa-box-archive"></i> Records &amp; system
        </a>
        <a href="{{ route('reports.index', $periodParams + ['tab' => 'engagement']) }}" class="{{ $tab === 'engagement' ? 'on' : '' }}" @if ($tab === 'engagement') aria-current="page" @endif>
            <i class="fas fa-users"></i> Student engagement
        </a>
        <a href="{{ route('reports.index', $periodParams + ['tab' => 'proposals']) }}" class="{{ $tab === 'proposals' ? 'on' : '' }}" @if ($tab === 'proposals') aria-current="page" @endif>
            <i class="fas fa-file-invoice"></i> Proposals
        </a>
    </nav>

    {{-- PERIOD FILTER - a school year, a recent window, or custom dates, in one control --}}
    @php
        $currentSy = \App\Support\SchoolYear::current();
        $syName = fn ($sy) => 'SY ' . str_replace('-', '–', $sy);
    @endphp
    <div class="filter-bar">
        <span class="filter-label" id="periodLabel">Period</span>
        <div class="period" role="group" aria-labelledby="periodLabel">
            <details class="period-menu {{ $rangeKey === 'sy' ? 'on' : '' }}">
                <summary>{{ $schoolYear ? $syName($schoolYear) : 'School year' }} <i class="fas fa-chevron-down" aria-hidden="true"></i></summary>
                <div class="menu">
                    @foreach ($schoolYears as $sy)
                        <a href="{{ $q(['sy' => $sy]) }}" class="{{ $schoolYear === $sy ? 'on' : '' }}" @if ($schoolYear === $sy) aria-current="true" @endif>
                            {{ $syName($sy) }} @if ($sy === $currentSy)<small>current</small>@endif
                        </a>
                    @endforeach
                </div>
            </details>
            @foreach (['7d' => '7 days', '30d' => '30 days', '90d' => '90 days', '12m' => '12 months'] as $key => $label)
                <a href="{{ $q(['range' => $key]) }}" class="{{ $rangeKey === $key ? 'on' : '' }}" title="Last {{ $label }}" @if ($rangeKey === $key) aria-current="true" @endif>{{ $label }}</a>
            @endforeach
            <details class="period-menu {{ $rangeKey === 'custom' ? 'on' : '' }}">
                <summary>{{ $rangeKey === 'custom' ? $from->format('M j') . ' – ' . $to->format('M j, Y') : 'Custom' }} <i class="fas fa-chevron-down" aria-hidden="true"></i></summary>
                <form method="GET" action="{{ route('reports.index') }}" class="menu menu-form">
                    <input type="hidden" name="tab" value="{{ $tab }}">
                    <label>From <input type="date" name="from" value="{{ $from->format('Y-m-d') }}" max="{{ now()->format('Y-m-d') }}" required></label>
                    <label>To <input type="date" name="to" value="{{ $to->format('Y-m-d') }}" max="{{ now()->format('Y-m-d') }}" required></label>
                    <button type="submit" class="btn-go">Show this period</button>
                </form>
            </details>
        </div>
        <a class="btn-export" href="{{ route('reports.index', $periodParams + ['tab' => $tab, 'export' => 'csv']) }}">
            <i class="fas fa-file-csv" aria-hidden="true"></i> Export CSV
        </a>
        <a class="btn-export" href="{{ route('reports.index', $periodParams + ['tab' => $tab, 'export' => 'pdf']) }}" target="_blank" title="Opens in a new tab, ready to print">
            <i class="fas fa-file-pdf" aria-hidden="true"></i> Open PDF
        </a>
    </div>
    <p class="period-note">
        {{ $periodLabel }}@if ($tab !== 'proposals') &middot; compared with {{ $comparedWith }}@endif
    </p>

@if ($tab === 'records')

    {{-- Officer activity, backups and sign-in security are SuperAdmin-only
         (see ReportController::index) - their data isn't sent to an Admin, so
         every panel that uses it sits inside an $isSuperAdmin check. --}}

    @php
        // Which panels need someone to act - they get a coloured edge and an icon.
        $failedBatches = (int) ($batchStatuses->firstWhere('key', 'failed')['total'] ?? 0);
        $signs = $isSuperAdmin ? count($loginAnomalies['bruteForce']) + count($loginAnomalies['multiIp']) + count($loginAnomalies['newLocation']) : 0;
        $tone = [
            'pipeline'  => $failedBatches > 0 ? 'critical' : ($pipeline['awaiting'] > 0 ? 'warning' : null),
            'integrity' => $preservation['fixity_failed'] > 0 ? 'critical' : ($preservation['fixity_unchecked'] > 0 ? 'warning' : null),
            'ocr'       => $ocr['failed'] > 0 ? 'warning' : null,
            'copies'    => $copies->where('readonly', false)->isNotEmpty() ? 'warning' : null,
            'backups'   => ! $isSuperAdmin ? null
                : (! $backups['last'] || $backups['last_age_hours'] > 24 * 7 ? 'critical'
                : ($backups['last_age_hours'] > 48 || $backups['partial'] > 0 ? 'warning' : null)),
            'security'  => ! $isSuperAdmin ? null
                : (collect($loginAnomalies['bruteForce'])->contains(fn ($r) => $r->attempts >= 5) ? 'critical'
                : ($signs > 0 ? 'warning' : null)),
        ];
        // System health is the worst of its lines.
        $healthTones = array_filter([$tone['integrity'], $tone['backups'], $tone['ocr']]);
        $tone['health'] = in_array('critical', $healthTones, true) ? 'critical' : ($healthTones ? 'warning' : null);
        $toneClass = fn ($key) => $tone[$key] ? ' tone-' . $tone[$key] : '';
        $flag = fn ($key) => $tone[$key] ? '<i class="fas fa-circle-exclamation flag" aria-hidden="true"></i><span class="sr-only">Needs attention:</span>' : '';
    @endphp

    {{-- KPI ROW - two cards for an Admin, three for a SuperAdmin. A change
         line only when there is something to compare with. --}}
    <div class="metric-grid" style="--kpis: {{ count($kpis) }};">
        @foreach ($kpis as $k)
            <div class="metric-card">
                <p class="label">{{ $k['label'] }}</p>
                <p class="value">{{ number_format($k['value']) }}</p>
                @if ($k['delta'] !== null)
                    <p class="delta {{ $k['delta'] > 0 ? 'up' : ($k['delta'] < 0 ? 'down' : '') }}">
                        {{ $k['delta'] > 0 ? '↑' : ($k['delta'] < 0 ? '↓' : '→') }} {{ abs($k['delta']) }}% vs {{ number_format($k['previous']) }}
                    </p>
                @endif
            </div>
        @endforeach
    </div>

    {{-- ACTIVITY TREND + OFFICER ACTIVITY --}}
    <div @if ($isSuperAdmin) class="row-2-wide" @endif>
        <div class="card-panel">
            <h3 class="q">Records added</h3>
            <p class="a">{!! $answers['trend'] !!}</p>
            <div class="chart-box"><canvas id="trendChart"></canvas></div>
        </div>
        @if ($isSuperAdmin)
        <div class="card-panel">
            <h3 class="q">Officer activity</h3>
            <p class="a">{!! $answers['categories'] !!} {!! $answers['officers'] !!}</p>
            @php $catMax = $activityByCategory->max('total') ?: 1; @endphp
            @forelse ($activityByCategory->take(6) as $row)
                <div class="rank-row">
                    <span class="rank-name" title="{{ $row['label'] }}">{{ $row['label'] }}</span>
                    <div class="rank-track"><div class="rank-fill" style="width:{{ round(($row['total'] / $catMax) * 100) }}%; background:{{ $row['label'] === 'Failed sign-ins' ? '#b3412f' : 'var(--seq-blue)' }};"></div></div>
                    <span class="rank-val">{{ number_format($row['total']) }}</span>
                </div>
            @empty
                <p class="empty-note">No activity recorded in this period.</p>
            @endforelse
            @if ($activeOfficers->isNotEmpty())
                <p class="sub-line">Most active:
                    @foreach ($activeOfficers->take(3) as $officer)
                        <span><b>{{ $officer->user_name }}</b> {{ number_format($officer->total) }}</span>
                    @endforeach
                </p>
            @endif
        </div>
        @endif
    </div>

    {{-- SCANNING + STORAGE --}}
    <div class="group-head"><i class="fas fa-file-import"></i> Scanning and storage</div>
    <div class="row-2">
        <div class="card-panel{{ $toneClass('pipeline') }}">
            <h3 class="q">{!! $flag('pipeline') !!}Scanning</h3>
            <p class="a">{!! $answers['pipeline'] !!}</p>
            <div class="mini-grid" style="grid-template-columns: repeat(4, 1fr);">
                <div class="mini"><p class="k">Batches</p><p class="v">{{ number_format($pipeline['batches']) }}</p><p class="h">{{ number_format($pipeline['pages']) }} pages</p></div>
                <div class="mini"><p class="k">Filed</p><p class="v">{{ number_format($pipeline['filed']) }}</p><p class="h">{{ number_format($pipeline['docs_from_scans']) }} documents</p></div>
                <div class="mini"><p class="k">Waiting</p><p class="v" @if($pipeline['awaiting'] > 0) style="color:var(--warning);" @endif>{{ number_format($pipeline['awaiting']) }}</p></div>
                <div class="mini"><p class="k">Failed</p><p class="v" @if($pipeline['failed'] > 0) style="color:#b3412f;" @endif>{{ number_format($pipeline['failed']) }}</p></div>
            </div>
            @php $batchTotal = $batchStatuses->sum('total'); @endphp
            @if ($batchTotal > 0)
                <div class="prop-bar" style="height:10px;">
                    @foreach ($batchStatuses as $i => $b)
                        <div class="prop-seg" style="width:{{ ($b['total'] / $batchTotal) * 100 }}%; background:{{ $b['key'] === 'failed' ? '#b3412f' : $catColors[$i % 8] }};" title="{{ $b['label'] }}: {{ $b['total'] }}"></div>
                    @endforeach
                </div>
                <div class="prop-legend">
                    @foreach ($batchStatuses as $i => $b)
                        <span><span class="swatch" style="background:{{ $b['key'] === 'failed' ? '#b3412f' : $catColors[$i % 8] }};"></span>{{ $b['label'] }} {{ $b['total'] }}</span>
                    @endforeach
                </div>
            @endif
            <p class="sub-line"><span>Digitized: {!! $answers['types'] !!}</span></p>
        </div>
        <div class="card-panel">
            <h3 class="q">Storage <span class="scope">Last 6 months</span></h3>
            <p class="a">{!! $answers['storage'] !!}</p>
            <div class="chart-box short"><canvas id="storageChart"></canvas></div>
            @if ($topFolders->isNotEmpty())
                <p class="sub-line">Largest:
                    @foreach ($topFolders->take(3) as $f)
                        <span><b>{{ $f->name }}</b> {{ $f->formatted }}</span>
                    @endforeach
                </p>
            @endif
        </div>
    </div>

    {{-- UPKEEP: system health (archives, backups, text search) --}}
    @php
        // One line per check, each with its own chip.
        $pr = $preservation;
        $healthLines = [[
            'name' => 'Archive integrity', 'answer' => $answers['integrity'],
            'chip' => $pr['fixity_failed'] > 0 ? ['critical', $pr['fixity_failed'] . ' failed']
                : ($pr['fixity_unchecked'] > 0 ? ['warning', $pr['fixity_unchecked'] . ' not checked']
                : ['ok', $pr['archives'] ? 'All intact' : 'No archives yet']),
        ]];
        if ($isSuperAdmin) {
            $healthLines[] = [
                'name' => 'Backups', 'answer' => $answers['backups'],
                'chip' => ! $backups['last'] || $backups['last_age_hours'] > 24 * 7 ? ['critical', $backups['last'] ? 'Over a week old' : 'Never run']
                    : ($backups['partial'] > 0 ? ['warning', $backups['partial'] . ' partial']
                    : ($backups['last_age_hours'] > 48 ? ['warning', '2+ days old'] : ['ok', 'Up to date'])),
            ];
        }
        $healthLines[] = [
            'name' => 'Text search', 'answer' => $answers['ocr'],
            'chip' => $ocr['failed'] > 0 ? ['warning', $ocr['failed'] . ' failed'] : ['ok', $ocr['coverage'] !== null ? $ocr['coverage'] . '% searchable' : 'No files yet'],
        ];
        $needLook = collect($healthLines)->filter(fn ($l) => $l['chip'][0] !== 'ok')->count();
    @endphp
    <div class="group-head"><i class="fas fa-shield-heart"></i> Upkeep</div>
    <div>
        <div class="card-panel{{ $toneClass('health') }}">
            <h3 class="q">{!! $flag('health') !!}System health</h3>
            <p class="a">
                @if ($needLook)
                    <strong>{{ $needLook }} of {{ count($healthLines) }}</strong> {{ $needLook === 1 ? 'needs' : 'need' }} a look.
                @else
                    {{ $isSuperAdmin ? 'Archives, backups and text search are all in order.' : 'Archives and text search are all in order.' }}
                @endif
            </p>
            @foreach ($healthLines as $line)
                <div class="stat-line health-line">
                    <span><b>{{ $line['name'] }}</b><span class="health-text">{!! $line['answer'] !!}</span></span>
                    <span class="n"><span class="status-chip {{ $line['chip'][0] }}">{{ $line['chip'][1] }}</span></span>
                </div>
            @endforeach
        </div>
    </div>

    {{-- TEXT MINING - copies of the same document (App\Services\DuplicateDetector,
         flagged as each file is saved) and App\Services\ArchiveInsights (TF-IDF
         vectors of the files' text). Only folders this officer may open, so it
         is the same for Admins and SuperAdmins. --}}
    <div class="group-head"><i class="fas fa-file-lines"></i> Found in the documents’ text</div>
    <p class="period-note" style="margin-top:-2px;">
        From the text of {{ number_format($insights['corpus']) }} {{ Str::plural('file', $insights['corpus']) }} you can open. Suggestions to review: nothing is moved or deleted for you.
    </p>

    <div class="row-2">
        <div class="card-panel{{ $toneClass('copies') }}">
            <h3 class="q">{!! $flag('copies') !!}Possible copies <span class="scope">All time</span></h3>
            <p class="a">{!! $answers['copies'] !!}</p>
            @foreach ($copies->take(8) as $c)
                <div class="copy-row">
                    <div class="copy-names">
                        <a href="{{ route('files.preview', $c->id) }}" class="rank-name" title="{{ $c->name }}">{{ $c->name }}</a>
                        <span class="rank-meta">
                            @if ($c->original)
                                copy of <a href="{{ route('files.preview', $c->original->id) }}" title="{{ $c->original->name }}">{{ $c->original->name }}</a>{{ $c->original->folder ? ' · ' . $c->original->folder : '' }}
                            @else
                                copy of a file in a folder you can’t open
                            @endif
                            {{ $c->readonly ? ' · past school year, read-only' : '' }}
                        </span>
                    </div>
                    <span class="status-chip warning">{{ $c->similarity === 100 ? 'Exact copy' : $c->similarity . '% alike' }}</span>
                </div>
            @endforeach
            @if ($copies->count() > 8)
                <p class="section-sub" style="margin:10px 0 0;">And {{ $copies->count() - 8 }} more. Each one is marked “Possible copy” in its folder.</p>
            @endif
        </div>
        <div class="card-panel">
            <h3 class="q">Possibly in the wrong folder <span class="scope">All time</span></h3>
            <p class="a">{!! $answers['misfiled'] !!}</p>
            @foreach ($insights['misfiled'] as $m)
                <div style="padding:9px 0; border-bottom:1px solid var(--gridline);">
                    <div style="font-size:13px; font-weight:500; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="{{ $m['file']['name'] }}">{{ $m['file']['name'] }}</div>
                    <div style="font-size:12px; color:var(--text-muted);">
                        @if ($m['reason'] === 'title')
                            In <a href="{{ route('folders.show', $m['file']['folder_id']) }}">{{ $m['file']['folder'] }}</a>, but its title says {{ $m['kind'] }}
                            &rarr; belongs in <a href="{{ route('folders.show', $m['suggested']['id']) }}">{{ $m['suggested']['name'] }}</a>
                        @else
                            In <a href="{{ route('folders.show', $m['file']['folder_id']) }}">{{ $m['file']['folder'] }}</a> ({{ $m['own_match'] }}% match)
                            &rarr; reads like <a href="{{ route('folders.show', $m['suggested']['id']) }}">{{ $m['suggested']['name'] }}</a> ({{ $m['other_match'] }}% match)
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    @if ($isSuperAdmin)
    {{-- SECURITY: LOGIN ANOMALIES - pattern detection over activity_logs,
         not a raw count. SuperAdmin-only. --}}
    <div class="group-head"><i class="fas fa-shield-halved"></i> Security</div>
    <div class="card-panel{{ $toneClass('security') }}">
        <h3 class="q">{!! $flag('security') !!}Suspicious sign-ins <span class="scope">Last 7 days</span></h3>
        <p class="a">{!! $answers['security'] ?? '' !!}</p>

        <div class="row-3">
            <div>
                <p class="section-title" style="font-size:11px;">Possible brute force</p>
                @forelse ($loginAnomalies['bruteForce'] as $row)
                    <div class="rank-row" style="margin-bottom:10px; align-items:flex-start;">
                        <div style="flex:1; min-width:0;">
                            <div style="font-size:13px; font-weight:500; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $row->user_name }}</div>
                            <div style="font-size:11px; color:var(--text-muted);">{{ $row->distinct_ips }} {{ Str::plural('IP', $row->distinct_ips) }} &middot; last {{ \Carbon\Carbon::parse($row->last_attempt)->diffForHumans() }}</div>
                        </div>
                        <span class="status-chip {{ $row->attempts >= 5 ? 'critical' : 'warning' }}"><i class="fas fa-circle"></i> {{ $row->attempts }}x</span>
                    </div>
                @empty
                    <p class="empty-note">No repeated failed attempts.</p>
                @endforelse
            </div>

            <div>
                <p class="section-title" style="font-size:11px;">Multiple IPs, same account</p>
                @forelse ($loginAnomalies['multiIp'] as $row)
                    <div class="rank-row" style="margin-bottom:10px; align-items:flex-start;">
                        <div style="flex:1; min-width:0;">
                            <div style="font-size:13px; font-weight:500; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $row->user_name }}</div>
                            <div style="font-size:11px; color:var(--text-muted);">{{ $row->total_logins }} logins &middot; last {{ \Carbon\Carbon::parse($row->last_login)->diffForHumans() }}</div>
                        </div>
                        <span class="status-chip warning"><i class="fas fa-circle"></i> {{ $row->distinct_ips }} IPs</span>
                    </div>
                @empty
                    <p class="empty-note">No accounts logging in from multiple locations.</p>
                @endforelse
            </div>

            <div>
                <p class="section-title" style="font-size:11px;">New-location logins</p>
                @forelse ($loginAnomalies['newLocation'] as $row)
                    <div class="rank-row" style="margin-bottom:10px; align-items:flex-start;">
                        <div style="flex:1; min-width:0;">
                            <div style="font-size:13px; font-weight:500; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $row->user_name }}</div>
                            <div style="font-size:11px; color:var(--text-muted);">{{ $row->ip_address }} &middot; {{ $row->created_at->diffForHumans() }}</div>
                        </div>
                        <span class="status-chip warning"><i class="fas fa-location-dot"></i> new</span>
                    </div>
                @empty
                    <p class="empty-note">No unfamiliar-location logins.</p>
                @endforelse
            </div>
        </div>
    </div>
    @endif

@elseif ($tab === 'engagement')
    @php $e = $engagement; @endphp

    <p class="eng-intro">
        <i class="fas fa-user-shield"></i>
        <span>
            Anonymous; officers' own visits aren't counted.
            @if ($e['since'])
                Recording since {{ $e['since']->format('M j, Y') }}.
            @else
                Recording starts the first time a student opens the student page.
            @endif
        </span>
    </p>

    {{-- KPI ROW --}}
    <div class="metric-grid">
        @foreach ($e['kpis'] as $k)
            <div class="metric-card">
                <p class="label">{{ $k['label'] }}</p>
                <p class="value">{{ number_format($k['value']) }}</p>
                {{-- A change line only when both periods were recorded. --}}
                @if ($e['comparable'] && $k['delta'] !== null)
                    <p class="delta {{ $k['delta'] > 0 ? 'up' : ($k['delta'] < 0 ? 'down' : '') }}">
                        {{ $k['delta'] > 0 ? '↑' : ($k['delta'] < 0 ? '↓' : '→') }} {{ abs($k['delta']) }}% vs {{ number_format($k['previous']) }}
                    </p>
                @endif
            </div>
        @endforeach
    </div>

    <div class="row-2-wide">
        <div class="card-panel">
            <h3 class="q">Visits
                <i class="fas fa-circle-info info" tabindex="0" role="img" aria-label="A visit is one device opening the page on a given day; reloads don't count." title="A visit is one device opening the page on a given day; reloads don't count."></i></h3>
            <p class="a">{!! $e['visits']['answer'] !!}</p>
            @if (array_sum($e['visits']['visits']) > 0)
                <div class="chart-box"><canvas id="engVisitsChart"></canvas></div>
            @endif
            {{-- Devices and notifications, once panels of their own. --}}
            <p class="sub-line"><span>{!! $e['devices']['answer'] !!}</span><span>{!! $e['notifications']['answer'] !!}</span></p>
        </div>

    <div class="card-panel">
        <h3 class="q">Unusual days
            <i class="fas fa-circle-info info" tabindex="0" role="img" aria-label="Each day is compared with the 28 days before it. A day is unusual when its visits are far above the median day (a modified z-score of 3.5 or more), with at least 10 visits and at least twice a usual day." title="Each day is compared with the 28 days before it. A day is unusual when its visits are far above the median day (a modified z-score of 3.5 or more), with at least 10 visits and at least twice a usual day."></i></h3>
        <p class="a">{!! $e['spikes']['answer'] !!}</p>
        @foreach ($e['spikes']['spikes'] as $s)
            <div class="stat-line spike-row">
                <span class="spike-day">
                    {{ $s['date']->format('D, M j, Y') }}
                    @if ($s['cause'])<span class="rank-meta">{{ $s['cause'] }}</span>@endif
                </span>
                <span class="n">{{ number_format($s['visits']) }} {{ Str::plural('visit', $s['visits']) }} <small>· usual {{ number_format($s['usual']) }}</small></span>
            </div>
        @endforeach
    </div>
    </div>

    <div class="card-panel">
        <h3 class="q">When students visit</h3>
        <p class="a">{!! $e['timing']['answer'] !!}</p>
        @php
            $days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
            $hourName = fn ($h) => $h === 0 ? '12 AM' : ($h === 12 ? '12 PM' : ($h % 12).($h < 12 ? ' AM' : ' PM'));
        @endphp
        <div class="heat" role="img" aria-label="{{ strip_tags($e['timing']['answer']) }}">
            <span></span>
            @for ($h = 0; $h < 24; $h++)
                <span class="hd">{{ $h % 3 === 0 ? $hourName($h) : '' }}</span>
            @endfor
            @foreach ($e['timing']['grid'] as $wd => $hours)
                <span class="dl">{{ $days[$wd] }}</span>
                @foreach ($hours as $h => $n)
                    <span class="c" title="{{ $days[$wd] }} {{ $hourName($h) }}: {{ $n }} {{ Str::plural('visit', $n) }}"
                          @if ($n > 0) style="background: color-mix(in srgb, var(--cat-1) {{ 15 + (int) round(85 * $n / $e['timing']['max']) }}%, var(--gridline));" @endif></span>
                @endforeach
            @endforeach
        </div>
        <div class="heat-legend" aria-hidden="true">
            <span>Fewer</span>
            @foreach ([0, 30, 60, 100] as $pct)
                <span class="c" style="background: {{ $pct ? 'color-mix(in srgb, var(--cat-1) '.$pct.'%, var(--gridline))' : 'var(--gridline)' }};"></span>
            @endforeach
            <span>More visits</span>
        </div>
    </div>

    <div class="row-2">
        <div class="card-panel">
            <h3 class="q">Top announcements
                <i class="fas fa-circle-info info" tabindex="0" role="img" aria-label="Reach counts devices where the announcement stayed on screen for at least a second." title="Reach counts devices where the announcement stayed on screen for at least a second."></i></h3>
            <p class="a">{!! $e['announcements']['answer'] !!}</p>
            @php $anMax = max(1, collect($e['announcements']['rows'])->max('views')); @endphp
            @foreach ($e['announcements']['rows'] as $row)
                <div class="rank-row">
                    <div class="rank-label">
                        <span class="rank-name" title="{{ $row['title'] }}">{{ $row['title'] }}</span>
                        @php
                            $detail = array_filter([
                                $row['opens'] ? $row['opens'] . ' opened in full' : null,
                                $row['downloads'] ? $row['downloads'] . ' attachment ' . Str::plural('download', $row['downloads']) : null,
                            ]);
                        @endphp
                        @if ($detail)<span class="rank-meta">{{ implode(' · ', $detail) }}</span>@endif
                    </div>
                    <div class="rank-track"><div class="rank-fill" style="width:{{ round($row['views'] / $anMax * 100) }}%; background:var(--cat-1);"></div></div>
                    <span class="rank-val">{{ number_format($row['views']) }}</span>
                </div>
            @endforeach
        </div>
        <div class="card-panel">
            <h3 class="q">Most used documents</h3>
            <p class="a">{!! $e['documents']['answer'] !!}</p>
            @php $docMax = max(1, collect($e['documents']['rows'])->max(fn ($r) => $r['previews'] + $r['downloads'])); @endphp
            @foreach ($e['documents']['rows'] as $row)
                <div class="rank-row">
                    <div class="rank-label">
                        <span class="rank-name" title="{{ $row['name'] }}">{{ $row['name'] }}</span>
                        <span class="rank-meta">{{ implode(' · ', array_filter([
                            $row['previews'] ? $row['previews'] . ' ' . Str::plural('preview', $row['previews']) : null,
                            $row['downloads'] ? $row['downloads'] . ' ' . Str::plural('download', $row['downloads']) : null,
                        ])) }}</span>
                    </div>
                    <div class="rank-track"><div class="rank-fill" style="width:{{ round(($row['previews'] + $row['downloads']) / $docMax * 100) }}%; background:var(--cat-2);"></div></div>
                    <span class="rank-val">{{ number_format($row['previews'] + $row['downloads']) }}</span>
                </div>
            @endforeach
        </div>
    </div>

    <div class="card-panel">
            <h3 class="q">Student searches</h3>
            <p class="a">{!! $e['searches']['answer'] !!}</p>
            @php
                // When every search found nothing, both lists are the same - show one.
                $sameLists = collect($e['searches']['top'])->pluck('term')->all() === collect($e['searches']['missing'])->pluck('term')->all();
            @endphp
            @if ($e['searches']['total'] > 0)
                <div @class(['row-2' => ! $sameLists])>
                    <div>
                        <p class="section-title">Found nothing</p>
                        <table class="rtable">
                            <tbody>
                                @forelse ($e['searches']['missing'] as $s)
                                    <tr><td>{{ $s->term }}</td><td class="num">{{ $s->n }}×</td></tr>
                                @empty
                                    <tr><td class="empty-note">None.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div @if ($sameLists) hidden @endif>
                        <p class="section-title">Searched most</p>
                        <table class="rtable">
                            <tbody>
                                @foreach ($e['searches']['top'] as $s)
                                    <tr><td>{{ $s->term }}</td><td class="num">{{ $s->n }}×</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
    </div>
@else
    {{-- PROPOSALS: budget data mining over Activity Proposals
         (App\Services\ProposalReport - PriceCheck and BudgetRules). --}}
    @php
        $pp = $proposalReport['prices'];
        $pr = $proposalReport['rules'];
        $peso = fn ($v) => \App\Services\PriceCheck::peso((float) $v);
        $qty = fn ($q) => rtrim(rtrim(number_format((float) $q, 2), '0'), '.');
        $rateText = rtrim(rtrim(number_format($pp['rate'], 2), '0'), '.');
        // Needs someone to act: a flagged price nobody has explained.
        $unexplained = collect($pp['flagged'])->contains(fn ($f) => blank($f['reason']));
    @endphp

    <div class="card-panel{{ $unexplained ? ' tone-warning' : '' }}">
        <h3 class="q">@if ($unexplained)<i class="fas fa-circle-exclamation flag" aria-hidden="true"></i><span class="sr-only">Needs attention:</span>@endif Unusual prices
            <i class="fas fa-circle-info info" tabindex="0" role="img" aria-label="Each Unit Price is compared with what approved proposals paid for the same item and unit. It is unusual when its robust z-score (from the median and the median absolute deviation) is 3.5 or more, so a price within about 35% of the usual one is never flagged. An item needs 3 or more past prices." title="Each Unit Price is compared with what approved proposals paid for the same item and unit. It is unusual when its robust z-score (from the median and the median absolute deviation) is 3.5 or more, so a price within about 35% of the usual one is never flagged. An item needs 3 or more past prices."></i></h3>
        <p class="a">{!! $pp['answer'] !!}</p>
        @if ($pp['flagged'])
            <div class="table-scroll">
                <table class="rtable" style="min-width:720px;">
                    <thead>
                        <tr><th>Proposal</th><th>Particular</th><th class="num">Unit price</th><th class="num">Usual</th><th>Check</th><th>Officer’s reason</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($pp['flagged'] as $f)
                            <tr>
                                <td>
                                    <a href="{{ route('proposals.show', $f['proposal']) }}">{{ $f['proposal']->title }}</a>
                                    <span class="rank-meta">{{ $f['proposal']->statusLabel() }} &middot; {{ $f['proposal']->dateLabel() }}</span>
                                </td>
                                <td>{{ $f['item']->particular }}<span class="rank-meta">{{ $qty($f['item']->quantity) }} {{ $f['item']->unit }}</span></td>
                                <td class="num">{{ $peso($f['item']->unit_price) }}</td>
                                <td class="num">{{ $peso($f['check']['median']) }}<span class="rank-meta">{{ $f['check']['count'] }} prices</span></td>
                                <td>{{ $f['check']['summary'] }}</td>
                                <td>@if (filled($f['reason'])) {{ $f['reason'] }} @else <span class="rank-meta" style="white-space:normal;">None yet</span> @endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        <p class="sub-line"><span>Older prices are brought up to today’s value at <b>{{ $rateText }}%</b> a year, and compared with this school year and the <b>{{ $pp['years'] }}</b> before. Only approved proposals count.</span></p>
        @if ($isSuperAdmin)
            <details class="price-settings" @if ($errors->has('price_inflation_rate') || $errors->has('price_compare_years')) open @endif>
                <summary>Change these settings</summary>
                <form method="POST" action="{{ route('reports.price-settings', $periodParams) }}">
                    @csrf
                    <label>Inflation per year
                        <span><input type="number" name="price_inflation_rate" min="0" max="30" step="0.1" value="{{ old('price_inflation_rate', $rateText) }}" required> %</span>
                    </label>
                    <label>Compare with
                        <select name="price_compare_years">
                            @for ($y = 1; $y <= 5; $y++)
                                <option value="{{ $y }}" @selected((int) old('price_compare_years', $pp['years']) === $y)>This school year and the {{ $y }} before</option>
                            @endfor
                        </select>
                    </label>
                    <button type="submit" class="btn-go">Save</button>
                    @error('price_inflation_rate')<span class="field-error">{{ $message }}</span>@enderror
                    @error('price_compare_years')<span class="field-error">{{ $message }}</span>@enderror
                </form>
                <p class="rank-meta" style="white-space:normal;">Enter the PSA’s inflation rate for the year (Consumer Price Index). 3% is the middle of the BSP’s 2–4% target.</p>
            </details>
        @endif
    </div>

    <div class="card-panel">
        <h3 class="q">Often budgeted together
            <i class="fas fa-circle-info info" tabindex="0" role="img" aria-label="Market-basket analysis with the Apriori algorithm: each approved proposal's budget is a basket of items. Confidence: of the proposals with the first items, the share that also had the second. Lift: how much more often they go together than by chance; above 1 means they belong together. Shown when at least 2 proposals had them together and confidence is 60% or more." title="Market-basket analysis with the Apriori algorithm: each approved proposal's budget is a basket of items. Confidence: of the proposals with the first items, the share that also had the second. Lift: how much more often they go together than by chance; above 1 means they belong together. Shown when at least 2 proposals had them together and confidence is 60% or more."></i>
            <span class="scope">All approved proposals</span></h3>
        <p class="a">{!! $pr['answer'] !!}</p>
        @if ($pr['rules'])
            <div class="table-scroll">
                <table class="rtable" style="min-width:560px;">
                    <thead>
                        <tr><th>If the budget has</th><th>It also had</th><th class="num">Together</th><th class="num">Confidence</th><th class="num">Lift</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($pr['rules'] as $r)
                            <tr>
                                <td>{{ $r['if_label'] }}</td>
                                <td>{{ $r['then_label'] }}</td>
                                <td class="num">{{ $r['together'] }} <span class="rank-meta" style="display:inline;">of {{ $pr['baskets'] }}</span></td>
                                <td class="num">{{ round($r['confidence'] * 100) }}% <span class="rank-meta" style="display:inline;">{{ $r['together'] }} of {{ $r['with_if'] }}</span></td>
                                <td class="num">{{ number_format($r['lift'], 1) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        <p class="sub-line"><span>While a budget is written, the form suggests the items these patterns point to.</span></p>
    </div>
@endif
</div>

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
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.5.1"></script>
<script>
const css = getComputedStyle(document.documentElement);
const cv = (name, fallback) => (css.getPropertyValue(name) || '').trim() || fallback;
const textMuted = cv('--text-3', '#898781');
const gridColor = cv('--border', '#e1e0d9');
const cat = ['--cat-1', '--cat-2', '--cat-3'].map((n, i) => cv(n, ['#058028', '#2f6fb5', '#b08900'][i]));

Chart.defaults.font.size = 11;
Chart.defaults.color = textMuted;

const baseScales = (yExtra = {}) => ({
    x: { grid: { display: false }, ticks: { color: textMuted, maxRotation: 0, autoSkip: true, maxTicksLimit: 12 } },
    y: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: textMuted, precision: 0 }, ...yExtra },
});

@if ($tab === 'records')
// ---- Activity over time ----
const trend = @json($trend);
new Chart(document.getElementById('trendChart'), {
    type: 'line',
    data: {
        labels: trend.labels,
        datasets: [
@if ($isSuperAdmin)
            { label: 'Logged actions', data: trend.actions, borderColor: cat[2], backgroundColor: cat[2], borderWidth: 2, pointRadius: 2, tension: 0.25 },
@endif
            { label: 'Files uploaded', data: trend.uploads, borderColor: cat[1], backgroundColor: cat[1], borderWidth: 2, pointRadius: 2, tension: 0.25 },
            { label: 'Documents digitized', data: trend.documents, borderColor: cat[0], backgroundColor: cat[0], borderWidth: 2, pointRadius: 2, tension: 0.25 },
        ],
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: { legend: { position: 'top', align: 'end', labels: { boxWidth: 10, boxHeight: 10 } } },
        scales: baseScales(),
    },
});

// ---- Storage growth + projection ----
const storageTrend = @json($storageTrend);
const projection = @json($projection);

const labels = [...storageTrend.map(p => p.label), ...projection.map(p => p.label)];
// Projected series starts from the last actual point so the two lines
// connect, with nulls elsewhere so Chart.js doesn't draw it across history.
const actualData = [...storageTrend.map(p => p.bytes), ...projection.map(() => null)];
const projectedData = [
    ...storageTrend.map(() => null),
    ...(storageTrend.length ? [storageTrend[storageTrend.length - 1].bytes] : []),
    ...projection.map(p => p.bytes),
];
if (projectedData.length > labels.length) projectedData.length = labels.length;
while (projectedData.length < labels.length) projectedData.push(null);

function formatBytesShort(bytes) {
    if (bytes >= 1073741824) return (bytes / 1073741824).toFixed(2) + ' GB';
    return (bytes / 1048576).toFixed(1) + ' MB';
}

new Chart(document.getElementById('storageChart'), {
    type: 'line',
    data: {
        labels,
        datasets: [
            { label: 'Actual', data: actualData, borderColor: cat[0], backgroundColor: 'rgba(5,128,40,0.08)', borderWidth: 2, pointRadius: 3, pointBackgroundColor: cat[0], fill: true, tension: 0.25, spanGaps: false },
            { label: 'Projected', data: projectedData, borderColor: cat[0], borderDash: [6, 5], borderWidth: 2, pointRadius: 3, pointBackgroundColor: 'transparent', pointBorderColor: cat[0], fill: false, tension: 0.25, spanGaps: true },
        ],
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: {
            legend: { position: 'top', align: 'end', labels: { boxWidth: 10, boxHeight: 10 } },
            tooltip: { callbacks: { label: (ctx) => ctx.dataset.label + ': ' + formatBytesShort(ctx.parsed.y) } },
        },
        scales: baseScales({ ticks: { color: textMuted, callback: (v) => formatBytesShort(v) } }),
    },
});
@elseif ($tab === 'engagement')
// ---- Visits over time ----
const visits = @json($engagement['visits']);
const visitsEl = document.getElementById('engVisitsChart');
if (visitsEl) {
    new Chart(visitsEl, {
        type: 'line',
        data: {
            labels: visits.labels,
            datasets: [
                { label: 'Visits', data: visits.visits, borderColor: cat[0], backgroundColor: 'rgba(5,128,40,0.10)', borderWidth: 2, pointRadius: 2, tension: 0.25, fill: true },
                { label: 'First-time visitors', data: visits.fresh, borderColor: cat[1], backgroundColor: cat[1], borderWidth: 2, pointRadius: 2, tension: 0.25 },
            ],
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { position: 'top', align: 'end', labels: { boxWidth: 10, boxHeight: 10 } } },
            scales: baseScales(),
        },
    });
}
@endif
</script>

@endsection
