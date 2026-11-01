{{--
    A file's version label ("v3", opens the Versions dialog) and its Final
    mark (App\Services\FileVersions), wherever a file is listed. Needs
    partials/file-versions on the page.

        @include('Admin.partials.version-badge', ['file' => $file])

    Uses .chip from Admin/partials/theme.
--}}
@if($file->version_no > 1)
    <button type="button" class="chip neutral" style="white-space:nowrap; flex-shrink:0; border:0; cursor:pointer; font-family:'DM Mono', monospace;"
            title="Version {{ $file->version_no }}. Click to see every version."
            data-versions-file
            data-versions-name="{{ $file->filename }}"
            data-versions-url="{{ route('files.versions.index', $file->id) }}">
        v{{ $file->version_no }}<span class="sr-only"> of {{ $file->filename }}, see all versions</span>
    </button>
@endif
@if($file->isFinal())
    <span class="chip" style="white-space:nowrap; flex-shrink:0;" title="Version {{ $file->version_no }} is marked final">
        <i class="bi bi-check2" aria-hidden="true"></i> Final
    </span>
@endif
