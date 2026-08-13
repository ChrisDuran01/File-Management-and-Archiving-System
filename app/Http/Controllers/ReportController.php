<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\File;
use App\Models\Folder;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /**
     * A folder that hasn't been touched in this many days (and isn't
     * already archived) gets flagged as needing attention.
     */
    private const STALE_WARNING_DAYS = 90;

    private const STALE_CRITICAL_DAYS = 180;

    /**
     * An account with this many failed login attempts in the lookback
     * window gets flagged as a possible brute-force target, even if it
     * never actually tripped the rate-limiter's lockout (e.g. an attacker
     * pacing attempts to stay just under the threshold, or spreading
     * attempts across several accounts).
     */
    private const BRUTE_FORCE_THRESHOLD = 3;

    /**
     * An account that successfully logged in from this many distinct IPs
     * within the lookback window gets flagged - could be legitimate (a
     * mobile officer switching networks), but is also the pattern shared
     * credentials or a compromised account produce.
     */
    private const MULTI_IP_THRESHOLD = 3;

    private const ANOMALY_LOOKBACK_DAYS = 7;

    /**
     * How far back "new location" detection looks for a user's prior login
     * history before treating an unfamiliar IP as their actual first login
     * ever (which isn't anomalous) rather than a new location for an
     * established account (which is). Bounded rather than the whole table's
     * history, to keep the query cost predictable as activity_logs grows.
     */
    private const LOGIN_HISTORY_LOOKBACK_MONTHS = 6;

    public function index()
    {
        $now = Carbon::now();
        $thisMonth = Carbon::now()->startOfMonth();
        $lastMonth = Carbon::now()->subMonth()->startOfMonth();
        $lastMonthEnd = Carbon::now()->subMonth()->endOfMonth();

        // =============================
        // KPI ROW
        // =============================
        $totalFiles = File::count();
        $totalFolders = Folder::count();
        $totalUsers = User::count();
        $totalStorage = File::sum('size');

        $filesLastMonth = File::whereBetween('created_at', [$lastMonth, $lastMonthEnd])->count();
        $filesThisMonth = File::where('created_at', '>=', $thisMonth)->count();
        $filesDelta = $filesLastMonth > 0
            ? round((($filesThisMonth - $filesLastMonth) / $filesLastMonth) * 100)
            : 0;

        $foldersLastMonth = Folder::whereBetween('created_at', [$lastMonth, $lastMonthEnd])->count();
        $foldersThisMonth = Folder::where('created_at', '>=', $thisMonth)->count();
        $foldersDelta = $foldersLastMonth > 0
            ? round((($foldersThisMonth - $foldersLastMonth) / $foldersLastMonth) * 100)
            : 0;

        $storageFormatted = $this->formatBytes($totalStorage);

        // =============================
        // STORAGE GROWTH TREND + PROJECTION
        // =============================
        $monthsOfHistory = 6;
        $monthlyAdds = File::selectRaw("DATE_FORMAT(created_at, '%Y-%m') as ym, SUM(size) as total")
            ->where('created_at', '>=', $now->copy()->subMonths($monthsOfHistory - 1)->startOfMonth())
            ->groupBy('ym')
            ->pluck('total', 'ym');

        // Running total as of the start of the earliest bucket, so the
        // trend line reflects real cumulative storage, not just that
        // month's additions.
        $baseline = File::where('created_at', '<', $now->copy()->subMonths($monthsOfHistory - 1)->startOfMonth())->sum('size');

        // Walk every month in the window explicitly - a month with zero
        // uploads still needs a point (running total unchanged), otherwise
        // a GROUP BY silently skipping it would draw a misleading jump
        // straight from the last active month to the next one.
        $storageTrend = [];
        $running = $baseline;
        for ($i = $monthsOfHistory - 1; $i >= 0; $i--) {
            $month = $now->copy()->subMonths($i);
            $running += $monthlyAdds[$month->format('Y-m')] ?? 0;
            $storageTrend[] = ['label' => $month->format('M y'), 'bytes' => (float) $running];
        }

        // Simple linear projection from the average month-over-month
        // increase actually observed, extended 3 months forward - not a
        // guess, a direct extrapolation of the real trend.
        $projection = [];
        if (count($storageTrend) >= 2) {
            $first = $storageTrend[0]['bytes'];
            $last = end($storageTrend)['bytes'];
            $avgMonthlyGrowth = ($last - $first) / (count($storageTrend) - 1);

            $cursor = $last;
            for ($i = 1; $i <= 3; $i++) {
                $cursor += $avgMonthlyGrowth;
                $projection[] = [
                    'label' => $now->copy()->addMonths($i)->format('M y'),
                    'bytes' => max(0, $cursor),
                ];
            }
        }

        $projectedTarget = $projection
            ? $this->formatBytes(end($projection)['bytes'])
            : null;

        // =============================
        // MOST ACTIVE OFFICERS - straight from the activity log.
        // =============================
        $activeOfficers = ActivityLog::selectRaw('user_name, count(*) as total')
            ->where('created_at', '>=', $now->copy()->subDays(90))
            ->groupBy('user_name')
            ->orderByDesc('total')
            ->take(6)
            ->get();

        // =============================
        // FOLDERS NEEDING ATTENTION - stale, not archived, actionable.
        // =============================
        $staleFolders = Folder::where('is_archived', false)
            ->where(function ($q) use ($now) {
                $q->where('last_accessed_at', '<', $now->copy()->subDays(self::STALE_WARNING_DAYS))
                    ->orWhereNull('last_accessed_at');
            })
            ->where('created_at', '<', $now->copy()->subDays(self::STALE_WARNING_DAYS))
            ->get()
            ->map(function (Folder $folder) use ($now) {
                $lastTouch = $folder->last_accessed_at ?? $folder->created_at;
                $daysStale = $lastTouch->diffInDays($now);

                return [
                    'name' => $folder->name,
                    'days_stale' => $daysStale,
                    'status' => $daysStale >= self::STALE_CRITICAL_DAYS ? 'critical' : 'warning',
                ];
            })
            ->sortByDesc('days_stale')
            ->take(8)
            ->values();

        // =============================
        // FILE TYPE BREAKDOWN (proportion of whole)
        // =============================
        $imgCount = File::whereIn(DB::raw('LOWER(RIGHT(filename, 3))'), ['png', 'jpg', 'gif'])
            ->orWhereIn(DB::raw('LOWER(RIGHT(filename, 4))'), ['jpeg', 'webp'])
            ->count();
        $pdfCount = File::where(DB::raw('LOWER(RIGHT(filename, 3))'), 'pdf')->count();
        $docCount = File::whereIn(DB::raw('LOWER(RIGHT(filename, 4))'), ['docx', 'xlsx'])
            ->orWhereIn(DB::raw('LOWER(RIGHT(filename, 3))'), ['doc', 'xls'])
            ->count();
        $otherCount = max(0, $totalFiles - $imgCount - $pdfCount - $docCount);

        $fileTypes = collect([
            ['label' => 'PDFs', 'total' => $pdfCount],
            ['label' => 'Images', 'total' => $imgCount],
            ['label' => 'Documents', 'total' => $docCount],
            ['label' => 'Other', 'total' => $otherCount],
        ])->map(fn ($t) => $t + [
            'percent' => $totalFiles > 0 ? round(($t['total'] / $totalFiles) * 100) : 0,
        ]);

        // =============================
        // RECENT DOCUMENTS
        // =============================
        $recentFiles = File::with('template')->latest()->take(10)->get();

        // =============================
        // SECURITY: LOGIN ANOMALIES - SuperAdmin only, since this surfaces
        // who might be under attack or have a compromised account, not
        // something every officer needs visibility into.
        // =============================
        $isSuperAdmin = Folder::isSuperAdmin(Auth::user());
        $loginAnomalies = $isSuperAdmin ? $this->detectLoginAnomalies() : null;

        $layout = $isSuperAdmin ? 'SuperAdmin.homeSuperAdmin' : 'Admin.home';

        return view('Admin.reports', compact(
            'totalFiles', 'totalFolders', 'totalUsers', 'storageFormatted',
            'filesDelta', 'foldersDelta',
            'storageTrend', 'projection', 'projectedTarget',
            'activeOfficers',
            'staleFolders',
            'fileTypes',
            'recentFiles',
            'loginAnomalies',
            'isSuperAdmin',
            'layout'
        ));
    }

    /**
     * Pattern-detection over activity_logs, not just a count - three
     * different anomaly shapes, each catching something a flat "X failed
     * logins today" number would miss:
     *
     *  - Brute force: an account/email with repeated failed attempts,
     *    even if it never actually tripped the rate-limiter's lockout
     *    (an attacker pacing themselves under the threshold, or spread
     *    across several target accounts, would otherwise leave no trace).
     *  - Multi-IP: an account successfully logging in from several
     *    different IPs in a short window - the pattern shared or
     *    compromised credentials produce, not just one person moving
     *    around.
     *  - New location: a successful login from an IP that account has
     *    never used before, for an account with real prior history (so a
     *    brand new account's first-ever login isn't flagged as "unusual").
     */
    private function detectLoginAnomalies(): array
    {
        $now = Carbon::now();
        $anomalyWindowStart = $now->copy()->subDays(self::ANOMALY_LOOKBACK_DAYS);

        $bruteForceAttempts = ActivityLog::whereIn('activity', [
                'Failed login attempt',
                'Login blocked - too many failed attempts',
                'Login attempt on disabled (former officer) account',
            ])
            ->where('created_at', '>=', $anomalyWindowStart)
            ->selectRaw('user_name, count(*) as attempts, count(distinct ip_address) as distinct_ips, max(created_at) as last_attempt')
            ->groupBy('user_name')
            ->having('attempts', '>=', self::BRUTE_FORCE_THRESHOLD)
            ->orderByDesc('attempts')
            ->get();

        $multiIpLogins = ActivityLog::where('activity', 'Logged in')
            ->where('created_at', '>=', $anomalyWindowStart)
            ->whereNotNull('ip_address')
            ->selectRaw('user_name, count(distinct ip_address) as distinct_ips, count(*) as total_logins, max(created_at) as last_login')
            ->groupBy('user_name')
            ->having('distinct_ips', '>=', self::MULTI_IP_THRESHOLD)
            ->orderByDesc('distinct_ips')
            ->get();

        $newLocationLogins = $this->findNewLocationLogins($anomalyWindowStart);

        return [
            'bruteForce' => $bruteForceAttempts,
            'multiIp' => $multiIpLogins,
            'newLocation' => $newLocationLogins,
        ];
    }

    /**
     * Walks successful logins in chronological order, tracking every
     * (user, IP) pair seen so far, and flags a login in the recent window
     * as "new location" only if that exact pair has never appeared before
     * AND the account has login history predating it - otherwise every
     * brand new account's very first login would get flagged, which isn't
     * unusual, it's just... their first login.
     */
    private function findNewLocationLogins(Carbon $anomalyWindowStart): array
    {
        $logins = ActivityLog::where('activity', 'Logged in')
            ->whereNotNull('ip_address')
            ->where('created_at', '>=', Carbon::now()->subMonths(self::LOGIN_HISTORY_LOOKBACK_MONTHS))
            ->orderBy('created_at')
            ->get(['user_name', 'ip_address', 'created_at']);

        $seenPairs = [];
        $seenUsers = [];
        $flagged = [];

        foreach ($logins as $login) {
            $pairKey = $login->user_name . '|' . $login->ip_address;
            $isNewPair = ! isset($seenPairs[$pairKey]);
            $hasPriorLogin = isset($seenUsers[$login->user_name]);

            if ($isNewPair && $hasPriorLogin && $login->created_at->greaterThanOrEqualTo($anomalyWindowStart)) {
                $flagged[] = $login;
            }

            $seenPairs[$pairKey] = true;
            $seenUsers[$login->user_name] = true;
        }

        return array_slice(array_reverse($flagged), 0, 10);
    }

    private function formatBytes(float $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2).' GB';
        }

        return number_format($bytes / 1048576, 2).' MB';
    }
}
