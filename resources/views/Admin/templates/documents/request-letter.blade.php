{{-- A request letter (RequestLetterDocument). $fields is already
     normalized; the body goes through inline() for **bold**. --}}
@php
    $doc = \App\Support\Documents\RequestLetterDocument::class;
    $groups = $doc::signatoryGroups($fields);
    // The sender signs under the closing, without a label.
    if (isset($groups['sender'])) {
        $groups['sender']['label'] = null;
    }
@endphp
<p class="letter-date">{{ $doc::longDate($fields['date']) }}</p>

<div class="letter-to">
    <div class="b">{{ $fields['recipient_name'] !== '' ? $fields['recipient_name'] : '____' }}</div>
    @foreach ($doc::splitLines($fields['recipient_details']) as $line)
        <div>{{ $line }}</div>
    @endforeach
</div>

@if ($fields['thru'])
    <table class="letter-thru">
        @foreach ($fields['thru'] as $person)
            <tr>
                <td class="k">{{ $loop->first ? 'Thru:' : '' }}</td>
                <td>
                    <div class="b">{{ $person['name'] }}</div>
                    @foreach ($doc::splitLines($person['position']) as $line)
                        <div>{{ $line }}</div>
                    @endforeach
                </td>
            </tr>
        @endforeach
    </table>
@endif

@if ($fields['subject'] !== '')
    <p><b>Subject:</b> {{ $fields['subject'] }}</p>
@endif

@if ($fields['salutation'] !== '')
    <p>{{ $fields['salutation'] }}</p>
@endif

@forelse ($doc::paragraphs($fields['body']) as $paragraph)
    <p class="letter-par">{!! $doc::inline($paragraph) !!}</p>
@empty
    <p class="letter-par doc-empty">The letter itself goes here.</p>
@endforelse

@if ($fields['closing'] !== '')
    <p class="letter-closing">{{ $fields['closing'] }}</p>
@endif

@include('Admin.templates.documents.signatories', [
    'groups' => $groups,
    'signed' => $fields['signed'],
    'align'  => 'left',
])
