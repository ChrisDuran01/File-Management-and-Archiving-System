@extends('Admin.home')

@section('content')

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

@php
    $ext      = strtolower(pathinfo($file->filename, PATHINFO_EXTENSION));
    $isImage  = in_array($ext, ['jpg','jpeg','png','gif','webp','svg']);
    $isPdf    = $ext === 'pdf';
    $isVideo  = in_array($ext, ['mp4','mov','webm','avi']);
    $isAudio  = in_array($ext, ['mp3','wav','ogg','m4a']);
    $isText   = in_array($ext, ['txt','md','csv','log','json','xml','html','css','js','php']);

    $iconMap = [
        'pdf'  => ['fas fa-file-pdf',   '#e74c3c', '#fdecea'],
        'doc'  => ['fas fa-file-word',  '#2b5797', '#e8eef8'],
        'docx' => ['fas fa-file-word',  '#2b5797', '#e8eef8'],
        'xls'  => ['fas fa-file-excel', '#1d7044', '#e6f4ec'],
        'xlsx' => ['fas fa-file-excel', '#1d7044', '#e6f4ec'],
        'csv'  => ['fas fa-file-csv',   '#1d7044', '#e6f4ec'],
        'ppt'  => ['fas fa-file-powerpoint','#c7511f','#fdf0ea'],
        'pptx' => ['fas fa-file-powerpoint','#c7511f','#fdf0ea'],
        'zip'  => ['fas fa-file-archive','#8e44ad','#f5eefa'],
        'rar'  => ['fas fa-file-archive','#8e44ad','#f5eefa'],
        'mp4'  => ['fas fa-file-video', '#e67e22','#fef5ea'],
        'mov'  => ['fas fa-file-video', '#e67e22','#fef5ea'],
        'mp3'  => ['fas fa-file-audio', '#16a085','#e8f8f5'],
        'wav'  => ['fas fa-file-audio', '#16a085','#e8f8f5'],
        'txt'  => ['fas fa-file-alt',   '#555',   '#f5f5f5'],
        'json' => ['fas fa-file-code',  '#f39c12','#fef9e7'],
        'php'  => ['fas fa-file-code',  '#6c3483','#f5eefa'],
        'js'   => ['fas fa-file-code',  '#f1c40f','#fefce6'],
        'html' => ['fas fa-file-code',  '#e74c3c','#fdecea'],
        'css'  => ['fas fa-file-code',  '#3498db','#eaf4fc'],
    ];

    $icon    = $iconMap[$ext] ?? ['fas fa-file', '#888', '#f5f5f5'];
    $sizeKb  = isset($file->size) ? ($file->size >= 1048576
                    ? round($file->size / 1048576, 1) . ' MB'
                    : round($file->size / 1024, 1) . ' KB')
                : null;
@endphp

<style>
.preview-wrapper {
    max-width: 960px;
    margin: 0 auto;
    animation: fadeUp 0.35s ease both;
}
@keyframes fadeUp {
    from { opacity:0; transform:translateY(16px); }
    to   { opacity:1; transform:translateY(0); }
}

/* ── Breadcrumb / back link ── */
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
.back-link i { font-size: 12px; }

/* ── File header card ── */
.file-header {
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
.file-header-icon {
    width: 52px;
    height: 52px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    flex-shrink: 0;
}
.file-header-info { flex: 1; min-width: 0; }
.file-header-name {
    font-size: 16px;
    font-weight: 600;
    color: #1a1a1a;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin: 0 0 3px;
}
.file-header-meta {
    font-size: 12px;
    color: #aaa;
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}
.file-header-meta span { display: flex; align-items: center; gap: 4px; }

.btn-download {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 8px 18px;
    background: #534AB7;
    color: #fff;
    border: none;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 500;
    text-decoration: none;
    white-space: nowrap;
    transition: background 0.2s, transform 0.15s;
    flex-shrink: 0;
}
.btn-download:hover { background: #3C3489; color: #fff; transform: translateY(-1px); }
.btn-download i { font-size: 12px; }

/* ── Preview container ── */
.preview-container {
    background: #fff;
    border: 1px solid #ececec;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.04);
    min-height: 200px;
}

/* Image */
.preview-image-wrap {
    background: repeating-conic-gradient(#f0f0f0 0% 25%, #fff 0% 50%) 0 0 / 20px 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem;
    min-height: 420px;
}
.preview-image-wrap img {
    max-width: 100%;
    max-height: 560px;
    border-radius: 8px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.14);
    object-fit: contain;
}

/* PDF */
.preview-container iframe {
    display: block;
    width: 100%;
    height: 640px;
    border: none;
}

/* Video */
.preview-video-wrap {
    background: #0a0a0a;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1.5rem;
}
.preview-video-wrap video {
    max-width: 100%;
    max-height: 560px;
    border-radius: 8px;
}
/* TEXT PREVIEW */
.preview-text-wrap {
    padding: 16px;
    background: #f8f8f8;
    max-height: 600px;
    overflow: auto;
}

.text-viewer {
    font-family: monospace;
    font-size: 13px;
    white-space: pre-wrap;
    word-break: break-word;
    color: #333;
}


/* Audio */
.preview-audio-wrap {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3rem 2rem;
    gap: 1.5rem;
    background: linear-gradient(135deg, #f8f9ff 0%, #f0f4ff 100%);
}
.audio-icon-ring {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: #fff;
    border: 1px solid #ececec;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 32px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
}
.preview-audio-wrap audio { width: 100%; max-width: 480px; }

/* No preview */
.preview-none {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 4rem 2rem;
    text-align: center;
    gap: 1rem;
    background: #fafafa;
}
.preview-none-icon {
    width: 80px;
    height: 80px;
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 34px;
}
.preview-none h5 {
    font-size: 16px;
    font-weight: 600;
    color: #333;
    margin: 0;
}
.preview-none p {
    font-size: 13px;
    color: #999;
    margin: 0;
    max-width: 320px;
}

/* Toolbar strip (for image/pdf) */
.preview-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 16px;
    border-bottom: 1px solid #f0f0f0;
    background: #fafafa;
    font-size: 12px;
    color: #999;
}
.preview-toolbar-actions { display: flex; gap: 8px; }
.tb-btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 12px;
    border: 1px solid #e5e5e5;
    border-radius: 7px;
    background: #fff;
    color: #555;
    font-size: 12px;
    text-decoration: none;
    cursor: pointer;
    transition: background 0.15s, border-color 0.15s;
}
.tb-btn:hover { background: #f0f0f0; border-color: #d5d5d5; color: #333; }
</style>

<div class="preview-wrapper">

    {{-- =============================
         BACK BUTTON
    ============================== --}}
    <a href="javascript:history.back()" class="back-link">
        <i class="fas fa-arrow-left"></i> Back
    </a>

    {{-- =============================
         FILE HEADER (INFO CARD)
    ============================== --}}
    <div class="file-header">

        {{-- File icon --}}
        <div class="file-header-icon"
             style="background:{{ $icon[2] }}; color:{{ $icon[1] }};">
            <i class="{{ $icon[0] }}"></i>
        </div>

        {{-- File details --}}
        <div class="file-header-info">
            <p class="file-header-name">{{ $file->filename }}</p>

            <div class="file-header-meta">
                <span><i class="fas fa-tag"></i> {{ strtoupper($ext) }}</span>

                {{-- File size --}}
                @if($sizeKb)
                <span><i class="fas fa-weight-hanging"></i> {{ $sizeKb }}</span>
                @endif

                {{-- Upload date --}}
                @if(isset($file->created_at))
                <span><i class="fas fa-calendar-alt"></i> {{ $file->created_at->format('M d, Y') }}</span>
                @endif

                {{-- Folder name --}}
                @if(isset($file->folder) && $file->folder)
                <span><i class="fas fa-folder"></i> {{ $file->folder->name }}</span>
                @endif
            </div>
        </div>

        {{-- Download button --}}
        <a href="{{ route('files.download', $file->id) }}" class="btn-download">
    <i class="fas fa-download"></i> Download
</a>
    </div>

    {{-- =============================
         FILE PREVIEW SECTION
    ============================== --}}
    <div class="preview-container">

    {{-- IMAGE --}}
    @if($isImage)
        <div class="preview-image-wrap">
            <img src="{{ $signedUrl }}" alt="{{ $file->filename }}">
        </div>

    {{-- PDF --}}
    @elseif($isPdf)
        <iframe src="{{ $signedUrl }}"></iframe>

    {{-- VIDEO --}}
    @elseif($isVideo)
        <div class="preview-video-wrap">
            <video controls>
                <source src="{{ $signedUrl }}">
            </video>
        </div>

    {{-- AUDIO --}}
    @elseif($isAudio)
        <div class="preview-audio-wrap">
            <audio controls>
                <source src="{{ $signedUrl }}">
            </audio>
        </div>

    {{-- TEXT --}}
    @elseif($isText)
        <div class="preview-text-wrap">

            <pre class="text-viewer">{{ $textContent }}</pre>
        </div>

    {{-- OFFICE (DOC, XLS, PPT) --}}
    @elseif(in_array($ext, ['doc','docx','xls','xlsx','ppt','pptx']))
        <iframe 
            src="https://view.officeapps.live.com/op/embed.aspx?src={{ urlencode($signedUrl) }}">
        </iframe>

    {{-- FALLBACK --}}
    @else
        <div class="preview-none">
            <h5>No preview available</h5>
            <p>This file type ({{ strtoupper($ext) }}) cannot be previewed.</p>

            <a href="{{ route('files.download', $file->id) }}" class="btn-download">
                <i class="fas fa-download"></i> Download file
            </a>
        </div>
    @endif

</div>

</div>

@endsection