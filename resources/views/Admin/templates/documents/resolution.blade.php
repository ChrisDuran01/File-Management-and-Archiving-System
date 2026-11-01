{{-- A resolution (ResolutionDocument), laid out after the SG's
     SG Executive Resolution No. 04-06-26 (2526-034). $fields is already
     normalized; clause text goes through inline() for **bold**. --}}
@php
    $doc = \App\Support\Documents\ResolutionDocument::class;
    $day = $doc::dayPhrase($fields['adopted_on']);
    $resolvedCount = count($fields['resolved']);
@endphp
<p class="res-number">{{ $fields['kind'] }} No. {{ $fields['number'] !== '' ? $fields['number'] : '____' }}</p>
<p class="res-series">Series of {{ $doc::series($fields['adopted_on']) }}</p>

<div class="res-title">{{ $fields['title'] !== '' ? mb_strtoupper($fields['title']) : '____' }}</div>
<div class="res-author">
    <div>Authored by:</div>
    <div class="b">{{ $fields['author'] !== '' ? mb_strtoupper($fields['author']) : '____' }}</div>
</div>

@foreach ($fields['whereas'] as $clause)
    <p class="clause"><b>WHEREAS,</b> {!! $doc::inline($clause) !!}</p>
@endforeach

@if ($fields['moved_by'] !== '')
    <p class="clause"><b>WHEREFORE</b>, upon the motion of {{ $fields['moved_by'] }}{{ $fields['seconded'] !== '' ? ' and '.$fields['seconded'] : '' }}, be it;</p>
@endif

@foreach ($fields['resolved'] as $i => $clause)
    <p class="clause"><b>{{ $doc::resolvedLead($i, $resolvedCount) }},</b> {!! $doc::inline($clause) !!}</p>
@endforeach

@if ($day !== '')
    <p class="clause"><b>ADOPTED</b> this {!! $day !!}{{ $fields['venue'] !== '' ? ' at '.rtrim($fields['venue'], '.') : '' }}.</p>
    @if ($fields['vote'] !== '')
        <p class="res-vote">{{ $fields['vote'] }} on the {!! $day !!}.</p>
    @endif
@endif

@include('Admin.templates.documents.signatories', [
    'groups' => $doc::signatoryGroups($fields),
    'signed' => $fields['signed'],
    'align'  => 'center',
    'strong' => ['certified'],
])
