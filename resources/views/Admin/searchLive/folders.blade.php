@foreach($folderItems as $folder)
    <a href="{{ route('folders.show', $folder->id) }}" class="live-search-row">
        <span class="live-search-icon folder"><i class="fas fa-folder"></i></span>
        <span class="live-search-text">
            <span class="live-search-name">{{ $folder->name }}</span>
            <span class="live-search-meta">Folder</span>
        </span>
    </a>
@endforeach
