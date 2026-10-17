@foreach($archivedFileItems as $file)
    @php
        $ext = strtolower(pathinfo($file->filename, PATHINFO_EXTENSION));

        $iconName = match(true) {
            $ext === 'pdf' => 'fa-file-pdf',
            in_array($ext, ['doc', 'docx']) => 'fa-file-word',
            in_array($ext, ['xls', 'xlsx', 'csv']) => 'fa-file-excel',
            in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']) => 'fa-file-image',
            default => 'fa-file-alt',
        };

        // Escape first, then wrap the matched term - never inject raw
        // extracted document text as HTML.
        $highlightedSnippet = null;
        if (!empty($file->ocr_snippet)) {
            $highlightedSnippet = preg_replace(
                '/(' . preg_quote(e($query), '/') . ')/i',
                '<mark>$1</mark>',
                e($file->ocr_snippet)
            );
        }
    @endphp

    <a href="{{ route('archives.previewFile', ['id' => $file->archive_id, 'filename' => $file->filename]) }}" class="live-search-row">
        <span class="live-search-icon archive"><i class="fas {{ $iconName }}"></i></span>
        <span class="live-search-text">
            <span class="live-search-name">{{ $file->filename }}</span>
            <span class="live-search-meta">
                Archived &middot; {{ $file->archive->folder_name }}
                @if($file->matched_in_content ?? false)
                    &middot; found in document text
                @endif
                @if($file->matched_in_document ?? false)
                    &middot; matched document details
                @endif
                @if($file->matched_in_school_year ?? false)
                    &middot; matched school year
                @endif
            </span>
            @if($highlightedSnippet)
                <span class="live-search-snippet">{!! $highlightedSnippet !!}</span>
            @endif
        </span>
    </a>
@endforeach
