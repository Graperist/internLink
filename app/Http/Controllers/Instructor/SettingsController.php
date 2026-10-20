<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The Subject Instructor's settings for their program - for now the Form 19
 * weekly report deadline (days after each week ends, 11:59 PM).
 */
class SettingsController extends Controller
{
    public function edit(): View
    {
        $instructor = $this->instructor();

        return view('instructor.settings', ['instructor' => $instructor, 'program' => $instructor->program]);
    }

    public function update(Request $request): RedirectResponse
    {
        $instructor = $this->instructor();
        abort_unless($instructor->program, 422, 'Your account has no program yet - ask your coordinator.');

        $validated = $request->validate([
            'weekly_report_due_days' => ['required', 'integer', 'min:0', 'max:14'],
        ], [
            'weekly_report_due_days.*' => 'Enter a number of days from 0 to 14.',
        ]);

        $instructor->program->forceFill(['weekly_report_due_days' => (int) $validated['weekly_report_due_days']])->save();

        return redirect()->route('instructor.settings')->with('status', 'Form 19 deadline saved: '.$this->describe((int) $validated['weekly_report_due_days']).'.');
    }

    private function describe(int $days): string
    {
        return $days === 0 ? 'due on the last day of each week' : "due {$days} ".\Illuminate\Support\Str::plural('day', $days).' after each week ends';
    }

    private function instructor()
    {
        $instructor = auth()->user()->instructor()->with('program')->first();
        abort_unless($instructor, 403);
        // The coordinator shares this portal but does not set the deadline.
        abort_if(auth()->user()->isCoordinator(), 403, 'The Subject Instructor sets the weekly report deadline.');

        return $instructor;
    }
}
