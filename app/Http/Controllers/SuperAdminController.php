<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ActivityLog;
use App\Models\User;

class SuperAdminController extends Controller
{
    public function activityLogs(Request $request)
    {
        // Start query
        $query = ActivityLog::query();

        // Filter by dates if submitted
        if ($request->from_date) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->to_date) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        // Fetch logs
        $logs = $query->latest()->get();

        // Pass to view
        return view('SuperAdmin.activityLogs', compact('logs'));
    }

    public function index(Request $request)
{
    $query = ActivityLog::query();

    // Filter FROM date
    if ($request->from_date) {
        $query->whereDate('created_at', '>=', $request->from_date);
    }

    // Filter TO date
    if ($request->to_date) {
        $query->whereDate('created_at', '<=', $request->to_date);
    }

    $logs = $query->latest()->get();

    return view('SuperAdmin.activityLogs', compact('logs'));
}

   
}