@extends($layout ?? 'Admin.home')
@section('content')

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

@php
    $iconMap = [
        'pdf'  => ['fas fa-file-pdf',   '#e74c3c', '#fdecea'],
        'doc'  => ['fas fa-file-word',  '#2b5797', '#e8eef8'],
        'docx' => ['fas fa-file-word',  '#2b5797', '#e8eef8'],
        'xls'  => ['fas fa-file-excel', '#1d7044', '#e6f4ec'],
        'xlsx' => ['fas fa-file-excel', '#1d7044', '#e6f4ec'],
        'csv'  => ['fas fa-file-csv',   '#1d7044', '#e6f4ec'],
        'mp4'  => ['fas fa-file-video', '#e67e22', '#fef5ea'],
        'mp3'  => ['fas fa-file-audio', '#16a085', '#e8f8f5'],
        'txt'  => ['fas fa-file-alt',   '#555',    '#f5f5f5'],
    ];
    $icon = $iconMap[$ext] ?? ['fas fa-file', '#888', '#f5f5f5'];

    $streamUrl = route('archives.streamFile', ['id' => $archive->id, 'filename' => $filename]);
@endphp

<style>
.preview-wrapper { max-width: 960px; margin: 0 auto; }
.back-link {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: 13px; color: #888; text-decoration: none; margin-bottom: 1.25rem;
}
.back-link:hover { color: #534AB7; }

.file-header {
    background: #fff; border: 1px solid #ececec; border-radius: 16px;
    padding: 1.25rem 1.5rem; display: flex; align-items: center; gap: 1rem;
    margin-bottom: 1rem; box-shadow: 0 2px 10px rgba(0,0,0,0.04);
}
.file-header-icon {
    width: 52px; height: 52px; border-radius: 12px; display: flex;
    align-items: center; justify-content: center; font-size: 22px; flex-shrink: 0;
}
.file-header-info { flex: 1; min-width: 0; }
.file-header-name {
    font-size: 16px; font-weight: 600; color: #1a1a1a;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin: 0 0 3px;
}
.file-header-meta { font-size: 12px; color: #aaa; }
.view-only-note {
    font-size: 11px; color: #854F0B; background: #faeeda; border-radius: 20px;
    padding: 4px 12px; display: inline-flex; align-items: center; gap: 5px; white-space: nowrap;
}

.preview-container {
    background: #fff; border: 1px solid #ececec; border-radius: 16px;
    overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.04); min-height: 200px;
}
.preview-image-wrap {
    background: repeating-conic-gradient(#f0f0f0 0% 25%, #fff 0% 50%) 0 0 / 20px 20px;
    display: flex; align-items: center; justify-content: center; padding: 2rem; min-height: 420px;
}
.preview-image-wrap img { max-width: 100%; max-height: 560px; border-radius: 8px; box-shadow: 0 8px 32px rgba(0,0,0,0.14); object-fit: contain; }
.preview-container iframe { display: block; width: 100%; height: 640px; border: none; }
.preview-video-wrap { background: #0a0a0a; display: flex; align-items: center; justify-content: center; padding: 1.5rem; }
.preview-video-wrap video { max-width: 100%; max-height: 560px; border-radius: 8px; }
.preview-text-wrap { padding: 16px; background: #f8f8f8; max-height: 600px; overflow: auto; }
.text-viewer { font-family: monospace; font-size: 13px; white-space: pre-wrap; word-break: break-word; color: #333; }
.preview-audio-wrap {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    padding: 3rem 2rem; gap: 1.5rem; background: linear-gradient(135deg, #f8f9ff 0%, #f0f4ff 100%);
}
.audio-icon-ring {
    width: 80px; height: 80px; border-radius: 50%; background: #fff; border: 1px solid #ececec;
    display: flex; align-items: center; justify-content: center; font-size: 32px; box-shadow: 0 4px 20px rgba(0,0,0,0.08);
}
.preview-audio-wrap audio { width: 100%; max-width: 480px; }
.preview-none {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    padding: 4rem 2rem; text-align: center; gap: 0.75rem; background: #fafafa;
}
.preview-none-icon {
    width: 80px; height: 80px; border-radius: 20px; display: flex;
    align-items: center; justify-content: center; font-size: 34px;
}
.preview-none h5 { font-size: 16px; font-weight: 600; color: #333; margin: 0; }
.preview-none p { font-size: 13px; color: #999; margin: 0; max-width: 340px; }
</style>

<div class="preview-wrapper">

    <a href="{{ route('archives.show', $archive->id) }}" class="back-link">
        <i class="fas fa-arrow-left"></i> Back to {{ $archive->folder_name }}
    </a>

    <div class="file-header">
        <div class="file-header-icon" style="background:{{ $icon[2] }}; color:{{ $icon[1] }};">
            <i class="{{ $icon[0] }}"></i>
        </div>
        <div class="file-header-info">
            <p class="file-header-name">{{ $filename }}</p>
            <div class="file-header-meta">Inside archived folder: {{ $archive->folder_name }}</div>
        </div>
        <span class="view-only-note"><i class="fas fa-eye"></i> View only - no download</span>
    </div>

    <div class="preview-container">

        @if($isImage)
            <div class="preview-image-wrap">
                <img src="{{ $streamUrl }}" alt="{{ $filename }}">
            </div>

        @elseif($isPdf)
            <iframe src="{{ $streamUrl }}"></iframe>

        @elseif($isVideo)
            <div class="preview-video-wrap">
                <video controls controlsList="nodownload">
                    <source src="{{ $streamUrl }}">
                </video>
            </div>

        @elseif($isAudio)
            <div class="preview-audio-wrap">
                <div class="audio-icon-ring"><i class="fas fa-music" style="color:#534AB7;"></i></div>
                <audio controls controlsList="nodownload">
                    <source src="{{ $streamUrl }}">
                </audio>
            </div>

        @elseif($isText)
            <div class="preview-text-wrap">
                <pre class="text-viewer">{{ $textContent }}</pre>
            </div>

        @else
            <div class="preview-none">
                <div class="preview-none-icon" style="background:{{ $icon[2] }}; color:{{ $icon[1] }};">
                    <i class="{{ $icon[0] }}"></i>
                </div>
                <h5>No in-browser preview for this file type</h5>
                <p>
                    {{ strtoupper($ext) }} files can't be viewed inline here. Ask this archive's
                    creator or a SuperAdmin to restore or download the full archive if you need
                    to open it.
                </p>
            </div>
        @endif

    </div>

</div>

@endsection
