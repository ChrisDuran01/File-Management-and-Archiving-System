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
            ->get()
            ->sortBy(fn ($term) => $this->positionRank($term->position->position_name ?? ''))
            ->values();

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

    /**
     * Ranks a position name by where it falls in the standard student
     * government hierarchy (President, Vice President, Secretary, ...) so
     * officers display in that order regardless of how they were entered
     * or fetched from the database. Matching is by keyword, so labels like
     * "SSLG - Vice President" still match "Vice President". Anything that
     * doesn't match a known role sorts after all recognized ones, in the
     * order it was found.
     */
    private function positionRank(string $positionName): int
    {
        // Rank values in display order. "vice president" is checked before
        // "president" below since "president" is a substring of it - a
        // check in display order would otherwise mis-rank Vice President
        // as President.
        $rank = [
            'president' => 0,
            'vice president' => 1,
            'secretary' => 2,
            'treasurer' => 3,
            'auditor' => 4,
            'public information officer' => 5,
            'pio' => 5,
            'business manager' => 6,
            'peace officer' => 7,
            'representative' => 8,
        ];

        $checkOrder = ['vice president', 'president', 'secretary', 'treasurer', 'auditor',
            'public information officer', 'pio', 'business manager', 'peace officer', 'representative'];

        $name = strtolower($positionName);

        foreach ($checkOrder as $keyword) {
            if (str_contains($name, $keyword)) {
                return $rank[$keyword];
            }
        }

        return count($rank);
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