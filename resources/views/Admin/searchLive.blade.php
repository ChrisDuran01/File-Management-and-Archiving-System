@php
    $folderItems   = $folders;
    $fileItems     = $files;
    $archiveItems  = $archives;
    $documentItems = $documents ?? collect();
    $archivedFileItems = $archivedFiles ?? collect();
    $total         = $folderItems->count() + $fileItems->count() + $archiveItems->count() + $documentItems->count() + $archivedFileItems->count();

    // Which section renders first depends on where the search was fired
    // from (set via window.searchContext in the topbar's fetch - see
    // partials/header-search.blade.php) - still a system-wide search either
    // way, just leading with whichever section is relevant to that page.
    // Folder-level prioritization (which FILES/DOCUMENTS come first within
    // their own section) is handled server-side in SearchController.
    $sectionOrder = match ($context ?? null) {
        'archives' => ['archives', 'archivedFiles', 'folders', 'files', 'documents'],
        default    => ['folders', 'files', 'archives', 'archivedFiles', 'documents'],
    };
@endphp

@if($total === 0)
    <div class="live-search-empty">
        <i class="fas fa-search"></i> No matches for "{{ $query }}"
    </div>
@else
    <div class="live-search-list">
        @foreach($sectionOrder as $section)
            @include("Admin.searchLive.{$section}")
        @endforeach
    </div>

    <a href="{{ route('search', ['query' => $query]) }}" class="live-search-viewall">
        View all results for "{{ $query }}" <i class="fas fa-arrow-right"></i>
    </a>
@endif
