<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Folder;
use App\Models\File;
use App\Models\Backup;
use App\Models\OfficerTerm;
use App\Models\ActivityLog;
use App\Models\Archive;
use App\Models\EngagementEvent;
use App\Models\Position;
use App\Models\PushSubscription;
use App\Models\ScanBatch;
use App\Models\SiteSetting;
use App\Services\BackupScheduler;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class DashboardController extends Controller
{
    /** Failed sign-ins in a week before the dashboard flags them. */
    private const FAILED_SIGNIN_ALERT = 3;

    /** A folder untouched this long (and not archived) is flagged. */
    private const STALE_FOLDER_DAYS = 90;

    /**
     * SuperAdmin dashboard: headline numbers, 30-day activity, what needs
     * attention, backups, recent uploads and activity, storage by file type,
     * and a snapshot of the public student page.
     */
    public function superAdminDashboard()
    {
        return $this->dashboard(true);
    }

    /**
     * The same dashboard for an Admin, with only what an Admin may open:
     * nothing from the SuperAdmin pages (officer accounts, activity log,
     * backups), and no file from a restricted folder they can't open.
     */
    public function adminDashboard()
    {
        return $this->dashboard(false);
    }

    private function dashboard(bool $superAdmin)
    {
        $now = now();
        $user = Auth::user();

        $hour = (int) $now->format('G');
        $greeting = ($hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening'))
            . ', ' . trim($user->name);

        return view('Admin.dashboard', [
            'layout'        => $superAdmin ? 'SuperAdmin.homeSuperAdmin' : 'Admin.home',
            'isSuperAdmin'  => $superAdmin,
            'greeting'      => $greeting,
            'kpis'          => $this->kpis($now, $superAdmin),
            'activityChart' => $this->activityChart($now, $superAdmin),
            'attention'     => $this->needsAttention($now, $superAdmin),
            'health'        => $superAdmin ? $this->systemHealth($now) : null,
            'recentUploads' => $this->recentUploads($user),
            'activities'    => $superAdmin ? ActivityLog::latest()->take(8)->get() : null,
            'storageByType' => $this->storageByType(),
            'studentPage'   => $this->studentPageSnapshot($now),
        ]);
    }

    /** The latest uploads the viewer may open - a restricted folder's files stay out. */
    private function recentUploads(User $user)
    {
        return File::with('folder')
            ->latest()
            ->take(40)
            ->get(['id', 'filename', 'size', 'folder_id', 'created_at', 'is_pending_approval'])
            ->filter(fn (File $file) => $file->isAccessibleBy($user))
            ->take(6)
            ->values();
    }

    /**
     * The headline cards: four for a SuperAdmin, three for an Admin (no
     * officer accounts). Every card has the same shape so the view can
     * render them in one loop: label, icon, value, an optional change chip,
     * a note, and an optional trend for the sparkline.
     */
    private function kpis(Carbon $now, bool $superAdmin): array
    {
        // Storage at the end of each of the last 6 months. Grouped here, not
        // in SQL, so it runs on any database (the tests use SQLite).
        $trendStart = $now->copy()->startOfMonth()->subMonthsNoOverflow(5);
        $running = (float) File::where('created_at', '<', $trendStart)->sum('size');
        $addedByMonth = File::where('created_at', '>=', $trendStart)
            ->get(['created_at', 'size'])
            ->groupBy(fn (File $file) => $file->created_at->format('Y-m'))
            ->map(fn ($files) => $files->sum('size'));
        $storageTrend = [];
        for ($month = $trendStart->copy(); $month->lte($now); $month->addMonthNoOverflow()) {
            $running += (float) ($addedByMonth[$month->format('Y-m')] ?? 0);
            $storageTrend[] = ['label' => $month->format('M Y'), 'value' => $running, 'display' => $this->formatBytes($running)];
        }
        $addedThisMonth = (float) File::where('created_at', '>=', $now->copy()->startOfMonth())->sum('size');

        // Uploads per 7-day window for the last 8 weeks, ending now - so the
        // last point is the same "this week" number shown on the card.
        $recentUploads = File::where('created_at', '>=', $now->copy()->subDays(56))->pluck('created_at');
        $uploadTrend = [];
        for ($i = 7; $i >= 0; $i--) {
            $end = $now->copy()->subDays(7 * $i);
            $start = $end->copy()->subDays(7);
            $n = $recentUploads->filter(fn ($t) => $t->gte($start) && ($i === 0 || $t->lt($end)))->count();
            $uploadTrend[] = [
                'label'   => $start->copy()->addDay()->format('M j') . '–' . $end->format('M j'),
                'value'   => $n,
                'display' => $n . ' ' . Str::plural('file', $n),
            ];
        }
        $thisWeek = $uploadTrend[7]['value'];
        $lastWeek = $uploadTrend[6]['value'];

        $cards = [
            [
                'label' => 'Storage used',
                'icon'  => 'bi-hdd-stack',
                'href'  => '/reports',
                'value' => $this->formatBytes((float) File::sum('size')),
                'chip'  => $addedThisMonth > 0 ? ['dir' => 'up', 'text' => '+' . $this->formatBytes($addedThisMonth)] : null,
                'note'  => $addedThisMonth > 0 ? 'added this month' : 'Nothing added this month',
                'trend' => $storageTrend,
            ],
            [
                'label' => 'Files',
                'icon'  => 'bi-file-earmark-text',
                'href'  => '/folders',
                'value' => number_format(File::count()),
                'chip'  => $thisWeek > 0 ? ['dir' => 'up', 'text' => '+' . number_format($thisWeek)] : null,
                'note'  => ($thisWeek > 0 ? 'this week' : 'None added this week') . ' · ' . number_format($lastWeek) . ' the week before',
                'trend' => $uploadTrend,
            ],
            [
                'label' => 'Folders',
                'icon'  => 'bi-folder2',
                'href'  => '/folders',
                'value' => number_format(Folder::count()),
                'chip'  => null,
                'note'  => number_format(Folder::where('is_archived', true)->count()) . ' archived · '
                    . number_format(Folder::where('is_restricted', true)->count()) . ' restricted',
                'trend' => null,
            ],
        ];

        // Officer accounts are a SuperAdmin page.
        if ($superAdmin) {
            $activeOfficers = OfficerTerm::where('status', 'active')->count();
            $formerOfficers = OfficerTerm::where('status', 'former')->count();
            $users = User::count();

            $cards[] = [
                'label' => 'Active officers',
                'icon'  => 'bi-person-badge',
                'href'  => '/manageAdmins',
                'value' => number_format($activeOfficers),
                'chip'  => null,
                'note'  => number_format($formerOfficers) . ' former · ' . number_format($users) . ' ' . Str::plural('user account', $users),
                'trend' => null,
            ];
        }

        return $cards;
    }

    /**
     * Files uploaded per day for the last 30 days, and for a SuperAdmin the
     * actions logged too (the activity log is a SuperAdmin page).
     */
    private function activityChart(Carbon $now, bool $superAdmin): array
    {
        $start = $now->copy()->subDays(29)->startOfDay();
        $perDay = fn ($query) => $query->where('created_at', '>=', $start)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as n')
            ->groupBy('day')
            ->pluck('n', 'day');
        $uploads = $perDay(File::query());
        $actions = $superAdmin ? $perDay(ActivityLog::query()) : null;

        $chart = ['labels' => [], 'uploads' => [], 'actions' => $superAdmin ? [] : null];
        for ($day = $start->copy(); $day->lte($now); $day->addDay()) {
            $chart['labels'][] = $day->format('M j');
            $chart['uploads'][] = (int) ($uploads[$day->toDateString()] ?? 0);
            if ($superAdmin) {
                $chart['actions'][] = (int) ($actions[$day->toDateString()] ?? 0);
            }
        }
        $chart['totalUploads'] = array_sum($chart['uploads']);
        $chart['totalActions'] = $superAdmin ? array_sum($chart['actions']) : 0;

        return $chart;
    }

    /**
     * Things someone should act on, most serious first. Empty means all clear.
     * Each item: level (danger|warning|info), icon, count (or null), text, link.
     * Backups and failed sign-ins lead to SuperAdmin pages, so an Admin
     * doesn't get them.
     */
    private function needsAttention(Carbon $now, bool $superAdmin): array
    {
        $items = [];
        $add = function (string $level, string $icon, ?int $count, string $text, string $href) use (&$items) {
            $items[] = compact('level', 'icon', 'count', 'text', 'href');
        };

        if ($n = Archive::where('fixity_status', 'failed')->count()) {
            $add('danger', 'bi-shield-exclamation', $n, Str::plural('archive', $n) . ' failed the integrity check', '/archives');
        }
        if ($n = ScanBatch::where('status', 'failed')->count()) {
            $add('danger', 'bi-x-octagon', $n, Str::plural('scan batch', $n) . ' failed to process', '/documents/scans');
        }

        if ($superAdmin) {
            $latestBackup = Backup::latest()->first();
            if (! $latestBackup) {
                $add('danger', 'bi-cloud-slash', null, 'No backup has been made yet', '/backup');
            } elseif ($latestBackup->created_at->lt($now->copy()->subDays(7))) {
                $add('warning', 'bi-cloud-arrow-up', null, 'Last backup was ' . $latestBackup->created_at->diffForHumans(), '/backup');
            }
        }

        if ($n = ScanBatch::where('status', 'ready_for_review')->count()) {
            $add('warning', 'bi-inboxes', $n, Str::plural('scan batch', $n) . ' waiting for review', '/documents/scans');
        }

        if ($superAdmin) {
            $failedSignIns = ActivityLog::whereIn('activity', [
                    'Failed login attempt',
                    'Login blocked - too many failed attempts',
                    'Login attempt on disabled (former officer) account',
                ])
                ->where('created_at', '>=', $now->copy()->subDays(7))
                ->count();
            if ($failedSignIns >= self::FAILED_SIGNIN_ALERT) {
                $add('warning', 'bi-shield-lock', $failedSignIns, 'failed sign-in attempts in the last 7 days', '/activityLogs');
            }
        }

        if ($n = File::where('ocr_status', 'failed')->count()) {
            $add('warning', 'bi-file-earmark-x', $n, Str::plural('file', $n) . " couldn't be read for text search", '/reports');
        }

        // Only copies the viewer can open and still delete: a past school
        // year's files are read-only.
        $user = Auth::user();
        $copies = File::flaggedAsCopy()->where('is_archived', false)->with('folder')->get()
            ->filter(fn (File $file) => $file->isAccessibleBy($user))
            ->count();
        if ($copies) {
            $add('info', 'bi-files', $copies, ($copies === 1 ? 'file looks' : 'files look') . ' like a copy of another file', '/reports');
        }

        $staleCutoff = $now->copy()->subDays(self::STALE_FOLDER_DAYS);
        $stale = Folder::where('is_archived', false)
            ->where('created_at', '<', $staleCutoff)
            ->where(fn ($q) => $q->where('last_accessed_at', '<', $staleCutoff)->orWhereNull('last_accessed_at'))
            ->count();
        if ($stale) {
            $add('info', 'bi-hourglass-split', $stale, Str::plural('folder', $stale) . ' untouched for ' . self::STALE_FOLDER_DAYS . '+ days', '/reports');
        }

        return $items;
    }

    /**
     * Whether backups are running. Each row: label, status
     * (ok|warning|danger) and a short detail. The background services
     * (scheduler, queue worker) aren't shown: officers can't act on them.
     */
    private function systemHealth(Carbon $now): array
    {
        $rows = [];

        $latest = Backup::latest()->first();
        $rows[] = ['label' => 'Last backup'] + match (true) {
            ! $latest => ['status' => 'danger', 'detail' => 'None yet'],
            $latest->status !== 'Success' => ['status' => 'warning', 'detail' => $latest->status . ', ' . $latest->created_at->diffForHumans()],
            $latest->created_at->lt($now->copy()->subDays(7)) => ['status' => 'warning', 'detail' => $latest->created_at->diffForHumans()],
            default => ['status' => 'ok', 'detail' => $latest->created_at->diffForHumans() . ($latest->size ? ' · ' . $latest->size : '')],
        };

        $settings = SiteSetting::current();
        $schedules = BackupScheduler::DESCRIPTIONS;
        $rows[] = ['label' => 'Automatic backups'] + (($settings->backup_enabled ?? true)
            ? ['status' => 'ok', 'detail' => $schedules[$settings->backup_frequency ?? 'daily'] ?? ucfirst((string) $settings->backup_frequency)]
            : ['status' => 'warning', 'detail' => 'Turned off']);

        return $rows;
    }

    /** Stored bytes per broad file type, largest first. */
    private function storageByType(): array
    {
        $rows = File::selectRaw("CASE
                WHEN LOWER(filename) LIKE '%.pdf' THEN 'PDFs'
                WHEN LOWER(filename) LIKE '%.png' OR LOWER(filename) LIKE '%.jpg' OR LOWER(filename) LIKE '%.jpeg'
                  OR LOWER(filename) LIKE '%.gif' OR LOWER(filename) LIKE '%.webp' THEN 'Images'
                WHEN LOWER(filename) LIKE '%.doc' OR LOWER(filename) LIKE '%.docx' OR LOWER(filename) LIKE '%.xls'
                  OR LOWER(filename) LIKE '%.xlsx' OR LOWER(filename) LIKE '%.ppt' OR LOWER(filename) LIKE '%.pptx' THEN 'Office files'
                ELSE 'Other' END as kind, COUNT(*) as files, COALESCE(SUM(size), 0) as bytes")
            ->groupBy('kind')
            ->orderByDesc('bytes')
            ->get();
        $total = (float) $rows->sum('bytes');

        return $rows->map(fn ($r) => [
            'kind'    => $r->kind,
            'files'   => (int) $r->files,
            'size'    => $this->formatBytes((float) $r->bytes),
            'percent' => $total > 0 ? (int) round($r->bytes / $total * 100) : 0,
        ])->all();
    }

    /** The public student page over the last 7 days (anonymous engagement events). */
    private function studentPageSnapshot(Carbon $now): array
    {
        $since = $now->copy()->subDays(7);
        $counts = EngagementEvent::where('created_at', '>=', $since)
            ->selectRaw("SUM(type = 'visit' AND first_today = 1) as visits,
                SUM(type = 'announcement_view') as views,
                SUM(type IN ('document_preview', 'document_download')) as documents,
                SUM(type = 'search') as searches")
            ->first();
        $missed = EngagementEvent::where('created_at', '>=', $since)
            ->where('type', 'search')->where('results', 0)
            ->selectRaw('term, COUNT(*) as n')->groupBy('term')->orderByDesc('n')
            ->first();

        return [
            'visits'      => (int) $counts->visits,
            'views'       => (int) $counts->views,
            'documents'   => (int) $counts->documents,
            'searches'    => (int) $counts->searches,
            'subscribers' => PushSubscription::count(),
            'missed'      => $missed?->term,
        ];
    }

    private function formatBytes(float $bytes): string
    {
        return $bytes >= 1073741824
            ? number_format($bytes / 1073741824, 2) . ' GB'
            : number_format($bytes / 1048576, 2) . ' MB';
    }

    /**
     * Show all officers (users with assigned positions)
     */
    public function officers()
    {
        $users = User::with('position') // eager load position relationship
                    ->whereNotNull('position_id') // only users with positions
                    ->get();

        return view('SuperAdmin.manageAdmins', compact('users'));
    }

    /**
     * Show form to create a new officer
     */
    public function createOfficer()
    {
        // Get all positions for dropdown selection
        $positions = Position::all();

        return view('SuperAdmin.addAdmin', compact('positions'));
    }

    /**
     * Store new officer in database
     */
    public function storeOfficer(Request $request)
    {
        // Validate incoming request
        $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'position_id' => 'required|exists:positions,id'
        ]);

        // Create new user with hashed password
        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password),
            'position_id' => $request->position_id,
        ]);

        return redirect()->route('officers.index')
            ->with('success', 'Officer added successfully!');
    }

    /**
     * Alternative method to display officers
     * Excludes position_id = 1 (likely Super Admin)
     */
    public function officer()
    {
        $users = User::with('position')
            ->whereNotNull('position_id')
            ->where('position_id', '!=', 1) // exclude specific role
            ->get();

        return view('SuperAdmin.manageAdmins', compact('users'));
    }

    /**
     * Show edit form for a specific officer
     */
    public function edit($id)
    {
        // Find user or fail if not found
        $user = User::findOrFail($id);

        // Get all positions for dropdown
        $positions = Position::all();

        return view('SuperAdmin.editAdmin', compact('user', 'positions'));
    }

    /**
     * Update officer details
     */
    public function update(Request $request, $id)
    {
        // Find user
        $user = User::findOrFail($id);

        // Update user data
        $user->update([
            'name' => $request->name,
            'email' => $request->email,
            'position_id' => $request->position_id,
        ]);

        return redirect()->route('officers.index')
            ->with('success', 'Officer updated successfully.');
    }
}