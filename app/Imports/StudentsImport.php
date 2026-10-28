<?php

namespace App\Imports;

use App\Models\Instructor;
use App\Models\Internship;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Throwable;

/**
 * Bulk-provisions Student Intern accounts from an uploaded spreadsheet.
 *
 * Read with App\Support\SpreadsheetRows (.csv or .xlsx). Expected columns
 * (see public/templates/student_import_template.csv):
 *   id_number, name, email, program_code (optional)
 *
 * Interns are added by their subject instructor, so every row goes into the
 * importing instructor's own campus AND program - neither is read from the
 * file. If a row does fill in program_code, it must match that program, so
 * a sheet meant for another program is caught instead of silently imported.
 *
 * The OJT Coordinator can import into any program of the campus: each row's
 * program_code decides (or the program chosen on the form when it is blank),
 * and the intern is placed under that program's subject instructor.
 */
class StudentsImport
{
    /** @var array<int, array{row:int,name:string,id_number:string,temporary_password:string}> */
    public array $created = [];

    /** @var array<int, array{row:int,reason:string}> */
    public array $skipped = [];

    public function __construct(private readonly Instructor $instructor, private readonly ?\App\Models\Program $defaultProgram = null)
    {
    }

    public function collection(Collection $rows): void
    {
        $studentRole = Role::where('code', 'student')->firstOrFail();
        $ownProgram = $this->instructor->program;
        $campusId = $this->instructor->campus_id;
        $coordinator = $this->instructor->isCoordinator();
        $campusPrograms = \App\Models\Program::where('campus_id', $campusId)->get();

        foreach ($rows as $index => $row) {
            $excelRow = $index + 2; // +1 for zero-index, +1 for the header row
            if ($row === null) {
                continue;   // a blank row in the sheet
            }

            $idNumber = trim((string) ($row['id_number'] ?? ''));
            $name = trim((string) ($row['name'] ?? ''));
            $email = trim((string) ($row['email'] ?? ''));
            $programCode = trim((string) ($row['program_code'] ?? ''));

            if ($idNumber === '' || $name === '' || $email === '') {
                $this->skipped[] = ['row' => $excelRow, 'reason' => 'Missing required field(s).'];
                continue;
            }

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->skipped[] = ['row' => $excelRow, 'reason' => "Email {$email} is not a valid email address."];
                continue;
            }

            if ($coordinator) {
                $program = $programCode !== ''
                    ? $campusPrograms->first(fn ($p) => strcasecmp($p->code, $programCode) === 0)
                    : ($this->defaultProgram ?? $ownProgram);
                if (! $program) {
                    $this->skipped[] = ['row' => $excelRow, 'reason' => $programCode !== ''
                        ? "Program '{$programCode}' is not a program of this campus."
                        : 'Fill in program_code, or choose a program on the import form.'];
                    continue;
                }
            } else {
                $program = $ownProgram;
                if ($programCode !== '' && strcasecmp($programCode, $program->code) !== 0) {
                    $this->skipped[] = ['row' => $excelRow, 'reason' => "Program '{$programCode}' is not your program ({$program->code}). Only its own instructor can add this intern."];
                    continue;
                }
            }
            $handler = $this->instructor->handlerFor($program);

            // Unique across ALL campuses - sign-in is by ID alone.
            if (User::where('id_number', $idNumber)->exists()) {
                $this->skipped[] = ['row' => $excelRow, 'reason' => "ID number {$idNumber} is already registered."];
                continue;
            }

            if (User::where('email', $email)->exists()) {
                $this->skipped[] = ['row' => $excelRow, 'reason' => "Email {$email} is already registered."];
                continue;
            }

            $temporaryPassword = Str::password(10, symbols: false);

            try {
                DB::transaction(function () use ($idNumber, $name, $email, $program, $handler, $campusId, $studentRole, $temporaryPassword) {
                    $user = User::create([
                        'campus_id' => $campusId,
                        'role_id' => $studentRole->id,
                        'id_number' => $idNumber,
                        'name' => $name,
                        'email' => $email,
                        'password' => Hash::make($temporaryPassword),
                        'status' => 'active',
                        'must_change_password' => true,
                    ]);

                    $student = Student::create([
                        'user_id' => $user->id,
                        'campus_id' => $campusId,
                        'program_id' => $program->id,
                        'instructor_id' => $handler?->id,
                        'hours_rendered' => 0,
                    ]);

                    // Same as the single Add Intern form: every student starts
                    // with an internship record so they can fill in forms.
                    Internship::create([
                        'student_id' => $student->id,
                        'campus_id' => $campusId,
                        'program_id' => $program->id,
                        'instructor_id' => $handler?->id,
                        'required_hours' => $program->required_hours ?? 486,
                        'status' => 'pre_deployment',
                    ]);
                });

                $this->created[] = [
                    'row' => $excelRow,
                    'name' => $name,
                    'id_number' => $idNumber,
                    'temporary_password' => $temporaryPassword,
                ];
            } catch (Throwable $e) {
                $this->skipped[] = ['row' => $excelRow, 'reason' => 'Unexpected error: '.$e->getMessage()];
            }
        }
    }
}
