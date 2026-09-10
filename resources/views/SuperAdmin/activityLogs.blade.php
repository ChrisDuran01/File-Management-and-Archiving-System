@extends('SuperAdmin.homeSuperAdmin')
@section('content')

@include('Admin.partials.theme')

<style>
    * { box-sizing: border-box; }
    body { background: var(--surface); font-family: 'Inter', sans-serif; color: var(--text-1); }

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
        letter-spacing: -.3px;
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
    .page-title-icon svg {
        width: 16px;
        height: 16px;
    }

    /* ── Card ── */
    .al-card {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        overflow: hidden;
        box-shadow: var(--shadow-sm);
    }

    /* ── Card Header / Filters ── */
    .al-card-header {
        padding: 0.875rem 1.25rem;
        border-bottom: 1px solid var(--border);
        background: #f9fafb;
    }
    .al-filter-row {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }
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
    .al-filter-sep { font-size: 12px; color: var(--text-2); }
    .al-filter-btn {
        background: var(--primary);
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

{{-- ── Page Header ── --}}
<div class="page-header">
    <h4>
        <div class="page-title-icon">
            <svg viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect x="2" y="2" width="12" height="12" rx="2" stroke="#058028" stroke-width="1.2"/>
                <line x1="5" y1="6" x2="11" y2="6" stroke="#058028" stroke-width="1.2" stroke-linecap="round"/>
                <line x1="5" y1="9" x2="9" y2="9" stroke="#058028" stroke-width="1.2" stroke-linecap="round"/>
            </svg>
        </div>
        Activity Logs
    </h4>
</div>

{{-- ── Main Card ── --}}
<div class="al-card">

    {{-- ── Filter Header ── --}}
    <div class="al-card-header">
        <form method="GET" action="{{ route('activity.logs') }}" class="al-filter-row">
            <span class="al-filter-label">Date range</span>

            <input type="date"
                   class="al-date-input"
                   name="from_date"
                   value="{{ request('from_date') }}">

            <span class="al-filter-sep">—</span>

            <input type="date"
                   class="al-date-input"
                   name="to_date"
                   value="{{ request('to_date') }}">

            <button type="submit" class="al-filter-btn">
                <i class="fas fa-filter me-1"></i> Filter
            </button>

            @if(request('from_date') || request('to_date'))
                <a href="{{ route('activity.logs') }}" class="al-clear-btn">
                    Clear
                </a>
            @endif
        </form>
    </div>

    {{-- ── Search ── --}}
    <div class="al-search-wrap">
        <div class="al-search-inner">
            <svg class="al-search-icon" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="6.5" cy="6.5" r="4.5" stroke="currentColor" stroke-width="1.3"/>
                <line x1="10" y1="10" x2="14" y2="14" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/>
            </svg>
            <input type="text"
                   id="searchInput"
                   placeholder="Search by user, action, or IP address…"
                   oninput="filterLogs()">
        </div>
    </div>

    {{-- ── Stats Chips ── --}}
    <div class="al-stats">
        <span class="al-chip">
            <i class="fas fa-list"></i>
            {{ $logs->count() }} {{ Str::plural('event', $logs->count()) }}
        </span>
        <span class="al-chip">
            <i class="fas fa-user"></i>
            {{ $logs->pluck('user_name')->unique()->count() }} {{ Str::plural('user', $logs->pluck('user_name')->unique()->count()) }}
        </span>
        <span class="al-chip">
            <i class="fas fa-globe"></i>
            {{ $logs->pluck('ip_address')->unique()->count() }} unique {{ Str::plural('IP', $logs->pluck('ip_address')->unique()->count()) }}
        </span>
    </div>

    {{-- ── Log Timeline ── --}}
    <div class="al-body">
        @if($logs->count() > 0)
            @foreach($logs as $i => $log)
                @php
                    $activity = strtolower($log->activity);
                    $type = match(true) {
                        str_contains($activity, 'upload')                   => 'upload',
                        str_contains($activity, 'added')                   => 'upload',
                        str_contains($activity, 'delete')                   => 'delete',
                        str_contains($activity, 'rename')                   => 'rename',
                        str_contains($activity, 'public') ||
                            str_contains($activity, 'private') ||
                            str_contains($activity, 'access')               => 'access',
                        str_contains($activity, 'login') ||
                            str_contains($activity, 'logged')               => 'login',
                        default                                             => 'login',
                    };
                    $badgeLabel = match($type) {
                        'upload' => 'Upload',
                        
                        'delete' => 'Delete',
                        'rename' => 'Rename',
                        'access' => 'Access',
                        'login'  => 'Login',
                        default  => ucfirst($type),
                    };
                    $isLast = $loop->last;
                @endphp

                <div class="al-log-item log-row"
                     data-search="{{ strtolower($log->user_name . ' ' . $log->activity . ' ' . $log->ip_address) }}">

                    {{-- Timeline dot + connector --}}
                    <div class="al-timeline-col">
                        <div class="al-dot {{ $type }}"></div>
                        @if(!$isLast)
                            <div class="al-line"></div>
                        @endif
                    </div>

                    {{-- Content --}}
                    <div class="al-content">
                        <div class="al-meta">
                            <span class="al-time">
                                {{ $log->created_at->format('M d, Y · h:i A') }}
                            </span>
                            <span class="al-ip">
                                <i class="fas fa-globe"></i>
                                {{ $log->ip_address ?? 'Unknown' }}
                            </span>
                        </div>
                        <div class="al-text">
                            <b>{{ $log->user_name }}</b>
                            <span class="al-detail">{{ $log->activity }}</span>
                            <span class="al-badge {{ $type }}">{{ $badgeLabel }}</span>
                        </div>
                    </div>

                </div>
            @endforeach
        @else
            <div class="al-empty">
                <i class="fas fa-folder-open"></i>
                <p>No activity logs found.</p>
            </div>
        @endif
    </div>

</div>

<script>
function filterLogs() {
    const q = document.getElementById('searchInput').value.toLowerCase();
    document.querySelectorAll('.log-row').forEach(row => {
        const text = row.getAttribute('data-search') || '';
        row.style.display = text.includes(q) ? '' : 'none';
    });
}
</script>

@endsection