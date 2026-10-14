<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Shared "upload/remove profile picture" behavior for every portal's Profile
 * controller (Student, Instructor, Coordinator, HTE representative) - every
 * account is a users row with the same avatar_path column, so the logic is
 * identical across all four; only the redirect target differs.
 */
trait ManagesProfilePhoto
{
    public function updatePhoto(Request $request): RedirectResponse
    {
        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $user = auth()->user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $path = $request->file('photo')->store('avatars', 'public');
        $user->update(['avatar_path' => $path]);

        return redirect()->route($this->profileRouteName())->with('status', 'Profile photo updated.');
    }

    public function removePhoto(): RedirectResponse
    {
        $user = auth()->user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->update(['avatar_path' => null]);
        }

        return redirect()->route($this->profileRouteName())->with('status', 'Profile photo removed.');
    }

    /**
     * The named route each portal's Profile page lives at, e.g. 'student.profile'.
     */
    abstract protected function profileRouteName(): string;
}
