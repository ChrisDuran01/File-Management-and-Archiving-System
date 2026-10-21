{{-- Browser-tab icon: the Site Settings logo, cut round (App\Services\Favicon).
     The ?v= changes with the logo, so browsers fetch the new icon instead of
     keeping the old one. The officer phone app has its own (OfficerApp/layout). --}}
@php
    $faviconVersion = rescue(fn () => app(\App\Services\Favicon::class)->version(\App\Models\SiteSetting::current()), null, false);
@endphp
<link rel="icon" href="{{ route('favicon', $faviconVersion ? ['v' => $faviconVersion] : []) }}" sizes="16x16 32x32 48x48">
