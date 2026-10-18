<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\DisplayOfficer;
use App\Models\Folder;
use App\Models\OfficerTerm;
use App\Models\SiteSetting;
use App\Services\ImageCompressor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class SiteSettingController extends Controller
{
    public function edit()
    {
        $settings = SiteSetting::current();
        $layout = Folder::isSuperAdmin(Auth::user()) ? 'SuperAdmin.homeSuperAdmin' : 'Admin.home';

        $officers = OfficerTerm::with(['user', 'position'])
            ->where('status', 'active')
            ->get();

        $displayOfficers = DisplayOfficer::orderBy('name')->get();

        return view('Admin.siteSettings', compact('settings', 'layout', 'officers', 'displayOfficers'));
    }

    public function storeDisplayOfficer(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'position_name' => 'required|string|max:255',
            'photo' => 'nullable|image|max:10240',
        ]);

        $officer = DisplayOfficer::create([
            'name' => $data['name'],
            'position_name' => $data['position_name'],
            'photo' => $request->hasFile('photo')
                ? app(ImageCompressor::class)->store($request->file('photo'), 'site/officers', ImageCompressor::PHOTO)
                : null,
        ]);

        ActivityLog::create([
            'user_name' => Auth::user()->name,
            'activity' => "Added display-only officer {$officer->name} ({$officer->position_name}) to site content",
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', 'Officer added to the student dashboard.');
    }

    public function destroyDisplayOfficer(Request $request, DisplayOfficer $displayOfficer)
    {
        if ($displayOfficer->photo) {
            Storage::disk('public')->delete($displayOfficer->photo);
        }

        $displayOfficer->delete();

        ActivityLog::create([
            'user_name' => Auth::user()->name,
            'activity' => "Removed display-only officer {$displayOfficer->name} from site content",
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', 'Officer removed from the student dashboard.');
    }

    /**
     * Maps each optional image upload's form field name to the model column
     * it's stored in.
     */
    private const IMAGE_SLOTS = [
        'logo' => 'logo_path',
        'hero_background' => 'hero_background_path',
        'vision_image' => 'vision_image_path',
        'mission_image' => 'mission_image_path',
        'hymn_image' => 'hymn_image_path',
    ];

    /** Longest edge each one is shrunk to on upload - see ImageCompressor. */
    public const IMAGE_SLOT_SIZES = [
        'logo_path' => ImageCompressor::LOGO,
        'hero_background_path' => ImageCompressor::HERO,
        'vision_image_path' => ImageCompressor::PAGE_IMAGE,
        'mission_image_path' => ImageCompressor::PAGE_IMAGE,
        'hymn_image_path' => ImageCompressor::PAGE_IMAGE,
    ];

    public function update(Request $request)
    {
        $data = $request->validate([
            'hymn' => 'nullable|string',
            'vision' => 'nullable|string',
            'mission' => 'nullable|string',
            // Up to 10 MB each: they are shrunk on upload (ImageCompressor).
            'logo' => 'nullable|image|max:10240',
            'hero_background' => 'nullable|image|max:10240',
            'vision_image' => 'nullable|image|max:10240',
            'mission_image' => 'nullable|image|max:10240',
            'hymn_image' => 'nullable|image|max:10240',
            'officer_photos' => 'nullable|array',
            'officer_photos.*' => 'nullable|image|max:10240',
            'remove_officer_photo' => 'nullable|array',
            'remove_officer_photo.*' => 'integer',
        ]);

        $settings = SiteSetting::current();

        // Officer photos for the public dashboard, keyed by officer_terms.id.
        // Uploading replaces the old one; "remove" falls back to the profile photo.
        foreach (OfficerTerm::where('status', 'active')->get() as $term) {
            $upload = $request->file("officer_photos.{$term->id}");
            $remove = in_array($term->id, $request->input('remove_officer_photo', []));

            if ($upload || $remove) {
                if ($term->site_photo) {
                    Storage::disk('public')->delete($term->site_photo);
                }

                $term->update(['site_photo' => $upload
                    ? app(ImageCompressor::class)->store($upload, 'site/officers', ImageCompressor::PHOTO)
                    : null]);
            }
        }

        foreach (self::IMAGE_SLOTS as $field => $column) {
            if ($request->hasFile($field)) {
                if ($settings->$column) {
                    Storage::disk('public')->delete($settings->$column);
                }

                $data[$column] = app(ImageCompressor::class)->store($request->file($field), 'site', self::IMAGE_SLOT_SIZES[$column]);
            }
        }

        $settings->update(collect($data)->except(array_keys(self::IMAGE_SLOTS))->all());

        ActivityLog::create([
            'user_name' => Auth::user()->name,
            'activity' => 'Updated site content (logo/hero background/hymn/vision/mission/officer photos)',
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', 'Site content updated.');
    }
}
