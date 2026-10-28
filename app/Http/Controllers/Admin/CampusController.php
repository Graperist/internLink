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
        $coordinatorRoleId = Role::where('code', 'coordinator')->value('id');

        $campuses = Campus::withCount([
            'programs',
            'students',
            'users as coordinators_count' => fn ($q) => $q->where('role_id', $coordinatorRoleId),
        ])->addSelect(['active_coordinator_id' => \App\Models\User::select('id')
            ->whereColumn('campus_id', 'campuses.id')
            ->where('role_id', $coordinatorRoleId)
            ->where('status', 'active')
            ->orderBy('id')->limit(1),
        ])->orderBy('name')->get();

        return view('admin.campuses.index', compact('campuses'));
    }

    public function create(): View
    {
        return view('admin.campuses.form', ['campus' => new Campus(['status' => 'active'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $campus = Campus::create($this->validated($request));
        AuditLogger::record('admin.campus_added', "Added campus {$campus->code} - {$campus->name}.", auth()->user(), $request, campusId: $campus->id);

        return redirect()->route('admin.campuses.index')
            ->with('status', "{$campus->name} was added. Next, add its OJT Coordinator account.");
    }

    public function edit(Campus $campus): View
    {
        return view('admin.campuses.form', compact('campus'));
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

    private function validated(Request $request, ?Campus $campus = null): array
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);

        return $request->validate([
            'code' => ['required', 'regex:/^[A-Z]{2,6}$/', Rule::unique('campuses', 'code')->ignore($campus?->id)],
            'name' => ['required', 'string', 'max:255', Rule::unique('campuses', 'name')->ignore($campus?->id)],
            'address' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ], [
            'code.regex' => 'The campus code is 2 to 6 letters, e.g. ACC or BMC. It is the code used inside employee IDs (ACC-COORD-001).',
        ]);
    }
}
