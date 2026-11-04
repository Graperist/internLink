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

        $academicYear = \App\Support\Term::current()[0];

        return view('instructor.advisees.create', compact('instructor', 'programs', 'selectedProgram', 'academicYear'));
    }

    public function store(Request $request): RedirectResponse
    {
        $instructor = $this->instructorWithProgram();
        // Blank means the current academic year.
        $request->merge(['academic_year' => \App\Support\Term::normalizeAcademicYear($request->input('academic_year')) ?? \App\Support\Term::current()[0]]);

        $data = $request->validate([
            'academic_year' => ['required', 'string', function ($attr, $value, $fail) {
                if (! \App\Support\Term::isValidAcademicYear($value)) {
                    $fail('Type the academic year like 2026-2027 (or just 2026).');
                }
            }],
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
                'academic_year' => $data['academic_year'],
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

        return redirect()->route('instructor.advisees', $this->yearQuery($data['academic_year']))->with('provisioned_account', [
            'name' => $data['name'],
            'id_number' => $data['id_number'],
            'temporary_password' => $temporaryPassword,
        ]);
    }

    public function resetPassword(Request $request, Student $student): RedirectResponse
    {
        $instructor = auth()->user()->instructor;
        // The coordinator resets interns of every program; an instructor their own.
        abort_unless($instructor && $instructor->canManageAccountOf($student), 403);

        $temporaryPassword = Str::password(10, symbols: false);

        $student->user->update([
            'password' => Hash::make($temporaryPassword),
            'must_change_password' => true,
        ]);

        AuditLogger::record('student.password_reset', "Reset the password of {$student->user->id_number}.", auth()->user(), $request, $student, $instructor->campus_id);

        return back()->with('provisioned_account', [
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

        $academicYear = \App\Support\Term::current()[0];

        return view('instructor.advisees.import', compact('instructor', 'programs', 'academicYear'));
    }

    public function import(Request $request): RedirectResponse
    {
        $instructor = $this->instructorWithProgram();

        // Blank means the current academic year.
        $request->merge(['academic_year' => \App\Support\Term::normalizeAcademicYear($request->input('academic_year')) ?? \App\Support\Term::current()[0]]);
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:5120'],
            'academic_year' => ['required', 'string', function ($attr, $value, $fail) {
                if (! \App\Support\Term::isValidAcademicYear($value)) {
                    $fail('Type the academic year like 2026-2027 (or just 2026).');
                }
            }],
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
        $import = new StudentsImport($instructor, $defaultProgram, $request->input('academic_year'));
        $import->collection(collect($rows));

        // Kept (for 2 hours) so the sign-in details can be downloaded as a
        // file and sent to the students, e.g. on Messenger.
        if ($import->created) {
            session()->put('import_credentials', [
                'at' => now()->toIso8601String(),
                'academic_year' => $request->input('academic_year'),
                'rows' => $import->created,
            ]);
        }
        AuditLogger::record('student.imported', count($import->created).' intern account(s) enrolled by bulk import for AY '.$request->input('academic_year').'.', auth()->user(), $request, null, $instructor->campus_id);

        return redirect()->route('instructor.advisees', $this->yearQuery($request->input('academic_year')))->with('import_results', [
            'created' => $import->created,
            'skipped' => $import->skipped,
        ]);
    }

    /**
     * The last bulk import's sign-in details as a CSV file (opens in Excel)
     * to send the students. Available for 2 hours after the import.
     */
    public function credentials(): \Symfony\Component\HttpFoundation\StreamedResponse|RedirectResponse
    {
        $saved = session('import_credentials');
        if (! $saved || now()->diffInMinutes(\Illuminate\Support\Carbon::parse($saved['at'])) > 120) {
            session()->forget('import_credentials');

            return redirect()->route('instructor.advisees')->withErrors(['credentials' => 'The sign-in details of the last import are no longer available. Use Reset password to give a student a new one.']);
        }

        $signIn = route('login');
        $filename = 'internlink-student-sign-in-details-'.\Illuminate\Support\Carbon::parse($saved['at'])->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($saved, $signIn) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");   // so Excel reads the names correctly
            fputcsv($out, ['Name', 'ID number', 'Temporary password', 'Program', 'Academic year', 'Sign in at']);
            foreach ($saved['rows'] as $row) {
                fputcsv($out, [$row['name'], $row['id_number'], $row['temporary_password'], $row['program'] ?? '', $saved['academic_year'] ?? '', $signIn]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
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

    /**
     * Edit a student: name, ID number, email and academic year. The OJT
     * Coordinator edits every program's students and can also move a
     * student to another program while they are not deployed yet; an
     * instructor edits their own program's students.
     */
    public function edit(Student $student): View
    {
        $instructor = auth()->user()->instructor;
        abort_unless($instructor && $instructor->canManageAccountOf($student), 403);
        $student->load('user', 'program', 'currentInternship.deployment');

        return view('instructor.advisees.edit', [
            'student' => $student,
            'instructor' => $instructor,
            'programs' => $this->programsFor($instructor),
            'canMoveProgram' => $instructor->isCoordinator() && $this->canMoveProgram($student),
        ]);
    }

    public function update(Request $request, Student $student): RedirectResponse
    {
        $instructor = auth()->user()->instructor;
        abort_unless($instructor && $instructor->canManageAccountOf($student), 403);
        $student->load('user', 'currentInternship.deployment');
        $user = $student->user;

        $request->merge(['academic_year' => \App\Support\Term::normalizeAcademicYear($request->input('academic_year')) ?? $student->academic_year]);
        $data = $request->validate([
            'academic_year' => ['required', 'string', function ($attr, $value, $fail) {
                if (! \App\Support\Term::isValidAcademicYear($value)) {
                    $fail('Type the academic year like 2026-2027 (or just 2026).');
                }
            }],
            'id_number' => ['required', 'string', 'max:50', Rule::unique('users', 'id_number')->ignore($user->id)],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'program_id' => ['nullable', 'integer', Rule::exists('programs', 'id')->where('campus_id', $instructor->campus_id)],
        ], [
            'id_number.unique' => 'An account with this ID number already exists.',
            'email.unique' => 'This email is already used by another account.',
        ]);

        $newProgram = null;
        if ($instructor->isCoordinator() && ! empty($data['program_id']) && (int) $data['program_id'] !== (int) $student->program_id) {
            abort_unless($this->canMoveProgram($student), 422, 'A student who is already deployed stays in their program.');
            $newProgram = \App\Models\Program::findOrFail($data['program_id']);
        }

        DB::transaction(function () use ($user, $student, $data, $newProgram, $instructor) {
            $user->update(['id_number' => $data['id_number'], 'name' => $data['name'], 'email' => $data['email']]);
            $student->update(['academic_year' => $data['academic_year']]);

            if ($newProgram) {
                $handler = $instructor->handlerFor($newProgram);
                $student->update(['program_id' => $newProgram->id, 'instructor_id' => $handler?->id]);
                $student->currentInternship?->update([
                    'program_id' => $newProgram->id,
                    'instructor_id' => $handler?->id,
                    'required_hours' => $newProgram->required_hours ?? $student->currentInternship->required_hours,
                ]);
            }
        });

        AuditLogger::record('student.updated', "Edited the student {$user->name} ({$user->id_number})".($newProgram ? ", moved to {$newProgram->code}" : '').'.', auth()->user(), $request, $student, $instructor->campus_id);

        return redirect()->route('instructor.advisees', $this->yearQuery($data['academic_year']))
            ->with('status', "{$user->name}'s details were updated.");
    }

    /** A student can change program only before being deployed. */
    private function canMoveProgram(Student $student): bool
    {
        $internship = $student->currentInternship;

        return ! $internship || ($internship->status === 'pre_deployment'
            && ! in_array($internship->deployment?->status, ['active', 'completed'], true));
    }

    /**
     * Archive instead of delete: the student's account, internship, forms
     * and hours are all kept; they just can't sign in until restored.
     */
    public function archive(Request $request, Student $student): RedirectResponse
    {
        $instructor = auth()->user()->instructor;
        abort_unless($instructor && $instructor->canManageAccountOf($student), 403);

        $student->user->update(['status' => 'archived']);
        AuditLogger::record('student.archived', "Archived the student {$student->user->name} ({$student->user->id_number}).", auth()->user(), $request, $student, $instructor->campus_id);

        return back()->with('status', "{$student->user->name} was archived and can no longer sign in. Their records are kept; restore them from Archived students.");
    }

    public function restore(Request $request, Student $student): RedirectResponse
    {
        $instructor = auth()->user()->instructor;
        abort_unless($instructor && $instructor->canManageAccountOf($student), 403);

        $student->user->update(['status' => 'active']);
        AuditLogger::record('student.restored', "Restored the student {$student->user->name} ({$student->user->id_number}).", auth()->user(), $request, $student, $instructor->campus_id);

        return back()->with('status', "{$student->user->name} was restored and can sign in again.");
    }

    /** The list opens on the year just enrolled (no parameter for the current year). */
    private function yearQuery(string $year): array
    {
        return $year === \App\Support\Term::current()[0] ? [] : ['academic_year' => $year];
    }
}
