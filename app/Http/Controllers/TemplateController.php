<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\File;
use App\Models\Folder;
use App\Models\Letterhead;
use App\Models\Template;
use App\Support\DocumentFormatter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TemplateController extends Controller
{
    /**
     * The document maker: category picker + dynamic fill-in form, built
     * from whichever templates exist for each category.
     */
    public function index()
    {
        $templates = Template::orderBy('category')->orderBy('name')->get();

        // Non-archived folders only - archived folders are read-only, so
        // they're not valid targets for a newly generated document.
        $folders = Folder::where('is_archived', false)->orderBy('name')->get(['id', 'name']);

        $recent = File::whereNotNull('generated_from_template_id')
            ->with(['template', 'folder'])
            ->latest()
            ->take(10)
            ->get();

        $letterheads = Letterhead::orderBy('name')->get(['id', 'name']);

        $layout = Folder::isSuperAdmin(Auth::user()) ? 'SuperAdmin.homeSuperAdmin' : 'Admin.home';

        return view('Admin.templates', compact('templates', 'folders', 'recent', 'letterheads', 'layout'));
    }

    /**
     * Render the picked template with the submitted field values, save it
     * as a PDF, and file it into the system exactly like an upload.
     */
    public function generate(Request $request)
    {
        $request->validate([
            'template_id' => 'required|exists:templates,id',
            'fields' => 'array',
            'folder_id' => 'nullable|exists:folders,id',
            'letterhead_id' => 'nullable|exists:letterheads,id',
        ]);

        $template = Template::findOrFail($request->template_id);

        if ($request->folder_id && Folder::find($request->folder_id)?->is_archived) {
            return back()->with('error', 'That folder is stored under a past school year and is read-only.');
        }

        $fields = $request->input('fields', []);

        $rendered = $template->content;
        foreach ($fields as $key => $value) {
            $rendered = str_replace('{{'.$key.'}}', e($value), $rendered);
        }

        // Anything left over (a placeholder the officer left blank isn't in
        // $fields at all if the input was empty - str_replace above already
        // handles that) - strip any still-unfilled tokens so they don't leak
        // into the final document.
        $rendered = preg_replace('/\{\{\s*[a-zA-Z0-9_]+\s*\}\}/', '', $rendered);

        $title = $fields['title'] ?? $fields['subject'] ?? $template->name;

        // Letterhead only applies to Resolutions - a value submitted for any
        // other category is ignored rather than trusted from the request.
        $letterhead = null;
        if ($template->category === 'resolution' && $request->letterhead_id) {
            $letterhead = Letterhead::find($request->letterhead_id);
        }

        $pdf = Pdf::loadView('Admin.templates-pdf', [
            'template' => $template,
            'content' => DocumentFormatter::format($rendered),
            'title' => $title,
            'letterhead' => $letterhead,
            'borderBox' => $letterhead?->borderCoverBox(Letterhead::PAGE_WIDTH_PT, Letterhead::PAGE_HEIGHT_PT),
        ])->setPaper([0, 0, Letterhead::PAGE_WIDTH_PT, Letterhead::PAGE_HEIGHT_PT]);

        $pdfContent = $pdf->output();

        // =============================
        // SAVE LOCALLY, THEN UPLOAD TO THE CLOUD DISK - same pattern
        // FileController uses for regular uploads.
        // =============================
        $filename = Str::slug($title ?: $template->name).'-'.now()->format('Ymd-His').'.pdf';
        $folderSegment = $request->folder_id ?? 'root';
        $localPath = 'uploads/'.$folderSegment.'/'.$filename;

        Storage::disk('public')->put($localPath, $pdfContent);

        $remotePath = time().'_'.Str::slug($title).'_'.uniqid().'.pdf';

        try {
            Storage::disk('cloud')->put($remotePath, $pdfContent);
            $storageType = 'both';
        } catch (\Throwable) {
            $storageType = 'local';
            $remotePath = $localPath; // fall back to referencing the local copy
        }

        File::create([
            'filename' => $filename,
            'filepath' => $remotePath,
            'local_path' => $localPath,
            'storage_type' => $storageType,
            'size' => strlen($pdfContent),
            'folder_id' => $request->folder_id ?: null,
            'generated_from_template_id' => $template->id,
        ]);

        ActivityLog::create([
            'user_name' => Auth::user()->name,
            'activity' => 'Generated document from template "'.$template->name.'": '.$filename,
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('templates.index')->with('success', 'Document generated and saved as '.$filename);
    }
}
