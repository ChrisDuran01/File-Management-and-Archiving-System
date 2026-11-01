@extends($layout ?? 'Admin.home')
@section('title', 'Archives')
@section('content')

@include('Admin.partials.theme')

{{-- Read by the topbar search (partials/header-search.blade.php) so the
     Archives section leads the dropdown here, without narrowing the
     search itself to only archives. --}}
<script>window.searchContext = 'archives';</script>

@include('Admin.partials.archive-styles')

@php
    $syName = fn ($sy) => 'SY ' . str_replace('-', '–', $sy);
    $howLabel = fn (array $year) => match ($year['how']) {
        'automatic'  => 'automatically',
        'by_hand'    => $year['by'] ? 'by ' . $year['by'] : 'by hand',
        'per_folder' => 'folder by folder, before October 2026',
        default      => null,
    };
@endphp

{{-- Skeleton placeholder, shown until the real content below is revealed --}}
<div class="skel-shell" aria-hidden="true">
    <div class="page-header">
        <div class="skel skel-text" style="width:120px; height:22px;"></div>
    </div>
    <div class="skel skel-text" style="width:60%; margin-bottom:22px;"></div>

    <div class="archive-grid">
        @for ($i = 0; $i < 4; $i++)
        <div class="archive-card">
            <div class="card-top">
                <div class="skel" style="width:42px; height:42px; border-radius:10px;"></div>
            </div>
            <div class="skel skel-text" style="width:55%; margin-bottom:8px;"></div>
            <div class="skel skel-text" style="width:70%; margin-bottom:14px;"></div>
            <div class="card-footer-row">
                <div class="skel" style="width:70px; height:18px; border-radius:20px;"></div>
                <div class="skel skel-text" style="width:80px;"></div>
            </div>
        </div>
        @endfor
    </div>
</div>

<div class="container-fluid skel-real">

    <div class="page-header">
        <h1>Archives</h1>
    </div>

    <p class="archive-rule">
        A school year's records are archived together, {{ $retentionYears }} {{ Str::plural('year', $retentionYears) }} after the school year ends.
        @if ($isSuperAdmin) You can archive a finished school year sooner below. @endif
        Archived files can still be found with the search bar and opened here without restoring anything.
    </p>

    {{-- Finished school years whose records are still under Previous School
         Years (Adviser/President only) --}}
    @if ($isSuperAdmin && $waiting->isNotEmpty())
    <section class="archive-section" aria-labelledby="waitingTitle">
        <h2 class="section-title" id="waitingTitle">Waiting to be archived</h2>
        <div class="waiting-list">
            @foreach ($waiting as $year)
            <div class="waiting-row">
                <div>
                    <div class="waiting-sy">{{ $syName($year['school_year']) }}</div>
                    <div class="waiting-meta">
                        {{ $year['folders'] }} {{ Str::plural('folder', $year['folders']) }} · {{ $year['files'] }} {{ Str::plural('file', $year['files']) }} ·
                        @if ($year['still_active'])
                            {{ $year['still_active'] }} {{ Str::plural('folder', $year['still_active']) }} still on the Folders page. Run End Term for this school year first.
                        @elseif ($year['held'])
                            Restored. It stays until someone archives it again.
                        @else
                            Archived automatically on {{ $year['due_on']->format('M j, Y') }}
                        @endif
                    </div>
                </div>
                @unless ($year['still_active'])
                <form method="POST" action="{{ route('archives.archiveSchoolYear', $year['school_year']) }}"
                      data-confirm-title="Archive {{ $syName($year['school_year']) }} now?"
                      data-confirm="Its {{ $year['folders'] }} {{ Str::plural('folder', $year['folders']) }} leave Previous School Years and are packed into the archive. They can still be found with the search bar, and the whole school year can be restored."
                      data-confirm-ok="Archive" data-confirm-tone="warning">
                    @csrf
                    <button type="submit" class="btn-action warn">
                        <i class="fas fa-box-archive" aria-hidden="true"></i> Archive now<span class="sr-only"> {{ $syName($year['school_year']) }}</span>
                    </button>
                </form>
                @endunless
            </div>
            @endforeach
        </div>
    </section>
    @endif

    <section aria-labelledby="archivedTitle">
        <h2 class="section-title" id="archivedTitle">
            Archived school years
            <span class="archive-count" id="archiveCount">{{ $years->count() }}</span>
        </h2>

        <div class="archive-grid" id="archiveGrid">
            @forelse ($years as $year)
            <a class="archive-card sy-card"
               href="{{ route('archives.schoolYear', $year['school_year']) }}"
               data-search="{{ strtolower($syName($year['school_year']) . ' ' . $year['school_year']) }}">

                <div class="card-top">
                    <div class="folder-icon-wrap">
                        <i class="fas fa-box-archive" aria-hidden="true"></i>
                    </div>
                </div>

                <div class="card-folder-name">{{ $syName($year['school_year']) }}</div>
                <div class="card-zip-name">
                    {{ $year['folders'] }} {{ Str::plural('folder', $year['folders']) }} · {{ $year['files'] }} {{ Str::plural('file', $year['files']) }}
                </div>

                <div class="card-footer-row">
                    <span class="status-badge badge-archived">
                        <span class="dot"></span> Archived
                    </span>
                    <span class="card-date">
                        {{ $year['archived_at'] ? $year['archived_at']->format('M j, Y') : '' }}
                    </span>
                </div>

                @if ($howLabel($year))
                    <div class="card-date" style="margin-top:6px;">Archived {{ $howLabel($year) }}</div>
                @endif

                {{-- Fixity: the weekly archives:verify-fixity check, over this
                     school year's folders --}}
                @if ($year['fixity_failed'])
                    <div class="fixity-row fixity-failed">
                        <svg width="13" height="13" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M8 1.6l6.9 12a1 1 0 0 1-.87 1.5H1.97a1 1 0 0 1-.87-1.5l6.9-12a1 1 0 0 1 1.74 0z" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round"/><line x1="8" y1="6.3" x2="8" y2="9.4" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/><circle cx="8" cy="11.8" r="0.75" fill="currentColor"/></svg>
                        <span class="fx-label">{{ $year['fixity_failed'] }} {{ Str::plural('folder', $year['fixity_failed']) }} failed the integrity check</span>
                    </div>
                @elseif ($year['fixity_unchecked'])
                    <div class="fixity-row fixity-none">
                        <svg width="13" height="13" viewBox="0 0 16 16" fill="none" aria-hidden="true"><circle cx="8" cy="8" r="6.5" stroke="currentColor" stroke-width="1.3"/><line x1="8" y1="5.3" x2="8" y2="8.6" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/><circle cx="8" cy="10.8" r="0.7" fill="currentColor"/></svg>
                        <span class="fx-label">Not yet verified</span>
                    </div>
                @else
                    <div class="fixity-row fixity-ok">
                        <svg width="13" height="13" viewBox="0 0 16 16" fill="none" aria-hidden="true"><circle cx="8" cy="8" r="6.5" stroke="currentColor" stroke-width="1.3"/><path d="M5.2 8.2l1.8 1.8 3.6-3.8" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <span class="fx-label">All verified</span>
                    </div>
                @endif
            </a>
            @empty
            <div class="empty-state">
                <i class="fas fa-archive" aria-hidden="true"></i>
                <p>No school year is archived yet.</p>
            </div>
            @endforelse
        </div>
    </section>
</div>

<script>
    /* ── Search / filter ──
       Called directly by the topbar search's input handler (see
       partials/header-search.blade.php), the only search box on this page. */
    function filterCards(query) {
        const q = (query ?? '').toLowerCase().trim();
        let visible = 0;

        document.querySelectorAll('.sy-card').forEach(card => {
            const match = ! q || card.dataset.search.includes(q);
            card.style.display = match ? '' : 'none';
            if (match) visible++;
        });

        document.getElementById('archiveCount').textContent = visible;
    }
</script>

@endsection
