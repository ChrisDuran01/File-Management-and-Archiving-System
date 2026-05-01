<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\File;

class StudentController extends Controller
{
    public function dashboard()
    {
        // Only show admin-approved files
        $files = File::where('is_public', 1)
                    ->latest()
                    ->get();

        return view('Student.stuDashboard', [
            'files' => $files,

            // Summary
            'totalFiles' => $files->count(),

            'recentFiles' => File::where('is_public', 1)
                                ->where('created_at', '>=', now()->subDays(7))
                                ->count(),

            // Chart data
            'fileTypes' => $files->groupBy('type')->map->count(),
        ]);
    }
}