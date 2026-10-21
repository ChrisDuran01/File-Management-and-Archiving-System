@extends($layout ?? 'Admin.home')
@section('title', 'Document Templates')
@section('content')

@include('Admin.partials.theme')

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

<style>
    * { box-sizing: border-box; }
    body { background: var(--surface); font-family: 'Inter', sans-serif; color: var(--text-1); }

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

    /* Category pills */
    .pill-row { display: flex; gap: 8px; flex-wrap: wrap; }
    .pill-btn {
        border: 1.5px solid var(--border); background: var(--card); color: var(--text-2);
        padding: 9px 18px; border-radius: 30px; font-size: .84rem; font-weight: 600;
        cursor: pointer; transition: all .15s;
    }
    .pill-btn:hover { border-color: var(--primary); color: var(--primary); }
    .pill-btn.active { background: var(--primary-fill); border-color: var(--primary-fill); color: #fff; }

    /* Form fields */
    .field-group { margin-bottom: 16px; }
    .field-group label {
        display: block; font-size: .78rem; font-weight: 600; color: var(--text-2); margin-bottom: 6px;
    }
    .field-group .form-control, .field-group select {
        border: 1.5px solid var(--border); border-radius: var(--radius-sm); padding: 9px 12px;
        font-size: .88rem; font-family: 'Inter', sans-serif;
    }
    .field-group .form-control:focus, .field-group select:focus {
        border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-dim); outline: none;
    }
    .field-hint { font-size: .74rem; color: var(--text-3); margin-top: 4px; }

    .btn-generate {
        width: 100%; background: #0f172a; color: #fff; border: none; border-radius: var(--radius-sm);
        padding: 12px; font-size: .9rem; font-weight: 600; cursor: pointer; transition: background .15s;
    }
    .btn-generate:hover { background: #1e293b; }

    /* Preview */
    .preview-box {
        border: 1.5px dashed var(--border); border-radius: var(--radius-sm); background: #fcfcfd;
        padding: 28px 32px; font-family: 'Times New Roman', serif; font-size: .84rem; line-height: 1.7;
        color: #222; word-break: break-word; min-height: 160px;
    }
    .preview-box p { margin: 0 0 .8rem; }
    .preview-box .doc-heading { text-align: center; font-weight: 700; margin: 0 0 .4rem; }
    .preview-box .doc-title { text-align: center; font-weight: 700; margin: .3rem 0 1rem; }
    .preview-box .doc-meta { margin: 0 0 .3rem; }
    .preview-box .doc-clause { text-align: justify; text-indent: 24px; margin: 0 0 .8rem; }
    .preview-box .doc-paragraph { text-align: justify; }
    .preview-box .doc-empty { color: var(--text-3); font-style: italic; }

    /* Records table */
    .records-table { width: 100%; border-collapse: collapse; font-size: .85rem; }
    .records-table th {
        text-align: left; font-size: .72rem; text-transform: uppercase; letter-spacing: .05em;
        color: var(--text-3); font-weight: 600; padding: 8px 10px; border-bottom: 1.5px solid var(--border);
    }
    .records-table td { padding: 10px; border-bottom: 1px solid var(--border); }
    .records-table tr:last-child td { border-bottom: none; }
    .cat-chip {
        font-size: .68rem; font-weight: 700; padding: 2px 9px; border-radius: 20px;
        background: var(--primary-dim); color: var(--primary); text-transform: capitalize;
    }
    .empty-row { text-align: center; color: var(--text-3); padding: 24px; font-size: .85rem; }

    /* ===== DARK MODE ===== */
    [data-theme="dark"] .preview-box { background: var(--surface); color: var(--text-1); }
</style>

{{-- Skeleton placeholder, shown until the real content below is revealed --}}
<div class="skel-shell" aria-hidden="true">
    <div class="page-header">
        <div>
            <div class="skel skel-text" style="width:220px; height:22px; margin-bottom:8px;"></div>
            <div class="skel skel-text" style="width:340px;"></div>
        </div>
        <div class="skel" style="width:150px; height:34px; border-radius:30px;"></div>
    </div>

    <div class="card-panel">
        <div class="pill-row">
            @for ($i = 0; $i < 3; $i++)
            <div class="skel" style="width:120px; height:36px; border-radius:30px;"></div>
            @endfor
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <div class="card-panel">
                <div class="skel skel-text" style="width:150px; margin-bottom:14px;"></div>
                @for ($i = 0; $i < 4; $i++)
                <div class="field-group">
                    <div class="skel skel-text" style="width:100px; margin-bottom:8px;"></div>
                    <div class="skel" style="width:100%; height:38px; border-radius:6px;"></div>
                </div>
                @endfor
                <div class="skel" style="width:100%; height:44px; border-radius:6px;"></div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card-panel">
                <div class="skel skel-text" style="width:140px; margin-bottom:14px;"></div>
                <div class="skel" style="width:100%; height:160px; border-radius:6px;"></div>
            </div>

            <div class="card-panel">
                <div class="skel skel-text" style="width:170px; margin-bottom:14px;"></div>
                @for ($i = 0; $i < 4; $i++)
                <div style="display:flex; gap:16px; padding:9px 0; border-bottom:1px solid var(--border);">
                    <div class="skel skel-text" style="width:20%;"></div>
                    <div class="skel skel-text" style="width:35%;"></div>
                    <div class="skel skel-text" style="width:20%;"></div>
                    <div class="skel skel-text" style="width:15%;"></div>
                </div>
                @endfor
            </div>
        </div>
    </div>
</div>

<div class="skel-real">

<div class="page-header">
    <div>
        <h1><i class="fas fa-file-signature me-2" style="color:var(--primary);font-size:1.1rem;"></i>Document Templates</h1>
        <p class="sub">Fill in a template to generate a formatted PDF. Each one is saved under "Saved to records".</p>
    </div>
    <a href="{{ route('letterheads.index') }}" class="pill-btn" style="text-decoration:none;">
        <i class="fas fa-stamp me-1"></i> Manage letterheads
    </a>
</div>

@php
    $categoryLabels = [
        'resolution' => 'Resolution',
        'request_letter' => 'Request letter',
        'memorandum' => 'Memorandum',
    ];
@endphp

<div class="card-panel">
    <div class="pill-row" id="templatePills" role="group" aria-label="Choose a template">
        @foreach($templates as $index => $tpl)
            <button type="button" class="pill-btn {{ $index === 0 ? 'active' : '' }}" data-template-id="{{ $tpl->id }}"
                    aria-pressed="{{ $index === 0 ? 'true' : 'false' }}">
                {{ $categoryLabels[$tpl->category] ?? ucfirst($tpl->category) }}
            </button>
        @endforeach
    </div>
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="card-panel">
            <div class="panel-label"><i class="fas fa-pen"></i> Fill in the details</div>

            <form id="generateForm" action="{{ route('templates.generate') }}" method="POST">
                @csrf
                <input type="hidden" name="template_id" id="templateIdInput" value="{{ $templates->first()->id ?? '' }}">

                <div id="fieldsContainer"></div>

                <div class="field-group" id="letterheadGroup" style="display:none;">
                    <label for="letterheadSelect"><i class="fas fa-stamp me-1" aria-hidden="true"></i> Letterhead</label>
                    <div class="d-flex gap-2 align-items-center">
                        <select name="letterhead_id" id="letterheadSelect" class="form-control form-select">
                            <option value="">— No letterhead (plain layout) —</option>
                            @foreach($letterheads as $lh)
                                <option value="{{ $lh->id }}">{{ $lh->name }}</option>
                            @endforeach
                        </select>
                        <a href="#" id="letterheadPreviewLink" target="_blank" class="pill-btn" style="display:none; text-decoration:none; white-space:nowrap;">
                            <i class="fas fa-eye me-1" aria-hidden="true"></i> Preview letterhead<span class="sr-only"> (opens in a new tab)</span>
                        </a>
                    </div>
                    @if($letterheads->isEmpty())
                        <div class="field-hint">No letterheads saved yet — <a href="{{ route('letterheads.index') }}">add one</a> to use on Resolutions.</div>
                    @endif
                </div>

                <div class="field-group">
                    <label for="folderSelect"><i class="fas fa-folder me-1" aria-hidden="true"></i> Save to folder</label>
                    <select name="folder_id" id="folderSelect" class="form-control form-select">
                        <option value="">— Choose a folder —</option>
                        @foreach($folders as $folder)
                            <option value="{{ $folder->id }}">{{ $folder->name }}</option>
                        @endforeach
                    </select>
                    <div class="field-hint">Suggested automatically from the title — change it if it's wrong.</div>
                </div>

                <button type="submit" class="btn-generate">
                    <i class="fas fa-magic me-1"></i> Generate &amp; save document
                </button>
            </form>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card-panel">
            <div class="panel-label"><i class="fas fa-eye"></i> Generated preview</div>
            <div class="preview-box" id="previewBox">Fill in the form to see a preview…</div>
        </div>

        <div class="card-panel">
            <div class="panel-label"><i class="fas fa-list"></i> Saved to records ({{ $recent->count() }})</div>

            @if($recent->isEmpty())
                <div class="empty-row">No documents generated yet.</div>
            @else
                <div class="table-responsive">
                    <table class="records-table">
                        <thead>
                            <tr><th>Type</th><th>File</th><th>Folder</th><th>Date</th></tr>
                        </thead>
                        <tbody>
                            @foreach($recent as $file)
                                <tr>
                                    <td><span class="cat-chip">{{ $categoryLabels[$file->template->category ?? ''] ?? '—' }}</span></td>
                                    <td>
                                        <a href="{{ route('files.preview', $file->id) }}" style="color:var(--text-1); text-decoration:none; font-weight:500;">
                                            {{ $file->filename }}
                                        </a>
                                        @include('Admin.partials.approval-badge', ['file' => $file])
                                    </td>
                                    <td>{{ $file->folder->name ?? 'Root' }}</td>
                                    <td>{{ $file->created_at->format('M d, Y') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>

</div>

<script>
// Raw template data (id -> {content, placeholders}) rendered server-side,
// used to build the fields form and drive the live preview without a
// round trip for every keystroke.
const TEMPLATES = {
    @foreach($templates as $tpl)
        {{ $tpl->id }}: {
            content: {!! json_encode($tpl->content) !!},
            placeholders: {!! json_encode($tpl->placeholders()) !!},
            category: {!! json_encode($tpl->category) !!},
            letterheadId: {!! json_encode($tpl->letterhead_id) !!},
        },
    @endforeach
};

const FOLDER_NAMES = {!! json_encode($folders->pluck('name', 'id')) !!};
const LETTERHEAD_PREVIEW_URLS = {!! json_encode($letterheads->mapWithKeys(
    fn ($lh) => [$lh->id => route('letterheads.preview', $lh->id)]
)) !!};

const fieldsContainer = document.getElementById('fieldsContainer');
const templateIdInput = document.getElementById('templateIdInput');
const previewBox = document.getElementById('previewBox');
const folderSelect = document.getElementById('folderSelect');
const letterheadGroup = document.getElementById('letterheadGroup');
const letterheadSelect = document.getElementById('letterheadSelect');
const letterheadPreviewLink = document.getElementById('letterheadPreviewLink');

function updateLetterheadPreviewLink() {
    const url = LETTERHEAD_PREVIEW_URLS[letterheadSelect.value];
    if (url) {
        letterheadPreviewLink.href = url;
        letterheadPreviewLink.style.display = '';
    } else {
        letterheadPreviewLink.style.display = 'none';
    }
}

letterheadSelect.addEventListener('change', updateLetterheadPreviewLink);

function labelFor(key) {
    return key.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
}

function isLongField(key) {
    return /body|clause|message/i.test(key);
}

function buildForm(templateId) {
    const tpl = TEMPLATES[templateId];
    fieldsContainer.innerHTML = '';

    tpl.placeholders.forEach(key => {
        const group = document.createElement('div');
        group.className = 'field-group';

        const label = document.createElement('label');
        label.textContent = labelFor(key);
        label.htmlFor = 'tplField-' + key;
        group.appendChild(label);

        const input = document.createElement(isLongField(key) ? 'textarea' : 'input');
        input.id = 'tplField-' + key;
        input.name = `fields[${key}]`;
        input.dataset.key = key;
        input.className = 'form-control';
        if (isLongField(key)) {
            input.rows = 5;
        } else if (key === 'date') {
            input.type = 'date';
        } else {
            input.type = 'text';
        }
        input.addEventListener('input', () => { renderPreview(templateId); suggestFolder(); });

        group.appendChild(input);
        fieldsContainer.appendChild(group);
    });

    if (tpl.category === 'resolution') {
        letterheadGroup.style.display = '';
        letterheadSelect.value = tpl.letterheadId || '';
    } else {
        letterheadGroup.style.display = 'none';
        letterheadSelect.value = '';
    }
    updateLetterheadPreviewLink();

    renderPreview(templateId);
}

function currentFieldValues() {
    const values = {};
    fieldsContainer.querySelectorAll('[data-key]').forEach(el => { values[el.dataset.key] = el.value; });
    return values;
}

// Built from separate concatenated single-brace pieces, since Blade scans
// this whole file for double-curly-brace tokens even inside JS comments
// and strings - writing the pair directly here would get compiled as PHP.
const OPEN_TAG = '{' + '{';
const CLOSE_TAG = '}' + '}';

// Mirrors TemplateController::formatDocumentBody() so the live preview
// looks like the final generated document, not just typed-out text.
function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function isMetaBlock(lines) {
    return lines.every(line => /^[A-Za-z][A-Za-z /]{0,20}:\s?/.test(line.trim()));
}

function looksLikeTitle(text) {
    const lettersOnly = text.replace(/[^A-Za-z]/g, '');
    return lettersOnly !== '' && lettersOnly === lettersOnly.toUpperCase() && text.length <= 200;
}

function formatDocumentBodyPreview(text) {
    const paragraphs = text.split(/\n\s*\n/).map(p => p.trim()).filter(p => p !== '');
    if (paragraphs.length === 0) {
        return '<p class="doc-empty">Fill in the form to see a preview…</p>';
    }

    return paragraphs.map(paragraph => {
        const lines = paragraph.split('\n');
        const joined = lines.map(l => l.trim()).join(' ');

        if (isMetaBlock(lines)) {
            const rows = lines.map(line => escapeHtml(line.trim())
                .replace(/^([A-Za-z][A-Za-z /]*:)/, '<b>$1</b>'));
            return `<p class="doc-meta">${rows.join('<br>')}</p>`;
        }
        if (/^(WHEREAS|RESOLVED)\b/i.test(joined)) {
            const withBold = escapeHtml(joined).replace(/^(WHEREAS|RESOLVED)\b/i, '<b>$1</b>');
            return `<p class="doc-clause">${withBold}</p>`;
        }
        if (/^APPROVED\b/i.test(joined)) {
            return `<p class="doc-heading">${escapeHtml(joined)}</p>`;
        }
        if (/^(RESOLUTION|MEMORANDUM)\s+NO\.?/i.test(joined)) {
            return `<p class="doc-heading">${escapeHtml(paragraph).replace(/\n/g, '<br>')}</p>`;
        }
        if (looksLikeTitle(joined)) {
            return `<p class="doc-title">${escapeHtml(joined)}</p>`;
        }
        return `<p class="doc-paragraph">${escapeHtml(joined)}</p>`;
    }).join('');
}

function renderPreview(templateId) {
    const tpl = TEMPLATES[templateId];
    const values = currentFieldValues();
    let text = tpl.content;

    Object.keys(values).forEach(key => {
        const token = OPEN_TAG + key + CLOSE_TAG;
        text = text.split(token).join(values[key] || token);
    });

    previewBox.innerHTML = formatDocumentBodyPreview(text);
}

// Hybrid folder suggestion: longest matching (non-archived) folder name
// found inside the title/subject field - always editable afterward, never
// silently applied without showing in the dropdown.
function suggestFolder() {
    const values = currentFieldValues();
    const needle = (values.title || values.subject || '').toLowerCase();
    if (!needle) return;

    let bestMatch = null;
    for (const [id, name] of Object.entries(FOLDER_NAMES)) {
        if (needle.includes(name.toLowerCase())) {
            if (!bestMatch || name.length > FOLDER_NAMES[bestMatch].length) {
                bestMatch = id;
            }
        }
    }

    if (bestMatch) {
        folderSelect.value = bestMatch;
    }
}

// Only the template choices: "Manage letterheads" and the letterhead
// Preview link share the .pill-btn look, and clicking Preview used to run
// this too - which emptied the form being filled in.
const templatePills = document.querySelectorAll('#templatePills .pill-btn');
templatePills.forEach(btn => {
    btn.addEventListener('click', () => {
        templatePills.forEach(b => {
            b.classList.remove('active');
            b.setAttribute('aria-pressed', 'false');
        });
        btn.classList.add('active');
        btn.setAttribute('aria-pressed', 'true');
        templateIdInput.value = btn.dataset.templateId;
        buildForm(btn.dataset.templateId);
    });
});

if (templateIdInput.value) {
    buildForm(templateIdInput.value);
}
</script>

@endsection
