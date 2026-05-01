<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Folder;
use App\Models\File;

class SearchController extends Controller
{
    /**
     * Handle search request
     * Searches both folders and files based on user input
     */
    public function index(Request $request)
    {
        // =============================
        // GET SEARCH QUERY FROM INPUT
        // =============================
        $query = $request->input('query');

        // =============================
        // SEARCH FOLDERS
        // =============================
        // Find folders where name matches the query (partial match)
        $folders = Folder::where('name', 'like', "%{$query}%")->get();

        // =============================
        // SEARCH FILES
        // =============================
        // Find files where filename matches the query (partial match)
        $files = File::where('filename', 'like', "%{$query}%")->get();

        // =============================
        // RETURN RESULTS TO VIEW
        // =============================
        return view('Admin.searchResults', compact('folders', 'files', 'query'));
    }
}