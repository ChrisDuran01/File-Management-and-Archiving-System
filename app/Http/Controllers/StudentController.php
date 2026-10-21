<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\File;
use App\Models\Announcement;
use App\Models\DisplayOfficer;
use App\Models\OfficerTerm;
use App\Models\SiteSetting;
use App\Services\FileResponse;
use App\Support\StudentPageCache;
use App\Support\StudentPageSeo;
use Illuminate\Support\Str;

class StudentController extends Controller
{
    /**
     * The three branches of the student government, in display order, with
     * a one-line plain-language description so students know which branch
     * handles what. "other" catches any position that fits none of them.
     */
    private const BRANCHES = [
        'executive' => ['name' => 'Executive', 'about' => 'Leads the student government and carries out its programs, projects and services.'],
        'legislative' => ['name' => 'Legislative', 'about' => 'Speaks for the student body and passes the resolutions and policies the government follows.'],
        'judicial' => ['name' => 'Judicial', 'about' => 'Interprets the student government constitution and settles disputes fairly.'],
        'other' => ['name' => 'Other officers', 'about' => 'Officers outside the three branches.'],
    ];

    /** Document "families" for the type filter, keyed by file extension. */
    private const DOC_FAMILIES = [
        'pdf' => ['label' => 'PDF', 'ext' => ['pdf']],
        'word' => ['label' => 'Word', 'ext' => ['doc', 'docx', 'odt', 'rtf']],
        'sheet' => ['label' => 'Spreadsheets', 'ext' => ['xls', 'xlsx', 'csv', 'ods']],
        'slides' => ['label' => 'Slides', 'ext' => ['ppt', 'pptx', 'odp']],
        'image' => ['label' => 'Images', 'ext' => ['jpg', 'jpeg', 'png', 'gif', 'webp']],
    ];

    public function dashboard()
    {
        // Every visitor sees the same files, announcements and officers, so
        // they're built once and shared (StudentPageCache). The site settings
        // are one small row, read fresh.
        $siteSettings = SiteSetting::current();
        $response = response()->view('Student.stuDashboard', StudentPageCache::remember(fn () => $this->pageData()) + [
            'siteSettings' => $siteSettings,
            'seo' => StudentPageSeo::home($siteSettings),
        ]);

        // A page showing this visitor's own contact-form result (a sent
        // message, or the details they typed after a failed send) must never
        // become the copy kept for offline use - public/sw.js skips no-store.
        if (session()->has('success') || session()->has('errors') || session()->hasOldInput()) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }

    /**
     * One announcement on its own page, so it can be shared as a link (with
     * its photo in the preview) and listed by search engines. The student
     * page shows the same post as a card and a pop-up.
     */
    public function announcement(Announcement $announcement, ?string $slug = null)
    {
        // A missing or outdated end (the title was edited since the link was
        // shared) goes to the one current address, so every link and search
        // result counts as the same page.
        if ($slug !== $announcement->slug()) {
            return redirect()->route('student.announcement', [$announcement, $announcement->slug()], 301);
        }

        $announcement->load('images');
        $siteSettings = SiteSetting::current();

        $response = response()->view('Student.announcement', [
            'announcement' => $announcement,
            'siteSettings' => $siteSettings,
            'seo' => StudentPageSeo::announcement($announcement, $siteSettings),
        ]);

        // Same as the page's robots meta tag, for crawlers that only read headers.
        if ($announcement->hide_from_search) {
            $response->headers->set('X-Robots-Tag', 'noindex');
        }

        return $response;
    }

    /**
     * Everything on the student page that's the same for every visitor.
     * Plain values with just the fields the page shows, since this is what
     * StudentPageCache keeps - no storage paths or OCR text.
     */
    private function pageData(): array
    {
        // Only show admin-approved files whose parent folder (if any) isn't restricted.
        // There is no "type" column - the type always comes from the file extension.
        $files = File::publiclyVisible()->latest()
            ->get(['id', 'filename', 'size', 'school_year', 'created_at'])
            ->map(function ($file) {
                $ext = strtolower(pathinfo($file->filename, PATHINFO_EXTENSION));

                return (object) [
                    'id' => $file->id,
                    'filename' => $file->filename,
                    'school_year' => $file->school_year,
                    'created_at' => $file->created_at,
                    'ext' => $ext,
                    'family' => $this->docFamily($ext),
                    'size_label' => $this->humanSize($file->size),
                ];
            });

        $docFamilies = collect(self::DOC_FAMILIES)
            ->map(fn ($meta, $key) => ['label' => $meta['label'], 'count' => $files->where('family', $key)->count()])
            ->put('other', ['label' => 'Other', 'count' => $files->where('family', 'other')->count()])
            ->filter(fn ($family) => $family['count'] > 0);

        $announcements = Announcement::with('images')->latest()->get()
            ->map(fn ($announcement) => (object) [
                'id' => $announcement->id,
                'slug' => $announcement->slug(),
                'title' => $announcement->title,
                'body' => $announcement->body,
                'attachment_path' => $announcement->attachment_path,
                'attachment_original_name' => $announcement->attachment_original_name,
                'created_at' => $announcement->created_at,
                'images' => $announcement->images->map(fn ($image) => (object) ['id' => $image->id, 'path' => $image->path]),
            ]);

        $officers = OfficerTerm::with(['user', 'position'])
            ->where('status', 'active')
            ->get()
            ->map(fn ($term) => $this->officer($term->user->name, $term->position->position_name ?? '', $term->display_photo))
            ->concat(DisplayOfficer::all()->map(fn ($o) => $this->officer($o->name, $o->position_name, $o->photo)));

        // Each branch lists its officers top-down: tier 0 is the head (President,
        // Chief Legislator, Chief Justice), tier 1 their deputy, tier 2 everyone else.
        $branches = collect(self::BRANCHES)
            ->map(fn ($meta, $key) => (object) [
                'key' => $key,
                'name' => $meta['name'],
                'about' => $meta['about'],
                'tiers' => $officers->where('branch', $key)->sortBy('rank')->groupBy('tier')->sortKeys(),
            ])
            ->filter(fn ($branch) => $branch->tiers->isNotEmpty())
            ->values();

        return compact('files', 'docFamilies', 'announcements', 'branches');
    }

    private function officer(string $name, string $position, ?string $photo): object
    {
        [$branch, $rank] = $this->classifyPosition($position);

        $words = preg_split('/\s+/', trim($name)) ?: [''];
        $initials = strtoupper(mb_substr($words[0], 0, 1).(count($words) > 1 ? mb_substr(end($words), 0, 1) : ''));

        return (object) [
            'name' => $name,
            'position' => $position,
            'photo' => $photo,
            'initials' => $initials,
            'branch' => $branch,
            'rank' => $rank,
            'tier' => min($rank, 2),
        ];
    }

    /**
     * "471 KB", "3.1 MB" - so students on mobile data know what they're
     * about to download. Done by hand because Laravel's Number::fileSize()
     * needs the intl extension, which XAMPP's PHP doesn't enable.
     */
    private function humanSize(?int $bytes): ?string
    {
        if (! $bytes) {
            return null;
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $power = min((int) floor(log($bytes, 1024)), count($units) - 1);
        $value = $bytes / (1024 ** $power);

        return ($power === 0 ? $value : number_format($value, $value < 10 ? 1 : 0)).' '.$units[$power];
    }

    private function docFamily(string $ext): string
    {
        foreach (self::DOC_FAMILIES as $key => $family) {
            if (in_array($ext, $family['ext'], true)) {
                return $key;
            }
        }

        return 'other';
    }

    /**
     * Places a position in its branch of the student government and ranks
     * it within that branch, so officers display in hierarchy order however
     * they were entered. Matching is by keyword, so labels like
     * "SSLG - Vice President" or "Chief Legislator - BSA Senator" still match.
     *
     * The branch is decided first: "Legislative Secretary" is a legislator,
     * not the executive Secretary, and "Senate President" is not the
     * President. Returns [branch, rank]; lower rank = higher in the branch.
     */
    private function classifyPosition(string $positionName): array
    {
        $name = strtolower($positionName);

        if (Str::contains($name, ['justice', 'judge', 'judicial', 'tribunal', 'court'])) {
            return ['judicial', Str::contains($name, 'chief') ? 0 : 1];
        }

        if (Str::contains($name, ['legislat', 'senator', 'senate', 'representative', 'councilor', 'speaker'])) {
            if (Str::contains($name, ['chief', 'speaker', 'senate president'])) {
                return ['legislative', 0];
            }

            return ['legislative', Str::contains($name, 'secretary') ? 1 : 2];
        }

        // "vice president" before "president", since the latter is a substring of it.
        $executive = [
            'vice president' => 1,
            'president' => 0,
            'secretary' => 2,
            'treasurer' => 3,
            'auditor' => 4,
            'public information officer' => 5,
            'pio' => 5,
            'business manager' => 6,
            'peace officer' => 7,
        ];

        foreach ($executive as $keyword => $rank) {
            if (str_contains($name, $keyword)) {
                return ['executive', $rank];
            }
        }

        return ['other', 99];
    }

    public function previewStudentDashboard($id)
    {
        $file = File::with('folder')->findOrFail($id);

        if (! $file->is_public || $file->is_pending_approval || ($file->folder && $file->folder->is_restricted)) {
            abort(403, 'This file is not available for public preview.');
        }

        try {
            $signedUrl = FileResponse::cloudLink($file->filepath);
        } catch (\Throwable) {
            return response()->json(['error' => 'Failed to generate preview URL'], 500);
        }

        return response()->json([
            'url'  => $signedUrl,
            'type' => strtolower(pathinfo($file->filename, PATHINFO_EXTENSION)),
            'name' => $file->filename,
        ]);
    }
}