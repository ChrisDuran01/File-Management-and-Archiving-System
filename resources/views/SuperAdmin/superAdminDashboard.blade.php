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
    .page-title-icon svg { width: 16px; height: 16px; }
    .page-header .as-of { font-size: 13px; color: var(--text-2); font-family: 'DM Mono', monospace; }

    /* ── Stat Cards ── */
    .bm-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
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
    .bm-stat-sub { font-size: 12px; color: var(--text-2); margin-top: 2px; }

    /* ── Card ── */
    .al-card {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        overflow: hidden;
        box-shadow: var(--shadow-sm);
    }
    .al-card-header {
        padding: 0.875rem 1.25rem;
        border-bottom: 1px solid var(--border);
        background: #f9fafb;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
    }
    .al-card-title {
        font-size: 15px;
        font-weight: 500;
        color: var(--text-1);
        margin: 0;
    }
    .al-card-link {
        font-size: 12px;
        color: var(--text-2);
        text-decoration: none;
    }
    .al-card-link:hover { color: var(--primary); }

    /* ── Activity timeline (reused from Activity Logs page) ── */
    .al-body { max-height: 380px; overflow-y: auto; }
    .al-log-item {
        display: flex;
        gap: 12px;
        padding: 0.75rem 1.25rem;
        border-bottom: 1px solid var(--border);
        align-items: flex-start;
    }
    .al-log-item:last-child { border-bottom: none; }
    .al-timeline-col { display: flex; flex-direction: column; align-items: center; flex-shrink: 0; }
    .al-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--primary); margin-top: 5px; flex-shrink: 0; }
    .al-dot.upload  { background: #185FA5; }
    .al-dot.delete  { background: #A32D2D; }
    .al-dot.rename  { background: #854F0B; }
    .al-dot.access  { background: #058028; }
    .al-dot.login   { background: #046322; }
    .al-line { width: 1.5px; flex: 1; background: var(--border); margin-top: 4px; min-height: 10px; }
    .al-content { flex: 1; min-width: 0; }
    .al-time { font-size: 12px; color: var(--text-2); font-family: 'DM Mono', monospace; }
    .al-text { font-size: 14px; color: var(--text-1); line-height: 1.45; }
    .al-text b { font-weight: 500; }
    .al-detail { color: var(--text-2); }
    .al-empty { text-align: center; padding: 2.5rem 1rem; color: var(--text-3); }
    .al-empty i { font-size: 1.75rem; margin-bottom: 10px; display: block; }
    .al-empty p { margin: 0; font-size: .875rem; }

    /* ── Backup mini card ── */
    .bk-name { font-size: 14px; color: var(--text-1); margin-bottom: 4px; }
    .bk-meta { font-size: 12px; color: var(--text-2); font-family: 'DM Mono', monospace; margin-bottom: 4px; }
    .bk-status { font-weight: 500; }
    .bk-status.ok  { color: #27500A; }
    .bk-status.bad { color: #791F1F; }
    .bk-time { font-size: 11px; color: var(--text-3); }

    /* ── Shortcut pills (same style as table action buttons elsewhere) ── */
    .shortcut-list { display: flex; flex-direction: column; gap: 6px; }
    .shortcut-pill {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 8px 12px;
        border-radius: var(--radius-sm);
        border: 1px solid var(--border);
        font-size: 13px;
        color: var(--text-1);
        text-decoration: none;
        transition: all .15s;
    }
    .shortcut-pill:hover { border-color: var(--primary); background: var(--primary-dim); color: var(--primary); }
    .shortcut-pill i { font-size: 12px; color: var(--text-3); }
</style>

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

{{-- ── Page Header ── --}}
<div class="page-header">
    <h4>
        <div class="page-title-icon">
            <svg viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect x="2" y="2" width="5" height="5" rx="1" stroke="#058028" stroke-width="1.2"/>
                <rect x="9" y="2" width="5" height="5" rx="1" stroke="#058028" stroke-width="1.2"/>
                <rect x="2" y="9" width="5" height="5" rx="1" stroke="#058028" stroke-width="1.2"/>
                <rect x="9" y="9" width="5" height="5" rx="1" stroke="#058028" stroke-width="1.2"/>
            </svg>
        </div>
        Dashboard
    </h4>
    <span class="as-of">{{ now()->format('M d, Y · h:i A') }}</span>
</div>

{{-- ── Stat Cards ── --}}
<div class="bm-stats">
    <div class="bm-stat">
        <div class="bm-stat-label">Users</div>
        <div class="bm-stat-val">{{ number_format($users) }}</div>
    </div>
    <div class="bm-stat">
        <div class="bm-stat-label">Officers</div>
        <div class="bm-stat-val">{{ number_format($activeOfficers) }}</div>
        <div class="bm-stat-sub">{{ number_format($formerOfficers) }} former</div>
    </div>
    <div class="bm-stat">
        <div class="bm-stat-label">Folders</div>
        <div class="bm-stat-val">{{ number_format($folders) }}</div>
    </div>
    <div class="bm-stat">
        <div class="bm-stat-label">Files</div>
        <div class="bm-stat-val">{{ number_format($files) }}</div>
    </div>
    <div class="bm-stat">
        <div class="bm-stat-label">Storage used</div>
        <div class="bm-stat-val">{{ $storageFormatted }}</div>
    </div>
</div>

<div class="row g-3">

    {{-- ── Recent Activity ── --}}
    <div class="col-md-8">
        <div class="al-card">
            <div class="al-card-header">
                <p class="al-card-title">Recent activity</p>
                <a href="/activityLogs" class="al-card-link">View all</a>
            </div>
            <div class="al-body">
                @if($activities->count() > 0)
                    @foreach($activities as $log)
                        @php
                            $a = strtolower($log->activity);
                            $type = match(true) {
                                str_contains($a, 'upload') || str_contains($a, 'added') => 'upload',
                                str_contains($a, 'delete')                               => 'delete',
                                str_contains($a, 'rename')                               => 'rename',
                                str_contains($a, 'public') || str_contains($a, 'private')
                                    || str_contains($a, 'access')                        => 'access',
                                default                                                   => 'login',
                            };
                            $isLast = $loop->last;
                        @endphp
                        <div class="al-log-item">
                            <div class="al-timeline-col">
                                <div class="al-dot {{ $type }}"></div>
                                @if(!$isLast)<div class="al-line"></div>@endif
                            </div>
                            <div class="al-content">
                                <div class="al-time">{{ $log->created_at->format('M d, Y · h:i A') }}</div>
                                <div class="al-text">
                                    <b>{{ $log->user_name }}</b>
                                    <span class="al-detail">{{ $log->activity }}</span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                @else
                    <div class="al-empty">
                        <i class="fas fa-inbox"></i>
                        <p>No activity recorded yet.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- ── Backup + Shortcuts ── --}}
    <div class="col-md-4 d-flex flex-column gap-3">

        <div class="al-card">
            <div class="al-card-header">
                <p class="al-card-title">Latest backup</p>
            </div>
            <div class="p-3">
                @if($latestBackup)
                    <div class="bk-name">{{ $latestBackup->name }}</div>
                    <div class="bk-meta">
                        {{ $latestBackup->size ?? '—' }} ·
                        <span class="bk-status {{ $latestBackup->status === 'Success' ? 'ok' : 'bad' }}">
                            {{ $latestBackup->status }}
                        </span>
                    </div>
                    <div class="bk-time">{{ $latestBackup->created_at->diffForHumans() }}</div>
                @else
                    <p class="text-muted mb-0" style="font-size:13px;">No backups yet.</p>
                @endif
            </div>
        </div>

        <div class="al-card">
            <div class="al-card-header">
                <p class="al-card-title">Shortcuts</p>
            </div>
            <div class="p-2">
                <div class="shortcut-list">
                    <a href="/addAdmin" class="shortcut-pill">Add officer <i class="fas fa-chevron-right"></i></a>
                    <a href="/manageAdmins" class="shortcut-pill">Manage admins <i class="fas fa-chevron-right"></i></a>
                    <a href="/backup" class="shortcut-pill">Backup settings <i class="fas fa-chevron-right"></i></a>
                </div>
            </div>
        </div>

    </div>

</div>

@endsection
