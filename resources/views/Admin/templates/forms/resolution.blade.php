{{-- Form for a resolution (ResolutionDocument). $p prefixes ids so two
     templates' forms can share the page; $form comes from
     TemplateController::formFor(). --}}
@php
    $doc = \App\Support\Documents\ResolutionDocument::class;
    $fields = $form['fields'];
    $resolvedCount = count($fields['resolved']);
@endphp

<h3 class="tpl-section">Heading</h3>
<div class="prop-fields">
    <div>
        <label for="{{ $p }}kind">Kind</label>
        <input id="{{ $p }}kind" name="fields[kind]" class="prop-input" maxlength="120" value="{{ $fields['kind'] }}">
    </div>
    <div>
        <label for="{{ $p }}number">Resolution No.</label>
        <input id="{{ $p }}number" name="fields[number]" class="prop-input" maxlength="60" value="{{ $fields['number'] }}"
               aria-describedby="{{ $p }}numberHint"
               data-res-number data-typed="{{ $form['number_typed'] ? '1' : '0' }}"
               data-sequences="{{ json_encode((object) $form['sequences']) }}">
    </div>
    <div class="full field-hint" id="{{ $p }}numberHint" style="margin-top:-8px;">
        The adoption date (MM-DD-YY), then the school year and its running number: 04-06-26 (2526-034) is the 34th of 2025-2026.
        @if ($form['last_number'])
            Last one made here: <b>{{ $form['last_number'] }}</b>.
        @else
            None made here yet, so check the running number against the SG's own list.
        @endif
    </div>
    <div class="full">
        <label for="{{ $p }}title">Title * <span class="hint">(printed in capitals)</span></label>
        <textarea id="{{ $p }}title" name="fields[title]" class="prop-input cell-text" rows="2" maxlength="500" required
                  data-title-field placeholder="A Resolution Temporarily Suspending the Implementation of ...">{{ $fields['title'] }}</textarea>
    </div>
    <div class="full">
        <label for="{{ $p }}author">Authored by</label>
        <input id="{{ $p }}author" name="fields[author]" class="prop-input" maxlength="255" value="{{ $fields['author'] }}">
    </div>
</div>

<h3 class="tpl-section">WHEREAS</h3>
<p class="budget-note">Type each clause without the word WHEREAS. Put **two asterisks** around words to print them in bold. Pasting several paragraphs at once puts each in its own box.</p>
<ol class="prop-list clause-list" id="{{ $p }}whereasList" data-clause-list="whereas">
    @foreach ($fields['whereas'] as $clause)
        <li data-row>
            <div class="prop-row">
                <textarea name="fields[whereas][]" class="prop-input cell-text" rows="2" maxlength="5000" data-label="WHEREAS clause" aria-label="WHEREAS clause {{ $loop->iteration }}">{{ $clause }}</textarea>
                <button type="button" class="row-remove" data-remove-row data-label="Remove WHEREAS clause" aria-label="Remove WHEREAS clause {{ $loop->iteration }}"><i class="fas fa-times" aria-hidden="true"></i></button>
            </div>
        </li>
    @endforeach
</ol>
<template id="{{ $p }}whereasTpl">
    <li data-row>
        <div class="prop-row">
            <textarea name="fields[whereas][]" class="prop-input cell-text" rows="2" maxlength="5000" data-label="WHEREAS clause" aria-label="WHEREAS clause"></textarea>
            <button type="button" class="row-remove" data-remove-row data-label="Remove WHEREAS clause" aria-label="Remove WHEREAS clause"><i class="fas fa-times" aria-hidden="true"></i></button>
        </div>
    </li>
</template>
<button type="button" class="btn-flat ghost btn-sm" data-add-row="{{ $p }}whereas"><i class="fas fa-plus me-1" aria-hidden="true"></i> Add WHEREAS</button>

<h3 class="tpl-section">WHEREFORE</h3>
<div class="prop-fields">
    <div class="full">
        <label for="{{ $p }}moved">Upon the motion of <span class="hint">(name and position; leave it blank to leave out WHEREFORE)</span></label>
        <input id="{{ $p }}moved" name="fields[moved_by]" class="prop-input" maxlength="255" value="{{ $fields['moved_by'] }}"
               data-moved-by placeholder="Kevin G. Laguitao, SG Interim Auditor and BSA Senator">
    </div>
    <div class="full">
        <label for="{{ $p }}seconded">And</label>
        <input id="{{ $p }}seconded" name="fields[seconded]" class="prop-input" maxlength="255" value="{{ $fields['seconded'] }}" data-seconded>
    </div>
</div>

<h3 class="tpl-section">RESOLVED</h3>
<p class="budget-note">The first is printed as RESOLVED, the next as RESOLVED FURTHER, and the last of three or more as RESOLVED FINALLY.</p>
<ol class="prop-list clause-list" id="{{ $p }}resolvedList" data-clause-list="resolved">
    @foreach ($fields['resolved'] as $clause)
        <li data-row>
            <div class="clause-lead" data-lead>{{ $doc::resolvedLead($loop->index, $resolvedCount) }}</div>
            <div class="prop-row">
                <textarea name="fields[resolved][]" class="prop-input cell-text" rows="2" maxlength="5000" data-label="RESOLVED clause" aria-label="RESOLVED clause {{ $loop->iteration }}"
                          placeholder="{{ $loop->first ? 'as it is hereby resolved, that ...' : 'that ...' }}">{{ $clause }}</textarea>
                <button type="button" class="row-remove" data-remove-row data-label="Remove RESOLVED clause" aria-label="Remove RESOLVED clause {{ $loop->iteration }}"><i class="fas fa-times" aria-hidden="true"></i></button>
            </div>
        </li>
    @endforeach
</ol>
<template id="{{ $p }}resolvedTpl">
    <li data-row>
        <div class="clause-lead" data-lead></div>
        <div class="prop-row">
            <textarea name="fields[resolved][]" class="prop-input cell-text" rows="2" maxlength="5000" data-label="RESOLVED clause" aria-label="RESOLVED clause" placeholder="that ..."></textarea>
            <button type="button" class="row-remove" data-remove-row data-label="Remove RESOLVED clause" aria-label="Remove RESOLVED clause"><i class="fas fa-times" aria-hidden="true"></i></button>
        </div>
    </li>
</template>
<button type="button" class="btn-flat ghost btn-sm" data-add-row="{{ $p }}resolved"><i class="fas fa-plus me-1" aria-hidden="true"></i> Add RESOLVED</button>

<h3 class="tpl-section">Adopted</h3>
<div class="prop-fields">
    <div>
        <label for="{{ $p }}adopted">Date</label>
        <input type="date" id="{{ $p }}adopted" name="fields[adopted_on]" class="prop-input" value="{{ $fields['adopted_on'] }}" data-adopted-on>
    </div>
    <div>
        <label for="{{ $p }}vote">Vote <span class="hint">(“… on the 6th day of April 2026.”)</span></label>
        <input id="{{ $p }}vote" name="fields[vote]" class="prop-input" maxlength="120" value="{{ $fields['vote'] }}">
    </div>
    <div class="full">
        <label for="{{ $p }}venue">At</label>
        <input id="{{ $p }}venue" name="fields[venue]" class="prop-input" maxlength="255" value="{{ $fields['venue'] }}">
    </div>
</div>

<h3 class="tpl-section">Signatories</h3>
<p class="budget-note">A new resolution starts with the names from the last one, so check them when officers change. A second line in Position is printed under the first.</p>
<label class="tpl-check">
    <input type="checkbox" name="fields[signed]" value="1" @checked($fields['signed'])>
    Print “(SGD)” above the names, for the copy posted after it is signed
</label>
@foreach ($doc::SIGNATORY_GROUPS as $group => $label)
    @include('Admin.templates.forms.people', [
        'id'     => $p.'sig-'.$group,
        'name'   => 'fields[signatories]['.$group.']',
        'label'  => $label,
        'people' => $fields['signatories'][$group] ?? [],
    ])
@endforeach
