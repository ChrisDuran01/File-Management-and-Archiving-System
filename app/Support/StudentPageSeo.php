<?php

namespace App\Support;

use App\Models\Announcement;
use App\Models\AnnouncementImage;
use App\Models\SiteSetting;
use App\Services\ShareCard;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * What the public pages tell search engines and link previews (Messenger,
 * Facebook, Google): title, description, address, picture and schema.org
 * data. Rendered by Student/partials/seo.blade.php.
 *
 * Built per request, never kept in StudentPageCache: every address in it
 * comes from route(), which uses the host the visitor came in on.
 */
class StudentPageSeo
{
    public static function home(SiteSetting $settings): array
    {
        $url = route('student.dashboard');
        $organization = self::organization($settings);

        return [
            'title' => config('seo.site_name').' · '.config('seo.campus'),
            'description' => config('seo.description'),
            'url' => $url,
            'type' => 'website',
            'image' => self::card($settings),
            'schema' => [
                '@context' => 'https://schema.org',
                '@graph' => [
                    $organization,
                    [
                        '@type' => 'WebSite',
                        'name' => config('seo.site_name'),
                        'url' => $url,
                        'inLanguage' => 'en-PH',
                        'publisher' => ['@id' => $organization['@id']],
                    ],
                ],
            ],
        ];
    }

    public static function announcement(Announcement $announcement, SiteSetting $settings): array
    {
        $url = route('student.announcement', [$announcement, $announcement->slug()]);
        $photos = $announcement->images->map(fn ($photo) => self::photo($photo, $announcement->title))->filter()->values();
        $image = $photos->first() ?? self::card($settings);
        $organization = self::organization($settings);
        $author = ['@type' => 'Organization', 'name' => $organization['name'], 'url' => $organization['url']];

        return [
            'title' => $announcement->title.' | '.config('seo.site_name'),
            'social_title' => $announcement->title,
            'description' => $announcement->summary(),
            'url' => $url,
            'type' => 'article',
            'image' => $image,
            'published' => $announcement->created_at,
            'modified' => $announcement->updated_at,
            'noindex' => $announcement->hide_from_search,
            'schema' => [
                '@context' => 'https://schema.org',
                '@graph' => [
                    [
                        '@type' => 'NewsArticle',
                        // Google shows at most 110 characters of a headline.
                        'headline' => Str::limit($announcement->title, 107),
                        'description' => $announcement->summary(),
                        'image' => $photos->isNotEmpty() ? $photos->pluck('url')->all() : [$image['url']],
                        'datePublished' => $announcement->created_at->toIso8601String(),
                        'dateModified' => $announcement->updated_at->toIso8601String(),
                        'author' => $author,
                        'publisher' => $author + ['logo' => ['@type' => 'ImageObject', 'url' => $organization['logo']]],
                        'mainEntityOfPage' => $url,
                        'inLanguage' => 'en-PH',
                    ],
                    [
                        '@type' => 'BreadcrumbList',
                        'itemListElement' => [
                            ['@type' => 'ListItem', 'position' => 1, 'name' => config('seo.site_name'), 'item' => route('student.dashboard')],
                            ['@type' => 'ListItem', 'position' => 2, 'name' => $announcement->title, 'item' => $url],
                        ],
                    ],
                ],
            ],
        ];
    }

    private static function organization(SiteSetting $settings): array
    {
        $home = route('student.dashboard');

        return [
            '@type' => 'Organization',
            '@id' => $home.'#organization',
            'name' => config('seo.site_name'),
            'alternateName' => 'Quirino State University '.config('seo.campus').' Student Government',
            'url' => $home,
            'logo' => $settings->logo_path ? asset('storage/'.$settings->logo_path) : asset('images/SG-logo.png'),
            'email' => config('seo.email'),
            'parentOrganization' => ['@type' => 'CollegeOrUniversity', 'name' => 'Quirino State University'],
        ];
    }

    /** The branded card (ShareCard), for pages without a photo of their own. */
    private static function card(SiteSetting $settings): array
    {
        return [
            'url' => route('share.card', ['v' => app(ShareCard::class)->version($settings)]),
            'width' => ShareCard::WIDTH,
            'height' => ShareCard::HEIGHT,
            'type' => 'image/jpeg',
            'alt' => config('seo.site_name'),
        ];
    }

    private static function photo(AnnouncementImage $photo, string $title): ?array
    {
        $size = @getimagesize(Storage::disk('public')->path($photo->path));
        if (! $size) {
            return null; // file missing on this server: fall back to the card
        }

        return [
            'url' => route('announcements.image', $photo->id),
            'width' => $size[0],
            'height' => $size[1],
            'type' => $size['mime'],
            'alt' => $title,
        ];
    }
}
