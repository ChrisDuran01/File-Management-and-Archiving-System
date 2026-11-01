@extends($layout ?? 'Admin.home')
@section('title', 'SY ' . str_replace('-', '–', $schoolYear))
@section('content')

@include('Admin.partials.theme')

{{-- Read by the topbar search (partials/header-search.blade.php) so the
     Archives section leads the dropdown here. --}}
<script>window.searchContext = 'archives';</script>

@include('Admin.partials.archive-styles')

@php
    $syName = 'SY ' . str_replace('-', '–', $schoolYear);
    $fileCount = $archives->sum(fn ($archive) => count($archive->filenames()));
    $how = match ($record?->archived_how) {
        'automatic'  => 'automatically',
        'by_hand'    => $record->archived_by ? 'by ' . $record->archived_by : 'by hand',
        'per_folder' => 'folder by folder, before October 2026',
        default      => null,
    };
    $archivedNote = $record?->archived_at
        ? ' · archived ' . $record->archived_at->format('M j, Y') . ($how ? ' ' . $how : '')
        : '';
@endphp

{{-- Skeleton placeholder, shown until the real content below is revealed --}}
<div class="skel-shell" aria-hidden="true">
    <div class="skel skel-text" style="width:150px; margin-bottom:1rem;"></div>
    <div class="page-header">
        <div class="skel skel-text" style="width:160px; height:22px;"></div>
        <div class="skel" style="width:190px; height:34px; border-radius:8px;"></div>
    </div>
    <div class="skel skel-text" style="width:60%; margin-bottom:22px;"></div>
    <div class="archive-grid">
        @for ($i = 0; $i < 4; $i++)
        <div class="archive-card">
            <div class="card-top">
                <div class="skel" style="width:42px; height:42px; border-radius:10px;"></div>
            </div>
            <div class="skel skel-text" style="width:75%; margin-bottom:8px;"></div>
            <div class="skel skel-text" style="width:55%; margin-bottom:14px;"></div>
        </div>
        @endfor
    </div>
</div>

<div class="container-fluid skel-real">

    @include('Admin.partials.back-link', ['backTo' => route('archives.index'), 'backLabel' => 'Archives'])

    <div class="page-header">
        <h1>{{ $syName }}</h1>

        {{-- The whole school year comes back together (Adviser/President) --}}
        @if ($isSuperAdmin)
        <form method="POST" action="{{ route('archives.restoreSchoolYear', $schoolYear) }}"
              data-confirm-title="Restore {{ $syName }}?"
              data-confirm="All {{ $archives->count() }} {{ Str::plural('folder', $archives->count()) }} and their files go back under Previous School Years ({{ $schoolYear }}), read-only. The school year stays there until someone archives it again from Archives."
              data-confirm-ok="Restore" data-confirm-tone="warning">
            @csrf
            <button type="submit" class="btn-action warn">
                <i class="fas fa-undo-alt" aria-hidden="true"></i> Restore school year
            </button>
        </form>
        @endif
    </div>

    <p class="archive-rule">
        {{ $archives->count() }} {{ Str::plural('folder', $archives->count()) }} · {{ $fileCount }} {{ Str::plural('file', $fileCount) }}{{ $archivedNote }}.
        Open a folder to see and open its files; nothing needs to be restored for that.
    </p>

    <div class="archive-grid" id="archiveGrid">
        @foreach ($archives as $archive)
        <div class="archive-card"
             data-folder="{{ strtolower($archive->folder_name) }}"
             data-zip="{{ strtolower($archive->zip_name) }}">

            <div class="card-top">
                <div class="folder-icon-wrap">
                    <i class="fas fa-folder" aria-hidden="true"></i>
                </div>

                <div class="card-menu-wrap">
                    <button class="card-menu-btn" onclick="toggleDropdown(this)" type="button"
                            aria-label="More actions for {{ $archive->folder_name }}" aria-expanded="false">
                        <i class="fas fa-ellipsis-v" aria-hidden="true"></i>
                    </button>

                    <div class="card-dropdown">
                        <a href="{{ route('archives.show', $archive->id) }}" class="card-dropdown-item">
                            <i class="fas fa-eye" style="font-size:12px;color:#058028;" aria-hidden="true"></i>
                            Open (view only)
                        </a>

                        <div class="card-dropdown-divider"></div>

                        <a href="{{ route('archives.download', $archive->id) }}" class="card-dropdown-item">
                            <i class="fas fa-download" style="font-size:12px;color:#058028;" aria-hidden="true"></i>
                            Download
                        </a>

                        @if ($archive->isManageableBy(auth()->user()))
                        <div class="card-dropdown-divider"></div>

                        <form method="POST" action="{{ route('archives.destroy', $archive->id) }}" style="margin:0;"
                              data-confirm-title="Delete this archive for good?"
                              data-confirm="“{{ $archive->folder_name }}” and every file in it are removed permanently. This cannot be undone."
                              data-confirm-ok="Delete">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="card-dropdown-item danger">
                                <i class="fas fa-trash" style="font-size:12px;" aria-hidden="true"></i>
                                Delete
                            </button>
                        </form>
                        @endif
                    </div>
                </div>
            </div>

            <a href="{{ route('archives.show', $archive->id) }}" class="card-folder-name" style="display:block; color:inherit; text-decoration:none;">{{ $archive->folder_name }}</a>

            <div class="card-zip-name">
                <i class="fas fa-file-archive" aria-hidden="true"></i>
                {{ count($archive->filenames()) }} {{ Str::plural('file', count($archive->filenames())) }}
            </div>

            <div class="card-footer-row">
                <span class="status-badge badge-archived">
                    <span class="dot"></span> Archived
                </span>
                <span class="card-date">
                    <i class="far fa-clock" style="font-size:10px;" aria-hidden="true"></i>
                    {{ $archive->archived_at ? $archive->archived_at->format('M j, Y') : 'N/A' }}
                </span>
            </div>

            {{-- Fixity indicator - set by the weekly archives:verify-fixity
                 check against the checksum recorded when this was archived. --}}
            @if ($archive->fixity_status === 'ok')
                <div class="fixity-row fixity-ok">
                    <svg width="13" height="13" viewBox="0 0 16 16" fill="none" aria-hidden="true"><circle cx="8" cy="8" r="6.5" stroke="currentColor" stroke-width="1.3"/><path d="M5.2 8.2l1.8 1.8 3.6-3.8" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <span class="fx-label">Verified {{ $archive->fixity_checked_at?->diffForHumans() }}</span>
                </div>
            @elseif ($archive->fixity_status === 'failed')
                <div class="fixity-row fixity-failed">
                    <svg width="13" height="13" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M8 1.6l6.9 12a1 1 0 0 1-.87 1.5H1.97a1 1 0 0 1-.87-1.5l6.9-12a1 1 0 0 1 1.74 0z" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round"/><line x1="8" y1="6.3" x2="8" y2="9.4" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/><circle cx="8" cy="11.8" r="0.75" fill="currentColor"/></svg>
                    <span class="fx-label">Integrity check failed{{ $archive->fixity_checked_at ? ' — checked ' . $archive->fixity_checked_at->diffForHumans() : '' }}</span>
                </div>
            @else
                <div class="fixity-row fixity-none">
                    <svg width="13" height="13" viewBox="0 0 16 16" fill="none" aria-hidden="true"><circle cx="8" cy="8" r="6.5" stroke="currentColor" stroke-width="1.3"/><line x1="8" y1="5.3" x2="8" y2="8.6" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/><circle cx="8" cy="10.8" r="0.7" fill="currentColor"/></svg>
                    <span class="fx-label">Not yet verified</span>
                </div>
            @endif
        </div>
        @endforeach
    </div>
</div>

<script>
    /* ── Card menus ── aria-expanded on each menu button follows its menu. */
    function closeCardMenu(menu) {
        menu.classList.remove('open');
        menu.previousElementSibling?.setAttribute('aria-expanded', 'false');
    }

    function toggleDropdown(btn) {
        const menu = btn.nextElementSibling;
        document.querySelectorAll('.card-dropdown.open').forEach(m => {
            if (m !== menu) closeCardMenu(m);
        });
        menu.classList.toggle('open');
        btn.setAttribute('aria-expanded', menu.classList.contains('open') ? 'true' : 'false');
    }

    document.addEventListener('click', function (e) {
        if (! e.target.closest('.card-menu-wrap')) {
            document.querySelectorAll('.card-dropdown.open').forEach(closeCardMenu);
        }
    });

    // Escape closes an open card menu and returns focus to its button.
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        document.querySelectorAll('.card-dropdown.open').forEach(m => {
            closeCardMenu(m);
            m.previousElementSibling?.focus();
        });
    });

    /* ── Search / filter ── called by the topbar search's input handler
       (partials/header-search.blade.php). */
    function filterCards(query) {
        const q = (query ?? '').toLowerCase().trim();
        document.querySelectorAll('#archiveGrid .archive-card').forEach(card => {
            const match = ! q || card.dataset.folder.includes(q) || card.dataset.zip.includes(q);
            card.style.display = match ? '' : 'none';
        });
    }
</script>

@endsection
