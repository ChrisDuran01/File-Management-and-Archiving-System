<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Folder;
use App\Models\Letterhead;
use App\Services\PdfPageImage;
use App\Support\Documents\ResolutionDocument;
use App\Support\SchoolYear;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LetterheadController extends Controller
{
    private const IMAGE_SLOTS = ['border_image', 'logo', 'seal', 'footer_image'];

    public function index()
    {
        $letterheads = Letterhead::orderByDesc('is_default')->orderBy('name')->get();
        $layout = Folder::isSuperAdmin(Auth::user()) ? 'SuperAdmin.homeSuperAdmin' : 'Admin.home';

        $currentYear = SchoolYear::current();
        $yearOptions = collect(SchoolYear::options())->prepend(SchoolYear::next($currentYear))->unique()->values();
        $default = $letterheads->firstWhere('is_default', true);

        return view('Admin.letterheads', compact('letterheads', 'layout', 'currentYear', 'yearOptions', 'default'));
    }

    /**
     * Renders a stand-in resolution (ResolutionDocument::sample()) through
     * the exact same views used by real document generation, so the
     * preview can never drift from what actually gets produced - streamed
     * inline, nothing is saved.
     */
    public function preview(Letterhead $letterhead)
    {
        $pdf = Pdf::loadView('Admin.templates-pdf', [
            'content' => view(ResolutionDocument::view(), ['fields' => ResolutionDocument::sample()])->render(),
            'letterhead' => $letterhead,
            'borderBox' => $letterhead->borderCoverBox(Letterhead::PAGE_WIDTH_PT, Letterhead::PAGE_HEIGHT_PT),
        ])->setPaper([0, 0, Letterhead::PAGE_WIDTH_PT, Letterhead::PAGE_HEIGHT_PT]);

        return $pdf->stream('letterhead-preview-'.$letterhead->id.'.pdf');
    }

    public function store(Request $request, PdfPageImage $pdfImage)
    {
        $data = $this->validated($request);
        $data += $this->storeImages($request, $pdfImage);
        $data['created_by'] = Auth::id();

        $letterhead = Letterhead::create($data);

        if ($request->boolean('make_default')) {
            $letterhead->makeDefault();
        }

        $this->log($request, 'Added letterhead: '.$letterhead->name);

        return back()->with('success', 'Letterhead "'.$letterhead->name.'" saved.'.$this->warningNote($letterhead));
    }

    public function update(Request $request, Letterhead $letterhead, PdfPageImage $pdfImage)
    {
        $data = $this->validated($request);
        $data += $this->storeImages($request, $pdfImage, $letterhead);

        $letterhead->update($data);

        $this->log($request, 'Updated letterhead: '.$letterhead->name);

        return back()->with('success', 'Letterhead "'.$letterhead->name.'" updated.'.$this->warningNote($letterhead));
    }

    /** New documents and proposal PDFs start with this letterhead. */
    public function makeDefault(Request $request, Letterhead $letterhead)
    {
        $letterhead->makeDefault();

        $this->log($request, 'Set the default letterhead: '.$letterhead->name);

        return back()->with('success', '"'.$letterhead->name.'" is now the letterhead new documents start with.');
    }

    /**
     * A copy with its own images, for the next school year: the SG changes
     * a detail or two each year (the e-mail in the footer, the logo), so
     * last year's is the quickest start.
     */
    public function copy(Request $request, Letterhead $letterhead)
    {
        $base = SchoolYear::isValid($letterhead->school_year) ? $letterhead->school_year
            : (SchoolYear::isValid($letterhead->name) ? $letterhead->name : null);
        $year = $base ? SchoolYear::next($base) : SchoolYear::current();

        $copy = $letterhead->replicate(['is_default']);
        $copy->name = SchoolYear::isValid($letterhead->name) ? $year : Str::limit($letterhead->name.' (copy)', 255, '');
        $copy->school_year = $year;
        $copy->created_by = Auth::id();

        foreach (self::IMAGE_SLOTS as $slot) {
            $column = $slot.'_path';
            if ($letterhead->$column && Storage::disk('public')->exists($letterhead->$column)) {
                $path = 'letterheads/'.Str::random(40).'.'.pathinfo($letterhead->$column, PATHINFO_EXTENSION);
                Storage::disk('public')->copy($letterhead->$column, $path);
                $copy->$column = $path;
            } else {
                $copy->$column = null;
            }
        }

        $copy->save();

        $this->log($request, 'Copied letterhead "'.$letterhead->name.'" as "'.$copy->name.'"');

        return redirect()->route('letterheads.index')
            ->with('success', 'Copied as "'.$copy->name.'". Upload the new design under Edit, then set it as the default.')
            ->with('open_letterhead', $copy->id);
    }

    public function destroy(Letterhead $letterhead)
    {
        foreach (self::IMAGE_SLOTS as $slot) {
            $column = $slot.'_path';
            if ($letterhead->$column) {
                Storage::disk('public')->delete($letterhead->$column);
            }
        }

        $name = $letterhead->name;
        $letterhead->delete();

        $this->log(request(), 'Deleted letterhead: '.$name);

        return back()->with('success', 'Letterhead "'.$name.'" deleted.');
    }

    private function validated(Request $request): array
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'school_year' => ['nullable', 'regex:/^\d{4}-\d{4}$/'],
            'university_name' => 'nullable|string|max:255',
            'office_name' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            // The whole page may be a PDF: Word's "Save as PDF" of the letterhead.
            'border_image' => 'nullable|file|mimes:jpg,jpeg,png,gif,webp,pdf|max:10240',
            'logo' => 'nullable|image|max:5120',
            'seal' => 'nullable|image|max:5120',
            'footer_image' => 'nullable|image|max:5120',
        ]);

        return $request->only(['name', 'school_year', 'university_name', 'office_name', 'address']);
    }

    /**
     * Saves the uploaded images, replacing $letterhead's old ones. A PDF
     * for the whole page is drawn as a 300-dpi JPEG first; one that can't
     * be read is refused before anything is saved.
     *
     * @return array<string, string> column => stored path
     */
    private function storeImages(Request $request, PdfPageImage $pdfImage, ?Letterhead $letterhead = null): array
    {
        $pageImage = null;
        $border = $request->file('border_image');
        if ($border && strtolower($border->getClientOriginalExtension()) === 'pdf') {
            $pageImage = $pdfImage->firstPage($border->getRealPath());
            if (! $pageImage) {
                throw ValidationException::withMessages([
                    'border_image' => 'That PDF could not be read. Save it again from Word (File, Save as, PDF), or upload an image.',
                ]);
            }
        }

        $paths = [];
        foreach (self::IMAGE_SLOTS as $slot) {
            if (! $request->hasFile($slot)) {
                continue;
            }

            $column = $slot.'_path';
            if ($letterhead?->$column) {
                Storage::disk('public')->delete($letterhead->$column);
            }

            if ($slot === 'border_image' && $pageImage) {
                $paths[$column] = 'letterheads/'.Str::random(40).'.jpg';
                Storage::disk('public')->put($paths[$column], file_get_contents($pageImage));
                @unlink($pageImage);
            } else {
                $paths[$column] = $request->file($slot)->store('letterheads', 'public');
            }
        }

        return $paths;
    }

    /** Tells the officer at once when the page image will print badly. */
    private function warningNote(Letterhead $letterhead): string
    {
        return $letterhead->printWarnings() ? ' Check the note on its card before using it.' : '';
    }

    private function log(Request $request, string $activity): void
    {
        ActivityLog::create([
            'user_name' => Auth::user()->name,
            'activity' => $activity,
            'ip_address' => $request->ip(),
        ]);
    }
}
