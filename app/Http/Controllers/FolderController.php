<?php
namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\File;
use App\Models\Folder;
use App\Models\OfficerTerm;
use App\Models\User;
use App\Support\SchoolYear;
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
        'folder_name' => 'required|string|max:255',
        'school_year' => 'nullable|string',
    ]);

    // A folder for an earlier school year: old records brought into the
    // system. It is stored away (read-only) from the start, like the rest
    // of that year, and remembers who added it.
    $pastYear = $request->filled('school_year') ? $request->school_year : null;
    if ($pastYear !== null && ! Folder::canAddToPastYear($pastYear)) {
        $open = Folder::pastYearsOpen();

        return back()->with('error', 'Old records can only be added to the last '.Folder::PAST_YEARS_OPEN.' school years, '
            .end($open).' to '.$open[0].'.');
    }

    $term = OfficerTerm::activeFor(Auth::id());

    // Unique among the folders shown together: the current folders, or one
    // past school year. So every year can have its own "Resolution".
    $folder = Folder::create([
        'name' => Folder::uniqueName($request->folder_name, $pastYear),
        'created_by' => Auth::id(),
        'officer_term_id' => $term?->id,
    ] + ($pastYear ? [
        'school_year'    => $pastYear,
        'is_archived'    => true,
        'added_later_at' => now(),
        'added_later_by' => Auth::id(),
    ] : [
        'school_year' => $term?->school_year,
    ]));

    ActivityLog::create([
        'user_name' => Auth::user()->name,
        'activity'  => 'Added folder: ' . $folder->name . ($pastYear ? ' (to past school year '.$pastYear.')' : ''),
        'ip_address' => $request->ip()
    ]);

    if ($pastYear) {
        return redirect()->route('folders.history', $pastYear)
            ->with('success', 'Folder "'.$folder->name.'" added to school year '.$pastYear.'. Open it to add that year\'s files.');
    }

    return redirect()->back()->with('success', 'Folder created successfully.');
}
    /**
     * Display all folders 
     */
    public function index()
    {
        // Only show folders that haven't been stored away under a past school
        // year. Who has access is loaded for all of them in one query (eager
        // loading) rather than one query per folder.
        $folders = Folder::where('is_archived', false)
            ->with(['allowedUsers' => fn ($query) => $query->select('users.id')])
            ->get();

        // Restricted folders still show up in the listing (so their
        // existence isn't a secret) but are locked for anyone without
        // access - flag per folder so the view can render them accordingly.
        // The access list is only for folders this user may manage (the
        // access editor) - nobody else needs to see who was given access.
        $user = Auth::user();
        $folders->each(function (Folder $folder) use ($user) {
            $folder->accessible = $folder->isAccessibleBy($user);
            $folder->manageable = $folder->isManageableBy($user);
            $folder->allowed_user_ids = $folder->manageable ? $folder->allowedUsers->pluck('id') : collect();
        });

        // Get files that are not inside any folder (root files), same rule
        $rootFiles = File::whereNull('folder_id')->where('is_archived', false)->get();

        // School years available to browse (from folders stored away previously)
        $pastSchoolYears = Folder::where('is_archived', true)
            ->whereNotNull('school_year')
            ->distinct()
            ->orderByDesc('school_year')
            ->pluck('school_year');

        // School years old records can be added to.
        $earlierYears = Folder::pastYearsOpen();

        // Officers only (SuperAdmin always has access, so isn't listed) -
        // for the restricted-folder access picker.
        $officerIds = DB::table('officer_terms')->pluck('user_id')->unique();
        $officers = User::whereIn('id', $officerIds)->orderBy('name')->get(['id', 'name']);

        // Same layout-by-role split already used in ProfileController - a
        // SuperAdmin keeps their own sidebar instead of swapping into the
        // Officer one.
        $layout = Folder::isSuperAdmin($user) ? 'SuperAdmin.homeSuperAdmin' : 'Admin.home';

        // Pass data to view
        return view('Admin.folders', compact('folders', 'rootFiles', 'pastSchoolYears', 'earlierYears', 'officers', 'layout'));
    }

    /**
     * Browse folders and files that were stored under a past school year
     */
    public function history($year)
    {
        abort_unless(SchoolYear::isValid($year), 404);

        $folders = Folder::where('is_archived', true)->where('school_year', $year)
            ->with('addedLaterBy:id,name')->orderBy('name')->get();

        $rootFiles = File::whereNull('folder_id')
            ->where('is_archived', true)
            ->where('school_year', $year)
            ->get();

        // Old records may be brought into any earlier school year.
        $canAdd = Folder::canAddToPastYear($year);

        $layout = Folder::isSuperAdmin(Auth::user()) ? 'SuperAdmin.homeSuperAdmin' : 'Admin.home';

        return view('Admin.folderHistory', compact('folders', 'rootFiles', 'year', 'canAdd', 'layout'));
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
        $files = File::where('folder_id', $id)->with(['duplicateOf.folder', 'addedLaterBy:id,name'])->get();

        // A past school year's folder takes new files (old records brought
        // in), but its own records stay as they are.
        $addingToPastYear = $folders->is_archived && Folder::canAddToPastYear($folders->school_year);

        $layout = Folder::isSuperAdmin(Auth::user()) ? 'SuperAdmin.homeSuperAdmin' : 'Admin.home';

        // Return folder view
        return view('Admin.showFolder', compact('folders', 'files', 'addingToPastYear', 'layout'));
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

        if ($folder->isLockedFor(Auth::user())) {
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

        if ($folder->isLockedFor(Auth::user())) {
            return back()->with('error', 'This folder is stored under a past school year and cannot be modified.');
        }

        // An added-later folder may hold only added-later files; anything
        // made during that school year keeps it from being trashed.
        if ($folder->is_archived && File::where('folder_id', $folder->id)->whereNull('added_later_at')->exists()) {
            return back()->with('error', 'This folder holds records from its own school year, so it cannot be moved to Trash.');
        }

        $folderName = $folder->name;

        // Soft delete the folder and everything inside it as one unit -
        // restorable from the Trash screen. Before soft deletes this relied
        // on the files table's ON DELETE CASCADE, which no longer fires.
        $folder->moveToTrash();

        ActivityLog::create([
            'user_name'  => Auth::user()->name,
            'activity'   => 'Moved folder to Trash: ' . $folderName,
            'ip_address' => request()->ip()
        ]);

        $message = 'Folder and its contents moved to Trash. They can be restored from there within ' . config('trash.retention_days') . ' days.';

        // From inside the folder there is no page to go back to.
        return $folder->is_archived && $folder->school_year
            ? redirect()->route('folders.history', $folder->school_year)->with('success', $message)
            : redirect()->back()->with('success', $message);



}

}