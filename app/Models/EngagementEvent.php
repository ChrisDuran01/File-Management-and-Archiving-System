<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * One anonymous thing a visitor did on the public student page. See the
 * migration for what is (and deliberately isn't) stored.
 */
class EngagementEvent extends Model
{
    public const UPDATED_AT = null;

    /** Event types the student page may send, and what each one means. */
    public const TYPES = [
        'visit'               => 'Opened the student page',
        'announcement_view'   => 'Saw an announcement (once per device)',
        'announcement_open'   => 'Opened an announcement in full',
        'attachment_download' => 'Downloaded an announcement attachment',
        'document_preview'    => 'Previewed a public document',
        'document_download'   => 'Downloaded a public document',
        'search'              => 'Searched the documents',
        'notify_on'           => 'Turned on announcement notifications',
        'notify_off'          => 'Turned off announcement notifications',
        'message_sent'        => 'Sent a message with the contact form',
    ];

    protected $fillable = [
        'type', 'subject_id', 'device', 'source', 'first_today', 'first_ever', 'term', 'results', 'created_at',
    ];

    protected $casts = [
        'first_today' => 'boolean',
        'first_ever' => 'boolean',
        'created_at' => 'datetime',
    ];

    /**
     * Nothing is recorded for signed-in officers (their testing would skew
     * the numbers), for browsers that ask not to be tracked, or for search
     * engine and link-preview crawlers, which load the public pages (and
     * may run their scripts) once the sitemap lists them.
     */
    public static function trackingAllowed(Request $request): bool
    {
        return ! $request->user()
            && $request->header('DNT') !== '1'
            && $request->header('Sec-GPC') !== '1'
            && ! self::isCrawler($request->userAgent());
    }

    /**
     * "bot/" rather than just "bot" catches Googlebot/2.1, bingbot/2.0,
     * Applebot/0.1 and friends without matching phone names like "CUBOT".
     */
    public static function isCrawler(?string $userAgent): bool
    {
        return (bool) preg_match(
            '~bot/|bot;|crawler|spider|facebookexternalhit|Google-InspectionTool|Lighthouse|HeadlessChrome~i',
            (string) $userAgent
        );
    }

    /** Coarse device category from the user agent - the string itself is never stored. */
    public static function deviceFrom(?string $userAgent): string
    {
        $ua = (string) $userAgent;

        if (preg_match('/iPad|Tablet|PlayBook|Silk|Android(?!.*Mobile)/i', $ua)) {
            return 'tablet';
        }

        return preg_match('/Mobi|iPhone|iPod|Android|Opera Mini|IEMobile/i', $ua) ? 'mobile' : 'desktop';
    }

    /** Records a server-side event (e.g. a contact message was sent), if allowed. */
    public static function recordFor(Request $request, string $type, array $attributes = []): void
    {
        if (! self::trackingAllowed($request)) {
            return;
        }

        self::create(['type' => $type, 'device' => self::deviceFrom($request->userAgent())] + $attributes);
    }
}
