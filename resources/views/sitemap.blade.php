{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
{{-- For search engines (SeoController::sitemap). Announcements ticked "Hide from Google" are left out. --}}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url>
        <loc>{{ route('student.dashboard') }}</loc>
@if($pageUpdated)
        <lastmod>{{ $pageUpdated->toAtomString() }}</lastmod>
@endif
    </url>
@foreach($announcements as $announcement)
    <url>
        <loc>{{ route('student.announcement', [$announcement, $announcement->slug()]) }}</loc>
        <lastmod>{{ $announcement->updated_at->toAtomString() }}</lastmod>
    </url>
@endforeach
</urlset>
