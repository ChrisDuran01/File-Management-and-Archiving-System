@extends('SuperAdmin.homeSuperAdmin')
@section('content')

@include('Admin.partials.theme')

<style>
    /* ── Dashboard ──
       Every section is the same .dash-card, and the four headline cards come
       from one loop, so the page always looks like one system. Colours come
       only from the theme tokens: green is the one accent, amber/red are
       kept for things that need attention. */

    .dash-actions { display: flex; flex-wrap: wrap; gap: 8px; }
    .dash-actions .btn-flat { display: inline-flex; align-items: center; gap: 6px; }
    .dash-actions .btn-flat:not(.ghost) { border: 1.5px solid var(--primary); }

    .dash-card {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        box-shadow: var(--shadow-sm);
        min-width: 0;
    }
    .dash-card-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
        padding: 18px 20px 0;
    }
    .dash-card-head h3 { font-size: 15px; font-weight: 600; color: var(--text-1); margin: 0; }
    .dash-card-head p { font-size: 12.5px; color: var(--text-2); margin: 3px 0 0; }
    .dash-card-link { font-size: 12.5px; font-weight: 500; color: var(--primary); text-decoration: none; white-space: nowrap; }
    .dash-card-link:hover { color: var(--primary-dark); text-decoration: underline; }
    .dash-card-body { padding: 16px 20px 20px; }
    .dash-card-body.is-list { padding: 8px 0 6px; }

    .badge-soft { display: inline-flex; align-items: center; gap: 4px; padding: 3px 9px; border-radius: 999px; font-size: 12px; font-weight: 600; white-space: nowrap; }
    .badge-soft.ok      { background: var(--success-dim); color: var(--success-text); }
    .badge-soft.warning { background: var(--warning-dim); color: var(--warning); }
    .badge-soft.danger  { background: var(--danger-dim);  color: var(--danger); }

    .dash-empty { text-align: center; padding: 28px 16px; color: var(--text-3); }
    .dash-empty i { display: block; font-size: 26px; margin-bottom: 8px; }
    .dash-empty.ok i { color: var(--success); }
    .dash-empty p { margin: 0; font-size: 13px; color: var(--text-2); }

    /* Headline cards */
    .kpi-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; margin-bottom: 16px; }
    .kpi {
        display: flex;
        flex-direction: column;
        gap: 12px;
        padding: 18px 20px;
        color: var(--text-1);
        text-decoration: none;
        transition: border-color .15s, box-shadow .15s;
    }
    .kpi:hover { border-color: color-mix(in srgb, var(--primary) 45%, var(--border)); box-shadow: var(--shadow-md); color: var(--text-1); }
    .kpi:focus-visible { outline: 2px solid var(--primary); outline-offset: 2px; }
    .kpi-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
    .kpi-label { font-size: 13px; font-weight: 500; color: var(--text-2); }
    .kpi-icon {
        width: 34px; height: 34px; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        border-radius: var(--radius-sm);
        background: var(--primary-dim); color: var(--primary);
        font-size: 16px;
    }
    .kpi-row { display: flex; align-items: flex-end; justify-content: space-between; gap: 12px; }
    .kpi-value { font-size: 28px; font-weight: 600; letter-spacing: -.6px; line-height: 1.1; white-space: nowrap; }
    .kpi-foot { display: flex; align-items: center; flex-wrap: wrap; gap: 6px 8px; font-size: 12.5px; color: var(--text-2); }

    /* Sparkline: muted line, the current period as the one green dot */
    .spark { position: relative; width: 104px; height: 38px; flex-shrink: 0; color: var(--text-3); }
    .spark svg { position: absolute; inset: 0; width: 100%; height: 100%; overflow: visible; }
    .spark polyline { fill: none; stroke: currentColor; stroke-width: 2; stroke-linejoin: round; stroke-linecap: round; vector-effect: non-scaling-stroke; }
    .spark-pt { position: absolute; width: 22px; height: 22px; transform: translate(-50%, -50%); }
    .spark-pt::before {
        content: ''; position: absolute; left: 50%; top: 50%;
        width: 8px; height: 8px; margin: -4px 0 0 -4px; border-radius: 50%;
        background: var(--text-3); box-shadow: 0 0 0 2px var(--card);
        opacity: 0; transition: opacity .12s;
    }
    .spark-pt.is-now::before { background: var(--primary); opacity: 1; }
    .spark-pt:hover::before { opacity: 1; }
    .spark-pt::after {
        content: attr(data-tip);
        position: absolute; bottom: calc(100% + 2px); right: 0;
        padding: 4px 8px; border-radius: 6px; white-space: nowrap;
        font-size: 12px; font-weight: 500;
        background: var(--text-1); color: var(--card);
        opacity: 0; pointer-events: none; transition: opacity .12s;
        z-index: 5;
    }
    .spark-pt:hover::after { opacity: 1; }

    /* Two-column body */
    .dash-grid { display: grid; grid-template-columns: minmax(0, 2fr) minmax(0, 1fr); gap: 16px; align-items: start; }
    .dash-col { display: flex; flex-direction: column; gap: 16px; min-width: 0; }

    /* Activity chart */
    .chart-legend { display: flex; flex-wrap: wrap; gap: 14px; font-size: 12.5px; color: var(--text-2); }
    .chart-legend span { display: inline-flex; align-items: center; gap: 6px; }
    .chart-legend .key-bar  { width: 10px; height: 10px; border-radius: 2px; background: var(--chart-uploads); }
    .chart-legend .key-line { width: 14px; height: 2px; border-radius: 1px; background: var(--chart-actions); }
    .chart-box { position: relative; height: 260px; }
    :root { --chart-uploads: #058028; --chart-actions: #6b7280; }
    :root[data-theme="dark"] { --chart-uploads: #2e9c42; --chart-actions: #8f8e88; }

    /* Lists: needs attention, activity feed, recent uploads */
    .dash-list { list-style: none; margin: 0; padding: 0; }
    .dash-list > li + li { border-top: 1px solid var(--border); }
    .dash-row { display: flex; align-items: center; gap: 12px; padding: 11px 20px; color: var(--text-1); text-decoration: none; }
    a.dash-row:hover { background: var(--surface); color: var(--text-1); }
    .row-icon {
        width: 32px; height: 32px; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        border-radius: var(--radius-sm);
        background: var(--surface); color: var(--text-2);
        font-size: 15px;
    }
    .row-icon.danger  { background: var(--danger-dim);  color: var(--danger); }
    .row-icon.warning { background: var(--warning-dim); color: var(--warning); }
    .row-main { flex: 1; min-width: 0; font-size: 13.5px; line-height: 1.4; }
    .row-main strong { font-weight: 600; }
    .row-sub { display: block; font-size: 12px; color: var(--text-3); margin-top: 1px; }
    .row-end { font-size: 12px; color: var(--text-3); white-space: nowrap; flex-shrink: 0; }
    .row-go { color: var(--text-3); font-size: 12px; flex-shrink: 0; }

    .avatar {
        width: 32px; height: 32px; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        border-radius: 50%;
        background: var(--primary-dim); color: var(--primary-dark);
        font-size: 12px; font-weight: 600;
    }
    .avatar.is-alert { background: var(--danger-dim); color: var(--danger); }
    .feed-text { color: var(--text-2); overflow-wrap: anywhere; }
    .feed-text strong { color: var(--text-1); }
    .file-name { display: block; color: var(--text-1); font-weight: 500; text-decoration: none; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .file-name:hover { color: var(--primary); }

    /* System health */
    .health-row { display: flex; align-items: flex-start; gap: 10px; padding: 10px 20px; font-size: 13px; }
    .health-row i { font-size: 15px; line-height: 1.3; flex-shrink: 0; }
    .health-row .st-ok      { color: var(--success); }
    .health-row .st-warning { color: var(--warning); }
    .health-row .st-danger  { color: var(--danger); }
    .health-label { color: var(--text-1); font-weight: 500; white-space: nowrap; }
    .health-detail { margin-left: auto; text-align: right; color: var(--text-2); }

    /* Storage by type */
    .type-row + .type-row { margin-top: 14px; }
    .type-top { display: flex; justify-content: space-between; gap: 12px; font-size: 13px; margin-bottom: 6px; }
    .type-top span:first-child { color: var(--text-1); font-weight: 500; }
    .type-top span:last-child { color: var(--text-2); white-space: nowrap; }
    .bar { height: 6px; border-radius: 3px; background: var(--primary-dim); overflow: hidden; }
    .bar i { display: block; height: 100%; border-radius: 3px; background: var(--primary); }
    .type-meta { font-size: 12px; color: var(--text-3); margin-top: 4px; }

    /* Student page snapshot */
    .mini-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
    .mini { background: var(--surface); border-radius: var(--radius-sm); padding: 12px 14px; }
    .mini b { display: block; font-size: 20px; font-weight: 600; color: var(--text-1); line-height: 1.2; }
    .mini span { font-size: 12px; color: var(--text-2); }
    .facts { list-style: none; margin: 14px 0 0; padding: 0; font-size: 13px; color: var(--text-2); }
    .facts li { display: flex; justify-content: space-between; gap: 12px; padding: 9px 0; border-top: 1px solid var(--border); }
    .facts li b { color: var(--text-1); font-weight: 500; text-align: right; overflow-wrap: anywhere; }

    @media (max-width: 1199.98px) {
        .kpi-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    /* One column: the two column wrappers dissolve so the most urgent card leads */
    @media (max-width: 991.98px) {
        .dash-grid { grid-template-columns: minmax(0, 1fr); }
        .dash-col { display: contents; }
        .o-1 { order: 1; } .o-2 { order: 2; } .o-3 { order: 3; } .o-4 { order: 4; }
        .o-5 { order: 5; } .o-6 { order: 6; } .o-7 { order: 7; }
    }
    @media (max-width: 575.98px) {
        .kpi-grid { gap: 12px; }
        .kpi { padding: 16px; }
        .kpi-value { font-size: 24px; }
        .kpi-row { flex-direction: column; align-items: stretch; }
        .spark { width: 100%; }
        .dash-row, .health-row { padding-left: 16px; padding-right: 16px; }
        .dash-card-head { padding: 16px 16px 0; }
        .dash-card-body { padding: 14px 16px 16px; }
    }
</style>

@php
    $initials = fn (string $name) => collect(preg_split('/[\s@._-]+/', trim($name)))
        ->filter()->take(2)->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))->implode('');

    $fileIcon = fn (string $name) => match (strtolower(pathinfo($name, PATHINFO_EXTENSION))) {
        'pdf' => 'bi-file-earmark-pdf',
        'png', 'jpg', 'jpeg', 'gif', 'webp' => 'bi-file-earmark-image',
        'doc', 'docx' => 'bi-file-earmark-word',
        'xls', 'xlsx' => 'bi-file-earmark-excel',
        'ppt', 'pptx' => 'bi-file-earmark-ppt',
        default => 'bi-file-earmark',
    };

    $fileSize = fn ($bytes) => $bytes >= 1048576
        ? number_format($bytes / 1048576, 1) . ' MB'
        : max(1, (int) round($bytes / 1024)) . ' KB';

    $attentionLevel = collect($attention)->contains('level', 'danger') ? 'danger'
        : (collect($attention)->contains('level', 'warning') ? 'warning' : 'ok');
    $healthIssues = collect($health)->where('status', '!=', 'ok')->count();
    $healthIcon = ['ok' => 'bi-check-circle-fill', 'warning' => 'bi-exclamation-triangle-fill', 'danger' => 'bi-x-circle-fill'];
@endphp

{{-- Skeleton placeholder, shown until the real content below is revealed --}}
<div class="skel-shell" aria-hidden="true">
    <div class="page-header">
        <div>
            <div class="skel skel-text" style="width:220px; height:24px; margin-bottom:8px;"></div>
            <div class="skel skel-text" style="width:260px;"></div>
        </div>
    </div>
    <div class="kpi-grid">
        @for ($i = 0; $i < 4; $i++)
            <div class="dash-card kpi">
                <div class="skel skel-text" style="width:50%;"></div>
                <div class="skel skel-text" style="width:40%; height:26px;"></div>
                <div class="skel skel-text" style="width:70%;"></div>
            </div>
        @endfor
    </div>
    <div class="dash-grid">
        <div class="dash-col">
            <div class="dash-card dash-card-body"><div class="skel" style="height:290px; border-radius:8px;"></div></div>
            <div class="dash-card dash-card-body"><div class="skel" style="height:320px; border-radius:8px;"></div></div>
        </div>
        <div class="dash-col">
            <div class="dash-card dash-card-body"><div class="skel" style="height:200px; border-radius:8px;"></div></div>
            <div class="dash-card dash-card-body"><div class="skel" style="height:230px; border-radius:8px;"></div></div>
        </div>
    </div>
</div>

<div class="skel-real">

{{-- ── Header ── --}}
<div class="page-header">
    <div>
        <h2>{{ $greeting }}</h2>
        <p class="sub">Here's what's happening in Libris · {{ now()->format('l, F j, Y') }}</p>
    </div>
    <div class="dash-actions">
        <a href="/reports" class="btn-flat ghost"><i class="bi bi-bar-chart-line"></i> Reports</a>
        <a href="/backup" class="btn-flat ghost"><i class="bi bi-cloud-arrow-up"></i> Backups</a>
        <a href="/addAdmin" class="btn-flat"><i class="bi bi-person-plus"></i> Add officer</a>
    </div>
</div>

{{-- ── Headline numbers ── --}}
<div class="kpi-grid">
    @foreach ($kpis as $kpi)
        <a href="{{ $kpi['href'] }}" class="dash-card kpi">
            <div class="kpi-head">
                <span class="kpi-label">{{ $kpi['label'] }}</span>
                <span class="kpi-icon"><i class="bi {{ $kpi['icon'] }}"></i></span>
            </div>
            <div class="kpi-row">
                <div class="kpi-value">{{ $kpi['value'] }}</div>
                @if ($kpi['trend'])
                    @php
                        // Geometry in a 0-100 box: the same numbers place the SVG
                        // line and the HTML dots (which stay round when it stretches).
                        $values = array_column($kpi['trend'], 'value');
                        $min = min($values);
                        $range = max($values) - $min;
                        $last = count($values) - 1;
                        $points = collect($kpi['trend'])->map(fn ($p, $i) => $p + [
                            'x' => round(4 + $i * 92 / max(1, $last), 2),
                            'y' => $range > 0 ? round(84 - ($p['value'] - $min) / $range * 68, 2) : 50,
                        ]);
                    @endphp
                    <div class="spark" role="img"
                         aria-label="{{ $kpi['label'] }}: {{ $points->first()['display'] }} in {{ $points->first()['label'] }}, {{ $points->last()['display'] }} now">
                        <svg viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
                            <polyline points="{{ $points->map(fn ($p) => $p['x'] . ',' . $p['y'])->implode(' ') }}"/>
                        </svg>
                        @foreach ($points as $i => $p)
                            <span class="spark-pt{{ $i === $last ? ' is-now' : '' }}"
                                  style="left: {{ $p['x'] }}%; top: {{ $p['y'] }}%;"
                                  data-tip="{{ $p['label'] }} · {{ $p['display'] }}"></span>
                        @endforeach
                    </div>
                @endif
            </div>
            <div class="kpi-foot">
                @if ($kpi['chip'])
                    <span class="badge-soft ok"><i class="bi bi-arrow-up-right"></i>{{ $kpi['chip']['text'] }}</span>
                @endif
                <span>{{ $kpi['note'] }}</span>
            </div>
        </a>
    @endforeach
</div>

<div class="dash-grid">

    {{-- ── Left column ── --}}
    <div class="dash-col">

        <section class="dash-card o-2">
            <header class="dash-card-head">
                <div>
                    <h3>Activity</h3>
                    <p>Last 30 days · {{ number_format($activityChart['totalUploads']) }} {{ Str::plural('file', $activityChart['totalUploads']) }} uploaded, {{ number_format($activityChart['totalActions']) }} {{ Str::plural('action', $activityChart['totalActions']) }} logged</p>
                </div>
                <div class="chart-legend" aria-hidden="true">
                    <span><i class="key-bar"></i>Files uploaded</span>
                    <span><i class="key-line"></i>Logged actions</span>
                </div>
            </header>
            <div class="dash-card-body">
                @if ($activityChart['totalUploads'] + $activityChart['totalActions'] > 0)
                    <div class="chart-box">
                        <canvas id="activityChart" role="img" aria-label="Files uploaded and actions logged per day over the last 30 days"></canvas>
                    </div>
                @else
                    <div class="dash-empty"><i class="bi bi-graph-up"></i><p>No uploads or logged actions in the last 30 days.</p></div>
                @endif
            </div>
        </section>

        <section class="dash-card o-4">
            <header class="dash-card-head">
                <div>
                    <h3>Recent activity</h3>
                    <p>What officers did most recently</p>
                </div>
                <a href="/activityLogs" class="dash-card-link">View all</a>
            </header>
            <div class="dash-card-body is-list">
                @if ($activities->isNotEmpty())
                    <ul class="dash-list">
                        @foreach ($activities as $log)
                            @php $isAlert = preg_match('/^(Failed login|Login blocked|Login attempt on disabled)/', $log->activity); @endphp
                            <li class="dash-row">
                                <span class="avatar{{ $isAlert ? ' is-alert' : '' }}">
                                    @if ($isAlert)<i class="bi bi-shield-exclamation"></i>@else{{ $initials($log->user_name) }}@endif
                                </span>
                                <div class="row-main feed-text">
                                    <strong>{{ $log->user_name }}</strong> {{ $log->activity }}
                                    <time class="row-sub" datetime="{{ $log->created_at->toIso8601String() }}" title="{{ $log->created_at->format('M d, Y · h:i A') }}">{{ $log->created_at->diffForHumans() }}</time>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <div class="dash-empty"><i class="bi bi-inbox"></i><p>No activity recorded yet.</p></div>
                @endif
            </div>
        </section>

        <section class="dash-card o-5">
            <header class="dash-card-head">
                <div>
                    <h3>Recent uploads</h3>
                    <p>The latest files added to Libris</p>
                </div>
                <a href="/folders" class="dash-card-link">Open folders</a>
            </header>
            <div class="dash-card-body is-list">
                @if ($recentUploads->isNotEmpty())
                    <ul class="dash-list">
                        @foreach ($recentUploads as $file)
                            <li class="dash-row">
                                <span class="row-icon"><i class="bi {{ $fileIcon($file->filename) }}"></i></span>
                                <div class="row-main">
                                    <a href="{{ route('files.preview', $file->id) }}" class="file-name" title="{{ $file->filename }}">{{ $file->filename }}</a>
                                    <span class="row-sub">{{ $file->folder->name ?? 'No folder' }} · {{ $fileSize((float) $file->size) }}@if ($file->is_pending_approval) · <span style="color:var(--warning); font-weight:600;">Subject for approval</span>@endif</span>
                                </div>
                                <time class="row-end" datetime="{{ $file->created_at->toIso8601String() }}" title="{{ $file->created_at->format('M d, Y · h:i A') }}">{{ $file->created_at->diffForHumans(short: true) }}</time>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <div class="dash-empty"><i class="bi bi-cloud-upload"></i><p>No files uploaded yet.</p></div>
                @endif
            </div>
        </section>

    </div>

    {{-- ── Right column ── --}}
    <div class="dash-col">

        <section class="dash-card o-1">
            <header class="dash-card-head">
                <div>
                    <h3>Needs attention</h3>
                    <p>Things someone should look at</p>
                </div>
                @if (count($attention))
                    <span class="badge-soft {{ $attentionLevel }}">{{ count($attention) }}</span>
                @endif
            </header>
            <div class="dash-card-body is-list">
                @if (count($attention))
                    <ul class="dash-list">
                        @foreach ($attention as $item)
                            <li>
                                <a href="{{ $item['href'] }}" class="dash-row">
                                    <span class="row-icon {{ $item['level'] }}"><i class="bi {{ $item['icon'] }}"></i></span>
                                    <span class="row-main">
                                        @if (! is_null($item['count']))<strong>{{ number_format($item['count']) }}</strong>@endif
                                        {{ $item['text'] }}
                                    </span>
                                    <i class="bi bi-chevron-right row-go" aria-hidden="true"></i>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <div class="dash-empty ok"><i class="bi bi-check2-circle"></i><p>All clear. Nothing needs your attention.</p></div>
                @endif
            </div>
        </section>

        <section class="dash-card o-3">
            <header class="dash-card-head">
                <div>
                    <h3>System health</h3>
                    <p>Backups and background tasks</p>
                </div>
                @if ($healthIssues)
                    <span class="badge-soft warning">{{ $healthIssues }} to check</span>
                @else
                    <span class="badge-soft ok">All normal</span>
                @endif
            </header>
            <div class="dash-card-body is-list">
                <ul class="dash-list">
                    @foreach ($health as $row)
                        <li class="health-row">
                            <i class="bi {{ $healthIcon[$row['status']] }} st-{{ $row['status'] }}" role="img" aria-label="{{ $row['status'] === 'ok' ? 'OK' : ucfirst($row['status']) }}"></i>
                            <span class="health-label">{{ $row['label'] }}</span>
                            <span class="health-detail">{{ $row['detail'] }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>

        <section class="dash-card o-6">
            <header class="dash-card-head">
                <div>
                    <h3>Storage by type</h3>
                    <p>Where the space goes</p>
                </div>
            </header>
            <div class="dash-card-body">
                @forelse ($storageByType as $type)
                    <div class="type-row">
                        <div class="type-top"><span>{{ $type['kind'] }}</span><span>{{ $type['size'] }}</span></div>
                        <div class="bar" role="img" aria-label="{{ $type['kind'] }}: {{ $type['percent'] }}% of storage"><i style="width: {{ max($type['percent'], 1) }}%;"></i></div>
                        <div class="type-meta">{{ number_format($type['files']) }} {{ Str::plural('file', $type['files']) }} · {{ $type['percent'] }}%</div>
                    </div>
                @empty
                    <div class="dash-empty"><i class="bi bi-hdd"></i><p>Nothing stored yet.</p></div>
                @endforelse
            </div>
        </section>

        <section class="dash-card o-7">
            <header class="dash-card-head">
                <div>
                    <h3>Student page</h3>
                    <p>Last 7 days, anonymous</p>
                </div>
                <a href="/reports?tab=engagement" class="dash-card-link">Details</a>
            </header>
            <div class="dash-card-body">
                <div class="mini-grid">
                    <div class="mini"><b>{{ number_format($studentPage['visits']) }}</b><span>Visits</span></div>
                    <div class="mini"><b>{{ number_format($studentPage['views']) }}</b><span>Announcement views</span></div>
                    <div class="mini"><b>{{ number_format($studentPage['documents']) }}</b><span>Document opens</span></div>
                    <div class="mini"><b>{{ number_format($studentPage['searches']) }}</b><span>Searches</span></div>
                </div>
                <ul class="facts">
                    <li><span>Getting notifications</span><b>{{ number_format($studentPage['subscribers']) }} {{ Str::plural('device', $studentPage['subscribers']) }}</b></li>
                    <li><span>Searched, not found</span><b>{{ $studentPage['missed'] ? '“' . $studentPage['missed'] . '”' : 'Nothing' }}</b></li>
                </ul>
            </div>
        </section>

    </div>
</div>

</div>

@if ($activityChart['totalUploads'] + $activityChart['totalActions'] > 0)
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(() => {
    const canvas = document.getElementById('activityChart');
    if (! canvas || ! window.Chart) return;

    const data = @json($activityChart);

    // Colours come from the theme tokens, re-read when dark mode is toggled.
    const colors = () => {
        const css = getComputedStyle(document.documentElement);
        const v = name => css.getPropertyValue(name).trim();
        return {
            uploads: v('--chart-uploads'), actions: v('--chart-actions'),
            grid: v('--border'), tick: v('--text-3'),
            tipBg: v('--text-1'), tipText: v('--card'),
        };
    };

    const c = colors();
    const chart = new Chart(canvas, {
        data: {
            labels: data.labels,
            datasets: [
                {
                    type: 'line', label: 'Logged actions', data: data.actions, order: 1,
                    // monotone: the smoothed line never dips below zero between points
                    borderColor: c.actions, backgroundColor: c.actions, borderWidth: 2, cubicInterpolationMode: 'monotone',
                    pointRadius: 0, pointHoverRadius: 4, pointHitRadius: 10,
                },
                {
                    type: 'bar', label: 'Files uploaded', data: data.uploads, order: 2,
                    backgroundColor: c.uploads, borderRadius: 4, maxBarThickness: 12,
                },
            ],
        },
        options: {
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: c.tipBg, titleColor: c.tipText, bodyColor: c.tipText,
                    padding: 10, cornerRadius: 8, boxPadding: 4, usePointStyle: true,
                },
            },
            scales: {
                x: {
                    grid: { display: false },
                    border: { color: c.grid },
                    ticks: { color: c.tick, maxTicksLimit: 8, maxRotation: 0, font: { size: 11 } },
                },
                y: {
                    beginAtZero: true,
                    grid: { color: c.grid },
                    border: { display: false },
                    ticks: { color: c.tick, precision: 0, maxTicksLimit: 5, font: { size: 11 } },
                },
            },
        },
    });

    new MutationObserver(() => {
        const n = colors();
        const [actions, uploads] = chart.data.datasets;
        actions.borderColor = actions.backgroundColor = n.actions;
        uploads.backgroundColor = n.uploads;
        chart.options.scales.x.border.color = n.grid;
        chart.options.scales.y.grid.color = n.grid;
        chart.options.scales.x.ticks.color = chart.options.scales.y.ticks.color = n.tick;
        Object.assign(chart.options.plugins.tooltip, { backgroundColor: n.tipBg, titleColor: n.tipText, bodyColor: n.tipText });
        chart.update('none');
    }).observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
})();
</script>
@endif

@endsection
