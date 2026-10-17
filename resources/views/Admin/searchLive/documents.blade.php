@foreach($documentItems as $document)
    <a href="{{ route('documents.show', $document->id) }}" class="live-search-row">
        <span class="live-search-icon file"><i class="fas fa-file-lines"></i></span>
        <span class="live-search-text">
            <span class="live-search-name">{{ $document->title }}</span>
            <span class="live-search-meta">
                Document
                @if($document->document_type) &middot; {{ $document->document_type }} @endif
                @if($document->reference_no) &middot; {{ $document->reference_no }} @endif
                @if($document->folder) &middot; {{ $document->folder->name }} @endif
            </span>
        </span>
    </a>
@endforeach
