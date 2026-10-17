@extends($layout ?? 'Admin.home')
@section('content')

@include('Admin.partials.theme')

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

<style>
    * { box-sizing:border-box; }
    body { background:var(--surface); font-family:'Inter',sans-serif; color:var(--text-1); }

    .card-panel {
        background:var(--card); border:1px solid var(--border); border-radius:var(--radius);
        box-shadow:var(--shadow-sm); padding:16px 18px; margin-bottom:18px;
    }

    .filter-row { display:flex; gap:12px; flex-wrap:wrap; align-items:end; }
    .filter-row .fg { display:flex; flex-direction:column; }
    .filter-row label { font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:var(--text-3); margin-bottom:5px; }
    .filter-row input, .filter-row select {
        border:1.5px solid var(--border); border-radius:var(--radius-sm); padding:8px 11px; font-size:.85rem; font-family:inherit;
    }
    .filter-row input:focus, .filter-row select:focus { border-color:var(--primary); box-shadow:0 0 0 3px var(--primary-dim); outline:none; }
    .btn-flat { background:var(--brand); color:#fff; border:none; border-radius:var(--radius-sm); padding:9px 18px; font-size:.85rem; font-weight:600; cursor:pointer; text-decoration:none; }
    .btn-flat.ghost { background:var(--card); color:var(--text-2); border:1.5px solid var(--border); }

    .doc-table { width:100%; border-collapse:collapse; font-size:.86rem; }
    .doc-table th { text-align:left; font-size:.7rem; text-transform:uppercase; letter-spacing:.05em; color:var(--text-3); font-weight:700; padding:11px 14px; border-bottom:1.5px solid var(--border); }
    .doc-table td { padding:12px 14px; border-bottom:1px solid var(--border); }
    .doc-table tr:last-child td { border-bottom:none; }
    .doc-table tbody tr:hover { background:var(--primary-dim); cursor:pointer; }
    .doc-title { font-weight:600; color:var(--text-1); text-decoration:none; }
    .type-chip { font-size:.68rem; font-weight:700; padding:2px 9px; border-radius:20px; background:#ede9fe; color:var(--brand); }
    .arch-chip { font-size:.66rem; font-weight:700; padding:2px 8px; border-radius:20px; background:#f1f5f9; color:#64748b; margin-left:6px; }
    .empty-row { text-align:center; color:var(--text-3); padding:40px 16px; }

    /* ===== DARK MODE ===== */
    [data-theme="dark"] .type-chip { background:#201f33; color:#a5a3e0; }
    [data-theme="dark"] .arch-chip { background:#26251f; color:var(--text-2); }
</style>

{{-- Skeleton placeholder, shown until the real content below is revealed --}}
<div class="skel-shell" aria-hidden="true">
    <div class="page-header">
        <div>
            <div class="skel skel-text" style="width:150px; height:22px; margin-bottom:8px;"></div>
            <div class="skel skel-text" style="width:220px;"></div>
        </div>
        <div class="skel" style="width:120px; height:38px; border-radius:8px;"></div>
    </div>

    <div class="card-panel">
        <div class="filter-row">
            @for ($i = 0; $i < 5; $i++)
            <div class="fg">
                <div class="skel skel-text" style="width:70px; margin-bottom:5px;"></div>
                <div class="skel" style="width:150px; height:38px; border-radius:6px;"></div>
            </div>
            @endfor
            <div class="skel" style="width:80px; height:38px; border-radius:6px;"></div>
        </div>
    </div>

    <div class="card-panel" style="padding:16px;">
        @for ($i = 0; $i < 6; $i++)
        <div style="display:flex; gap:16px; padding:9px 0; border-bottom:1px solid var(--border);">
            <div class="skel skel-text" style="width:20%;"></div>
            <div class="skel skel-text" style="width:10%;"></div>
            <div class="skel skel-text" style="width:15%;"></div>
            <div class="skel skel-text" style="width:15%;"></div>
            <div class="skel skel-text" style="width:10%;"></div>
            <div class="skel skel-text" style="width:10%;"></div>
            <div class="skel skel-text" style="width:15%;"></div>
        </div>
        @endfor
    </div>
</div>

<div class="skel-real">

<div class="page-header">
    <div>
        <h2><i class="fas fa-folder-open me-2" style="color:var(--brand);font-size:1.1rem;"></i>Documents</h2>
        <p class="sub">Digitized hardcopy records. {{ $documents->count() }} shown.</p>
    </div>
    <a href="{{ route('documents.scans.index') }}" class="btn-flat ghost"><i class="fas fa-inbox me-1"></i> Scan inbox</a>
</div>

<div class="card-panel">
    <form method="GET" action="{{ route('documents.index') }}" class="filter-row">
        <div class="fg">
            <label>Search</label>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="title, ref no, sender" style="min-width:220px;">
        </div>
        <div class="fg">
            <label>Type</label>
            <select name="type">
                <option value="">Any</option>
                @foreach ($types as $t)
                    <option value="{{ $t }}" @selected(request('type') === $t)>{{ $t }}</option>
                @endforeach
            </select>
        </div>
        <div class="fg">
            <label>Folder</label>
            <select name="folder_id">
                <option value="">Any</option>
                @foreach ($folders as $folder)
                    <option value="{{ $folder->id }}" @selected((string) request('folder_id') === (string) $folder->id)>{{ $folder->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="fg">
            <label>Received from</label>
            <input type="date" name="from" value="{{ request('from') }}">
        </div>
        <div class="fg">
            <label>To</label>
            <input type="date" name="to" value="{{ request('to') }}">
        </div>
        <div class="fg">
            <label>&nbsp;</label>
            <label style="font-weight:500;font-size:.8rem;text-transform:none;letter-spacing:0;color:var(--text-2);">
                <input type="checkbox" name="show_archived" value="1" @checked(request()->boolean('show_archived'))> include archived
            </label>
        </div>
        <button type="submit" class="btn-flat">Filter</button>
        <a href="{{ route('documents.index') }}" class="btn-flat ghost">Reset</a>
    </form>
</div>

<div class="card-panel" style="padding:6px 4px;">
    <table class="doc-table">
        <thead>
            <tr>
                <th>Title</th>
                <th>Type</th>
                <th>Reference no.</th>
                <th>Folder</th>
                <th>Doc date</th>
                <th>Received</th>
                <th>From</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($documents as $doc)
                <tr onclick="window.location='{{ route('documents.show', $doc) }}'">
                    <td>
                        <a class="doc-title" href="{{ route('documents.show', $doc) }}">{{ $doc->title }}</a>
                        @if ($doc->is_archived)<span class="arch-chip">archived</span>@endif
                    </td>
                    <td>@if($doc->document_type)<span class="type-chip">{{ $doc->document_type }}</span>@else <span style="color:var(--text-3);">—</span> @endif</td>
                    <td>{{ $doc->reference_no ?: '—' }}</td>
                    <td>{{ $doc->folder?->name ?: '—' }}</td>
                    <td>{{ optional($doc->document_date)->format('Y-m-d') ?: '—' }}</td>
                    <td>{{ optional($doc->date_received)->format('Y-m-d') ?: '—' }}</td>
                    <td>{{ $doc->sender ?: '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty-row"><i class="fas fa-folder-open mb-2 d-block" style="font-size:1.6rem;"></i>No documents match.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

</div>

<script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>

@endsection
