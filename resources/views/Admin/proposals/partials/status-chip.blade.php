{{-- A proposal's status as a chip (.chip from Admin/partials/theme): grey
     while nothing is decided, amber while it is being signed, red if it was
     not approved, green once approved or done. --}}
@php
    $tone = ['draft' => 'neutral', 'for_signing' => 'warning', 'not_approved' => 'danger', 'cancelled' => 'neutral'][$proposal->status] ?? '';
@endphp
<span class="chip {{ $tone }}">{{ $proposal->statusLabel() }}</span>
