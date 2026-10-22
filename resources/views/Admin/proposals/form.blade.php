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
    $items = old('items', $proposal->exists && $proposal->items->isNotEmpty()
        ? $proposal->items->map(fn ($item) => [
            'category'   => $item->category,
            'particular' => $item->particular,
            'quantity'   => $plain($item->quantity),
            'unit'       => $item->unit,
            'unit_price' => $plain($item->unit_price),
        ])->all()
        : array_fill(0, 3, ['category' => '', 'particular' => '', 'quantity' => '', 'unit' => '', 'unit_price' => '']));
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

<form method="POST" id="proposalForm" class="prop-form"
      action="{{ $proposal->exists ? route('proposals.update', $proposal) : route('proposals.store') }}">
    @csrf
    @if ($proposal->exists) @method('PUT') @endif

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
                            <td><input name="items[{{ $key }}][particular]" class="prop-input @error('items.'.$key.'.particular') is-invalid @enderror" maxlength="255" value="{{ $row['particular'] ?? '' }}" data-label="Particular, row" aria-label="Particular, row {{ $loop->iteration }}"></td>
                            <td><input type="number" step="any" min="0" inputmode="decimal" name="items[{{ $key }}][quantity]" class="prop-input @error('items.'.$key.'.quantity') is-invalid @enderror" value="{{ $row['quantity'] ?? '' }}" data-qty data-label="Quantity, row" aria-label="Quantity, row {{ $loop->iteration }}"></td>
                            <td><input name="items[{{ $key }}][unit]" class="prop-input" maxlength="30" list="propUnits" value="{{ $row['unit'] ?? '' }}" data-label="Unit, row" aria-label="Unit, row {{ $loop->iteration }}"></td>
                            <td><input type="number" step="0.01" min="0" inputmode="decimal" name="items[{{ $key }}][unit_price]" class="prop-input @error('items.'.$key.'.unit_price') is-invalid @enderror" value="{{ $row['unit_price'] ?? '' }}" data-price data-label="Unit Price, row" aria-label="Unit Price, row {{ $loop->iteration }}"></td>
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
                <td><input type="number" step="0.01" min="0" inputmode="decimal" name="items[__KEY__][unit_price]" class="prop-input" data-price data-label="Unit Price, row" aria-label="Unit Price"></td>
                <td class="num" data-row-total>₱0.00</td>
                <td><button type="button" class="row-remove" data-remove-row data-label="Remove budget row" aria-label="Remove budget row"><i class="fas fa-times" aria-hidden="true"></i></button></td>
            </tr>
        </template>
        <button type="button" class="btn-flat ghost btn-sm" data-add-row="budget"><i class="fas fa-plus me-1" aria-hidden="true"></i> Add row</button>

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
            var addButton = form.querySelector('[data-add-row="' + kindOf + '"]');
            if (addButton) addButton.focus();
        }
    });

    form.addEventListener('input', function (e) {
        if (e.target.matches('[data-qty], [data-price]')) recompute();
        if (e.target.matches('textarea.cell-text')) fit(e.target);
    });
})();
</script>

@endsection
