@extends($layout ?? 'Admin.home')
@section('title', 'Activity Proposals')
@section('content')

@include('Admin.partials.theme')

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

@include('Admin.proposals.partials.styles')

{{-- Skeleton placeholder, shown until the real content below is revealed --}}
<div class="skel-shell" aria-hidden="true">
    <div class="page-header">
        <div>
            <div class="skel skel-text" style="width:190px; height:22px; margin-bottom:8px;"></div>
            <div class="skel skel-text" style="width:360px;"></div>
        </div>
        <div class="skel" style="width:130px; height:38px; border-radius:8px;"></div>
    </div>
    <div class="card-panel" style="display:flex; gap:10px;">
        <div class="skel" style="flex:1; height:38px; border-radius:8px;"></div>
        <div class="skel" style="width:150px; height:38px; border-radius:8px;"></div>
    </div>
    <div class="card-panel">
        @for ($i = 0; $i < 5; $i++)
            <div class="skel skel-text" style="width:{{ 90 - $i * 8 }}%; margin:12px 0;"></div>
        @endfor
    </div>
</div>

<div class="skel-real">

<div class="page-header">
    <div>
        <h1><i class="fas fa-clipboard-list me-2" style="color:var(--brand);font-size:1.1rem;" aria-hidden="true"></i>Activity Proposals</h1>
        <p class="sub">
            Detailed Activity Proposals with their rationale, objectives, methods, schedule, budget and signatories.
            {{ $proposals->count() }} {{ $year === 'all' ? 'in all school years' : 'in '.$year }}.
        </p>
    </div>
    <a href="{{ route('proposals.create') }}" class="btn-flat"><i class="fas fa-plus me-1" aria-hidden="true"></i> New proposal</a>
</div>

<form method="GET" action="{{ route('proposals.index') }}" class="card-panel prop-filters">
    <label for="propSearch" class="sr-only">Search proposals</label>
    <input type="search" id="propSearch" name="q" value="{{ $search }}" placeholder="Search by title, theme, particular or category">
    <label for="propYear" class="sr-only">School year</label>
    <select id="propYear" name="year">
        @foreach ($years as $y)
            <option value="{{ $y }}" @selected($year === $y)>{{ $y }}</option>
        @endforeach
        <option value="all" @selected($year === 'all')>All school years</option>
    </select>
    <button type="submit" class="btn-flat ghost">Show</button>
</form>

@if ($proposals->isEmpty())
    <div class="card-panel">
        <div class="empty-state">
            <i class="fas fa-clipboard-list" aria-hidden="true"></i>
            @if ($search !== '')
                <p style="margin:0;">No proposal matches “{{ $search }}”.</p>
            @else
                <p style="margin:0 0 6px;">No proposals {{ $year === 'all' ? 'yet' : 'for '.$year.' yet' }}.</p>
                @if ($year !== 'all')
                    <p style="margin:0;">To start from last year's, <a href="{{ route('proposals.index', ['year' => 'all']) }}">show all school years</a> and click Copy.</p>
                @endif
            @endif
        </div>
    </div>
@else
    <div class="card-panel" style="padding:6px 4px 4px;">
        <div class="table-scroll">
            <table class="data-table prop-table">
                <thead>
                    <tr>
                        <th scope="col">Activity</th>
                        <th scope="col">Date</th>
                        <th scope="col">Prepared by</th>
                        <th scope="col" class="num">Budget</th>
                        <th scope="col" class="actions"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($proposals as $proposal)
                    <tr>
                        <td>
                            <a href="{{ route('proposals.show', $proposal) }}" class="prop-name">{{ $proposal->title }}</a>
                            @if ($proposal->theme || $proposal->copiedFrom)
                                <div class="item-meta">
                                    {{ $proposal->theme }}
                                    @if ($proposal->copiedFrom)
                                        {{ $proposal->theme ? '·' : '' }} Copied from {{ $proposal->copiedFrom->school_year ?? 'an older proposal' }}
                                    @endif
                                </div>
                            @endif
                        </td>
                        <td class="item-meta">{{ $proposal->dateLabel() }}</td>
                        <td class="item-meta">{{ $proposal->preparedByLabel() }}</td>
                        <td class="num">{{ \App\Models\ActivityProposal::peso($proposal->budgetTotal()) }}</td>
                        <td class="actions">
                            <div class="prop-actions" style="justify-content:flex-end;">
                                <form method="POST" action="{{ route('proposals.copy', $proposal) }}">
                                    @csrf
                                    <button type="submit" class="btn-flat ghost btn-sm"><i class="fas fa-copy me-1" aria-hidden="true"></i> Copy<span class="sr-only"> {{ $proposal->title }}</span></button>
                                </form>
                                @if ($proposal->canBeChangedBy(Auth::user()))
                                    <a href="{{ route('proposals.edit', $proposal) }}" class="btn-flat ghost btn-sm"><i class="fas fa-pen me-1" aria-hidden="true"></i> Edit<span class="sr-only"> {{ $proposal->title }}</span></a>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

</div>

@endsection
