<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\File;
use Illuminate\Support\Facades\Http;

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

   public function previewStudentDashboard($id)
{
    $file = File::findOrFail($id);

    $bucket = env('SUPABASE_BUCKET');
    $url    = env('SUPABASE_URL');
    $key    = env('SUPABASE_SERVICE_KEY');

    $response = Http::withHeaders([
    'Authorization' => 'Bearer ' . $key,
    'apikey'        => $key,
])->post("$url/storage/v1/object/sign/$bucket/" . $file->filepath, [
    'expiresIn' => 3600,
    'transform' => [
        'contentType' => 'image/png' // optional but helps
    ]
]);

    if (! $response->successful()) {
        return response()->json(['error' => 'Failed to generate preview URL'], 500);
    }

    $signedUrl = $url . '/storage/v1' . $response['signedURL'];

    return response()->json([
        'url'  => $signedUrl,
        'type' => strtolower($file->type),
        'name' => $file->filename,
    ]);
}

}