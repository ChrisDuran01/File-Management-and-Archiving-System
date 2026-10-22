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

    /* Every panel leads with the question it answers; the answer is the
       finding, so it is the line that stands out. The chart below is the
       evidence. No extra description lines - a definition goes in an (i)
       tooltip, and a panel that ignores the selected period says so in a
       small .scope tag. */
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
    </div>
    <p class="period-note">
        {{ $periodLabel }} &middot; compared with {{ $comparedWith }}
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
            'stale'     => $staleFolders->isNotEmpty() ? 'warning' : null,
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
        $toneClass = fn ($key) => $tone[$key] ? ' tone-' . $tone[$key] : '';
        $flag = fn ($key) => $tone[$key] ? '<i class="fas fa-circle-exclamation flag" aria-hidden="true"></i><span class="sr-only">Needs attention:</span>' : '';
    @endphp

    {{-- KPI ROW - three cards for an Admin, five for a SuperAdmin. A change
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

    {{-- ACTIVITY TREND + BREAKDOWN --}}
    <div @if ($isSuperAdmin) class="row-2-wide" @endif>
        <div class="card-panel">
            <h3 class="q">Is the archive being kept up to date?</h3>
            <p class="a">{!! $answers['trend'] !!}</p>
            <div class="chart-box"><canvas id="trendChart"></canvas></div>
        </div>
        @if ($isSuperAdmin)
        <div class="card-panel">
            <h3 class="q">What do officers spend their time on?</h3>
            <p class="a">{!! $answers['categories'] !!}</p>
            @php $catMax = $activityByCategory->max('total') ?: 1; @endphp
            @forelse ($activityByCategory as $i => $row)
                <div class="rank-row">
                    <span class="rank-name" title="{{ $row['label'] }}">{{ $row['label'] }}</span>
                    <div class="rank-track"><div class="rank-fill" style="width:{{ round(($row['total'] / $catMax) * 100) }}%; background:{{ $row['label'] === 'Failed sign-ins' ? '#b3412f' : 'var(--seq-blue)' }};"></div></div>
                    <span class="rank-val">{{ number_format($row['total']) }}</span>
                </div>
            @empty
                <p class="empty-note">No activity recorded in this period.</p>
            @endforelse
        </div>
        @endif
    </div>

    {{-- DIGITIZATION --}}
    <div class="group-head"><i class="fas fa-file-import"></i> Digitization pipeline</div>
    <div class="card-panel{{ $toneClass('pipeline') }}">
        <h3 class="q">{!! $flag('pipeline') !!}Is digitization keeping up?</h3>
        <p class="a">{!! $answers['pipeline'] !!}</p>
        @php $hours = $pipeline['turnaround']; @endphp
        <div class="mini-grid">
            <div class="mini"><p class="k">Scan batches</p><p class="v">{{ number_format($pipeline['batches']) }}</p><p class="h">{{ number_format($pipeline['pages']) }} pages</p></div>
            <div class="mini"><p class="k">Filed</p><p class="v">{{ number_format($pipeline['filed']) }}</p><p class="h">{{ number_format($pipeline['docs_from_scans']) }} documents</p></div>
            <div class="mini"><p class="k">Success rate</p><p class="v">{{ $pipeline['success_rate'] !== null ? $pipeline['success_rate'].'%' : '—' }}</p></div>
            <div class="mini"><p class="k">Scan to filed</p><p class="v">{{ $hours === null ? '—' : ($hours < 1 ? max(1, (int) round($hours * 60)).' min' : $hours.' h') }}</p></div>
            <div class="mini"><p class="k">Waiting for review</p><p class="v" @if($pipeline['awaiting'] > 0) style="color:var(--warning);" @endif>{{ number_format($pipeline['awaiting']) }}</p></div>
        </div>

        <div class="row-2">
            <div>
                <h4 class="q sm">Did any scan batches fail?</h4>
                <p class="a">{!! $answers['batches'] !!}</p>
                @php $batchTotal = $batchStatuses->sum('total'); @endphp
                @if ($batchTotal > 0)
                    <div class="prop-bar">
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
            </div>
            <div>
                <h4 class="q sm">What kinds of records are being digitized?</h4>
                <p class="a">{!! $answers['types'] !!}</p>
                @php $typeMax = $documentTypes->max('total') ?: 1; @endphp
                @foreach ($documentTypes as $i => $t)
                    <div class="rank-row" style="margin-bottom:9px;">
                        <span class="rank-name" title="{{ $t['label'] }}">{{ $t['label'] }}</span>
                        <div class="rank-track"><div class="rank-fill" style="width:{{ round(($t['total'] / $typeMax) * 100) }}%; background:{{ $catColors[$i % 8] }};"></div></div>
                        <span class="rank-val">{{ $t['total'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- USAGE PATTERNS - who works when, from the Activity Logs. SuperAdmin-only. --}}
    @if ($isSuperAdmin)
    <div class="group-head"><i class="fas fa-clock"></i> Usage patterns</div>
    <div class="row-3">
        <div class="card-panel">
            <h3 class="q">When in the day do officers work?</h3>
            <p class="a">{!! $answers['hours'] !!}</p>
            <div class="chart-box short"><canvas id="hourChart"></canvas></div>
        </div>
        <div class="card-panel">
            <h3 class="q">Which days are busiest?</h3>
            <p class="a">{!! $answers['weekdays'] !!}</p>
            <div class="chart-box short"><canvas id="weekdayChart"></canvas></div>
        </div>
        <div class="card-panel">
            <h3 class="q">Is the work shared across officers?</h3>
            <p class="a">{!! $answers['officers'] !!}</p>
            @php $max = $activeOfficers->max('total') ?: 1; @endphp
            @foreach ($activeOfficers as $officer)
                <div class="rank-row">
                    <span class="rank-name" title="{{ $officer->user_name }}">{{ $officer->user_name }}</span>
                    <div class="rank-track"><div class="rank-fill" style="width:{{ round(($officer->total / $max) * 100) }}%; background:var(--seq-blue);"></div></div>
                    <span class="rank-val">{{ number_format($officer->total) }}</span>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- STORAGE --}}
    <div class="group-head"><i class="fas fa-hard-drive"></i> Storage &amp; library</div>
    <div class="row-2-wide">
        <div class="card-panel">
            <h3 class="q">Will storage run out? <span class="scope">Last 6 months</span></h3>
            <p class="a">{!! $answers['storage'] !!}</p>
            <div class="chart-box"><canvas id="storageChart"></canvas></div>
        </div>
        <div class="card-panel">
            <h3 class="q">Where is the storage going? <span class="scope">All time</span></h3>
            <p class="a">{!! $answers['folders'] !!}</p>
            @php $fMax = $topFolders->max('bytes') ?: 1; @endphp
            @foreach ($topFolders as $f)
                <div class="rank-row">
                    <span class="rank-name" title="{{ $f->name }}">{{ $f->name }}</span>
                    <div class="rank-track"><div class="rank-fill" style="width:{{ round(($f->bytes / $fMax) * 100) }}%; background:var(--cat-2);"></div></div>
                    <span class="rank-val" style="min-width:70px;" title="{{ $f->files }} files">{{ $f->formatted }}</span>
                </div>
            @endforeach
        </div>
    </div>

    <div class="row-2">
        <div class="card-panel">
            <h3 class="q">What is the library made of? <span class="scope">All time</span></h3>
            <p class="a">{!! $answers['fileTypes'] !!}</p>
            @php $typeColors = ['var(--cat-1)', 'var(--cat-2)', 'var(--cat-3)', 'var(--cat-4)']; @endphp
            <div class="prop-bar">
                @foreach ($fileTypes as $i => $type)
                    @if ($type['percent'] > 0)
                        <div class="prop-seg" style="width:{{ $type['percent'] }}%; background:{{ $typeColors[$i] }};" title="{{ $type['label'] }} {{ $type['percent'] }}%"></div>
                    @endif
                @endforeach
            </div>
            <div class="prop-legend">
                @foreach ($fileTypes as $i => $type)
                    <span><span class="swatch" style="background:{{ $typeColors[$i] }};"></span>{{ $type['label'] }} {{ $type['percent'] }}% ({{ number_format($type['total']) }})</span>
                @endforeach
            </div>
        </div>
        <div class="card-panel{{ $toneClass('stale') }}">
            <h3 class="q">{!! $flag('stale') !!}Which folders have gone quiet?</h3>
            <p class="a">{!! $answers['stale'] !!}</p>
            @foreach ($staleFolders as $folder)
                <div class="rank-row" style="margin-bottom:10px;">
                    <span class="rank-name" style="width:auto; flex:1;">{{ $folder['name'] }}</span>
                    <span class="status-chip {{ $folder['status'] }}"><i class="fas fa-circle"></i> {{ $folder['days_stale'] }}d untouched</span>
                </div>
            @endforeach
        </div>
    </div>

    {{-- PRESERVATION & HEALTH --}}
    <div class="group-head"><i class="fas fa-shield-heart"></i> Preservation &amp; health</div>
    <div class="{{ $isSuperAdmin ? 'row-3' : 'row-2' }}">
        <div class="card-panel{{ $toneClass('integrity') }}">
            <h3 class="q">{!! $flag('integrity') !!}Are archived records still intact?</h3>
            <p class="a">{!! $answers['integrity'] !!}</p>
            <div class="stat-line"><span>Verified intact</span><span class="n"><span class="status-chip ok">{{ $preservation['fixity_ok'] }}</span></span></div>
            <div class="stat-line"><span>Failed verification</span><span class="n"><span class="status-chip {{ $preservation['fixity_failed'] > 0 ? 'critical' : 'ok' }}">{{ $preservation['fixity_failed'] }}</span></span></div>
            <div class="stat-line"><span>Not yet checked</span><span class="n">{{ $preservation['fixity_unchecked'] }}</span></div>
            <div class="stat-line"><span>Last check</span><span class="n">{{ $preservation['last_fixity'] ? $preservation['last_fixity']->diffForHumans() : 'never' }}</span></div>
        </div>
        @if ($isSuperAdmin)
        <div class="card-panel{{ $toneClass('backups') }}">
            <h3 class="q">{!! $flag('backups') !!}Are backups running?</h3>
            <p class="a">{!! $answers['backups'] !!}</p>
            <div class="stat-line"><span>Local only / partial</span><span class="n"><span class="status-chip {{ $backups['partial'] > 0 ? 'warning' : 'ok' }}">{{ $backups['partial'] }}</span></span></div>
            <div class="stat-line"><span>Last backup</span>
                <span class="n">
                    @if ($backups['last'])
                        <span class="status-chip {{ $backups['last_age_hours'] > 24 * 7 ? 'critical' : ($backups['last_age_hours'] > 48 ? 'warning' : 'ok') }}">{{ $backups['last']->diffForHumans() }}</span>
                    @else
                        <span class="status-chip critical">never</span>
                    @endif
                </span>
            </div>
        </div>
        @endif
        <div class="card-panel{{ $toneClass('ocr') }}">
            <h3 class="q">{!! $flag('ocr') !!}Can every file be found by its text?</h3>
            <p class="a">{!! $answers['ocr'] !!}</p>
            <div class="prop-bar" style="height:8px;" role="img" aria-label="{{ $ocr['coverage'] ?? 0 }}% of files have searchable text"><div class="prop-seg" style="width:{{ $ocr['coverage'] ?? 0 }}%; background:var(--seq-blue);"></div></div>
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
            <h3 class="q">{!! $flag('copies') !!}Are there copies of the same document? <span class="scope">All time</span></h3>
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
            <h3 class="q">Which documents read alike? <span class="scope">All time</span></h3>
            <p class="a">{!! $answers['map'] !!}</p>
            @if (count($insights['map']) >= 3)
                <div class="chart-box" style="height:300px;"><canvas id="docMapChart" role="img" aria-label="Map of {{ count($insights['map']) }} documents placed by how alike their text is"></canvas></div>
            @endif
        </div>
    </div>

    <div class="row-2">
        <div class="card-panel">
            <h3 class="q">Are any files in the wrong folder? <span class="scope">All time</span></h3>
            <p class="a">{!! $answers['misfiled'] !!}</p>
            @foreach ($insights['misfiled'] as $m)
                <div style="padding:9px 0; border-bottom:1px solid var(--gridline);">
                    <div style="font-size:13px; font-weight:500; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="{{ $m['file']['name'] }}">{{ $m['file']['name'] }}</div>
                    <div style="font-size:12px; color:var(--text-muted);">
                        In <a href="{{ route('folders.show', $m['file']['folder_id']) }}">{{ $m['file']['folder'] }}</a> ({{ $m['own_match'] }}% match)
                        &rarr; reads like <a href="{{ route('folders.show', $m['suggested']['id']) }}">{{ $m['suggested']['name'] }}</a> ({{ $m['other_match'] }}% match)
                    </div>
                </div>
            @endforeach
        </div>

        <div class="card-panel">
            <h3 class="q">What is each folder about? <span class="scope">All time</span></h3>
            <p class="a">{!! $answers['topics'] !!}</p>
            @foreach ($insights['folderTopics'] as $t)
                <div style="padding:8px 0; border-bottom:1px solid var(--gridline);">
                    <div style="font-size:13px; font-weight:500;">{{ $t['folder'] }} <span style="color:var(--text-muted); font-weight:400;">({{ $t['files'] }} {{ Str::plural('file', $t['files']) }})</span></div>
                    <div style="font-size:12px; color:var(--text-secondary);">{{ implode(' · ', $t['terms']) }}</div>
                </div>
            @endforeach
        </div>
    </div>

    @if ($isSuperAdmin)
    {{-- SECURITY: LOGIN ANOMALIES - pattern detection over activity_logs,
         not a raw count. SuperAdmin-only. --}}
    <div class="group-head"><i class="fas fa-shield-halved"></i> Security</div>
    <div class="card-panel{{ $toneClass('security') }}">
        <h3 class="q">{!! $flag('security') !!}Is anyone trying to break in? <span class="scope">Last 7 days</span></h3>
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

@else
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
            <h3 class="q">Are students visiting the student page?
                <i class="fas fa-circle-info info" tabindex="0" role="img" aria-label="A visit is one device opening the page on a given day; reloads don't count." title="A visit is one device opening the page on a given day; reloads don't count."></i></h3>
            <p class="a">{!! $e['visits']['answer'] !!}</p>
            @if (array_sum($e['visits']['visits']) > 0)
                <div class="chart-box"><canvas id="engVisitsChart"></canvas></div>
            @endif
        </div>
        <div class="stack">
            <div class="card-panel">
                <h3 class="q">Do students use phones or computers?</h3>
                <p class="a">{!! $e['devices']['answer'] !!}</p>
                @if ($e['devices']['total'] > 0)
                    <div class="prop-bar">
                        @foreach ($e['devices']['rows'] as $i => $d)
                            @if ($d['percent'] > 0)
                                <div class="prop-seg" style="width:{{ $d['percent'] }}%; background:{{ $catColors[$i] }};" title="{{ $d['label'] }} {{ $d['percent'] }}%"></div>
                            @endif
                        @endforeach
                    </div>
                    <div class="prop-legend">
                        @foreach ($e['devices']['rows'] as $i => $d)
                            <span><span class="swatch" style="background:{{ $catColors[$i] }};"></span>{{ $d['label'] }} {{ $d['percent'] }}%</span>
                        @endforeach
                    </div>
                @endif
            </div>
            <div class="card-panel">
                <h3 class="q">Do notifications bring students back?</h3>
                <p class="a">{!! $e['notifications']['answer'] !!}</p>
                <div class="stat-line"><span>Turned on in this period</span><span class="n">{{ number_format($e['notifications']['on']) }}</span></div>
                <div class="stat-line"><span>Turned off in this period</span><span class="n">{{ number_format($e['notifications']['off']) }}</span></div>
            </div>
        </div>
    </div>

    <div class="card-panel">
        <h3 class="q">When are students on the page, and when should we post?</h3>
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
            <h3 class="q">Which announcements reached the most students?
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
            <h3 class="q">How quickly do students see a new announcement?
                <i class="fas fa-circle-info info" tabindex="0" role="img" aria-label="Views of announcements posted in this period, by time since posting." title="Views of announcements posted in this period, by time since posting."></i></h3>
            <p class="a">{!! $e['reach']['answer'] !!}</p>
            @if ($e['reach']['enough'])
                <div class="chart-box short"><canvas id="engReachChart"></canvas></div>
            @endif
        </div>
    </div>

    <div class="row-2">
        <div class="card-panel">
            <h3 class="q">Which documents do students use?</h3>
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
        <div class="card-panel">
            <h3 class="q">What are students searching for but not finding?</h3>
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

@if ($isSuperAdmin)
// ---- Busiest hours / days ----
new Chart(document.getElementById('hourChart'), {
    type: 'bar',
    data: { labels: @json($hourLabels), datasets: [{ label: 'Actions', data: @json(array_values($byHour)), backgroundColor: cat[0], borderRadius: 3 }] },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: baseScales() },
});
new Chart(document.getElementById('weekdayChart'), {
    type: 'bar',
    data: { labels: @json($weekdayLabels), datasets: [{ label: 'Actions', data: @json(array_values($byWeekday)), backgroundColor: cat[1], borderRadius: 3 }] },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: baseScales() },
});
@endif

// ---- Document map (scatter) ----
// ArchiveInsights names the two largest folders and puts the rest in
// "Other folders", so three colours - each with its own shape too. A file
// that reads like another folder is a larger triangle.
const mapPoints = @json($insights['map']);
const mapEl = document.getElementById('docMapChart');
if (mapEl && mapPoints.length) {
    const shapes = ['circle', 'rect', 'rectRot'];
    const groups = {};
    mapPoints.forEach(p => { (groups[p.folder] = groups[p.folder] || []).push(p); });
    const names = Object.keys(groups).sort((a, b) => (a === 'Other folders') - (b === 'Other folders') || groups[b].length - groups[a].length);

    new Chart(mapEl, {
        type: 'scatter',
        data: {
            datasets: names.map((name, i) => ({
                label: name,
                data: groups[name],
                backgroundColor: cat[Math.min(i, 2)] + 'cc',
                borderColor: cat[Math.min(i, 2)],
                pointRadius: groups[name].map(p => p.moved ? 8 : 5),
                pointStyle: groups[name].map(p => p.moved ? 'triangle' : shapes[Math.min(i, 2)]),
            })),
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true } },
                tooltip: { callbacks: { label: (c) => c.raw.file + (c.raw.moved ? '  (reads like another folder)' : '') } },
            },
            scales: {
                x: { grid: { color: gridColor }, ticks: { display: false }, title: { display: true, text: 'how alike the text is (no units)', color: textMuted } },
                y: { grid: { color: gridColor }, ticks: { display: false } },
            },
        },
    });
}

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
@else
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

// ---- How fast announcements reach students ----
const reach = @json($engagement['reach']);
const reachEl = document.getElementById('engReachChart');
if (reachEl) {
    new Chart(reachEl, {
        type: 'bar',
        data: { labels: reach.labels, datasets: [{ label: 'Share of views', data: reach.percent, backgroundColor: cat[1], borderRadius: 3 }] },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip: { callbacks: { label: (ctx) => ctx.parsed.y + '% of views' } } },
            scales: baseScales({ ticks: { color: textMuted, callback: (v) => v + '%' } }),
        },
    });
}
@endif
</script>

@endsection
