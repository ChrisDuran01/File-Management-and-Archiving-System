@extends('SuperAdmin.homeSuperAdmin')
@section('title', 'Edit Officer')

@section('content')

@include('Admin.partials.theme')

@include('SuperAdmin.partials.officer-form-styles')

@php
    // Your own password is changed from Profile, not reset here.
    $isSelf = $user->is(auth()->user());
@endphp

{{-- Skeleton placeholder, shown until the real content below is revealed --}}
<div class="skel-shell" aria-hidden="true">
    <div class="skel skel-text" style="width:150px; margin-bottom:1rem;"></div>
    <div class="page-header">
        <div class="skel skel-text" style="width:130px; height:22px;"></div>
    </div>

    <div class="al-card">
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
        </div>
        <div class="form-footer">
            <div class="skel" style="width:70px; height:32px; border-radius:6px;"></div>
            <div class="skel" style="width:120px; height:32px; border-radius:6px;"></div>
        </div>
    </div>

    <div class="al-card">
        <div class="al-card-header">
            <div class="skel skel-text" style="width:130px;"></div>
        </div>
        <div class="form-body">
            <div class="skel skel-text" style="width:80%;"></div>
            <div class="skel" style="height:58px; border-radius:6px;"></div>
        </div>
    </div>
</div>

<div class="skel-real">

@include('Admin.partials.back-link', ['backTo' => route('officers.index'), 'backLabel' => 'Officers'])

{{-- ── Page Header ── --}}
<div class="page-header">
    <h1>
        <div class="page-title-icon">
            <svg viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="6" cy="5" r="2.5" stroke="#058028" stroke-width="1.2"/>
                <path d="M2 13c0-2.21 1.79-4 4-4 .9 0 1.73.3 2.4.8" stroke="#058028" stroke-width="1.2" stroke-linecap="round"/>
                <path d="M10 13.5l.4-1.7 3-3a.9.9 0 0 1 1.3 1.3l-3 3-1.7.4z" stroke="#058028" stroke-width="1.2" stroke-linejoin="round"/>
            </svg>
        </div>
        Edit Officer
    </h1>
</div>

{{-- ── Officer Information ── --}}
<div class="al-card">

    <div class="al-card-header">
        <span class="al-card-title">
            <span class="al-card-title-dot"></span>
            Officer Information
        </span>
    </div>

    <form action="{{ route('officers.update', $term->id) }}" method="POST" novalidate>
        @csrf
        @method('PUT')

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
                        value="{{ old('name', $user->name) }}"
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
                        value="{{ old('email', $user->email) }}"
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
                            @foreach ($positions as $position)
                                <option value="{{ $position->id }}" @selected(old('position_id', $term->position_id) == $position->id)>
                                    {{ $position->position_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    @error('position_id')
                        <span class="field-error"><i class="fas fa-circle-exclamation"></i> {{ $message }}</span>
                    @enderror
                </div>

                {{-- Locked: End Term ends a school year's officers together. --}}
                <div class="field-group">
                    <label for="officer-school-year" class="field-label">
                        <i class="fas fa-calendar"></i>
                        School Year
                    </label>
                    <input id="officer-school-year"
                        type="text"
                        class="field-input mono"
                        value="{{ $term->school_year }}"
                        aria-describedby="officer-school-year-hint"
                        readonly
                    >
                    <span class="field-hint" id="officer-school-year-hint"><i class="fas fa-lock"></i> Can't be changed here</span>
                </div>
            </div>

        </div>

        {{-- Footer --}}
        <div class="form-footer">
            <a href="{{ route('officers.index') }}" class="btn-cancel">
                Cancel
            </a>
            <button type="submit" class="btn-save">
                <i class="fas fa-check"></i> Save changes
            </button>
        </div>

    </form>

</div>

{{-- ── Reset Password ── --}}
<div class="al-card">

    <div class="al-card-header">
        <span class="al-card-title">
            <span class="al-card-title-dot"></span>
            Reset Password
        </span>
    </div>

    @if ($isSelf)
        <div class="form-body">
            <p class="form-note">This is your own account. Change your password from <a href="{{ route('profile.edit') }}">Profile</a>.</p>
        </div>
    @else
        <form action="{{ route('officers.resetPassword', $term->id) }}" method="POST" novalidate>
            @csrf
            @method('PATCH')

            <div class="form-body">
                <p class="form-note">
                    For when {{ $user->name }} can't use "Forgot password". Give them this temporary password:
                    they must choose a new one at their next login, and any device where they are logged in is logged out.
                </p>

                <div class="field-group">
                    <label for="passwordField" class="field-label">
                        <i class="fas fa-lock"></i>
                        Temporary Password <span class="field-required">*</span>
                    </label>
                    <div class="input-wrap">
                        <input
                            type="password"
                            name="password"
                            id="passwordField"
                            class="field-input {{ $errors->has('password') ? 'is-invalid' : '' }}"
                            autocomplete="new-password"
                            aria-describedby="passwordHint"
                            required
                        >
                        <button type="button" class="toggle-pw" onclick="togglePassword()" title="Show or hide the password" aria-label="Show or hide the password">
                            <i class="fas fa-eye" id="pwIcon"></i>
                        </button>
                    </div>
                    <span class="field-hint" id="passwordHint"><i class="fas fa-circle-info"></i> At least 8 characters, with upper and lower case letters, a number and a symbol</span>
                    @error('password')
                        <span class="field-error"><i class="fas fa-circle-exclamation"></i> {{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="form-footer">
                <button type="submit" class="btn-save">
                    <i class="fas fa-key"></i> Reset password
                </button>
            </div>
        </form>
    @endif

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
