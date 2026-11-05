{{--
    "Looks like a Resolution · Move" (App\Services\FolderHint): the file's
    title names another kind of document than this folder's name says it
    holds. Opens the Move dialog (showFolder) with that kind's folder
    already chosen; "Keep it here" there stops the hint for good.

        @include('Admin.partials.folder-hint', ['file' => $file, 'hint' => $hints[$file->id] ?? null])

    Uses .chip.warning from Admin/partials/theme.
--}}
@if($hint)
    @php
        $kind = $hint['kind'];
        $kindWithArticle = (in_array($kind, ['Minutes', 'Constitution and By-Laws'], true) ? '' : (preg_match('/^[AEIOU]/i', $kind) ? 'an ' : 'a ')) . $kind;
    @endphp
    <button type="button" class="chip warning folder-hint" data-move-file
            data-move-url="{{ route('files.move', $file->id) }}"
            data-keep-url="{{ route('files.keepHere', $file->id) }}"
            data-move-name="{{ $file->filename }}"
            data-move-to="{{ $hint['folder']->id }}"
            data-move-kind="{{ $kindWithArticle }}"
            title="Its title says it is {{ $kindWithArticle }}. Click to move it to {{ $hint['folder']->name }}, or keep it here.">
        <i class="bi bi-arrow-right-square" aria-hidden="true"></i> Looks like {{ $kindWithArticle }} · Move
    </button>
@endif
