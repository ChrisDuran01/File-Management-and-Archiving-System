@extends('Admin.home')
@section('content')

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

<style>
body { font-family: 'Segoe UI', sans-serif; }

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

.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 10px; }
.page-title { font-size: 20px; font-weight: 600; color: #1a1a1a; margin: 0; }
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
    color: #999;
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
.file-meta { font-size: 11px; color: #aaa; margin-top: 2px; }
</style>

{{-- Page Header --}}
<div class="page-header">
    <div>
        <h4 class="page-title">School Year {{ $year }}</h4>
        <p class="text-muted mb-0" style="font-size:13px;">Read-only view of folders and files stored under this school year</p>
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
                <i class="bi bi-folder-fill text-warning" style="font-size:4rem;"></i>
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


@endsection
