<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Services\ImageCompressor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function edit()
    {
        $user = Auth::user();

        $isOfficer = DB::table('officer_terms')->where('user_id', $user->id)->exists();

        return view($isOfficer ? 'Admin.profile' : 'SuperAdmin.profile', compact('user'));
    }

    public function update(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'name'             => 'required|string|max:255',
            'email'            => 'required|email|unique:users,email,'.$user->id,
            // Up to 10 MB: shrunk to avatar size on upload (ImageCompressor).
            'profile_photo'    => 'nullable|image|max:10240',
            'current_password' => 'nullable|required_with:password|current_password',
            'password'         => 'nullable|min:6|confirmed',
        ]);

        $user->name  = $request->name;
        $user->email = $request->email;

        $changedPhoto   = false;
        $changedPassword = false;

        if ($request->hasFile('profile_photo')) {
            if ($user->profile_photo) {
                Storage::disk('public')->delete($user->profile_photo);
            }

            $user->profile_photo = app(ImageCompressor::class)->store($request->file('profile_photo'), 'profile-photos', ImageCompressor::PHOTO);
            $changedPhoto = true;
        }

        if ($request->filled('password')) {
            $user->password             = Hash::make($request->password);
            $user->must_change_password = false;
            $changedPassword = true;
        }

        $user->save();

        ActivityLog::create([
            'user_name'  => $user->name,
            'activity'   => 'Updated profile (' . implode(', ', array_filter([
                'info',
                $changedPhoto ? 'photo' : null,
                $changedPassword ? 'password' : null,
            ])) . ')',
            'ip_address' => $request->ip()
        ]);

        return back()->with('success', 'Profile updated successfully.');
    }
}
