@php
    $tabItems = [
        'home'    => ['Home', 'home', route('app.home')],
        'folders' => ['Folders', 'folder', route('app.folders')],
        'search'  => ['Search', 'search', route('app.search')],
        'offline' => ['Offline', 'offline', route('app.offline')],
    ];
@endphp
<nav class="tabs" aria-label="Main">
    @foreach ($tabItems as $key => [$label, $icon, $href])
        <a href="{{ $href }}" class="tab" @if ($tab === $key) aria-current="page" @endif>
            <span class="tab-icon">@include('OfficerApp.partials.icon', ['name' => $icon, 'size' => 22, 'stroke' => $tab === $key ? 2 : 1.75])</span>
            <span>{{ $label }}</span>
        </a>
    @endforeach
</nav>
