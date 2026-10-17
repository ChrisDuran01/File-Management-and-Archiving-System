{{--
    "Subject for approval" label for a file that isn't official yet
    (files.is_pending_approval). Included wherever a file is listed, so an
    unapproved draft is never mistaken for an official document.

        @include('Admin.partials.approval-badge', ['file' => $file])

    Uses .chip.warning from Admin/partials/theme.
--}}
@if($file->is_pending_approval)
    <span class="chip warning" style="white-space:nowrap; flex-shrink:0;"
          title="Not yet official: this file is still subject for approval">
        <i class="bi bi-hourglass-split"></i> Subject for approval
    </span>
@endif
