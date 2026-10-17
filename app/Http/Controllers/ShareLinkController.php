<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsurePhone;
use App\Models\ActivityLog;
use App\Models\File;
use App\Models\FileShareLink;
use App\Models\User;
use App\Services\FileResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Share links: an officer makes a link to one file (website Share dialog -
 * partials/share-link - or the phone app's Share sheet), and anyone who has
 * the link can open and download that file without an account until it
 * expires or is turned off. Who may make one: File::isShareableBy(). When a
 * link stops working: FileShareLink::opensFile().
 *
 * Making and turning off a link go in the activity log under the officer's
 * name. Opening one is only counted on the link: the person opening it has no
 * account, and their IP address would be personal data kept for nothing.
 */
class ShareLinkController extends Controller
{
    /** Sent with every page and file a link opens. */
    private const PUBLIC_HEADERS = [
        'Cache-Control'   => 'no-store, private',
        'Referrer-Policy' => 'no-referrer',     // the link is the key - never pass it on
        'X-Robots-Tag'    => 'noindex, nofollow',
    ];

    /** Chat apps fetch a link to draw its preview; that isn't someone opening it. */
    private const LINK_PREVIEWERS = '/bot|crawl|spider|facebookexternalhit|facebookcatalog|whatsapp|telegram|slack|discord|viber|skypeuripreview|linkedin|embedly|preview/i';

    /** The Share dialog's contents for one file: can this officer make a link, and the links already made. */
    public function index($id)
    {
        $user = Auth::user();
        $file = File::with('folder')->findOrFail($id);

        abort_unless($file->isAccessibleBy($user), 403, 'This file is in a restricted folder you don\'t have access to.');

        $links = $file->shareLinks()->live()->with('creator')->latest()->get()
            ->filter(fn (FileShareLink $link) => $link->opensFile())
            ->map(fn (FileShareLink $link) => $this->describe($link, $file, $user))
            ->values();

        return response()->json([
            'can_create' => $file->isShareableBy($user),
            'reason'     => $file->isShareableBy($user) ? null
                : 'This file is in a restricted folder. Only the folder\'s creator or the Adviser/President can make a link for it.',
            'pending'    => (bool) $file->is_pending_approval,
            'durations'  => collect(FileShareLink::DURATIONS)->map(fn (int $days) => [
                'days'    => $days,
                'label'   => $days === 1 ? '1 day' : $days . ' days',
                'default' => $days === FileShareLink::DEFAULT_DURATION,
            ]),
            'links'      => $links,
        ]);
    }

    public function store(Request $request, $id)
    {
        $user = Auth::user();
        $file = File::with('folder')->findOrFail($id);

        abort_unless($file->isShareableBy($user), 403, 'Only the folder\'s creator or the Adviser/President can make a link for a file in a restricted folder.');

        $data = $request->validate([
            'days' => ['required', 'integer', Rule::in(FileShareLink::DURATIONS)],
        ]);

        $link = FileShareLink::createFor($file, $user, (int) $data['days']);

        ActivityLog::create([
            'user_name'  => $user->name,
            'activity'   => 'Made a share link for file: ' . $file->filename . ' (works for ' . $data['days'] . ' ' . ($data['days'] == 1 ? 'day' : 'days') . ')',
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['link' => $this->describe($link->load('creator'), $file, $user)], 201);
    }

    /** Turn a link off - the officer who made it, or whoever manages the file. */
    public function destroy(Request $request, FileShareLink $link)
    {
        $user = Auth::user();
        $file = File::withTrashed()->with('folder')->find($link->file_id);

        abort_unless($link->created_by === $user->id || ($file && $file->isManageableBy($user)), 403,
            'Only the officer who made this link, the folder\'s creator or the Adviser/President can turn it off.');

        if (! $link->revoked_at) {
            $link->forceFill(['revoked_at' => now(), 'revoked_by' => $user->id])->save();

            ActivityLog::create([
                'user_name'  => $user->name,
                'activity'   => 'Turned off a share link for file: ' . ($file?->filename ?? 'a deleted file'),
                'ip_address' => $request->ip(),
            ]);
        }

        return response()->json(['ok' => true]);
    }

    /* --------------------------- What the link opens --------------------------- */

    public function show(Request $request, string $token)
    {
        $link = FileShareLink::findByToken($token);
        $file = $link?->opensFile();

        if (! $file) {
            return $this->unavailable($link);
        }

        if (! preg_match(self::LINK_PREVIEWERS, (string) $request->userAgent())) {
            $link->increment('open_count', 1, ['last_opened_at' => now()]);
        }

        $ext = strtolower(pathinfo($file->filename, PATHINFO_EXTENSION));

        return response()->view('Share.show', [
            'token'   => $token,
            'link'    => $link,
            'file'    => $file,
            'ext'     => $ext,
            'size'    => OfficerAppController::sizeLabel((int) $file->size),
            'tone'    => OfficerAppController::toneOf($ext),
            // Phone browsers don't show a PDF inside a page - they get an Open button instead.
            'preview' => match (true) {
                in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true) => 'image',
                $ext === 'pdf' && ! EnsurePhone::isPhone($request)           => 'pdf',
                default                                                     => null,
            },
        ])->withHeaders(self::PUBLIC_HEADERS);
    }

    /** The file itself: inline for the page's preview, or ?download=1 to save it. */
    public function file(Request $request, string $token)
    {
        $link = FileShareLink::findByToken($token);
        $file = $link?->opensFile();

        if (! $file) {
            return $this->unavailable($link);
        }

        $download = $request->boolean('download');

        if ($download) {
            $link->increment('download_count');
        }

        return FileResponse::make($file, $download, self::PUBLIC_HEADERS);
    }

    /**
     * Expired, turned off and never-existed all read the same: nothing here
     * tells a guesser which links are real. A page, not an error, because
     * whoever sees it has no account and nothing else to go on.
     */
    private function unavailable(?FileShareLink $link)
    {
        return response()->view('Share.unavailable', [], $link ? 410 : 404)->withHeaders(self::PUBLIC_HEADERS);
    }

    private function describe(FileShareLink $link, File $file, User $user): array
    {
        return [
            'id'           => $link->id,
            'url'          => $link->url(),
            'mine'         => $link->created_by === $user->id,
            'made_by'      => $link->creator?->name,
            'until'        => $link->expires_at->format('M j, g:i A'),
            'opened'       => (int) $link->open_count,
            'downloads'    => (int) $link->download_count,
            'can_turn_off' => $link->created_by === $user->id || $file->isManageableBy($user),
            'turn_off_url' => route('share-links.destroy', $link->id),
        ];
    }
}
