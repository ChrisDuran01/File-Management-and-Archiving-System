@extends($layout ?? 'Admin.home')
@section('title', $filename)
@section('content')

@include('Admin.partials.theme')

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
    font-size: 13px; color: var(--text-2); text-decoration: none; margin-bottom: 1.25rem;
}
.back-link:hover { color: #058028; }

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
    line-height: inherit;
}
.file-header-meta { font-size: 12px; color: var(--text-3); }
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
/* PDF pages drawn by PDF.js (no browser PDF toolbar: no download, print or drawing) */
.pdf-pages { max-height: 720px; overflow-y: auto; background: #525659; padding: 16px; display: grid; gap: 12px; justify-items: center; }
.pdf-page { width: 100%; max-width: 820px; background: #fff; box-shadow: 0 2px 10px rgba(0,0,0,.3); }
.pdf-page canvas { display: block; width: 100%; height: auto; }
.pdf-status { color: #f1f1f1; font-size: 13px; margin: 2rem 0; }
/* Word files drawn by docx-preview, as pages on the same grey */
.docx-view { max-height: 720px; overflow-y: auto; background: #525659; }
.docx-view > .pdf-status { text-align: center; padding: 0 16px; }
.docx-view .docx-wrapper { background: transparent; padding: 16px; }
.docx-view .docx-wrapper > section.docx { box-shadow: 0 2px 10px rgba(0,0,0,.3); margin-bottom: 12px; }
/* View only: printing the page leaves the file out. */
@media print { .preview-container { display: none !important; } }
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
.preview-none p { font-size: 13px; color: var(--text-3); margin: 0; max-width: 340px; }

/* ===== DARK MODE (page-unique classes; shared ones live in partials/theme) ===== */
[data-theme="dark"] .file-header,
[data-theme="dark"] .preview-container { background: var(--card); border-color: var(--border); box-shadow: none; }
[data-theme="dark"] .file-header-name { color: var(--text-1); }
[data-theme="dark"] .file-header-meta { color: var(--text-3); }
[data-theme="dark"] .view-only-note { color: #e3b877; background: var(--warning-dim); }
[data-theme="dark"] .preview-image-wrap { background: repeating-conic-gradient(#1c1c1a 0% 25%, #121211 0% 50%) 0 0 / 20px 20px; }
[data-theme="dark"] .preview-text-wrap { background: var(--surface); }
[data-theme="dark"] .preview-audio-wrap { background: linear-gradient(135deg, #14161c 0%, #12141a 100%); }
[data-theme="dark"] .preview-none h5 { color: var(--text-1); }
</style>

{{-- Skeleton placeholder, shown until the real content below is revealed --}}
<div class="skel-shell" aria-hidden="true">
    <div class="preview-wrapper">
        <div class="skel skel-text" style="width:160px; margin-bottom:1.25rem;"></div>

        <div class="file-header">
            <div class="skel" style="width:52px; height:52px; border-radius:12px;"></div>
            <div style="flex:1;">
                <div class="skel skel-text" style="width:220px; margin-bottom:8px;"></div>
                <div class="skel skel-text" style="width:150px;"></div>
            </div>
            <div class="skel" style="width:150px; height:26px; border-radius:20px;"></div>
        </div>

        <div class="preview-container">
            <div class="preview-image-wrap">
                <div class="skel" style="width:100%; max-width:480px; height:420px; border-radius:8px;"></div>
            </div>
        </div>
    </div>
</div>

<div class="preview-wrapper skel-real">

    <a href="{{ route('archives.show', $archive->id) }}" class="back-link">
        <i class="fas fa-arrow-left"></i> Back to {{ $archive->folder_name }}
    </a>

    <div class="file-header">
        <div class="file-header-icon" style="background:{{ $icon[2] }}; color:{{ $icon[1] }};">
            <i class="{{ $icon[0] }}"></i>
        </div>
        <div class="file-header-info">
            <h1 class="file-header-name">{{ $filename }}</h1>
            <div class="file-header-meta">Inside archived folder: {{ $archive->folder_name }}</div>
        </div>
        <span class="view-only-note"><i class="fas fa-eye"></i> View only - no download</span>
    </div>

    {{-- View only: no right-click "Save as" or dragging the file out --}}
    <div class="preview-container" oncontextmenu="return false;" ondragstart="return false;">

        @if($isImage)
            <div class="preview-image-wrap">
                <img src="{{ $streamUrl }}" alt="{{ $filename }}" draggable="false">
            </div>

        @elseif($isPdf)
            {{-- Drawn page by page with PDF.js (script below), not in the
                 browser's own PDF viewer, whose toolbar downloads, prints
                 and draws on the file. --}}
            <div class="pdf-pages" id="pdfPages" data-src="{{ $streamUrl }}">
                <p class="pdf-status" role="status">Opening…</p>
            </div>

        @elseif($isDocx)
            {{-- Drawn as pages with docx-preview (script below), the
                 officer phone app's Word viewer. --}}
            <div class="docx-view" id="docxView" data-src="{{ $streamUrl }}">
                <p class="pdf-status" role="status">Opening…</p>
            </div>

        @elseif($isVideo)
            <div class="preview-video-wrap">
                <video controls controlsList="nodownload">
                    <source src="{{ $streamUrl }}">
                </video>
            </div>

        @elseif($isAudio)
            <div class="preview-audio-wrap">
                <div class="audio-icon-ring"><i class="fas fa-music" style="color:#058028;"></i></div>
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
                    {{ strtoupper($ext) }} files can't be viewed inline here. Download this
                    folder's archive from its school year in Archives to open it, or ask the
                    Adviser or President to restore the school year.
                </p>
            </div>
        @endif

    </div>

</div>

@if($isPdf)
<script src="{{ asset('vendor/officer-app/pdf.min.js') }}"></script>
<script>
// Each page is drawn when it scrolls near (like the officer phone app's
// viewer), so a long PDF opens quickly.
(async function () {
    const box = document.getElementById('pdfPages');
    if (! box || ! window.pdfjsLib) return;
    pdfjsLib.GlobalWorkerOptions.workerSrc = @json(asset('vendor/officer-app/pdf.worker.min.js'));

    try {
        const response = await fetch(box.dataset.src, { credentials: 'same-origin' });
        if (! response.ok) throw new Error('HTTP ' + response.status);

        const pdf = await pdfjsLib.getDocument({ data: await response.arrayBuffer() }).promise;
        const first = (await pdf.getPage(1)).getViewport({ scale: 1 });

        box.replaceChildren();
        const slots = [];
        for (let n = 1; n <= pdf.numPages; n++) {
            const slot = document.createElement('div');
            slot.className = 'pdf-page';
            slot.style.aspectRatio = first.width + ' / ' + first.height;
            slot.dataset.page = String(n);
            slot.setAttribute('role', 'img');
            slot.setAttribute('aria-label', 'Page ' + n + ' of ' + pdf.numPages);
            box.append(slot);
            slots.push(slot);
        }

        let queue = Promise.resolve(); // one page at a time
        const near = new IntersectionObserver(entries => entries.forEach(entry => {
            if (! entry.isIntersecting) return;
            const slot = entry.target;
            near.unobserve(slot);
            queue = queue.then(async () => {
                const page = await pdf.getPage(Number(slot.dataset.page));
                const base = page.getViewport({ scale: 1 });
                const viewport = page.getViewport({ scale: (slot.clientWidth / base.width) * Math.min(window.devicePixelRatio || 1, 2) });
                const canvas = document.createElement('canvas');
                canvas.width = viewport.width;
                canvas.height = viewport.height;
                await page.render({ canvasContext: canvas.getContext('2d'), viewport: viewport }).promise;
                slot.style.aspectRatio = base.width + ' / ' + base.height;
                slot.replaceChildren(canvas);
            }).catch(() => {});
        }), { root: box, rootMargin: '150% 0px' });

        slots.forEach(slot => near.observe(slot));
    } catch (e) {
        box.innerHTML = '<p class="pdf-status" role="status">This PDF could not be shown.</p>';
    }
})();
</script>
@endif

@if($isDocx)
<script src="{{ asset('vendor/officer-app/jszip.min.js') }}"></script>
<script src="{{ asset('vendor/officer-app/docx-preview.min.js') }}"></script>
<script>
(async function () {
    const box = document.getElementById('docxView');
    if (! box || ! window.docx) return;

    try {
        const response = await fetch(box.dataset.src, { credentials: 'same-origin' });
        if (! response.ok) throw new Error('HTTP ' + response.status);

        const doc = document.createElement('div');
        box.replaceChildren(doc);
        await window.docx.renderAsync(await response.blob(), doc);
    } catch (e) {
        box.innerHTML = '<p class="pdf-status" role="status">This Word file could not be shown.</p>';
    }
})();
</script>
@endif

@endsection
