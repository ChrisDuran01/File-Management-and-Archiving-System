<?php
namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\File;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FolderController extends Controller
{
    /**
     * Create a new folder
     * Supports optional parent folder (for nested folders)
     */
   public function store(Request $request)
{
    $request->validate([
        'folder_name' => 'required',
    ]);

    $baseName = $request->folder_name;
    $name = $baseName;
    $counter = 1;

    // Keep checking until we find a unique name
    while (Folder::where('name', $name)->exists()) {
        $name = $baseName . ' (' . $counter . ')';
        $counter++;
    }

    $folder = Folder::create([
        'name' => $name,
        'created_by' => Auth::id(),
    ]);

    ActivityLog::create([
        'user_name' => Auth::user()->name,
        'activity'  => 'Added folder: ' . $folder->name,
        'ip_address' => $request->ip()
    ]);

    return redirect()->back()->with('success', 'Folder created successfully.');
}
    /**
     * Display all folders 
     */
    public function index()
    {
        // Only show folders that haven't been stored away under a past school year
        $folders = Folder::where('is_archived', false)->get();

        // Restricted folders still show up in the listing (so their
        // existence isn't a secret) but are locked for anyone without
        // access - flag per folder so the view can render them accordingly.
        $user = Auth::user();
        $folders->each(function (Folder $folder) use ($user) {
            $folder->accessible = $folder->isAccessibleBy($user);
            $folder->manageable = $folder->isManageableBy($user);
            $folder->allowed_user_ids = $folder->allowedUsers()->pluck('users.id');
        });

        // Get files that are not inside any folder (root files), same rule
        $rootFiles = File::whereNull('folder_id')->where('is_archived', false)->get();

        // School years available to browse (from folders stored away previously)
        $pastSchoolYears = Folder::where('is_archived', true)
            ->whereNotNull('school_year')
            ->distinct()
            ->orderByDesc('school_year')
            ->pluck('school_year');

        // Officers only (SuperAdmin always has access, so isn't listed) -
        // for the restricted-folder access picker.
        $officerIds = DB::table('officer_terms')->pluck('user_id')->unique();
        $officers = User::whereIn('id', $officerIds)->orderBy('name')->get(['id', 'name']);

        // Same layout-by-role split already used in ProfileController - a
        // SuperAdmin keeps their own sidebar instead of swapping into the
        // Officer one.
        $layout = Folder::isSuperAdmin($user) ? 'SuperAdmin.homeSuperAdmin' : 'Admin.home';

        // Pass data to view
        return view('Admin.folders', compact('folders', 'rootFiles', 'pastSchoolYears', 'officers', 'layout'));
    }

    /**
     * Browse folders and files that were stored under a past school year
     */
    public function history($year)
    {
        $folders = Folder::where('is_archived', true)->where('school_year', $year)->get();

        $rootFiles = File::whereNull('folder_id')
            ->where('is_archived', true)
            ->where('school_year', $year)
            ->get();

        return view('Admin.folderHistory', compact('folders', 'rootFiles', 'year'));
    }

    /**
     * Start a new school year: tag every current folder and file with the
     * given school year, hide them from the main page, and leave it empty
     * again for the new year's uploads.
     */
    public function startNewSchoolYear(Request $request)
    {
        $request->validate([
            'school_year' => 'required|string|max:255',
        ]);

        $year = $request->school_year;

        Folder::where('is_archived', false)->update([
            'school_year' => $year,
            'is_archived' => true,
        ]);

        File::where('is_archived', false)->update([
            'school_year' => $year,
            'is_archived' => true,
        ]);

        ActivityLog::create([
            'user_name'  => Auth::user()->name,
            'activity'   => 'Started new school year: stored all folders and files under SY ' . $year,
            'ip_address' => $request->ip()
        ]);

        return redirect()->route('folders.index')
            ->with('success', "All folders and files have been stored under school year {$year}. You can start uploading for the new school year.");
    }

    /**
     * Admin dashboard summary
     */
    public function adminDashboard()
    {
        $totalFolders = Folder::where('is_archived', false)->count();
        $totalFiles   = File::where('is_archived', false)->count();

        $storageUsed = File::where('is_archived', false)->sum('size');
        $storageFormatted = $storageUsed >= 1073741824
            ? number_format($storageUsed / 1073741824, 2) . ' GB'
            : number_format($storageUsed / 1048576, 2) . ' MB';

        // Get 5 most recent folders
        $recentFolders = Folder::where('is_archived', false)->latest()->take(5)->get();

        return view('Admin.adminDashboard', compact(
            'totalFolders',
            'totalFiles',
            'storageFormatted',
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

        if (! $folders->isAccessibleBy(Auth::user())) {
            return redirect()->route('folders.index')
                ->with('error', 'This folder is restricted — you don\'t have access to it.');
        }

        $folders->touchAccessed();

        // =============================
        // FETCH RELATED DATA
        // =============================

        $folders->manageable = $folders->isManageableBy(Auth::user());

        // Files inside this folder
        $files = File::where('folder_id', $id)->get();

        $layout = Folder::isSuperAdmin(Auth::user()) ? 'SuperAdmin.homeSuperAdmin' : 'Admin.home';

        // Return folder view
        return view('Admin.showFolder', compact('folders', 'files', 'layout'));
    }

    /**
     * Update a folder's restriction flag and access list. Only its creator
     * or SuperAdmin may do this - checked here, not just hidden in the UI.
     */
    public function updateAccess(Request $request, $id)
    {
        $folder = Folder::findOrFail($id);

        if (! $folder->isManageableBy(Auth::user())) {
            return back()->with('error', 'Only this folder\'s creator or a SuperAdmin can manage its access.');
        }

        $request->validate([
            'is_restricted' => 'nullable|boolean',
            'allowed_users' => 'array',
            'allowed_users.*' => 'exists:users,id',
        ]);

        $folder->is_restricted = $request->boolean('is_restricted');
        $folder->save();

        $folder->allowedUsers()->sync($request->input('allowed_users', []));

        ActivityLog::create([
            'user_name' => Auth::user()->name,
            'activity' => ($folder->is_restricted ? 'Restricted' : 'Unrestricted').' folder: '.$folder->name,
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', $folder->is_restricted
            ? 'Folder is now restricted.'
            : 'Folder is no longer restricted.');
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

        if (! $folder->isAccessibleBy(Auth::user())) {
            return back()->with('error', 'This folder is restricted — you don\'t have access to it.');
        }

        if ($folder->is_archived) {
            return back()->with('error', 'This folder is stored under a past school year and cannot be modified.');
        }

        $oldName = $folder->name;

        // =============================
        // UPDATE FOLDER NAME
        // =============================
        $folder->name = $request->name;
        $folder->save();

        ActivityLog::create([
            'user_name'  => Auth::user()->name,
            'activity'   => 'Renamed folder "' . $oldName . '" to "' . $folder->name . '"',
            'ip_address' => $request->ip()
        ]);

        // Redirect back with success message
        return redirect()->back()->with('success', 'Folder renamed successfully.');
    }

    /**
     * Delete a folder and all its contents (files and subfolders)
     */
    public function destroy($id)
    {
        $folder = Folder::findOrFail($id);

        if (! $folder->isAccessibleBy(Auth::user())) {
            return back()->with('error', 'This folder is restricted — you don\'t have access to it.');
        }

        if ($folder->is_archived) {
            return back()->with('error', 'This folder is stored under a past school year and cannot be modified.');
        }

        $folderName = $folder->name;
        $folder->delete();

        ActivityLog::create([
            'user_name'  => Auth::user()->name,
            'activity'   => 'Deleted folder: ' . $folderName,
            'ip_address' => request()->ip()
        ]);

        return redirect()->back()->with('success', 'Folder and all its contents deleted successfully.');



}

}