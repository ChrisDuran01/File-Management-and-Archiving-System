<?php
namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\File;
use App\Models\Folder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FolderController extends Controller
{
    /**
     * Create a new folder
     * Supports optional parent folder (for nested folders)
     */
    public function store(Request $request)
    {
        // Validate folder name
        $request->validate([
            'folder_name' => 'required',
        ]);

        // =============================
        // STEP 1: CREATE FOLDER
        // =============================
        $folder = Folder::create([
            'name'      => $request->folder_name,
            'parent_id' => $request->parent_id ?? null, // null = root folder
        ]);

        // =============================
        // STEP 2: LOG ACTIVITY
        // =============================
        ActivityLog::create([
            'user_name' => Auth::user()->name,
            'activity'  => 'Added folder: ' . $folder->name,
        ]);

        // Redirect back to previous page
        return redirect()->back();
    }

    /**
     * Display all folders and root-level files
     */
    public function index()
    {
        // Get all folders
        $folders = Folder::all();

        // Get files that are not inside any folder (root files)
        $rootFiles = File::whereNull('folder_id')->get();
        
        // Pass data to view
        return view('Admin.folders', compact('folders', 'rootFiles'));
    }

    /**
     * Admin dashboard summary (folders only)
     */
    public function adminDashboard()
    {
        // Count total folders
        $totalFolders = Folder::count();

        // Get 5 most recent folders
        $recentFolders = Folder::latest()->take(5)->get();

        return view('Admin.adminDashboard', compact(
            'totalFolders',
            'recentFolders'
        ));
    }

    /**
     * Show a specific folder
     * Includes subfolders and files inside it
     */
    public function show($id)
    {
        // Get selected folder
        $folders = Folder::findOrFail($id);

        // =============================
        // FETCH RELATED DATA
        // =============================

    

        // Files inside this folder
        $files = File::where('folder_id', $id)->get();

        // Return folder view
        return view('Admin.showFolder', compact('folders', 'files'));
    }

    /**
     * Rename an existing folder
     */
    public function update(Request $request, $id)
    {
        // =============================
        // VALIDATE INPUT
        // =============================
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        // =============================
        // FIND FOLDER
        // =============================
        $folder = Folder::findOrFail($id);

        // =============================
        // UPDATE FOLDER NAME
        // =============================
        $folder->name = $request->name;
        $folder->save();

        // Redirect back with success message
        return redirect()->back()->with('success', 'Folder renamed successfully.');
    }

}

