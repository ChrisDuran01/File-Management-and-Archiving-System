@extends('SuperAdmin.homeSuperAdmin')

@section('content')
<div class="container card mt-3 p-3">
    <h4>Add Officer</h4>
    <form action="{{ route('officers.store') }}" method="POST">
        @csrf
        <div class="mb-3">
            <label>Name</label>
            <input type="text" name="name" class="form-control" required>
        </div>
        <div class="mb-3">
            <label>Email</label>
            <input type="email" name="email" class="form-control" required>
        </div>
        <div class="mb-3">
            <label>Position</label>
            <select name="position_id" class="form-control" required>
                <option value="">Select Position</option>
                @foreach ($positions as $position)
                    <option value="{{ $position->id }}">{{ $position->position_name }}</option>
                @endforeach
            </select>
        </div>
        <div class="mb-3">
            <label>Password</label>
            <input type="password" name="password" class="form-control" required>
        </div>
        <button class="btn btn-primary">Save Officer</button>
    </form>
</div>
@endsection