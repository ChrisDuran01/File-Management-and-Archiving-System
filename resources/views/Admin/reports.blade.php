@extends('home')
@section('content')

<style>
.section-title { font-size: 12px; font-weight: 600; color: #6c757d; letter-spacing: .07em; text-transform: uppercase; margin: 0 0 10px; }
.metric-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 1.5rem; }
.metric-card { background: #f8f9fa; border-radius: 10px; padding: 16px 18px; }
.metric-card .label { font-size: 12px; color: #6c757d; margin: 0 0 6px; }
.metric-card .value { font-size: 26px; font-weight: 600; margin: 0; line-height: 1.1; }
.metric-card .delta { font-size: 11px; margin-top: 4px; }
.delta-up { color: #28a745; }
.delta-dn { color: #dc3545; }
.card-panel { background: #fff; border: 1px solid #e9ecef; border-radius: 12px; padding: 18px 20px; margin-bottom: 16px; }
.chart-row { display: grid; grid-template-columns: 2fr 1fr; gap: 14px; margin-bottom: 16px; }
.half-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 16px; }
.filter-chips { display: flex; gap: 8px; margin-bottom: 1rem; flex-wrap: wrap; }
.chip { font-size: 12px; padding: 4px 14px; border-radius: 20px; border: 1px solid #dee2e6; background: transparent; color: #6c757d; cursor: pointer; transition: all .15s; }
.chip:hover { background: #e9ecef; }
.chip.active { background: #cfe2ff; color: #084298; border-color: transparent; }
.mini-bar { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
.mini-bar .name { font-size: 13px; color: #495057; width: 110px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.mini-bar .track { flex: 1; height: 6px; background: #e9ecef; border-radius: 3px; overflow: hidden; }
.mini-bar .fill { height: 100%; border-radius: 3px; }
.mini-bar .val { font-size: 12px; color: #6c757d; width: 36px; text-align: right; }
.rtable { width: 100%; border-collapse: collapse; font-size: 13px; }
.rtable th { font-size: 11px; color: #6c757d; text-transform: uppercase; letter-spacing: .05em; font-weight: 600; padding: 6px 10px; border-bottom: 1px solid #dee2e6; text-align: left; }
.rtable td { padding: 9px 10px; border-bottom: 1px solid #f1f3f5; vertical-align: middle; }
.rtable tr:last-child td { border-bottom: none; }
.badge-type { display: inline-block; font-size: 10px; font-weight: 600; padding: 2px 8px; border-radius: 20px; letter-spacing: .04em; }
.badge-img  { background:#cfe2ff; color:#084298; }
.badge-pdf  { background:#f8d7da; color:#842029; }
.badge-doc  { background:#fff3cd; color:#664d03; }
.badge-zip  { background:#e2e3e5; color:#41464b; }
.avatar { width: 28px; height: 28px; border-radius: 50%; display:inline-flex; align-items:center; justify-content:center; font-size: 11px; font-weight: 600; }
</style>

<div class="container-fluid py-3">
    <h5 class="fw-semibold mb-4">Reports &amp; Analytics</h5>

    {{-- METRIC CARDS --}}
    <div class="metric-grid">
        <div class="metric-card">
            <p class="label">Total files</p>
            <p class="value text-primary">{{ number_format($totalFiles) }}</p>
            <p class="delta delta-up">↑ {{ $filesDelta }}% vs last month</p>
        </div>
        <div class="metric-card">
            <p class="label">Total folders</p>
            <p class="value text-success">{{ number_format($totalFolders) }}</p>
            <p class="delta delta-up">↑ {{ $foldersDelta }}% vs last month</p>
        </div>
        <div class="metric-card">
            <p class="label">Active users</p>
            <p class="value text-warning">{{ number_format($totalUsers) }}</p>
            <p class="delta {{ $usersDelta >= 0 ? 'delta-up' : 'delta-dn' }}">
                {{ $usersDelta >= 0 ? '↑' : '↓' }} {{ abs($usersDelta) }}% vs last month
            </p>
        </div>
        <div class="metric-card">
            <p class="label">Storage used</p>
            <p class="value text-danger">{{ $storageFormatted }}</p>
            <p class="delta delta-up">↑ {{ $storageDelta }} MB this month</p>
        </div>
    </div>

    {{-- DATE RANGE FILTER --}}
    <div class="filter-chips">
        <button class="chip active" data-range="7d">Last 7 days</button>
        <button class="chip" data-range="30d">30 days</button>
        <button class="chip" data-range="90d">90 days</button>
        <button class="chip" data-range="12m">12 months</button>
    </div>

    {{-- CHARTS ROW --}}
    <div class="chart-row">
        <div class="card-panel">
            <p class="section-title mb-3">Monthly uploads</p>
            <div class="mb-2" style="display:flex;gap:16px;font-size:12px;color:#6c757d;">
                <span><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:#3b82f6;margin-right:4px;"></span>Files</span>
                <span><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:#22c55e;margin-right:4px;"></span>Folders</span>
            </div>
            <div style="position:relative;width:100%;height:220px;">
                <canvas id="uploadChart"></canvas>
            </div>
        </div>
        <div class="card-panel">
            <p class="section-title mb-3">File types</p>
            <div style="position:relative;width:100%;height:150px;">
                <canvas id="typeChart"></canvas>
            </div>
            <div style="display:flex;flex-direction:column;gap:5px;font-size:12px;color:#6c757d;margin-top:10px;">
                @foreach($fileTypes as $type)
                <span>
                    <span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:{{ $type['color'] }};margin-right:4px;"></span>
                    {{ $type['label'] }} {{ $type['percent'] }}%
                </span>
                @endforeach
            </div>
        </div>
    </div>

    {{-- RECENT FILES --}}
    <div class="card-panel">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <p class="section-title mb-0">Recent files</p>
            <a href="" class="btn btn-sm btn-outline-secondary" style="font-size:12px;">View all</a>
        </div>
        <table class="rtable">
            <thead>
                <tr>
                    <th>File name</th>
                    <th>Type</th>
                    <th>Uploaded by</th>
                    <th>Size</th>
                    <th>Uploaded at</th>
                </tr>
            </thead>
            <tbody>
                @foreach($recentFiles as $file)
                @php
                    $ext = strtolower(pathinfo($file->filename, PATHINFO_EXTENSION));
                    $badgeClass = match(true) {
                        in_array($ext, ['png','jpg','jpeg','gif','webp']) => 'badge-img',
                        $ext === 'pdf'                                    => 'badge-pdf',
                        in_array($ext, ['doc','docx','xls','xlsx'])       => 'badge-doc',
                        default                                           => 'badge-zip',
                    };
                    $badgeLabel = match(true) {
                        in_array($ext, ['png','jpg','jpeg','gif','webp']) => 'IMG',
                        $ext === 'pdf'                                    => 'PDF',
                        in_array($ext, ['doc','docx'])                   => 'DOC',
                        in_array($ext, ['xls','xlsx'])                   => 'XLS',
                        default                                           => strtoupper($ext) ?: 'FILE',
                    };
                    $initials = collect(explode(' ', $file->user->name ?? 'U'))->map(fn($w)=>strtoupper($w[0]))->take(2)->join('');
                @endphp
                <tr>
                    <td>
                        <span style="font-weight:500;">{{ $file->filename }}</span>
                    </td>
                    <td><span class="badge-type {{ $badgeClass }}">{{ $badgeLabel }}</span></td>
                    <td>
                        <span class="avatar" style="background:#e9ecef;color:#495057;">{{ $initials }}</span>
                        <span style="margin-left:6px;font-size:13px;">{{ $file->user->name ?? '—' }}</span>
                    </td>
                    <td>{{ $file->size ? number_format($file->size / 1024, 1) . ' KB' : '—' }}</td>
                    <td style="color:#6c757d;">{{ $file->created_at->format('M j, Y H:i') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Pass PHP data to JS safely
    window.uploadData = @json($uploadData);
    window.fileTypes = @json($fileTypes);
</script>

<script type="module" src="{{ Vite::asset('resources/js/app.js') }}"></script>
<script>



const uploadChart = new Chart(document.getElementById('uploadChart'), {
    type: 'bar',
    data: {
        labels: uploadData['12m'].labels,
        datasets: [
            { label: 'Files', data: uploadData['12m'].files, backgroundColor: '#bfdbfe', borderRadius: 4 },
            { label: 'Folders', data: uploadData['12m'].folders, backgroundColor: '#bbf7d0', borderRadius: 4 },
        ]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            x: { grid: { display: false }, ticks: { font: { size: 11 }, color: '#6c757d', autoSkip: false, maxRotation: 45 } },
            y: { grid: { color: 'rgba(0,0,0,.06)' }, ticks: { font: { size: 11 }, color: '#6c757d' } }
        }
    }
});

document.querySelectorAll('.chip[data-range]').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.chip[data-range]').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        const d = uploadData[btn.dataset.range];
        uploadChart.data.labels = d.labels;
        uploadChart.data.datasets[0].data = d.files;
        uploadChart.data.datasets[1].data = d.folders;
        uploadChart.update();
    });
});
new Chart(document.getElementById('typeChart'), {
    type: 'doughnut',
    data: {
        labels: window.fileTypes.map(t => t.label),
        datasets: [{
            data: window.fileTypes.map(t => t.percent),
            backgroundColor: window.fileTypes.map(t => t.color),
            borderWidth: 0,
            hoverOffset: 4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '65%',
        plugins: {
            legend: { display: false }
        }
    }
});
</script>

@endsection