@extends($layout ?? 'Admin.home')
@section('content')

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

<style>
    @import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap');

    :root {
        --surface: #f7f8fc; --card: #ffffff; --border: #e8eaf0; --primary: #2563eb; --primary-dim: #eff4ff;
        --text-1: #111827; --text-2: #6b7280; --text-3: #9ca3af;
        --shadow-sm: 0 1px 3px rgba(0,0,0,.06), 0 1px 2px rgba(0,0,0,.04);
        --radius: 12px; --radius-sm: 8px;
    }
    * { box-sizing: border-box; }
    body { background: var(--surface); font-family: 'DM Sans', sans-serif; color: var(--text-1); }

    .page-header h2 { font-size: 1.35rem; font-weight: 600; letter-spacing: -.3px; margin: 0 0 4px; }
    .page-header .sub { font-size: .85rem; color: var(--text-2); margin: 0 0 20px; }
    .back-link { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: #888; text-decoration: none; margin-bottom: 1rem; }
    .back-link:hover { color: var(--primary); }

    .card-panel {
        background: var(--card); border: 1px solid var(--border); border-radius: var(--radius);
        box-shadow: var(--shadow-sm); padding: 20px 22px; margin-bottom: 20px;
    }
    .panel-label {
        font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .07em;
        color: var(--text-3); margin-bottom: 14px; display: flex; align-items: center; gap: 6px;
    }
    .panel-label::after { content: ''; flex: 1; height: 1px; background: var(--border); }

    .field-group { margin-bottom: 14px; }
    .field-group label { display: block; font-size: .78rem; font-weight: 600; color: var(--text-2); margin-bottom: 6px; }
    .field-group .form-control {
        border: 1.5px solid var(--border); border-radius: var(--radius-sm); padding: 8px 12px; font-size: .86rem;
    }
    .field-group .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-dim); outline: none; }
    .upload-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; }

    .btn-primary-flat {
        background: var(--primary); color: #fff; border: none; border-radius: var(--radius-sm);
        padding: 10px 20px; font-size: .86rem; font-weight: 600; cursor: pointer;
    }
    .btn-primary-flat:hover { background: #1d4ed8; }

    .lh-card {
        border: 1px solid var(--border); border-radius: var(--radius); padding: 16px 18px;
        display: flex; gap: 16px; align-items: flex-start; margin-bottom: 12px; background: var(--card);
    }
    .lh-thumbs { display: flex; gap: 8px; flex-shrink: 0; }
    .lh-thumb {
        width: 52px; height: 52px; border-radius: 8px; border: 1px solid var(--border);
        object-fit: contain; background: #fafafa; padding: 4px;
    }
    .lh-thumb-empty {
        width: 52px; height: 52px; border-radius: 8px; border: 1.5px dashed var(--border);
        display: flex; align-items: center; justify-content: center; color: var(--text-3); font-size: .7rem;
    }
    .lh-info { flex: 1; min-width: 0; }
    .lh-name { font-weight: 600; font-size: .95rem; margin-bottom: 2px; }
    .lh-meta { font-size: .78rem; color: var(--text-2); }
    .lh-actions { display: flex; gap: 8px; flex-shrink: 0; }
    .lh-actions button, .lh-actions a {
        border: 1px solid var(--border); background: #fff; border-radius: var(--radius-sm);
        padding: 6px 12px; font-size: .78rem; cursor: pointer; color: var(--text-2); text-decoration: none;
    }
    .lh-actions .danger:hover { background: #fdecea; border-color: #f3c1bc; color: #b3261e; }
    .lh-actions .edit:hover { background: var(--primary-dim); border-color: var(--primary); color: var(--primary); }

    .edit-panel { display: none; margin-top: 14px; padding-top: 14px; border-top: 1px dashed var(--border); }
    .edit-panel.show { display: block; }
    .empty-row { text-align: center; color: var(--text-3); padding: 24px; font-size: .85rem; }
</style>

<a href="{{ route('templates.index') }}" class="back-link"><i class="fas fa-arrow-left"></i> Back to Document Templates</a>

<div class="page-header">
    <h2><i class="fas fa-stamp me-2" style="color:var(--primary);font-size:1.1rem;"></i>Letterheads</h2>
    <p class="sub">Upload the border, logo, seal, and footer images used to dress up generated Resolutions. Any Admin or Officer can add and manage these.</p>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div class="card-panel">
    <div class="panel-label"><i class="fas fa-plus"></i> Add a new letterhead</div>

    <form action="{{ route('letterheads.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="row">
            <div class="col-md-6">
                <div class="field-group">
                    <label>Letterhead name</label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. SG 2025-2026 Letterhead" required>
                </div>
            </div>
            <div class="col-md-6">
                <div class="field-group">
                    <label>University name</label>
                    <input type="text" name="university_name" class="form-control" placeholder="QUIRINO STATE UNIVERSITY">
                </div>
            </div>
            <div class="col-md-6">
                <div class="field-group">
                    <label>Office name</label>
                    <input type="text" name="office_name" class="form-control" placeholder="OFFICE OF THE STUDENT GOVERNMENT">
                </div>
            </div>
            <div class="col-md-6">
                <div class="field-group">
                    <label>Address</label>
                    <input type="text" name="address" class="form-control" placeholder="Diffun, Quirino">
                </div>
            </div>
        </div>

        <div class="field-group">
            <label>Images</label>
            <div class="upload-grid">
                <div><label style="font-weight:400;">Border / frame</label><input type="file" name="border_image" class="form-control" accept="image/*"></div>
                <div><label style="font-weight:400;">Logo</label><input type="file" name="logo" class="form-control" accept="image/*"></div>
                <div><label style="font-weight:400;">Seal</label><input type="file" name="seal" class="form-control" accept="image/*"></div>
                <div><label style="font-weight:400;">Footer badges</label><input type="file" name="footer_image" class="form-control" accept="image/*"></div>
            </div>
        </div>

        <button type="submit" class="btn-primary-flat"><i class="fas fa-save me-1"></i> Save letterhead</button>
    </form>
</div>

<div class="card-panel">
    <div class="panel-label"><i class="fas fa-list"></i> Saved letterheads ({{ $letterheads->count() }})</div>

    @if($letterheads->isEmpty())
        <div class="empty-row">No letterheads yet — add one above.</div>
    @else
        @foreach($letterheads as $lh)
            <div class="lh-card">
                <div class="lh-thumbs">
                    @foreach(['border_image_path' => 'Border', 'logo_path' => 'Logo', 'seal_path' => 'Seal', 'footer_image_path' => 'Footer'] as $col => $label)
                        @if($lh->$col)
                            <img src="{{ asset('storage/'.$lh->$col) }}" class="lh-thumb" title="{{ $label }}" alt="{{ $label }}">
                        @else
                            <span class="lh-thumb-empty" title="No {{ $label }} uploaded">{{ $label }}</span>
                        @endif
                    @endforeach
                </div>

                <div class="lh-info">
                    <div class="lh-name">{{ $lh->name }}</div>
                    <div class="lh-meta">
                        {{ $lh->university_name ?: 'No university name set' }}
                        @if($lh->office_name) &middot; {{ $lh->office_name }} @endif
                        @if($lh->address) &middot; {{ $lh->address }} @endif
                    </div>

                    <div class="edit-panel" id="edit-{{ $lh->id }}">
                        <form action="{{ route('letterheads.update', $lh->id) }}" method="POST" enctype="multipart/form-data">
                            @csrf @method('PUT')
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="field-group"><label>Letterhead name</label>
                                        <input type="text" name="name" class="form-control" value="{{ $lh->name }}" required></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="field-group"><label>University name</label>
                                        <input type="text" name="university_name" class="form-control" value="{{ $lh->university_name }}"></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="field-group"><label>Office name</label>
                                        <input type="text" name="office_name" class="form-control" value="{{ $lh->office_name }}"></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="field-group"><label>Address</label>
                                        <input type="text" name="address" class="form-control" value="{{ $lh->address }}"></div>
                                </div>
                            </div>
                            <div class="upload-grid" style="margin-bottom:14px;">
                                <div><label style="font-weight:400;font-size:.78rem;">Border / frame</label><input type="file" name="border_image" class="form-control" accept="image/*"></div>
                                <div><label style="font-weight:400;font-size:.78rem;">Logo</label><input type="file" name="logo" class="form-control" accept="image/*"></div>
                                <div><label style="font-weight:400;font-size:.78rem;">Seal</label><input type="file" name="seal" class="form-control" accept="image/*"></div>
                                <div><label style="font-weight:400;font-size:.78rem;">Footer badges</label><input type="file" name="footer_image" class="form-control" accept="image/*"></div>
                            </div>
                            <button type="submit" class="btn-primary-flat"><i class="fas fa-save me-1"></i> Save changes</button>
                        </form>
                    </div>
                </div>

                <div class="lh-actions">
                    <a href="{{ route('letterheads.preview', $lh->id) }}" target="_blank" class="edit">
                        <i class="fas fa-eye"></i> Preview
                    </a>
                    <button type="button" class="edit" onclick="document.getElementById('edit-{{ $lh->id }}').classList.toggle('show')">
                        <i class="fas fa-pen"></i> Edit
                    </button>
                    <form action="{{ route('letterheads.destroy', $lh->id) }}" method="POST" onsubmit="return confirm('Delete this letterhead? Templates using it will fall back to the plain layout.');">
                        @csrf @method('DELETE')
                        <button type="submit" class="danger"><i class="fas fa-trash"></i> Delete</button>
                    </form>
                </div>
            </div>
        @endforeach
    @endif
</div>

@endsection
