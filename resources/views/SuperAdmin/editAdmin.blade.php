@extends('SuperAdmin.homeSuperAdmin')

@section('content')

<div class="container card p-4">
    <h4>Edit Officer</h4>

    <form action="{{ route('officers.update', $term->id) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="mb-3">
            <label>Name</label>
            <input type="text" name="name" class="form-control" value="{{ $user->name }}">
        </div>

        <div class="mb-3">
            <label>Email</label>
            <input type="email" name="email" class="form-control" value="{{ $user->email }}">
        </div>

        <div class="mb-3">
            <label>Position</label>
            <select name="position_id" class="form-control">
                @foreach ($positions as $position)
                    <option value="{{ $position->id }}"
                        {{ $term->position_id == $position->id ? 'selected' : '' }}>
                        {{ $position->position_name }}
                    </option>
                @endforeach
            </select>
        </div>

        <button class="btn btn-success">Update</button>
    </form>
</div>

@endsection