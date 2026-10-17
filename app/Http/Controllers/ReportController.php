<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Archive;
use App\Models\Backup;
use App\Models\Document;
use App\Models\File;
use App\Models\Folder;
use App\Models\ScanBatch;
use App\Models\User;
use App\Services\ArchiveInsights;
use App\Services\EngagementReport;
use App\Support\SchoolYear;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

    /** Preset report windows, in days. */
    private const PRESETS = ['7d' => 7, '30d' => 30, '90d' => 90, '12m' => 365];

    /**
     * Activity-log strings carry names and counts, so they're bucketed by
     * pattern (first match wins - order matters, e.g. "Downloaded backup"
     * must be caught before the generic "Downloaded").
     */
    private const ACTIVITY_CATEGORIES = [
        'Backup & restore'    => '/^(Created backup|Downloaded backup|Restored system|Turned automatic backup|Changed backup)/',
        'Failed sign-ins'     => '/^(Failed login|Login blocked|Login attempt on disabled)/',
        'Sign-ins / sign-outs' => '/^(Logged in|Logged out)/',
        'Digitization'        => '/^(Ingested scan|Filed scan|Discarded scan|Re-processing scan|Scan batch|Pruned)/',
        'Uploads & generated' => '/^(Uploaded|Generated document)/',
        'Downloads'           => '/^Downloaded/',
        'Trash'               => '/^(Moved .* to Trash|Permanently deleted|Emptied the Trash|Restored from Trash|Trash auto-purge)/',
        'Archiving'           => '/^(Archived folder|Auto-archived|Restored folder|Deleted archive|Ended )/',
        'Edits & access'      => '/^(Renamed|Updated|Changed access|Restricted|Unrestricted|Added folder)/',
        'Officers & accounts' => '/^(Added officer|Archived officer|Reactivated officer|Deleted officer|Changed password|Reset password|Requested password|Acknowledged privacy)/',
        'Site content'        => '/(announcement|letterhead|site content|display-only)/i',
    ];

    public function index(Request $request)
    {
        $now = Carbon::now();

        // =============================
        // REPORT WINDOW - a school year, a preset, or a custom from/to range.
        // =============================
        [$from, $to, $rangeKey, $schoolYear] = $this->resolveRange($request, $now);
        $days = max(1, (int) $from->diffInDays($to) + 1);

        if ($schoolYear) {
            // Same dates one school year earlier: August is compared with
            // August, not with the summer break just before it.
            $prevFrom = $from->copy()->subYear();
            $prevTo = $to->copy()->subYear();
            $comparedWith = 'the same dates in SY ' . SchoolYear::previous($schoolYear);
        } else {
            $prevTo = $from->copy()->subSecond();
            $prevFrom = $from->copy()->subDays($days);
            $comparedWith = 'the ' . $days . ' ' . Str::plural('day', $days) . ' before';
        }

        $periodLabel = ($schoolYear ? 'SY ' . $schoolYear . ', ' : '') . $from->format('M j, Y') . ' – ' . $to->format('M j, Y');
        $schoolYears = SchoolYear::options();
        $tab = $request->query('tab') === 'engagement' ? 'engagement' : 'records';

        // Time buckets for every trend chart: daily for short windows,
        // weekly for medium ones, monthly for the long ones.
        $buckets = $this->makeBuckets($from, $to);

        // The report follows the same access as the pages its numbers come
        // from. Activity Logs and Backups are SuperAdmin pages, so every
        // section built on them (officer activity, usage patterns, backups,
        // sign-in security) is SuperAdmin-only here too - for a regular
        // Admin they are never queried, not merely hidden in the view.
        $isSuperAdmin = Folder::isSuperAdmin(Auth::user());
        $layout = $isSuperAdmin ? 'SuperAdmin.homeSuperAdmin' : 'Admin.home';

        // =============================
        // STUDENT ENGAGEMENT TAB - anonymous events from the public page.
        // =============================
        if ($tab === 'engagement') {
            $engagement = app(EngagementReport::class)->build($from, $to, $prevFrom, $prevTo, $buckets, $comparedWith);

            if ($request->query('export') === 'csv') {
                return $this->exportEngagementCsv($periodLabel, $engagement);
            }

            return view('Admin.reports', compact(
                'tab', 'engagement', 'rangeKey', 'from', 'to', 'periodLabel', 'days',
                'schoolYear', 'schoolYears', 'comparedWith', 'isSuperAdmin', 'layout'
            ));
        }

        // =============================
        // RAW ROWS FOR THE WINDOW - one query each, reused by many panels.
        // =============================
        $filesInRange = File::whereBetween('created_at', [$from, $to])->get(['created_at', 'size', 'generated_from_template_id']);
        $docsInRange = Document::whereBetween('created_at', [$from, $to])->get(['created_at', 'document_type', 'status', 'created_by', 'page_count']);
        $logsInRange = $isSuperAdmin
            ? ActivityLog::whereBetween('created_at', [$from, $to])->get(['user_name', 'activity', 'created_at'])
            : null;

        // =============================
        // KPI ROW - each with the same-length previous period for context.
        // =============================
        $kpis = [
            $this->kpi('Files uploaded', $filesInRange->count(),
                File::whereBetween('created_at', [$prevFrom, $prevTo])->count()),
            $this->kpi('Documents digitized', $docsInRange->count(),
                Document::whereBetween('created_at', [$prevFrom, $prevTo])->count()),
            $this->kpi('Pages digitized', (int) $docsInRange->sum('page_count'),
                (int) Document::whereBetween('created_at', [$prevFrom, $prevTo])->sum('page_count')),
        ];
        if ($isSuperAdmin) {
            $kpis[] = $this->kpi('Logged actions', $logsInRange->count(),
                ActivityLog::whereBetween('created_at', [$prevFrom, $prevTo])->count());
            $kpis[] = $this->kpi('Active officers', $logsInRange->pluck('user_name')->unique()->count(),
                ActivityLog::whereBetween('created_at', [$prevFrom, $prevTo])->distinct('user_name')->count('user_name'));
        }

        $totalFiles = File::count();
        $totalDocuments = Document::count();
        $totalFolders = Folder::count();
        $totalStorage = (float) File::sum('size');
        $storageFormatted = $this->formatBytes($totalStorage);

        // =============================
        // RECORDS OVER TIME (uploads, digitized docs)
        // =============================
        $trend = [
            'labels'    => $buckets['labels'],
            'uploads'   => $this->countByBucket($filesInRange->pluck('created_at'), $buckets),
            'documents' => $this->countByBucket($docsInRange->pluck('created_at'), $buckets),
        ];

        // =============================
        // OFFICER ACTIVITY (SuperAdmin only) - actions over time, when the
        // system is used, action categories, leaderboard, latest actions.
        // =============================
        $byHour = $byWeekday = $activityByCategory = $activeOfficers = $recentActivity = null;

        if ($isSuperAdmin) {
            $trend['actions'] = $this->countByBucket($logsInRange->pluck('created_at'), $buckets);

            // Hour-of-day profile: when the system is actually being used.
            $byHour = array_fill(0, 24, 0);
            foreach ($logsInRange as $log) {
                $byHour[(int) $log->created_at->format('G')]++;
            }

            // Day-of-week profile (Mon..Sun).
            $byWeekday = array_fill(1, 7, 0);
            foreach ($logsInRange as $log) {
                $byWeekday[(int) $log->created_at->isoWeekday()]++;
            }

            $activityByCategory = $logsInRange
                ->groupBy(fn ($l) => $this->categorize($l->activity))
                ->map->count()
                ->sortDesc()
                ->map(fn ($n, $label) => ['label' => $label, 'total' => $n])
                ->values();

            $activeOfficers = $logsInRange->groupBy('user_name')
                ->map(fn ($g, $name) => (object) [
                    'user_name' => $name,
                    'total'     => $g->count(),
                    'last_seen' => $g->max('created_at'),
                ])
                ->sortByDesc('total')
                ->take(8)
                ->values();

            $recentActivity = $logsInRange->sortByDesc('created_at')->take(10)->values();

            // DATA MINING (disabled): officer scatter plot. Re-enable with the view's data mining section.
            // $officerScatter = $this->officerScatter($logsInRange);
        }

        // =============================
        // DIGITIZATION PIPELINE
        // =============================
        $batchesInRange = ScanBatch::whereBetween('created_at', [$from, $to])
            ->get(['status', 'created_at', 'filed_at', 'page_count', 'filed_document_count']);

        $batchStatusLabels = [
            'ingesting' => 'Ingesting', 'extracting' => 'Extracting', 'ready_for_review' => 'Awaiting review',
            'filing' => 'Filing', 'filed' => 'Filed', 'failed' => 'Failed', 'discarded' => 'Discarded',
        ];
        $batchStatuses = $batchesInRange->groupBy('status')
            ->map(fn ($g, $status) => [
                'label' => $batchStatusLabels[$status] ?? ucfirst(str_replace('_', ' ', (string) $status)),
                'key'   => $status,
                'total' => $g->count(),
            ])
            ->sortByDesc('total')
            ->values();

        $filedBatches = $batchesInRange->filter(fn ($b) => $b->filed_at);
        $avgTurnaroundHours = $filedBatches->isNotEmpty()
            ? round($filedBatches->avg(fn ($b) => $b->created_at->diffInMinutes($b->filed_at)) / 60, 1)
            : null;

        $pipeline = [
            'batches'         => $batchesInRange->count(),
            'filed'           => $filedBatches->count(),
            'failed'          => $batchesInRange->where('status', 'failed')->count(),
            'awaiting'        => ScanBatch::where('status', 'ready_for_review')->count(), // current backlog, not period-bound
            'pages'           => (int) $batchesInRange->sum('page_count'),
            'docs_from_scans' => (int) $batchesInRange->sum('filed_document_count'),
            'turnaround'      => $avgTurnaroundHours,
            'success_rate'    => $batchesInRange->whereIn('status', ['filed', 'failed'])->count() > 0
                ? round($filedBatches->count() / $batchesInRange->whereIn('status', ['filed', 'failed'])->count() * 100)
                : null,
        ];

        $documentTypes = $docsInRange->groupBy(fn ($d) => $d->document_type ?: 'Unclassified')
            ->map(fn ($g, $type) => ['label' => $type, 'total' => $g->count()])
            ->sortByDesc('total')
            ->values();
        if ($documentTypes->count() > 7) {
            $documentTypes = $documentTypes->take(6)->push([
                'label' => 'Other types',
                'total' => $documentTypes->slice(6)->sum('total'),
            ]);
        }

        // OCR coverage across the whole library (searchability of scans).
        $ocrCounts = File::selectRaw('ocr_status, count(*) as total')->groupBy('ocr_status')->pluck('total', 'ocr_status');
        $ocrDone = (int) ($ocrCounts['done'] ?? 0);
        $ocrEligible = max(0, $totalFiles - (int) ($ocrCounts['skipped'] ?? 0));
        $ocr = [
            'done'     => $ocrDone,
            'pending'  => (int) ($ocrCounts['pending'] ?? 0),
            'failed'   => (int) ($ocrCounts['failed'] ?? 0),
            'skipped'  => (int) ($ocrCounts['skipped'] ?? 0),
            'coverage' => $ocrEligible > 0 ? round($ocrDone / $ocrEligible * 100) : null,
        ];

        // =============================
        // STORAGE: GROWTH + PROJECTION, TOP FOLDERS, FILE TYPES
        // =============================
        [$storageTrend, $projection] = $this->storageGrowth($now);
        $projectedTarget = $projection ? $this->formatBytes(end($projection)['bytes']) : null;

        $topFolders = File::selectRaw('folder_id, count(*) as files, coalesce(sum(size),0) as bytes')
            ->whereNotNull('folder_id')
            ->groupBy('folder_id')
            ->orderByDesc('bytes')
            ->take(6)
            ->get();
        $folderNames = Folder::withTrashed()->whereIn('id', $topFolders->pluck('folder_id'))->pluck('name', 'id');
        $topFolders = $topFolders->map(fn ($r) => (object) [
            'name'      => $folderNames[$r->folder_id] ?? 'Unknown folder',
            'files'     => (int) $r->files,
            'bytes'     => (float) $r->bytes,
            'formatted' => $this->formatBytes((float) $r->bytes),
        ]);

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
        // PRESERVATION: ARCHIVES (fixity) + BACKUPS
        // =============================
        $archiveStatus = Archive::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $fixity = Archive::selectRaw("coalesce(fixity_status, 'unchecked') as s, count(*) as total")->groupBy('s')->pluck('total', 's');
        $lastFixity = Archive::max('fixity_checked_at');
        $preservation = [
            'archives'        => (int) $archiveStatus->sum(),
            'archived_period' => Archive::whereBetween('archived_at', [$from, $to])->count(),
            'fixity_ok'       => (int) ($fixity['ok'] ?? 0),
            'fixity_failed'   => (int) ($fixity['failed'] ?? 0),
            'fixity_unchecked' => (int) ($fixity['unchecked'] ?? 0),
            'last_fixity'     => $lastFixity ? Carbon::parse($lastFixity) : null,
        ];

        // Backup health is SuperAdmin-only, like the Backups page itself.
        $backups = null;
        if ($isSuperAdmin) {
            $backupsInRange = Backup::whereBetween('created_at', [$from, $to])->get(['status', 'created_at']);
            $lastBackup = Backup::latest()->first();
            $backups = [
                'total'    => $backupsInRange->count(),
                'success'  => $backupsInRange->where('status', 'Success')->count(),
                'partial'  => $backupsInRange->where('status', '!=', 'Success')->count(),
                'last'     => $lastBackup?->created_at,
                'last_age_hours' => $lastBackup ? (int) $lastBackup->created_at->diffInHours($now) : null,
            ];
        }

        // =============================
        // FOLDERS NEEDING ATTENTION - stale, not archived, actionable.
        // =============================
        $allStaleFolders = Folder::where('is_archived', false)
            ->where(function ($q) use ($now) {
                $q->where('last_accessed_at', '<', $now->copy()->subDays(self::STALE_WARNING_DAYS))
                    ->orWhereNull('last_accessed_at');
            })
            ->where('created_at', '<', $now->copy()->subDays(self::STALE_WARNING_DAYS))
            ->get()
            ->map(function (Folder $folder) use ($now) {
                $lastTouch = $folder->last_accessed_at ?? $folder->created_at;
                $daysStale = (int) $lastTouch->diffInDays($now);

                return [
                    'name' => $folder->name,
                    'days_stale' => $daysStale,
                    'status' => $daysStale >= self::STALE_CRITICAL_DAYS ? 'critical' : 'warning',
                ];
            })
            ->sortByDesc('days_stale');
        $staleCount = $allStaleFolders->count();
        $staleFolders = $allStaleFolders->take(8)->values();

        // =============================
        // CSV EXPORT - the same numbers the page shows.
        // =============================
        if ($request->query('export') === 'csv') {
            return $this->exportCsv($periodLabel, $kpis, $trend, $activityByCategory, $activeOfficers, $documentTypes, $batchStatuses, $topFolders, $pipeline, $preservation, $backups);
        }

        // =============================
        // SECURITY: LOGIN ANOMALIES - SuperAdmin only, since this surfaces
        // who might be under attack or have a compromised account, not
        // something every officer needs visibility into.
        // =============================
        $loginAnomalies = $isSuperAdmin ? $this->detectLoginAnomalies() : null;

        // DATA MINING (disabled): duplicates, misfiled, anomalies, document map.
        // $insights = app(ArchiveInsights::class)->build(Auth::user());

        $hourLabels = array_map(fn ($h) => sprintf('%02d:00', $h), range(0, 23));
        $weekdayLabels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

        // Every panel is a question; these are the computed one-line answers.
        $answers = $this->recordsAnswers(compact(
            'trend', 'buckets', 'pipeline', 'batchStatuses', 'documentTypes', 'storageTrend', 'projection',
            'projectedTarget', 'storageFormatted', 'totalStorage', 'topFolders', 'fileTypes', 'staleFolders',
            'staleCount', 'preservation', 'ocr'
        ));
        if ($isSuperAdmin) {
            $answers += $this->superAdminAnswers(compact(
                'activityByCategory', 'logsInRange', 'byHour', 'byWeekday', 'activeOfficers',
                'recentActivity', 'backups', 'loginAnomalies'
            ));
        }

        return view('Admin.reports', compact(
            'tab', 'schoolYear', 'schoolYears', 'comparedWith', 'answers', 'buckets',
            'rangeKey', 'from', 'to', 'periodLabel', 'days',
            'kpis', 'totalFiles', 'totalDocuments', 'totalFolders', 'storageFormatted',
            'trend', 'byHour', 'byWeekday', 'hourLabels', 'weekdayLabels',
            'activityByCategory', 'activeOfficers', 'recentActivity', /* 'officerScatter', */
            'pipeline', 'batchStatuses', 'documentTypes', 'ocr',
            'storageTrend', 'projection', 'projectedTarget', 'topFolders', 'fileTypes',
            'preservation', 'backups',
            'staleFolders', /* 'insights', */
            'loginAnomalies', 'isSuperAdmin', 'layout'
        ));
    }

    /**
     * One point per officer: x = all logged actions, y = sensitive actions
     * (downloads / deletions). A least-squares line through the points is
     * the "expected" sensitive count for that much activity; officers well
     * above it (residual > 1.5 standard deviations) are flagged outliers.
     * Needs at least 4 officers to say anything meaningful.
     */
    private function officerScatter($logs): array
    {
        $sensitive = '/^(Downloaded|Moved .* to Trash|Permanently deleted|Emptied the Trash|Deleted )/';

        $points = $logs->groupBy('user_name')->map(function ($g, $name) use ($sensitive) {
            return [
                'name' => $name,
                'x'    => $g->count(),
                'y'    => $g->filter(fn ($l) => preg_match($sensitive, $l->activity))->count(),
                'days' => $g->map(fn ($l) => $l->created_at->format('Y-m-d'))->unique()->count(),
                'outlier' => false,
            ];
        })->values()->all();

        $n = count($points);
        $line = null;

        if ($n >= 4) {
            $mx = array_sum(array_column($points, 'x')) / $n;
            $my = array_sum(array_column($points, 'y')) / $n;
            $sxx = array_sum(array_map(fn ($p) => ($p['x'] - $mx) ** 2, $points));

            if ($sxx > 0) {
                $slope = array_sum(array_map(fn ($p) => ($p['x'] - $mx) * ($p['y'] - $my), $points)) / $sxx;
                $intercept = $my - $slope * $mx;

                $resid = array_map(fn ($p) => $p['y'] - ($intercept + $slope * $p['x']), $points);
                $std = sqrt(array_sum(array_map(fn ($r) => $r * $r, $resid)) / $n);

                foreach ($points as $i => &$p) {
                    $p['outlier'] = $std > 0 && $resid[$i] > 1.5 * $std && $p['y'] >= 3;
                }
                unset($p);

                $xs = array_column($points, 'x');
                $line = [
                    ['x' => min($xs), 'y' => round($intercept + $slope * min($xs), 2)],
                    ['x' => max($xs), 'y' => round($intercept + $slope * max($xs), 2)],
                ];
            }
        }

        return ['points' => $points, 'line' => $line];
    }

    /**
     * A school year (?sy=2026-2027), a custom ?from/?to, or a preset ?range.
     * A school year still in progress ends today, not on its last day.
     *
     * @return array{0: Carbon, 1: Carbon, 2: string, 3: ?string} from, to, range key, school year
     */
    private function resolveRange(Request $request, Carbon $now): array
    {
        $sy = $request->query('sy');
        if (SchoolYear::isValid($sy)) {
            [$start, $end] = SchoolYear::range($sy);
            if ($start->lte($now)) {
                return [$start, $end->gt($now) ? $now->copy()->endOfDay() : $end, 'sy', $sy];
            }
        }

        $from = $request->query('from');
        $to = $request->query('to');

        if ($from && $to) {
            try {
                $start = Carbon::parse($from)->startOfDay();
                $end = Carbon::parse($to)->endOfDay();
                if ($start->lte($end) && $start->diffInDays($end) <= 1830) {
                    return [$start, $end->gt($now) ? $now->copy()->endOfDay() : $end, 'custom', null];
                }
            } catch (\Throwable) {
                // fall through to the default preset
            }
        }

        $key = array_key_exists($request->query('range'), self::PRESETS) ? $request->query('range') : '30d';

        return [
            $now->copy()->subDays(self::PRESETS[$key] - 1)->startOfDay(),
            $now->copy()->endOfDay(),
            $key,
            null,
        ];
    }

    /**
     * @return array{labels: string[], starts: Carbon[], unit: string}
     */
    private function makeBuckets(Carbon $from, Carbon $to): array
    {
        $span = $from->diffInDays($to) + 1;
        $unit = $span <= 45 ? 'day' : ($span <= 200 ? 'week' : 'month');

        $cursor = match ($unit) {
            'day'   => $from->copy()->startOfDay(),
            'week'  => $from->copy()->startOfWeek(),
            'month' => $from->copy()->startOfMonth(),
        };

        $labels = [];
        $starts = [];
        while ($cursor->lte($to)) {
            $starts[] = $cursor->copy();
            $labels[] = match ($unit) {
                'day'   => $cursor->format('M j'),
                'week'  => 'Wk of ' . $cursor->format('M j'),
                'month' => $cursor->format('M Y'),
            };
            match ($unit) {
                'day'   => $cursor->addDay(),
                'week'  => $cursor->addWeek(),
                'month' => $cursor->addMonthNoOverflow(),
            };
        }

        return ['labels' => $labels, 'starts' => $starts, 'unit' => $unit];
    }

    /** Count timestamps into the pre-built buckets (zero-filled). */
    private function countByBucket($timestamps, array $buckets): array
    {
        $counts = array_fill(0, count($buckets['starts']), 0);
        $last = count($buckets['starts']) - 1;

        foreach ($timestamps as $ts) {
            // Buckets are sorted; the last start <= ts is the right one.
            for ($i = $last; $i >= 0; $i--) {
                if ($ts->gte($buckets['starts'][$i])) {
                    $counts[$i]++;
                    break;
                }
            }
        }

        return $counts;
    }

    private function kpi(string $label, int $value, int $previous): array
    {
        return [
            'label'    => $label,
            'value'    => $value,
            'previous' => $previous,
            // null = no baseline to compare against (don't invent a %)
            'delta'    => $previous > 0 ? (int) round((($value - $previous) / $previous) * 100) : null,
        ];
    }

    /**
     * One-line answers for the Records tab - each panel is a question, and
     * the chart under it is the evidence. HTML: dynamic text goes through
     * e(), only <strong> is added. These are the panels every officer sees;
     * the SuperAdmin-only ones are answered in superAdminAnswers().
     *
     * @return array<string, string>
     */
    private function recordsAnswers(array $d): array
    {
        $b = fn ($text) => '<strong>' . e($text) . '</strong>';
        $n = fn ($value) => number_format($value);
        $a = [];

        // Is the archive being kept up to date?
        $uploads = array_sum($d['trend']['uploads']);
        $digitized = array_sum($d['trend']['documents']);
        if ($uploads + $digitized === 0) {
            $a['trend'] = 'Nothing was uploaded or digitized in this period.';
        } else {
            $unit = $d['buckets']['unit'];
            $combined = array_map(fn ($u, $doc) => $u + $doc, $d['trend']['uploads'], $d['trend']['documents']);
            $a['trend'] = $b($n($uploads)) . ' ' . Str::plural('file', $uploads) . ' uploaded and ' . $b($n($digitized)) . ' '
                . Str::plural('document', $digitized) . ' digitized.';
            if (count($combined) > 1) {
                $a['trend'] .= ' Busiest ' . $unit . ': ' . $b($d['trend']['labels'][array_search(max($combined), $combined, true)]) . '.';
                $quiet = count(array_filter($combined, fn ($v) => $v === 0));
                if ($quiet > 0) {
                    $a['trend'] .= ' ' . $b($quiet . ' of ' . count($combined)) . ' ' . Str::plural($unit, count($combined)) . ' had no new records at all.';
                }
            }
        }

        // Is digitization keeping up?
        $p = $d['pipeline'];
        $waiting = $p['awaiting'] > 0
            ? ' ' . $b($n($p['awaiting'])) . ' ' . Str::plural('batch', $p['awaiting']) . ' ' . ($p['awaiting'] === 1 ? 'is' : 'are') . ' waiting for review right now.'
            : ' Nothing is waiting for review.';
        $turnaround = $p['turnaround'] === null ? null
            : ($p['turnaround'] < 1 ? max(1, (int) round($p['turnaround'] * 60)) . ' minutes' : $p['turnaround'] . ' hours');
        $a['pipeline'] = $p['batches'] === 0
            ? 'No hard copies were scanned in this period.' . ($p['awaiting'] > 0 ? $waiting : '')
            : $b($p['filed'] . ' of ' . $p['batches']) . ' scan batches were filed'
                . ($turnaround ? ', taking about ' . $b($turnaround) . ' each from scan to filed' : '') . '.' . $waiting;

        $batchTotal = (int) $d['batchStatuses']->sum('total');
        $failed = (int) ($d['batchStatuses']->firstWhere('key', 'failed')['total'] ?? 0);
        $a['batches'] = $batchTotal === 0
            ? 'No scan batches in this period.'
            : ($failed > 0 ? $b($failed . ' of ' . $batchTotal) . ' failed and need a look.' : 'None of the ' . $n($batchTotal) . ' batches failed.');

        // What kinds of records are being digitized?
        $typeTotal = (int) $d['documentTypes']->sum('total');
        $a['types'] = $typeTotal === 0
            ? 'No documents were digitized in this period.'
            : 'Mostly ' . $b($d['documentTypes']->first()['label']) . ' (' . $d['documentTypes']->first()['total'] . ' of ' . $typeTotal . ').';

        // Will storage run out?
        $trend = $d['storageTrend'];
        $growth = count($trend) >= 2 ? (end($trend)['bytes'] - $trend[0]['bytes']) / (count($trend) - 1) : 0;
        $a['storage'] = $b($d['storageFormatted']) . ' used. '
            . ($growth > 0
                ? 'Growing about ' . $b($this->formatBytes($growth)) . ' a month'
                    . ($d['projectedTarget'] ? ', so expect about ' . $b($d['projectedTarget']) . ' in 3 months.' : '.')
                : 'Storage has not grown over the last 6 months.');

        // Where is the storage going?
        $topFolder = $d['topFolders']->first();
        $a['folders'] = $topFolder && $d['totalStorage'] > 0
            ? $b('“' . $topFolder->name . '”') . ' alone holds ' . $b(round($topFolder->bytes / $d['totalStorage'] * 100) . '%') . ' of all stored files (' . e($topFolder->formatted) . ').'
            : 'No files stored in folders yet.';

        // What is the library made of?
        $sortedTypes = $d['fileTypes']->sortByDesc('total')->values();
        $a['fileTypes'] = $sortedTypes->sum('total') === 0
            ? 'The library is empty.'
            : $b($sortedTypes[0]['label']) . ' make up ' . $b($sortedTypes[0]['percent'] . '%') . ' of the library'
                . ($sortedTypes[1]['total'] > 0 ? ', then ' . e($sortedTypes[1]['label']) . ' (' . $sortedTypes[1]['percent'] . '%).' : '.');

        // Which folders have gone quiet?
        $oldest = $d['staleFolders']->first();
        $a['stale'] = $d['staleCount'] === 0
            ? 'None. Every active folder was used in the last ' . self::STALE_WARNING_DAYS . ' days.'
            : $b($d['staleCount'] . ' active ' . Str::plural('folder', $d['staleCount'])) . ' untouched for ' . self::STALE_WARNING_DAYS . '+ days; the oldest, '
                . $b('“' . $oldest['name'] . '”') . ', for ' . $oldest['days_stale'] . ' days. Consider archiving them.';

        // Are archived records still intact?
        $pr = $d['preservation'];
        $a['integrity'] = match (true) {
            $pr['archives'] === 0 => 'No archives yet.',
            $pr['fixity_failed'] > 0 => $b($pr['fixity_failed'] . ' ' . Str::plural('archive', $pr['fixity_failed'])) . ' failed verification and may have been altered or damaged. Check them in Archives.',
            $pr['fixity_ok'] > 0 => 'All ' . $b($n($pr['fixity_ok'])) . ' checked archives are intact' . ($pr['fixity_unchecked'] ? ', ' . $pr['fixity_unchecked'] . ' not yet checked.' : '.'),
            default => 'None of the ' . $n($pr['archives']) . ' archives has been checked yet.',
        };

        // Can every file be found by its text?
        $o = $d['ocr'];
        $a['ocr'] = $o['coverage'] === null
            ? 'No files to read yet.'
            : $b($o['coverage'] . '%') . ' of files can be found by the words inside them'
                . ($o['failed'] ? '; ' . $b($n($o['failed'])) . ' failed and need a re-run' : '')
                . ($o['pending'] ? '; ' . $n($o['pending']) . ' still processing' : '') . '.';

        return $a;
    }

    /**
     * Answers for the panels only a SuperAdmin gets: officer activity (from
     * the Activity Logs), backups and sign-in security. Same rules as
     * recordsAnswers().
     *
     * @return array<string, string>
     */
    private function superAdminAnswers(array $d): array
    {
        $b = fn ($text) => '<strong>' . e($text) . '</strong>';
        $n = fn ($value) => number_format($value);
        $a = [];

        // What do officers spend their time on?
        $actions = (int) $d['activityByCategory']->sum('total');
        if ($actions === 0) {
            $a['categories'] = 'No officer activity was logged in this period.';
        } else {
            $top = $d['activityByCategory']->first();
            $a['categories'] = 'Mostly ' . $b($top['label']) . ' (' . round($top['total'] / $actions * 100) . '% of ' . $n($actions) . ' actions)';
            if ($second = $d['activityByCategory']->get(1)) {
                $a['categories'] .= ', then ' . $b($second['label']) . ' (' . round($second['total'] / $actions * 100) . '%)';
            }
            $a['categories'] .= '.';
        }

        // When do officers work in the system?
        $hourTotal = array_sum($d['byHour']);
        if ($hourTotal < 10) {
            $a['hours'] = 'Too little activity in this period to see a pattern.';
        } else {
            $bestStart = 0;
            $bestSum = -1;
            for ($h = 0; $h <= 22; $h++) {
                if ($d['byHour'][$h] + $d['byHour'][$h + 1] > $bestSum) {
                    [$bestStart, $bestSum] = [$h, $d['byHour'][$h] + $d['byHour'][$h + 1]];
                }
            }
            $clock = fn ($h) => Carbon::createFromTime($h % 24)->format('g A');
            $a['hours'] = 'Most work happens between ' . $b($clock($bestStart) . ' and ' . $clock($bestStart + 2))
                . ' (' . round($bestSum / $hourTotal * 100) . '% of actions).';
        }

        $dayNames = [1 => 'Mondays', 'Tuesdays', 'Wednesdays', 'Thursdays', 'Fridays', 'Saturdays', 'Sundays'];
        if (array_sum($d['byWeekday']) < 10) {
            $a['weekdays'] = 'Too little activity in this period to see a pattern.';
        } else {
            $busiest = array_search(max($d['byWeekday']), $d['byWeekday'], true);
            $quietest = array_search(min($d['byWeekday']), $d['byWeekday'], true);
            $a['weekdays'] = $b($dayNames[$busiest]) . ' are busiest; ' . $b($dayNames[$quietest]) . ' are the quietest.';
        }

        // Is the work shared across officers?
        $logged = $d['logsInRange']->count();
        $people = $d['logsInRange']->pluck('user_name')->unique()->count();
        if ($logged === 0) {
            $a['officers'] = 'No one logged any action in this period.';
        } else {
            $top = $d['activeOfficers']->first();
            $share = (int) round($top->total / $logged * 100);
            $a['officers'] = $b($top->user_name) . ' did ' . $b($share . '%') . ' of all logged actions'
                . ($share >= 50 && $people > 1
                    ? ', so most of the work rests on one person. Others may need training or access.'
                    : ($people > 1 ? '; the work is spread across ' . $people . ' people.' : '.'));
        }

        $latest = $d['recentActivity']->first();
        $a['latest'] = $latest
            ? 'Last action ' . $b($latest->created_at->diffForHumans()) . ' by ' . e($latest->user_name) . '.'
            : 'Nothing logged in this period.';

        // Are backups running?
        $bk = $d['backups'];
        $a['backups'] = ! $bk['last']
            ? $b('No backup has ever run.') . ' Run one now from Backups.'
            : 'Last backup ' . $b($bk['last']->diffForHumans())
                . ($bk['total'] > 0 ? '; ' . $bk['success'] . ' of ' . $bk['total'] . ' runs in this period succeeded.' : '; none ran in this period.')
                . ($bk['last_age_hours'] > 24 * 7 ? ' That is over a week old.' : '');

        // Is anyone trying to break in?
        $signs = count($d['loginAnomalies']['bruteForce']) + count($d['loginAnomalies']['multiIp']) + count($d['loginAnomalies']['newLocation']);
        $a['security'] = $signs === 0
            ? 'No warning signs in the last 7 days.'
            : $b($signs . ' warning ' . Str::plural('sign', $signs)) . ' in the last 7 days. Review them below.';

        return $a;
    }

    private function categorize(string $activity): string
    {
        foreach (self::ACTIVITY_CATEGORIES as $label => $pattern) {
            if (preg_match($pattern, $activity)) {
                return $label;
            }
        }

        return 'Other';
    }

    /**
     * Cumulative storage for the last 6 months plus a 3-month linear
     * projection from the average month-over-month increase observed.
     *
     * @return array{0: array, 1: array}
     */
    private function storageGrowth(Carbon $now): array
    {
        $monthsOfHistory = 6;
        $monthlyAdds = File::selectRaw("DATE_FORMAT(created_at, '%Y-%m') as ym, SUM(size) as total")
            ->where('created_at', '>=', $now->copy()->subMonths($monthsOfHistory - 1)->startOfMonth())
            ->groupBy('ym')
            ->pluck('total', 'ym');

        // Running total as of the start of the earliest bucket, so the
        // trend line reflects real cumulative storage, not just that
        // month's additions.
        $baseline = File::where('created_at', '<', $now->copy()->subMonths($monthsOfHistory - 1)->startOfMonth())->sum('size');

        // Walk every month explicitly - a month with zero uploads still
        // needs a point, otherwise a GROUP BY silently skipping it would
        // draw a misleading jump.
        $storageTrend = [];
        $running = $baseline;
        for ($i = $monthsOfHistory - 1; $i >= 0; $i--) {
            $month = $now->copy()->subMonthsNoOverflow($i);
            $running += $monthlyAdds[$month->format('Y-m')] ?? 0;
            $storageTrend[] = ['label' => $month->format('M y'), 'bytes' => (float) $running];
        }

        $projection = [];
        if (count($storageTrend) >= 2) {
            $first = $storageTrend[0]['bytes'];
            $last = end($storageTrend)['bytes'];
            $avgMonthlyGrowth = ($last - $first) / (count($storageTrend) - 1);

            $cursor = $last;
            for ($i = 1; $i <= 3; $i++) {
                $cursor += $avgMonthlyGrowth;
                $projection[] = [
                    'label' => $now->copy()->addMonthsNoOverflow($i)->format('M y'),
                    'bytes' => max(0, $cursor),
                ];
            }
        }

        return [$storageTrend, $projection];
    }

    /**
     * $activityByCategory, $activeOfficers and $backups are null for a regular
     * Admin (SuperAdmin-only sections), and their rows are left out.
     */
    private function exportCsv(string $periodLabel, array $kpis, array $trend, $activityByCategory, $activeOfficers, $documentTypes, $batchStatuses, $topFolders, array $pipeline, array $preservation, ?array $backups)
    {
        $filename = 'libris-report-' . Carbon::now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($periodLabel, $kpis, $trend, $activityByCategory, $activeOfficers, $documentTypes, $batchStatuses, $topFolders, $pipeline, $preservation, $backups) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel reads it correctly

            // Spreadsheet apps execute cells starting with = + - @ as formulas.
            $safe = fn ($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;

            fputcsv($out, ['Libris Reports & Analytics']);
            fputcsv($out, ['Period', $periodLabel]);
            fputcsv($out, ['Generated', Carbon::now()->format('Y-m-d H:i')]);
            fputcsv($out, []);

            fputcsv($out, ['SUMMARY', 'This period', 'Previous period', 'Change %']);
            foreach ($kpis as $k) {
                fputcsv($out, [$k['label'], $k['value'], $k['previous'], $k['delta'] ?? 'n/a']);
            }
            fputcsv($out, []);

            $withActions = isset($trend['actions']);
            fputcsv($out, array_merge(['TREND', 'Files uploaded', 'Documents digitized'], $withActions ? ['Logged actions'] : []));
            foreach ($trend['labels'] as $i => $label) {
                fputcsv($out, array_merge([$label, $trend['uploads'][$i], $trend['documents'][$i]], $withActions ? [$trend['actions'][$i]] : []));
            }
            fputcsv($out, []);

            if ($activityByCategory !== null) {
                fputcsv($out, ['ACTIVITY BY CATEGORY', 'Actions']);
                foreach ($activityByCategory as $row) {
                    fputcsv($out, [$safe($row['label']), $row['total']]);
                }
                fputcsv($out, []);
            }

            if ($activeOfficers !== null) {
                fputcsv($out, ['MOST ACTIVE OFFICERS', 'Actions', 'Last seen']);
                foreach ($activeOfficers as $o) {
                    fputcsv($out, [$safe($o->user_name), $o->total, $o->last_seen]);
                }
                fputcsv($out, []);
            }

            fputcsv($out, ['DOCUMENTS BY TYPE', 'Count']);
            foreach ($documentTypes as $row) {
                fputcsv($out, [$safe($row['label']), $row['total']]);
            }
            fputcsv($out, []);

            fputcsv($out, ['SCAN BATCHES BY STATUS', 'Count']);
            foreach ($batchStatuses as $row) {
                fputcsv($out, [$row['label'], $row['total']]);
            }
            fputcsv($out, ['Avg. turnaround (hours, ingest to filed)', $pipeline['turnaround'] ?? 'n/a']);
            fputcsv($out, ['Awaiting review now', $pipeline['awaiting']]);
            fputcsv($out, []);

            fputcsv($out, ['LARGEST FOLDERS', 'Files', 'Size']);
            foreach ($topFolders as $f) {
                fputcsv($out, [$safe($f->name), $f->files, $f->formatted]);
            }
            fputcsv($out, []);

            fputcsv($out, ['PRESERVATION', 'Value']);
            fputcsv($out, ['Archives total', $preservation['archives']]);
            fputcsv($out, ['Fixity OK', $preservation['fixity_ok']]);
            fputcsv($out, ['Fixity FAILED', $preservation['fixity_failed']]);
            fputcsv($out, ['Fixity not yet checked', $preservation['fixity_unchecked']]);
            if ($backups !== null) {
                fputcsv($out, ['Backups in period', $backups['total']]);
                fputcsv($out, ['Backups successful', $backups['success']]);
                fputcsv($out, ['Backups local-only / partial', $backups['partial']]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** The Student engagement tab as CSV: every panel's question, answer and numbers. */
    private function exportEngagementCsv(string $periodLabel, array $e)
    {
        $filename = 'libris-student-engagement-' . Carbon::now()->format('Ymd-His') . '.csv';
        $plain = fn (string $html) => html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return response()->streamDownload(function () use ($periodLabel, $e, $plain) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel reads it correctly

            // Spreadsheet apps execute cells starting with = + - @ as formulas -
            // and search terms are typed by anonymous visitors.
            $safe = fn ($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;

            fputcsv($out, ['Libris Reports & Analytics - Student engagement']);
            fputcsv($out, ['Period', $periodLabel]);
            fputcsv($out, ['Recording since', $e['since'] ? $e['since']->format('Y-m-d') : 'not started']);
            fputcsv($out, ['Generated', Carbon::now()->format('Y-m-d H:i')]);
            fputcsv($out, []);

            fputcsv($out, ['SUMMARY', 'This period', 'Comparison period', 'Change %']);
            foreach ($e['kpis'] as $k) {
                fputcsv($out, [$k['label'], $k['value'], $k['previous'] ?? 'n/a', $k['delta'] ?? 'n/a']);
            }
            fputcsv($out, []);

            $section = function (string $question, string $answer, array $header, array $rows) use ($out, $plain, $safe) {
                fputcsv($out, [$question]);
                fputcsv($out, [$plain($answer)]);
                fputcsv($out, $header);
                foreach ($rows as $row) {
                    fputcsv($out, array_map($safe, $row));
                }
                fputcsv($out, []);
            };

            $section('Are students visiting the student page?', $e['visits']['answer'], ['Period', 'Visits', 'First-time visitors'],
                array_map(fn ($label, $v, $f) => [$label, $v, $f], $e['visits']['labels'], $e['visits']['visits'], $e['visits']['fresh']));

            $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
            $section('When are students on the page?', $e['timing']['answer'], array_merge(['Day'], array_map(fn ($h) => sprintf('%02d:00', $h), range(0, 23))),
                array_map(fn ($i) => array_merge([$days[$i]], $e['timing']['grid'][$i]), range(0, 6)));

            $section('Do students use phones or computers?', $e['devices']['answer'], ['Device', 'Visits', 'Share %'],
                array_map(fn ($d) => [$d['label'], $d['total'], $d['percent']], $e['devices']['rows']));

            $n = $e['notifications'];
            $section('Do notifications bring students back?', $n['answer'], ['Measure', 'Value'], [
                ['Devices getting notifications now', $n['active']],
                ['Turned on in this period', $n['on']],
                ['Turned off in this period', $n['off']],
                ['Visits from a notification tap', $n['from_push']],
            ]);

            $section('Which announcements reached the most students?', $e['announcements']['answer'], ['Announcement', 'Students reached', 'Opened in full', 'Attachment downloads'],
                array_map(fn ($r) => [$r['title'], $r['views'], $r['opens'], $r['downloads']], $e['announcements']['rows']));

            $section('How quickly do students see a new announcement?', $e['reach']['answer'], ['Time after posting', 'Share of views %'],
                array_map(fn ($label, $pct) => [$label, $pct], $e['reach']['labels'], $e['reach']['percent']));

            $section('Which documents do students use?', $e['documents']['answer'], ['Document', 'Previews', 'Downloads'],
                array_map(fn ($r) => [$r['name'], $r['previews'], $r['downloads']], $e['documents']['rows']));

            $section('What are students searching for but not finding?', $e['searches']['answer'], ['Search with no results', 'Times'],
                array_map(fn ($r) => [$r->term, $r->n], $e['searches']['missing']));

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
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
