<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CampusSettingsController extends Controller
{
    /**
     * Institutional facts about this campus that printable forms need but
     * shouldn't have hardcoded - starting with just the Campus Executive
     * Director's name, since that's what actually broke first (printable
     * templates were hardcoding one specific person regardless of which
     * campus the student actually belongs to).
     */
    public function edit(): View
    {
        $campus = auth()->user()->campus;
        // Each program's Department Chair is set here too (printed on its forms).
        $programs = \App\Models\Program::where('campus_id', $campus->id)->orderBy('code')->get();

        return view('coordinator.campus-settings.edit', compact('campus', 'programs'));
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ced_name' => ['nullable', 'string', 'max:255'],
            'college_dean_name' => ['nullable', 'string', 'max:255'],
            'registrar_name' => ['nullable', 'string', 'max:255'],
            'department_chairs' => ['nullable', 'array'],
            'department_chairs.*' => ['nullable', 'string', 'max:255'],
        ]);

        $campus = auth()->user()->campus;
        $campus->update(\Illuminate\Support\Arr::except($validated, ['department_chairs']));
        foreach ($validated['department_chairs'] ?? [] as $programId => $name) {
            \App\Models\Program::where('campus_id', $campus->id)->whereKey((int) $programId)
                ->update(['department_chair_name' => filled($name) ? trim($name) : null]);
        }

        return redirect()
            ->route('coordinator.campus-settings.edit')
            ->with('status', 'Campus settings updated.');
    }
}
