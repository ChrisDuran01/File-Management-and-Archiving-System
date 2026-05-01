@extends('SuperAdmin.homeSuperAdmin')

@section('content')

<div class="container-fluid">
    <h4 class="mb-4">Backup Management</h4>

    <!-- Backup Settings -->
    <div class="card mb-4 p-3">
        <div class="d-flex align-items-center gap-3">
            
            <label class="mb-0">Backup files</label>

            <!-- Toggle -->
            <form method="POST" action="{{ route('backup.toggle') }}">
                @csrf
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" 
                        name="backup_enabled" 
                        onchange="this.form.submit()"
                        {{ $backupEnabled ? 'checked' : '' }}>
                </div>
            </form>

            <!-- Frequency Dropdown -->
            <form method="POST" action="{{ route('backup.frequency') }}">
                @csrf
                <select name="frequency" class="form-select form-select-sm w-auto"
                        onchange="this.form.submit()">
                    <option value="daily" {{ $frequency == 'daily' ? 'selected' : '' }}>Every day</option>
                    <option value="weekly" {{ $frequency == 'weekly' ? 'selected' : '' }}>Weekly</option>
                    <option value="monthly" {{ $frequency == 'monthly' ? 'selected' : '' }}>Monthly</option>
                </select>
            </form>

            <!-- Manual Backup Button -->
            <form method="POST" action="{{ route('backup.create') }}">
                @csrf
                <button class="btn btn-sm btn-primary">
                    Backup Now
                </button>
            </form>

        </div>
    </div>

    <!-- Filter Section -->
    <div class="card mb-4 p-3">
        <div class="row align-items-end">
            <div class="col-md-4">
                <label class="form-label fw-bold">Search</label>
                <input type="text" id="searchInput" class="form-control" 
                       placeholder="Search by name or status...">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold">Status Filter</label>
                <select id="statusFilter" class="form-select">
                    <option value="all">All Status</option>
                    <option value="Success">Success</option>
                    <option value="Failed">Failed</option>
                    <option value="Pending">Pending</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold">Date Range</label>
                <select id="dateFilter" class="form-select">
                    <option value="all">All Time</option>
                    <option value="today">Today</option>
                    <option value="yesterday">Yesterday</option>
                    <option value="last7days">Last 7 Days</option>
                    <option value="last30days">Last 30 Days</option>
                    <option value="thisMonth">This Month</option>
                </select>
            </div>
            <div class="col-md-2">
                <button id="resetFilters" class="btn btn-secondary w-100">
                    Reset Filters
                </button>
            </div>
        </div>
    </div>

    <!-- Backup Table -->
    <div class="card p-3">
        <div class="table-responsive">
            <table class="table table-bordered align-middle" id="backupTable">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Size</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="backupTableBody">
                    @forelse($backups as $backup)
                        <tr data-name="{{ strtolower($backup->name) }}" 
                            data-status="{{ $backup->status }}" 
                            data-date="{{ $backup->created_at->format('Y-m-d') }}">
                            <td>{{ $backup->name }}</td>
                            <td>{{ $backup->size }}</td>
                            <td>{{ $backup->created_at->format('M d, Y') }}</td>
                            <td>
                                <span class="badge bg-{{ $backup->status == 'Success' ? 'success' : ($backup->status == 'Failed' ? 'danger' : 'warning') }}">
                                    {{ $backup->status }}
                                </span>
                            </td>
                            <td>
                                <a href="{{ route('backup.download', $backup->id) }}" 
                                   class="btn btn-sm btn-success">
                                    Download
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr id="noDataRow">
                            <td colspan="5" class="text-center text-muted">
                                No backups found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        <!-- Results Count -->
        <div class="mt-3 text-muted small" id="resultsCount">
            Showing <span id="visibleCount">{{ $backups->count() }}</span> of {{ $backups->count() }} backups
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const statusFilter = document.getElementById('statusFilter');
    const dateFilter = document.getElementById('dateFilter');
    const resetBtn = document.getElementById('resetFilters');
    const tableBody = document.getElementById('backupTableBody');
    const visibleCountSpan = document.getElementById('visibleCount');
    const noDataRow = document.getElementById('noDataRow');
    
    function filterTable() {
        const searchTerm = searchInput.value.toLowerCase();
        const statusValue = statusFilter.value;
        const dateValue = dateFilter.value;
        
        const rows = tableBody.querySelectorAll('tr');
        let visibleCount = 0;
        const today = new Date();
        
        rows.forEach(row => {
            // Skip if it's the no data row
            if (row.id === 'noDataRow') return;
            
            const name = row.getAttribute('data-name') || '';
            const status = row.getAttribute('data-status') || '';
            const rowDate = row.getAttribute('data-date') || '';
            
            let showRow = true;
            
            // Search filter
            if (searchTerm && !name.includes(searchTerm) && !status.toLowerCase().includes(searchTerm)) {
                showRow = false;
            }
            
            // Status filter
            if (statusValue !== 'all' && status !== statusValue) {
                showRow = false;
            }
            
            // Date filter
            if (dateValue !== 'all' && rowDate) {
                const rowDateObj = new Date(rowDate);
                const todayDate = new Date(today.getFullYear(), today.getMonth(), today.getDate());
                const rowDateOnly = new Date(rowDateObj.getFullYear(), rowDateObj.getMonth(), rowDateObj.getDate());
                
                switch(dateValue) {
                    case 'today':
                        if (rowDateOnly.getTime() !== todayDate.getTime()) showRow = false;
                        break;
                    case 'yesterday':
                        const yesterday = new Date(todayDate);
                        yesterday.setDate(yesterday.getDate() - 1);
                        if (rowDateOnly.getTime() !== yesterday.getTime()) showRow = false;
                        break;
                    case 'last7days':
                        const sevenDaysAgo = new Date(todayDate);
                        sevenDaysAgo.setDate(sevenDaysAgo.getDate() - 7);
                        if (rowDateOnly < sevenDaysAgo) showRow = false;
                        break;
                    case 'last30days':
                        const thirtyDaysAgo = new Date(todayDate);
                        thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
                        if (rowDateOnly < thirtyDaysAgo) showRow = false;
                        break;
                    case 'thisMonth':
                        if (rowDateObj.getMonth() !== today.getMonth() || 
                            rowDateObj.getFullYear() !== today.getFullYear()) {
                            showRow = false;
                        }
                        break;
                }
            }
            
            if (showRow) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        
        // Update visible count
        visibleCountSpan.textContent = visibleCount;
        
        // Show/hide no data message
        if (visibleCount === 0 && tableBody.querySelectorAll('tr:not([style*="display: none"]):not(#noDataRow)').length === 0) {
            if (!document.getElementById('noDataMessage')) {
                const noDataMessage = document.createElement('tr');
                noDataMessage.id = 'noDataMessage';
                noDataMessage.innerHTML = '<td colspan="5" class="text-center text-muted">No backups match your filters.</td>';
                tableBody.appendChild(noDataMessage);
            }
        } else {
            const noDataMessage = document.getElementById('noDataMessage');
            if (noDataMessage) noDataMessage.remove();
        }
    }
    
    // Add event listeners
    searchInput.addEventListener('keyup', filterTable);
    statusFilter.addEventListener('change', filterTable);
    dateFilter.addEventListener('change', filterTable);
    
    // Reset filters
    resetBtn.addEventListener('click', function() {
        searchInput.value = '';
        statusFilter.value = 'all';
        dateFilter.value = 'all';
        filterTable();
    });
    
    // Initial filter (just in case)
    filterTable();
});
</script>

<style>
    /* Smooth transitions */
    .table tbody tr {
        transition: all 0.3s ease;
    }
    
    /* Custom select styling */
    .form-select, .form-control {
        border-radius: 0.375rem;
    }
    
    /* Filter section styling */
    .card .row .col-md-4,
    .card .row .col-md-3,
    .card .row .col-md-2 {
        margin-bottom: 10px;
    }
    
    @media (min-width: 768px) {
        .card .row .col-md-4,
        .card .row .col-md-3,
        .card .row .col-md-2 {
            margin-bottom: 0;
        }
    }
</style>

@endsection