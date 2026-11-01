@extends($layout ?? 'Admin.home')
@section('title', $proposal->exists ? 'Edit Activity Proposal' : 'New Activity Proposal')
@section('content')

@include('Admin.partials.theme')

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

@include('Admin.proposals.partials.styles')

@php
    // Numbers as an <input type="number"> wants them: 2500.00 -> "2500", 12.50 -> "12.5".
    $plain = fn ($v) => ($v === null || $v === '') ? '' : rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
    $peso = fn ($amount) => \App\Models\ActivityProposal::peso((float) $amount);

    // After a failed save, the officer's own input comes back; otherwise
    // the saved proposal, or a few empty rows to start a new one.
    $objectives = old('objectives', $proposal->objectives ?: ['', '', '']);
    $schedule = old('schedule', $proposal->schedule ?: array_fill(0, 3, ['time' => '', 'activity' => '', 'personnel' => '']));
    // Read from a Word file (ActivityProposalController::importRead): its
    // budget rows, and a note on each row whose numbers don't add up.
    $import = $import ?? null;
    $items = old('items', ! empty($import['items'])
        ? $import['items']
        : ($proposal->exists && $proposal->items->isNotEmpty()
            ? $proposal->items->map(fn ($item) => [
                'category'       => $item->category,
                'particular'     => $item->particular,
                'quantity'       => $plain($item->quantity),
                'unit'           => $item->unit,
                'unit_price'     => $plain($item->unit_price),
                'price_note'     => $item->price_note,
                'price_note_for' => $item->price_note ? $plain($item->unit_price) : '',
            ])->all()
            : array_fill(0, 3, ['category' => '', 'particular' => '', 'quantity' => '', 'unit' => '', 'unit_price' => ''])));
    $importChecks = old('items') ? [] : ($import['checks'] ?? []);
    $signatories = old('signatories', $proposal->signatories ?: []);

    $rowTotal = fn ($row) => round((float) ($row['quantity'] ?? 0) * (float) ($row['unit_price'] ?? 0), 2);
    $grandTotal = collect($items)->sum($rowTotal);
@endphp

{{-- Skeleton placeholder, shown until the real content below is revealed --}}
<div class="skel-shell" aria-hidden="true">
    <div class="skel skel-text" style="width:160px; margin-bottom:1rem;"></div>
    <div class="page-header"><div class="skel skel-text" style="width:260px; height:22px;"></div></div>
    <div class="card-panel prop-form">
        <div class="skel skel-text" style="width:140px; margin-bottom:14px;"></div>
        <div class="skel" style="width:100%; height:38px; border-radius:8px;"></div>
    </div>
    <div class="card-panel prop-form">
        <div class="skel skel-text" style="width:140px; margin-bottom:14px;"></div>
        <div class="skel" style="width:100%; height:100px; border-radius:8px;"></div>
    </div>
</div>

<div class="skel-real">

@if ($proposal->exists)
    @include('Admin.partials.back-link', ['backTo' => route('proposals.show', $proposal), 'backLabel' => 'the proposal'])
@else
    @include('Admin.partials.back-link', ['backTo' => route('proposals.index'), 'backLabel' => 'Activity Proposals'])
@endif

<div class="page-header">
    <div>
        <h1>{{ $proposal->exists ? 'Edit Activity Proposal' : 'New Activity Proposal' }}</h1>
        <p class="sub">Only the title is required. Save now and finish the rest later.</p>
    </div>
</div>

@if ($import)
    <div class="card-panel prop-form import-note" role="status">
        <p style="margin:0;"><i class="fas fa-file-word me-1" aria-hidden="true"></i>
            <b>Filled in from “{{ $import['filename'] }}”.</b> Check every part below; nothing is saved until you click Save proposal.</p>
        <p class="item-meta" style="margin:4px 0 0;">
            Found {{ $import['found']['objectives'] }} {{ Str::plural('objective', $import['found']['objectives']) }},
            {{ $import['found']['schedule'] }} schedule {{ Str::plural('row', $import['found']['schedule']) }},
            {{ $import['found']['budget'] }} budget {{ Str::plural('row', $import['found']['budget']) }} and
            {{ $import['found']['signatories'] }} {{ Str::plural('signatory', $import['found']['signatories']) }}.
        </p>
        @if ($import['warnings'] || $importChecks)
            <ul class="import-warnings">
                @foreach ($import['warnings'] as $warning)
                    <li>{{ $warning }}</li>
                @endforeach
                @if ($importChecks)
                    <li>{{ count($importChecks) }} budget {{ Str::plural('row', count($importChecks)) }} {{ count($importChecks) === 1 ? 'needs' : 'need' }} a look; {{ count($importChecks) === 1 ? 'it is' : 'they are' }} marked in V. Budgetary Requirements.</li>
                @endif
            </ul>
        @endif
    </div>
@endif

<form method="POST" id="proposalForm" class="prop-form"
      action="{{ $proposal->exists ? route('proposals.update', $proposal) : route('proposals.store') }}"
      data-budget-check="{{ route('proposals.budget-check') }}" data-proposal="{{ $proposal->id }}">
    @csrf
    @if ($proposal->exists) @method('PUT') @endif
    @if ($import)
        <input type="hidden" name="imported_from" value="{{ $import['filename'] }}">
    @endif

    <section class="card-panel" aria-labelledby="secActivity">
        <h2 id="secActivity">Activity</h2>
        <div class="prop-fields">
            <div class="full">
                <label for="pTitle">Title *</label>
                <input id="pTitle" name="title" class="prop-input @error('title') is-invalid @enderror" maxlength="255" required
                       placeholder="e.g. Freshmen Ball 2026" value="{{ old('title', $proposal->title) }}">
                @error('title') <div class="field-error">{{ $message }}</div> @enderror
            </div>
            <div class="full">
                <label for="pTheme">Theme</label>
                <input id="pTheme" name="theme" class="prop-input" maxlength="255" value="{{ old('theme', $proposal->theme) }}">
            </div>
            <div>
                <label for="pStart">Date</label>
                <input type="date" id="pStart" name="starts_on" class="prop-input @error('starts_on') is-invalid @enderror"
                       value="{{ old('starts_on', optional($proposal->starts_on)->format('Y-m-d')) }}">
                @error('starts_on') <div class="field-error">{{ $message }}</div> @enderror
            </div>
            <div>
                <label for="pEnd">Last day <span class="hint">(only if more than one day)</span></label>
                <input type="date" id="pEnd" name="ends_on" class="prop-input @error('ends_on') is-invalid @enderror"
                       value="{{ old('ends_on', optional($proposal->ends_on)->format('Y-m-d')) }}">
                @error('ends_on') <div class="field-error">{{ $message }}</div> @enderror
            </div>
            <div>
                <label for="pProponent">Proponent</label>
                <input id="pProponent" name="proponent" class="prop-input" maxlength="255" value="{{ old('proponent', $proposal->proponent) }}">
            </div>
            <div>
                <span class="field-label">Budget</span>
                <p class="budget-readout"><span data-budget-total>{{ $peso($grandTotal) }}</span> <span class="hint">· the Grand Total of part V</span></p>
            </div>
        </div>
    </section>

    <section class="card-panel">
        <h2><label for="pRationale">I. Rationale</label></h2>
        <textarea id="pRationale" name="rationale" class="prop-input" rows="8" maxlength="20000"
                  placeholder="Why the activity is needed. Start each paragraph on a new line.">{{ old('rationale', $proposal->rationale) }}</textarea>
    </section>

    <section class="card-panel" aria-labelledby="secObjectives">
        <h2 id="secObjectives">II. Objectives</h2>
        <div class="prop-fields" style="margin-bottom:12px;">
            <div class="full">
                <label for="pObjIntro">Before the list <span class="hint">(optional, e.g. “The celebration specifically aims to:”)</span></label>
                <textarea id="pObjIntro" name="objectives_intro" class="prop-input" rows="4" maxlength="10000">{{ old('objectives_intro', $proposal->objectives_intro) }}</textarea>
            </div>
        </div>
        <ol class="prop-list" id="objectiveList">
            @foreach ($objectives as $objective)
                <li data-row>
                    <div class="prop-row">
                        <input name="objectives[]" class="prop-input" maxlength="500" value="{{ $objective }}"
                               data-label="Objective" aria-label="Objective {{ $loop->iteration }}">
                        <button type="button" class="row-remove" data-remove-row data-label="Remove objective" aria-label="Remove objective {{ $loop->iteration }}"><i class="fas fa-times" aria-hidden="true"></i></button>
                    </div>
                </li>
            @endforeach
        </ol>
        <template id="objectiveTpl">
            <li data-row>
                <div class="prop-row">
                    <input name="objectives[]" class="prop-input" maxlength="500" data-label="Objective" aria-label="Objective">
                    <button type="button" class="row-remove" data-remove-row data-label="Remove objective" aria-label="Remove objective"><i class="fas fa-times" aria-hidden="true"></i></button>
                </div>
            </li>
        </template>
        <button type="button" class="btn-flat ghost btn-sm" data-add-row="objective"><i class="fas fa-plus me-1" aria-hidden="true"></i> Add objective</button>
    </section>

    <section class="card-panel">
        <h2><label for="pMethods">III. Methods &amp; Approaches</label></h2>
        <textarea id="pMethods" name="methods" class="prop-input" rows="6" maxlength="20000"
                  placeholder="How the activity will be done. Start each paragraph on a new line.">{{ old('methods', $proposal->methods) }}</textarea>
    </section>

    <section class="card-panel" aria-labelledby="secSchedule">
        <h2 id="secSchedule">IV. Schedule of Activities</h2>
        <p class="budget-note">A cell can have more than one line, e.g. a name and, on the next line, the position.</p>
        <div class="table-scroll">
            <table class="grid-table" style="min-width:680px;">
                <thead>
                    <tr>
                        <th scope="col" style="width:150px;">Time</th>
                        <th scope="col">Activity</th>
                        <th scope="col" style="width:240px;">Venue/Personnel</th>
                        <th scope="col" style="width:48px;"><span class="sr-only">Remove</span></th>
                    </tr>
                </thead>
                <tbody id="scheduleList">
                    @foreach ($schedule as $key => $row)
                        <tr data-row>
                            <td><input name="schedule[{{ $key }}][time]" class="prop-input" maxlength="60" value="{{ $row['time'] ?? '' }}" placeholder="1:00-1:30 pm" data-label="Time, row" aria-label="Time, row {{ $loop->iteration }}"></td>
                            <td><textarea name="schedule[{{ $key }}][activity]" class="prop-input cell-text" rows="1" maxlength="2000" data-label="Activity, row" aria-label="Activity, row {{ $loop->iteration }}">{{ $row['activity'] ?? '' }}</textarea></td>
                            <td><textarea name="schedule[{{ $key }}][personnel]" class="prop-input cell-text" rows="1" maxlength="500" data-label="Venue or personnel, row" aria-label="Venue or personnel, row {{ $loop->iteration }}">{{ $row['personnel'] ?? $row['in_charge'] ?? '' }}</textarea></td>
                            <td><button type="button" class="row-remove" data-remove-row data-label="Remove schedule row" aria-label="Remove schedule row {{ $loop->iteration }}"><i class="fas fa-times" aria-hidden="true"></i></button></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <template id="scheduleTpl">
            <tr data-row>
                <td><input name="schedule[__KEY__][time]" class="prop-input" maxlength="60" placeholder="1:00-1:30 pm" data-label="Time, row" aria-label="Time"></td>
                <td><textarea name="schedule[__KEY__][activity]" class="prop-input cell-text" rows="1" maxlength="2000" data-label="Activity, row" aria-label="Activity"></textarea></td>
                <td><textarea name="schedule[__KEY__][personnel]" class="prop-input cell-text" rows="1" maxlength="500" data-label="Venue or personnel, row" aria-label="Venue or personnel"></textarea></td>
                <td><button type="button" class="row-remove" data-remove-row data-label="Remove schedule row" aria-label="Remove schedule row"><i class="fas fa-times" aria-hidden="true"></i></button></td>
            </tr>
        </template>
        <button type="button" class="btn-flat ghost btn-sm" data-add-row="schedule"><i class="fas fa-plus me-1" aria-hidden="true"></i> Add row</button>

        <div class="prop-fields" style="margin-top:14px;">
            <div>
                <label for="pEmcee">Master of Ceremony <span class="hint">(the last row of the table)</span></label>
                <input id="pEmcee" name="master_of_ceremony" class="prop-input" maxlength="255" value="{{ old('master_of_ceremony', $proposal->master_of_ceremony) }}">
            </div>
        </div>
    </section>

    <section class="card-panel" aria-labelledby="secBudget">
        <h2 id="secBudget">V. Budgetary Requirements</h2>
        <p class="budget-note">Total Price is Quantity × Unit Price, computed for you, and so is the Grand Total. In the PDF, rows next to each other with the same Category share one Category cell.</p>
        <div class="table-scroll">
            <table class="grid-table" style="min-width:860px;">
                <thead>
                    <tr>
                        <th scope="col" style="width:160px;">Category</th>
                        <th scope="col">Particular</th>
                        <th scope="col" style="width:95px;">Quantity</th>
                        <th scope="col" style="width:95px;">Unit</th>
                        <th scope="col" style="width:115px;">Unit Price</th>
                        <th scope="col" class="num" style="width:130px;">Total Price</th>
                        <th scope="col" style="width:48px;"><span class="sr-only">Remove</span></th>
                    </tr>
                </thead>
                <tbody id="budgetList">
                    @foreach ($items as $key => $row)
                        <tr data-row>
                            <td><input name="items[{{ $key }}][category]" class="prop-input" maxlength="100" list="propCategories" value="{{ $row['category'] ?? '' }}" data-label="Category, row" aria-label="Category, row {{ $loop->iteration }}"></td>
                            <td><input name="items[{{ $key }}][particular]" class="prop-input @error('items.'.$key.'.particular') is-invalid @enderror" maxlength="255" value="{{ $row['particular'] ?? '' }}" data-label="Particular, row" aria-label="Particular, row {{ $loop->iteration }}">
                                @if (isset($importChecks[$key]))
                                    <div class="import-check"><i class="fas fa-triangle-exclamation me-1" aria-hidden="true"></i>{{ $importChecks[$key] }}</div>
                                @endif
                            </td>
                            <td><input type="number" step="any" min="0" inputmode="decimal" name="items[{{ $key }}][quantity]" class="prop-input @error('items.'.$key.'.quantity') is-invalid @enderror" value="{{ $row['quantity'] ?? '' }}" data-qty data-label="Quantity, row" aria-label="Quantity, row {{ $loop->iteration }}"></td>
                            <td><input name="items[{{ $key }}][unit]" class="prop-input" maxlength="30" list="propUnits" value="{{ $row['unit'] ?? '' }}" data-label="Unit, row" aria-label="Unit, row {{ $loop->iteration }}"></td>
                            <td><div class="price-cell" data-price-cell><i class="fas fa-triangle-exclamation" aria-hidden="true"></i><input type="number" step="0.01" min="0" inputmode="decimal" name="items[{{ $key }}][unit_price]" class="prop-input @error('items.'.$key.'.unit_price') is-invalid @enderror" value="{{ $row['unit_price'] ?? '' }}" data-price data-label="Unit Price, row" aria-label="Unit Price, row {{ $loop->iteration }}"></div>
                                @include('Admin.proposals.partials.price-note-fields', ['key' => $key, 'row' => $row])
                            </td>
                            <td class="num" data-row-total>{{ $peso($rowTotal($row)) }}</td>
                            <td><button type="button" class="row-remove" data-remove-row data-label="Remove budget row" aria-label="Remove budget row {{ $loop->iteration }}"><i class="fas fa-times" aria-hidden="true"></i></button></td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="5" style="text-align:right;">Grand Total</td>
                        <td class="num" id="budgetTotal" data-budget-total aria-live="polite">{{ $peso($grandTotal) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <template id="budgetTpl">
            <tr data-row>
                <td><input name="items[__KEY__][category]" class="prop-input" maxlength="100" list="propCategories" data-label="Category, row" aria-label="Category"></td>
                <td><input name="items[__KEY__][particular]" class="prop-input" maxlength="255" data-label="Particular, row" aria-label="Particular"></td>
                <td><input type="number" step="any" min="0" inputmode="decimal" name="items[__KEY__][quantity]" class="prop-input" data-qty data-label="Quantity, row" aria-label="Quantity"></td>
                <td><input name="items[__KEY__][unit]" class="prop-input" maxlength="30" list="propUnits" data-label="Unit, row" aria-label="Unit"></td>
                <td><div class="price-cell" data-price-cell><i class="fas fa-triangle-exclamation" aria-hidden="true"></i><input type="number" step="0.01" min="0" inputmode="decimal" name="items[__KEY__][unit_price]" class="prop-input" data-price data-label="Unit Price, row" aria-label="Unit Price"></div>
                    @include('Admin.proposals.partials.price-note-fields', ['key' => '__KEY__', 'row' => []])
                </td>
                <td class="num" data-row-total>₱0.00</td>
                <td><button type="button" class="row-remove" data-remove-row data-label="Remove budget row" aria-label="Remove budget row"><i class="fas fa-times" aria-hidden="true"></i></button></td>
            </tr>
        </template>
        <button type="button" class="btn-flat ghost btn-sm" data-add-row="budget"><i class="fas fa-plus me-1" aria-hidden="true"></i> Add row</button>

        {{-- Budget data mining, filled in by the script below: the price
             check (an unusual price is only marked in its row; what it is
             and the officer's reason are here), and items often budgeted
             with these ones. --}}
        <div class="budget-help">
            <div class="help-box" data-price-box hidden>
                <h3>Price check</h3>
                <p class="item-meta" data-price-summary aria-live="polite"></p>
                <div data-price-list></div>
                <p class="item-meta help-foot" data-price-foot hidden>Reasons show on the proposal page and in Reports, not in the PDF.</p>
            </div>
            <div class="help-box" data-suggest hidden>
                <h3>Often budgeted together</h3>
                <p class="item-meta" data-suggest-intro>Items that approved proposals with these items also had.</p>
                <div data-suggest-list></div>
                <p class="item-meta" data-suggest-wait hidden></p>
            </div>
        </div>

        {{-- Suggestions from earlier proposals; any other name can be typed. --}}
        <datalist id="propCategories">
            @foreach ($categories as $category)
                <option value="{{ $category }}"></option>
            @endforeach
        </datalist>
        <datalist id="propUnits">
            @foreach ($units as $unit)
                <option value="{{ $unit }}"></option>
            @endforeach
        </datalist>
    </section>

    <section class="card-panel" aria-labelledby="secSign">
        <h2 id="secSign">Signatories</h2>
        <p class="budget-note">Printed at the end of the PDF, two to a line. A new proposal starts with the names from the last one, so check them when officers change.</p>

        @foreach (\App\Models\ActivityProposal::SIGNATORY_GROUPS as $group => $groupLabel)
            @php $people = $signatories[$group] ?? []; @endphp
            <div class="sig-group">
                <h3 id="sig-{{ $group }}-title">{{ $groupLabel }}</h3>
                <div class="table-scroll">
                    <table class="grid-table" style="min-width:560px; margin-bottom:6px;" aria-labelledby="sig-{{ $group }}-title">
                        <thead>
                            <tr>
                                <th scope="col">Name</th>
                                <th scope="col">Position</th>
                                <th scope="col" style="width:48px;"><span class="sr-only">Remove</span></th>
                            </tr>
                        </thead>
                        <tbody id="sig-{{ $group }}List">
                            @foreach ($people as $key => $person)
                                <tr data-row>
                                    <td><input name="signatories[{{ $group }}][{{ $key }}][name]" class="prop-input" maxlength="120" value="{{ $person['name'] ?? '' }}" data-label="{{ $groupLabel }}, name" aria-label="{{ $groupLabel }}, name {{ $loop->iteration }}"></td>
                                    <td><input name="signatories[{{ $group }}][{{ $key }}][position]" class="prop-input" maxlength="150" value="{{ $person['position'] ?? '' }}" data-label="{{ $groupLabel }}, position" aria-label="{{ $groupLabel }}, position {{ $loop->iteration }}"></td>
                                    <td><button type="button" class="row-remove" data-remove-row data-label="Remove {{ $groupLabel }} signatory" aria-label="Remove {{ $groupLabel }} signatory {{ $loop->iteration }}"><i class="fas fa-times" aria-hidden="true"></i></button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <template id="sig-{{ $group }}Tpl">
                    <tr data-row>
                        <td><input name="signatories[{{ $group }}][__KEY__][name]" class="prop-input" maxlength="120" data-label="{{ $groupLabel }}, name" aria-label="{{ $groupLabel }}, name"></td>
                        <td><input name="signatories[{{ $group }}][__KEY__][position]" class="prop-input" maxlength="150" data-label="{{ $groupLabel }}, position" aria-label="{{ $groupLabel }}, position"></td>
                        <td><button type="button" class="row-remove" data-remove-row data-label="Remove {{ $groupLabel }} signatory" aria-label="Remove {{ $groupLabel }} signatory"><i class="fas fa-times" aria-hidden="true"></i></button></td>
                    </tr>
                </template>
                <button type="button" class="btn-flat ghost btn-sm" data-add-row="sig-{{ $group }}"><i class="fas fa-plus me-1" aria-hidden="true"></i> Add to {{ $groupLabel }}</button>
            </div>
        @endforeach
    </section>

    <div class="form-actions">
        <a href="{{ $proposal->exists ? route('proposals.show', $proposal) : route('proposals.index') }}" class="btn-flat ghost">Cancel</a>
        <button type="submit" class="btn-flat">{{ $proposal->exists ? 'Save changes' : 'Save proposal' }}</button>
    </div>
</form>

</div>

<script>
(function () {
    var form = document.getElementById('proposalForm');
    if (!form) return;

    // New rows get keys no existing row uses; the server only cares that
    // each row's fields share one.
    var nextKey = Date.now();

    function num(value) {
        var n = parseFloat(value);
        return isNaN(n) ? 0 : n;
    }

    function peso(n) {
        return '₱' + n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    // Screen readers hear "Particular, row 3", so the numbers follow the
    // rows as they are added and removed.
    function renumber(list) {
        Array.prototype.forEach.call(list.children, function (row, i) {
            row.querySelectorAll('[data-label]').forEach(function (el) {
                el.setAttribute('aria-label', el.getAttribute('data-label') + ' ' + (i + 1));
            });
        });
    }

    function recompute() {
        var total = 0;
        document.querySelectorAll('#budgetList [data-row]').forEach(function (row) {
            var rowTotal = Math.round(num(row.querySelector('[data-qty]').value) * num(row.querySelector('[data-price]').value) * 100) / 100;
            total += rowTotal;
            row.querySelector('[data-row-total]').textContent = peso(rowTotal);
        });
        var text = peso(Math.round(total * 100) / 100);
        document.querySelectorAll('[data-budget-total]').forEach(function (el) { el.textContent = text; });
    }

    // Multi-line schedule cells grow with what is typed in them.
    function fit(area) {
        area.style.height = 'auto';
        area.style.height = area.scrollHeight + 2 + 'px';
    }
    form.querySelectorAll('textarea.cell-text').forEach(fit);

    form.addEventListener('click', function (e) {
        var add = e.target.closest('[data-add-row]');
        if (add) {
            var kind = add.getAttribute('data-add-row');
            var list = document.getElementById(kind + 'List');
            var html = document.getElementById(kind + 'Tpl').innerHTML.replace(/__KEY__/g, String(nextKey++));
            list.insertAdjacentHTML('beforeend', html);
            renumber(list);
            var first = list.lastElementChild.querySelector('input, textarea');
            if (first) first.focus();
            return;
        }

        var remove = e.target.closest('[data-remove-row]');
        if (remove) {
            var row = remove.closest('[data-row]');
            var rows = row.parentNode;
            var kindOf = rows.id.replace(/List$/, '');
            row.remove();
            renumber(rows);
            recompute();
            if (kindOf === 'budget') scheduleCheck();
            var addButton = form.querySelector('[data-add-row="' + kindOf + '"]');
            if (addButton) addButton.focus();
            return;
        }

        // "Price is correct" in the Price check box: opens the reason for that price.
        var ok = e.target.closest('[data-price-ok]');
        if (ok) {
            var okKey = ok.getAttribute('data-price-ok');
            var okRow = rowByKey(okKey);
            if (okRow) {
                opened[okKey] = true;
                okRow.querySelector('[data-price-note-for]').value = okRow.querySelector('[data-price]').value;
                renderPrices();
                var reasonInput = form.querySelector('[data-reason-input="' + okKey + '"]');
                if (reasonInput) reasonInput.focus();
            }
            return;
        }

        var suggestion = e.target.closest('[data-suggest-add]');
        if (suggestion) addSuggested(suggestions[+suggestion.getAttribute('data-suggest-add')]);
    });

    form.addEventListener('input', function (e) {
        if (e.target.matches('[data-qty], [data-price]')) recompute();
        if (e.target.matches('textarea.cell-text')) fit(e.target);
        if (e.target.matches('[data-price]')) forgetReason(e.target.closest('[data-row]'));
        if (e.target.matches('[data-reason-input]')) {
            // Kept in the row's hidden fields, for the price as it is now.
            var reasonRow = rowByKey(e.target.getAttribute('data-reason-input'));
            if (reasonRow) {
                reasonRow.querySelector('[data-price-note]').value = e.target.value;
                reasonRow.querySelector('[data-price-note-for]').value = reasonRow.querySelector('[data-price]').value;
            }
        }
        if (e.target.closest('#budgetList') && e.target.matches('[name$="[particular]"], [name$="[unit]"], [data-price]')) scheduleCheck();
    });
    var startInput = document.getElementById('pStart');
    startInput.addEventListener('change', scheduleCheck);

    // ---- Budget data mining ----------------------------------------------
    // The server compares each Unit Price with approved proposals (an
    // unusual one is flagged) and names the items often budgeted with the
    // ones already here. Asked half a second after typing stops.
    var checkUrl = form.getAttribute('data-budget-check');
    var token = form.querySelector('input[name="_token"]').value;
    var timer = null;
    var asked = 0;
    var suggestions = [];
    var lastPrices = {};   // the last check, by row key
    var opened = {};       // rows whose "Price is correct" was clicked, reason not typed yet

    function budgetRows() {
        return Array.prototype.slice.call(document.querySelectorAll('#budgetList [data-row]'));
    }

    function rowKey(row) {
        var particular = row.querySelector('[name$="[particular]"]');
        var match = particular && particular.name.match(/^items\[([^\]]+)\]/);
        return match ? match[1] : null;
    }

    function rowByKey(key) {
        return budgetRows().filter(function (row) { return rowKey(row) === key; })[0] || null;
    }

    function value(row, name) {
        var el = row.querySelector('[name$="[' + name + ']"]');
        return el ? el.value : '';
    }

    // ₱150 or ₱12.50: whole pesos without the centavos.
    function shortPeso(n) {
        return '₱' + n.toLocaleString('en-US', { minimumFractionDigits: n % 1 ? 2 : 0, maximumFractionDigits: 2 });
    }

    // A reason holds only for the price it was written for.
    function forgetReason(row) {
        if (!row) return;
        if (num(row.querySelector('[data-price-note-for]').value) === num(row.querySelector('[data-price]').value)) return;
        row.querySelector('[data-price-note]').value = '';
        delete opened[rowKey(row)];
        renderPrices();
    }

    function scheduleCheck() {
        clearTimeout(timer);
        timer = setTimeout(runCheck, 500);
    }

    function runCheck() {
        var items = {};
        budgetRows().forEach(function (row) {
            var key = rowKey(row);
            if (key !== null) items[key] = { particular: value(row, 'particular'), unit: value(row, 'unit'), unit_price: value(row, 'unit_price') };
        });
        var mine = ++asked;
        fetch(checkUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
            body: JSON.stringify({ proposal: form.getAttribute('data-proposal') || null, starts_on: startInput.value || null, items: items })
        })
            .then(function (response) { return response.ok ? response.json() : null; })
            .then(function (data) { if (data && mine === asked) showCheck(data); })
            .catch(function () { /* the check is a help only; the form works without it */ });
    }

    function showCheck(data) {
        lastPrices = data.prices || {};
        renderPrices();
        renderSuggestions(data);
    }

    // One line of the Price check box: the item, its price and what is
    // unusual about it, then "Price is correct" or the reason field.
    function priceItem(list, key) {
        var item = list.querySelector('[data-price-item="' + key + '"]');
        if (item) return item;
        item = document.createElement('div');
        item.className = 'help-item';
        item.id = 'price-item-' + key;
        item.setAttribute('data-price-item', key);
        var text = document.createElement('div');
        text.id = item.id + '-text';
        text.appendChild(document.createElement('b'));
        text.appendChild(document.createTextNode(''));
        text.appendChild(document.createElement('br'));
        var why = document.createElement('span');
        why.className = 'item-meta';
        text.appendChild(why);
        var action = document.createElement('div');
        action.className = 'help-action';
        item.appendChild(text);
        item.appendChild(action);
        return item;
    }

    function fillPriceItem(item, row, check, open) {
        var key = item.getAttribute('data-price-item');
        var text = item.firstChild;
        var unit = value(row, 'unit');
        text.childNodes[0].textContent = value(row, 'particular') || 'This row';
        text.childNodes[1].textContent = ' · ' + shortPeso(num(value(row, 'unit_price'))) + (unit ? ' per ' + unit : '');
        text.childNodes[3].textContent = check.summary + ' (' + check.proposals + ' approved ' + (check.proposals === 1 ? 'proposal' : 'proposals') + ')';

        // Rebuilt only when it changes, so a reason being typed keeps its focus.
        var action = item.lastChild;
        item.classList.toggle('is-open', open);
        if (open && !action.querySelector('input')) {
            action.textContent = '';
            var label = document.createElement('label');
            label.textContent = 'Why this price is right';
            var input = document.createElement('input');
            input.className = 'prop-input';
            input.maxLength = 300;
            input.placeholder = 'e.g. catered, drinks included';
            input.setAttribute('data-reason-input', key);
            input.value = row.querySelector('[data-price-note]').value;
            label.appendChild(input);
            action.appendChild(label);
        } else if (!open && !action.querySelector('button')) {
            action.textContent = '';
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn-flat ghost btn-sm';
            button.setAttribute('data-price-ok', key);
            button.textContent = 'Price is correct';
            action.appendChild(button);
        }
        var ok = action.querySelector('button');
        if (ok) ok.setAttribute('aria-label', 'Price is correct: ' + text.childNodes[0].textContent);
    }

    // Marks each unusual Unit Price in its row, and lists them in the
    // Price check box in the order of the rows.
    function renderPrices() {
        var box = form.querySelector('[data-price-box]');
        var list = box.querySelector('[data-price-list]');
        var priced = 0, compared = 0, flagged = 0, explained = 0, shown = [];
        budgetRows().forEach(function (row) {
            var key = rowKey(row);
            var check = key === null ? null : lastPrices[key];
            var unusual = !!check && (check.state === 'high' || check.state === 'low');
            var price = row.querySelector('[data-price]');
            if (check) priced++;
            if (check && check.state !== 'few') compared++;
            row.querySelector('[data-price-cell]').classList.toggle('is-unusual', unusual);
            if (!unusual) {
                price.removeAttribute('aria-describedby');
                return;
            }
            flagged++;
            var note = row.querySelector('[data-price-note]').value.trim();
            if (note) explained++;
            var item = priceItem(list, key);
            fillPriceItem(item, row, check, note !== '' || !!opened[key]);
            price.setAttribute('aria-describedby', item.id + '-text');
            shown.push(item);
        });
        // Only moved when out of place: moving the line with the reason
        // field would take the focus away from it.
        shown.forEach(function (item, i) {
            if (list.children[i] !== item) list.insertBefore(item, list.children[i] || null);
        });
        while (list.children.length > shown.length) list.removeChild(list.lastChild);

        box.hidden = priced === 0;
        box.querySelector('[data-price-foot]').hidden = flagged === 0;
        var summary = box.querySelector('[data-price-summary]');
        summary.textContent = '';
        if (flagged > 0) {
            var count = document.createElement('b');
            count.className = 'warn-text';
            count.textContent = flagged + (flagged === 1 ? ' price looks' : ' prices look') + ' unusual';
            summary.appendChild(count);
            summary.appendChild(document.createTextNode(' next to approved proposals. '
                + (flagged > explained ? 'Fix the price, or click “Price is correct” and say why.' : 'Each has your reason.')));
        } else if (compared > 0) {
            summary.textContent = (compared === 1 ? 'The 1 price' : 'The ' + compared + ' prices') + ' compared with approved proposals '
                + (compared === 1 ? 'is' : 'are') + ' close to what they paid.'
                + (priced > compared ? ' ' + (priced - compared) + ' had nothing to compare with yet.' : '');
        } else {
            summary.textContent = 'None of these items has 3 or more prices in approved proposals yet, so there is nothing to compare with.';
        }
    }

    function renderSuggestions(data) {
        suggestions = data.suggestions || [];
        var box = form.querySelector('[data-suggest]');
        var list = box.querySelector('[data-suggest-list]');
        var wait = box.querySelector('[data-suggest-wait]');
        list.textContent = '';
        suggestions.forEach(function (s, i) {
            var item = document.createElement('div');
            item.className = 'help-item';
            var text = document.createElement('div');
            var name = document.createElement('b');
            name.textContent = s.label;
            var why = document.createElement('span');
            why.className = 'item-meta';
            why.textContent = s.text;
            text.appendChild(name);
            text.appendChild(document.createElement('br'));
            text.appendChild(why);
            var add = document.createElement('button');
            add.type = 'button';
            add.className = 'btn-flat ghost btn-sm';
            add.setAttribute('data-suggest-add', String(i));
            add.setAttribute('aria-label', 'Add ' + s.label + ' to the budget');
            add.innerHTML = '<i class="fas fa-plus me-1" aria-hidden="true"></i> Add row';
            item.appendChild(text);
            item.appendChild(add);
            list.appendChild(item);
        });
        var waiting = data.approved < data.needed;
        wait.hidden = !waiting;
        wait.textContent = waiting ? 'Suggestions start once there are ' + data.needed
            + ' approved proposals (now ' + data.approved + ').' : '';
        box.querySelector('[data-suggest-intro]').hidden = waiting;
        box.hidden = !waiting && suggestions.length === 0;
    }

    // A suggested item becomes a new budget row with its usual category and unit.
    function addSuggested(s) {
        if (!s) return;
        var list = document.getElementById('budgetList');
        list.insertAdjacentHTML('beforeend', document.getElementById('budgetTpl').innerHTML.replace(/__KEY__/g, String(nextKey++)));
        var row = list.lastElementChild;
        row.querySelector('[name$="[category]"]').value = s.category || '';
        row.querySelector('[name$="[particular]"]').value = s.label;
        row.querySelector('[name$="[unit]"]').value = s.unit || '';
        renumber(list);
        row.querySelector('[data-qty]').focus();
        scheduleCheck();
    }

    runCheck();
})();
</script>

@endsection
