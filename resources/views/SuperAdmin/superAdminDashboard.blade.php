@extends('SuperAdmin.homeSuperAdmin')

@section('content')
<div class="container mt-4">

    <!-- Dashboard Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3>Super Admin Dashboard</h3>
        <span class="text-muted">{{ now()->format('M d, Y h:i A') }}</span>
    </div>

    <!-- Stats Cards -->
    <div class="row g-4">
        <!-- Users -->
        <div class="col-md-3">
            <div class="card shadow-sm text-center p-3">
                <i class="bi bi-people-fill fs-1 text-primary"></i>
                <h4 class="mt-2">{{ $users }}</h4>
                <p class="text-muted">Total Users</p>
            </div>
        </div>

        <!-- Folders -->
        <div class="col-md-3">
            <div class="card shadow-sm text-center p-3">
                <i class="bi bi-folder-fill fs-1 text-warning"></i>
                <h4 class="mt-2">{{ $folders }}</h4>
                <p class="text-muted">Total Folders</p>
            </div>
        </div>
    </div>

    
    </div>

</div>
@endsection