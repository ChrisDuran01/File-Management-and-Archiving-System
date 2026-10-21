@extends('SuperAdmin.homeSuperAdmin')
@section('title', 'Edit Officer')

@section('content')

@include('Admin.partials.theme')

{{-- Skeleton placeholder, shown until the real content below is revealed --}}
<div class="skel-shell" aria-hidden="true">
    <div class="container card p-4">
        <div class="skel skel-text" style="width:150px; margin-bottom:1rem;"></div>

        <div class="page-header">
            <div class="skel skel-text" style="width:130px; height:22px;"></div>
        </div>
        <div class="skel skel-text" style="width:50px; margin-bottom:8px;"></div>
        <div class="skel" style="width:100%; height:38px; border-radius:6px; margin-bottom:16px;"></div>
        <div class="skel skel-text" style="width:50px; margin-bottom:8px;"></div>
        <div class="skel" style="width:100%; height:38px; border-radius:6px; margin-bottom:16px;"></div>
        <div class="skel skel-text" style="width:70px; margin-bottom:8px;"></div>
        <div class="skel" style="width:100%; height:38px; border-radius:6px; margin-bottom:16px;"></div>
        <div class="skel" style="width:110px; height:38px; border-radius:6px;"></div>
    </div>
</div>

<div class="container card p-4 skel-real">
    @include('Admin.partials.back-link', ['backTo' => route('officers.index'), 'backLabel' => 'Officers'])

    <div class="page-header"><h1>Edit Officer</h1></div>

    <form action="{{ route('officers.update', $term->id) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="mb-3">
            <label for="officer-name">Name</label>
            <input id="officer-name" type="text" name="name" class="form-control" value="{{ $user->name }}">
        </div>

        <div class="mb-3">
            <label for="officer-email">Email</label>
            <input id="officer-email" type="email" name="email" class="form-control" value="{{ $user->email }}">
        </div>

        <div class="mb-3">
            <label for="officer-position-id">Position</label>
            <select id="officer-position-id" name="position_id" class="form-control">
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