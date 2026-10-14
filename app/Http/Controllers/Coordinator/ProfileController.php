<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Concerns\ManagesProfilePhoto;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileController extends Controller
{
    use ManagesProfilePhoto;

    protected function profileRouteName(): string
    {
        return 'coordinator.profile';
    }

    /**
     * My Profile. Campus and ID number are set at account creation and
     * aren't editable here - a coordinator can only update their own
     * name/email, same as the Student/Instructor profile pages.
     */
    public function index(): View
    {
        $coordinator = auth()->user();

        return view('coordinator.profile', compact('coordinator'));
    }

    public function updateAccount(Request $request): RedirectResponse
    {
        $user = auth()->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $user->update($validated);

        return redirect()->route('coordinator.profile')->with('status', 'Account details updated.');
    }
}
