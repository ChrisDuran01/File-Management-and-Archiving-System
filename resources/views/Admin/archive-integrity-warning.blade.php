@extends($layout ?? 'Admin.home')
@section('content')

@include('Admin.partials.theme')

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

<style>
    * { box-sizing: border-box; }
    body { font-family: 'Inter', sans-serif; color: var(--text-1); }

    .warning-panel {
        max-width: 560px; margin: 40px auto; background: #fff; border: 1px solid var(--danger);
        border-radius: 12px; padding: 32px; box-shadow: 0 4px 16px rgba(179,38,30,0.08);
    }
    .warning-icon { font-size: 2.5rem; color: var(--danger); margin-bottom: 14px; }
    .warning-panel h2 { font-size: 1.2rem; font-weight: 700; margin-bottom: 10px; color: var(--danger); }
    .warning-panel p { font-size: 0.9rem; color: var(--text-2); line-height: 1.6; margin-bottom: 10px; }
    .warning-panel .archive-name { font-weight: 600; color: var(--text-1); }

    .btn-row { display: flex; gap: 10px; margin-top: 22px; flex-wrap: wrap; }
    .btn-anyway {
        background: var(--danger); color: #fff; border: none; border-radius: 8px; padding: 10px 18px;
        font-size: 0.85rem; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;
    }
    .btn-anyway:hover { background: #93201a; color: #fff; }
    .btn-cancel {
        background: #fff; color: var(--text-2); border: 1px solid var(--border); border-radius: 8px; padding: 10px 18px;
        font-size: 0.85rem; font-weight: 600; text-decoration: none;
    }
    .btn-cancel:hover { background: #f7f8fc; }

    /* ===== DARK MODE ===== */
    [data-theme="dark"] .warning-panel { background: var(--card); box-shadow: none; }
    [data-theme="dark"] .btn-cancel { background: var(--card); }
    [data-theme="dark"] .btn-cancel:hover { background: var(--nav-hover-bg); }
</style>

<div class="warning-panel">
    <div class="warning-icon"><i class="fas fa-triangle-exclamation"></i></div>
    <h2>Integrity check failed</h2>
    <p>
        <span class="archive-name">{{ $archive->folder_name }}</span> ({{ $archive->zip_name }}) does not match
        the checksum recorded when it was archived. This means the stored file has changed since then - either
        through corruption or an unexpected modification - and can no longer be trusted as an exact copy of
        what was originally archived.
    </p>
    <p>
        This attempt has been recorded in the activity log. You can still download the file as-is if you need to
        inspect or attempt recovery, but treat its contents as unverified.
    </p>

    <div class="btn-row">
        <a href="{{ route('archives.download', $archive->id) }}?confirmed=1" class="btn-anyway">
            <i class="fas fa-download"></i> Download anyway
        </a>
        <a href="{{ route('archives.index') }}" class="btn-cancel">Cancel</a>
    </div>
</div>

@endsection
