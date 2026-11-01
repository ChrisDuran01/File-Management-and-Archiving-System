{{-- Form for a plain text template: one box per placeholder in its
     content, in the order they first appear. --}}
@php $fields = $form['fields']; @endphp

<div class="prop-fields">
    @foreach ($template->placeholders() as $key)
        @php $label = \Illuminate\Support\Str::of($key)->replace('_', ' ')->title(); @endphp
        <div class="full">
            <label for="{{ $p.$key }}">{{ $label }}</label>
            @if (preg_match('/body|clause|message/i', $key))
                <textarea id="{{ $p.$key }}" name="fields[{{ $key }}]" class="prop-input" rows="6">{{ $fields[$key] ?? '' }}</textarea>
            @else
                <input id="{{ $p.$key }}" name="fields[{{ $key }}]" class="prop-input" type="{{ $key === 'date' ? 'date' : 'text' }}"
                       value="{{ $fields[$key] ?? '' }}" @if (in_array($key, ['title', 'subject'], true)) data-title-field @endif>
            @endif
        </div>
    @endforeach
</div>
