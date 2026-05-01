@extends('SuperAdmin.homeSuperAdmin')

@section('content')

    <div class="row mb-3">
        <div class="col-md-12 d-flex justify-content-between align-items-center">
            <h4>SG Officers</h4>
            <a href="{{ route('officers.create') }}" class="btn btn-success">
                Add Officer
            </a>
        </div>
    </div>

<div class="container card">
    <div class="row">
        <div class="col-md-12">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Position</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>

                    @foreach ($users as $user)
                    <tr>
                        <td>{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td>{{ $user->position->position_name}}</td>
                        <td>
                            <a href="{{ route('officers.edit', $user->id) }}" class="btn btn-sm btn-primary">
    Edit
</a>
                        </td>
                    </tr>
                    @endforeach

                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection