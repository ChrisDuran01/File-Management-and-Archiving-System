{{-- Form for a request letter (RequestLetterDocument). $p prefixes ids
     so two templates' forms can share the page; $form comes from
     TemplateController::formFor(). --}}
@php
    $doc = \App\Support\Documents\RequestLetterDocument::class;
    $fields = $form['fields'];
@endphp

<h3 class="tpl-section">To</h3>
<div class="prop-fields">
    <div>
        <label for="{{ $p }}date">Date</label>
        <input type="date" id="{{ $p }}date" name="fields[date]" class="prop-input" value="{{ $fields['date'] }}">
    </div>
    <div></div>
    <div class="full">
        <label for="{{ $p }}recipient">Name *</label>
        <input id="{{ $p }}recipient" name="fields[recipient_name]" class="prop-input" maxlength="255" required
               value="{{ $fields['recipient_name'] }}" placeholder="ENGR. ALFONSO R. SIMON, PhD">
    </div>
    <div class="full">
        <label for="{{ $p }}details">Position, office, address <span class="hint">(one per line)</span></label>
        <textarea id="{{ $p }}details" name="fields[recipient_details]" class="prop-input cell-text" rows="3" maxlength="1000"
                  placeholder="OIC-University President&#10;Quirino State University&#10;Diffun, Quirino">{{ $fields['recipient_details'] }}</textarea>
    </div>
</div>

@include('Admin.templates.forms.people', [
    'id'     => $p.'thru',
    'name'   => 'fields[thru]',
    'label'  => 'Thru',
    'people' => $fields['thru'],
])

<h3 class="tpl-section">Letter</h3>
<div class="prop-fields">
    <div class="full">
        <label for="{{ $p }}subject">Subject <span class="hint">(also names the file)</span></label>
        <input id="{{ $p }}subject" name="fields[subject]" class="prop-input" maxlength="255" value="{{ $fields['subject'] }}" data-title-field>
    </div>
    <div class="full">
        <label for="{{ $p }}salutation">Salutation</label>
        <input id="{{ $p }}salutation" name="fields[salutation]" class="prop-input" maxlength="120" value="{{ $fields['salutation'] }}">
    </div>
    <div class="full">
        <label for="{{ $p }}body">Body * <span class="hint">(each new line starts a paragraph; **two asterisks** for bold)</span></label>
        <textarea id="{{ $p }}body" name="fields[body]" class="prop-input" rows="10" maxlength="20000" required>{{ $fields['body'] }}</textarea>
    </div>
    <div class="full">
        <label for="{{ $p }}closing">Closing</label>
        <input id="{{ $p }}closing" name="fields[closing]" class="prop-input" maxlength="120" value="{{ $fields['closing'] }}">
    </div>
</div>

<h3 class="tpl-section">Signatories</h3>
<p class="budget-note">"{{ $doc::SIGNATORY_GROUPS['sender'] }}" is printed under the closing without a label. A new letter starts with the names from the last one.</p>
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
