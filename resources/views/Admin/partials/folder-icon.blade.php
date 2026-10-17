{{--
    Two-tone folder icon for folder cards (Folders page, School Year history).
    Back panel + tab, a sheet of paper, and a gradient front flap that dips a
    little when the .folder-card is hovered.

        @include('Admin.partials.folder-icon')
        @include('Admin.partials.folder-icon', ['locked' => true])
--}}
@php($gid = 'fi-grad-' . \Illuminate\Support\Str::random(8))

@once
<style>
.folder-icon {
    position: relative;
    display: inline-block;
    line-height: 0;
    margin: 6px 0 4px;
}
.folder-icon svg { overflow: visible; }
.folder-icon .fi-paper,
.folder-icon .fi-flap {
    transform-box: fill-box;
    transform-origin: bottom center;
    transition: transform .2s ease;
}
.folder-card:hover .folder-icon .fi-paper { transform: translateY(-2px); }
.folder-card:hover .folder-icon .fi-flap  { transform: scaleY(.9); }
@media (prefers-reduced-motion: reduce) {
    .folder-icon .fi-paper,
    .folder-icon .fi-flap { transition: none; }
}
.folder-icon-lock {
    position: absolute;
    right: -6px;
    bottom: -4px;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    line-height: 1;
    color: #6c757d;
    background: var(--card, #fff);
    box-shadow: 0 0 0 1px var(--border, #e8eaf0), 0 1px 3px rgba(0,0,0,.12);
}
[data-theme="dark"] .folder-icon .fi-paper { fill: #e9e4d8; }
[data-theme="dark"] .folder-icon-lock { color: var(--text-2); }
</style>
@endonce

<span class="folder-icon">
    <svg width="72" height="58" viewBox="0 0 72 58" aria-hidden="true" focusable="false">
        <defs>
            <linearGradient id="{{ $gid }}" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="#FFD766"/>
                <stop offset="1" stop-color="#F8B820"/>
            </linearGradient>
        </defs>
        {{-- back panel + tab --}}
        <path d="M6 4h18.3a4 4 0 0 1 3.1 1.5l3.6 4.5a4 4 0 0 0 3.1 1.5H66a4 4 0 0 1 4 4V52a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8a4 4 0 0 1 4-4z"
              fill="#E39A0E"/>
        {{-- paper --}}
        <rect class="fi-paper" x="8" y="15" width="56" height="26" rx="2.5"
              fill="#ffffff" stroke="rgba(0,0,0,.06)"/>
        {{-- front flap --}}
        <g class="fi-flap">
            <rect x="2" y="21" width="68" height="35" rx="4" fill="url(#{{ $gid }})"/>
            <path d="M6 21.8H66" stroke="rgba(255,255,255,.6)" stroke-width="1.2" stroke-linecap="round"/>
        </g>
    </svg>
    @if(!empty($locked))
        <span class="folder-icon-lock" title="Restricted"><i class="bi bi-lock-fill"></i></span>
    @endif
</span>
