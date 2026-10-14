<?php

namespace App\Http\Controllers\Instructor;

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
        return 'instructor.profile';
    }

    /**
     * My Profile. Like the Student portal, an instructor doesn't manage any
     * separate company-style record - campus, program, and id_number are
     * all set by the coordinator, so the only thing an instructor can edit
     * here is their own account name/email.
     */
    public function index(): View
    {
        $instructor = auth()->user()->instructor;

        return view('instructor.profile', compact('instructor'));
    }

    public function updateAccount(Request $request): RedirectResponse
    {
        $user = auth()->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $user->update($validated);

        return redirect()->route('instructor.profile')->with('status', 'Account details updated.');
    }
}
