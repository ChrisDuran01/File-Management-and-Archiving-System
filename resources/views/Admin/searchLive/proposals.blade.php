@foreach($proposalItems as $proposal)
    <a href="{{ route('proposals.show', $proposal) }}" class="live-search-row">
        <span class="live-search-icon file"><i class="fas fa-clipboard-list"></i></span>
        <span class="live-search-text">
            <span class="live-search-name">{{ $proposal->title }}</span>
            <span class="live-search-meta">
                Activity proposal &middot; {{ $proposal->statusLabel() }} &middot; {{ $proposal->dateLabel() }}
                @if($proposal->matched_item) &middot; Budget: {{ $proposal->matched_item }} @endif
            </span>
        </span>
    </a>
@endforeach
