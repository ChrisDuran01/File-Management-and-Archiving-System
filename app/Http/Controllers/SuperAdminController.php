<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Support\ActivityType;
use App\Support\SchoolYear;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class SuperAdminController extends Controller
{
    /** Logs sent per load - the page asks for the next batch as it is scrolled. */
    public const LOGS_PER_LOAD = 50;

    /** Quick periods on Activity Logs, beside All time, School year and Custom. */
    public const LOG_PERIODS = ['today' => 'Today', '7d' => '7 days', 'month' => 'This month'];

    /**
     * Activity Logs, newest first, a batch at a time (infinite scroll).
     *
     * Each batch is "older than the last one shown" (`before` = that log's
     * id), not "page N": logs keep arriving while someone reads, and a page
     * number would then shift, showing some twice and skipping others.
     *
     * The page asks for the next batch with an XHR and gets JSON back; the
     * first load is a normal page. Search runs here, not in the browser, so
     * it finds old entries that haven't been loaded yet.
     */
    public function activityLogs(Request $request)
    {
        $request->validate([
            'from_date' => 'nullable|date',
            'to_date'   => 'nullable|date',
            'range'     => ['nullable', Rule::in(array_keys(self::LOG_PERIODS))],
            'sy'        => 'nullable|string|max:20',
            'user'      => 'nullable|string|max:255',
            'type'      => ['nullable', Rule::in(array_keys(ActivityType::options()))],
            'q'         => 'nullable|string|max:100',
            'before'    => 'nullable|integer|min:1',
        ]);

        [$from, $to, $period] = $this->logPeriod($request);
        $filtered = $this->filteredLogs($request, $from, $to);

        $batch = (clone $filtered)
            ->when($request->before, fn (Builder $query, $before) => $query->where('id', '<', $before))
            ->orderByDesc('id')
            ->limit(self::LOGS_PER_LOAD + 1)
            ->get();

        $hasMore = $batch->count() > self::LOGS_PER_LOAD;
        $logs    = $batch->take(self::LOGS_PER_LOAD);
        $next    = $hasMore ? $logs->last()->id : null;

        // The counts describe everything that matches, not just what is
        // loaded - so a later batch doesn't need them again.
        $stats = ($request->ajax() && $request->before) ? null : [
            'events' => (clone $filtered)->count(),
            'users'  => (clone $filtered)->distinct()->count('user_name'),
            'ips'    => (clone $filtered)->distinct()->count('ip_address'),
        ];

        if ($request->ajax()) {
            return response()->json([
                'html'    => view('SuperAdmin.partials.activity-log-rows', [
                    'logs'      => $logs,
                    'hasMore'   => $hasMore,
                    'firstLoad' => ! $request->before,
                ])->render(),
                'next'    => $next,
                'hasMore' => $hasMore,
                'stats'   => $stats,
            ]);
        }

        return view('SuperAdmin.activityLogs', compact('logs', 'hasMore', 'next', 'stats', 'period', 'from', 'to') + [
            'schoolYears' => SchoolYear::options(),
            // Names, plus the emails typed into failed sign-ins (the view splits them).
            'logUsers'    => ActivityLog::query()->distinct()->orderBy('user_name')->pluck('user_name'),
            'types'       => ActivityType::options(),
        ]);
    }

    /**
     * The period picked on the page: a school year, a quick period, or
     * custom dates, where either end may be left open. Nothing picked is
     * all time.
     *
     * @return array{0: ?CarbonInterface, 1: ?CarbonInterface, 2: string} first day, last day (whole days), period key
     */
    private function logPeriod(Request $request): array
    {
        if (SchoolYear::isValid($request->input('sy'))) {
            [$start, $end] = SchoolYear::range($request->input('sy'));

            return [$start, $end->copy()->startOfDay(), 'sy'];
        }

        $today = Carbon::today();

        return match ($request->input('range')) {
            'today' => [$today, $today, 'today'],
            '7d'    => [$today->copy()->subDays(6), $today, '7d'],
            'month' => [$today->copy()->startOfMonth(), $today, 'month'],
            default => $request->filled('from_date') || $request->filled('to_date')
                ? [
                    $request->filled('from_date') ? Carbon::parse($request->input('from_date'))->startOfDay() : null,
                    $request->filled('to_date') ? Carbon::parse($request->input('to_date'))->startOfDay() : null,
                    'custom',
                ]
                : [null, null, 'all'],
        };
    }

    private function filteredLogs(Request $request, ?CarbonInterface $from, ?CarbonInterface $to): Builder
    {
        return ActivityLog::query()
            // Ranges rather than whereDate(), so an index on created_at can be used.
            ->when($from, fn (Builder $query) => $query->where('created_at', '>=', $from))
            ->when($to, fn (Builder $query) => $query->where('created_at', '<', $to->copy()->addDay()))
            ->when($request->filled('user'), fn (Builder $query) => $query->where('user_name', $request->input('user')))
            ->when(ActivityType::isValid($request->input('type')), fn (Builder $query) => ActivityType::scope($query, $request->input('type')))
            ->when(trim((string) $request->q), function (Builder $query, string $search) {
                $like = '%' . addcslashes($search, '%_\\') . '%';
                $query->where(fn (Builder $match) => $match
                    ->where('user_name', 'like', $like)
                    ->orWhere('activity', 'like', $like)
                    ->orWhere('ip_address', 'like', $like));
            });
    }
}
