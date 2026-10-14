<?php

namespace App\Http\Controllers\Student;

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
        return 'student.profile';
    }

    /**
     * My Profile. Unlike the HTE representative, a student doesn't manage
     * any separate company-style record - campus, program, instructor,
     * and id_number are all set by the coordinator, so the only thing a
     * student can edit here is their own account name/email.
     */
    public function index(): View
    {
        $student = auth()->user()->student;

        return view('student.profile', compact('student'));
    }

    public function updateAccount(Request $request): RedirectResponse
    {
        $user = auth()->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $user->update($validated);

        return redirect()->route('student.profile')->with('status', 'Account details updated.');
    }
}
