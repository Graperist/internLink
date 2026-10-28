<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Concerns\ManagesProfilePhoto;
use App\Http\Controllers\Controller;
use App\Models\Program;
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

        // The coordinator is also a program instructor and picks the program
        // they teach here (instructors get theirs from the coordinator).
        $programs = $instructor->isCoordinator()
            ? Program::where('campus_id', $instructor->campus_id)->orderBy('code')->get()
            : collect();

        return view('instructor.profile', compact('instructor', 'programs'));
    }

    public function updateAccount(Request $request): RedirectResponse
    {
        $user = auth()->user();

        $isCoordinator = $user->isCoordinator();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'program_id' => $isCoordinator
                ? ['nullable', Rule::exists('programs', 'id')->where('campus_id', $user->campus_id)]
                : ['prohibited'],
        ]);

        $user->update(['name' => $validated['name'], 'email' => $validated['email']]);

        if ($isCoordinator) {
            $user->instructor->update(['program_id' => $validated['program_id'] ?? null]);
        }

        return redirect()->route('instructor.profile')->with('status', 'Account details updated.');
    }
}
