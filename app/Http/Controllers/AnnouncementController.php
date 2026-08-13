<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Announcement;
use App\Models\Folder;
use App\Services\HtmlSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class AnnouncementController extends Controller
{
    /**
     * Only pdf/image/doc attachments are accepted, and downloads are always
     * forced (see download()) rather than linked to the public disk
     * directly - so nothing uploaded here can end up rendered inline by a
     * student's browser.
     */
    private const ATTACHMENT_RULES = 'nullable|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240';

    public function index()
    {
        $announcements = Announcement::with('author')->latest()->get();
        $layout = Folder::isSuperAdmin(Auth::user()) ? 'SuperAdmin.homeSuperAdmin' : 'Admin.home';

        return view('Admin.announcements', compact('announcements', 'layout'));
    }

    public function store(Request $request, HtmlSanitizer $sanitizer)
    {
        $data = $this->validated($request, $sanitizer);

        if ($request->hasFile('attachment')) {
            $data['attachment_path'] = $request->file('attachment')->store('announcements', 'public');
            $data['attachment_original_name'] = $request->file('attachment')->getClientOriginalName();
        }

        $data['created_by'] = Auth::id();

        $announcement = Announcement::create($data);

        ActivityLog::create([
            'user_name' => Auth::user()->name,
            'activity' => 'Posted announcement: '.$announcement->title,
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', 'Announcement "'.$announcement->title.'" posted.');
    }

    public function update(Request $request, Announcement $announcement, HtmlSanitizer $sanitizer)
    {
        $data = $this->validated($request, $sanitizer);

        if ($request->hasFile('attachment')) {
            if ($announcement->attachment_path) {
                Storage::disk('public')->delete($announcement->attachment_path);
            }

            $data['attachment_path'] = $request->file('attachment')->store('announcements', 'public');
            $data['attachment_original_name'] = $request->file('attachment')->getClientOriginalName();
        }

        $announcement->update($data);

        ActivityLog::create([
            'user_name' => Auth::user()->name,
            'activity' => 'Updated announcement: '.$announcement->title,
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', 'Announcement "'.$announcement->title.'" updated.');
    }

    public function destroy(Announcement $announcement)
    {
        if ($announcement->attachment_path) {
            Storage::disk('public')->delete($announcement->attachment_path);
        }

        $title = $announcement->title;
        $announcement->delete();

        ActivityLog::create([
            'user_name' => Auth::user()->name,
            'activity' => 'Deleted announcement: '.$title,
            'ip_address' => request()->ip(),
        ]);

        return back()->with('success', 'Announcement "'.$title.'" deleted.');
    }

    /**
     * Public (no auth) - students reach this straight from the dashboard.
     * Always forces a download rather than serving the file inline.
     */
    public function download(Announcement $announcement)
    {
        abort_if(! $announcement->attachment_path, 404);

        return Storage::disk('public')->download(
            $announcement->attachment_path,
            $announcement->attachment_original_name ?? basename($announcement->attachment_path)
        );
    }

    /**
     * The body comes back from a rich text editor as HTML, and that HTML is
     * rendered unescaped on the public student dashboard - sanitize() is
     * the actual security boundary here (the editor's toolbar only
     * encourages safe markup; a request can always be crafted by hand to
     * submit anything, so this must run server-side regardless of what the
     * client sent).
     */
    private function validated(Request $request, HtmlSanitizer $sanitizer): array
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'attachment' => self::ATTACHMENT_RULES,
        ]);

        $data = $request->only(['title', 'body']);
        $data['body'] = $sanitizer->clean($data['body']);

        if (trim(strip_tags($data['body'])) === '') {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'body' => 'The message must contain some text.',
            ]);
        }

        return $data;
    }
}
