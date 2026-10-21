@extends($layout ?? 'Admin.home')
@section('title', 'Site Content')
@section('content')

@include('Admin.partials.theme')

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

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

    /* Two-column layout: panels flow top-to-bottom, left then right, and are never split across columns */
    .sc-cols { column-count: 2; column-gap: 20px; }
    .sc-cols > form { display: contents; }
    .sc-cols .card-panel { break-inside: avoid; }
    @media (max-width: 991px) { .sc-cols { column-count: 1; } }

    .field-group { margin-bottom: 14px; }
    .field-group label { display: block; font-size: .78rem; font-weight: 600; color: var(--text-2); margin-bottom: 6px; }
    .field-group .form-control {
        border: 1.5px solid var(--border); border-radius: var(--radius-sm); padding: 8px 12px; font-size: .86rem;
    }
    .field-group .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-dim); outline: none; }

    .logo-preview {
        width: 72px; height: 72px; border-radius: 50%; object-fit: contain; background: #fff;
        border: 1px solid var(--border); padding: 6px; margin-bottom: 10px;
    }
    .logo-preview-empty {
        width: 72px; height: 72px; border-radius: 50%; border: 1.5px dashed var(--border);
        display: flex; align-items: center; justify-content: center; color: var(--text-3);
        font-size: .68rem; text-align: center; margin-bottom: 10px;
    }
    .banner-preview {
        width: 100%; max-width: 420px; height: 110px; object-fit: cover; border-radius: var(--radius-sm);
        border: 1px solid var(--border); margin-bottom: 10px; display: block;
    }
    .banner-preview-empty {
        width: 100%; max-width: 420px; height: 110px; border-radius: var(--radius-sm);
        border: 1.5px dashed var(--border); display: flex; align-items: center; justify-content: center;
        color: var(--text-3); font-size: .78rem; margin-bottom: 10px;
    }
    .image-preview {
        max-width: 280px; max-height: 160px; border-radius: var(--radius-sm);
        border: 1px solid var(--border); margin-bottom: 10px; display: block; object-fit: contain;
    }
    .hint { font-size: .76rem; color: var(--text-3); margin-top: 4px; }

    .btn-primary-flat {
        background: var(--primary-fill); color: #fff; border: none; border-radius: var(--radius-sm);
        padding: 10px 20px; font-size: .86rem; font-weight: 600; cursor: pointer;
    }
    .btn-primary-flat:hover { background: #145029; }
</style>

{{-- Skeleton placeholder, shown until the real content below is revealed --}}
<div class="skel-shell" aria-hidden="true">
    <div class="page-header">
        <div>
            <div class="skel skel-text" style="width:200px; height:22px; margin-bottom:8px;"></div>
            <div class="skel skel-text" style="width:460px;"></div>
        </div>
    </div>

    <div class="card-panel">
        <div class="skel skel-text" style="width:100px; margin-bottom:14px;"></div>
        <div class="skel skel-circle" style="width:72px; height:72px; margin-bottom:10px;"></div>
        <div class="skel skel-text" style="width:140px;"></div>
    </div>

    <div class="card-panel">
        <div class="skel skel-text" style="width:160px; margin-bottom:14px;"></div>
        <div class="skel skel-text" style="width:80%; margin-bottom:10px;"></div>
        <div class="skel" style="width:100%; max-width:420px; height:110px; border-radius:8px; margin-bottom:10px;"></div>
        <div class="skel skel-text" style="width:140px;"></div>
    </div>

    @for ($i = 0; $i < 3; $i++)
    <div class="card-panel">
        <div class="skel skel-text" style="width:110px; margin-bottom:14px;"></div>
        <div class="skel" style="width:100%; height:80px; border-radius:8px; margin-bottom:10px;"></div>
        <div class="skel skel-text" style="width:220px;"></div>
    </div>
    @endfor

    <div class="skel" style="width:160px; height:40px; border-radius:8px;"></div>
</div>

<div class="skel-real">

<div class="page-header">
    <div>
        <h1><i class="fas fa-file-alt me-2" style="color:var(--primary);font-size:1.1rem;"></i>Site Content</h1>
        <p class="sub">The SG logo, QSU Hymn, Vision, and Mission shown on the public student dashboard. Any Admin or Officer can update these.</p>
    </div>
</div>

<div class="sc-cols">
<form action="{{ route('site-settings.update') }}" method="POST" enctype="multipart/form-data">
    @csrf

    <div class="card-panel">
        <div class="panel-label"><i class="fas fa-image"></i> SG Logo</div>

        @if($settings->logo_path)
            <img src="{{ asset('storage/'.$settings->logo_path) }}" class="logo-preview" alt="Current logo">
        @else
            <div class="logo-preview-empty">No logo uploaded</div>
        @endif

        <div class="field-group">
            <label for="site-logo">Replace logo</label>
            <input id="site-logo" type="file" name="logo" class="form-control" accept="image/*">
        </div>
    </div>

    <div class="card-panel">
        <div class="panel-label"><i class="fas fa-panorama"></i> Hero Background</div>
        <p class="sub" style="margin-top:-8px;">Fills the top banner behind the logo/title on the student dashboard. If none is uploaded, it stays a plain green background.</p>

        @if($settings->hero_background_path)
            <img src="{{ asset('storage/'.$settings->hero_background_path) }}" class="banner-preview" alt="Current hero background">
        @else
            <div class="banner-preview-empty">No background photo uploaded - using plain green</div>
        @endif

        <div class="field-group">
            <label for="site-hero-background">Replace hero background</label>
            <input id="site-hero-background" type="file" name="hero_background" class="form-control" accept="image/*">
            <p class="hint">A wide photo (e.g. campus/building) works best - it will be cropped to fill the banner on any screen size.</p>
        </div>
    </div>

    <div class="card-panel">
        <div class="panel-label"><i class="fas fa-music"></i> QSU Hymn</div>
        <div class="field-group">
            <textarea name="hymn" class="form-control" rows="8" placeholder="Hymn lyrics..." aria-label="QSU Hymn lyrics">{{ old('hymn', $settings->hymn) }}</textarea>
        </div>

        @if($settings->hymn_image_path)
            <img loading="lazy" decoding="async" src="{{ asset('storage/'.$settings->hymn_image_path) }}" class="image-preview" alt="Current hymn image">
        @endif
        <div class="field-group">
            <label for="site-hymn-image">Or upload an already-designed image instead<span class="sr-only"> of the hymn text</span></label>
            <input id="site-hymn-image" type="file" name="hymn_image" class="form-control" accept="image/*">
            <p class="hint">If you upload an image, it replaces the text above on the public dashboard.</p>
        </div>
    </div>

    <div class="card-panel">
        <div class="panel-label"><i class="fas fa-eye"></i> Vision</div>
        <div class="field-group">
            <textarea name="vision" class="form-control" rows="5" placeholder="Vision statement..." aria-label="Vision statement">{{ old('vision', $settings->vision) }}</textarea>
        </div>

        @if($settings->vision_image_path)
            <img loading="lazy" decoding="async" src="{{ asset('storage/'.$settings->vision_image_path) }}" class="image-preview" alt="Current vision image">
        @endif
        <div class="field-group">
            <label for="site-vision-image">Or upload an already-designed image instead<span class="sr-only"> of the vision text</span></label>
            <input id="site-vision-image" type="file" name="vision_image" class="form-control" accept="image/*">
            <p class="hint">If you upload an image, it replaces the text above on the public dashboard.</p>
        </div>
    </div>

    <div class="card-panel">
        <div class="panel-label"><i class="fas fa-bullseye"></i> Mission</div>
        <div class="field-group">
            <textarea name="mission" class="form-control" rows="5" placeholder="Mission statement..." aria-label="Mission statement">{{ old('mission', $settings->mission) }}</textarea>
        </div>

        @if($settings->mission_image_path)
            <img loading="lazy" decoding="async" src="{{ asset('storage/'.$settings->mission_image_path) }}" class="image-preview" alt="Current mission image">
        @endif
        <div class="field-group">
            <label for="site-mission-image">Or upload an already-designed image instead<span class="sr-only"> of the mission text</span></label>
            <input id="site-mission-image" type="file" name="mission_image" class="form-control" accept="image/*">
            <p class="hint">If you upload an image, it replaces the text above on the public dashboard.</p>
        </div>
    </div>

    <div class="card-panel">
        <div class="panel-label"><i class="fas fa-users"></i> Officer Photos</div>
        <p class="sub" style="margin-top:-8px;">Photos shown in the Officers section of the student dashboard. If you don't upload one for an officer, their profile picture is used.</p>

        @forelse($officers as $term)
            <div class="field-group" style="display:flex; align-items:center; gap:14px; flex-wrap:wrap;">
                {{-- alt=""/aria-hidden: the officer's name is the label right beside it --}}
                @if($term->display_photo)
                    <img loading="lazy" decoding="async" src="{{ asset('storage/'.$term->display_photo) }}" alt="" style="width:56px;height:56px;border-radius:50%;object-fit:cover;">
                @else
                    <div aria-hidden="true" style="width:56px;height:56px;border-radius:50%;background:var(--primary-fill);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:600;">{{ strtoupper(substr($term->user->name, 0, 1)) }}</div>
                @endif
                <div style="flex:1; min-width:220px;">
                    <label for="officerPhoto-{{ $term->id }}"><span class="sr-only">Photo for </span>{{ $term->user->name }} <span class="hint">- {{ $term->position->position_name ?? '' }}{{ $term->site_photo ? ' (custom photo)' : '' }}</span></label>
                    <input type="file" name="officer_photos[{{ $term->id }}]" id="officerPhoto-{{ $term->id }}" class="form-control" accept="image/*">
                    @if($term->site_photo)
                        <label class="hint"><input type="checkbox" name="remove_officer_photo[]" value="{{ $term->id }}"> Remove custom photo (use profile picture)</label>
                    @endif
                </div>
            </div>
        @empty
            <p class="hint">No active officers.</p>
        @endforelse
    </div>

    <div class="card-panel">
        <button type="submit" class="btn-primary-flat"><i class="fas fa-save me-1"></i> Save changes</button>
    </div>
</form>

<div class="card-panel">
    <div class="panel-label"><i class="fas fa-user-plus"></i> Officers Without an Account</div>
    <p class="sub" style="margin-top:-8px;">People who only need to appear in the Officers section of the student dashboard - no login is created. They're ordered by position along with the other officers.</p>

    @foreach($displayOfficers as $person)
        <div class="field-group" style="display:flex; align-items:center; gap:14px;">
            @if($person->photo)
                <img loading="lazy" decoding="async" src="{{ asset('storage/'.$person->photo) }}" alt="" style="width:56px;height:56px;border-radius:50%;object-fit:cover;">
            @else
                <div aria-hidden="true" style="width:56px;height:56px;border-radius:50%;background:var(--primary-fill);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:600;">{{ strtoupper(substr($person->name, 0, 1)) }}</div>
            @endif
            <div style="flex:1;"><strong>{{ $person->name }}</strong> <span class="hint">- {{ $person->position_name }}</span></div>
            <form action="{{ route('site-settings.display-officers.destroy', $person) }}" method="POST"
                  data-confirm-title="Remove {{ $person->name }}?"
                  data-confirm="They will no longer appear on the student dashboard."
                  data-confirm-ok="Remove">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash" aria-hidden="true"></i> Remove<span class="sr-only"> {{ $person->name }}</span></button>
            </form>
        </div>
    @endforeach

    <form action="{{ route('site-settings.display-officers.store') }}" method="POST" enctype="multipart/form-data" style="margin-top:12px;">
        @csrf
        <div class="field-group">
            <label for="dispOfficer-name">Name</label>
            <input id="dispOfficer-name" type="text" name="name" class="form-control" required maxlength="255">
        </div>
        <div class="field-group">
            <label for="dispOfficer-position-name">Position</label>
            <input id="dispOfficer-position-name" type="text" name="position_name" class="form-control" required maxlength="255" placeholder="e.g. Auditor">
        </div>
        <div class="field-group">
            <label for="dispOfficer-photo">Photo (optional)</label>
            <input id="dispOfficer-photo" type="file" name="photo" class="form-control" accept="image/*">
        </div>
        <button type="submit" class="btn-primary-flat"><i class="fas fa-plus me-1"></i> Add officer</button>
    </form>
</div>
</div>{{-- /.sc-cols --}}

</div>

@endsection
