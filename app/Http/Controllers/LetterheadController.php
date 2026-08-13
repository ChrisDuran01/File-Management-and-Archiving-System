<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Folder;
use App\Models\Letterhead;
use App\Support\DocumentFormatter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class LetterheadController extends Controller
{
    /**
     * A stand-in resolution used purely to show what this letterhead looks
     * like wrapped around a real document - so an officer can check it
     * before picking it while generating an actual one.
     */
    private const SAMPLE_RESOLUTION = <<<'TEXT'
        RESOLUTION NO. 00-00-00
        Series of 0000

        SAMPLE RESOLUTION FOR LETTERHEAD PREVIEW

        Date: 0000-00-00
        Authored by: Juan Dela Cruz

        WHEREAS, this is placeholder text standing in for a real resolution, shown only so you can see how this letterhead's border, logo, seal, and footer look around an actual document;

        RESOLVED, that this preview does not get saved anywhere.

        APPROVED this day at your institution.
        TEXT;

    public function index()
    {
        $letterheads = Letterhead::orderBy('name')->get();
        $layout = Folder::isSuperAdmin(Auth::user()) ? 'SuperAdmin.homeSuperAdmin' : 'Admin.home';

        return view('Admin.letterheads', compact('letterheads', 'layout'));
    }

    /**
     * Renders the sample resolution through the exact same PDF view used by
     * real document generation, so the preview can never drift from what
     * actually gets produced - streamed inline, nothing is saved.
     */
    public function preview(Letterhead $letterhead)
    {
        $pdf = Pdf::loadView('Admin.templates-pdf', [
            'content' => DocumentFormatter::format(self::SAMPLE_RESOLUTION),
            'title' => 'Letterhead preview',
            'letterhead' => $letterhead,
            'borderBox' => $letterhead->borderCoverBox(Letterhead::PAGE_WIDTH_PT, Letterhead::PAGE_HEIGHT_PT),
        ])->setPaper([0, 0, Letterhead::PAGE_WIDTH_PT, Letterhead::PAGE_HEIGHT_PT]);

        return $pdf->stream('letterhead-preview-'.$letterhead->id.'.pdf');
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        foreach (['border_image', 'logo', 'seal', 'footer_image'] as $slot) {
            if ($request->hasFile($slot)) {
                $data[$slot.'_path'] = $request->file($slot)->store('letterheads', 'public');
            }
        }

        $data['created_by'] = Auth::id();

        $letterhead = Letterhead::create($data);

        ActivityLog::create([
            'user_name' => Auth::user()->name,
            'activity' => 'Added letterhead: '.$letterhead->name,
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', 'Letterhead "'.$letterhead->name.'" saved.');
    }

    public function update(Request $request, Letterhead $letterhead)
    {
        $data = $this->validated($request);

        foreach (['border_image', 'logo', 'seal', 'footer_image'] as $slot) {
            $column = $slot.'_path';

            if ($request->hasFile($slot)) {
                if ($letterhead->$column) {
                    Storage::disk('public')->delete($letterhead->$column);
                }

                $data[$column] = $request->file($slot)->store('letterheads', 'public');
            }
        }

        $letterhead->update($data);

        ActivityLog::create([
            'user_name' => Auth::user()->name,
            'activity' => 'Updated letterhead: '.$letterhead->name,
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', 'Letterhead "'.$letterhead->name.'" updated.');
    }

    public function destroy(Letterhead $letterhead)
    {
        foreach (['border_image_path', 'logo_path', 'seal_path', 'footer_image_path'] as $column) {
            if ($letterhead->$column) {
                Storage::disk('public')->delete($letterhead->$column);
            }
        }

        $name = $letterhead->name;
        $letterhead->delete();

        ActivityLog::create([
            'user_name' => Auth::user()->name,
            'activity' => 'Deleted letterhead: '.$name,
            'ip_address' => request()->ip(),
        ]);

        return back()->with('success', 'Letterhead "'.$name.'" deleted.');
    }

    private function validated(Request $request): array
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'university_name' => 'nullable|string|max:255',
            'office_name' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'border_image' => 'nullable|image|max:5120',
            'logo' => 'nullable|image|max:5120',
            'seal' => 'nullable|image|max:5120',
            'footer_image' => 'nullable|image|max:5120',
        ]);

        return $request->only(['name', 'university_name', 'office_name', 'address']);
    }
}
