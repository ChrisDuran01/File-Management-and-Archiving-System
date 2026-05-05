@extends('SuperAdmin.homeSuperAdmin')

@section('content')

<style>
    @import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap');

    :root {
        --surface:     #f7f8fc;
        --card:        #ffffff;
        --border:      #e8eaf0;
        --primary:     #534AB7;
        --primary-dim: #EEEDFE;
        --text-1:      #111827;
        --text-2:      #6b7280;
        --text-3:      #9ca3af;
        --shadow-sm:   0 1px 3px rgba(0,0,0,.06), 0 1px 2px rgba(0,0,0,.04);
        --radius:      12px;
        --radius-sm:   8px;
        --red:         #A32D2D;
        --red-dim:     #FCEBEB;
    }

    * { box-sizing: border-box; }
    body {
        background: var(--surface);
        font-family: 'DM Sans', sans-serif;
        color: var(--text-1);
    }

    /* ── Page Header ── */
    .page-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }
    .page-header h4 {
        font-size: 1.25rem;
        font-weight: 500;
        letter-spacing: -.3px;
        margin: 0;
        color: var(--text-1);
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .page-title-icon {
        width: 32px;
        height: 32px;
        border-radius: var(--radius-sm);
        background: var(--primary-dim);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .page-title-icon svg { width: 16px; height: 16px; }

    /* ── Back link ── */
    .back-link {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 15px;
        color: var(--text-2);
        text-decoration: none;
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        padding: 6px 12px;
        transition: all .15s;
        background: var(--card);
    }
    .back-link:hover { border-color: var(--primary); color: var(--primary); }
    .back-link i { font-size: 13px; }

    /* ── Card ── */
    .al-card {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        overflow: hidden;
        box-shadow: var(--shadow-sm);
        max-width: 640px;
    }

    .al-card-header {
        padding: 0.875rem 1.25rem;
        border-bottom: 1px solid var(--border);
        background: #f9fafb;
    }
    .al-card-title {
        font-size: 15px;
        font-weight: 500;
        color: var(--text-1);
        display: flex;
        align-items: center;
        gap: 7px;
        margin: 0;
    }
    .al-card-title-dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: var(--primary);
        flex-shrink: 0;
    }

    /* ── Form body ── */
    .form-body {
        padding: 1.5rem 1.25rem;
        display: flex;
        flex-direction: column;
        gap: 1.25rem;
    }

    /* ── Field ── */
    .field-group {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }
    .field-label {
        font-size: 14px;
        font-weight: 500;
        color: var(--text-1);
        display: flex;
        align-items: center;
        gap: 5px;
    }
    .field-label i {
        font-size: 10px;
        color: var(--text-3);
    }
    .field-required {
        color: var(--red);
        font-size: 13px;
        margin-left: 1px;
    }

    .field-input,
    .field-select {
        width: 100%;
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        padding: 8px 12px;
        font-size: 15px;
        font-family: 'DM Sans', sans-serif;
        color: var(--text-1);
        background: var(--card);
        outline: none;
        transition: border-color .15s, box-shadow .15s;
        appearance: none;
        -webkit-appearance: none;
    }
    .field-input::placeholder { color: var(--text-3); }
    .field-input:focus,
    .field-select:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(83,74,183,.1);
    }

    /* mono inputs */
    .field-input.mono {
        font-family: 'DM Mono', monospace;
        font-size: 14px;
    }

    /* select wrapper for custom arrow */
    .select-wrap { position: relative; }
    .select-wrap::after {
        content: '';
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        width: 0; height: 0;
        border-left: 4px solid transparent;
        border-right: 4px solid transparent;
        border-top: 5px solid var(--text-3);
        pointer-events: none;
    }
    .select-wrap .field-select { padding-right: 32px; cursor: pointer; }

    /* password wrapper */
    .input-wrap { position: relative; }
    .input-wrap .field-input { padding-right: 38px; }
    .toggle-pw {
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        cursor: pointer;
        color: var(--text-3);
        font-size: 14px;
        padding: 2px 4px;
        transition: color .15s;
        display: flex;
        align-items: center;
    }
    .toggle-pw:hover { color: var(--primary); }

    /* field hint */
    .field-hint {
        font-size: 12px;
        color: var(--text-3);
        display: flex;
        align-items: center;
        gap: 4px;
        font-family: 'DM Mono', monospace;
    }
    .field-hint i { font-size: 8px; }

    /* ── Divider ── */
    .form-divider {
        height: 1px;
        background: var(--border);
        margin: 0 -1.25rem;
    }
    .divider-label {
        font-size: 12px;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: var(--text-3);
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .divider-label::before,
    .divider-label::after {
        content: '';
        flex: 1;
        height: 1px;
        background: var(--border);
    }

    /* ── Two-col row ── */
    .field-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }
    @media (max-width: 520px) {
        .field-row { grid-template-columns: 1fr; }
    }

    /* ── Footer ── */
    .form-footer {
        padding: 1rem 1.25rem;
        border-top: 1px solid var(--border);
        background: #f9fafb;
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 8px;
    }
    .btn-cancel {
        background: none;
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        color: var(--text-2);
        padding: 7px 16px;
        font-size: 15px;
        font-family: 'DM Sans', sans-serif;
        cursor: pointer;
        transition: all .15s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .btn-cancel:hover { border-color: var(--primary); color: var(--primary); }
    .btn-save {
        background: var(--primary);
        border: none;
        border-radius: var(--radius-sm);
        color: #fff;
        padding: 7px 20px;
        font-size: 15px;
        font-weight: 500;
        font-family: 'DM Sans', sans-serif;
        cursor: pointer;
        transition: background .15s;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .btn-save:hover { background: #3C3489; }

    /* ── Validation errors ── */
    .field-error {
        font-size: 13px;
        color: var(--red);
        display: flex;
        align-items: center;
        gap: 4px;
    }
    .field-error i { font-size: 12px; }
    .field-input.is-invalid,
    .field-select.is-invalid {
        border-color: var(--red);
        box-shadow: 0 0 0 3px rgba(163,45,45,.08);
    }
</style>

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

{{-- ── Page Header ── --}}
<div class="page-header">
    <h4>
        <div class="page-title-icon">
            <svg viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="6" cy="5" r="2.5" stroke="#534AB7" stroke-width="1.2"/>
                <path d="M2 13c0-2.21 1.79-4 4-4s4 1.79 4 4" stroke="#534AB7" stroke-width="1.2" stroke-linecap="round"/>
                <path d="M11 7.5l1.5 1.5L15 6" stroke="#534AB7" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>
        Add Officer
    </h4>

    <a href="{{ route('officers.index') }}" class="back-link">
        <i class="fas fa-arrow-left"></i> Back to Officers
    </a>
</div>

{{-- ── Form Card ── --}}
<div class="al-card">

    <div class="al-card-header">
        <span class="al-card-title">
            <span class="al-card-title-dot"></span>
            Officer Information
        </span>
    </div>

    <form action="{{ route('officers.store') }}" method="POST" novalidate>
        @csrf

        <div class="form-body">

            {{-- Name + Email --}}
            <div class="field-row">
                <div class="field-group">
                    <label class="field-label">
                        <i class="fas fa-user"></i>
                        Full Name <span class="field-required">*</span>
                    </label>
                    <input
                        type="text"
                        name="name"
                        class="field-input {{ $errors->has('name') ? 'is-invalid' : '' }}"
                        placeholder="e.g. Juan dela Cruz"
                        value="{{ old('name') }}"
                        required
                    >
                    @error('name')
                        <span class="field-error"><i class="fas fa-circle-exclamation"></i> {{ $message }}</span>
                    @enderror
                </div>

                <div class="field-group">
                    <label class="field-label">
                        <i class="fas fa-envelope"></i>
                        Email Address <span class="field-required">*</span>
                    </label>
                    <input
                        type="email"
                        name="email"
                        class="field-input mono {{ $errors->has('email') ? 'is-invalid' : '' }}"
                        placeholder="officer@school.edu"
                        value="{{ old('email') }}"
                        required
                    >
                    @error('email')
                        <span class="field-error"><i class="fas fa-circle-exclamation"></i> {{ $message }}</span>
                    @enderror
                </div>
            </div>

            {{-- Position + School Year --}}
            <div class="field-row">
                <div class="field-group">
                    <label class="field-label">
                        <i class="fas fa-id-badge"></i>
                        Position <span class="field-required">*</span>
                    </label>
                    <div class="select-wrap">
                        <select
                            name="position_id"
                            class="field-select {{ $errors->has('position_id') ? 'is-invalid' : '' }}"
                            required
                        >
                            <option value="">Select position…</option>
                            @foreach ($positions as $position)
                                <option value="{{ $position->id }}" {{ old('position_id') == $position->id ? 'selected' : '' }}>
                                    {{ $position->position_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    @error('position_id')
                        <span class="field-error"><i class="fas fa-circle-exclamation"></i> {{ $message }}</span>
                    @enderror
                </div>

                <div class="field-group">
                    <label class="field-label">
                        <i class="fas fa-calendar"></i>
                        School Year <span class="field-required">*</span>
                    </label>
                    <input
                        type="text"
                        name="school_year"
                        class="field-input mono {{ $errors->has('school_year') ? 'is-invalid' : '' }}"
                        placeholder="2026-2027"
                        value="{{ old('school_year') }}"
                        required
                    >
                    <span class="field-hint"><i class="fas fa-circle-info"></i> Format: YYYY-YYYY</span>
                    @error('school_year')
                        <span class="field-error"><i class="fas fa-circle-exclamation"></i> {{ $message }}</span>
                    @enderror
                </div>
            </div>

            {{-- Divider --}}
            <div class="divider-label">Account Credentials</div>

            {{-- Password --}}
            <div class="field-group">
                <label class="field-label">
                    <i class="fas fa-lock"></i>
                    Password <span class="field-required">*</span>
                </label>
                <div class="input-wrap">
                    <input
                        type="password"
                        name="password"
                        id="passwordField"
                        class="field-input {{ $errors->has('password') ? 'is-invalid' : '' }}"
                        placeholder="Enter a secure password"
                        required
                    >
                    <button type="button" class="toggle-pw" onclick="togglePassword()" title="Toggle visibility">
                        <i class="fas fa-eye" id="pwIcon"></i>
                    </button>
                </div>
                <span class="field-hint"><i class="fas fa-circle-info"></i> Minimum 8 characters recommended</span>
                @error('password')
                    <span class="field-error"><i class="fas fa-circle-exclamation"></i> {{ $message }}</span>
                @enderror
            </div>

        </div>

        {{-- Footer --}}
        <div class="form-footer">
            <a href="{{ route('officers.index') }}" class="btn-cancel">
                Cancel
            </a>
            <button type="submit" class="btn-save">
                <i class="fas fa-check"></i> Save Officer
            </button>
        </div>

    </form>

</div>

<script>
function togglePassword() {
    const field  = document.getElementById('passwordField');
    const icon   = document.getElementById('pwIcon');
    const isText = field.type === 'text';
    field.type   = isText ? 'password' : 'text';
    icon.className = isText ? 'fas fa-eye' : 'fas fa-eye-slash';
}
</script>

@endsection