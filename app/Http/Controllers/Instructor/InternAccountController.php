<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Imports\StudentsImport;
use App\Models\Internship;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use App\Support\SpreadsheetRows;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Intern accounts are provisioned by the subject instructor, into their own
 * program only. The OJT Coordinator can add interns to any program on the
 * campus; they are placed under that program's subject instructor.
 */
class InternAccountController extends Controller
{
    public function create(Request $request): View
    {
        $instructor = $this->instructorWithProgram();
        $programs = $this->programsFor($instructor);
        $selectedProgram = $instructor->programForNewRecord($request->query('program_id')) ?? $instructor->program_id;

        return view('instructor.advisees.create', compact('instructor', 'programs', 'selectedProgram'));
    }

    public function store(Request $request): RedirectResponse
    {
        $instructor = $this->instructorWithProgram();

        $data = $request->validate([
            // Unique across ALL campuses - sign-in is by ID alone.
            'id_number' => ['required', 'string', 'max:50', Rule::unique('users', 'id_number')],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'program_id' => [$instructor->isCoordinator() ? 'required' : 'nullable', 'integer', Rule::exists('programs', 'id')->where('campus_id', $instructor->campus_id)],
        ], [
            'id_number.unique' => 'An account with this ID number already exists.',
            'program_id.required' => 'Choose the student\'s program.',
        ]);

        $temporaryPassword = Str::password(10, symbols: false);
        $program = \App\Models\Program::findOrFail($instructor->programForNewRecord($data['program_id'] ?? null) ?? $instructor->program_id);
        // The coordinator's own program stays with them; another program goes to its instructor.
        $handler = $instructor->handlerFor($program);

        $student = DB::transaction(function () use ($data, $instructor, $program, $handler, $temporaryPassword) {
            $user = User::create([
                'campus_id' => $instructor->campus_id,
                'role_id' => Role::where('code', 'student')->firstOrFail()->id,
                'id_number' => $data['id_number'],
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($temporaryPassword),
                'status' => 'active',
                'must_change_password' => true,
            ]);

            $student = Student::create([
                'user_id' => $user->id,
                'campus_id' => $instructor->campus_id,
                'program_id' => $program->id,
                'instructor_id' => $handler?->id,
                'hours_rendered' => 0,
            ]);

            // Every student starts with an internship record so they can fill
            // in their forms; the deployment is added automatically once
            // their Application for OJT is accepted.
            Internship::create([
                'student_id' => $student->id,
                'campus_id' => $instructor->campus_id,
                'program_id' => $program->id,
                'instructor_id' => $handler?->id,
                'required_hours' => $program->required_hours ?? 486,
                'status' => 'pre_deployment',
            ]);

            return $student;
        });

        AuditLogger::record('student.created', "Added intern account {$data['id_number']} ({$program->code}).", auth()->user(), $request, $student, $instructor->campus_id);

        return redirect()->route('instructor.advisees')->with('provisioned_account', [
            'name' => $data['name'],
            'id_number' => $data['id_number'],
            'temporary_password' => $temporaryPassword,
        ]);
    }

    public function resetPassword(Request $request, Student $student): RedirectResponse
    {
        $instructor = auth()->user()->instructor;
        abort_unless($instructor && $instructor->canActOn($student), 403);

        $temporaryPassword = Str::password(10, symbols: false);

        $student->user->update([
            'password' => Hash::make($temporaryPassword),
            'must_change_password' => true,
        ]);

        AuditLogger::record('student.password_reset', "Reset the password of {$student->user->id_number}.", auth()->user(), $request, $student, $instructor->campus_id);

        return redirect()->route('instructor.advisees')->with('provisioned_account', [
            'name' => $student->user->name,
            'id_number' => $student->user->id_number,
            'temporary_password' => $temporaryPassword,
            'reset' => true,
        ]);
    }

    public function importForm(): View
    {
        $instructor = $this->instructorWithProgram();
        $programs = $this->programsFor($instructor);

        return view('instructor.advisees.import', compact('instructor', 'programs'));
    }

    public function import(Request $request): RedirectResponse
    {
        $instructor = $this->instructorWithProgram();

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:5120'],
        ], ['file.mimes' => 'Upload a .csv or .xlsx file. (For an old .xls file, open it in Excel and save it as .xlsx or .csv.)']);

        $file = $request->file('file');
        try {
            $rows = SpreadsheetRows::read($file->getRealPath(), $file->getClientOriginalExtension() ?: $file->extension());
        } catch (\Throwable $e) {
            return back()->withErrors(['file' => $e instanceof \RuntimeException ? $e->getMessage() : 'The file could not be read. Save it as .csv or .xlsx and try again.']);
        }
        if (! $rows) {
            return back()->withErrors(['file' => 'The file has no rows. Use the template: id_number, name, email, program_code.']);
        }
        $missing = array_diff(['id_number', 'name', 'email'], array_keys(collect($rows)->filter()->first() ?? []));
        if ($missing) {
            return back()->withErrors(['file' => 'The heading row is missing: '.implode(', ', $missing).'. Use the template: id_number, name, email, program_code.']);
        }

        // The coordinator imports into any program: the program_code column,
        // or the program chosen on the form for rows that leave it blank.
        $defaultProgram = $instructor->isCoordinator() && $request->filled('program_id')
            ? \App\Models\Program::where('campus_id', $instructor->campus_id)->find($request->input('program_id'))
            : null;
        $import = new StudentsImport($instructor, $defaultProgram);
        $import->collection(collect($rows));

        return redirect()->route('instructor.advisees')->with('import_results', [
            'created' => $import->created,
            'skipped' => $import->skipped,
        ]);
    }

    public function template(): BinaryFileResponse
    {
        return response()->download(
            public_path('templates/student_import_template.csv'),
            'internlink-student-import-template.csv'
        );
    }

    /** The programs the coordinator can add interns to (empty for an instructor). */
    private function programsFor($instructor)
    {
        return $instructor->isCoordinator()
            ? \App\Models\Program::where('campus_id', $instructor->campus_id)->orderBy('code')->get()
            : collect();
    }

    /** Adding interns needs a program to add them to (the coordinator picks one). */
    private function instructorWithProgram()
    {
        $instructor = auth()->user()->instructor;
        abort_unless($instructor, 403);
        abort_unless($instructor->canAddForPrograms(), 403, 'Set the program you teach in My Profile before adding interns.');
        $instructor->loadMissing('program', 'campus');

        return $instructor;
    }
}
