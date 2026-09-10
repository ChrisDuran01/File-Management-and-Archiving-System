@extends($layout ?? 'Admin.home')
@section('content')

@include('Admin.partials.theme')

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

<style>
    * { box-sizing:border-box; }
    body { background:var(--surface); font-family:'Inter',sans-serif; color:var(--text-1); }

    .rv-header { display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px; margin-bottom:16px; }
    .rv-header h2 { font-size:1.3rem; font-weight:600; margin:0 0 3px; }
    .rv-header .sub { font-size:.83rem; color:var(--text-2); margin:0; }

    .rv-topbar {
        background:var(--card); border:1px solid var(--border); border-radius:var(--radius);
        box-shadow:var(--shadow-sm); padding:14px 18px; margin-bottom:18px;
        display:flex; align-items:end; gap:16px; flex-wrap:wrap;
    }
    .rv-topbar label { display:block; font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:var(--text-3); margin-bottom:6px; }
    .rv-topbar select { border:1.5px solid var(--border); border-radius:var(--radius-sm); padding:9px 12px; font-size:.88rem; min-width:280px; font-family:inherit; }
    .rv-topbar select:focus { border-color:var(--primary); box-shadow:0 0 0 3px var(--primary-dim); outline:none; }
    .rv-spacer { flex:1; }
    .btn-save {
        background:var(--brand); color:#fff; border:none; border-radius:var(--radius-sm);
        padding:11px 22px; font-size:.9rem; font-weight:600; cursor:pointer;
    }
    .btn-save:hover { background:#433aa0; }
    .btn-save[disabled] { opacity:.5; cursor:not-allowed; }
    .btn-cancel { border:1.5px solid var(--border); background:var(--card); color:var(--text-2); border-radius:var(--radius-sm); padding:11px 18px; font-size:.88rem; font-weight:600; text-decoration:none; }

    .rv-grid { display:grid; grid-template-columns:300px 1fr; gap:20px; align-items:start; }
    @media (max-width:900px) { .rv-grid { grid-template-columns:1fr; } }

    .rv-pages {
        background:var(--card); border:1px solid var(--border); border-radius:var(--radius);
        box-shadow:var(--shadow-sm); padding:14px; position:sticky; top:14px; max-height:calc(100vh - 40px); overflow-y:auto;
    }
    .seg-label {
        font-size:.68rem; font-weight:800; text-transform:uppercase; letter-spacing:.06em;
        color:var(--brand); margin:10px 2px 6px;
    }
    .thumb-wrap { position:relative; border:2px solid transparent; border-radius:var(--radius-sm); overflow:hidden; background:#f1f3f9; }
    .thumb-wrap img { width:100%; display:block; }
    .thumb-wrap .pg-num {
        position:absolute; top:6px; left:6px; background:rgba(17,24,39,.78); color:#fff;
        font-size:.68rem; font-weight:700; padding:2px 7px; border-radius:12px;
    }
    .thumb-wrap .pg-exclude {
        position:absolute; top:6px; right:6px; background:rgba(255,255,255,.9); border:none;
        width:24px; height:24px; border-radius:50%; cursor:pointer; font-size:.72rem; color:var(--text-2);
    }
    .thumb-wrap.excluded { opacity:.45; }
    .thumb-wrap.excluded::after {
        content:'EXCLUDED'; position:absolute; inset:0; display:flex; align-items:center; justify-content:center;
        font-size:.72rem; font-weight:800; letter-spacing:.08em; color:#b91c1c; background:rgba(255,255,255,.35);
    }
    .thumb-wrap.seg-0 { border-color:#058028; } .thumb-wrap.seg-1 { border-color:#058028; }
    .thumb-wrap.seg-2 { border-color:#0d9488; } .thumb-wrap.seg-3 { border-color:#d97706; }
    .thumb-wrap.seg-4 { border-color:#db2777; } .thumb-wrap.seg-5 { border-color:#65a30d; }

    .gap {
        display:flex; align-items:center; gap:8px; padding:5px 2px; margin:2px 0;
    }
    .gap .line { flex:1; height:1px; background:var(--border); }
    .gap button {
        border:1.5px solid var(--border); background:var(--card); color:var(--text-3);
        border-radius:20px; font-size:.7rem; font-weight:700; padding:3px 10px; cursor:pointer;
    }
    .gap.is-split button { border-color:var(--brand); color:var(--brand); background:#ede9fe; }

    .seg-card {
        background:var(--card); border:1px solid var(--border); border-radius:var(--radius);
        box-shadow:var(--shadow-sm); padding:16px 18px; margin-bottom:16px;
    }
    .seg-card h3 { font-size:.9rem; font-weight:700; margin:0 0 2px; }
    .seg-card .rng { font-size:.75rem; color:var(--text-3); margin-bottom:12px; }
    .seg-fields { display:grid; grid-template-columns:1fr 1fr; gap:12px 14px; }
    .seg-fields .full { grid-column:1 / -1; }
    .seg-fields label { display:block; font-size:.74rem; font-weight:600; color:var(--text-2); margin-bottom:5px; }
    .seg-fields input, .seg-fields select, .seg-fields textarea {
        width:100%; border:1.5px solid var(--border); border-radius:var(--radius-sm);
        padding:8px 10px; font-size:.85rem; font-family:inherit;
    }
    .seg-fields input:focus, .seg-fields select:focus, .seg-fields textarea:focus {
        border-color:var(--primary); box-shadow:0 0 0 3px var(--primary-dim); outline:none;
    }
    .seg-fields textarea { resize:vertical; min-height:56px; }

    .rv-alert { border-radius:var(--radius-sm); padding:12px 16px; font-size:.86rem; margin-bottom:16px; }
    .rv-alert.err { background:#fee2e2; color:#b91c1c; }
    .fail-panel { background:var(--card); border:1px solid var(--border); border-radius:var(--radius); box-shadow:var(--shadow-sm); padding:24px; }
    .fail-panel code { background:#f1f3f9; padding:2px 6px; border-radius:4px; font-size:.82rem; }

    /* ===== DARK MODE ===== */
    [data-theme="dark"] .thumb-wrap { background:var(--surface); }
    [data-theme="dark"] .thumb-wrap .pg-exclude { background:rgba(0,0,0,.55); color:var(--text-1); }
    [data-theme="dark"] .thumb-wrap.excluded::after { background:rgba(0,0,0,.55); color:#fca5a5; }
    [data-theme="dark"] .gap.is-split button { background:var(--primary-dim); }
    [data-theme="dark"] .rv-alert.err { background:var(--danger-dim); color:#fca5a5; }
    [data-theme="dark"] .fail-panel code { background:var(--surface); }
</style>

@if ($errors->any())
    <div class="rv-alert err">{{ $errors->first() }}</div>
@endif

<div class="rv-header">
    <div>
        <h2><i class="fas fa-scissors me-2" style="color:var(--brand);"></i>Review &amp; File — batch #{{ $batch->id }}</h2>
        <p class="sub">{{ $batch->original_filename }} &middot; {{ $batch->page_count ?? '?' }} page(s)
            @if($batch->detected_separators && $batch->detected_separators !== 'none')
                &middot; split proposed from <strong>{{ $batch->detected_separators }}</strong> separators
            @endif
        </p>
    </div>
    <a href="{{ route('documents.scans.index') }}" class="btn-cancel">Back to inbox</a>
</div>

@if ($batch->isFailed())
    <div class="fail-panel">
        <p style="font-weight:700;color:#b91c1c;margin-bottom:6px;"><i class="fas fa-triangle-exclamation me-1"></i> Processing failed</p>
        <p style="color:var(--text-2);font-size:.88rem;">{{ $batch->error ?: 'No detail was recorded.' }}</p>
        <form method="POST" action="{{ route('documents.scans.reprocess', $batch) }}" style="margin-top:14px;">
            @csrf
            <button type="submit" class="btn-save"><i class="fas fa-rotate-right me-1"></i> Try again</button>
        </form>
    </div>
@else

<form id="reviewForm" onsubmit="return false;">
    <div class="rv-topbar">
        <div>
            <label>File into folder</label>
            <select id="targetFolder">
                <option value="">— choose a folder —</option>
                @foreach ($folders as $folder)
                    <option value="{{ $folder->id }}">{{ $folder->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="rv-spacer"></div>
        <a href="{{ route('documents.scans.index') }}" class="btn-cancel">Cancel</a>
        <button type="button" id="saveBtn" class="btn-save">Save <span id="saveCount"></span></button>
    </div>

    <div id="formAlert" class="rv-alert err" style="display:none;"></div>

    <div class="rv-grid">
        <div class="rv-pages" id="pagesCol"></div>
        <div id="segmentsCol"></div>
    </div>
</form>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    const CSRF = "{{ csrf_token() }}";
    const FILE_URL = "{{ route('documents.scans.file', $batch->id) }}";
    const INDEX_URL = "{{ route('documents.scans.index') }}";
    const PAGE_URL = (n) => "{{ route('documents.scans.page', [$batch->id, '__P__']) }}".replace('__P__', n);
    const TODAY = "{{ now()->toDateString() }}";

    const PAGE_COUNT = {{ (int) ($batch->page_count ?: 1) }};
    const RAW_SEGMENTS = @json(array_values($segments));
    const DROPPED = @json(array_values($batch->dropped_pages ?? []));
    const DOC_TYPES = @json($documentTypes);
    const STATUSES = @json($statuses);

    // ---- state -------------------------------------------------------------
    const included = {};
    for (let p = 1; p <= PAGE_COUNT; p++) included[p] = DROPPED.indexOf(p) === -1;

    // A split occurs *after* these page numbers. Seed from the proposed
    // segments: a boundary after every segment end except the last.
    const splitAfter = new Set();
    RAW_SEGMENTS.forEach((s, i) => { if (i < RAW_SEGMENTS.length - 1) splitAfter.add(s.end_page); });

    // Per-visible-segment entered values, kept by segment index across re-renders.
    const segData = {};

    function guessFor(startPage) {
        const match = RAW_SEGMENTS.find(s => s.start_page === startPage);
        return (match && match.guessed) ? match.guessed : {};
    }

    function computeSegments() {
        const segs = [];
        let cur = null;
        for (let p = 1; p <= PAGE_COUNT; p++) {
            if (!included[p]) { if (cur) { segs.push(cur); cur = null; } continue; }
            if (!cur) cur = { start: p, end: p };
            else cur.end = p;
            if (splitAfter.has(p)) { segs.push(cur); cur = null; }
        }
        if (cur) segs.push(cur);
        return segs;
    }

    // ---- rendering -------------------------------------------------------
    const pagesCol = document.getElementById('pagesCol');
    const segmentsCol = document.getElementById('segmentsCol');
    const saveCount = document.getElementById('saveCount');

    function renderPages(segments) {
        const segOf = {};
        segments.forEach((s, idx) => { for (let p = s.start; p <= s.end; p++) segOf[p] = idx; });

        pagesCol.innerHTML = '';
        for (let p = 1; p <= PAGE_COUNT; p++) {
            if (segOf[p] !== undefined && (p === 1 || segOf[p - 1] !== segOf[p])) {
                const lbl = document.createElement('div');
                lbl.className = 'seg-label';
                lbl.textContent = 'Segment ' + (segOf[p] + 1);
                pagesCol.appendChild(lbl);
            }

            const wrap = document.createElement('div');
            wrap.className = 'thumb-wrap' + (included[p] ? (' seg-' + ((segOf[p] ?? 0) % 6)) : ' excluded');
            wrap.innerHTML =
                '<span class="pg-num">p' + p + '</span>' +
                '<button type="button" class="pg-exclude" title="' + (included[p] ? 'Exclude this page' : 'Include this page') + '">' +
                    '<i class="fas ' + (included[p] ? 'fa-eye' : 'fa-eye-slash') + '"></i></button>' +
                '<img loading="lazy" src="' + PAGE_URL(p) + '" alt="page ' + p + '">';
            wrap.querySelector('.pg-exclude').addEventListener('click', () => { included[p] = !included[p]; render(); });
            pagesCol.appendChild(wrap);

            if (p < PAGE_COUNT) {
                const gap = document.createElement('div');
                gap.className = 'gap' + (splitAfter.has(p) ? ' is-split' : '');
                gap.innerHTML = '<span class="line"></span><button type="button"><i class="fas fa-scissors"></i> ' +
                    (splitAfter.has(p) ? 'split' : 'join') + '</button><span class="line"></span>';
                gap.querySelector('button').addEventListener('click', () => {
                    if (splitAfter.has(p)) splitAfter.delete(p); else splitAfter.add(p);
                    render();
                });
                pagesCol.appendChild(gap);
            }
        }
    }

    function fieldsFromDom(idx) {
        const card = segmentsCol.querySelector('[data-seg-index="' + idx + '"]');
        if (!card) return;
        segData[idx] = {
            title:         card.querySelector('[name="title"]').value,
            document_type: card.querySelector('[name="document_type"]').value,
            reference_no:  card.querySelector('[name="reference_no"]').value,
            document_date: card.querySelector('[name="document_date"]').value,
            date_received: card.querySelector('[name="date_received"]').value,
            sender:        card.querySelector('[name="sender"]').value,
            recipient:     card.querySelector('[name="recipient"]').value,
            status:        card.querySelector('[name="status"]').value,
            notes:         card.querySelector('[name="notes"]').value,
        };
    }

    function captureAll() {
        segmentsCol.querySelectorAll('[data-seg-index]').forEach(c => fieldsFromDom(parseInt(c.dataset.segIndex, 10)));
    }

    function opt(list, val) {
        return list.map(o => '<option value="' + o + '"' + (o === val ? ' selected' : '') + '>' + o + '</option>').join('');
    }

    function renderSegments(segments) {
        segmentsCol.innerHTML = '';
        saveCount.textContent = '(' + segments.length + ' document' + (segments.length === 1 ? '' : 's') + ')';

        segments.forEach((s, idx) => {
            const g = segData[idx] || guessFor(s.start);
            const card = document.createElement('div');
            card.className = 'seg-card';
            card.dataset.segIndex = idx;
            card.innerHTML =
                '<h3>Document ' + (idx + 1) + '</h3>' +
                '<div class="rng">pages ' + s.start + (s.end > s.start ? '–' + s.end : '') + '</div>' +
                '<div class="seg-fields">' +
                    '<div class="full"><label>Title / subject *</label><input name="title" value="' + esc(g.title || '') + '"></div>' +
                    '<div><label>Document type</label><select name="document_type"><option value="">—</option>' + opt(DOC_TYPES, g.document_type || '') + '</select></div>' +
                    '<div><label>Reference no.</label><input name="reference_no" value="' + esc(g.reference_no || '') + '"></div>' +
                    '<div><label>Document date</label><input type="date" name="document_date" value="' + esc(g.document_date || '') + '"></div>' +
                    '<div><label>Date received *</label><input type="date" name="date_received" value="' + esc(g.date_received || TODAY) + '"></div>' +
                    '<div><label>From / sender</label><input name="sender" value="' + esc(g.sender || '') + '"></div>' +
                    '<div><label>To / recipient</label><input name="recipient" value="' + esc(g.recipient || '') + '"></div>' +
                    '<div><label>Status</label><select name="status">' + opt(STATUSES, g.status || 'filed') + '</select></div>' +
                    '<div class="full"><label>Notes</label><textarea name="notes">' + esc(g.notes || '') + '</textarea></div>' +
                '</div>';
            card.querySelectorAll('input,select,textarea').forEach(el =>
                el.addEventListener('input', () => fieldsFromDom(idx)));
            segmentsCol.appendChild(card);
        });
    }

    function esc(v) { return String(v).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }

    function render() {
        captureAll();
        const segments = computeSegments();
        renderPages(segments);
        renderSegments(segments);
    }

    // ---- save ----------------------------------------------------------
    const alertBox = document.getElementById('formAlert');
    const saveBtn = document.getElementById('saveBtn');

    saveBtn.addEventListener('click', () => {
        captureAll();
        alertBox.style.display = 'none';

        const folderId = document.getElementById('targetFolder').value;
        if (!folderId) { showAlert('Choose a folder to file into.'); return; }

        const segments = computeSegments();
        if (!segments.length) { showAlert('Nothing to file — every page is excluded.'); return; }

        const payload = {
            target_folder_id: folderId,
            segments: segments.map((s, idx) => {
                const d = segData[idx] || {};
                return {
                    start_page: s.start, end_page: s.end,
                    title: d.title || '', document_type: d.document_type || '',
                    reference_no: d.reference_no || '', document_date: d.document_date || '',
                    date_received: d.date_received || TODAY,
                    sender: d.sender || '', recipient: d.recipient || '',
                    status: d.status || 'filed', notes: d.notes || '',
                };
            }),
        };

        saveBtn.disabled = true;
        saveBtn.textContent = 'Filing…';

        fetch(FILE_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': CSRF,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payload),
        })
        .then(async r => {
            const data = await r.json().catch(() => ({}));
            if (r.ok && data.redirect) { window.location = data.redirect; return; }
            if (r.status === 422) {
                const first = data.errors ? Object.values(data.errors)[0][0] : (data.message || 'Please check the form.');
                showAlert(first);
            } else {
                showAlert(data.message || 'Something went wrong filing this batch.');
            }
            saveBtn.disabled = false;
            saveBtn.textContent = 'Save ';
            render();
        })
        .catch(() => {
            showAlert('Network error — nothing was filed. Try again.');
            saveBtn.disabled = false; saveBtn.textContent = 'Save ';
            render();
        });
    });

    function showAlert(msg) { alertBox.textContent = msg; alertBox.style.display = 'block'; window.scrollTo({ top: 0, behavior: 'smooth' }); }

    render();
})();
</script>
@endif

@endsection
