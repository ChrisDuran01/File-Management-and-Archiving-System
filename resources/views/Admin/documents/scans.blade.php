@extends($layout ?? 'Admin.home')
@section('content')

@include('Admin.partials.theme')

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

<style>
    * { box-sizing: border-box; }
    body { background: var(--surface); font-family: 'Inter', sans-serif; color: var(--text-1); }

    .page-header h2 { font-size: 1.35rem; font-weight: 600; letter-spacing: -.3px; margin: 0 0 4px; }
    .page-header .sub { font-size: .85rem; color: var(--text-2); margin: 0 0 20px; }

    .card-panel {
        background: var(--card); border: 1px solid var(--border); border-radius: var(--radius);
        box-shadow: var(--shadow-sm); padding: 6px 4px; margin-bottom: 20px;
    }

    .scan-table { width: 100%; border-collapse: collapse; font-size: .86rem; }
    .scan-table th {
        text-align: left; font-size: .7rem; text-transform: uppercase; letter-spacing: .05em;
        color: var(--text-3); font-weight: 700; padding: 12px 16px; border-bottom: 1.5px solid var(--border);
    }
    .scan-table td { padding: 13px 16px; border-bottom: 1px solid var(--border); vertical-align: middle; }
    .scan-table tr:last-child td { border-bottom: none; }
    .scan-name { font-weight: 600; }
    .scan-name .fa-file-pdf { color: #e74c3c; margin-right: 8px; }
    .scan-name .fa-file-image { color: var(--primary); margin-right: 8px; }

    .chip {
        font-size: .68rem; font-weight: 700; padding: 3px 10px; border-radius: 20px;
        text-transform: capitalize; white-space: nowrap; display: inline-block;
    }
    .chip.ingesting, .chip.extracting { background: #f1f5f9; color: #64748b; }
    .chip.ready_for_review           { background: #ede9fe; color: var(--brand); }
    .chip.filing                     { background: #fef3c7; color: #b45309; }
    .chip.filed                      { background: #dcfce7; color: #15803d; }
    .chip.failed                     { background: #fee2e2; color: #b91c1c; }

    .row-actions { display: flex; gap: 6px; justify-content: flex-end; }
    .btn-sm-flat {
        border: 1.5px solid var(--border); background: var(--card); color: var(--text-2);
        padding: 6px 12px; border-radius: 20px; font-size: .76rem; font-weight: 600;
        cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 5px;
    }
    .btn-sm-flat:hover { border-color: var(--primary); color: var(--primary); }
    .btn-sm-flat.review { border-color: var(--brand); color: var(--brand); }
    .btn-sm-flat.danger:hover { border-color: #b91c1c; color: #b91c1c; }
    .btn-sm-flat[disabled], .btn-sm-flat.is-disabled { opacity: .4; pointer-events: none; }

    .scan-error { font-size: .74rem; color: #b91c1c; margin-top: 4px; }
    .empty-row { text-align: center; color: var(--text-3); padding: 40px 16px; font-size: .9rem; }
    .hint-box {
        font-size: .8rem; color: var(--text-2); background: var(--primary-dim);
        border: 1px solid #dbe4ff; border-radius: var(--radius-sm); padding: 10px 14px; margin-bottom: 18px;
    }
</style>

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
@if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
@if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        {{ $errors->first() }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="page-header" style="display:flex; align-items:flex-start; justify-content:space-between; gap:16px; flex-wrap:wrap;">
    <div>
        <h2><i class="fas fa-inbox me-2" style="color:var(--brand);font-size:1.1rem;"></i>Scan Inbox</h2>
        <p class="sub">Files scanned into the watched folder land here. Review the proposed split, fill in the details, and file each document.</p>
    </div>
    <div style="display:flex; align-items:center; gap:8px;">
        <button type="button" id="checkNowBtn" class="btn-sm-flat">
            <i class="fas fa-arrows-rotate"></i> Check inbox now
        </button>
        <span id="checkNowMsg" style="font-size:.78rem; color:var(--text-3);"></span>
    </div>
</div>

<div class="hint-box">
    <i class="fas fa-circle-info me-1"></i>
    Drop scanned PDFs into <code>{{ config('scan.inbox_path') }}</code>. They are picked up automatically every few minutes
    (or run <code>php&nbsp;artisan&nbsp;documents:scan-inbox</code>), or click "Check inbox now" above to pick them up
    immediately. A background worker must be running for processing to finish.
</div>

<div class="card-panel">
    <table class="scan-table">
        <thead>
            <tr>
                <th>File</th>
                <th>Status</th>
                <th>Pages</th>
                <th>Documents</th>
                <th>Updated</th>
                <th style="text-align:right;">Actions</th>
            </tr>
        </thead>
        <tbody id="scanRows">
            @forelse ($batches as $batch)
                <tr data-batch-id="{{ $batch->id }}">
                    <td class="scan-name">
                        <i class="fas {{ in_array(strtolower($batch->extension ?? ''), ['jpg','jpeg','png']) ? 'fa-file-image' : 'fa-file-pdf' }}"></i>
                        {{ $batch->original_filename }}
                        <div class="scan-error" data-field="error" style="{{ $batch->error ? '' : 'display:none;' }}">{{ $batch->error }}</div>
                    </td>
                    <td><span class="chip {{ $batch->status }}" data-field="status">{{ str_replace('_', ' ', $batch->status) }}</span></td>
                    <td data-field="page_count">{{ $batch->page_count ?? '—' }}</td>
                    <td data-field="documents">
                        @if ($batch->status === 'filed')
                            {{ $batch->filed_document_count }} filed
                        @elseif ($batch->segment_count)
                            {{ $batch->segment_count }} proposed
                        @else
                            —
                        @endif
                    </td>
                    <td data-field="updated_at" style="color:var(--text-3);font-size:.8rem;">{{ optional($batch->updated_at)->diffForHumans() }}</td>
                    <td>
                        <div class="row-actions">
                            <a class="btn-sm-flat review {{ ($batch->isReadyForReview() || $batch->isFailed()) ? '' : 'is-disabled' }}"
                               data-action="review" href="{{ route('documents.scans.review', $batch) }}">
                                <i class="fas fa-scissors"></i> Review
                            </a>

                            @if ($batch->isFailed())
                                <form method="POST" action="{{ route('documents.scans.reprocess', $batch) }}" style="display:inline;">
                                    @csrf
                                    <button type="submit" class="btn-sm-flat"><i class="fas fa-rotate-right"></i> Reprocess</button>
                                </form>
                            @endif

                            <form method="POST" action="{{ route('documents.scans.destroy', $batch) }}" style="display:inline;"
                                  onsubmit="return confirm('Discard this scan batch? Filed documents are not affected.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-sm-flat danger"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr id="scanEmptyRow"><td colspan="6" class="empty-row"><i class="fas fa-inbox mb-2 d-block" style="font-size:1.6rem;"></i>Nothing in the inbox yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    const statusUrl = "{{ route('documents.scans.status') }}";

    function label(status) { return status.replace(/_/g, ' '); }

    function applyRow(row, b) {
        const chip = row.querySelector('[data-field="status"]');
        if (chip) { chip.className = 'chip ' + b.status; chip.textContent = label(b.status); }

        const pages = row.querySelector('[data-field="page_count"]');
        if (pages && b.page_count) pages.textContent = b.page_count;

        const docs = row.querySelector('[data-field="documents"]');
        if (docs) {
            if (b.status === 'filed') docs.textContent = (b.filed_document_count ?? 0) + ' filed';
            else if (b.segment_count) docs.textContent = b.segment_count + ' proposed';
        }

        const upd = row.querySelector('[data-field="updated_at"]');
        if (upd && b.updated_at) upd.textContent = b.updated_at;

        const reviewLink = row.querySelector('[data-action="review"]');
        if (reviewLink) {
            const canReview = b.status === 'ready_for_review' || b.status === 'failed';
            reviewLink.classList.toggle('is-disabled', !canReview);
        }
    }

    function poll() {
        fetch(statusUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
            .then(r => r.ok ? r.json() : Promise.reject(r.status))
            .then(data => {
                let changedStructural = false;
                (data.batches || []).forEach(b => {
                    const row = document.querySelector('tr[data-batch-id="' + b.id + '"]');
                    if (row) applyRow(row, b);
                    else changedStructural = true; // a brand-new batch appeared
                });
                // A new batch or a failed->reprocess transition changes which
                // action buttons should exist; a full reload keeps it simple.
                if (changedStructural) window.location.reload();
            })
            .catch(() => { /* transient - try again next tick */ });
    }

    setInterval(poll, 3000);

    const checkNowBtn = document.getElementById('checkNowBtn');
    const checkNowMsg = document.getElementById('checkNowMsg');
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    if (checkNowBtn) {
        checkNowBtn.addEventListener('click', function () {
            checkNowBtn.disabled = true;
            const original = checkNowBtn.innerHTML;
            checkNowBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking...';
            checkNowMsg.textContent = '';

            fetch("{{ route('documents.scans.check-now') }}", {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
            })
                .then(r => r.json())
                .then(data => {
                    if (data.ingested > 0) {
                        window.location.reload();
                        return;
                    }
                    checkNowMsg.textContent = data.message || 'No new scans found.';
                    checkNowBtn.disabled = false;
                    checkNowBtn.innerHTML = original;
                })
                .catch(() => {
                    checkNowMsg.textContent = 'Could not reach the server - try again.';
                    checkNowBtn.disabled = false;
                    checkNowBtn.innerHTML = original;
                });
        });
    }
})();
</script>

@endsection
