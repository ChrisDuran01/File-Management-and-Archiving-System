<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\File;
use App\Models\Announcement;
use App\Models\OfficerTerm;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Storage;

class StudentController extends Controller
{
    public function dashboard()
    {
        // Only show admin-approved files whose parent folder (if any) isn't restricted
        $files = $this->publiclyVisibleFiles()->latest()->get();

        $announcements = Announcement::latest()->get();

        $officers = OfficerTerm::with(['user', 'position'])
            ->where('status', 'active')
            ->get();

        $siteSettings = SiteSetting::current();

        return view('Student.stuDashboard', [
            'files' => $files,
            'announcements' => $announcements,
            'officers' => $officers,
            'siteSettings' => $siteSettings,

            // Summary
            'totalFiles' => $files->count(),

            'recentFiles' => $this->publiclyVisibleFiles()
                                ->where('created_at', '>=', now()->subDays(7))
                                ->count(),

            // Chart data
            'fileTypes' => $files->groupBy('type')->map->count(),
        ]);
    }

    /**
     * Files eligible for the public student dashboard: marked public AND
     * (no parent folder, or a parent folder that isn't restricted). Being
     * public on the file alone isn't enough - a restricted folder still
     * hides everything inside it from anonymous visitors.
     */
    private function publiclyVisibleFiles()
    {
        return File::where('is_public', 1)
            ->where(function ($query) {
                $query->whereNull('folder_id')
                    ->orWhereHas('folder', function ($folderQuery) {
                        $folderQuery->where('is_restricted', false);
                    });
            });
    }

    public function previewStudentDashboard($id)
    {
        $file = File::with('folder')->findOrFail($id);

        if (! $file->is_public || ($file->folder && $file->folder->is_restricted)) {
            abort(403, 'This file is not available for public preview.');
        }

        try {
            $signedUrl = Storage::disk('cloud')->getAdapter()->getSignedUrl($file->filepath, ['expiresIn' => 3600]);
        } catch (\Throwable) {
            return response()->json(['error' => 'Failed to generate preview URL'], 500);
        }

        return response()->json([
            'url'  => $signedUrl,
            'type' => strtolower($file->type),
            'name' => $file->filename,
        ]);
    }
}