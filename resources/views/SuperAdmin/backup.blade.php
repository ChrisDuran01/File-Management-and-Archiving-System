@extends('SuperAdmin.homeSuperAdmin')
@section('content')

<style>
    @import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap');

    :root {
        --surface:     #f7f8fc;
        --card:        #ffffff;
        --border:      #e8eaf0;
        --primary:     #534AB7;
        --primary-dim: #EEEDFE;
        --text-1:      #111827;
        --text-2:      #6b7280;
        --text-3:      #9ca3af;
        --shadow-sm:   0 1px 3px rgba(0,0,0,.06), 0 1px 2px rgba(0,0,0,.04);
        --radius:      12px;
        --radius-sm:   8px;
    }

    * { box-sizing: border-box; }
    body { background: var(--surface); font-family: 'DM Sans', sans-serif; color: var(--text-1); }

    /* ── Page Header ── */
    .page-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }
    .page-header h4 {
        font-size: 1.25rem;
        font-weight: 500;
        margin: 0;
        color: var(--text-1);
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .page-title-icon {
        width: 32px;
        height: 32px;
        border-radius: var(--radius-sm);
        background: var(--primary-dim);
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .page-title-icon svg { width: 16px; height: 16px; }

    /* ── Stat Cards ── */
    .bm-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 10px;
        margin-bottom: 1.25rem;
    }
    .bm-stat {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 0.875rem 1rem;
        box-shadow: var(--shadow-sm);
    }
    .bm-stat-label {
        font-size: 12px;
        color: var(--text-2);
        text-transform: uppercase;
        letter-spacing: .05em;
        margin-bottom: 4px;
    }
    .bm-stat-val { font-size: 22px; font-weight: 500; color: var(--text-1); }
    .bm-stat-val.green  { color: #27500A; }
    .bm-stat-val.red    { color: #791F1F; }
    .bm-stat-val.amber  { color: #633806; }

    /* ── Settings Card ── */
    .bm-settings-card {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 1rem 1.25rem;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 16px;
        flex-wrap: wrap;
        box-shadow: var(--shadow-sm);
    }
    .bm-settings-label {
        font-size: 15px;
        font-weight: 500;
        color: var(--text-1);
        white-space: nowrap;
    }

    /* Toggle */
    .bm-toggle-wrap { display: flex; align-items: center; gap: 8px; }
    .bm-toggle { position: relative; width: 40px; height: 22px; flex-shrink: 0; }
    .bm-toggle input { opacity: 0; width: 0; height: 0; }
    .bm-slider {
        position: absolute;
        inset: 0;
        background: #D3D1C7;
        border-radius: 22px;
        cursor: pointer;
        transition: background .2s;
    }
    .bm-slider::before {
        content: '';
        position: absolute;
        width: 16px;
        height: 16px;
        left: 3px;
        top: 3px;
        background: #fff;
        border-radius: 50%;
        transition: transform .2s;
    }
    .bm-toggle input:checked + .bm-slider { background: var(--primary); }
    .bm-toggle input:checked + .bm-slider::before { transform: translateX(18px); }
    .bm-toggle-text { font-size: 14px; color: var(--text-2); }

    .bm-sep { width: 1px; height: 24px; background: var(--border); }

    .bm-select {
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        padding: 5px 10px;
        font-size: 14px;
        font-family: 'DM Sans', sans-serif;
        background: var(--card);
        color: var(--text-1);
        outline: none;
        cursor: pointer;
    }
    .bm-select:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(83,74,183,.1); }

    .bm-btn-primary {
        background: var(--primary);
        border: none;
        border-radius: var(--radius-sm);
        color: #fff;
        padding: 6px 16px;
        font-size: 14px;
        font-weight: 500;
        font-family: 'DM Sans', sans-serif;
        cursor: pointer;
        transition: background .15s;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        white-space: nowrap;
    }
    .bm-btn-primary:hover { background: #3C3489; }

    /* ── Filters Card ── */
    .bm-filters-card {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 1rem 1.25rem;
        margin-bottom: 1rem;
        box-shadow: var(--shadow-sm);
    }
    .bm-filters-row {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        align-items: flex-end;
    }
    .bm-filter-group {
        display: flex;
        flex-direction: column;
        gap: 4px;
        flex: 1;
        min-width: 130px;
    }
    .bm-filter-label {
        font-size: 13px;
        font-weight: 500;
        color: var(--text-2);
        text-transform: uppercase;
        letter-spacing: .05em;
    }
    .bm-input-wrap { position: relative; }
    .bm-input {
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        padding: 6px 10px 6px 30px;
        font-size: 13px;
        font-family: 'DM Sans', sans-serif;
        background: var(--card);
        color: var(--text-1);
        outline: none;
        width: 100%;
        transition: border-color .15s;
    }
    .bm-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(83,74,183,.1); }
    .bm-input-icon {
        position: absolute;
        left: 9px;
        top: 50%;
        transform: translateY(-50%);
        width: 13px;
        height: 13px;
        color: var(--text-3);
        pointer-events: none;
    }
    .bm-btn-secondary {
        background: none;
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        color: var(--text-2);
        padding: 6px 14px;
        font-size: 13px;
        font-family: 'DM Sans', sans-serif;
        cursor: pointer;
        transition: all .15s;
        white-space: nowrap;
    }
    .bm-btn-secondary:hover { border-color: var(--primary); color: var(--primary); }

    /* ── Table Card ── */
    .bm-table-card {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        overflow: hidden;
        box-shadow: var(--shadow-sm);
    }
    .bm-table { width: 100%; border-collapse: collapse; }
    .bm-table thead {
        background: #f9fafb;
        border-bottom: 1px solid var(--border);
    }
    .bm-table thead th {
        padding: 10px 14px;
        font-size: 13px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: var(--text-3);
        white-space: nowrap;
    }
    .bm-table tbody tr {
        border-bottom: 1px solid var(--border);
        transition: background .1s;
    }
    .bm-table tbody tr:last-child { border-bottom: none; }
    .bm-table tbody tr:hover { background: var(--primary-dim); }
    .bm-table td {
        padding: 11px 14px;
        font-size: 14px;
        color: var(--text-1);
        vertical-align: middle;
    }

    .bm-name-cell { display: flex; align-items: center; gap: 8px; }
    .bm-file-icon {
        width: 30px;
        height: 30px;
        border-radius: var(--radius-sm);
        background: var(--primary-dim);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .bm-file-icon svg { width: 14px; height: 14px; }
    .bm-name-text {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        max-width: 260px;
        font-size: 14px;
    }

    .bm-size {
        font-family: 'DM Mono', monospace;
        font-size: 13px;
        color: var(--text-2);
    }
    .bm-date { font-size: 13px; color: var(--text-2); }

    /* Badges */
    .bm-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 8px;
        border-radius: 50px;
        font-size: 12px;
        font-weight: 500;
    }
    .bm-badge.success { background: #EAF3DE; color: #27500A; }
    .bm-badge.failed  { background: #FCEBEB; color: #791F1F; }
    .bm-badge.pending { background: #FAEEDA; color: #633806; }
    .bm-badge-dot { width: 5px; height: 5px; border-radius: 50%; }
    .bm-badge.success .bm-badge-dot { background: #3B6D11; }
    .bm-badge.failed  .bm-badge-dot { background: #A32D2D; }
    .bm-badge.pending .bm-badge-dot { background: #854F0B; }

    .bm-dl-btn {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 10px;
        border-radius: var(--radius-sm);
        border: 1px solid var(--border);
        background: none;
        font-size: 14px;
        font-family: 'DM Sans', sans-serif;
        color: var(--text-2);
        cursor: pointer;
        text-decoration: none;
        transition: all .15s;
    }
    .bm-dl-btn:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-dim); }
    .bm-dl-btn svg { width: 12px; height: 12px; }

    .bm-footer {
        padding: 10px 14px;
        border-top: 1px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: #f9fafb;
    }
    .bm-count { font-size: 12px; color: var(--text-2); }

    .bm-empty { text-align: center; padding: 3rem 1rem; color: var(--text-3); }
    .bm-empty i { font-size: 2rem; margin-bottom: 12px; display: block; }
    .bm-empty p { margin: 0; font-size: .9rem; }

    @media (max-width: 600px) {
        .bm-settings-card { flex-direction: column; align-items: flex-start; }
        .bm-sep { width: 100%; height: 1px; }
    }
</style>

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

{{-- ── Page Header ── --}}
<div class="page-header">
    <h4>
        <div class="page-title-icon">
            <svg viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M8 2C5.24 2 3 4.24 3 7c0 .34.04.67.1.99C2.43 8.4 2 9.15 2 10c0 1.66 1.34 3 3 3h7c1.38 0 2.5-1.12 2.5-2.5 0-1.3-.99-2.37-2.26-2.49C12.08 5.09 10.24 2 8 2z"
                      stroke="#534AB7" stroke-width="1.1" stroke-linejoin="round"/>
                <path d="M8 9v3M6.5 10.5L8 9l1.5 1.5"
                      stroke="#534AB7" stroke-width="1.1" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>
        Backup Management
    </h4>
</div>

{{-- ── Stat Cards ── --}}
<div class="bm-stats">
    <div class="bm-stat">
        <div class="bm-stat-label">Total backups</div>
        <div class="bm-stat-val">{{ $backups->count() }}</div>
    </div>
    <div class="bm-stat">
        <div class="bm-stat-label">Successful</div>
        <div class="bm-stat-val green">{{ $backups->where('status','Success')->count() }}</div>
    </div>
    <div class="bm-stat">




        
        <div class="bm-stat-label">Failed</div>
        <div class="bm-stat-val red">{{ $backups->where('status','Failed')->count() }}</div>
    </div>
    <div class="bm-stat">
        <div class="bm-stat-label">Pending</div>
        <div class="bm-stat-val amber">{{ $backups->where('status','Pending')->count() }}</div>
    </div>
</div>

{{-- ── Settings Card ── --}}
<div class="bm-settings-card">
    <span class="bm-settings-label">Auto backup</span>

    <div class="bm-toggle-wrap">
        <form method="POST" action="{{ route('backup.toggle') }}" id="toggleForm">
            @csrf
            <label class="bm-toggle">
                <input type="checkbox"
                       name="backup_enabled"
                       id="backupToggle"
                       onchange="document.getElementById('toggleForm').submit()"
                       {{ $backupEnabled ? 'checked' : '' }}>
                <span class="bm-slider"></span>
            </label>
        </form>
        <span class="bm-toggle-text">{{ $backupEnabled ? 'Enabled' : 'Disabled' }}</span>
    </div>

    <div class="bm-sep"></div>
    <span class="bm-settings-label" style="font-size:12px;color:var(--text-2);">Frequency</span>

    <form method="POST" action="{{ route('backup.frequency') }}" id="freqForm">
        @csrf
        <select name="frequency" class="bm-select" onchange="document.getElementById('freqForm').submit()">
            <option value="daily"   {{ $frequency == 'daily'   ? 'selected' : '' }}>Every day</option>
            <option value="weekly"  {{ $frequency == 'weekly'  ? 'selected' : '' }}>Weekly</option>
            <option value="monthly" {{ $frequency == 'monthly' ? 'selected' : '' }}>Monthly</option>
        </select>
    </form>

    <div class="bm-sep"></div>

    <form method="POST" action="{{ route('backup.create') }}">
        @csrf
        <button type="submit" class="bm-btn-primary">
            <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                <path d="M6 1v6M6 1L3.5 3.5M6 1l2.5 2.5" stroke="#fff" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M1.5 8.5V10a.5.5 0 00.5.5h8a.5.5 0 00.5-.5V8.5" stroke="#fff" stroke-width="1.3" stroke-linecap="round"/>
            </svg>
            Backup now
        </button>
    </form>
</div>

{{-- ── Filters Card ── --}}
<div class="bm-filters-card">
    <div class="bm-filters-row">
        <div class="bm-filter-group" style="flex:2;min-width:160px;">
            <span class="bm-filter-label">Search</span>
            <div class="bm-input-wrap">
                <svg class="bm-input-icon" viewBox="0 0 14 14" fill="none">
                    <circle cx="5.5" cy="5.5" r="4" stroke="currentColor" stroke-width="1.3"/>
                    <line x1="9" y1="9" x2="13" y2="13" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/>
                </svg>
                <input class="bm-input" type="text" id="searchInput"
                       placeholder="Search by name or status…"
                       oninput="applyFilters()">
            </div>
        </div>

        <div class="bm-filter-group">
            <span class="bm-filter-label">Status</span>
            <select class="bm-select" style="width:100%;" id="statusFilter" onchange="applyFilters()">
                <option value="all">All status</option>
                <option value="Success">Success</option>
                <option value="Failed">Failed</option>
                <option value="Pending">Pending</option>
            </select>
        </div>

        <div class="bm-filter-group">
            <span class="bm-filter-label">Date range</span>
            <select class="bm-select" style="width:100%;" id="dateFilter" onchange="applyFilters()">
                <option value="all">All time</option>
                <option value="today">Today</option>
                <option value="yesterday">Yesterday</option>
                <option value="last7">Last 7 days</option>
                <option value="last30">Last 30 days</option>
                <option value="thisMonth">This month</option>
            </select>
        </div>

        <div style="display:flex;align-items:flex-end;">
            <button class="bm-btn-secondary" id="resetFilters" onclick="resetFilters()">
                Reset
            </button>
        </div>
    </div>
</div>

{{-- ── Backup Table ── --}}
<div class="bm-table-card">
    <table class="bm-table" id="backupTable">
        <thead>
            <tr>
                <th>Name</th>
                <th>Size</th>
                <th>Date</th>
                <th>Status</th>
                <th style="text-align:right">Action</th>
            </tr>
        </thead>
        <tbody id="backupTableBody">
            @forelse($backups as $backup)
                @php
                    $statusClass = match($backup->status) {
                        'Success' => 'success',
                        'Failed'  => 'failed',
                        default   => 'pending',
                    };
                @endphp
                <tr class="backup-row"
                    data-name="{{ strtolower($backup->name) }}"
                    data-status="{{ $backup->status }}"
                    data-date="{{ $backup->created_at->format('Y-m-d') }}">

                    <td>
                        <div class="bm-name-cell">
                            <div class="bm-file-icon">
                                <svg viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M3 2h5.5L11 4.5V12H3V2z" stroke="#534AB7" stroke-width="1.1" stroke-linejoin="round"/>
                                    <path d="M8.5 2v3H11" stroke="#534AB7" stroke-width="1.1" stroke-linejoin="round"/>
                                </svg>
                            </div>
                            <span class="bm-name-text" title="{{ $backup->name }}">{{ $backup->name }}</span>
                        </div>
                    </td>

                    <td class="bm-size">{{ $backup->size }}</td>

                    <td class="bm-date">{{ $backup->created_at->format('M d, Y') }}</td>

                    <td>
                        <span class="bm-badge {{ $statusClass }}">
                            <span class="bm-badge-dot"></span>
                            {{ $backup->status }}
                        </span>
                    </td>

                    <td style="text-align:right">
                        <a href="{{ route('backup.download', $backup->id) }}" class="bm-dl-btn">
                            <svg viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M6 1v6M6 7L3.5 4.5M6 7l2.5-2.5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M1.5 9.5V10a.5.5 0 00.5.5h8a.5.5 0 00.5-.5v-.5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
                            </svg>
                            Download
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">
                        <div class="bm-empty">
                            <i class="fas fa-database"></i>
                            <p>No backups found.</p>
                        </div>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="bm-footer">
        <span class="bm-count">
            Showing <span id="visibleCount">{{ $backups->count() }}</span>
            of {{ $backups->count() }} {{ Str::plural('backup', $backups->count()) }}
        </span>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput  = document.getElementById('searchInput');
    const statusFilter = document.getElementById('statusFilter');
    const dateFilter   = document.getElementById('dateFilter');
    const visibleCount = document.getElementById('visibleCount');

    function applyFilters() {
        const q   = searchInput.value.toLowerCase();
        const st  = statusFilter.value;
        const dt  = dateFilter.value;

        const today = new Date();
        today.setHours(0, 0, 0, 0);

        const rows = document.querySelectorAll('.backup-row');
        let visible = 0;

        rows.forEach(row => {
            const name   = row.getAttribute('data-name') || '';
            const status = row.getAttribute('data-status') || '';
            const date   = row.getAttribute('data-date') || '';

            let show = true;

            if (q && !name.includes(q) && !status.toLowerCase().includes(q)) show = false;
            if (st !== 'all' && status !== st) show = false;

            if (dt !== 'all' && date) {
                const d = new Date(date);
                d.setHours(0, 0, 0, 0);
                const diff = (today - d) / (1000 * 60 * 60 * 24);
                if (dt === 'today'     && diff !== 0)  show = false;
                if (dt === 'yesterday' && diff !== 1)  show = false;
                if (dt === 'last7'     && diff > 7)    show = false;
                if (dt === 'last30'    && diff > 30)   show = false;
                if (dt === 'thisMonth' && (d.getMonth() !== today.getMonth() || d.getFullYear() !== today.getFullYear())) show = false;
            }

            row.style.display = show ? '' : 'none';
            if (show) visible++;
        });

        visibleCount.textContent = visible;

        const noMsg = document.getElementById('noFilterMsg');
        if (visible === 0) {
            if (!noMsg) {
                const tr = document.createElement('tr');
                tr.id = 'noFilterMsg';
                tr.innerHTML = '<td colspan="5"><div class="bm-empty"><i class="fas fa-search"></i><p>No backups match your filters.</p></div></td>';
                document.getElementById('backupTableBody').appendChild(tr);
            }
        } else if (noMsg) {
            noMsg.remove();
        }
    }

    window.applyFilters = applyFilters;

    window.resetFilters = function () {
        searchInput.value   = '';
        statusFilter.value  = 'all';
        dateFilter.value    = 'all';
        applyFilters();
    };

    searchInput.addEventListener('keyup', applyFilters);
    statusFilter.addEventListener('change', applyFilters);
    dateFilter.addEventListener('change', applyFilters);

    applyFilters();
});
</script>

@endsection