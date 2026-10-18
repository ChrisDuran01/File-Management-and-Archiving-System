{{-- One batch of Activity Logs rows - the first page render and every
     "older" batch the page fetches while scrolling (SuperAdminController).
     $hasMore keeps the timeline line running into the next batch. --}}
@forelse($logs as $log)
    @php
        $activity = strtolower($log->activity);
        $type = match(true) {
            str_contains($activity, 'upload')                   => 'upload',
            str_contains($activity, 'added')                    => 'upload',
            str_contains($activity, 'delete')                   => 'delete',
            str_contains($activity, 'rename')                   => 'rename',
            str_contains($activity, 'public') ||
                str_contains($activity, 'private') ||
                str_contains($activity, 'access')               => 'access',
            str_contains($activity, 'login') ||
                str_contains($activity, 'logged')               => 'login',
            default                                             => 'login',
        };
        $badgeLabel = match($type) {
            'upload' => 'Upload',
            'delete' => 'Delete',
            'rename' => 'Rename',
            'access' => 'Access',
            'login'  => 'Login',
            default  => ucfirst($type),
        };
    @endphp

    <div class="al-log-item log-row">

        {{-- Timeline dot + connector --}}
        <div class="al-timeline-col">
            <div class="al-dot {{ $type }}"></div>
            @if(! ($loop->last && ! $hasMore))
                <div class="al-line"></div>
            @endif
        </div>

        {{-- Content --}}
        <div class="al-content">
            <div class="al-meta">
                <span class="al-time">
                    {{ $log->created_at->format('M d, Y · h:i A') }}
                </span>
                <span class="al-ip">
                    <i class="fas fa-globe"></i>
                    {{ $log->ip_address ?? 'Unknown' }}
                </span>
            </div>
            <div class="al-text">
                <b>{{ $log->user_name }}</b>
                <span class="al-detail">{{ $log->activity }}</span>
                <span class="al-badge {{ $type }}">{{ $badgeLabel }}</span>
            </div>
        </div>

    </div>
@empty
    @if($firstLoad ?? true)
        <div class="al-empty">
            <i class="fas fa-folder-open"></i>
            <p>No activity logs found.</p>
        </div>
    @endif
@endforelse
