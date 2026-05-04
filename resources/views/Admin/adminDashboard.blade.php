@extends('Admin.home')

@section('content')

<style>
.card-box {
    border-radius: 12px;
    padding: 20px;
    color: white;
}

.bg-total { background: #4e73df; }
.bg-visible { background: #1cc88a; }
.bg-hidden { background: #e74a3b; }

.card-box i {
    font-size: 28px;
}
</style>

<div class="container-fluid">

    <!-- Header -->
    <div class="mb-4">
        <h4>Admin Dashboard</h4>
        <p class="text-muted">Manage folders</p>
    </div>

    <!-- Stats -->
    <div class="row mb-4">

        <div class="col-md-4">
            <div class="card-box bg-total shadow">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6>Total Folders</h6>
                        <h3>{{ $totalFolders}}</h3>
                    </div>
                    <i class="fas fa-folder"></i>
                </div>
            </div>
        </div>

        

        
    </div>

    <!-- Quick Actions -->
    <div class="card shadow mb-4">
        <div class="card-header">
            <h5>Quick Actions</h5>
        </div>

        <div class="card-body">
            <a href="{{ route('folders.store') }}" class="btn btn-success">
                + Create Folder
            </a>

            <a href="{{ route('folders.index') }}" class="btn btn-primary">
                Manage Folders
            </a>
        </div>
    </div>

    <!-- Recent Folders -->
    <div class="card shadow">
        <div class="card-header">
            <h5>Recent Folders</h5>
        </div>

        <div class="card-body">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Folder Name</th>
                        
                        <th>Date Created</th>
                    </tr>
                </thead>
                <tbody>
    @forelse($recentFolders as $folder)
        <tr>
            <td>{{ $folder->name }}</td>
            <td>{{ $folder->created_at }}</td>
        </tr>
    @empty
        <tr>
            <td colspan="2">No folders found.</td>
        </tr>
    @endforelse
</tbody>
        </div>
    </div>

</div>

@endsection