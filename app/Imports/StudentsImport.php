<?php

namespace App\Imports;

use App\Models\Instructor;
use App\Models\Program;
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
 *   id_number, name, email, program_code, instructor_email (optional)
 *
 * campus_id is NEVER read from the file - every row is provisioned into the
 * logged-in coordinator's own campus, same rule as the single-add form.
 */
class StudentsImport implements ToCollection, WithHeadingRow, WithValidation, SkipsOnError, SkipsOnFailure
{
    use Importable;

    /** @var array<int, array{row:int,name:string,id_number:string,temporary_password:string}> */
    public array $created = [];

    /** @var array<int, array{row:int,reason:string}> */
    public array $skipped = [];

    public function __construct(private readonly int $campusId)
    {
    }

    public function collection(Collection $rows): void
    {
        $studentRole = Role::where('code', 'student')->firstOrFail();

        foreach ($rows as $index => $row) {
            $excelRow = $index + 2; // +1 for zero-index, +1 for the header row

            $idNumber = trim((string) ($row['id_number'] ?? ''));
            $name = trim((string) ($row['name'] ?? ''));
            $email = trim((string) ($row['email'] ?? ''));
            $programCode = trim((string) ($row['program_code'] ?? ''));
            $instructorEmail = trim((string) ($row['instructor_email'] ?? ''));

            if ($idNumber === '' || $name === '' || $email === '' || $programCode === '') {
                $this->skipped[] = ['row' => $excelRow, 'reason' => 'Missing required field(s).'];
                continue;
            }

            if (User::where('campus_id', $this->campusId)->where('id_number', $idNumber)->exists()) {
                $this->skipped[] = ['row' => $excelRow, 'reason' => "ID number {$idNumber} already exists at this campus."];
                continue;
            }

            if (User::where('email', $email)->exists()) {
                $this->skipped[] = ['row' => $excelRow, 'reason' => "Email {$email} is already registered."];
                continue;
            }

            $program = Program::where('campus_id', $this->campusId)->where('code', $programCode)->first();
            if (! $program) {
                $this->skipped[] = ['row' => $excelRow, 'reason' => "Program code '{$programCode}' not found for this campus."];
                continue;
            }

            $instructorId = null;
            if ($instructorEmail !== '') {
                $instructor = Instructor::whereHas('user', fn ($q) => $q->where('email', $instructorEmail))
                    ->where('campus_id', $this->campusId)
                    ->first();

                if (! $instructor) {
                    $this->skipped[] = ['row' => $excelRow, 'reason' => "Instructor '{$instructorEmail}' not found for this campus."];
                    continue;
                }
                $instructorId = $instructor->id;
            }

            $temporaryPassword = Str::password(10, symbols: false);

            try {
                DB::transaction(function () use ($idNumber, $name, $email, $program, $instructorId, $studentRole, $temporaryPassword) {
                    $user = User::create([
                        'campus_id' => $this->campusId,
                        'role_id' => $studentRole->id,
                        'id_number' => $idNumber,
                        'name' => $name,
                        'email' => $email,
                        'password' => Hash::make($temporaryPassword),
                        'status' => 'active',
                        'must_change_password' => true,
                    ]);

                    Student::create([
                        'user_id' => $user->id,
                        'campus_id' => $this->campusId,
                        'program_id' => $program->id,
                        'instructor_id' => $instructorId,
                        'hours_rendered' => 0,
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
            'program_code' => ['required'],
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
