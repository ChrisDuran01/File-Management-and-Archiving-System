@extends($layout ?? 'Admin.home')
@section('content')

@include('Admin.partials.theme')
@include('partials.image-carousel', ['images' => collect()])

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.snow.css" rel="stylesheet">

<style>
    * { box-sizing: border-box; }
    body { background: var(--surface); font-family: 'Inter', sans-serif; color: var(--text-1); }

    .card-panel {
        background: var(--card); border: 1px solid var(--border); border-radius: var(--radius);
        box-shadow: var(--shadow-sm); padding: 20px 22px; margin-bottom: 20px;
    }
    .panel-label {
        font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .07em;
        color: var(--text-3); margin-bottom: 14px; display: flex; align-items: center; gap: 6px;
    }
    .panel-label::after { content: ''; flex: 1; height: 1px; background: var(--border); }

    .photo-preview { margin-top: 6px; }
    .photo-status { margin-top: 8px; padding: 8px 12px; border-radius: var(--radius-sm); font-size: .8rem; background: rgba(217,119,6,.12); color: #b45309; }
    .existing-photo { min-height: 44px; }
    .existing-photos { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 10px; }
    .existing-photo { width: 96px; font-size: .72rem; color: var(--text-2); cursor: pointer; margin: 0 !important; }
    .existing-photo img { width: 96px; height: 72px; object-fit: cover; border-radius: 8px; display: block; margin-bottom: 4px; }
    .existing-photo:has(input:checked) img { opacity: .35; }

    .field-group { margin-bottom: 14px; }
    .field-group label { display: block; font-size: .78rem; font-weight: 600; color: var(--text-2); margin-bottom: 6px; }
    .field-group .form-control {
        border: 1.5px solid var(--border); border-radius: var(--radius-sm); padding: 8px 12px; font-size: .86rem;
    }
    .field-group .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-dim); outline: none; }

    .btn-primary-flat {
        background: var(--primary); color: #fff; border: none; border-radius: var(--radius-sm);
        padding: 10px 20px; font-size: .86rem; font-weight: 600; cursor: pointer;
    }
    .btn-primary-flat:hover { background: #046322; }

    .an-card {
        border: 1px solid var(--border); border-radius: var(--radius); padding: 16px 18px;
        margin-bottom: 12px; background: var(--card);
    }
    .an-title { font-weight: 600; font-size: .95rem; margin-bottom: 2px; }
    .an-meta { font-size: .78rem; color: var(--text-2); margin-bottom: 8px; }
    .an-body { font-size: .85rem; color: var(--text-1); white-space: pre-line; margin-bottom: 10px; }
    .an-attachment { font-size: .8rem; }
    .an-attachment a { color: var(--primary); text-decoration: none; }
    .an-attachment a:hover { text-decoration: underline; }
    .an-actions { display: flex; gap: 8px; margin-top: 10px; }
    .an-actions button {
        border: 1px solid var(--border); background: #fff; border-radius: var(--radius-sm);
        padding: 6px 12px; font-size: .78rem; cursor: pointer; color: var(--text-2);
    }
    .an-actions .danger:hover { background: #fdecea; border-color: #f3c1bc; color: #b3261e; }
    .an-actions .edit:hover { background: var(--primary-dim); border-color: var(--primary); color: var(--primary); }

    [data-theme="dark"] .an-actions button { background: var(--card); }
    [data-theme="dark"] .an-actions .danger:hover { background: var(--danger-dim); border-color: #7f2a2a; color: #fca5a5; }
    [data-theme="dark"] .quill-editor { background: var(--card); color: var(--text-1); }

    .edit-panel { display: none; margin-top: 14px; padding-top: 14px; border-top: 1px dashed var(--border); }
    .edit-panel.show { display: block; }
    .empty-row { text-align: center; color: var(--text-3); padding: 24px; font-size: .85rem; }

    .quill-editor { background: #fff; border-radius: var(--radius-sm); font-size: .86rem; }
    .quill-editor .ql-toolbar { border: 1.5px solid var(--border); border-bottom: none; border-radius: var(--radius-sm) var(--radius-sm) 0 0; }
    .quill-editor .ql-container { border: 1.5px solid var(--border); border-radius: 0 0 var(--radius-sm) var(--radius-sm); min-height: 110px; font-family: inherit; font-size: .86rem; }
    .an-body p:last-child { margin-bottom: 0; }
    .an-body ul, .an-body ol { padding-left: 1.4em; margin-bottom: 0.5em; }
</style>

{{-- Skeleton placeholder, shown until the real content below is revealed --}}
<div class="skel-shell" aria-hidden="true">
    <div class="page-header">
        <div>
            <div class="skel skel-text" style="width:170px; height:22px; margin-bottom:8px;"></div>
            <div class="skel skel-text" style="width:400px;"></div>
        </div>
    </div>

    <div class="card-panel">
        <div class="skel skel-text" style="width:180px; margin-bottom:14px;"></div>
        <div class="field-group">
            <div class="skel skel-text" style="width:60px; margin-bottom:6px;"></div>
            <div class="skel" style="width:100%; height:36px; border-radius:8px;"></div>
        </div>
        <div class="field-group">
            <div class="skel skel-text" style="width:80px; margin-bottom:6px;"></div>
            <div class="skel" style="width:100%; height:110px; border-radius:8px;"></div>
        </div>
        <div class="field-group">
            <div class="skel skel-text" style="width:140px; margin-bottom:6px;"></div>
            <div class="skel" style="width:100%; height:36px; border-radius:8px;"></div>
        </div>
        <div class="skel" style="width:170px; height:38px; border-radius:8px;"></div>
    </div>

    <div class="card-panel">
        <div class="skel skel-text" style="width:200px; margin-bottom:14px;"></div>
        @for ($i = 0; $i < 3; $i++)
        <div class="an-card">
            <div class="skel skel-text" style="width:220px; margin-bottom:6px;"></div>
            <div class="skel skel-text" style="width:160px; margin-bottom:10px;"></div>
            <div class="skel skel-text" style="width:90%; margin-bottom:4px;"></div>
            <div class="skel skel-text" style="width:70%;"></div>
        </div>
        @endfor
    </div>
</div>

<div class="skel-real">

<div class="page-header">
    <div>
        <h2><i class="fas fa-bullhorn me-2" style="color:var(--primary);font-size:1.1rem;"></i>Announcements</h2>
        <p class="sub">Post updates for students. These show up on the public student dashboard as soon as you save them.</p>
    </div>
</div>

<div class="card-panel">
    <div class="panel-label"><i class="fas fa-plus"></i> Post a new announcement</div>

    <form action="{{ route('announcements.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="field-group">
            <label>Title</label>
            <input type="text" name="title" class="form-control" placeholder="e.g. General Assembly this Friday" required>
        </div>
        <div class="field-group">
            <label>Message</label>
            <div id="quill-new" class="quill-editor" data-placeholder="Details for students..."></div>
            <input type="hidden" name="body" id="body-new">
        </div>
        <div class="field-group">
            <label>Photos (optional)</label>
            <input type="file" name="images[]" class="form-control photo-input" accept=".jpg,.jpeg,.png,.webp" multiple>
            <small style="color:var(--text-3);">Up to 10 photos, 5MB each. Swipe the preview to check them; tap &times; to remove one.</small>
            <div class="photo-preview"></div>
        </div>
        <div class="field-group">
            <label>Attachment (optional)</label>
            <input type="file" name="attachment" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
            <small style="color:var(--text-3);">PDF, image, or Word doc, up to 10MB.</small>
        </div>

        <button type="submit" class="btn-primary-flat"><i class="fas fa-paper-plane me-1"></i> Post announcement</button>
    </form>
</div>

<div class="card-panel">
    <div class="panel-label"><i class="fas fa-list"></i> Posted announcements ({{ $announcements->count() }})</div>

    @if($announcements->isEmpty())
        <div class="empty-row">No announcements yet — post one above.</div>
    @else
        @foreach($announcements as $an)
            <div class="an-card">
                <div class="an-title">{{ $an->title }}</div>
                <div class="an-meta">
                    Posted {{ $an->created_at->format('M d, Y g:i A') }}
                    @if($an->author) &middot; by {{ $an->author->name }} @endif
                </div>
                @include('partials.image-carousel', ['images' => $an->images])
                <div class="an-body">{!! $an->body !!}</div>

                @if($an->attachment_path)
                    <div class="an-attachment">
                        <i class="fas fa-paperclip"></i>
                        <a href="{{ route('announcements.download', $an->id) }}">{{ $an->attachment_original_name ?? 'Attachment' }}</a>
                    </div>
                @endif

                <div class="an-actions">
                    <button type="button" class="edit" onclick="toggleEditPanel({{ $an->id }})">
                        <i class="fas fa-pen"></i> Edit
                    </button>
                    <form action="{{ route('announcements.destroy', $an->id) }}" method="POST"
                          data-confirm-title="Delete this announcement?"
                          data-confirm="“{{ $an->title }}” will be removed from the student page. This cannot be undone."
                          data-confirm-ok="Delete">
                        @csrf @method('DELETE')
                        <button type="submit" class="danger"><i class="fas fa-trash"></i> Delete</button>
                    </form>
                </div>

                <div class="edit-panel" id="edit-{{ $an->id }}">
                    <form action="{{ route('announcements.update', $an->id) }}" method="POST" enctype="multipart/form-data">
                        @csrf @method('PUT')
                        <div class="field-group">
                            <label>Title</label>
                            <input type="text" name="title" class="form-control" value="{{ $an->title }}" required>
                        </div>
                        <div class="field-group">
                            <label>Message</label>
                            <div id="quill-edit-{{ $an->id }}" class="quill-editor" data-initial="{{ $an->body }}"></div>
                            <input type="hidden" name="body" id="body-edit-{{ $an->id }}">
                        </div>
                        <div class="field-group">
                            <label>Photos</label>
                            @if($an->images->isNotEmpty())
                                <div class="existing-photos">
                                    @foreach($an->images as $img)
                                        <label class="existing-photo">
                                            <img src="{{ route('announcements.image', $img->id) }}" alt="">
                                            <span><input type="checkbox" name="remove_images[]" value="{{ $img->id }}"> Remove</span>
                                        </label>
                                    @endforeach
                                </div>
                            @endif
                            <input type="file" name="images[]" class="form-control photo-input" accept=".jpg,.jpeg,.png,.webp" multiple>
                            <small style="color:var(--text-3);">Add more photos (10 max, 5MB each).</small>
                            <div class="photo-preview"></div>
                        </div>
                        <div class="field-group">
                            <label>Replace attachment (optional)</label>
                            <input type="file" name="attachment" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                        </div>
                        <button type="submit" class="btn-primary-flat"><i class="fas fa-save me-1"></i> Save changes</button>
                    </form>
                </div>
            </div>
        @endforeach
    @endif
</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.js"></script>
<script>
const QUILL_TOOLBAR = [
    ['bold', 'italic', 'underline', 'strike'],
    [{ list: 'ordered' }, { list: 'bullet' }],
    ['link', 'blockquote'],
    ['clean'],
];

// Editors for edit panels are created lazily (only when that panel is
// first opened) rather than all at once on page load, since a page with
// many posted announcements would otherwise initialize dozens of unused
// editors up front.
const editEditors = {};

function toggleEditPanel(id) {
    const panel = document.getElementById('edit-' + id);
    panel.classList.toggle('show');

    if (panel.classList.contains('show') && ! editEditors[id]) {
        const container = document.getElementById('quill-edit-' + id);
        const editor = new Quill(container, { theme: 'snow', modules: { toolbar: QUILL_TOOLBAR } });
        editor.root.innerHTML = container.dataset.initial || '';
        editEditors[id] = editor;
    }
}

// Live, swipeable preview of the photos picked in a file input. Keeps its
// own list so a photo can be dropped before posting; the input's FileList
// is rebuilt from it with DataTransfer.
function setupPhotoPicker(input) {
    const box = input.parentElement.querySelector('.photo-preview');
    const MAX = 10;

    // Polite live region so screen readers hear the same warning.
    const status = document.createElement('div');
    status.className = 'photo-status';
    status.setAttribute('role', 'status');
    status.hidden = true;
    box.before(status);
    let files = [];

    function sync() {
        const dt = new DataTransfer();
        files.forEach(f => dt.items.add(f));
        input.files = dt.files;
        render();
    }

    // Same stacked deck the students will see, built from the picked files.
    function render() {
        box.innerHTML = '';
        if (! files.length) return;

        const deck = document.createElement('div');
        deck.className = 'ps';
        const stage = document.createElement('div');
        stage.className = 'ps-stage';
        files.forEach(function (f, i) {
            const slide = document.createElement('div');
            slide.className = 'ps-slide';
            const img = document.createElement('img');
            img.src = URL.createObjectURL(f);
            img.draggable = false;
            const x = document.createElement('button');
            x.type = 'button';
            x.className = 'ps-x';
            x.innerHTML = '&times;';
            x.title = 'Remove this photo';
            x.onclick = () => { files.splice(i, 1); sync(); };
            slide.append(img, x);
            stage.append(slide);
        });
        deck.append(stage);

        if (files.length > 1) {
            const nav = document.createElement('div');
            nav.className = 'ps-nav';
            nav.innerHTML = '<button type="button" class="ps-prev" aria-label="Previous photo">&#8249;</button>'
                + '<span class="ps-count"></span>'
                + '<button type="button" class="ps-next" aria-label="Next photo">&#8250;</button>';
            deck.append(nav);
        }

        box.append(deck);
        window.initPhotoStack(deck);
    }

    input.addEventListener('change', function () {
        // Catch problems now, with a plain-language reason, instead of
        // letting the server reject the whole post after the officer hit Post.
        const problems = [];
        const ok = Array.from(input.files).filter(function (f) {
            if (! /^image\/(jpeg|png|webp)$/.test(f.type)) { problems.push(f.name + ' is not a JPG, PNG or WebP photo'); return false; }
            if (f.size > 5 * 1024 * 1024) { problems.push(f.name + ' is over 5MB'); return false; }
            return true;
        });

        // A new pick adds to the earlier ones (the browser would replace them).
        const merged = files.concat(ok);
        if (merged.length > MAX) problems.push('Only ' + MAX + ' photos are allowed, so the extra ones were skipped');
        files = merged.slice(0, MAX);

        status.textContent = problems.join('. ');
        status.hidden = problems.length === 0;
        sync();
    });
}

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.photo-input').forEach(setupPhotoPicker);

    // "Post a new announcement" editor - always present, initialized right away.
    const newEditor = new Quill('#quill-new', {
        theme: 'snow',
        placeholder: document.getElementById('quill-new').dataset.placeholder || '',
        modules: { toolbar: QUILL_TOOLBAR },
    });

    // Quill renders into its own <div>, not a form field, so the editor's
    // HTML has to be copied into a hidden input right before each form
    // actually submits.
    document.querySelectorAll('form').forEach(function (form) {
        form.addEventListener('submit', function () {
            const hiddenInput = form.querySelector('input[type="hidden"][id^="body-"]');
            if (! hiddenInput) return;

            const editor = hiddenInput.id === 'body-new'
                ? newEditor
                : editEditors[hiddenInput.id.replace('body-edit-', '')];

            if (editor) {
                hiddenInput.value = editor.getText().trim() === '' ? '' : editor.root.innerHTML;
            }
        });
    });
});
</script>

@endsection
