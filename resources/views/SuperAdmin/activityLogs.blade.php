@extends('SuperAdmin.homeSuperAdmin')
@section('content')

<div class="">
    <h4>Activity Logs</h4>
       
</div>
<div class="container mt-4">
    <div class="row justify-content-start">
        <div class="col-md-4">
            <!-- Tall Activity Logs Card -->
            <div class="card" style="height: 80vh; overflow-y: auto;">
                <!-- Card Header with Date Filter -->
                <div class="card-header d-flex justify-content-between align-items-center">
                
    <form method="GET" action="{{ route('activity.logs') }}" class="d-flex align-items-center gap-2">
        
        <input type="date" 
               class="form-control form-control-sm" 
               name="from_date" 
               value="{{ request('from_date') }}">

        -

        <input type="date" 
               class="form-control form-control-sm" 
               name="to_date" 
               value="{{ request('to_date') }}">

        <button type="submit" class="btn btn-sm btn-primary">
            Filter
        </button>

    </form>

                </div>

                <!-- Card Body with Logs -->
<div class="card-body p-2">

    @if($logs->count() > 0)
        @foreach($logs as $log)
            <div class="border-bottom p-2">
                <small class="text-muted">
                    {{ $log->created_at->format('M d, Y h:i A') }}
                </small>

                <div>
                    <b>{{ $log->user_name }}</b> - {{ $log->activity }}
                </div>
            </div>
        @endforeach
    @else
        <div class="text-center text-muted p-2">
            No activity logs found.
        </div>
    @endif

</div>
                    
                </div>
            </div>
        </div>
    </div>
</div>
@endsection