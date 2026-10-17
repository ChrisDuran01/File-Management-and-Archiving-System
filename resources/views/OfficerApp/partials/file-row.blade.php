{{--
    One file in a list. $f is OfficerAppController::describe()'s array;
    $meta overrides the grey second line (default: size and upload date).
    The check on the right is shown by app.js when a copy of the file is
    saved on this phone - saving itself is done from the file's own screen.
--}}
<a class="row" href="{{ $f['page'] }}">
    <span class="chip {{ \App\Http\Controllers\OfficerAppController::toneOf($f['ext']) }}">{{ $f['ext'] ?: 'file' }}</span>
    <span class="row-text">
        <span class="row-name">{{ $f['name'] }}</span>
        <span class="row-meta">{{ $meta ?? ($f['size_label'] . ' · ' . $f['date']) }}</span>
        @if (! empty($f['snippet']))
            @php
                // Escape first, then wrap the matched term - never inject
                // raw extracted document text as HTML.
                $highlighted = preg_replace('/(' . preg_quote(e($query ?? ''), '/') . ')/i', '<mark>$1</mark>', e($f['snippet']));
            @endphp
            <span class="row-snippet">{!! $highlighted !!}</span>
        @endif
        @if (! empty($f['pending']))
            <span class="tag">Subject for approval</span>
        @endif
    </span>
    <span class="saved-mark" data-saved-mark="{{ $f['id'] }}" role="img" aria-label="Saved on this phone" hidden>
        @include('OfficerApp.partials.icon', ['name' => 'check', 'size' => 18, 'stroke' => 2])
    </span>
</a>
