@extends('SuperAdmin.homeSuperAdmin')
@section('title', 'My Profile')

@section('content')

@include('Admin.partials.theme')

<style>
.profile-card {
    border-radius: 12px;
}

.profile-photo-preview {
    width: 110px;
    height: 110px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid #058028;
}
</style>

{{-- Skeleton placeholder, shown until the real content below is revealed --}}
<div class="skel-shell" aria-hidden="true">
    <div class="page-header">
        <div>
            <div class="skel skel-text" style="width:140px; height:22px; margin-bottom:8px;"></div>
            <div class="skel skel-text" style="width:240px;"></div>
        </div>
    </div>

    <div class="card profile-card p-4" style="max-width: 600px;">
        <div class="d-flex align-items-center gap-3 mb-4">
            <div class="skel skel-circle" style="width:110px; height:110px;"></div>
            <div style="flex:1;">
                <div class="skel skel-text" style="width:110px; margin-bottom:8px;"></div>
                <div class="skel" style="width:100%; height:36px; border-radius:6px;"></div>
            </div>
        </div>

        @for ($i = 0; $i < 6; $i++)
        <div class="mb-3">
            <div class="skel skel-text" style="width:90px; margin-bottom:8px;"></div>
            <div class="skel" style="width:100%; height:36px; border-radius:6px;"></div>
        </div>
        @endfor

        <div class="skel" style="width:140px; height:38px; border-radius:6px;"></div>
    </div>
</div>

<div class="container-fluid skel-real">

    <div class="page-header">
        <div>
            <h1>My Profile</h1>        </div>
    </div>

    <div class="card profile-card p-4" style="max-width: 600px;">

        <form action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="d-flex align-items-center gap-3 mb-4">
                <img class="profile-photo-preview"
                     src="{{ $user->profile_photo ? asset('storage/'.$user->profile_photo) : 'https://ui-avatars.com/api/?name='.urlencode($user->name) }}"
                     alt="Your current profile photo" id="photoPreview">

                <div>
                    <label for="photoInput" class="form-label mb-1">Profile Photo</label>
                    <input type="file" name="profile_photo" accept="image/*" class="form-control" id="photoInput">
                </div>
            </div>

            <div class="mb-3">
                <label for="profile-name" class="form-label">Name</label>
                <input id="profile-name" type="text" name="name" class="form-control" value="{{ old('name', $user->name) }}" required>
            </div>

            <div class="mb-3">
                <label for="profile-email" class="form-label">Email</label>
                <input id="profile-email" type="email" name="email" class="form-control" value="{{ old('email', $user->email) }}" required>
            </div>

            <div class="mb-3">
                <label class="form-label" for="profile-role">Role</label>
                <input type="text" id="profile-role" class="form-control" value="Super Administrator" disabled>
            </div>

            <hr>

            <p class="text-muted mb-2">Leave the password fields blank to keep your current password.</p>

            <div class="mb-3">
                <label for="profile-current-password" class="form-label">Current Password</label>
                <input id="profile-current-password" type="password" name="current_password" class="form-control">
            </div>

            <div class="mb-3">
                <label for="profile-password" class="form-label">New Password</label>
                <input id="profile-password" type="password" name="password" class="form-control">
            </div>

            <div class="mb-3">
                <label for="profile-password-confirmation" class="form-label">Confirm New Password</label>
                <input id="profile-password-confirmation" type="password" name="password_confirmation" class="form-control">
            </div>

            <button type="submit" class="btn btn-success">Save Changes</button>
        </form>

    </div>

</div>

<script>
    document.getElementById('photoInput').addEventListener('change', function (e) {
        const file = e.target.files[0];
        if (file) {
            document.getElementById('photoPreview').src = URL.createObjectURL(file);
        }
    });
</script>

@endsection
