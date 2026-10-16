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
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Validators\Failure;
use Throwable;

/**
 * Bulk-provisions Student Intern accounts from an uploaded spreadsheet.
 *
 * Expected columns (see public/templates/student_import_template.csv):
 *   id_number, name, email, program_code (optional)
 *
 * Interns are added by their subject instructor, so every row goes into the
 * importing instructor's own campus AND program - neither is read from the
 * file. If a row does fill in program_code, it must match that program, so
 * a sheet meant for another program is caught instead of silently imported.
 */
class StudentsImport implements ToCollection, WithHeadingRow, WithValidation, SkipsOnError, SkipsOnFailure
{
    use Importable;

    /** @var array<int, array{row:int,name:string,id_number:string,temporary_password:string}> */
    public array $created = [];

    /** @var array<int, array{row:int,reason:string}> */
    public array $skipped = [];

    public function __construct(private readonly Instructor $instructor)
    {
    }

    public function collection(Collection $rows): void
    {
        $studentRole = Role::where('code', 'student')->firstOrFail();
        $program = $this->instructor->program;
        $campusId = $this->instructor->campus_id;

        foreach ($rows as $index => $row) {
            $excelRow = $index + 2; // +1 for zero-index, +1 for the header row

            $idNumber = trim((string) ($row['id_number'] ?? ''));
            $name = trim((string) ($row['name'] ?? ''));
            $email = trim((string) ($row['email'] ?? ''));
            $programCode = trim((string) ($row['program_code'] ?? ''));

            if ($idNumber === '' || $name === '' || $email === '') {
                $this->skipped[] = ['row' => $excelRow, 'reason' => 'Missing required field(s).'];
                continue;
            }

            if ($programCode !== '' && strcasecmp($programCode, $program->code) !== 0) {
                $this->skipped[] = ['row' => $excelRow, 'reason' => "Program '{$programCode}' is not your program ({$program->code}). Only its own instructor can add this intern."];
                continue;
            }

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
                DB::transaction(function () use ($idNumber, $name, $email, $program, $campusId, $studentRole, $temporaryPassword) {
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
                        'instructor_id' => $this->instructor->id,
                        'hours_rendered' => 0,
                    ]);

                    // Same as the single Add Intern form: every student starts
                    // with an internship record so they can fill in forms.
                    Internship::create([
                        'student_id' => $student->id,
                        'campus_id' => $campusId,
                        'program_id' => $program->id,
                        'instructor_id' => $this->instructor->id,
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

    public function rules(): array
    {
        return [
            'id_number' => ['required'],
            'name' => ['required'],
            'email' => ['required', 'email'],
        ];
    }

    public function onError(Throwable $e): void
    {
        $this->skipped[] = ['row' => 0, 'reason' => $e->getMessage()];
    }

    public function onFailure(Failure ...$failures): void
    {
        foreach ($failures as $failure) {
            $this->skipped[] = [
                'row' => $failure->row(),
                'reason' => implode(' ', $failure->errors()),
            ];
        }
    }
}
