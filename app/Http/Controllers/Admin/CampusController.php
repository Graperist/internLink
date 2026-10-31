<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campus;
use App\Models\Role;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Super Admin: the PSU campuses. A campus is never deleted (its records
 * would go with it) - it is set to Inactive instead, which takes it off the
 * login page's campus detection.
 */
class CampusController extends Controller
{
    public function index(): View
    {
        $all = Campus::withCount('programs')->orderBy('name')->get();
        $campuses = $all->where('status', 'active')->values();
        $archived = $all->where('status', '!=', 'active')->values();

        return view('admin.campuses.index', compact('campuses', 'archived'));
    }

    public function create(): View
    {
        return view('admin.campuses.form', ['campus' => new Campus, 'taken' => $this->taken()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $campus = Campus::create($this->validated($request) + ['status' => 'active']);
        AuditLogger::record('admin.campus_added', "Added campus {$campus->code} - {$campus->name}.", auth()->user(), $request, campusId: $campus->id);

        return redirect()->route('admin.campuses.index')
            ->with('status', "{$campus->name} was added. Next, add its OJT Coordinator account.");
    }

    public function edit(Campus $campus): View
    {
        return view('admin.campuses.form', ['campus' => $campus, 'taken' => $this->taken($campus)]);
    }

    public function update(Request $request, Campus $campus): RedirectResponse
    {
        $data = $this->validated($request, $campus);
        // Accounts find their campus from the code inside their ID, so the
        // code can't change once the campus has accounts.
        if ($campus->users()->exists()) {
            $data['code'] = $campus->code;
        }
        $campus->update($data);
        AuditLogger::record('admin.campus_updated', "Updated campus {$campus->code} - {$campus->name}.", auth()->user(), $request, campusId: $campus->id);

        return redirect()->route('admin.campuses.index')->with('status', "{$campus->name} was updated.");
    }

    /**
     * Archive instead of delete: the campus and every record on it stay;
     * its accounts can no longer sign in. Restore brings it back.
     */
    public function archive(Request $request, Campus $campus): RedirectResponse
    {
        $campus->update(['status' => 'archived']);
        if (\App\Support\CampusManager::campusId() === $campus->id) {
            $request->session()->forget(\App\Support\CampusManager::SESSION_KEY);
        }
        AuditLogger::record('admin.campus_archived', "Archived campus {$campus->code} - {$campus->name}.", auth()->user(), $request, campusId: $campus->id);

        return redirect()->route('admin.campuses.index')
            ->with('status', "{$campus->name} was archived. Its records are kept and its accounts can no longer sign in. You can restore it from Archived campuses.");
    }

    public function restore(Request $request, Campus $campus): RedirectResponse
    {
        $campus->update(['status' => 'active']);
        AuditLogger::record('admin.campus_restored', "Restored campus {$campus->code} - {$campus->name}.", auth()->user(), $request, campusId: $campus->id);

        return redirect()->route('admin.campuses.index')->with('status', "{$campus->name} was restored. Its accounts can sign in again.");
    }

    /** Codes and names already used (for the warning on the form), except this campus's own. */
    private function taken(?Campus $campus = null): array
    {
        $others = Campus::when($campus, fn ($q) => $q->whereKeyNot($campus->id))->get(['code', 'name']);

        return [
            'codes' => $others->pluck('code')->map(fn ($c) => strtoupper($c))->values()->all(),
            'names' => $others->pluck('name')->map(fn ($n) => mb_strtolower(trim($n)))->values()->all(),
        ];
    }

    private function validated(Request $request, ?Campus $campus = null): array
    {
        $request->merge([
            'code' => strtoupper(trim((string) $request->input('code'))),
            'name' => trim((string) $request->input('name')),
        ]);

        return $request->validate([
            'code' => ['required', 'regex:/^[A-Z]{2,3}$/', Rule::unique('campuses', 'code')->ignore($campus?->id)],
            'name' => ['required', 'string', 'max:255', function ($attribute, $value, $fail) use ($campus) {
                $exists = Campus::when($campus, fn ($q) => $q->whereKeyNot($campus->id))
                    ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($value)])->exists();
                if ($exists) {
                    $fail('A campus with this name already exists.');
                }
            }],
            'address' => ['nullable', 'string', 'max:255'],
        ], [
            'code.regex' => 'The campus code is 2 to 3 letters, e.g. ACC or BC. It is the code used inside employee IDs (ACC-COORD-001).',
            'code.unique' => 'The campus code :input is already used by another campus.',
        ]);
    }
}
