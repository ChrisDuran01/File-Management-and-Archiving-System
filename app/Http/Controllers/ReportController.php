<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\File;
use App\Models\Folder;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /**
     * Display analytics and reports dashboard
     */
    public function index()
    {
        // =============================
        // DATE SETUP
        // =============================
        $now           = Carbon::now();
        $thisMonth     = Carbon::now()->startOfMonth();
        $lastMonth     = Carbon::now()->subMonth()->startOfMonth();
        $lastMonthEnd  = Carbon::now()->subMonth()->endOfMonth();

        // =============================
        // METRIC CARDS (TOTAL COUNTS)
        // =============================
        $totalFiles   = File::count();
        $totalFolders = Folder::count();
        $totalUsers   = User::count();

        // Total storage used (in bytes)
        $totalStorage = File::sum('size');

        // =============================
        // MONTH-OVER-MONTH COMPARISON
        // =============================

        // Files
        $filesLastMonth = File::whereBetween('created_at', [$lastMonth, $lastMonthEnd])->count();
        $filesThisMonth = File::where('created_at', '>=', $thisMonth)->count();

        $filesDelta = $filesLastMonth > 0
            ? round((($filesThisMonth - $filesLastMonth) / $filesLastMonth) * 100)
            : 0;

        // Folders
        $foldersLastMonth = Folder::whereBetween('created_at', [$lastMonth, $lastMonthEnd])->count();
        $foldersThisMonth = Folder::where('created_at', '>=', $thisMonth)->count();

        $foldersDelta = $foldersLastMonth > 0
            ? round((($foldersThisMonth - $foldersLastMonth) / $foldersLastMonth) * 100)
            : 0;

        // Users
        $usersLastMonth = User::whereBetween('created_at', [$lastMonth, $lastMonthEnd])->count();
        $usersThisMonth = User::where('created_at', '>=', $thisMonth)->count();

        $usersDelta = $usersLastMonth > 0
            ? round((($usersThisMonth - $usersLastMonth) / $usersLastMonth) * 100)
            : 0;

        // Storage growth (MB difference from last month)
        $storageLastMonth = File::whereBetween('created_at', [$lastMonth, $lastMonthEnd])->sum('size');
        $storageDelta = round(($totalStorage - $storageLastMonth) / 1024 / 1024, 1);

        // =============================
        // FORMAT STORAGE (MB / GB)
        // =============================
        $storageFormatted = $totalStorage >= 1073741824
            ? number_format($totalStorage / 1073741824, 2) . ' GB'
            : number_format($totalStorage / 1048576, 2) . ' MB';

        // =============================
        // MONTHLY DATA (LAST 12 MONTHS)
        // =============================

        // File uploads per month
        $uploads = File::selectRaw("
                DATE_FORMAT(created_at,'%b') as label,
                MONTH(created_at) as m,
                COUNT(*) as total
            ")
            ->where('created_at', '>=', $now->copy()->subMonths(11)->startOfMonth())
            ->groupByRaw("label, m")
            ->orderBy('m')
            ->get();

        // Folder creation per month
        $folders = Folder::selectRaw("
                DATE_FORMAT(created_at,'%b') as label,
                MONTH(created_at) as m,
                COUNT(*) as total
            ")
            ->where('created_at', '>=', $now->copy()->subMonths(11)->startOfMonth())
            ->groupByRaw("label, m")
            ->orderBy('m')
            ->get();

        // =============================
        // 30-DAY DATA (DAILY)
        // =============================

        $uploads30d = File::selectRaw("
                DATE_FORMAT(created_at,'%m/%d') as label,
                DATE(created_at) as d,
                COUNT(*) as total
            ")
            ->where('created_at', '>=', $now->copy()->subDays(29)->startOfDay())
            ->groupByRaw("label, d")
            ->orderBy('d')
            ->get();

        $folders30d = Folder::selectRaw("
                DATE_FORMAT(created_at,'%m/%d') as label,
                DATE(created_at) as d,
                COUNT(*) as total
            ")
            ->where('created_at', '>=', $now->copy()->subDays(29)->startOfDay())
            ->groupByRaw("label, d")
            ->orderBy('d')
            ->get();

        // =============================
        // 90-DAY DATA (WEEKLY)
        // =============================

        $uploads90d = File::selectRaw("
                CONCAT('W', WEEK(created_at)) as label,
                WEEK(created_at) as w,
                COUNT(*) as total
            ")
            ->where('created_at', '>=', $now->copy()->subDays(89)->startOfDay())
            ->groupByRaw("label, w")
            ->orderBy('w')
            ->get();

        $folders90d = Folder::selectRaw("
                CONCAT('W', WEEK(created_at)) as label,
                WEEK(created_at) as w,
                COUNT(*) as total
            ")
            ->where('created_at', '>=', $now->copy()->subDays(89)->startOfDay())
            ->groupByRaw("label, w")
            ->orderBy('w')
            ->get();

        // =============================
        // FILE TYPE BREAKDOWN
        // =============================

        // Chart colors and labels
        $typeColors = ['#3b82f6','#ef4444','#f59e0b','#6b7280'];
        $typeLabels = ['Images','PDFs','Docs','Other'];

        // Count files by extension
        $imgCount = File::whereIn(DB::raw('LOWER(RIGHT(filename, 3))'), ['png','jpg','gif'])
            ->orWhereIn(DB::raw('LOWER(RIGHT(filename, 4))'), ['jpeg','webp'])
            ->count();

        $pdfCount = File::where(DB::raw('LOWER(RIGHT(filename, 3))'), 'pdf')->count();

        $docCount = File::whereIn(DB::raw('LOWER(RIGHT(filename, 4))'), ['docx','xlsx'])
            ->orWhereIn(DB::raw('LOWER(RIGHT(filename, 3))'), ['doc','xls'])
            ->count();

        // Everything else
        $otherCount = max(0, $totalFiles - $imgCount - $pdfCount - $docCount);

        $typeCounts = [$imgCount, $pdfCount, $docCount, $otherCount];

        // Build percentage-based dataset
        $fileTypes = collect($typeLabels)->map(fn($label, $i) => [
            'label'   => $label,
            'percent' => $totalFiles > 0
                ? round(($typeCounts[$i] / $totalFiles) * 100)
                : 0,
            'color'   => $typeColors[$i],
        ]);

        // =============================
        // RECENT FILES
        // =============================

        $recentFiles = File::latest()->take(10)->get();

        // =============================
        // COMBINED DATA FOR CHARTS
        // =============================

        $uploadData = [
            '30d' => [
                'labels'  => $uploads30d->pluck('label')->values(),
                'files'   => $uploads30d->pluck('total')->values(),
                'folders' => $folders30d->pluck('total')->values(),
            ],
            '90d' => [
                'labels'  => $uploads90d->pluck('label')->values(),
                'files'   => $uploads90d->pluck('total')->values(),
                'folders' => $folders90d->pluck('total')->values(),
            ],
            '12m' => [
                'labels'  => $uploads->pluck('label')->values(),
                'files'   => $uploads->pluck('total')->values(),
                'folders' => $folders->pluck('total')->values(),
            ],
        ];

        // =============================
        // RETURN VIEW WITH ALL DATA
        // =============================
        return view('Admin.reports', compact(
            'totalFiles', 'totalFolders', 'totalUsers', 'storageFormatted',
            'filesDelta', 'foldersDelta', 'usersDelta', 'storageDelta',
            'uploads', 'folders',
            'uploads30d', 'folders30d',
            'uploads90d', 'folders90d',
            'fileTypes', 'recentFiles',
            'uploadData'
        ));
    }
}