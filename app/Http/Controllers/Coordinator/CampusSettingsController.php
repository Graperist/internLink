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

        return view('coordinator.campus-settings.edit', compact('campus'));
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ced_name' => ['nullable', 'string', 'max:255'],
            'college_dean_name' => ['nullable', 'string', 'max:255'],
            'registrar_name' => ['nullable', 'string', 'max:255'],
        ]);

        auth()->user()->campus->update($validated);

        return redirect()
            ->route('coordinator.campus-settings.edit')
            ->with('status', 'Campus settings updated.');
    }
}
