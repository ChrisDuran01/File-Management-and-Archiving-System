@extends($layout ?? 'Admin.home')
@section('content')

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

<style>
    .back-link {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 13px;
        color: #888;
        text-decoration: none;
        margin-bottom: 1.25rem;
        transition: color 0.15s;
    }
    .back-link:hover { color: #534AB7; }

    .archive-header {
        background: #fff;
        border: 1px solid #ececec;
        border-radius: 16px;
        padding: 1.25rem 1.5rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1rem;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
    }
    .archive-header-icon {
        width: 52px;
        height: 52px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        flex-shrink: 0;
        background: #f4f4f5;
        color: #71717a;
    }
    .archive-header-info { flex: 1; min-width: 0; }
    .archive-header-name {
        font-size: 16px;
        font-weight: 600;
        color: #1a1a1a;
        margin: 0 0 3px;
    }
    .archive-header-meta {
        font-size: 12px;
        color: #aaa;
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
    }
    .archive-header-meta span { display: flex; align-items: center; gap: 4px; }
    .view-only-note {
        font-size: 11px;
        color: #854F0B;
        background: #faeeda;
        border-radius: 20px;
        padding: 4px 12px;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        white-space: nowrap;
    }

    .file-list {
        background: #fff;
        border: 1px solid #ececec;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
    }
    .file-row {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 18px;
        border-bottom: 1px solid #f4f4f4;
        text-decoration: none;
        color: #1a1a1a;
        transition: background 0.12s;
    }
    .file-row:last-child { border-bottom: none; }
    .file-row:hover { background: #f8f8fb; color: #1a1a1a; }
    .file-row-icon {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 13px;
        background: #eff4ff;
        color: #2563eb;
        flex-shrink: 0;
    }
    .file-row-name { font-size: 13px; font-weight: 500; flex: 1; word-break: break-word; }
    .file-row-chevron { color: #ccc; font-size: 12px; }

    .empty-state {
        text-align: center;
        padding: 3rem 1rem;
        color: #bbb;
    }
    .empty-state i { font-size: 2.5rem; display: block; margin-bottom: 10px; opacity: 0.4; }
</style>

<div style="max-width:760px; margin:0 auto;">

    <a href="{{ route('archives.index') }}" class="back-link">
        <i class="fas fa-arrow-left"></i> Back to Archives
    </a>

    <div class="archive-header">
        <div class="archive-header-icon"><i class="fas fa-box-archive"></i></div>
        <div class="archive-header-info">
            <p class="archive-header-name">{{ $archive->folder_name }}</p>
            <div class="archive-header-meta">
                <span><i class="fas fa-file-archive"></i> {{ $archive->zip_name }}</span>
                <span><i class="far fa-clock"></i> {{ $archive->archived_at ? $archive->archived_at->format('Y-m-d H:i') : 'N/A' }}</span>
                <span><i class="fas fa-files"></i> {{ count($filenames) }} {{ Str::plural('file', count($filenames)) }}</span>
            </div>
        </div>
        <span class="view-only-note"><i class="fas fa-eye"></i> View only</span>
    </div>

    <div class="file-list">
        @forelse($filenames as $filename)
            @php $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION)); @endphp
            <a href="{{ route('archives.previewFile', ['id' => $archive->id, 'filename' => $filename]) }}" class="file-row">
                <span class="file-row-icon"><i class="fas fa-file"></i></span>
                <span class="file-row-name">{{ $filename }}</span>
                <span style="font-size:11px; color:#aaa; text-transform:uppercase;">{{ $ext }}</span>
                <i class="fas fa-chevron-right file-row-chevron"></i>
            </a>
        @empty
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <p>No files recorded for this archive.</p>
            </div>
        @endforelse
    </div>

</div>

@endsection
