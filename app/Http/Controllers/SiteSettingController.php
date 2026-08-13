<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Folder;
use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class SiteSettingController extends Controller
{
    public function edit()
    {
        $settings = SiteSetting::current();
        $layout = Folder::isSuperAdmin(Auth::user()) ? 'SuperAdmin.homeSuperAdmin' : 'Admin.home';

        return view('Admin.siteSettings', compact('settings', 'layout'));
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

    public function update(Request $request)
    {
        $data = $request->validate([
            'hymn' => 'nullable|string',
            'vision' => 'nullable|string',
            'mission' => 'nullable|string',
            'logo' => 'nullable|image|max:5120',
            'hero_background' => 'nullable|image|max:8192',
            'vision_image' => 'nullable|image|max:8192',
            'mission_image' => 'nullable|image|max:8192',
            'hymn_image' => 'nullable|image|max:8192',
        ]);

        $settings = SiteSetting::current();

        foreach (self::IMAGE_SLOTS as $field => $column) {
            if ($request->hasFile($field)) {
                if ($settings->$column) {
                    Storage::disk('public')->delete($settings->$column);
                }

                $data[$column] = $request->file($field)->store('site', 'public');
            }
        }

        $settings->update(collect($data)->except(array_keys(self::IMAGE_SLOTS))->all());

        ActivityLog::create([
            'user_name' => Auth::user()->name,
            'activity' => 'Updated site content (logo/hero background/hymn/vision/mission)',
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', 'Site content updated.');
    }
}
