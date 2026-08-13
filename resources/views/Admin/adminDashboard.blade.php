@extends('Admin.home')

@section('content')

<style>
.section-title { font-size: 12px; font-weight: 600; color: #6c757d; letter-spacing: .07em; text-transform: uppercase; margin: 0 0 10px; }
.metric-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 1.5rem; }
.metric-card { background: #f8f9fa; border-radius: 10px; padding: 16px 18px; }
.metric-card .label { font-size: 12px; color: #6c757d; margin: 0 0 6px; }
.metric-card .value { font-size: 26px; font-weight: 600; margin: 0; line-height: 1.1; }
.card-panel { background: #fff; border: 1px solid #e9ecef; border-radius: 12px; padding: 18px 20px; margin-bottom: 16px; }
.rtable { width: 100%; border-collapse: collapse; font-size: 13px; }
.rtable th { font-size: 11px; color: #6c757d; text-transform: uppercase; letter-spacing: .05em; font-weight: 600; padding: 6px 10px; border-bottom: 1px solid #dee2e6; text-align: left; }
.rtable td { padding: 9px 10px; border-bottom: 1px solid #f1f3f5; vertical-align: middle; }
.rtable tr:last-child td { border-bottom: none; }

.quick-links { display: flex; gap: 10px; flex-wrap: wrap; }
.quick-links a {
    display: inline-flex;
    align-items: center;
    padding: 9px 16px;
    border-radius: 8px;
    border: 1px solid #dee2e6;
    color: #212529;
    font-size: 14px;
    font-weight: 500;
    text-decoration: none;
    transition: background .15s, border-color .15s;
}
.quick-links a:hover { background: #f8f9fa; border-color: #adb5bd; }
.quick-links a:focus-visible {
    outline: 2px solid #0d6efd;
    outline-offset: 2px;
}
.quick-links a.primary { background: #0d6efd; border-color: #0d6efd; color: #fff; }
.quick-links a.primary:hover { background: #0b5ed7; border-color: #0a58ca; }

@media (max-width: 700px) {
    .metric-grid { grid-template-columns: 1fr; }
}
</style>

<div class="container-fluid py-3">

    <h5 class="fw-bold mb-1">Admin Dashboard</h5>
    <p class="text-muted mb-4" style="font-size:13px;">Overview of your folders, files, and storage</p>

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
            <p class="value" style="color:#b45309;">{{ $storageFormatted }}</p>
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
            <a href="{{ route('folders.index') }}" class="text-muted" style="font-size:12px;">View all</a>
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
                        <td style="color:#6c757d;">{{ $folder->created_at->format('M j, Y g:i A') }}</td>
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
