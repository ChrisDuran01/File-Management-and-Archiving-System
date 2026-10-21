<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Announcement;
use App\Models\AnnouncementImage;
use App\Models\Folder;
use App\Models\User;
use App\Jobs\SendAnnouncementPush;
use App\Notifications\AnnouncementPosted;
use App\Services\HtmlSanitizer;
use App\Services\ImageCompressor;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
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

    /**
     * Photos shown inline as a swipeable gallery. Raster formats only (no
     * SVG - it can carry script) and served through image() with a fixed
     * content type rather than straight off the public disk. Up to 10 MB:
     * they are shrunk on upload (ImageCompressor), so a phone photo is fine.
     */
    private const IMAGE_RULES = 'image|mimes:jpg,jpeg,png,webp|max:10240';
    private const MAX_IMAGES = 10;

    public function index()
    {
        $announcements = Announcement::with(['author', 'images'])->latest()->get();
        $layout = Folder::isSuperAdmin(Auth::user()) ? 'SuperAdmin.homeSuperAdmin' : 'Admin.home';

        return view('Admin.announcements', compact('announcements', 'layout'));
    }

    public function store(Request $request, HtmlSanitizer $sanitizer)
    {
        $data = $this->validated($request, $sanitizer);

        if ($request->hasFile('attachment')) {
            $data = $this->storeAttachment($request->file('attachment')) + $data;
        }

        $data['created_by'] = Auth::id();

        $announcement = Announcement::create($data);
        $this->saveImages($request, $announcement);

        ActivityLog::create([
            'user_name' => Auth::user()->name,
            'activity' => 'Posted announcement: '.$announcement->title,
            'ip_address' => $request->ip(),
        ]);

        // In-app bell for every other officer (the author already knows).
        Notification::send(
            User::activeOfficers()->where('id', '!=', Auth::id()),
            new AnnouncementPosted($announcement->title, Auth::user()->name)
        );

        // Browser push to students who opted in on the public page (queued).
        SendAnnouncementPush::dispatch($announcement);

        return back()->with('success', 'Announcement "'.$announcement->title.'" posted.');
    }

    public function update(Request $request, Announcement $announcement, HtmlSanitizer $sanitizer)
    {
        $data = $this->validated($request, $sanitizer);

        if ($request->hasFile('attachment')) {
            if ($announcement->attachment_path) {
                Storage::disk('public')->delete($announcement->attachment_path);
            }

            $data = $this->storeAttachment($request->file('attachment')) + $data;
        }

        $announcement->update($data);

        $remove = $announcement->images()->whereIn('id', (array) $request->input('remove_images', []))->get();
        foreach ($remove as $img) {
            Storage::disk('public')->delete($img->path);
            $img->delete();
        }
        $this->saveImages($request, $announcement);

        ActivityLog::create([
            'user_name' => Auth::user()->name,
            'activity' => 'Updated announcement: '.$announcement->title,
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', 'Announcement "'.$announcement->title.'" updated.');
    }

    public function destroy(Announcement $announcement)
    {
        foreach ($announcement->images as $img) {
            Storage::disk('public')->delete($img->path);
        }

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

    /** Public (no auth) - serves a gallery photo inline with a fixed image type. */
    public function image(AnnouncementImage $image)
    {
        $path = Storage::disk('public')->path($image->path);
        abort_unless(is_file($path), 404);

        $type = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };

        return response()->file($path, [
            'Content-Type' => $type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    private function saveImages(Request $request, Announcement $announcement): void
    {
        $order = (int) $announcement->images()->max('sort_order');
        $compressor = app(ImageCompressor::class);

        foreach ($request->file('images', []) as $file) {
            AnnouncementImage::create([
                'announcement_id' => $announcement->id,
                'path' => $compressor->store($file, 'announcements/images', ImageCompressor::ANNOUNCEMENT_IMAGE),
                'sort_order' => ++$order,
            ]);
        }
    }

    /**
     * Stores an attachment - an image one shrunk like the gallery photos,
     * a PDF or Word file as it is. Its download name follows the stored
     * type, so a PNG saved as a JPEG downloads as .jpg.
     *
     * @return array{attachment_path: string, attachment_original_name: string}
     */
    private function storeAttachment(UploadedFile $file): array
    {
        $path = app(ImageCompressor::class)->store($file, 'announcements', ImageCompressor::ANNOUNCEMENT_ATTACHMENT);
        $name = $file->getClientOriginalName();

        $storedExtension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== $storedExtension
            && in_array($storedExtension, ['jpg', 'png'], true)) {
            $name = pathinfo($name, PATHINFO_FILENAME) . '.' . $storedExtension;
        }

        return ['attachment_path' => $path, 'attachment_original_name' => $name];
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
            'images' => 'nullable|array|max:'.self::MAX_IMAGES,
            'images.*' => self::IMAGE_RULES,
            'remove_images' => 'nullable|array',
            'hide_from_search' => 'nullable|boolean',
        ]);

        $data = $request->only(['title', 'body']);
        $data['body'] = $sanitizer->clean($data['body']);
        // An unticked checkbox sends nothing, which means "show in search".
        $data['hide_from_search'] = $request->boolean('hide_from_search');

        if (trim(strip_tags($data['body'])) === '') {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'body' => 'The message must contain some text.',
            ]);
        }

        return $data;
    }
}
