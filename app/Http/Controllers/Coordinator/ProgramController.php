<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Programs - the courses (and majors) offered for OJT on this campus.
 * Each row is one course + optional major; instructors are assigned to a
 * program and add interns into it, and the student forms take their
 * Course / Major choices from this list.
 */
class ProgramController extends Controller
{
    public function index(): View
    {
        $programs = Program::where('campus_id', auth()->user()->campus_id)
            ->withCount(['students', 'instructors'])
            ->orderBy('code')
            ->get();

        return view('coordinator.programs.index', compact('programs'));
    }

    public function create(): View
    {
        return view('coordinator.programs.form', ['program' => new Program(['required_hours' => 486])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $campusId = auth()->user()->campus_id;
        $data = $this->validated($request, $campusId);

        $program = Program::create($data + ['campus_id' => $campusId]);

        AuditLogger::record('program.created', "Added program {$program->code}.", auth()->user(), $request, $program, $campusId);

        return redirect()->route('coordinator.programs.index')->with('status', "{$program->code} was added.");
    }

    public function edit(Program $program): View
    {
        $this->authorizeCampus($program);

        return view('coordinator.programs.form', compact('program'));
    }

    public function update(Request $request, Program $program): RedirectResponse
    {
        $this->authorizeCampus($program);
        $program->update($this->validated($request, $program->campus_id, $program));

        AuditLogger::record('program.updated', "Updated program {$program->code}.", auth()->user(), $request, $program, $program->campus_id);

        return redirect()->route('coordinator.programs.index')->with('status', "{$program->code} was updated.");
    }

    public function destroy(Request $request, Program $program): RedirectResponse
    {
        $this->authorizeCampus($program);

        if ($program->students()->exists() || $program->instructors()->exists()) {
            return back()->withErrors(['program' => "{$program->code} still has students or instructors, so it can't be removed."]);
        }

        $code = $program->code;
        $program->delete();

        AuditLogger::record('program.deleted', "Removed program {$code}.", auth()->user(), $request, null, auth()->user()->campus_id);

        return redirect()->route('coordinator.programs.index')->with('status', "{$code} was removed.");
    }

    private function validated(Request $request, int $campusId, ?Program $program = null): array
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);

        return $request->validate([
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9-]+$/',
                Rule::unique('programs', 'code')->where('campus_id', $campusId)->ignore($program?->id)],
            'name' => ['required', 'string', 'max:255'],
            'major' => ['nullable', 'string', 'max:255'],
            'department_chair_name' => ['nullable', 'string', 'max:255'],
            'required_hours' => ['required', 'integer', 'min:1', 'max:2000'],
        ], [
            'code.unique' => 'This campus already has a program with that code.',
            'code.regex' => 'Use letters, numbers and dashes only (e.g. BSBA-MM).',
            'name.required' => 'Enter the course name.',
        ]);
    }

    private function authorizeCampus(Program $program): void
    {
        abort_unless((int) $program->campus_id === (int) auth()->user()->campus_id, 403);
    }
}
