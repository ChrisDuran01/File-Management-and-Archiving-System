@extends('Student.home')

@section('content')
<div class="container-fluid px-3 px-md-4">

    <!-- 🔹 Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-3 gap-2">
        <h4 class="fw-bold m-0">📁 Student Dashboard</h4>

        <input type="text" id="search"
               class="form-control w-100 w-md-25"
               placeholder="Search allowed files...">
    </div>

    <!-- 🔹 Summary -->
    <div class="row g-3 mb-3">
        <div class="col-12 col-sm-6 col-lg-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <small>Available Files</small>
                    <h4 class="fw-bold">{{ $totalFiles }}</h4>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-lg-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <small>Recently Added</small>
                    <h4 class="fw-bold">{{ $recentFiles }}</h4>
                </div>
            </div>
        </div>
    </div>

    <!-- 🔹 Chart -->
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <div style="height:250px;">
                <canvas id="fileChart"></canvas>
            </div>
        </div>
    </div>

    <!-- 🔹 File Grid -->
    <div class="row g-3" id="fileContainer">
        @forelse($files as $file)
        <div class="col-12 col-sm-6 col-md-4 col-lg-3 file-item">
            <div class="card h-100 shadow-sm border-0">

                <div class="card-body p-3">

                    <!-- Icon -->
                    <div class="text-center mb-2">
                        <i class="bi bi-file-earmark-text fs-2 text-primary"></i>
                    </div>

                    <!-- Name -->
                    <h6 class="text-truncate mb-1">{{ $file->filename }}</h6>

                    <!-- Type -->
                    <span class="badge bg-info text-dark">
                        {{ strtoupper($file->type) }}
                    </span>

                    <!-- Date -->
                    <p class="text-muted small mt-2 mb-0">
                        {{ $file->created_at->format('M d, Y') }}
                    </p>
                </div>

                

            </div>
        </div>
        @empty
            <div class="text-center mt-5">
                <h6 class="text-muted">No approved files available</h6>
            </div>
        @endforelse
    </div>

</div>

<!-- 🔹 Scripts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const labels = @json($fileTypes->pluck('type'));
const data = @json($fileTypes->pluck('count'));

if (labels.length > 0) {
    new Chart(document.getElementById('fileChart'), {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: [
                    '#4e73df',
                    '#1cc88a',
                    '#36b9cc',
                    '#f6c23e',
                    '#e74a3b'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });
} else {
    console.warn("No data available for chart.");
}

// 🔍 Search filter
document.getElementById('search').addEventListener('keyup', function() {
    let value = this.value.toLowerCase();
    document.querySelectorAll('.file-item').forEach(item => {
        item.style.display = item.innerText.toLowerCase().includes(value) ? '' : 'none';
    });
});
</script>

