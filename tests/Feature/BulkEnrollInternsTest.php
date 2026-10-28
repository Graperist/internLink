<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Instructor;
use App\Models\Program;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Bulk enroll (Add Interns from a spreadsheet) without any Excel package. */
class BulkEnrollInternsTest extends TestCase
{
    use RefreshDatabase;

    private User $instructor;

    protected function setUp(): void
    {
        parent::setUp();
        $roles = [];
        foreach (['coordinator', 'instructor', 'hte_representative', 'student'] as $code) {
            $roles[$code] = Role::forceCreate(['code' => $code, 'name' => ucfirst($code)]);
        }
        $campus = Campus::forceCreate(['code' => 'ACC', 'name' => 'Alaminos City Campus', 'status' => 'active']);
        $program = Program::forceCreate(['campus_id' => $campus->id, 'code' => 'BSIT', 'name' => 'BSIT', 'required_hours' => 486]);
        Program::forceCreate(['campus_id' => $campus->id, 'code' => 'BSHM', 'name' => 'BSHM', 'required_hours' => 486]);
        $this->instructor = User::forceCreate([
            'campus_id' => $campus->id, 'role_id' => $roles['instructor']->id, 'id_number' => 'ACC-INST-001', 'name' => 'Prof',
            'email' => 'prof@t.local', 'password' => Hash::make('x'), 'status' => 'active', 'must_change_password' => false,
        ]);
        Instructor::forceCreate(['user_id' => $this->instructor->id, 'campus_id' => $campus->id, 'program_id' => $program->id]);
        User::forceCreate([
            'campus_id' => $campus->id, 'role_id' => $roles['student']->id, 'id_number' => '23-AC-0001', 'name' => 'Existing',
            'email' => 'existing@t.local', 'password' => Hash::make('x'), 'status' => 'active', 'must_change_password' => false,
        ]);
    }

    private function upload(UploadedFile $file)
    {
        return $this->actingAs($this->instructor)->post(route('instructor.interns.import.store'), ['file' => $file]);
    }

    public function test_a_csv_creates_the_interns_and_reports_skipped_rows(): void
    {
        $csv = "\u{FEFF}id_number,name,email,program_code\n"
            ."23-AC-0451,Juan Reyes,juan.reyes@example.com,BSIT\n"
            ."23-AC-0001,Dup Id,dup@example.com,BSIT\n"
            ."23-AC-0452,Bad Email,not-an-email,\n"
            ."\n"
            ."23-AC-0453,Ana Cruz,ana@example.com,\n";
        $response = $this->upload(UploadedFile::fake()->createWithContent('interns.csv', $csv));

        $response->assertRedirect(route('instructor.advisees'));
        $results = session('import_results');
        $this->assertSame(['23-AC-0451', '23-AC-0453'], array_column($results['created'], 'id_number'));
        $this->assertSame([3, 4], array_column($results['skipped'], 'row'));
        $this->assertNotNull(User::where('id_number', '23-AC-0451')->first()?->student?->currentInternship);
        $this->assertTrue((bool) User::where('id_number', '23-AC-0451')->value('must_change_password'));
    }

    public function test_an_xlsx_made_in_excel_works_with_friendly_headings(): void
    {
        $file = new UploadedFile(base_path('tests/fixtures/student_import.xlsx'), 'interns.xlsx', null, null, true);
        $this->upload($file)->assertRedirect(route('instructor.advisees'));

        $results = session('import_results');
        $this->assertSame(['23-AC-0451', '23-AC-0238'], array_column($results['created'], 'id_number'));
        $this->assertSame('María Lim', User::where('id_number', '23-AC-0238')->value('name'));
        $this->assertSame([5], array_column($results['skipped'], 'row'));   // BSHM is not the instructor's program
        $this->assertSame(2, Student::count());
    }

    public function test_a_file_without_the_right_headings_is_explained(): void
    {
        $this->from(route('instructor.interns.import'))
            ->upload(UploadedFile::fake()->createWithContent('x.csv', "student,mail\nJuan,j@x.com\n"))
            ->assertRedirect(route('instructor.interns.import'))
            ->assertSessionHasErrors('file');
        $this->upload(UploadedFile::fake()->create('old.xls', 10, 'application/vnd.ms-excel'))->assertSessionHasErrors('file');
        $this->assertSame(0, Student::count());
    }
}
