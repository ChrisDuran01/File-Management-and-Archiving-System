<?php

namespace App\Http\Controllers;

use App\Jobs\ExtractFileOcrText;
use App\Models\ActivityLog;
use App\Models\File;
use App\Models\Folder;
use App\Models\Letterhead;
use App\Models\OfficerTerm;
use App\Models\Template;
use App\Services\CloudFileUploader;
use App\Services\FileVersions;
use App\Support\DocumentFormatter;
use App\Support\Documents\ResolutionDocument;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TemplateController extends Controller
{
    /**
     * The document maker: one form per template, the live preview, and the
     * documents made lately. ?edit={file} opens a saved document to fix it
     * (saving replaces its PDF); ?copy={file} starts a new one from it.
     */
    public function index(Request $request)
    {
        // Resolution first, then the request letter, then any plain template.
        $order = array_flip(array_keys(Template::DOCUMENTS));
        $templates = Template::visible()->orderBy('name')->get()
            ->sortBy(fn ($template) => $order[$template->category] ?? count($order))
            ->values();

        $editing = $request->filled('edit') ? $this->editableFile((int) $request->query('edit')) : null;
        $copying = ! $editing && $request->filled('copy') ? $this->copyableFile((int) $request->query('copy')) : null;
        $source = $editing ?? $copying;

        $sourceTemplateId = $source ? (int) $source->generated_from_template_id : null;
        // After a refused save, the form comes back with what was typed.
        $oldTemplateId = $request->old('template_id') ? (int) $request->old('template_id') : null;

        $forms = [];
        foreach ($templates as $template) {
            $forms[$template->id] = $this->formFor($template, $sourceTemplateId === $template->id ? $source : null, (bool) $editing);

            if ($oldTemplateId === $template->id && is_array($request->old('fields'))) {
                $doc = $template->documentClass();
                $forms[$template->id]['fields'] = $doc ? $doc::normalize($request->old('fields')) : $request->old('fields');
                $forms[$template->id]['letterhead_id'] = $request->old('letterhead_id');
                $forms[$template->id]['folder_id'] = $request->old('folder_id');
                $forms[$template->id]['number_typed'] = true;
            }
        }

        $activeId = $oldTemplateId && isset($forms[$oldTemplateId])
            ? $oldTemplateId
            : ($sourceTemplateId ?? $templates->first()?->id);
        $activeTemplate = $templates->firstWhere('id', $activeId);
        $initialPreview = $activeTemplate ? $this->build($activeTemplate, $forms[$activeId]['fields'], true)[0] : '';

        // Non-archived folders only - archived folders are read-only, so
        // they're not valid targets for a newly generated document.
        $folders = Folder::where('is_archived', false)->orderBy('name')->get(['id', 'name']);

        $recent = File::whereNotNull('generated_from_template_id')
            ->with(['template', 'folder'])
            ->latest()
            ->take(10)
            ->get();

        $letterheads = Letterhead::orderBy('name')->get(['id', 'name', 'is_default']);

        $layout = Folder::isSuperAdmin(Auth::user()) ? 'SuperAdmin.homeSuperAdmin' : 'Admin.home';

        return view('Admin.templates', compact(
            'templates', 'forms', 'activeId', 'initialPreview', 'editing', 'copying', 'folders', 'recent', 'letterheads', 'layout'
        ));
    }

    /**
     * Save the filled-in template as a PDF and file it into the system
     * exactly like an upload - or, with replace_file_id, put the new PDF
     * in place of a document made earlier.
     */
    public function generate(Request $request, CloudFileUploader $uploader, FileVersions $versions)
    {
        $request->validate([
            'template_id' => 'required|integer',
            'fields' => 'array',
            'folder_id' => 'nullable|exists:folders,id',
            'letterhead_id' => 'nullable|exists:letterheads,id',
            'replace_file_id' => 'nullable|integer',
        ]);

        $template = Template::visible()->findOrFail($request->template_id);

        if ($doc = $template->documentClass()) {
            $request->validate($doc::rules());
        }

        $replacing = $request->filled('replace_file_id') ? $this->editableFile((int) $request->replace_file_id) : null;
        if ($replacing && (int) $replacing->generated_from_template_id !== $template->id) {
            return back()->with('error', 'That document was made from another template.');
        }

        if ($request->folder_id && Folder::find($request->folder_id)?->is_archived) {
            return back()->with('error', 'That folder is stored under a past school year and is read-only.');
        }

        [$body, $fields, $title, $basename] = $this->build($template, $request->input('fields', []));
        $letterhead = $request->letterhead_id ? Letterhead::find($request->letterhead_id) : null;
        $fields['letterhead_id'] = $letterhead?->id;

        $pdfContent = $this->pdf($body, $letterhead)->output();

        $folderId = $request->folder_id ? (int) $request->folder_id : null;
        $filename = $doc ? $this->uniqueFilename($basename, $folderId, $replacing?->id) : $basename.'.pdf';
        [$localPath, $remotePath, $storageType] = $this->storePdf($uploader, $pdfContent, $title, $folderId);

        $saved = [
            'filename' => $filename,
            'filepath' => $remotePath,
            'local_path' => $localPath,
            'storage_type' => $storageType,
            'size' => strlen($pdfContent),
            'content_hash' => hash('sha256', $pdfContent),
            'folder_id' => $folderId,
            'template_fields' => $fields,
        ];

        if ($replacing) {
            // The PDF before this edit is kept as an older version
            // (FileVersions), so a document returned for fixes still shows
            // what was sent. The text and the copy check are redone for the
            // new PDF.
            DB::transaction(function () use ($versions, $replacing, $saved) {
                $versions->keepCurrent($replacing);

                $replacing->forceFill($saved + FileVersions::FRESH_TEXT + [
                    'version_no' => $replacing->version_no + 1,
                    'version_note' => 'Edited with the form',
                    'version_by' => Auth::id(),
                    'version_at' => now(),
                ])->save();
            });

            $file = $replacing;
            $activity = 'Edited document "'.$filename.'" (template "'.$template->name.'")';
        } else {
            $term = OfficerTerm::activeFor(Auth::id());

            $file = File::create($saved + [
                'generated_from_template_id' => $template->id,
                'officer_term_id' => $term?->id,
                'school_year' => $term?->school_year,
            ]);
            $activity = 'Generated document from template "'.$template->name.'": '.$filename;
        }

        // Makes the document's text searchable, like an uploaded PDF's.
        ExtractFileOcrText::dispatch($file->id);

        ActivityLog::create([
            'user_name' => Auth::user()->name,
            'activity' => $activity,
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('templates.index')
            ->with('success', ($replacing ? 'Changes saved to ' : 'Document saved as ').$filename);
    }

    /** The document drawn from what is typed so far, for the live preview. */
    public function preview(Request $request)
    {
        $request->validate(['template_id' => 'required|integer', 'fields' => 'nullable|array']);

        $template = Template::visible()->findOrFail($request->template_id);
        [$body] = $this->build($template, $request->input('fields', []), true);

        return response()->json(['html' => $body]);
    }

    /** The PDF as it would be saved, opened in a new tab - nothing is saved. */
    public function previewPdf(Request $request)
    {
        $request->validate([
            'template_id' => 'required|integer',
            'fields' => 'nullable|array',
            'letterhead_id' => 'nullable|exists:letterheads,id',
        ]);

        $template = Template::visible()->findOrFail($request->template_id);
        [$body, , , $basename] = $this->build($template, $request->input('fields', []));
        $letterhead = $request->letterhead_id ? Letterhead::find($request->letterhead_id) : null;

        return $this->pdf($body, $letterhead)->stream($basename.'.pdf');
    }

    /**
     * What one template's form starts with: a document opened to edit or
     * copy, else a new one carrying over the last one's signatories.
     */
    private function formFor(Template $template, ?File $source, bool $editing): array
    {
        $doc = $template->documentClass();
        $last = $this->lastFields($template);
        $saved = $source?->template_fields ?? [];

        if ($doc) {
            $fields = $source ? ($editing ? $doc::normalize($saved) : $doc::copyOf($saved)) : $doc::defaults($last);
        } else {
            $fields = $saved;
        }

        // A document opened again keeps its own letterhead; a new one starts
        // with the default, else the last one used with this template.
        $letterheadId = $source
            ? ($saved['letterhead_id'] ?? null)
            : (Letterhead::defaultOne()?->id ?? $last['letterhead_id'] ?? $template->letterhead_id);
        $form = [
            'fields' => $fields,
            'letterhead_id' => Letterhead::whereKey($letterheadId)->exists() ? $letterheadId : null,
            'folder_id' => $source?->folder_id,
        ];

        if ($doc === ResolutionDocument::class) {
            $numbers = File::where('generated_from_template_id', $template->id)
                ->whereNotNull('template_fields')
                ->when($editing && $source, fn ($q) => $q->whereKeyNot($source->id))
                ->latest('id')
                ->get(['id', 'template_fields'])
                ->map(fn ($file) => $file->template_fields['number'] ?? null)
                ->filter();

            $form['sequences'] = ResolutionDocument::sequences($numbers);
            $form['last_number'] = $numbers->first();
            $form['number_typed'] = $fields['number'] !== '';
            if ($fields['number'] === '') {
                $form['fields']['number'] = ResolutionDocument::suggestNumber($fields['adopted_on'], $form['sequences']);
            }
        }

        return $form;
    }

    /** What was typed into the last document made from this template. */
    private function lastFields(Template $template): ?array
    {
        $fields = File::where('generated_from_template_id', $template->id)
            ->whereNotNull('template_fields')
            ->latest('id')
            ->value('template_fields');

        $fields = is_string($fields) ? json_decode($fields, true) : $fields;

        return is_array($fields) ? $fields : null;
    }

    /**
     * The document's HTML, the fields to keep, its title, and its file
     * name without ".pdf". A preview keeps unfilled {{placeholders}} of a
     * plain template showing, so the officer sees what is still missing.
     *
     * @return array{0: string, 1: array, 2: string, 3: string}
     */
    private function build(Template $template, array $input, bool $preview = false): array
    {
        if ($doc = $template->documentClass()) {
            $fields = $doc::normalize($input);
            $body = view($doc::view(), ['fields' => $fields])->render();

            return [$body, $fields, $doc::title($fields) ?: $template->name, $doc::filename($fields)];
        }

        $fields = collect($input)
            ->filter(fn ($value, $key) => is_string($key) && (is_string($value) || $value === null))
            ->map(fn ($value) => (string) $value)
            ->all();

        $rendered = $template->content;
        foreach ($fields as $key => $value) {
            if ($value !== '') {
                $rendered = str_replace('{{'.$key.'}}', e($value), $rendered);
            }
        }

        // Anything left over (a placeholder the officer left blank) is
        // stripped so it doesn't leak into the final document.
        if (! $preview) {
            $rendered = preg_replace('/\{\{\s*[a-zA-Z0-9_]+\s*\}\}/', '', $rendered);
        }

        $title = ($fields['title'] ?? '') ?: (($fields['subject'] ?? '') ?: $template->name);

        return [DocumentFormatter::format($rendered), $fields, $title, Str::slug($title).'-'.now()->format('Ymd-His')];
    }

    private function pdf(string $body, ?Letterhead $letterhead)
    {
        return Pdf::loadView('Admin.templates-pdf', [
            'content' => $body,
            'letterhead' => $letterhead,
            'borderBox' => $letterhead?->borderCoverBox(Letterhead::PAGE_WIDTH_PT, Letterhead::PAGE_HEIGHT_PT),
        ])->setPaper([0, 0, Letterhead::PAGE_WIDTH_PT, Letterhead::PAGE_HEIGHT_PT]);
    }

    /**
     * Saved locally first, then to the cloud disk - same pattern as
     * FileController::store(). A failed cloud upload keeps the local copy
     * and marks it 'local'; files:sync-to-cloud finishes the job later.
     *
     * @return array{0: string, 1: string, 2: string} local path, cloud path, storage type
     */
    private function storePdf(CloudFileUploader $uploader, string $pdfContent, string $title, ?int $folderId): array
    {
        $localPath = 'uploads/'.($folderId ?? 'root').'/'.Str::random(40).'.pdf';
        Storage::disk('local')->put($localPath, $pdfContent);

        $remotePath = time().'_'.Str::slug($title).'_'.uniqid().'.pdf';
        $uploaded = $uploader->upload($remotePath, Storage::disk('local')->path($localPath));

        return [$localPath, $remotePath, $uploaded ? 'both' : 'local'];
    }

    /** "$basename.pdf", or "$basename (2).pdf" when the folder already has one. */
    private function uniqueFilename(string $basename, ?int $folderId, ?int $exceptId): string
    {
        $taken = File::where(fn ($q) => $folderId ? $q->where('folder_id', $folderId) : $q->whereNull('folder_id'))
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            ->pluck('filename')
            ->flip();

        $name = $basename.'.pdf';
        for ($n = 2; isset($taken[$name]); $n++) {
            $name = $basename.' ('.$n.').pdf';
        }

        return $name;
    }

    /** A document made from a template that this officer may change. */
    private function editableFile(int $id): File
    {
        $file = $this->copyableFile($id);

        abort_unless($file->isManageableBy(Auth::user()), 403);
        abort_if($file->is_archived || $file->folder?->is_archived, 403, 'This document is stored under a past school year and is read-only.');

        return $file;
    }

    /** A document made from a (shown) template that this officer may open. */
    private function copyableFile(int $id): File
    {
        $file = File::whereNotNull('template_fields')->with(['template', 'folder'])->findOrFail($id);

        abort_unless($file->template && ! in_array($file->template->category, Template::HIDDEN_CATEGORIES, true), 404);
        abort_unless($file->isAccessibleBy(Auth::user()), 403);

        return $file;
    }
}
