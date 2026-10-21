{{-- What search engines and link previews (Messenger, Facebook, Google) read.
     $seo comes from App\Support\StudentPageSeo. --}}
<title>{{ $seo['title'] }}</title>
<meta name="description" content="{{ $seo['description'] }}">
<link rel="canonical" href="{{ $seo['url'] }}">
@if(! empty($seo['noindex']))
    {{-- "Hide from Google" was ticked: still shareable, just not listed. --}}
    <meta name="robots" content="noindex">
@endif
@if(config('seo.google_site_verification'))
    <meta name="google-site-verification" content="{{ config('seo.google_site_verification') }}">
@endif

<meta property="og:site_name" content="{{ config('seo.site_name') }}">
<meta property="og:locale" content="en_PH">
<meta property="og:type" content="{{ $seo['type'] }}">
<meta property="og:title" content="{{ $seo['social_title'] ?? $seo['title'] }}">
<meta property="og:description" content="{{ $seo['description'] }}">
<meta property="og:url" content="{{ $seo['url'] }}">
<meta property="og:image" content="{{ $seo['image']['url'] }}">
<meta property="og:image:type" content="{{ $seo['image']['type'] }}">
<meta property="og:image:width" content="{{ $seo['image']['width'] }}">
<meta property="og:image:height" content="{{ $seo['image']['height'] }}">
<meta property="og:image:alt" content="{{ $seo['image']['alt'] }}">
@isset($seo['published'])
    <meta property="article:published_time" content="{{ $seo['published']->toIso8601String() }}">
    <meta property="article:modified_time" content="{{ $seo['modified']->toIso8601String() }}">
@endisset
<meta name="twitter:card" content="summary_large_image">

{{-- JSON_HEX_TAG turns < and > into </>, so a title can never close this tag. --}}
<script type="application/ld+json">@json($seo['schema'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP)</script>
