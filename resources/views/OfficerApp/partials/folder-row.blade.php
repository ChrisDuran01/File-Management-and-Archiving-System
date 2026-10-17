{{--
    One folder in a list. $folder has ->accessible (see
    OfficerAppController::flagAccess()) and ->file_count. A folder this
    officer cannot open still lists, greyed and locked, as on the website.
--}}
@if ($folder->accessible)
    <a href="{{ route('app.folder', $folder->id) }}" class="row">
        <span class="chip folder">@include('OfficerApp.partials.icon', ['name' => 'folder'])</span>
        <span class="row-text">
            <span class="row-name">{{ $folder->name }}</span>
            <span class="row-meta">{{ $folder->file_count }} {{ Str::plural('file', $folder->file_count) }}{{ $folder->is_restricted ? ' · Restricted' : '' }}</span>
        </span>
        <span class="row-end">@include('OfficerApp.partials.icon', ['name' => 'chevron', 'size' => 18])</span>
    </a>
@else
    <div class="row is-locked">
        <span class="chip">@include('OfficerApp.partials.icon', ['name' => 'folder'])</span>
        <span class="row-text">
            <span class="row-name">{{ $folder->name }}</span>
            <span class="row-meta">Restricted · No access</span>
        </span>
        <span class="row-end">@include('OfficerApp.partials.icon', ['name' => 'lock', 'size' => 18])</span>
    </div>
@endif
