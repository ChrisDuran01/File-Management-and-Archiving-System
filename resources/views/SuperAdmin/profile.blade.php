@extends('SuperAdmin.homeSuperAdmin')

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

<div class="container-fluid">

    <div class="mb-4">
        <h4>My Profile</h4>
        <p class="text-muted">Manage your account information</p>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card profile-card p-4" style="max-width: 600px;">

        <form action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="d-flex align-items-center gap-3 mb-4">
                <img class="profile-photo-preview"
                     src="{{ $user->profile_photo ? asset('storage/'.$user->profile_photo) : 'https://ui-avatars.com/api/?name='.urlencode($user->name) }}"
                     alt="profile photo" id="photoPreview">

                <div>
                    <label class="form-label mb-1">Profile Photo</label>
                    <input type="file" name="profile_photo" accept="image/*" class="form-control" id="photoInput">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Name</label>
                <input type="text" name="name" class="form-control" value="{{ old('name', $user->name) }}" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="{{ old('email', $user->email) }}" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Role</label>
                <input type="text" class="form-control" value="Super Administrator" disabled>
            </div>

            <hr>

            <p class="text-muted mb-2">Leave the password fields blank to keep your current password.</p>

            <div class="mb-3">
                <label class="form-label">Current Password</label>
                <input type="password" name="current_password" class="form-control">
            </div>

            <div class="mb-3">
                <label class="form-label">New Password</label>
                <input type="password" name="password" class="form-control">
            </div>

            <div class="mb-3">
                <label class="form-label">Confirm New Password</label>
                <input type="password" name="password_confirmation" class="form-control">
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
