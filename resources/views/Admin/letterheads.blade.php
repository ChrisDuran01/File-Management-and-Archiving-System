@extends($layout ?? 'Admin.home')
@section('title', 'Letterheads')
@section('content')

@include('Admin.partials.theme')

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

<style>
    * { box-sizing: border-box; }
    body { background: var(--surface); font-family: 'Inter', sans-serif; color: var(--text-1); }

    .back-link { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: var(--text-2); text-decoration: none; margin-bottom: 1rem; }
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
        background: var(--primary-fill); color: #fff; border: none; border-radius: var(--radius-sm);
        padding: 10px 20px; font-size: .86rem; font-weight: 600; cursor: pointer;
    }
    .btn-primary-flat:hover { background: #046322; }

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

    .lh-actions { flex-wrap: wrap; justify-content: flex-end; max-width: 330px; }
    .lh-actions form { margin: 0; }
    .lh-name .chip { margin-left: 6px; vertical-align: middle; }
    .lh-warning { font-size: .78rem; color: var(--warning); margin-top: 6px; }
    .lh-notice {
        border: 1px solid var(--border); border-left: 4px solid var(--warning); background: var(--card);
        border-radius: var(--radius-sm); padding: 12px 16px; margin-bottom: 16px; font-size: .86rem;
    }
    .lh-hint { font-size: .78rem; color: var(--text-3); margin: 0 0 10px; }
    .lh-check { display: flex; gap: 8px; align-items: center; font-size: .84rem; color: var(--text-2); margin: 0 0 14px; cursor: pointer; }

    .edit-panel { display: none; margin-top: 14px; padding-top: 14px; border-top: 1px dashed var(--border); }
    .edit-panel.show { display: block; }
    .empty-row { text-align: center; color: var(--text-3); padding: 24px; font-size: .85rem; }

    /* ===== DARK MODE ===== */
    [data-theme="dark"] .lh-thumb { background: var(--surface); }
    [data-theme="dark"] .lh-actions button,
    [data-theme="dark"] .lh-actions a { background: var(--card); }
    [data-theme="dark"] .lh-actions .danger:hover { background: var(--danger-dim); border-color: #7f2a2a; color: #fca5a5; }
</style>

{{-- Skeleton placeholder, shown until the real content below is revealed --}}
<div class="skel-shell" aria-hidden="true">
    <div class="skel skel-text" style="width:180px; margin-bottom:1rem;"></div>

    <div class="page-header">
        <div>
            <div class="skel skel-text" style="width:150px; height:22px; margin-bottom:8px;"></div>
            <div class="skel skel-text" style="width:420px;"></div>
        </div>
    </div>

    <div class="card-panel">
        <div class="skel skel-text" style="width:170px; margin-bottom:14px;"></div>
        <div class="row">
            @for ($i = 0; $i < 4; $i++)
            <div class="col-md-6">
                <div class="field-group">
                    <div class="skel skel-text" style="width:100px; margin-bottom:6px;"></div>
                    <div class="skel" style="width:100%; height:36px; border-radius:8px;"></div>
                </div>
            </div>
            @endfor
        </div>
        <div class="upload-grid">
            @for ($i = 0; $i < 4; $i++)
            <div class="skel" style="width:100%; height:60px; border-radius:8px;"></div>
            @endfor
        </div>
        <div class="skel" style="width:150px; height:38px; border-radius:8px; margin-top:14px;"></div>
    </div>

    <div class="card-panel">
        <div class="skel skel-text" style="width:190px; margin-bottom:14px;"></div>
        @for ($i = 0; $i < 3; $i++)
        <div class="lh-card">
            <div class="lh-thumbs">
                @for ($j = 0; $j < 4; $j++)
                <div class="skel" style="width:52px; height:52px; border-radius:8px;"></div>
                @endfor
            </div>
            <div class="lh-info">
                <div class="skel skel-text" style="width:180px; margin-bottom:6px;"></div>
                <div class="skel skel-text" style="width:260px;"></div>
            </div>
        </div>
        @endfor
    </div>
</div>

<div class="skel-real">

<a href="{{ route('templates.index') }}" class="back-link"><i class="fas fa-arrow-left"></i> Back to Document Templates</a>

<div class="page-header">
    <div>
        <h1><i class="fas fa-stamp me-2" style="color:var(--primary);font-size:1.1rem;"></i>Letterheads</h1>
        <p class="sub">The page design printed around resolutions, request letters and activity proposals. Any Admin or Officer can add and manage these.</p>
    </div>
</div>

@if (! $default && $letterheads->isNotEmpty())
    <div class="lh-notice">
        No default letterhead yet, so every new document starts without one. Pick the SG's current one and press <b>Set as default</b>.
    </div>
@elseif ($default && $default->isFromPastSchoolYear())
    <div class="lh-notice">
        The default letterhead, <b>{{ $default->name }}</b>, is for SY {{ $default->school_year }}; this is SY {{ $currentYear }}.
        Press <b>Copy</b> on it to start this year's, upload the new design, then set the new one as the default.
    </div>
@endif

<div class="card-panel">
    <div class="panel-label"><i class="fas fa-plus"></i> Add a new letterhead</div>

    <form action="{{ route('letterheads.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="row">
            <div class="col-md-6">
                <div class="field-group">
                    <label for="lhNew-name">Letterhead name</label>
                    <input id="lhNew-name" type="text" name="name" class="form-control" placeholder="e.g. SG 2025-2026 Letterhead" required>
                </div>
            </div>
            <div class="col-md-6">
                <div class="field-group">
                    <label for="lhNew-school-year">School year</label>
                    <select id="lhNew-school-year" name="school_year" class="form-control form-select">
                        <option value="">Not for a particular year</option>
                        @foreach ($yearOptions as $year)
                            <option value="{{ $year }}" @selected($year === $currentYear)>{{ $year }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-md-6">
                <div class="field-group">
                    <label for="lhNew-university-name">University name</label>
                    <input id="lhNew-university-name" type="text" name="university_name" class="form-control" placeholder="QUIRINO STATE UNIVERSITY">
                </div>
            </div>
            <div class="col-md-6">
                <div class="field-group">
                    <label for="lhNew-office-name">Office name</label>
                    <input id="lhNew-office-name" type="text" name="office_name" class="form-control" placeholder="OFFICE OF THE STUDENT GOVERNMENT">
                </div>
            </div>
            <div class="col-md-6">
                <div class="field-group">
                    <label for="lhNew-address">Address</label>
                    <input id="lhNew-address" type="text" name="address" class="form-control" placeholder="Diffun, Quirino">
                </div>
            </div>
        </div>

        <div class="field-group">
            <label id="lhNew-images-label">Images</label>
            <p class="lh-hint" id="lhNew-page-hint">
                For a sharp print, open the SG letterhead in Word, set the paper to Long (8.5 × 13 in), choose File, Save as, PDF,
                and upload that PDF as the <b>Whole page</b>. A screenshot of the letterhead prints blurry.
            </p>
            <div class="upload-grid" role="group" aria-labelledby="lhNew-images-label">
                <div><label for="lhNew-border-image" style="font-weight:400;">Whole page (PDF or image)</label><input id="lhNew-border-image" type="file" name="border_image" class="form-control" accept="image/*,application/pdf" aria-describedby="lhNew-page-hint"></div>
                <div><label for="lhNew-logo" style="font-weight:400;">Logo</label><input id="lhNew-logo" type="file" name="logo" class="form-control" accept="image/*"></div>
                <div><label for="lhNew-seal" style="font-weight:400;">Seal</label><input id="lhNew-seal" type="file" name="seal" class="form-control" accept="image/*"></div>
                <div><label for="lhNew-footer-image" style="font-weight:400;">Footer badges</label><input id="lhNew-footer-image" type="file" name="footer_image" class="form-control" accept="image/*"></div>
            </div>
        </div>

        <label class="lh-check">
            <input type="checkbox" name="make_default" value="1" @checked(! $default)>
            New documents start with this letterhead (default)
        </label>

        <button type="submit" class="btn-primary-flat"><i class="fas fa-save me-1"></i> Save letterhead</button>
    </form>
</div>

<div class="card-panel">
    <div class="panel-label"><i class="fas fa-list"></i> Saved letterheads ({{ $letterheads->count() }})</div>

    @if($letterheads->isEmpty())
        <div class="empty-row">No letterheads yet — add one above.</div>
    @else
        @foreach($letterheads as $lh)
            @php
                $warnings = $lh->printWarnings();
                $open = (int) session('open_letterhead') === $lh->id;
            @endphp
            <div class="lh-card">
                <div class="lh-thumbs">
                    @foreach(['border_image_path' => 'Page', 'logo_path' => 'Logo', 'seal_path' => 'Seal', 'footer_image_path' => 'Footer'] as $col => $label)
                        @if($lh->$col)
                            <img loading="lazy" decoding="async" src="{{ asset('storage/'.$lh->$col) }}" class="lh-thumb" title="{{ $label }}" alt="{{ $label }}">
                        @else
                            <span class="lh-thumb-empty" title="No {{ $label }} uploaded">{{ $label }}</span>
                        @endif
                    @endforeach
                </div>

                <div class="lh-info">
                    <div class="lh-name">
                        {{ $lh->name }}
                        @if ($lh->is_default)<span class="chip">Default</span>@endif
                        @if ($lh->school_year)<span class="chip neutral">SY {{ $lh->school_year }}</span>@endif
                    </div>
                    <div class="lh-meta">
                        {{ $lh->university_name ?: 'No university name set' }}
                        @if($lh->office_name) &middot; {{ $lh->office_name }} @endif
                        @if($lh->address) &middot; {{ $lh->address }} @endif
                    </div>
                    @foreach ($warnings as $warning)
                        <div class="lh-warning"><i class="fas fa-exclamation-triangle" aria-hidden="true"></i> {{ $warning }}</div>
                    @endforeach

                    <div class="edit-panel {{ $open ? 'show' : '' }}" id="edit-{{ $lh->id }}">
                        <form action="{{ route('letterheads.update', $lh->id) }}" method="POST" enctype="multipart/form-data">
                            @csrf @method('PUT')
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="field-group"><label for="lh-name-{{ $lh->id }}">Letterhead name</label>
                                        <input id="lh-name-{{ $lh->id }}" type="text" name="name" class="form-control" value="{{ $lh->name }}" required></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="field-group"><label for="lh-school-year-{{ $lh->id }}">School year</label>
                                        <select id="lh-school-year-{{ $lh->id }}" name="school_year" class="form-control form-select">
                                            <option value="">Not for a particular year</option>
                                            @foreach ($yearOptions->concat([$lh->school_year])->filter()->unique()->sortDesc() as $year)
                                                <option value="{{ $year }}" @selected($year === $lh->school_year)>{{ $year }}</option>
                                            @endforeach
                                        </select></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="field-group"><label for="lh-university-name-{{ $lh->id }}">University name</label>
                                        <input id="lh-university-name-{{ $lh->id }}" type="text" name="university_name" class="form-control" value="{{ $lh->university_name }}"></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="field-group"><label for="lh-office-name-{{ $lh->id }}">Office name</label>
                                        <input id="lh-office-name-{{ $lh->id }}" type="text" name="office_name" class="form-control" value="{{ $lh->office_name }}"></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="field-group"><label for="lh-address-{{ $lh->id }}">Address</label>
                                        <input id="lh-address-{{ $lh->id }}" type="text" name="address" class="form-control" value="{{ $lh->address }}"></div>
                                </div>
                            </div>
                            <div class="upload-grid" style="margin-bottom:14px;">
                                <div><label for="lh-border-image-{{ $lh->id }}" style="font-weight:400;font-size:.78rem;">Whole page (PDF or image)</label><input id="lh-border-image-{{ $lh->id }}" type="file" name="border_image" class="form-control" accept="image/*,application/pdf"></div>
                                <div><label for="lh-logo-{{ $lh->id }}" style="font-weight:400;font-size:.78rem;">Logo</label><input id="lh-logo-{{ $lh->id }}" type="file" name="logo" class="form-control" accept="image/*"></div>
                                <div><label for="lh-seal-{{ $lh->id }}" style="font-weight:400;font-size:.78rem;">Seal</label><input id="lh-seal-{{ $lh->id }}" type="file" name="seal" class="form-control" accept="image/*"></div>
                                <div><label for="lh-footer-image-{{ $lh->id }}" style="font-weight:400;font-size:.78rem;">Footer badges</label><input id="lh-footer-image-{{ $lh->id }}" type="file" name="footer_image" class="form-control" accept="image/*"></div>
                            </div>
                            <button type="submit" class="btn-primary-flat"><i class="fas fa-save me-1"></i> Save changes</button>
                        </form>
                    </div>
                </div>

                <div class="lh-actions">
                    {{-- .sr-only adds the letterhead's name, so each card's
                         identical buttons can be told apart by ear. --}}
                    <a href="{{ route('letterheads.preview', $lh->id) }}" target="_blank" class="edit">
                        <i class="fas fa-eye" aria-hidden="true"></i> Preview<span class="sr-only"> {{ $lh->name }} (opens in a new tab)</span>
                    </a>
                    @unless ($lh->is_default)
                        <form action="{{ route('letterheads.default', $lh->id) }}" method="POST">
                            @csrf
                            <button type="submit" class="edit"><i class="fas fa-star" aria-hidden="true"></i> Set as default<span class="sr-only"> {{ $lh->name }}</span></button>
                        </form>
                    @endunless
                    <form action="{{ route('letterheads.copy', $lh->id) }}" method="POST">
                        @csrf
                        <button type="submit" class="edit"><i class="fas fa-copy" aria-hidden="true"></i> Copy<span class="sr-only"> {{ $lh->name }}</span></button>
                    </form>
                    <button type="button" class="edit" aria-expanded="{{ $open ? 'true' : 'false' }}" aria-controls="edit-{{ $lh->id }}"
                            onclick="this.setAttribute('aria-expanded', document.getElementById('edit-{{ $lh->id }}').classList.toggle('show'))">
                        <i class="fas fa-pen" aria-hidden="true"></i> Edit<span class="sr-only"> {{ $lh->name }}</span>
                    </button>
                    <form action="{{ route('letterheads.destroy', $lh->id) }}" method="POST"
                          data-confirm-title="Delete this letterhead?"
                          data-confirm="Templates using it will fall back to the plain layout. This cannot be undone."
                          data-confirm-ok="Delete">
                        @csrf @method('DELETE')
                        <button type="submit" class="danger"><i class="fas fa-trash" aria-hidden="true"></i> Delete<span class="sr-only"> {{ $lh->name }}</span></button>
                    </form>
                </div>
            </div>
        @endforeach
    @endif
</div>

</div>

@endsection
