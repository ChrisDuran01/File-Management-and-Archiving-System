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
    // The price check of each budget row (App\Services\PriceCheck), by item id.
    $isUnusual = fn ($check) => $check && in_array($check['state'], ['high', 'low'], true);
    $compared = collect($priceChecks)->filter(fn ($check) => $check && $check['state'] !== 'few');
    $unusual = $compared->filter($isUnusual);
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
            @include('Admin.proposals.partials.status-chip')
            · {{ $proposal->dateLabel() }}
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
        @if ($canEdit)
            <a href="{{ route('proposals.edit', $proposal) }}" class="btn-flat"><i class="fas fa-pen me-1" aria-hidden="true"></i> Edit</a>
            <form method="POST" action="{{ route('proposals.destroy', $proposal) }}"
                  data-confirm-title="Delete this proposal?"
                  data-confirm="“{{ $proposal->title }}” will be moved to the Trash. You can restore it from there."
                  data-confirm-ok="Delete">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn-flat danger"><i class="fas fa-trash-alt me-1" aria-hidden="true"></i> Delete</button>
            </form>
        @endif
    </div>
</div>

{{-- STATUS + SIGNED COPY. Changed by the maker or the Super Admin; an
     approved proposal is locked until the Super Admin reopens it. --}}
<div class="card-panel prop-doc prop-status">
    <h2>Status and signed copy</h2>
    <dl class="prop-facts">
        <dt>Status</dt>
        <dd>
            @include('Admin.proposals.partials.status-chip')
            @if ($proposal->approved_at)
                <span class="item-meta">approved {{ $proposal->approved_at->format('M j, Y') }}</span>
            @endif
        </dd>
        <dt>Signed copy</dt>
        <dd>
            @if ($proposal->signedFile)
                <a href="{{ route('files.preview', $proposal->signedFile->id) }}"><i class="fas fa-file-signature me-1" aria-hidden="true"></i>{{ $proposal->signedFile->filename }}</a>
                <span class="item-meta">in {{ \App\Models\ActivityProposal::SIGNED_COPIES_FOLDER }}</span>
            @else
                <span class="prop-empty">None yet.</span>
            @endif
        </dd>
        <dt>Student page</dt>
        <dd>
            @if ($proposal->isOnStudentPage())
                <a href="{{ route('student.activity', [$proposal->id, $proposal->slug()]) }}" target="_blank" rel="noopener"><i class="fas fa-globe me-1" aria-hidden="true"></i>Shown to students</a>
            @elseif ($proposal->is_public)
                <span>Switched on. It shows once the proposal is approved.</span>
            @else
                <span class="prop-empty">Not shown.</span>
            @endif
            @if ($canChange)
                <form method="POST" action="{{ route('proposals.publish', $proposal) }}" style="display:inline;">
                    @csrf
                    <input type="hidden" name="is_public" value="{{ $proposal->is_public ? 0 : 1 }}">
                    <button type="submit" class="btn-flat ghost btn-sm">{{ $proposal->is_public ? 'Hide from student page' : 'Show on student page' }}</button>
                </form>
            @endif
        </dd>
    </dl>
    @if ($canChange)
        <p class="item-meta" style="margin:8px 0 0;">
            Students see the title, theme, date, objectives and the budget per category. Names, signatures and the signed copy are never shown.
        </p>
    @endif

    @if ($proposal->isLocked())
        <p class="item-meta" style="margin:12px 0 0;">
            <i class="fas fa-lock me-1" aria-hidden="true"></i>
            Approved, so it stays as it was signed and can't be edited or deleted. To change it, Copy it into a new proposal{{ $isSuperAdmin ? '' : ', or ask the Super Admin to reopen it' }}.
        </p>
    @endif

    @if ($canChange)
        <div class="status-forms">
            <form method="POST" action="{{ route('proposals.status', $proposal) }}" class="status-form">
                @csrf
                <label for="propStatus">Set status</label>
                <div class="prop-row">
                    <select id="propStatus" name="status" class="prop-input">
                        @foreach ($proposal->statusOptions() as $key => $label)
                            <option value="{{ $key }}" @selected($proposal->status === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn-flat">Save</button>
                </div>
                @error('status')<div class="field-error">{{ $message }}</div>@enderror
            </form>

            <form method="POST" action="{{ route('proposals.signed', $proposal) }}" enctype="multipart/form-data" class="status-form">
                @csrf
                <label for="signedUpload">{{ $proposal->signedFile ? 'Replace the signed copy' : 'Upload the signed copy' }}</label>
                <div class="prop-row">
                    <input type="file" id="signedUpload" name="files[]" accept=".pdf,.jpg,.jpeg,.png,.webp" class="prop-input" required>
                    <button type="submit" class="btn-flat">Upload</button>
                </div>
                <div class="item-meta" style="margin-top:4px;">
                    A PDF or photo of the signed paper. It is saved in the “{{ \App\Models\ActivityProposal::SIGNED_COPIES_FOLDER }}” folder{{ $proposal->isLocked() ? '' : ', and the proposal becomes Approved' }}.
                </div>
                @error('files')<div class="field-error">{{ $message }}</div>@enderror
                @error('files.*')<div class="field-error">{{ $message }}</div>@enderror
            </form>

            @if ($signedChoices->isNotEmpty())
                <form method="POST" action="{{ route('proposals.signed', $proposal) }}" class="status-form">
                    @csrf
                    <label for="signedPick">Or choose a scan already in the folder</label>
                    <div class="prop-row">
                        <select id="signedPick" name="file_id" class="prop-input" required>
                            @foreach ($signedChoices as $choice)
                                <option value="{{ $choice->id }}">{{ $choice->filename }} ({{ $choice->created_at->format('M j, Y') }})</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn-flat ghost">Attach</button>
                    </div>
                </form>
            @endif
        </div>
    @endif

    @if ($isSuperAdmin && $proposal->isLocked())
        <form method="POST" action="{{ route('proposals.reopen', $proposal) }}" style="margin-top:14px;"
              data-confirm-title="Reopen this proposal?"
              data-confirm="It becomes a draft again and can be edited, so it may no longer match the signed paper. The signed copy stays attached."
              data-confirm-ok="Reopen">
            @csrf
            <button type="submit" class="btn-flat ghost"><i class="fas fa-lock-open me-1" aria-hidden="true"></i> Reopen for changes</button>
        </form>
    @endif
</div>

<form method="GET" action="{{ route('proposals.pdf', $proposal) }}" target="_blank" class="card-panel prop-filters prop-doc" style="align-items:center;">
    <i class="fas fa-file-pdf" style="color:var(--danger);" aria-hidden="true"></i>
    <label for="pdfLetterhead" style="font-weight:600; font-size:.86rem; margin:0;">Print on</label>
    <select id="pdfLetterhead" name="letterhead">
        @foreach ($letterheads as $lh)
            <option value="{{ $lh->id }}" @selected((int) $letterheadId === $lh->id || (! $letterheadId && $loop->first))>{{ $lh->name }}{{ $lh->is_default ? ' (default)' : '' }}</option>
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
                            @php $check = $priceChecks[$item->id] ?? null; @endphp
                            <tr>
                                <td>{{ $item->category ?? '' }}</td>
                                <td>{{ $item->particular }}</td>
                                <td class="num">{{ $qty($item->quantity) }}</td>
                                <td>{{ $item->unit }}</td>
                                <td class="num">@if ($isUnusual($check))<span class="price-mark" title="Unusual price, see Price check"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i><span class="sr-only">Unusual price:</span></span>@endif{{ $peso($item->unit_price) }}</td>
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

            {{-- The price check: what is unusual about each marked price and the
                 officer's reason. Not in the PDF. --}}
            @if ($compared->isNotEmpty())
                <div class="budget-help">
                    <div class="help-box">
                        <h3>Price check</h3>
                        <p class="item-meta">
                            @if ($unusual->isNotEmpty())
                                <b class="warn-text">{{ $unusual->count() }} of the {{ $compared->count() }} prices {{ $unusual->count() === 1 ? 'looks' : 'look' }} unusual</b> next to approved proposals.
                            @else
                                {{ $compared->count() === 1 ? 'The 1 price' : 'The '.$compared->count().' prices' }} compared with approved proposals {{ $compared->count() === 1 ? 'is' : 'are' }} close to what they paid.
                            @endif
                        </p>
                        @foreach ($proposal->items as $item)
                            @php $check = $priceChecks[$item->id] ?? null; @endphp
                            @if ($isUnusual($check))
                                <div class="help-item is-open">
                                    <div>
                                        <b>{{ $item->particular }}</b> · {{ \App\Services\PriceCheck::peso((float) $item->unit_price) }}{{ $item->unit ? ' per '.$item->unit : '' }}<br>
                                        <span class="item-meta">{{ $check['summary'] }} ({{ $check['proposals'] }} approved {{ Str::plural('proposal', $check['proposals']) }})</span>
                                    </div>
                                    <div>{!! filled($item->price_note) ? '<span class="item-meta">Reason:</span> '.e($item->price_note) : '<span class="item-meta">No reason given yet.</span>' !!}</div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
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
