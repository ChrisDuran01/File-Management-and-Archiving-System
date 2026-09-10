@extends($layout ?? 'Admin.home')
@section('content')

@include('Admin.partials.theme')

<style>
    /* Page-local aliases + a restrained categorical set for the charts,
       all anchored to the shared green accent. */
    :root {
        --page-plane: var(--surface);
        --surface-1: var(--card);
        --text-primary: var(--text-1);
        --text-secondary: var(--text-2);
        --text-muted: var(--text-3);
        --gridline: var(--border);
        --seq-blue: var(--primary);
        --cat-blue: #058028;
        --cat-green: #4ca86a;
        --cat-magenta: #7a8a99;
        --cat-yellow: #b08900;
    }

    body { background: var(--page-plane); color: var(--text-primary); }

    .section-title { font-size: 12px; font-weight: 600; color: var(--text-secondary); letter-spacing: .06em; text-transform: uppercase; margin: 0 0 4px; }
    .section-sub { font-size: 12px; color: var(--text-muted); margin: 0 0 14px; }
    .card-panel { background: var(--surface-1); border: 1px solid var(--border); border-radius: 12px; padding: 18px 20px; margin-bottom: 16px; }

    /* KPI row */
    .metric-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 16px; }
    .metric-card { background: var(--surface-1); border: 1px solid var(--border); border-radius: 10px; padding: 16px 18px; }
    .metric-card .label { font-size: 12px; color: var(--text-muted); margin: 0 0 6px; }
    .metric-card .value { font-size: 26px; font-weight: 600; margin: 0; line-height: 1.1; font-variant-numeric: tabular-nums; color: var(--text-primary); }
    .metric-card .delta { font-size: 11px; margin-top: 4px; color: var(--text-muted); }
    .metric-card .delta.up { color: var(--success-text); }

    .row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    .row-2-wide { display: grid; grid-template-columns: 1.6fr 1fr; gap: 14px; }

    /* Meter (single ratio against a limit) */
    .meter-figure { font-size: 30px; font-weight: 700; font-variant-numeric: tabular-nums; margin: 2px 0 10px; }
    .meter-track { height: 8px; border-radius: 4px; background: var(--gridline); overflow: hidden; margin-bottom: 6px; }
    .meter-fill { height: 100%; border-radius: 4px; background: var(--seq-blue); }
    .meter-caption { font-size: 12px; color: var(--text-muted); }

    /* Thin ranked bars (officers, categories) - direct-labeled */
    .rank-row { display: flex; align-items: center; gap: 10px; margin-bottom: 11px; }
    .rank-row:last-child { margin-bottom: 0; }
    .rank-name { font-size: 13px; color: var(--text-primary); width: 130px; flex-shrink: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .rank-track { flex: 1; height: 8px; background: var(--gridline); border-radius: 4px; overflow: hidden; }
    .rank-fill { height: 100%; border-radius: 4px; }
    .rank-val { font-size: 12px; color: var(--text-secondary); width: 34px; text-align: right; font-variant-numeric: tabular-nums; flex-shrink: 0; }

    /* Proportion bar (part-to-whole, horizontal, categorical) */
    .prop-bar { display: flex; height: 22px; border-radius: 6px; overflow: hidden; margin-bottom: 12px; }
    .prop-seg { height: 100%; }
    .prop-legend { display: flex; flex-wrap: wrap; gap: 14px; font-size: 12px; color: var(--text-secondary); }
    .prop-legend span.swatch { display: inline-block; width: 10px; height: 10px; border-radius: 2px; margin-right: 5px; vertical-align: -1px; }

    /* Tables */
    .rtable { width: 100%; border-collapse: collapse; font-size: 13px; }
    .rtable th { font-size: 11px; color: var(--text-muted); text-transform: uppercase; letter-spacing: .05em; font-weight: 600; padding: 6px 10px; border-bottom: 1px solid var(--gridline); text-align: left; }
    .rtable td { padding: 9px 10px; border-bottom: 1px solid var(--gridline); vertical-align: middle; color: var(--text-primary); }
    .rtable tr:last-child td { border-bottom: none; }

    .status-chip { display: inline-flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 600; padding: 3px 10px; border-radius: 20px; }
    .status-chip.warning { background: #fff3d6; color: #7a5200; }
    .status-chip.critical { background: #fbe1e1; color: #7a1f1f; }
    .status-chip i { font-size: 9px; }

    .badge-type { display: inline-block; font-size: 10px; font-weight: 600; padding: 2px 8px; border-radius: 20px; }
    .badge-generated { background: #e6f0fb; color: #184f95; }
    .badge-uploaded { background: #f0efec; color: var(--text-secondary); }

    .empty-note { font-size: 13px; color: var(--text-muted); padding: 8px 0; }
</style>

<div class="container-fluid py-3">
    <h5 class="fw-semibold mb-1">Reports &amp; Analytics</h5>
    <p class="section-sub">What the records archive is actually doing, not just how much is in it.</p>

    {{-- KPI ROW --}}
    <div class="metric-grid" style="grid-template-columns: repeat(2, 1fr);">
        <div class="metric-card">
            <p class="label">Total documents</p>
            <p class="value">{{ number_format($totalFiles) }}</p>
            <p class="delta {{ $filesDelta >= 0 ? 'up' : '' }}">{{ $filesDelta >= 0 ? '↑' : '↓' }} {{ abs($filesDelta) }}% vs last month</p>
        </div>
        <div class="metric-card">
            <p class="label">Storage used</p>
            <p class="value">{{ $storageFormatted }}</p>
            <p class="delta">projected {{ $projectedTarget ?? '—' }} in 3 months</p>
        </div>
    </div>

    {{-- STORAGE TREND + OFFICER LEADERBOARD --}}
    <div class="row-2-wide">
        <div class="card-panel">
            <p class="section-title">Storage growth</p>
            <p class="section-sub">Actual monthly total, with a 3-month projection from the observed trend.</p>
            <div style="position:relative;width:100%;height:220px;">
                <canvas id="storageChart"></canvas>
            </div>
        </div>
        <div class="card-panel">
            <p class="section-title">Most active officers</p>
            <p class="section-sub">Logged actions, last 90 days.</p>
            @forelse($activeOfficers as $officer)
                @php $max = $activeOfficers->max('total') ?: 1; @endphp
                <div class="rank-row">
                    <span class="rank-name">{{ $officer->user_name }}</span>
                    <div class="rank-track"><div class="rank-fill" style="width:{{ round(($officer->total/$max)*100) }}%; background:var(--seq-blue);"></div></div>
                    <span class="rank-val">{{ $officer->total }}</span>
                </div>
            @empty
                <p class="empty-note">No activity recorded yet.</p>
            @endforelse
        </div>
    </div>

    {{-- FOLDERS NEEDING ATTENTION + FILE TYPES --}}
    <div class="row-2">
        <div class="card-panel">
            <p class="section-title">Folders needing attention</p>
            <p class="section-sub">Not archived, untouched for {{ 90 }}+ days.</p>
            @forelse($staleFolders as $folder)
                <div class="rank-row" style="margin-bottom:10px;">
                    <span class="rank-name" style="width:auto; flex:1;">{{ $folder['name'] }}</span>
                    <span class="status-chip {{ $folder['status'] }}">
                        <i class="fas fa-circle"></i> {{ $folder['days_stale'] }}d stale
                    </span>
                </div>
            @empty
                <p class="empty-note">Nothing stale right now — every active folder has been touched recently.</p>
            @endforelse
        </div>

        <div class="card-panel">
            <p class="section-title">Document types</p>
            <p class="section-sub">Share of the archive by file type.</p>
            @php $typeColors = ['var(--cat-blue)', 'var(--cat-green)', 'var(--cat-magenta)', 'var(--cat-yellow)']; @endphp
            <div class="prop-bar">
                @foreach($fileTypes as $i => $type)
                    @if($type['percent'] > 0)
                        <div class="prop-seg" style="width:{{ $type['percent'] }}%; background:{{ $typeColors[$i] }};" title="{{ $type['label'] }} {{ $type['percent'] }}%"></div>
                    @endif
                @endforeach
            </div>
            <div class="prop-legend">
                @foreach($fileTypes as $i => $type)
                    <span><span class="swatch" style="background:{{ $typeColors[$i] }};"></span>{{ $type['label'] }} {{ $type['percent'] }}%</span>
                @endforeach
            </div>
        </div>
    </div>

    {{-- RECENT DOCUMENTS --}}
    <div class="card-panel">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <p class="section-title mb-0">Recent documents</p>
            <a href="/folders" class="btn btn-sm btn-outline-secondary" style="font-size:12px;">View all</a>
        </div>
        <table class="rtable">
            <thead>
                <tr>
                    <th>File name</th>
                    <th>Source</th>
                    <th>Size</th>
                    <th>Uploaded at</th>
                </tr>
            </thead>
            <tbody>
                @forelse($recentFiles as $file)
                <tr>
                    <td><span style="font-weight:500;">{{ $file->filename }}</span></td>
                    <td>
                        @if($file->generated_from_template_id)
                            <span class="badge-type badge-generated">Generated</span>
                        @else
                            <span class="badge-type badge-uploaded">Uploaded</span>
                        @endif
                    </td>
                    <td>{{ $file->size ? number_format($file->size / 1024, 1) . ' KB' : '—' }}</td>
                    <td style="color:var(--text-muted);">{{ $file->created_at->format('M j, Y H:i') }}</td>
                </tr>
                @empty
                <tr><td colspan="4" class="empty-note">No documents yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($isSuperAdmin)
    {{-- SECURITY: LOGIN ANOMALIES - pattern detection over activity_logs,
         not a raw count. SuperAdmin-only: this surfaces who might be under
         attack or have a compromised account. --}}
    <div class="card-panel">
        <p class="section-title"><i class="fas fa-shield-halved me-1"></i> Security: login anomalies</p>
        <p class="section-sub">Patterns in the last {{ 7 }} days worth a human look - not just a failed-login count.</p>

        <div class="row-2" style="grid-template-columns: 1fr 1fr 1fr; gap: 14px;">
            <div>
                <p class="section-title" style="font-size:11px;">Possible brute force</p>
                @forelse($loginAnomalies['bruteForce'] as $row)
                    <div class="rank-row" style="margin-bottom:10px; align-items:flex-start;">
                        <div style="flex:1; min-width:0;">
                            <div style="font-size:13px; font-weight:500; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $row->user_name }}</div>
                            <div style="font-size:11px; color:var(--text-muted);">{{ $row->distinct_ips }} {{ Str::plural('IP', $row->distinct_ips) }} &middot; last {{ \Carbon\Carbon::parse($row->last_attempt)->diffForHumans() }}</div>
                        </div>
                        <span class="status-chip {{ $row->attempts >= 5 ? 'critical' : 'warning' }}">
                            <i class="fas fa-circle"></i> {{ $row->attempts }}x
                        </span>
                    </div>
                @empty
                    <p class="empty-note">No repeated failed attempts.</p>
                @endforelse
            </div>

            <div>
                <p class="section-title" style="font-size:11px;">Multiple IPs, same account</p>
                @forelse($loginAnomalies['multiIp'] as $row)
                    <div class="rank-row" style="margin-bottom:10px; align-items:flex-start;">
                        <div style="flex:1; min-width:0;">
                            <div style="font-size:13px; font-weight:500; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $row->user_name }}</div>
                            <div style="font-size:11px; color:var(--text-muted);">{{ $row->total_logins }} logins &middot; last {{ \Carbon\Carbon::parse($row->last_login)->diffForHumans() }}</div>
                        </div>
                        <span class="status-chip warning">
                            <i class="fas fa-circle"></i> {{ $row->distinct_ips }} IPs
                        </span>
                    </div>
                @empty
                    <p class="empty-note">No accounts logging in from multiple locations.</p>
                @endforelse
            </div>

            <div>
                <p class="section-title" style="font-size:11px;">New-location logins</p>
                @forelse($loginAnomalies['newLocation'] as $row)
                    <div class="rank-row" style="margin-bottom:10px; align-items:flex-start;">
                        <div style="flex:1; min-width:0;">
                            <div style="font-size:13px; font-weight:500; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $row->user_name }}</div>
                            <div style="font-size:11px; color:var(--text-muted);">{{ $row->ip_address }} &middot; {{ $row->created_at->diffForHumans() }}</div>
                        </div>
                        <span class="status-chip warning">
                            <i class="fas fa-location-dot"></i> new
                        </span>
                    </div>
                @empty
                    <p class="empty-note">No unfamiliar-location logins.</p>
                @endforelse
            </div>
        </div>
    </div>
    @endif
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const storageTrend = @json($storageTrend);
const projection = @json($projection);

const labels = [...storageTrend.map(p => p.label), ...projection.map(p => p.label)];

// Projected series starts from the last actual point so the two lines
// visually connect, with nulls elsewhere so Chart.js doesn't draw it
// across the historical range.
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
            {
                label: 'Actual',
                data: actualData,
                borderColor: '#058028',
                backgroundColor: 'rgba(5,128,40,0.08)',
                borderWidth: 2,
                pointRadius: 3,
                pointBackgroundColor: '#058028',
                fill: true,
                tension: 0.25,
                spanGaps: false,
            },
            {
                label: 'Projected',
                data: projectedData,
                borderColor: '#058028',
                borderDash: [6, 5],
                borderWidth: 2,
                pointRadius: 3,
                pointStyle: 'circle',
                pointBackgroundColor: '#fcfcfb',
                pointBorderColor: '#058028',
                fill: false,
                tension: 0.25,
                spanGaps: true,
            },
        ],
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: true,
                position: 'top',
                align: 'end',
                labels: { boxWidth: 10, boxHeight: 10, font: { size: 11 }, color: '#52514e' },
            },
            tooltip: {
                callbacks: {
                    label: (ctx) => ctx.dataset.label + ': ' + formatBytesShort(ctx.parsed.y),
                },
            },
        },
        scales: {
            x: { grid: { display: false }, ticks: { font: { size: 11 }, color: '#898781' } },
            y: {
                grid: { color: '#e1e0d9' },
                ticks: {
                    font: { size: 11 }, color: '#898781',
                    callback: (v) => formatBytesShort(v),
                },
            },
        },
    },
});
</script>

@endsection
