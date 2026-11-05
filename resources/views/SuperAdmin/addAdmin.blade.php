@extends('SuperAdmin.homeSuperAdmin')
@section('title', 'Add Officer')

@section('content')

@include('Admin.partials.theme')

@include('SuperAdmin.partials.officer-form-styles')

{{-- Skeleton placeholder, shown until the real content below is revealed --}}
<div class="skel-shell" aria-hidden="true">
    <div class="page-header">
        <div class="skel skel-text" style="width:130px; height:22px;"></div>
        <div class="skel" style="width:150px; height:34px; border-radius:6px;"></div>
    </div>

    <div class="al-card" style="max-width:640px;">
        <div class="al-card-header">
            <div class="skel skel-text" style="width:160px;"></div>
        </div>
        <div class="form-body">
            <div class="field-row">
                <div class="skel" style="height:58px; border-radius:6px;"></div>
                <div class="skel" style="height:58px; border-radius:6px;"></div>
            </div>
            <div class="field-row">
                <div class="skel" style="height:58px; border-radius:6px;"></div>
                <div class="skel" style="height:58px; border-radius:6px;"></div>
            </div>
            <div class="skel skel-text" style="width:160px;"></div>
            <div class="skel" style="height:58px; border-radius:6px;"></div>
        </div>
        <div class="form-footer">
            <div class="skel" style="width:70px; height:32px; border-radius:6px;"></div>
            <div class="skel" style="width:120px; height:32px; border-radius:6px;"></div>
        </div>
    </div>
</div>

<div class="skel-real">

{{-- ── Page Header ── --}}
<div class="page-header">
    <h1>
        <div class="page-title-icon">
            <svg viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="6" cy="5" r="2.5" stroke="#058028" stroke-width="1.2"/>
                <path d="M2 13c0-2.21 1.79-4 4-4s4 1.79 4 4" stroke="#058028" stroke-width="1.2" stroke-linecap="round"/>
                <path d="M11 7.5l1.5 1.5L15 6" stroke="#058028" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>
        Add Officer
    </h1>

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
                    <label for="officer-name" class="field-label">
                        <i class="fas fa-user"></i>
                        Full Name <span class="field-required">*</span>
                    </label>
                    <input id="officer-name"
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
                    <label for="officer-email" class="field-label">
                        <i class="fas fa-envelope"></i>
                        Email Address <span class="field-required">*</span>
                    </label>
                    <input id="officer-email"
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
                    <label for="officer-position-id" class="field-label">
                        <i class="fas fa-id-badge"></i>
                        Position <span class="field-required">*</span>
                    </label>
                    <div class="select-wrap">
                        <select id="officer-position-id"
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
                    <label for="officer-school-year" class="field-label">
                        <i class="fas fa-calendar"></i>
                        School Year <span class="field-required">*</span>
                    </label>
                    @if ($currentYear)
                        <input id="officer-school-year"
                            type="text"
                            name="school_year"
                            class="field-input mono"
                            value="{{ $currentYear }}"
                            readonly
                        >
                        <span class="field-hint"><i class="fas fa-circle-info"></i> The officers in office. The next school year starts at End Term.</span>
                    @else
                        <input id="officer-school-year"
                            type="text"
                            name="school_year"
                            class="field-input mono {{ $errors->has('school_year') ? 'is-invalid' : '' }}"
                            placeholder="2026-2027"
                            value="{{ old('school_year', \App\Support\SchoolYear::current()) }}"
                            required
                        >
                        <span class="field-hint"><i class="fas fa-circle-info"></i> Format: YYYY-YYYY</span>
                    @endif
                    @error('school_year')
                        <span class="field-error"><i class="fas fa-circle-exclamation"></i> {{ $message }}</span>
                    @enderror
                </div>
            </div>

            {{-- Divider --}}
            <div class="divider-label">Account Credentials</div>

            {{-- Password --}}
            <div class="field-group">
                <label for="passwordField" class="field-label">
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
                <span class="field-hint"><i class="fas fa-circle-info"></i> At least 8 characters, with upper and lower case letters, a number and a symbol. They choose their own at first sign-in.</span>
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