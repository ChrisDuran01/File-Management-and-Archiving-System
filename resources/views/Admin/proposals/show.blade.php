@extends($layout ?? 'Admin.home')
@section('title', $proposal->title)
@section('content')

@include('Admin.partials.theme')

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

@include('Admin.proposals.partials.styles')

@php
    $peso = fn ($amount) => \App\Models\ActivityProposal::peso((float) $amount);
    // 120.00 -> "120", 2.50 -> "2.5"
    $qty = fn ($q) => rtrim(rtrim(number_format((float) $q, 2), '0'), '.');
    $byCategory = $proposal->items
        ->groupBy(fn ($item) => $item->category ?: 'No category')
        ->map(fn ($items) => $items->sum(fn ($item) => $item->totalPrice()));
    $paragraphs = fn (?string $text) => array_values(array_filter(array_map('trim', preg_split('/\R+/', (string) $text))));
    $signatoryGroups = $proposal->signatoryGroups();
@endphp

{{-- Skeleton placeholder, shown until the real content below is revealed --}}
<div class="skel-shell" aria-hidden="true">
    <div class="skel skel-text" style="width:160px; margin-bottom:1rem;"></div>
    <div class="page-header">
        <div>
            <div class="skel skel-text" style="width:320px; height:22px; margin-bottom:8px;"></div>
            <div class="skel skel-text" style="width:260px;"></div>
        </div>
    </div>
    <div class="card-panel prop-doc">
        <div class="skel skel-text" style="width:90%; margin:12px 0;"></div>
        <div class="skel skel-text" style="width:75%; margin:12px 0;"></div>
        <div class="skel skel-text" style="width:85%; margin:12px 0;"></div>
        <div class="skel skel-text" style="width:60%; margin:12px 0;"></div>
    </div>
</div>

<div class="skel-real">

@include('Admin.partials.back-link', [
    'backTo'    => route('proposals.index', ['year' => $proposal->school_year ?? 'all']),
    'backLabel' => 'Activity Proposals',
])

<div class="page-header">
    <div>
        <h1>{{ $proposal->title }}</h1>
        <p class="sub">
            {{ $proposal->dateLabel() }}
            @if ($proposal->school_year) · School year {{ $proposal->school_year }} @endif
            · Last changed {{ $proposal->updated_at->format('M j, Y, g:i A') }}
        </p>
        @if ($proposal->copiedFrom)
            <p class="sub">Copied from <a href="{{ route('proposals.show', $proposal->copiedFrom) }}">{{ $proposal->copiedFrom->title }}{{ $proposal->copiedFrom->school_year ? ' ('.$proposal->copiedFrom->school_year.')' : '' }}</a></p>
        @endif
    </div>
    <div class="prop-actions">
        <form method="POST" action="{{ route('proposals.copy', $proposal) }}">
            @csrf
            <button type="submit" class="btn-flat ghost"><i class="fas fa-copy me-1" aria-hidden="true"></i> Copy</button>
        </form>
        @if ($canChange)
            <a href="{{ route('proposals.edit', $proposal) }}" class="btn-flat"><i class="fas fa-pen me-1" aria-hidden="true"></i> Edit</a>
            <form method="POST" action="{{ route('proposals.destroy', $proposal) }}"
                  data-confirm-title="Delete this proposal?"
                  data-confirm="“{{ $proposal->title }}” and its budget will be deleted. This cannot be undone."
                  data-confirm-ok="Delete">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn-flat danger"><i class="fas fa-trash-alt me-1" aria-hidden="true"></i> Delete</button>
            </form>
        @endif
    </div>
</div>

<form method="GET" action="{{ route('proposals.pdf', $proposal) }}" target="_blank" class="card-panel prop-filters prop-doc" style="align-items:center;">
    <i class="fas fa-file-pdf" style="color:var(--danger);" aria-hidden="true"></i>
    <label for="pdfLetterhead" style="font-weight:600; font-size:.86rem; margin:0;">Print on</label>
    <select id="pdfLetterhead" name="letterhead">
        @foreach ($letterheads as $lh)
            <option value="{{ $lh->id }}" @selected((int) $letterheadId === $lh->id || (! $letterheadId && $loop->first))>{{ $lh->name }}</option>
        @endforeach
        <option value="" @selected($letterheads->isEmpty())>No letterhead</option>
    </select>
    <button type="submit" class="btn-flat">Open PDF</button>
    <span class="item-meta">Opens in a new tab, ready to print. Signatures are done on paper.</span>
</form>

<div class="card-panel prop-doc">

    <dl class="prop-facts">
        <dt>Title</dt><dd>{{ $proposal->title }}</dd>
        <dt>Theme</dt><dd>{{ $proposal->theme ?? '—' }}</dd>
        <dt>Date</dt><dd>{{ $proposal->dateLabel() }}</dd>
        <dt>Proponent</dt><dd>{{ $proposal->proponent ?? '—' }}</dd>
        <dt>Budget</dt><dd>{{ $peso($proposal->budgetTotal()) }}</dd>
        <dt>Made by</dt><dd>{{ $proposal->preparedByLabel() }}{{ $proposal->prepared_by_position && $proposal->creator ? ' ('.$proposal->creator->name.')' : '' }}</dd>
    </dl>

    <section aria-labelledby="secRationale">
        <h2 id="secRationale">I. Rationale</h2>
        @forelse ($paragraphs($proposal->rationale) as $p)
            <p class="prop-text" style="margin-bottom:8px;">{{ $p }}</p>
        @empty
            <p class="prop-empty">Not written yet.</p>
        @endforelse
    </section>

    <section aria-labelledby="secObjectives">
        <h2 id="secObjectives">II. Objectives</h2>
        @foreach ($paragraphs($proposal->objectives_intro) as $p)
            <p class="prop-text" style="margin-bottom:8px;">{{ $p }}</p>
        @endforeach
        @if (! empty($proposal->objectives))
            <ol>
                @foreach ($proposal->objectives as $objective)
                    <li>{{ $objective }}</li>
                @endforeach
            </ol>
        @elseif (! $proposal->objectives_intro)
            <p class="prop-empty">Not written yet.</p>
        @endif
    </section>

    <section aria-labelledby="secMethods">
        <h2 id="secMethods">III. Methods &amp; Approaches</h2>
        @forelse ($paragraphs($proposal->methods) as $p)
            <p class="prop-text" style="margin-bottom:8px;">{{ $p }}</p>
        @empty
            <p class="prop-empty">Not written yet.</p>
        @endforelse
    </section>

    <section aria-labelledby="secSchedule">
        <h2 id="secSchedule">IV. Schedule of Activities</h2>
        @if (! empty($proposal->schedule) || $proposal->master_of_ceremony)
            <div class="table-scroll">
                <table class="read-table" style="min-width:540px;">
                    <thead>
                        <tr><th scope="col">Time</th><th scope="col">Activity</th><th scope="col">Venue/Personnel</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($proposal->schedule ?? [] as $row)
                            <tr>
                                <td style="white-space:nowrap;">{{ $row['time'] ?? '' }}</td>
                                <td class="prop-text">{{ $row['activity'] ?? '' }}</td>
                                <td class="prop-text">{{ $row['personnel'] ?? $row['in_charge'] ?? '' }}</td>
                            </tr>
                        @endforeach
                        @if ($proposal->master_of_ceremony)
                            <tr><td colspan="3" style="text-align:center;">{{ $proposal->master_of_ceremony }}<br><span class="item-meta">Master of Ceremony</span></td></tr>
                        @endif
                    </tbody>
                </table>
            </div>
        @else
            <p class="prop-empty">Not written yet.</p>
        @endif
    </section>

    <section aria-labelledby="secBudget">
        <h2 id="secBudget">V. Budgetary Requirements</h2>
        @if ($proposal->items->isNotEmpty())
            <div class="table-scroll">
                <table class="read-table" style="min-width:680px;">
                    <thead>
                        <tr>
                            <th scope="col">Category</th>
                            <th scope="col">Particular</th>
                            <th scope="col" class="num">Quantity</th>
                            <th scope="col">Unit</th>
                            <th scope="col" class="num">Unit Price</th>
                            <th scope="col" class="num">Total Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($proposal->items as $item)
                            <tr>
                                <td>{{ $item->category ?? '' }}</td>
                                <td>{{ $item->particular }}</td>
                                <td class="num">{{ $qty($item->quantity) }}</td>
                                <td>{{ $item->unit }}</td>
                                <td class="num">{{ $peso($item->unit_price) }}</td>
                                <td class="num">{{ $peso($item->totalPrice()) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="5" style="text-align:right;">Grand Total</td>
                            <td class="num">{{ $peso($proposal->budgetTotal()) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            @if ($byCategory->count() > 1)
                <table class="read-table cat-totals">
                    <caption class="item-meta" style="caption-side:top; padding:0 0 4px;">Total per category</caption>
                    <tbody>
                        @foreach ($byCategory as $category => $sum)
                            <tr><td>{{ $category }}</td><td class="num">{{ $peso($sum) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        @else
            <p class="prop-empty">No budget yet.</p>
        @endif
    </section>

    <section aria-labelledby="secSign">
        <h2 id="secSign">Signatories</h2>
        @forelse ($signatoryGroups as $group)
            <p class="item-meta" style="margin:10px 0 4px; font-weight:600;">{{ $group['label'] }}</p>
            <ul style="margin:0; padding-left:18px;">
                @foreach ($group['people'] as $person)
                    <li><b>{{ $person['name'] ?: '[no name yet]' }}</b>{{ $person['position'] ? ', '.$person['position'] : '' }}</li>
                @endforeach
            </ul>
        @empty
            <p class="prop-empty">None yet.</p>
        @endforelse
    </section>
</div>

</div>

@endsection
