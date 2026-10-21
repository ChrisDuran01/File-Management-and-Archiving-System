@extends('Admin.home')
@section('title', 'Dashboard')

@section('content')

@include('Admin.partials.theme')

<style>
.section-title { font-size: 12px; font-weight: 600; color: var(--text-2); letter-spacing: .07em; text-transform: uppercase; margin: 0 0 10px; }
.metric-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 1.5rem; }
.metric-card { background: var(--card); border-radius: 10px ; border: 1px solid var(--border); padding: 16px 18px; }
.metric-card .label { font-size: 12px; color: var(--text-2); margin: 0 0 6px; }
.metric-card .value { font-size: 26px; font-weight: 600; margin: 0; line-height: 1.1; }
.card-panel { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 18px 20px; margin-bottom: 16px; }
.rtable { width: 100%; border-collapse: collapse; font-size: 13px; }
.rtable th { font-size: 11px; color: var(--text-2); text-transform: uppercase; letter-spacing: .05em; font-weight: 600; padding: 6px 10px; border-bottom: 1px solid var(--border); text-align: left; }
.rtable td { padding: 9px 10px; border-bottom: 1px solid var(--border); vertical-align: middle; }
.rtable tr:last-child td { border-bottom: none; }

.quick-links { display: flex; gap: 10px; flex-wrap: wrap; }
.quick-links a {
    display: inline-flex;
    align-items: center;
    padding: 9px 16px;
    border-radius: 8px;
    border: 1px solid var(--border);
    color: var(--text-1);
    font-size: 14px;
    font-weight: 500;
    text-decoration: none;
    transition: background .15s, border-color .15s;
}
.quick-links a:hover { background: var(--surface); border-color: var(--text-3); }
.quick-links a:focus-visible {
    outline: 2px solid var(--primary);
    outline-offset: 2px;
}
.quick-links a.primary { background: var(--primary-fill); border-color: var(--primary-fill); color: #fff; }
.quick-links a.primary:hover { background: var(--primary-fill-hover); border-color: var(--primary-fill-hover); }

@media (max-width: 700px) {
    .metric-grid { grid-template-columns: 1fr; }
}
</style>

{{-- Skeleton placeholder, shown until the real content below is revealed --}}
<div class="container-fluid py-3 skel-shell" aria-hidden="true">
    <div class="page-header">
        <div>
            <div class="skel skel-text" style="width:180px; height:22px; margin-bottom:8px;"></div>
            <div class="skel skel-text" style="width:280px;"></div>
        </div>
    </div>

    <section class="metric-grid">
        @for ($i = 0; $i < 3; $i++)
        <div class="metric-card">
            <div class="skel skel-text" style="width:70%; margin-bottom:10px;"></div>
            <div class="skel skel-text" style="width:45%; height:26px;"></div>
        </div>
        @endfor
    </section>

    <div class="card-panel">
        <div class="skel skel-text" style="width:110px; margin-bottom:14px;"></div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <div class="skel" style="width:140px; height:36px; border-radius:8px;"></div>
            <div class="skel" style="width:120px; height:36px; border-radius:8px;"></div>
            <div class="skel" style="width:130px; height:36px; border-radius:8px;"></div>
        </div>
    </div>

    <div class="card-panel">
        <div class="skel skel-text" style="width:130px; margin-bottom:16px;"></div>
        @for ($i = 0; $i < 4; $i++)
        <div style="display:flex; gap:16px; padding:9px 0; border-bottom:1px solid var(--border);">
            <div class="skel skel-text" style="width:45%;"></div>
            <div class="skel skel-text" style="width:30%;"></div>
        </div>
        @endfor
    </div>
</div>

<div class="container-fluid py-3 skel-real">

    <div class="page-header">
        <div>
            <h1>Admin Dashboard</h1>
        </div>
    </div>

    {{-- METRIC CARDS --}}
    <section aria-label="Summary metrics" class="metric-grid">
        <div class="metric-card">
            <p class="label">Total folders</p>
            <p class="value text-primary">{{ number_format($totalFolders) }}</p>
        </div>
        <div class="metric-card">
            <p class="label">Total files</p>
            <p class="value text-success">{{ number_format($totalFiles) }}</p>
        </div>
        <div class="metric-card">
            <p class="label">Storage used</p>
            <p class="value" style="color:var(--warning);">{{ $storageFormatted }}</p>
        </div>
    </section>

    {{-- QUICK ACTIONS --}}
    <div class="card-panel">
        <p class="section-title">Quick actions</p>
        <nav class="quick-links" aria-label="Quick actions">
            <a href="{{ route('folders.index') }}" class="primary">Manage Folders</a>
            <a href="{{ route('reports.index') }}">View Reports</a>
            <a href="{{ route('archives.index') }}">View Archives</a>
        </nav>
    </div>

    {{-- RECENT FOLDERS --}}
    <div class="card-panel">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <p class="section-title mb-0">Recent folders</p>
            <a href="{{ route('folders.index') }}" class="text-muted" style="font-size:12px;">View all folders</a>
        </div>

        <table class="rtable" aria-label="Recently created folders">
            <thead>
                <tr>
                    <th scope="col">Folder name</th>
                    <th scope="col">Date created</th>
                </tr>
            </thead>
            <tbody>
                @forelse($recentFolders as $folder)
                    <tr>
                        <td>{{ $folder->name }}</td>
                        <td style="color:var(--text-2);">{{ $folder->created_at->format('M j, Y g:i A') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="2" class="text-center text-muted py-3">No folders yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>

@endsection
