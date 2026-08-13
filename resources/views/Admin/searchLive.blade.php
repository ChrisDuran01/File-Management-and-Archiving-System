@php
    $folderItems  = $folders;
    $fileItems    = $files;
    $archiveItems = $archives;
    $total        = $folderItems->count() + $fileItems->count() + $archiveItems->count();
@endphp

@if($total === 0)
    <div class="live-search-empty">
        <i class="fas fa-search"></i> No matches for "{{ $query }}"
    </div>
@else
    <div class="live-search-list">
        @foreach($folderItems as $folder)
            <a href="{{ route('folders.show', $folder->id) }}" class="live-search-row">
                <span class="live-search-icon folder"><i class="fas fa-folder"></i></span>
                <span class="live-search-text">
                    <span class="live-search-name">{{ $folder->name }}</span>
                    <span class="live-search-meta">Folder</span>
                </span>
            </a>
        @endforeach

        @foreach($fileItems as $file)
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

            <a href="{{ route('files.preview', $file->id) }}" class="live-search-row">
                <span class="live-search-icon file"><i class="fas {{ $iconName }}"></i></span>
                <span class="live-search-text">
                    <span class="live-search-name">{{ $file->filename }}</span>
                    <span class="live-search-meta">
                        {{ $file->folder->name ?? 'Root' }}
                        @if($file->matched_in_content ?? false)
                            &middot; found in document text
                        @endif
                        @if($file->matched_in_folder ?? false)
                            &middot; matched folder name
                        @endif
                        @if($file->matched_in_school_year ?? false)
                            &middot; matched school year
                        @endif
                        @if($file->matched_in_type ?? false)
                            &middot; matched file type
                        @endif
                    </span>
                    @if($highlightedSnippet)
                        <span class="live-search-snippet">{!! $highlightedSnippet !!}</span>
                    @endif
                </span>
            </a>
        @endforeach

        @foreach($archiveItems as $archive)
            <a href="{{ route('archives.show', $archive->id) }}" class="live-search-row">
                <span class="live-search-icon archive"><i class="fas fa-box-archive"></i></span>
                <span class="live-search-text">
                    <span class="live-search-name">{{ $archive->folder_name }}</span>
                    <span class="live-search-meta">
                        Archived
                        @if($archive->matched_in_contents ?? false)
                            &middot; found: {{ implode(', ', array_slice($archive->matched_filenames, 0, 2)) }}
                            @if(count($archive->matched_filenames) > 2)
                                +{{ count($archive->matched_filenames) - 2 }} more
                            @endif
                        @endif
                    </span>
                </span>
            </a>
        @endforeach
    </div>

    <a href="{{ route('search', ['query' => $query]) }}" class="live-search-viewall">
        View all results for "{{ $query }}" <i class="fas fa-arrow-right"></i>
    </a>
@endif
