<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Archive;
use App\Models\Document;
use App\Models\Folder;
use App\Models\File;
use Illuminate\Support\Facades\Auth;

class SearchController extends Controller
{
    /**
     * Category words that expand to a set of extensions, so "image" or
     * "spreadsheet" surfaces files whose filename never mentions those
     * words - mirrors the icon categories already used in the search
     * result views.
     */
    private const TYPE_ALIASES = [
        'pdf'         => ['pdf'],
        'doc'         => ['doc', 'docx'],
        'docx'        => ['doc', 'docx'],
        'word'        => ['doc', 'docx'],
        'document'    => ['doc', 'docx', 'pdf', 'txt'],
        'excel'       => ['xls', 'xlsx', 'csv'],
        'spreadsheet' => ['xls', 'xlsx', 'csv'],
        'xlsx'        => ['xls', 'xlsx', 'csv'],
        'image'       => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'],
        'photo'       => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'],
        'picture'     => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'],
    ];

    /**
     * Handle search request
     * Searches both folders and files based on user input
     */
    public function index(Request $request)
    {
        $query = $request->input('query');

        [$folders, $files, $archives, $documents] = $this->performSearch($query);

        return view('Admin.searchResults', compact('folders', 'files', 'archives', 'documents', 'query'));
    }

    /**
     * As-you-type search used by the live dropdown under the search bar.
     * Same matching logic as index(), just capped to a handful of results
     * per section so the dropdown stays short.
     */
    public function live(Request $request)
    {
        $query = trim($request->input('query', ''));

        if ($query === '') {
            return response('');
        }

        [$folders, $files, $archives, $documents] = $this->performSearch($query, limit: 5);

        return view('Admin.searchLive', compact('folders', 'files', 'archives', 'documents', 'query'));
    }

    /**
     * Shared folder/file lookup used by both the full results page and the
     * live dropdown, so the matching and snippet-building logic only lives
     * in one place.
     */
    private function performSearch(string $query, ?int $limit = null): array
    {
        $user = Auth::user();

        // Restricted folders/files are filtered out in PHP (not the query)
        // since accessibility depends on per-folder creator/allowed-user
        // rules that isAccessibleBy() already encapsulates - filtering
        // post-fetch keeps that single source of truth instead of
        // duplicating the rule as SQL.
        $folders = Folder::where('name', 'like', "%{$query}%")
            ->get()
            ->filter(fn (Folder $folder) => $folder->isAccessibleBy($user))
            ->values();

        $extensions = self::TYPE_ALIASES[mb_strtolower(trim($query))] ?? [mb_strtolower(trim($query))];

        $files = File::where('filename', 'like', "%{$query}%")
            ->orWhere('ocr_text', 'like', "%{$query}%")
            ->orWhere('school_year', 'like', "%{$query}%")
            ->orWhereHas('folder', fn ($folderQuery) => $folderQuery->where('name', 'like', "%{$query}%"))
            ->orWhere(function ($extQuery) use ($extensions) {
                foreach ($extensions as $ext) {
                    $extQuery->orWhere('filename', 'like', "%.{$ext}");
                }
            })
            ->with('folder')
            ->get()
            ->filter(fn (File $file) => $file->isAccessibleBy($user))
            ->values();

        // Archived folders no longer have live Folder/File rows - archiving
        // hard-deletes them (FolderArchiver::archive()) - so filenames are
        // matched against the file_checksums map snapshotted at archive time
        // instead of a files table. Restored archives are excluded since the
        // folder they came from is live again and already covered above.
        $archives = Archive::whereNull('restored_at')
            ->get()
            ->filter(fn (Archive $archive) => $archive->isAccessibleBy($user))
            ->filter(function (Archive $archive) use ($query) {
                if (stripos($archive->folder_name, $query) !== false) {
                    return true;
                }

                foreach (array_keys($archive->file_checksums ?? []) as $filename) {
                    if (stripos($filename, $query) !== false) {
                        return true;
                    }
                }

                return false;
            })
            ->values();

        // Structured document records - matched on the human-entered metadata
        // (the split PDF's OCR text is already covered by the File search
        // above). Same post-fetch access filtering via the folder rules.
        $documents = Document::where('is_archived', false)
            ->where(function ($documentQuery) use ($query) {
                $documentQuery->where('title', 'like', "%{$query}%")
                    ->orWhere('reference_no', 'like', "%{$query}%")
                    ->orWhere('sender', 'like', "%{$query}%")
                    ->orWhere('recipient', 'like', "%{$query}%")
                    ->orWhere('notes', 'like', "%{$query}%");
            })
            ->with('folder')
            ->get()
            ->filter(fn (Document $document) => $document->isAccessibleBy($user))
            ->values();

        if ($limit) {
            $folders = $folders->take($limit)->values();
            $files = $files->take($limit)->values();
            $archives = $archives->take($limit)->values();
            $documents = $documents->take($limit)->values();
        }

        // Flag which field a result matched on (beyond the obviously-visible
        // filename) so the views can explain why it showed up, same as the
        // existing "found in document text" badge.
        $files->each(function (File $file) use ($query, $extensions) {
            $matchedFilename = stripos($file->filename, $query) !== false;

            $file->matched_in_content = ! $matchedFilename
                && $file->ocr_text
                && stripos($file->ocr_text, $query) !== false;

            if ($file->matched_in_content) {
                $file->ocr_snippet = $this->buildSnippet($file->ocr_text, $query);
            }

            $file->matched_in_school_year = ! $matchedFilename
                && $file->school_year
                && stripos((string) $file->school_year, $query) !== false;

            $file->matched_in_folder = ! $matchedFilename
                && $file->folder
                && stripos($file->folder->name, $query) !== false;

            $file->matched_in_type = ! $matchedFilename
                && in_array(strtolower(pathinfo($file->filename, PATHINFO_EXTENSION)), $extensions, true);
        });

        // Flag archives that only matched on a filename inside them (not the
        // archive/folder name itself), and which filenames matched, so the
        // view can show "found: Budget_2025.xlsx" instead of just a bare hit.
        $archives->each(function (Archive $archive) use ($query) {
            $matchedFolderName = stripos($archive->folder_name, $query) !== false;

            $matchedFiles = array_values(array_filter(
                array_keys($archive->file_checksums ?? []),
                fn ($filename) => stripos($filename, $query) !== false
            ));

            $archive->matched_in_contents = ! $matchedFolderName && ! empty($matchedFiles);
            $archive->matched_filenames = $matchedFiles;
        });

        return [$folders, $files, $archives, $documents];
    }

    /**
     * Grabs a short window of text around the first match, for display
     * under a search result that matched on document content.
     */
    private function buildSnippet(string $text, string $query, int $radius = 60): string
    {
        $position = stripos($text, $query);

        if ($position === false) {
            return '';
        }

        $start  = max(0, $position - $radius);
        $length = $radius * 2 + strlen($query);

        $snippet = substr($text, $start, $length);
        $snippet = trim(preg_replace('/\s+/', ' ', $snippet));

        return ($start > 0 ? '…' : '') . $snippet . (($start + $length) < strlen($text) ? '…' : '');
    }
}