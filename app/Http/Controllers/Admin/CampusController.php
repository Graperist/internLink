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
    public function index(Request $request): View
    {
        $all = Campus::withCount('programs')->orderBy('name')->get();
        if ($search = trim((string) $request->query('search'))) {
            $needle = mb_strtolower($search);
            $all = $all->filter(fn ($c) => str_contains(mb_strtolower($c->code.' '.$c->name.' '.$c->address), $needle))->values();
        }
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
            'names' => $others->pluck('name')->map(fn ($n) => self::placeKey($n))->values()->all(),
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
                // "PSU Binmaley" and "Pangasinan State University - Binmaley
                // Campus" are the same campus.
                $same = Campus::when($campus, fn ($q) => $q->whereKeyNot($campus->id))->get(['name'])
                    ->first(fn ($c) => self::placeKey($c->name) === self::placeKey($value));
                if ($same) {
                    $fail("This campus already exists: {$same->name}.");
                }
            }],
            'address' => ['nullable', 'string', 'max:255'],
        ], [
            'code.regex' => 'The campus code must be 2 to 3 capital letters only (A-Z), e.g. ACC or BC - no numbers, spaces or symbols.',
            'code.unique' => 'The campus code :input is already used by another campus.',
        ]);
    }

    /**
     * A campus's place, for spotting the same campus typed differently:
     * "Pangasinan State University - Binmaley Campus", "PSU Binmaley" and
     * "binmaley campus" all give "binmaley".
     */
    public static function placeKey(string $name): string
    {
        $key = mb_strtolower($name);
        $key = str_replace(['pangasinan state university', 'psu'], ' ', $key);
        $key = preg_replace('/\b(campus|city|of|the)\b/u', ' ', $key);
        $key = preg_replace('/[^a-z0-9]+/u', '', $key);

        return $key !== '' ? $key : mb_strtolower(trim($name));
    }
}
