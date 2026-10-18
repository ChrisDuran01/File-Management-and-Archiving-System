<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SuperAdminController extends Controller
{
    /** Logs sent per load - the page asks for the next batch as it is scrolled. */
    public const LOGS_PER_LOAD = 50;

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
            'q'         => 'nullable|string|max:100',
            'before'    => 'nullable|integer|min:1',
        ]);

        $filtered = $this->filteredLogs($request);

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

        return view('SuperAdmin.activityLogs', compact('logs', 'hasMore', 'next', 'stats'));
    }

    private function filteredLogs(Request $request): Builder
    {
        return ActivityLog::query()
            // Ranges rather than whereDate(), so an index on created_at can be used.
            ->when($request->from_date, fn (Builder $query, $from) => $query->where('created_at', '>=', Carbon::parse($from)->startOfDay()))
            ->when($request->to_date, fn (Builder $query, $to) => $query->where('created_at', '<', Carbon::parse($to)->addDay()->startOfDay()))
            ->when(trim((string) $request->q), function (Builder $query, string $search) {
                $like = '%' . addcslashes($search, '%_\\') . '%';
                $query->where(fn (Builder $match) => $match
                    ->where('user_name', 'like', $like)
                    ->orWhere('activity', 'like', $like)
                    ->orWhere('ip_address', 'like', $like));
            });
    }
}
