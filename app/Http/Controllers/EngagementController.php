<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\EngagementEvent;
use App\Models\File;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Receives anonymous usage events from the public student page (sent with
 * navigator.sendBeacon, so the body is JSON as text/plain). Public and
 * CSRF-exempt by design - see bootstrap/app.php - but throttled, capped per
 * request, and every event is validated against an allow-list: unknown
 * types are dropped and ids must point at a real announcement or a
 * publicly visible document.
 */
class EngagementController extends Controller
{
    private const MAX_EVENTS_PER_REQUEST = 25;

    private const ANNOUNCEMENT_TYPES = ['announcement_view', 'announcement_open', 'attachment_download'];

    private const DOCUMENT_TYPES = ['document_preview', 'document_download'];

    public function store(Request $request): Response
    {
        if (! EngagementEvent::trackingAllowed($request)) {
            return response()->noContent();
        }

        $payload = json_decode($request->getContent(), true);
        $events = array_slice(is_array($payload['events'] ?? null) ? $payload['events'] : [], 0, self::MAX_EVENTS_PER_REQUEST);
        $events = array_filter($events, fn ($e) => is_array($e) && is_string($e['type'] ?? null));

        if (! $events) {
            return response()->noContent();
        }

        // Look up only the ids this batch mentions, in two queries.
        $ids = fn (array $types) => collect($events)
            ->filter(fn ($e) => in_array($e['type'], $types, true) && is_numeric($e['id'] ?? null))
            ->map(fn ($e) => (int) $e['id'])->unique()->values();
        $announcementIds = Announcement::whereIn('id', $ids(self::ANNOUNCEMENT_TYPES))->pluck('id')->flip();
        $documentIds = File::publiclyVisible()->whereIn('id', $ids(self::DOCUMENT_TYPES))->pluck('id')->flip();

        $device = EngagementEvent::deviceFrom($request->userAgent());
        $now = now();
        $rows = [];

        foreach ($events as $event) {
            $row = [
                'type' => $event['type'], 'subject_id' => null, 'device' => $device, 'source' => null,
                'first_today' => null, 'first_ever' => null, 'term' => null, 'results' => null, 'created_at' => $now,
            ];

            switch ($event['type']) {
                case 'visit':
                    $row['first_today'] = (bool) ($event['first_today'] ?? false);
                    $row['first_ever'] = (bool) ($event['first_ever'] ?? false);
                    $row['source'] = ($event['via'] ?? null) === 'push' ? 'push' : null;
                    break;

                case 'announcement_view':
                case 'announcement_open':
                case 'attachment_download':
                    if (! isset($announcementIds[(int) ($event['id'] ?? 0)])) {
                        continue 2;
                    }
                    $row['subject_id'] = (int) $event['id'];
                    break;

                case 'document_preview':
                case 'document_download':
                    if (! isset($documentIds[(int) ($event['id'] ?? 0)])) {
                        continue 2;
                    }
                    $row['subject_id'] = (int) $event['id'];
                    break;

                case 'search':
                    $term = mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) ($event['term'] ?? ''))));
                    if (mb_strlen($term) < 2) {
                        continue 2;
                    }
                    $row['term'] = mb_substr($term, 0, 60);
                    $row['results'] = max(0, min(100000, (int) ($event['results'] ?? 0)));
                    break;

                default:
                    continue 2; // unknown type, or one only the server records (notify_*, message_sent)
            }

            $rows[] = $row;
        }

        if ($rows) {
            EngagementEvent::insert($rows);
        }

        return response()->noContent();
    }
}
