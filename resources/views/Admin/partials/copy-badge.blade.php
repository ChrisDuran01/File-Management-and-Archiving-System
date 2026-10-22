{{--
    "Possible copy" label for a file that looks like a copy of an older one
    (App\Services\DuplicateDetector). Links to the original when the viewer
    may open it; otherwise it only says the original is somewhere they
    can't see. Hidden once an officer answered "not a copy", or once the
    original is in Trash.

        @include('Admin.partials.copy-badge', ['file' => $file])

    Uses .chip.neutral from Admin/partials/theme.
--}}
@if($file->duplicate_of_id && ! $file->duplicate_dismissed_at && $file->duplicateOf)
    @php
        $original = $file->duplicateOf;
        $canOpen = $original->isAccessibleBy(Auth::user());
        $alike = $file->duplicate_similarity >= 100 ? 'exact copy' : $file->duplicate_similarity . '% alike';
        $where = $canOpen
            ? '“' . $original->filename . '”' . ($original->folder ? ' in ' . $original->folder->name : '')
            : 'a file in a folder you can’t open';
    @endphp
    @if($canOpen)
        <a href="{{ route('files.preview', $original->id) }}" class="chip neutral" style="white-space:nowrap; flex-shrink:0;"
           title="Possible copy of {{ $where }} ({{ $alike }}). Open the original."
           onclick="event.stopPropagation()">
            <i class="bi bi-files"></i> Possible copy
        </a>
    @else
        <span class="chip neutral" style="white-space:nowrap; flex-shrink:0;" title="Possible copy of {{ $where }} ({{ $alike }})">
            <i class="bi bi-files"></i> Possible copy
        </span>
    @endif
    <span class="sr-only">of {{ $where }}, {{ $alike }}</span>
@endif
