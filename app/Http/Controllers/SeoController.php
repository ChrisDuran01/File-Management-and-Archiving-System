<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\File;
use App\Models\SiteSetting;
use App\Services\Favicon;
use App\Services\ShareCard;
use Carbon\Carbon;

/**
 * What search engines and browsers fetch on their own: the sitemap,
 * robots.txt, the tab icon and the branded card used as the preview
 * picture of pages without a photo. All public, all built from the host
 * the request came in on, so they point at the right address wherever the
 * site is deployed.
 */
class SeoController extends Controller
{
    /** The student page and every announcement that may be listed. */
    public function sitemap()
    {
        $announcements = Announcement::where('hide_from_search', false)
            ->latest()
            ->get(['id', 'title', 'updated_at']);

        $pageUpdated = collect([
            Announcement::max('updated_at'),
            File::publiclyVisible()->max('updated_at'),
        ])->filter()->map(fn ($date) => Carbon::parse($date))->max();

        return response()
            ->view('sitemap', compact('announcements', 'pageUpdated'))
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    /**
     * Served by the app rather than as public/robots.txt, because the
     * Sitemap line must be a full address and the domain isn't known until
     * the site is deployed.
     */
    public function robots()
    {
        return response("User-agent: *\nDisallow:\n\nSitemap: ".route('sitemap')."\n")
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    /** The ?v= in links to this only makes a new logo a new address; any value gets today's card. */
    public function shareCard(ShareCard $card)
    {
        return response()->file($card->path(SiteSetting::current()), [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }

    /** Same as the share card: ?v= only makes a new logo a new address. */
    public function favicon(Favicon $favicon)
    {
        return response()->file($favicon->path(SiteSetting::current()), [
            'Content-Type' => 'image/x-icon',
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }
}
