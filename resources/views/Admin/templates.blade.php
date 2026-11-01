@extends($layout ?? 'Admin.home')
@section('title', 'Document Templates')
@section('content')

@include('Admin.partials.theme')

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

@include('Admin.proposals.partials.styles')

<style>
    .card-panel {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        box-shadow: var(--shadow-sm);
        padding: 20px 22px;
        margin-bottom: 20px;
    }
    .panel-label {
        font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .07em;
        color: var(--text-3); margin-bottom: 14px; display: flex; align-items: center; gap: 6px;
    }
    .panel-label::after { content: ''; flex: 1; height: 1px; background: var(--border); }

    /* Which template */
    .tpl-tabs { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px; }
    .tpl-tab {
        border: 1.5px solid var(--border); background: var(--card); color: var(--text-2);
        padding: 8px 16px; border-radius: var(--radius-sm); font-size: .85rem; font-weight: 600; cursor: pointer;
    }
    .tpl-tab:hover { border-color: var(--primary); color: var(--primary); }
    .tpl-tab[aria-pressed="true"] { background: var(--primary-fill); border-color: var(--primary-fill); color: #fff; }

    /* Form */
    .tpl-fieldset { border: 0; padding: 0; margin: 0; min-width: 0; }
    .tpl-section { font-size: .9rem; font-weight: 700; color: var(--text-1); margin: 22px 0 10px; }
    .tpl-section:first-child { margin-top: 0; }
    .tpl-form textarea.cell-text { min-height: 38px; }
    .tpl-form .sig-group h4 { font-size: .8rem; font-weight: 700; color: var(--text-2); margin: 14px 0 4px; }
    .tpl-form .sig-table { min-width: 460px; margin-bottom: 6px; }
    .clause-list { padding-left: 26px; }
    .clause-lead { font-size: .7rem; font-weight: 700; letter-spacing: .05em; color: var(--text-3); margin-bottom: 3px; }
    .tpl-check { display: flex; gap: 8px; align-items: flex-start; font-size: .84rem; color: var(--text-2); margin: 0 0 6px; cursor: pointer; }
    .tpl-check input { margin-top: 3px; }
    .field-group { margin-bottom: 14px; }
    .field-group label { display: block; font-size: .74rem; font-weight: 600; color: var(--text-2); margin-bottom: 5px; }
    .field-hint { font-size: .76rem; color: var(--text-3); margin-top: 4px; }
    .tpl-actions { display: flex; flex-wrap: wrap; gap: 10px; justify-content: flex-end; margin-bottom: 24px; }
    .tpl-notice {
        border: 1px solid var(--border); border-left: 4px solid var(--primary); background: var(--card);
        border-radius: var(--radius-sm); padding: 12px 16px; margin-bottom: 16px; font-size: .86rem;
    }

    /* Preview: a sheet of paper in both themes */
    .preview-panel { position: sticky; top: 16px; }
    .preview-box {
        border: 1px solid var(--border); border-radius: var(--radius-sm); background: #fff;
        padding: 28px 30px; font-size: 12.5px; min-height: 200px;
        max-height: calc(100vh - 150px); overflow: auto; word-break: break-word;
    }
    @include('Admin.templates.documents.styles')

    /* Records table */
    .records-table { width: 100%; border-collapse: collapse; font-size: .85rem; }
    .records-table th {
        text-align: left; font-size: .72rem; text-transform: uppercase; letter-spacing: .05em;
        color: var(--text-3); font-weight: 600; padding: 8px 10px; border-bottom: 1.5px solid var(--border);
    }
    .records-table td { padding: 10px; border-bottom: 1px solid var(--border); vertical-align: top; }
    .records-table tr:last-child td { border-bottom: none; }
    .records-table .actions { text-align: right; white-space: nowrap; }
    .records-table .actions a { margin-left: 6px; text-decoration: none; }
    .empty-row { text-align: center; color: var(--text-3); padding: 24px; font-size: .85rem; }

    @media (max-width: 991px) {
        .preview-panel { position: static; }
        .preview-box { max-height: none; }
    }
</style>

{{-- Skeleton placeholder, shown until the real content below is revealed --}}
<div class="skel-shell" aria-hidden="true">
    <div class="page-header">
        <div>
            <div class="skel skel-text" style="width:220px; height:22px; margin-bottom:8px;"></div>
            <div class="skel skel-text" style="width:340px;"></div>
        </div>
        <div class="skel" style="width:150px; height:34px; border-radius:8px;"></div>
    </div>

    <div style="display:flex; gap:8px; margin-bottom:16px;">
        @for ($i = 0; $i < 2; $i++)
        <div class="skel" style="width:120px; height:36px; border-radius:8px;"></div>
        @endfor
    </div>

    <div class="row">
        <div class="col-lg-7">
            <div class="card-panel">
                @for ($i = 0; $i < 5; $i++)
                <div class="field-group">
                    <div class="skel skel-text" style="width:100px; margin-bottom:8px;"></div>
                    <div class="skel" style="width:100%; height:38px; border-radius:6px;"></div>
                </div>
                @endfor
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card-panel">
                <div class="skel skel-text" style="width:140px; margin-bottom:14px;"></div>
                <div class="skel" style="width:100%; height:360px; border-radius:6px;"></div>
            </div>
        </div>
    </div>
</div>

<div class="skel-real">

@php
    $categoryLabels = [
        'resolution' => 'Resolution',
        'request_letter' => 'Request letter',
        'memorandum' => 'Memorandum',
    ];
    $user = auth()->user();
    $shown = fn ($file) => $file->template_fields && $file->template
        && ! in_array($file->template->category, \App\Models\Template::HIDDEN_CATEGORIES, true);
@endphp

<div class="page-header">
    <div>
        <h1><i class="fas fa-file-signature me-2" style="color:var(--primary);font-size:1.1rem;"></i>Document Templates</h1>
        <p class="sub">Fill in a template to make a PDF. It is saved in the folder you pick and listed under "Saved to records".</p>
    </div>
    <a href="{{ route('letterheads.index') }}" class="btn-flat ghost" style="text-decoration:none;">
        <i class="fas fa-stamp me-1" aria-hidden="true"></i> Manage letterheads
    </a>
</div>

@if ($editing)
    <div class="tpl-notice">
        Editing <b>{{ $editing->filename }}</b>. Saving puts the new PDF in its place, in the same folder.
        <a href="{{ route('templates.index') }}">Stop editing</a>
    </div>
@elseif ($copying)
    <div class="tpl-notice">
        Starting from a copy of <b>{{ $copying->filename }}</b>. Saving makes a new document; the old one stays as it is.
    </div>
@endif

@if ($templates->isEmpty())
    <div class="card-panel empty-row">No templates yet.</div>
@else

<form id="generateForm" class="tpl-form" action="{{ route('templates.generate') }}" method="POST"
      data-preview-url="{{ route('templates.preview') }}"
      data-start-month="{{ \App\Support\SchoolYear::startMonth() }}">
    @csrf
    <input type="hidden" name="template_id" id="templateIdInput" value="{{ $activeId }}">
    @if ($editing)
        <input type="hidden" name="replace_file_id" value="{{ $editing->id }}">
    @endif

    @unless ($editing)
        <div class="tpl-tabs" role="group" aria-label="Choose a template">
            @foreach ($templates as $template)
                <button type="button" class="tpl-tab" data-template-id="{{ $template->id }}"
                        aria-pressed="{{ $template->id === $activeId ? 'true' : 'false' }}">
                    {{ $categoryLabels[$template->category] ?? ucfirst($template->category) }}
                </button>
            @endforeach
        </div>
    @endunless

    <div class="row">
        <div class="col-lg-7">
            @foreach ($templates as $template)
                @php
                    $form = $forms[$template->id];
                    $p = 'tpl'.$template->id.'-';
                    $active = $template->id === $activeId;
                    $doc = $template->documentClass();
                @endphp
                {{-- Only the chosen template's fieldset is enabled, so only its fields are sent. --}}
                <fieldset class="tpl-fieldset" data-template-form="{{ $template->id }}" @disabled(! $active) @if (! $active) hidden @endif>
                    <legend class="sr-only">{{ $categoryLabels[$template->category] ?? ucfirst($template->category) }}</legend>

                    <div class="card-panel">
                        @include($doc ? $doc::form() : 'Admin.templates.forms.plain', ['template' => $template, 'form' => $form, 'p' => $p])
                    </div>

                    <div class="card-panel">
                        <div class="prop-fields">
                            <div>
                                <label for="{{ $p }}letterhead">Letterhead</label>
                                <select id="{{ $p }}letterhead" name="letterhead_id" class="prop-input form-select">
                                    <option value="">No letterhead (plain page)</option>
                                    @foreach ($letterheads as $lh)
                                        <option value="{{ $lh->id }}" @selected((int) $form['letterhead_id'] === $lh->id)>{{ $lh->name }}{{ $lh->is_default ? ' (default)' : '' }}</option>
                                    @endforeach
                                </select>
                                @if ($letterheads->isEmpty())
                                    <div class="field-hint">No letterheads saved yet. <a href="{{ route('letterheads.index') }}">Add one</a>.</div>
                                @endif
                            </div>
                            <div>
                                <label for="{{ $p }}folder">Save to folder</label>
                                <select id="{{ $p }}folder" name="folder_id" class="prop-input form-select" data-folder-select
                                        @if ($form['folder_id']) data-touched="1" @endif>
                                    <option value="">Choose a folder</option>
                                    @foreach ($folders as $folder)
                                        <option value="{{ $folder->id }}" @selected((int) $form['folder_id'] === $folder->id)>{{ $folder->name }}</option>
                                    @endforeach
                                </select>
                                <div class="field-hint">Picked from the title when a folder name is in it. Change it if it's wrong.</div>
                            </div>
                        </div>
                    </div>
                </fieldset>
            @endforeach

            <div class="tpl-actions">
                @if ($editing)
                    <a href="{{ route('templates.index') }}" class="btn-flat ghost" style="text-decoration:none;">Cancel</a>
                @endif
                <button type="submit" class="btn-flat ghost" formaction="{{ route('templates.preview-pdf') }}" formtarget="_blank" formnovalidate>
                    <i class="fas fa-file-pdf me-1" aria-hidden="true"></i> Preview PDF<span class="sr-only"> (opens in a new tab)</span>
                </button>
                <button type="submit" class="btn-flat">
                    <i class="fas fa-save me-1" aria-hidden="true"></i> {{ $editing ? 'Save changes' : 'Save document' }}
                </button>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card-panel preview-panel">
                <div class="panel-label"><i class="fas fa-eye" aria-hidden="true"></i> Preview</div>
                <div class="preview-box doc-sheet" id="previewBox">{!! $initialPreview !!}</div>
            </div>
        </div>
    </div>
</form>
@endif

<div class="card-panel">
    <div class="panel-label"><i class="fas fa-list" aria-hidden="true"></i> Saved to records ({{ $recent->count() }})</div>

    @if($recent->isEmpty())
        <div class="empty-row">No documents made yet.</div>
    @else
        <div class="table-responsive">
            <table class="records-table">
                <thead>
                    <tr><th>Type</th><th>File</th><th>Folder</th><th>Date</th><th><span class="sr-only">Actions</span></th></tr>
                </thead>
                <tbody>
                    @foreach($recent as $file)
                        <tr>
                            <td><span class="chip">{{ $categoryLabels[$file->template->category ?? ''] ?? '—' }}</span></td>
                            <td>
                                <a href="{{ route('files.preview', $file->id) }}" style="color:var(--text-1); text-decoration:none; font-weight:500;">
                                    {{ $file->filename }}
                                </a>
                                @include('Admin.partials.approval-badge', ['file' => $file])
                            </td>
                            <td>{{ $file->folder->name ?? 'Root' }}</td>
                            <td style="white-space:nowrap;">{{ $file->created_at->format('M d, Y') }}</td>
                            <td class="actions">
                                @if ($shown($file))
                                    @if (! $file->is_archived && ! $file->folder?->is_archived && $file->isManageableBy($user))
                                        <a href="{{ route('templates.index', ['edit' => $file->id]) }}" class="btn-flat ghost btn-sm">
                                            Edit<span class="sr-only"> {{ $file->filename }}</span>
                                        </a>
                                    @endif
                                    <a href="{{ route('templates.index', ['copy' => $file->id]) }}" class="btn-flat ghost btn-sm">
                                        Copy<span class="sr-only"> {{ $file->filename }}</span>
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

</div>

<script>
(function () {
    var form = document.getElementById('generateForm');
    if (!form) return;

    var templateIdInput = document.getElementById('templateIdInput');
    var previewBox = document.getElementById('previewBox');
    var token = form.querySelector('input[name="_token"]').value;
    var startMonth = parseInt(form.getAttribute('data-start-month'), 10) || 8;
    var folderNames = {!! json_encode($folders->pluck('name', 'id')) !!};

    // New rows get keys no existing row uses; the server only cares that
    // each row's fields share one.
    var nextKey = Date.now();

    function activeFieldset() {
        return form.querySelector('[data-template-form="' + templateIdInput.value + '"]');
    }

    // Boxes grow with what is typed in them (only measurable once shown).
    function fit(area) {
        area.style.height = 'auto';
        area.style.height = area.scrollHeight + 2 + 'px';
    }
    function fitAll(root) {
        root.querySelectorAll('textarea.cell-text').forEach(fit);
    }

    // Screen readers hear "WHEREAS clause 3", so the numbers follow the
    // rows as they are added and removed. RESOLVED rows also show the
    // lead word they will be printed with.
    function renumber(list) {
        var rows = Array.prototype.slice.call(list.children);
        rows.forEach(function (row, i) {
            row.querySelectorAll('[data-label]').forEach(function (el) {
                el.setAttribute('aria-label', el.getAttribute('data-label') + ' ' + (i + 1));
            });
            var lead = row.querySelector('[data-lead]');
            if (lead) {
                lead.textContent = i === 0 ? 'RESOLVED'
                    : (i === rows.length - 1 && rows.length >= 3 ? 'RESOLVED FINALLY' : 'RESOLVED FURTHER');
            }
        });
    }

    function addRow(kind, after) {
        var list = document.getElementById(kind + 'List');
        var html = document.getElementById(kind + 'Tpl').innerHTML.replace(/__KEY__/g, String(nextKey++));
        if (after) {
            after.insertAdjacentHTML('afterend', html);
        } else {
            list.insertAdjacentHTML('beforeend', html);
        }
        renumber(list);
        return after ? after.nextElementSibling : list.lastElementChild;
    }

    // ---- Live preview, drawn by the server from the same view as the PDF ----
    var previewTimer = null;
    var previewRequest = null;

    function refreshPreview() {
        clearTimeout(previewTimer);
        previewTimer = setTimeout(function () {
            var fieldset = activeFieldset();
            if (!fieldset) return;

            var data = new FormData();
            data.append('_token', token);
            data.append('template_id', templateIdInput.value);
            fieldset.querySelectorAll('[name^="fields["]').forEach(function (el) {
                if (el.type === 'checkbox' && !el.checked) return;
                data.append(el.name, el.value);
            });

            if (previewRequest) previewRequest.abort();
            previewRequest = new AbortController();

            fetch(form.getAttribute('data-preview-url'), {
                method: 'POST',
                body: data,
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                signal: previewRequest.signal
            })
                .then(function (response) { return response.ok ? response.json() : null; })
                .then(function (json) { if (json) previewBox.innerHTML = json.html; })
                .catch(function () {});
        }, 350);
    }

    // ---- Resolution number: follows the adoption date until typed over ----
    function schoolYearCode(date) {
        var year = parseInt(date.slice(0, 4), 10);
        var start = parseInt(date.slice(5, 7), 10) >= startMonth ? year : year - 1;
        return String(start).slice(2) + String(start + 1).slice(2);
    }

    function suggestNumber(input, date) {
        var sequences = JSON.parse(input.getAttribute('data-sequences') || '{}');
        var code = schoolYearCode(date);
        var next = String((sequences[code] || 0) + 1);
        while (next.length < 3) next = '0' + next;
        return date.slice(5, 7) + '-' + date.slice(8, 10) + '-' + date.slice(2, 4) + ' (' + code + '-' + next + ')';
    }

    // ---- Folder suggestion: the longest folder name found in the title ----
    function suggestFolder(fieldset) {
        var select = fieldset.querySelector('[data-folder-select]');
        var titleField = fieldset.querySelector('[data-title-field]');
        if (!select || !titleField || select.getAttribute('data-touched') === '1') return;

        var needle = titleField.value.toLowerCase();
        if (!needle) return;

        var best = null;
        Object.keys(folderNames).forEach(function (id) {
            var name = folderNames[id];
            if (needle.indexOf(name.toLowerCase()) !== -1 && (!best || name.length > folderNames[best].length)) {
                best = id;
            }
        });
        if (best) select.value = best;
    }

    // ---- Pasting a whole drafted resolution into a clause box ----
    var LEADS = {
        whereas: /^WHEREAS\b[,:;]?\s*/i,
        resolved: /^(NOW,?\s+THEREFORE,?\s+BE\s+IT\s+)?RESOLVED(\s+(FURTHER|FINALLY))?\b[,:;]?\s*/i
    };

    function pasteClauses(area, text) {
        var parts = text.split(/\r?\n\s*\r?\n|\r?\n(?=\s*(?:WHEREAS|WHEREFORE|RESOLVED|NOW,?\s+THEREFORE)\b)/i)
            .map(function (part) { return part.replace(/\s*\r?\n\s*/g, ' ').trim(); })
            .filter(Boolean);
        if (parts.length < 2) return false;

        var fieldset = area.closest('fieldset');
        var p = 'tpl' + fieldset.getAttribute('data-template-form') + '-';
        var row = area.closest('[data-row]');
        var kind = area.closest('[data-clause-list]').getAttribute('data-clause-list');
        var first = true;

        parts.forEach(function (part) {
            var target = kind;
            if (LEADS.resolved.test(part)) target = 'resolved';
            else if (LEADS.whereas.test(part)) target = 'whereas';
            else if (/^WHEREFORE\b/i.test(part)) {
                // "WHEREFORE, upon the motion of X and unanimously seconded by ..., be it;"
                var m = part.match(/upon the motion of\s+(.+)\s+and\s+((?:\w+\s+)?seconded.*?)(?:,?\s*be it;?)?$/i);
                if (m) {
                    fieldset.querySelector('[data-moved-by]').value = m[1].replace(/,$/, '');
                    fieldset.querySelector('[data-seconded]').value = m[2];
                    return;
                }
            }
            var clause = part.replace(LEADS[target], '');

            if (first && target === kind) {
                area.value = (area.value.trim() ? area.value.trim() + ' ' : '') + clause;
                first = false;
                return;
            }
            var list = document.getElementById(p + target + 'List');
            var empty = Array.prototype.find.call(list.querySelectorAll('textarea'), function (t) { return !t.value.trim() && t !== area; });
            var box = empty || addRow(p + target, target === kind ? row : null).querySelector('textarea');
            box.value = clause;
            if (target === kind) row = box.closest('[data-row]');
        });

        fitAll(fieldset);
        return true;
    }

    form.addEventListener('paste', function (e) {
        var area = e.target.closest('[data-clause-list] textarea');
        if (!area) return;
        var text = (e.clipboardData || window.clipboardData).getData('text');
        if (pasteClauses(area, text)) {
            e.preventDefault();
            refreshPreview();
        }
    });

    form.addEventListener('click', function (e) {
        var tab = e.target.closest('.tpl-tab');
        if (tab) {
            form.querySelectorAll('.tpl-tab').forEach(function (b) { b.setAttribute('aria-pressed', b === tab ? 'true' : 'false'); });
            templateIdInput.value = tab.getAttribute('data-template-id');
            form.querySelectorAll('[data-template-form]').forEach(function (fs) {
                var on = fs.getAttribute('data-template-form') === templateIdInput.value;
                fs.disabled = !on;
                fs.hidden = !on;
            });
            fitAll(activeFieldset());
            refreshPreview();
            return;
        }

        var add = e.target.closest('[data-add-row]');
        if (add) {
            var newRow = addRow(add.getAttribute('data-add-row'));
            var first = newRow.querySelector('input, textarea');
            if (first) first.focus();
            refreshPreview();
            return;
        }

        var remove = e.target.closest('[data-remove-row]');
        if (remove) {
            var row = remove.closest('[data-row]');
            var rows = row.parentNode;
            var kind = rows.id.replace(/List$/, '');
            row.remove();
            renumber(rows);
            var addButton = form.querySelector('[data-add-row="' + kind + '"]');
            if (addButton) addButton.focus();
            refreshPreview();
        }
    });

    form.addEventListener('input', function (e) {
        var el = e.target;
        if (el.matches('textarea.cell-text')) fit(el);
        if (el.matches('[data-res-number]')) el.setAttribute('data-typed', el.value.trim() ? '1' : '0');
        if (el.matches('[data-adopted-on]') && el.value) {
            var number = el.closest('fieldset').querySelector('[data-res-number]');
            if (number && number.getAttribute('data-typed') !== '1') number.value = suggestNumber(number, el.value);
        }
        if (el.matches('[data-title-field]')) suggestFolder(el.closest('fieldset'));
        if (el.name && el.name.indexOf('fields[') === 0) refreshPreview();
    });

    form.addEventListener('change', function (e) {
        if (e.target.matches('[data-folder-select]')) e.target.setAttribute('data-touched', '1');
        if (e.target.matches('input[type="checkbox"], input[type="date"]')) refreshPreview();
    });

    // The page is shown only after the loading skeleton (Admin/partials/theme),
    // so the boxes are measured once the form actually has a size.
    var measured = false;
    new ResizeObserver(function (entries) {
        if (!measured && entries[0].contentRect.height > 0) {
            measured = true;
            fitAll(activeFieldset());
        }
    }).observe(form);
})();
</script>

@endsection
