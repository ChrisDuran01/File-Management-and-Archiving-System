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
