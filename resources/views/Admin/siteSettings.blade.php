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
        background: var(--primary); color: #fff; border: none; border-radius: var(--radius-sm);
        padding: 10px 20px; font-size: .86rem; font-weight: 600; cursor: pointer;
    }
    .btn-primary-flat:hover { background: #145029; }
</style>

<div class="page-header">
    <h2><i class="fas fa-file-alt me-2" style="color:var(--primary);font-size:1.1rem;"></i>Site Content</h2>
    <p class="sub">The SG logo, QSU Hymn, Vision, and Mission shown on the public student dashboard. Any Admin or Officer can update these.</p>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

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
            <label>Replace logo</label>
            <input type="file" name="logo" class="form-control" accept="image/*">
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
            <label>Replace hero background</label>
            <input type="file" name="hero_background" class="form-control" accept="image/*">
            <p class="hint">A wide photo (e.g. campus/building) works best - it will be cropped to fill the banner on any screen size.</p>
        </div>
    </div>

    <div class="card-panel">
        <div class="panel-label"><i class="fas fa-music"></i> QSU Hymn</div>
        <div class="field-group">
            <textarea name="hymn" class="form-control" rows="8" placeholder="Hymn lyrics...">{{ old('hymn', $settings->hymn) }}</textarea>
        </div>

        @if($settings->hymn_image_path)
            <img src="{{ asset('storage/'.$settings->hymn_image_path) }}" class="image-preview" alt="Current hymn image">
        @endif
        <div class="field-group">
            <label>Or upload an already-designed image instead</label>
            <input type="file" name="hymn_image" class="form-control" accept="image/*">
            <p class="hint">If you upload an image, it replaces the text above on the public dashboard.</p>
        </div>
    </div>

    <div class="card-panel">
        <div class="panel-label"><i class="fas fa-eye"></i> Vision</div>
        <div class="field-group">
            <textarea name="vision" class="form-control" rows="5" placeholder="Vision statement...">{{ old('vision', $settings->vision) }}</textarea>
        </div>

        @if($settings->vision_image_path)
            <img src="{{ asset('storage/'.$settings->vision_image_path) }}" class="image-preview" alt="Current vision image">
        @endif
        <div class="field-group">
            <label>Or upload an already-designed image instead</label>
            <input type="file" name="vision_image" class="form-control" accept="image/*">
            <p class="hint">If you upload an image, it replaces the text above on the public dashboard.</p>
        </div>
    </div>

    <div class="card-panel">
        <div class="panel-label"><i class="fas fa-bullseye"></i> Mission</div>
        <div class="field-group">
            <textarea name="mission" class="form-control" rows="5" placeholder="Mission statement...">{{ old('mission', $settings->mission) }}</textarea>
        </div>

        @if($settings->mission_image_path)
            <img src="{{ asset('storage/'.$settings->mission_image_path) }}" class="image-preview" alt="Current mission image">
        @endif
        <div class="field-group">
            <label>Or upload an already-designed image instead</label>
            <input type="file" name="mission_image" class="form-control" accept="image/*">
            <p class="hint">If you upload an image, it replaces the text above on the public dashboard.</p>
        </div>
    </div>

    <button type="submit" class="btn-primary-flat"><i class="fas fa-save me-1"></i> Save changes</button>
</form>

@endsection
