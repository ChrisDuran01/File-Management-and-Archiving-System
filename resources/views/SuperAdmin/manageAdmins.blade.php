@extends('SuperAdmin.homeSuperAdmin')

@section('content')

<style>
    @import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap');

    :root {
        --surface:     #f7f8fc;
        --card:        #ffffff;
        --border:      #e8eaf0;
        --primary:     #534AB7;
        --primary-dim: #EEEDFE;
        --text-1:      #111827;
        --text-2:      #6b7280;
        --text-3:      #9ca3af;
        --shadow-sm:   0 1px 3px rgba(0,0,0,.06), 0 1px 2px rgba(0,0,0,.04);
        --radius:      12px;
        --radius-sm:   8px;

        --green:       #0F6E56;
        --green-dim:   #E1F5EE;
        --red:         #A32D2D;
        --red-dim:     #FCEBEB;
        --amber:       #854F0B;
        --amber-dim:   #FAEEDA;
        --blue:        #185FA5;
        --blue-dim:    #E6F1FB;
    }

    * { box-sizing: border-box; }
    body {
        background: var(--surface);
        font-family: 'DM Sans', sans-serif;
        color: var(--text-1);
    }

    /* ── Page Header ── */
    .page-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }
    .page-header h4 {
        font-size: 1.25rem;
        font-weight: 500;
        letter-spacing: -.3px;
        margin: 0;
        color: var(--text-1);
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .page-title-icon {
        width: 32px;
        height: 32px;
        border-radius: var(--radius-sm);
        background: var(--green-dim);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .page-title-icon svg {
        width: 16px;
        height: 16px;
    }

    .header-actions {
        display: flex;
        gap: 8px;
        align-items: center;
        flex-wrap: wrap;
    }

    /* ── Buttons ── */
    .btn-primary-custom {
        background: var(--green);
        border: none;
        border-radius: var(--radius-sm);
        color: #fff;
        padding: 7px 16px;
        font-size: 15px;
        font-weight: 500;
        font-family: 'DM Sans', sans-serif;
        cursor: pointer;
        transition: background .15s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        line-height: 1;
    }
    .btn-primary-custom:hover { background: #1d6923; color: #fff; }

    .btn-warning-custom {
        background: none;
        border: 1px solid #d97706;
        border-radius: var(--radius-sm);
        color: #92400e;
        padding: 7px 16px;
        font-size: 14px;
        font-weight: 500;
        font-family: 'DM Sans', sans-serif;
        cursor: pointer;
        transition: all .15s;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        line-height: 1;
    }
    .btn-warning-custom:hover { background: var(--amber-dim); }

    /* ── Card ── */
    .al-card {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        overflow: hidden;
        box-shadow: var(--shadow-sm);
        margin-bottom: 20px;
    }

    /* ── Card Header ── */
    .al-card-header {
        padding: 0.875rem 1.25rem;
        border-bottom: 1px solid var(--border);
        background: #f9fafb;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        flex-wrap: wrap;
    }
    .al-card-title {
        font-size: 15px;
        font-weight: 500;
        color: var(--text-1);
        display: flex;
        align-items: center;
        gap: 7px;
        margin: 0;
    }
    .al-card-title-dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: var(--primary);
        flex-shrink: 0;
    }
    .al-card-title-dot.active { background: var(--green); }
    .al-card-title-dot.former { background: var(--text-3); }

    /* ── Stats Chips ── */
    .al-stats {
        display: flex;
        gap: 8px;
        padding: 0.75rem 1.25rem;
        border-bottom: 1px solid var(--border);
        flex-wrap: wrap;
    }
    .al-chip {
        display: flex;
        align-items: center;
        gap: 5px;
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 50px;
        padding: 3px 10px;
        font-size: 12px;
        color: var(--text-2);
        box-shadow: var(--shadow-sm);
    }
    .al-chip i { font-size: 11px; color: var(--text-3); }

    /* ── Table ── */
    .al-body { overflow-x: auto; }

    .al-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 15px;
    }
    .al-table thead tr {
        border-bottom: 1px solid var(--border);
        background: #f9fafb;
    }
    .al-table thead th {
        padding: 10px 1.25rem;
        font-weight: 500;
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: .05em;
        color: var(--text-2);
        white-space: nowrap;
        text-align: left;
    }
    .al-table tbody tr {
        border-bottom: 1px solid var(--border);
        transition: background .1s;
    }
    .al-table tbody tr:last-child { border-bottom: none; }
    .al-table tbody tr:hover { background: var(--primary-dim); }

    .al-table td {
        padding: 0.875rem 1.25rem;
        color: var(--text-1);
        vertical-align: middle;
    }

    /* ── Name cell ── */
    .officer-name {
        font-weight: 500;
        color: var(--text-1);
    }
    .officer-email {
        font-size: 13px;
        font-family: 'DM Mono', monospace;
        color: var(--text-2);
        margin-top: 2px;
    }

    /* ── Position badge ── */
    .position-badge {
        display: inline-flex;
        align-items: center;
        padding: 2px 9px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 500;
        background: var(--green-dim);
        color: var(--green);
        letter-spacing: .02em;
    }

    /* ── School year ── */
    .sy-badge {
        font-size: 12px;
        font-family: 'DM Mono', monospace;
        background: #f9fafb;
        border: 1px solid var(--border);
        border-radius: 4px;
        padding: 2px 7px;
        color: var(--text-2);
    }

    /* ── Term end ── */
    .term-end {
        font-size: 12px;
        font-family: 'DM Mono', monospace;
        color: var(--text-2);
        display: flex;
        align-items: center;
        gap: 4px;
    }
    .term-end i { font-size: 12px; opacity: .6; }

    /* ── Action buttons ── */
    .actions-cell {
        display: flex;
        align-items: center;
        gap: 6px;
        flex-wrap: wrap;
    }
    .tbl-btn {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 11px;
        border-radius: var(--radius-sm);
        font-size: 13px;
        font-weight: 500;
        font-family: 'DM Sans', sans-serif;
        cursor: pointer;
        border: 1px solid transparent;
        transition: all .15s;
        text-decoration: none;
        line-height: 1.4;
        white-space: nowrap;
    }
    .tbl-btn i { font-size: 12px; }
    .tbl-btn.edit {
        background: var(--green-dim);
        color: var(--green);
        border-color: #d0cdf7;
    }
    .tbl-btn.edit:hover { background: #dddaf9; }
    .tbl-btn.end-term {
        background: var(--red-dim);
        color: var(--red);
        border-color: #f5c6c6;
    }
    .tbl-btn.end-term:hover { background: #f9d4d4; }
    .tbl-btn.reactivate {
        background: var(--green-dim);
        color: var(--green);
        border-color: #b8e8da;
    }
    .tbl-btn.reactivate:hover { background: #c9eedf; }
    .tbl-btn.delete {
        background: var(--amber-dim);
        color: var(--amber);
        border-color: var(--border);
    }
    .tbl-btn.delete:hover { background: var(--red-dim); color: var(--red); border-color: #f5c6c6; }

    /* ── Empty state ── */
    .al-empty {
        text-align: center;
        padding: 3rem 1rem;
        color: var(--text-3);
    }
    .al-empty i { font-size: 2rem; margin-bottom: 12px; display: block; }
    .al-empty p { margin: 0; font-size: .875rem; }

    /* ── Modal ── */
    .modal-overlay {
        display: none;
        position: fixed;
        z-index: 9999;
        left: 0; top: 0;
        width: 100%; height: 100%;
        background: rgba(17,24,39,.45);
        backdrop-filter: blur(2px);
        align-items: center;
        justify-content: center;
    }
    .modal-overlay.active { display: flex; }

    .modal-box {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        box-shadow: 0 20px 60px rgba(0,0,0,.15);
        padding: 28px;
        width: 90%;
        max-width: 440px;
        animation: modalIn .18s ease;
    }
    @keyframes modalIn {
        from { transform: translateY(10px) scale(.98); opacity: 0; }
        to   { transform: translateY(0) scale(1); opacity: 1; }
    }

    .modal-icon {
        width: 44px;
        height: 44px;
        border-radius: var(--radius-sm);
        background: var(--amber-dim);
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 16px;
    }
    .modal-icon i { font-size: 18px; color: var(--amber); }

    .modal-box h5 {
        font-size: 1rem;
        font-weight: 600;
        margin: 0 0 6px;
        color: var(--text-1);
    }
    .modal-box p {
        font-size: 14px;
        color: var(--text-2);
        line-height: 1.55;
        margin: 0 0 6px;
    }
    .modal-warning {
        display: flex;
        align-items: flex-start;
        gap: 8px;
        background: var(--red-dim);
        border: 1px solid #f5c6c6;
        border-radius: var(--radius-sm);
        padding: 10px 12px;
        font-size: 12px;
        color: var(--red);
        margin: 14px 0 20px;
    }
    .modal-warning i { margin-top: 1px; flex-shrink: 0; }

    .modal-actions {
        display: flex;
        justify-content: flex-end;
        gap: 8px;
    }
    .modal-cancel {
        background: none;
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        color: var(--text-2);
        padding: 7px 16px;
        font-size: 13px;
        font-family: 'DM Sans', sans-serif;
        cursor: pointer;
        transition: all .15s;
    }
    .modal-cancel:hover { border-color: var(--primary); color: var(--primary); }
    .modal-confirm {
        background: var(--amber);
        border: none;
        border-radius: var(--radius-sm);
        color: #fff;
        padding: 7px 16px;
        font-size: 13px;
        font-weight: 500;
        font-family: 'DM Sans', sans-serif;
        cursor: pointer;
        transition: background .15s;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .modal-confirm:hover { background: #633806; }
</style>

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

{{-- ── Page Header ── --}}
<div class="page-header">
    <h4>
        <div class="page-title-icon">
            <svg viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="6" cy="5" r="2.5" stroke="#534AB7" stroke-width="1.2"/>
                <path d="M2 13c0-2.21 1.79-4 4-4s4 1.79 4 4" stroke="#534AB7" stroke-width="1.2" stroke-linecap="round"/>
                <path d="M11 7.5l1.5 1.5L15 6" stroke="#534AB7" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>
        SG Officers
    </h4>

    <div class="header-actions">
        <button type="button" class="btn-warning-custom" onclick="showArchiveModal()">
            <i class="fas fa-clock-rotate-left"></i>
            End Current Term
        </button>
        <a href="{{ route('officers.create') }}" class="btn-primary-custom">
            <i class="fas fa-plus"></i>
            Add Officer
        </a>
    </div>
</div>

{{-- ── CURRENT OFFICERS ── --}}
<div class="al-card">

    <div class="al-card-header">
        <span class="al-card-title">
            <span class="al-card-title-dot active"></span>
            Current Officers
        </span>
        <span class="al-chip">
            <i class="fas fa-users"></i>
            {{ $currentOfficers->count() }} active
        </span>
    </div>

    <div class="al-body">
        @if($currentOfficers->count() > 0)
            <table class="al-table">
                <thead>
                    <tr>
                        <th>Officer</th>
                        <th>Position</th>
                        <th>School Year</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($currentOfficers as $term)
                    <tr>
                        <td>
                            <div class="officer-name">{{ $term->user->name }}</div>
                            <div class="officer-email">{{ $term->user->email }}</div>
                        </td>
                        <td>
                            <span class="position-badge">{{ $term->position->position_name }}</span>
                        </td>
                        <td>
                            <span class="sy-badge">{{ $term->school_year }}</span>
                        </td>
                        <td>
                            <div class="actions-cell">
                                <a href="{{ route('officers.edit', $term->id) }}" class="tbl-btn edit">
                                    <i class="fas fa-pen"></i> Edit
                                </a>

                                <form action="{{ route('officers.archiveOfficer', $term->id) }}" method="POST" style="margin:0;">
                                    @csrf
                                    @method('PATCH')
                                    <button type="button" class="tbl-btn end-term"
                                        onclick="confirmEndTerm(this.form, '{{ addslashes($term->user->name) }}')">
                                        <i class="fas fa-clock"></i> End Term
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="al-empty">
                <i class="fas fa-user-slash"></i>
                <p>No current officers found.</p>
            </div>
        @endif
    </div>

</div>

{{-- ── FORMER OFFICERS ── --}}
<div class="al-card">

    <div class="al-card-header">
        <span class="al-card-title">
            <span class="al-card-title-dot former"></span>
            Former Officers
        </span>
        <span class="al-chip">
            <i class="fas fa-archive"></i>
            {{ $formerOfficers->count() }} archived
        </span>
    </div>

    <div class="al-body">
        @if($formerOfficers->count() > 0)
            <table class="al-table">
                <thead>
                    <tr>
                        <th>Officer</th>
                        <th>Position</th>
                        <th>School Year</th>
                        <th>Term End</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($formerOfficers as $term)
                    <tr>
                        <td>
                            <div class="officer-name">{{ $term->user->name }}</div>
                            <div class="officer-email">{{ $term->user->email }}</div>
                        </td>
                        <td>
                            <span class="position-badge" style="background:#f3f4f6;color:var(--text-2);">
                                {{ $term->position->position_name }}
                            </span>
                        </td>
                        <td>
                            <span class="sy-badge">{{ $term->school_year }}</span>
                        </td>
                        <td>
                            <span class="term-end">
                                <i class="fas fa-calendar-xmark"></i>
                                {{ $term->term_end }}
                            </span>
                        </td>
                        <td>
                            <div class="actions-cell">
                                <form action="{{ route('officers.reactivate', $term->id) }}" method="POST" style="margin:0;">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="tbl-btn reactivate">
                                        <i class="fas fa-rotate-left"></i> Reactivate
                                    </button>
                                </form>

                                <!--<form action="{{ route('officers.destroy', $term->id) }}" method="POST" style="margin:0;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="button" class="tbl-btn delete"
                                        onclick="confirmDelete(this.form, '{{ addslashes($term->user->name) }}')">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </form>-->
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="al-empty">
                <i class="fas fa-folder-open"></i>
                <p>No former officers found.</p>
            </div>
        @endif
    </div>

</div>

{{-- ── END ALL TERMS MODAL ── --}}
<div id="archiveModal" class="modal-overlay" onclick="handleOverlayClick(event, 'archiveModal')">
    <div class="modal-box">
        <div class="modal-icon">
            <i class="fas fa-clock-rotate-left"></i>
        </div>
        <h5>End Current Term</h5>
        <p>This will conclude the active term for all current officers.</p>
        <div class="modal-warning">
            <i class="fas fa-triangle-exclamation"></i>
            All active officers will be moved to Former Officers.
        </div>
        <div class="modal-actions">
            <button onclick="closeModal('archiveModal')" class="modal-cancel">Cancel</button>
            <form action="{{ route('officers.archiveAll') }}" method="POST" style="margin:0;">
                @csrf
                @method('DELETE')
                <button type="submit" class="modal-confirm">
                    <i class="fas fa-check"></i> Yes, End Term
                </button>
            </form>
        </div>
    </div>
</div>

{{-- ── END SINGLE TERM MODAL ── --}}
<div id="endTermModal" class="modal-overlay" onclick="handleOverlayClick(event, 'endTermModal')">
    <div class="modal-box">
        <div class="modal-icon">
            <i class="fas fa-clock"></i>
        </div>
        <h5>End Officer's Term</h5>
        <p>You are about to end the term of <b id="endTermName"></b>.</p>
        <div class="modal-warning">
            <i class="fas fa-triangle-exclamation"></i>
            They will be moved to Former Officers.
        </div>
        <div class="modal-actions">
            <button onclick="closeModal('endTermModal')" class="modal-cancel">Cancel</button>
            <button type="button" class="modal-confirm" id="endTermConfirm" onclick="submitEndTerm()">
                <i class="fas fa-check"></i> Confirm
            </button>
        </div>
    </div>
</div>

{{-- ── DELETE MODAL ── --}}
<div id="deleteModal" class="modal-overlay" onclick="handleOverlayClick(event, 'deleteModal')">
    <div class="modal-box">
        <div class="modal-icon" style="background:var(--red-dim);">
            <i class="fas fa-trash" style="color:var(--red);"></i>
        </div>
        <h5>Delete Record</h5>
        <p>You are about to permanently delete the record of <b id="deleteOfficerName"></b>.</p>
        <div class="modal-warning">
            <i class="fas fa-triangle-exclamation"></i>
            This action cannot be undone.
        </div>
        <div class="modal-actions">
            <button onclick="closeModal('deleteModal')" class="modal-cancel">Cancel</button>
            <button type="button" class="modal-confirm" id="deleteConfirmBtn"
                onclick="submitDelete()"
                style="background:var(--red);">
                <i class="fas fa-trash"></i> Delete
            </button>
        </div>
    </div>
</div>

<script>
    let _endTermForm = null;
    let _deleteForm  = null;

    function showArchiveModal() {
        document.getElementById('archiveModal').classList.add('active');
    }

    function confirmEndTerm(form, name) {
        _endTermForm = form;
        document.getElementById('endTermName').textContent = name;
        document.getElementById('endTermModal').classList.add('active');
    }

    function submitEndTerm() {
        if (_endTermForm) _endTermForm.submit();
    }

    function confirmDelete(form, name) {
        _deleteForm = form;
        document.getElementById('deleteOfficerName').textContent = name;
        document.getElementById('deleteModal').classList.add('active');
    }

    function submitDelete() {
        if (_deleteForm) _deleteForm.submit();
    }

    function closeModal(id) {
        document.getElementById(id).classList.remove('active');
    }

    function handleOverlayClick(event, id) {
        if (event.target === document.getElementById(id)) {
            closeModal(id);
        }
    }
</script>

@endsection