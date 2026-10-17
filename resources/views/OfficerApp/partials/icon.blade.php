{{--
    One outline icon. @include('OfficerApp.partials.icon', ['name' => 'folder'])
    Optional: size (px, default 20), stroke (default 1.75), attrs (raw extra
    attributes, e.g. 'class="chevron"' or 'data-icon-on hidden').
--}}
@php
    $iconPaths = [
        'home'     => '<path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"></path>',
        'folder'   => '<path d="M3 6a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>',
        'search'   => '<circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.5-3.5"></path>',
        'offline'  => '<path d="M12 3v11"></path><path d="m7 10 5 5 5-5"></path><path d="M5 20h14"></path>',
        'bell'     => '<path d="M6 9a6 6 0 0 1 12 0c0 6 2 7 2 7H4s2-1 2-7"></path><path d="M10 20a2 2 0 0 0 4 0"></path>',
        'chevron'  => '<path d="m9 6 6 6-6 6"></path>',
        'back'     => '<path d="M19 12H5"></path><path d="m11 6-6 6 6 6"></path>',
        'file'     => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"></path><path d="M14 3v5h5"></path>',
        'lock'     => '<rect x="5" y="11" width="14" height="9" rx="2"></rect><path d="M8 11V8a4 4 0 0 1 8 0v3"></path>',
        'check'    => '<circle cx="12" cy="12" r="9"></circle><path d="m8.5 12 2.5 2.5 4.5-5"></path>',
        'plus'     => '<path d="M12 5v14"></path><path d="M5 12h14"></path>',
        'camera'   => '<path d="M4 8h3l2-3h6l2 3h3a1 1 0 0 1 1 1v9a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1z"></path><circle cx="12" cy="13" r="3.5"></circle>',
        'wifi-off' => '<path d="M3 3l18 18"></path><path d="M8.5 16.5a5 5 0 0 1 7 0"></path><path d="M5 12.5a10 10 0 0 1 4-2.6"></path><path d="M19 12.5a10 10 0 0 0-5-2.9"></path><path d="M12 20h.01"></path>',
        'info'     => '<circle cx="12" cy="12" r="9"></circle><path d="M12 11v5"></path><path d="M12 8h.01"></path>',
        'archive'  => '<rect x="3" y="4" width="18" height="5" rx="1"></rect><path d="M5 9v9a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V9"></path><path d="M10 13h4"></path>',
        'calendar' => '<rect x="4" y="5" width="16" height="16" rx="2"></rect><path d="M8 3v4"></path><path d="M16 3v4"></path><path d="M4 10h16"></path>',
        'phone'    => '<rect x="7" y="2" width="10" height="20" rx="2"></rect><path d="M11 18h2"></path>',
        'share'    => '<circle cx="18" cy="5" r="2.5"></circle><circle cx="6" cy="12" r="2.5"></circle><circle cx="18" cy="19" r="2.5"></circle><path d="m8.2 13.3 7.6 4.4"></path><path d="m15.8 6.3-7.6 4.4"></path>',
        'link'     => '<path d="M10 13a5 5 0 0 0 7.07 0l3-3a5 5 0 0 0-7.07-7.07l-1.5 1.5"></path><path d="M14 11a5 5 0 0 0-7.07 0l-3 3a5 5 0 0 0 7.07 7.07l1.5-1.5"></path>',
        'close'    => '<path d="M6 6l12 12"></path><path d="M18 6 6 18"></path>',
    ];
@endphp
<svg aria-hidden="true" width="{{ $size ?? 20 }}" height="{{ $size ?? 20 }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="{{ $stroke ?? 1.75 }}" stroke-linecap="round" stroke-linejoin="round" {!! $attrs ?? '' !!}>{!! $iconPaths[$name] !!}</svg>
