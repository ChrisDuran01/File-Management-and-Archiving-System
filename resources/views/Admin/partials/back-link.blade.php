{{--
    In-page back link for pages you drill into (a folder, an edit form) and
    can't reach from the sidebar. Goes first in the page's content, above the
    .page-header; styled by .page-back in Admin/partials/theme.blade.php.

        @include('Admin.partials.back-link', ['backTo' => route('folders.index'), 'backLabel' => 'Folders'])
--}}
<a href="{{ $backTo }}" class="page-back">
    <i class="bi bi-arrow-left"></i> Back to {{ $backLabel }}
</a>
