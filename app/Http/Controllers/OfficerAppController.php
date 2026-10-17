<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Archive;
use App\Models\File;
use App\Models\Folder;
use App\Models\User;
use App\Services\FileResponse;
use App\Support\SchoolYear;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The officer phone app (/app): the handful of things an officer does on a
 * phone - find a file, read it, add one, and have files on hand without a
 * connection - as phone-sized screens over the same folders, files and
 * access rules as the website. Everything else (officers, reports, backups,
 * archiving, trash) stays on the website.
 *
 * Offline: the phone keeps copies of files in the browser's own storage
 * (see public/officer-app/app.js and public/officer-sw.js). sync() tells it
 * which ones to keep automatically and which it must drop; nothing here
 * ever stores "who has what offline" on the server.
 */
class OfficerAppController extends Controller
{
    /** How the phone shows a file, by extension - see the viewer in app.js. */
    private const KINDS = [
        'pdf'  => 'pdf',
        'docx' => 'docx',
        'jpg'  => 'image', 'jpeg' => 'image', 'png' => 'image', 'gif' => 'image', 'webp' => 'image',
        'txt'  => 'text', 'md' => 'text', 'csv' => 'text', 'log' => 'text',
    ];

    /**
     * The website's own sign-in page, so there is only one. It is served at
     * /app/login too because an installed app opens a page outside /app in
     * a browser bar instead of in the app.
     */
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('app.home');
        }

        return view('login', ['fromApp' => true]);
    }

    public function home()
    {
        $user = Auth::user();

        $recent = File::whereNotNull('last_accessed_at')
            ->with('folder')
            ->orderByDesc('last_accessed_at')
            ->limit(12)
            ->get()
            ->filter(fn (File $file) => $file->isAccessibleBy($user))
            ->take(3)
            ->map(fn (File $file) => $this->describe($file))
            ->values();

        // Most recently used first - but the ones this officer can open
        // ahead of any locked ones, which would otherwise take up the few
        // places on this screen.
        $all = $this->flagAccess(
            Folder::where('is_archived', false)
                ->withCount('file')
                ->orderByDesc('last_accessed_at')
                ->orderBy('name')
                ->get(),
            $user
        );

        $folders = $all->sortByDesc('accessible')->take(6)->values();

        return view('OfficerApp.home', [
            'recent'      => $recent,
            'folders'     => $folders,
            'moreFolders' => $all->count() > $folders->count(),
            'unread'      => $user->unreadNotifications()->count(),
        ]);
    }

    /** Who is signed in, plus sign out and install - kept off Home so Home stays about the files. */
    public function account()
    {
        $user = Auth::user();

        return view('OfficerApp.account', [
            'user'     => $user,
            'position' => DB::table('officer_terms')
                ->join('positions', 'officer_terms.position_id', '=', 'positions.id')
                ->where('officer_terms.user_id', $user->id)
                ->where('officer_terms.status', 'active')
                ->value('positions.position_name'),
        ]);
    }

    public function folders()
    {
        $user = Auth::user();

        return view('OfficerApp.folders', [
            'year'      => null,
            'folders'   => $this->flagAccess(Folder::where('is_archived', false)->withCount('file')->orderBy('name')->get(), $user),
            'rootFiles' => File::whereNull('folder_id')->where('is_archived', false)->latest()->get()->map(fn (File $file) => $this->describe($file)),
            'pastYears' => Folder::where('is_archived', true)
                ->whereNotNull('school_year')
                ->distinct()
                ->orderByDesc('school_year')
                ->pluck('school_year'),
        ]);
    }

    /** Folders stored away under a past school year - read-only, like on the website. */
    public function year(string $year)
    {
        abort_unless(SchoolYear::isValid($year), 404);

        $user = Auth::user();

        return view('OfficerApp.folders', [
            'year'      => $year,
            'folders'   => $this->flagAccess(Folder::where('is_archived', true)->where('school_year', $year)->withCount('file')->orderBy('name')->get(), $user),
            'rootFiles' => File::whereNull('folder_id')->where('is_archived', true)->where('school_year', $year)->latest()->get()->map(fn (File $file) => $this->describe($file)),
            'pastYears' => collect(),
        ]);
    }

    public function folder($id)
    {
        $folder = Folder::findOrFail($id);

        if (! $folder->isAccessibleBy(Auth::user())) {
            return redirect()->route('app.folders')->with('error', 'This folder is restricted. You do not have access to it.');
        }

        $folder->touchAccessed();

        return view('OfficerApp.folder', [
            'folder' => $folder,
            'files'  => File::where('folder_id', $folder->id)->with('folder')->latest()->get()->map(fn (File $file) => $this->describe($file)),
        ]);
    }

    public function addForm($id)
    {
        $folder = Folder::findOrFail($id);

        if (! $folder->isAccessibleBy(Auth::user())) {
            return redirect()->route('app.folders')->with('error', 'This folder is restricted. You do not have access to it.');
        }

        if ($folder->is_archived) {
            return redirect()->route('app.folder', $folder->id)->with('error', 'This folder is stored under a past school year and is read-only.');
        }

        return view('OfficerApp.add', ['folder' => $folder]);
    }

    /**
     * Hands the upload to the website's own FileController::store(), so a
     * file added from a phone goes through exactly the same checks and
     * steps (type check, cloud copy, text extraction, activity log, the
     * folder creator's notification) - only where it lands afterwards is
     * different. store() has already flashed its result message by the time
     * it returns.
     */
    public function storeFile(Request $request, $id)
    {
        $folder = Folder::findOrFail($id);

        abort_unless($folder->isAccessibleBy(Auth::user()), 403, 'This folder is restricted. You do not have access to it.');

        $request->merge(['folder_id' => $folder->id]);

        app()->call([app(FileController::class), 'store'], ['request' => $request]);

        return session()->has('error')
            ? redirect()->route('app.folder.add', $folder->id)
            : redirect()->route('app.folder', $folder->id);
    }

    public function file($id)
    {
        $file = File::with(['folder', 'officerTerm.user'])->findOrFail($id);

        if (! $file->isAccessibleBy(Auth::user())) {
            return redirect()->route('app.folders')->with('error', 'This file is in a restricted folder you do not have access to.');
        }

        $file->touchAccessed();

        return view('OfficerApp.file', [
            'file'     => $file,
            'meta'     => $this->describe($file),
            'archived' => null,
            'canShare' => $file->isShareableBy(Auth::user()),
        ]);
    }

    /**
     * A file's bytes, for the app's viewer and for the copy the phone keeps.
     * Same-origin on purpose: a signed cloud URL expires within the hour, so
     * the phone could never find its saved copy under it again.
     */
    public function raw($id)
    {
        $file = File::findOrFail($id);

        abort_unless($file->isAccessibleBy(Auth::user()), 403, 'This file is in a restricted folder you do not have access to.');

        return FileResponse::make($file);
    }

    public function search(Request $request, SearchController $search)
    {
        $query = trim((string) $request->input('query', ''));

        $results = ['folders' => collect(), 'files' => collect(), 'archives' => collect(), 'archivedFiles' => collect()];

        if ($query !== '') {
            [$folders, $files, $archives, , $archivedFiles] = $search->performSearch($query);

            $results = [
                'folders'       => $folders,
                'files'         => $files->map(fn (File $file) => $this->describe($file) + ['why' => $this->matchReason($file), 'snippet' => $file->ocr_snippet]),
                'archives'      => $archives,
                'archivedFiles' => $archivedFiles,
            ];
        }

        return view('OfficerApp.search', ['query' => $query] + $results);
    }

    /** What is inside one archived folder - view only, as on the website's Archives page. */
    public function archive($id)
    {
        $archive = Archive::findOrFail($id);

        $filenames = array_keys($archive->file_checksums ?? []);
        sort($filenames);

        return view('OfficerApp.archive', compact('archive', 'filenames'));
    }

    public function archivedFile($id, string $filename)
    {
        $archive = Archive::findOrFail($id);

        abort_unless(array_key_exists($filename, $archive->file_checksums ?? []), 404, 'File not found in this archive.');

        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return view('OfficerApp.file', [
            'file'     => null,
            'archived' => $archive,
            'meta'     => [
                'id'         => null,
                'name'       => $filename,
                'ext'        => $ext,
                'kind'       => self::KINDS[$ext] ?? 'other',
                'size'       => null,
                'size_label' => null,
                'folder'     => $archive->folder_name,
                'url'        => route('archives.streamFile', ['id' => $archive->id, 'filename' => $filename]),
            ],
        ]);
    }

    /** The one screen that still opens with no connection - filled in on the phone, from what it has saved. */
    public function offline()
    {
        return view('OfficerApp.offline');
    }

    public function notifications()
    {
        $user = Auth::user();

        $notifications = $user->notifications()->latest()->limit(30)->get()->map(fn ($n) => [
            'id'      => $n->id,
            'title'   => $n->data['title'] ?? 'Notification',
            'message' => $n->data['message'] ?? '',
            'read'    => $n->read_at !== null,
            'when'    => $n->created_at->diffForHumans(),
        ] + $this->appLink($n->data['link'] ?? null));

        return view('OfficerApp.notifications', compact('notifications'));
    }

    /**
     * The phone says which files it holds; this answers with the set it
     * should hold automatically and the ones it must delete.
     *
     * Automatic set: files in use this school year (nothing stored away
     * under a past one), outside restricted folders (an officer with access
     * keeps those by hand, so the most sensitive records don't spread to
     * every phone on their own), within the size limits in
     * config('officer_app.offline'). Newest first, so when the total limit
     * cuts the list short it is the older files that are left out.
     *
     * Revoked: anything the phone holds that this officer can no longer
     * open - trashed, archived away, or its folder restricted since.
     */
    public function sync(Request $request)
    {
        $user = Auth::user();

        $have = collect($request->input('have', []))->map(fn ($id) => (int) $id)->filter()->unique()->values();

        $maxFile  = config('officer_app.offline.auto_max_file_mb') * 1048576;
        $maxTotal = config('officer_app.offline.auto_total_mb') * 1048576;
        $total    = 0;
        $auto     = [];

        File::where('is_archived', false)
            ->where('size', '<=', $maxFile)
            ->where(function ($q) {
                $q->whereNull('folder_id')
                    ->orWhereHas('folder', fn ($folder) => $folder->where('is_restricted', false)->where('is_archived', false));
            })
            ->with('folder')
            ->latest('updated_at')
            ->get()
            ->each(function (File $file) use (&$total, &$auto, $maxTotal) {
                if ($total + $file->size > $maxTotal) {
                    return; // too big for what's left - a smaller, older file may still fit
                }

                $total += $file->size;
                $auto[] = $this->describe($file);
            });

        $stillOpen = File::whereIn('id', $have)
            ->with('folder')
            ->get()
            ->filter(fn (File $file) => $file->isAccessibleBy($user))
            ->pluck('id');

        return response()->json([
            'user'        => $user->id,
            'files'       => $auto,
            'revoked'     => $have->diff($stillOpen)->values(),
            'expire_days' => config('officer_app.offline.expire_days'),
        ]);
    }

    /**
     * The phone reporting, for the activity log, what it has done since it
     * last got through: how many files automatic saving stored (one line
     * for the lot - a line per file would bury the log every time an
     * officer opens the app), which ones an officer chose with "Keep
     * offline" (a line each, named), and each copy handed to another app -
     * Bluetooth, Quick Share, Messenger - often sent with no connection.
     * As with "Keep offline", only files this officer can open are written
     * under their name.
     */
    public function syncSaved(Request $request)
    {
        $data = $request->validate([
            'count'     => 'nullable|integer|min:0|max:1000',
            'kept'      => 'nullable|array|max:200',
            'kept.*'    => 'integer',
            'sent'      => 'nullable|array|max:200',
            'sent.*.id' => 'required|integer',
            'sent.*.at' => 'nullable|integer',
        ]);

        $user  = Auth::user();
        $count = (int) ($data['count'] ?? 0);

        if ($count > 0) {
            ActivityLog::create([
                'user_name'  => $user->name,
                'activity'   => 'Saved ' . $count . ' ' . Str::plural('file', $count) . ' on a phone for offline use',
                'ip_address' => $request->ip(),
            ]);
        }

        File::whereIn('id', $data['kept'] ?? [])
            ->with('folder')
            ->get()
            ->filter(fn (File $file) => $file->isAccessibleBy($user))
            ->each(fn (File $file) => ActivityLog::create([
                'user_name'  => $user->name,
                'activity'   => 'Kept file offline on a phone: ' . $file->filename,
                'ip_address' => $request->ip(),
            ]));

        $sent  = collect($data['sent'] ?? []);
        $files = File::whereIn('id', $sent->pluck('id')->unique())
            ->with('folder')
            ->get()
            ->filter(fn (File $file) => $file->isAccessibleBy($user))
            ->keyBy('id');

        $sent->each(function (array $entry) use ($files, $user, $request) {
            if ($file = $files->get($entry['id'])) {
                ActivityLog::create([
                    'user_name'  => $user->name,
                    'activity'   => 'Sent a copy of a file from a phone: ' . $file->filename . self::whenSent($entry['at'] ?? null),
                    'ip_address' => $request->ip(),
                ]);
            }
        });

        return response()->json(['ok' => true]);
    }

    /**
     * " (sent Oct 12, 3:40 PM)" when a send reaches the log well after it
     * happened, since the log line itself carries the time it arrived. A
     * time from the future or months back is the phone's clock, not news.
     */
    private static function whenSent(?int $at): string
    {
        if (! $at) {
            return '';
        }

        $when = Carbon::createFromTimestampMs($at)->setTimezone(config('app.timezone'));

        if ($when->gt(now()->subMinutes(5)) || $when->lt(now()->subDays(60))) {
            return '';
        }

        return ' (sent ' . $when->format('M j, g:i A') . ')';
    }

    /**
     * Everything a screen (or the phone's saved-files list) needs to show
     * one file and open it.
     */
    private function describe(File $file): array
    {
        $ext = strtolower(pathinfo($file->filename, PATHINFO_EXTENSION));

        return [
            'id'         => $file->id,
            'name'       => $file->filename,
            'ext'        => $ext,
            'kind'       => self::KINDS[$ext] ?? 'other',
            'size'       => (int) $file->size,
            'size_label' => self::sizeLabel((int) $file->size),
            'folder'     => $file->folder?->name ?? 'No folder',
            'date'       => $file->created_at?->format('M j, Y'),
            'opened'     => $file->last_accessed_at?->diffForHumans(),
            'pending'    => (bool) $file->is_pending_approval,
            // Changes when the stored file does (a rename, or a photo being
            // swapped for its searchable PDF), which is how the phone knows
            // a copy it saved earlier is out of date. Deliberately not
            // updated_at: merely opening a file stamps that (touchAccessed),
            // and every phone would download the file again for nothing.
            'version'    => substr(sha1($file->filename . '|' . $file->filepath . '|' . (int) $file->size), 0, 12),
            'page'       => route('app.file', $file->id),
            'url'        => route('app.file.raw', $file->id),
        ];
    }

    /**
     * Which tint a file's tile gets in a list (the .chip.* classes in
     * app.css; app.js has the same table for rows it draws itself).
     */
    public static function toneOf(?string $ext): string
    {
        return match (strtolower((string) $ext)) {
            'pdf'                               => 'pdf',
            'doc', 'docx'                       => 'doc',
            'xls', 'xlsx', 'csv'                => 'sheet',
            'ppt', 'pptx'                       => 'slide',
            'jpg', 'jpeg', 'png', 'gif', 'webp' => 'image',
            default                             => '',
        };
    }

    /** Marks which folders this officer can open; the rest still list, locked, as on the website. */
    private function flagAccess(Collection $folders, User $user): Collection
    {
        return $folders->each(function (Folder $folder) use ($user) {
            $folder->accessible = $folder->isAccessibleBy($user);
        });
    }

    private function matchReason(File $file): ?string
    {
        return match (true) {
            (bool) $file->matched_in_content     => 'Found in document text',
            (bool) $file->matched_in_folder      => 'Matched folder name',
            (bool) $file->matched_in_school_year => 'Matched school year',
            (bool) $file->matched_in_type        => 'Matched file type',
            default                              => null,
        };
    }

    /**
     * A notification's link points at a website page. The two that have a
     * screen in the app are sent there; the rest still open the website.
     */
    private function appLink(?string $link): array
    {
        $path = (string) parse_url((string) $link, PHP_URL_PATH);

        if (preg_match('#/folders/(\d+)$#', $path, $m)) {
            return ['link' => route('app.folder', $m[1]), 'website' => false];
        }

        if (preg_match('#/files/(\d+)/preview$#', $path, $m)) {
            return ['link' => route('app.file', $m[1]), 'website' => false];
        }

        return ['link' => $link ?: null, 'website' => (bool) $link];
    }

    /** PHP's intl extension isn't available on this stack, so sizes are formatted by hand. */
    public static function sizeLabel(int $bytes): string
    {
        return match (true) {
            $bytes >= 1073741824 => round($bytes / 1073741824, 1) . ' GB',
            $bytes >= 1048576    => round($bytes / 1048576, 1) . ' MB',
            $bytes >= 1024       => round($bytes / 1024) . ' KB',
            default              => $bytes . ' B',
        };
    }
}
