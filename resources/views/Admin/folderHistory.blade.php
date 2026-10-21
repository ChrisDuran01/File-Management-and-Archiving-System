@extends($layout ?? 'Admin.home')
@section('title', 'School Year '.$year)
@section('content')

@include('Admin.partials.theme')

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

<style>
.folder-card {
    border-radius: 14px;
    border: 1px solid #ececec;
    background: #fff;
    transition: all 0.2s ease;
    position: relative;
}
.folder-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.08);
    border-color: #d5d5d5;
}

.folder-count {
    font-size: 12px;
    background: #f4f4f4;
    border: 1px solid #e8e8e8;
    border-radius: 20px;
    padding: 3px 10px;
    color: #666;
}

.section-divider {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 2rem 0 1rem;
    color: var(--text-3);
    font-size: 13px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: .05em;
}
.section-divider::before,
.section-divider::after {
    content: '';
    flex: 1;
    height: 1px;
    background: #ececec;
}

.file-card {
    border-radius: 12px;
    border: 1px solid #ececec;
    background: #fff;
    transition: all 0.2s ease;
}
.file-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.07);
    border-color: #d5d5d5;
}
.file-icon-wrap {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    margin: 0 auto 8px;
    background: #f4f4f4;
    color: #666;
}
.file-name {
    font-size: 12px;
    font-weight: 500;
    color: #333;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 100%;
}
.file-meta { font-size: 11px; color: var(--text-3); margin-top: 2px; }

/* ===== DARK MODE (most classes handled by partials/theme shared block) ===== */
[data-theme="dark"] .folder-card,
[data-theme="dark"] .file-card { background: var(--card); border-color: var(--border); }
[data-theme="dark"] .folder-card:hover,
[data-theme="dark"] .file-card:hover { border-color: var(--text-3); }
[data-theme="dark"] .file-icon-wrap { background: var(--nav-hover-bg); color: var(--text-2); }
</style>

{{-- Skeleton placeholder, shown until the real content below is revealed --}}
<div class="skel-shell" aria-hidden="true">
    <div class="page-header">
        <div>
            <div class="skel skel-text" style="width:160px; height:22px; margin-bottom:8px;"></div>
            <div class="skel skel-text" style="width:320px;"></div>
        </div>
        <div class="skel" style="width:190px; height:36px; border-radius:8px;"></div>
    </div>

    <div class="skel skel-text" style="width:80px; margin-bottom:1.5rem;"></div>

    <div class="row g-3">
        @for ($i = 0; $i < 12; $i++)
        <div class="col-xl-2 col-lg-2 col-md-3 col-sm-4 col-6">
            <div class="folder-card p-3 text-center">
                <div class="skel" style="width:64px; height:64px; border-radius:12px; margin:0 auto 12px;"></div>
                <div class="skel skel-text" style="width:70%; margin:0 auto;"></div>
            </div>
        </div>
        @endfor
    </div>
</div>

<div class="skel-real">

{{-- Page Header --}}
<div class="page-header">
    <div>
        <h1>School Year {{ $year }}</h1>
        <p class="sub">Read-only view of folders and files stored under this school year</p>
    </div>
    <a href="{{ route('folders.index') }}" class="btn btn-light">
        <i class="bi bi-arrow-left me-1"></i> Back to Current Folders
    </a>
</div>

<span class="folder-count">{{ $folders->count() }} folders</span>

<br><br>

{{-- Folders Grid --}}
<div class="row g-3">
    @forelse($folders as $folder)
    <div class="col-xl-2 col-lg-2 col-md-3 col-sm-4 col-6">
        <div class="folder-card p-3 text-center">
            <a href="{{ route('folders.show', $folder->id) }}" style="text-decoration:none; color:inherit;">
                @include('Admin.partials.folder-icon')
                <div class="mt-2 text-truncate" style="font-size:13px; font-weight:500;">
                    {{ $folder->name }}
                </div>
            </a>
        </div>
    </div>
    @empty
    <div class="col-12 text-center py-5 text-muted">
        <i class="bi bi-folder-x" style="font-size:4rem; opacity:0.3;"></i>
        <p class="mt-3">No folders were stored under this school year.</p>
    </div>
    @endforelse
</div>

</div>

@endsection
