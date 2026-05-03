@extends('SuperAdmin.homeSuperAdmin')

@section('content')

<div class="row mb-3">
    <div class="col-md-12 d-flex justify-content-between align-items-center">
        <h4>SG Officers</h4>
        <div>
            <button type="button" class="btn btn-warning me-2" onclick="showArchiveModal()">
                <i class="fas fa-archive"></i> End Current Term
            </button>
            <a href="{{ route('officers.create') }}" class="btn btn-success">
                Add Officer
            </a>
        </div>
    </div>
</div>

{{-- CURRENT OFFICERS --}}
<div class="container card">
    <div class="row">
        <div class="col-md-12">

            <h5 class="mt-2">Current Officers</h5>

            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Position</th>
                        <th>School Year</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach ($currentOfficers as $term)
                    <tr>
                        <td>{{ $term->user->name }}</td>
                        <td>{{ $term->user->email }}</td>
                        <td>{{ $term->position->position_name }}</td>
                        <td>{{ $term->school_year }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

        </div>
    </div>
</div>

{{-- FORMER OFFICERS --}}
<div class="container card mt-4">
    <div class="row">
        <div class="col-md-12">

            <h5 class="mt-2">Former Officers</h5>

            @if($formerOfficers->count() > 0)

                <table class="table table-striped table-bordered">
                    <thead class="table-secondary">
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Position</th>
                            <th>School Year</th>
                            <th>Term End</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach($formerOfficers as $term)
                        <tr>
                            <td>{{ $term->user->name}}</td>
                            <td>{{ $term->user->email }}</td>
                            <td>{{ $term->position->position_name }}</td>
                            <td>{{ $term->school_year }}</td>
                            <td>{{ $term->term_end }}</td>
                            <td>
                                <form action="{{ route('officers.reactivate', $term->id) }}" method="POST" style="display:inline-block;">
                                    @csrf
                                    @method('PATCH')
                                    <button class="btn btn-success btn-sm">
                                        Reactivate
                                    </button>
                                </form>

                                <form action="{{ route('officers.destroy', $term->id) }}" method="POST" style="display:inline-block;">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-danger btn-sm"
                                        onclick="return confirm('Delete this record permanently?')">
                                        Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>

            @else
                <div class="alert alert-info">
                    No former officers found.
                </div>
            @endif

        </div>
    </div>
</div>

{{-- END TERM MODAL --}}
<div id="archiveModal" style="display:none; position:fixed; z-index:9999; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5);">

    <div style="background:#fff; margin:15% auto; padding:20px; width:80%; max-width:500px; border-radius:5px;">

        <h5>End Current Term</h5>

        <p>
            Are you sure you want to end all current officer terms?
        </p>

        <p class="text-danger">
            This will move all active officers to former officers.
        </p>

        <div style="display:flex; justify-content:flex-end; gap:10px;">
            <button onclick="closeArchiveModal()" class="btn btn-secondary">Cancel</button>

            <form action="{{ route('officers.archiveAll') }}" method="POST">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-warning">
                    Yes, End Term
                </button>
            </form>
        </div>

    </div>

</div>

<script>
function showArchiveModal() {
    document.getElementById('archiveModal').style.display = 'block';
}

function closeArchiveModal() {
    document.getElementById('archiveModal').style.display = 'none';
}

window.onclick = function(event) {
    var modal = document.getElementById('archiveModal');
    if (event.target == modal) {
        modal.style.display = 'none';
    }
}
</script>

@endsection