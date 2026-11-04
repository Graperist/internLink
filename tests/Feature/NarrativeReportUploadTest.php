<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Deployment;
use App\Models\Document;
use App\Models\DocumentRequirement;
use App\Models\Hte;
use App\Models\Internship;
use App\Models\Program;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** The Narrative Report is one PDF of up to 50 MB. */
class NarrativeReportUploadTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private DocumentRequirement $narrative;
    private DocumentRequirement $other;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
        $role = Role::forceCreate(['code' => 'student', 'name' => 'student']);
        $campus = Campus::forceCreate(['code' => 'ACC', 'name' => 'Alaminos City Campus', 'status' => 'active']);
        $program = Program::forceCreate(['campus_id' => $campus->id, 'code' => 'BSIT', 'name' => 'BSIT', 'required_hours' => 486]);
        $this->user = User::forceCreate(['campus_id' => $campus->id, 'role_id' => $role->id, 'id_number' => 'S-1', 'name' => 'Stu',
            'email' => 's@t.local', 'password' => Hash::make('x'), 'status' => 'active', 'must_change_password' => false]);
        $student = Student::forceCreate(['user_id' => $this->user->id, 'campus_id' => $campus->id, 'program_id' => $program->id, 'hours_rendered' => 0]);
        $internship = Internship::forceCreate(['student_id' => $student->id, 'campus_id' => $campus->id, 'program_id' => $program->id, 'required_hours' => 486, 'status' => 'completed']);
        $hte = Hte::forceCreate(['campus_id' => $campus->id, 'name' => 'Agency']);
        Deployment::forceCreate(['internship_id' => $internship->id, 'hte_id' => $hte->id, 'status' => 'completed', 'start_date' => '2026-08-01']);
        $make = fn ($code, $name) => DocumentRequirement::forceCreate([
            'campus_id' => $campus->id, 'code' => $code, 'name' => $name, 'stage' => 'post_deployment', 'audience' => 'student',
            'workflow_mode' => 'upload', 'is_active' => true, 'is_required' => true, 'display_order' => 220,
        ]);
        $this->narrative = $make('NARRATIVE_REPORT', 'Narrative Report');
        $this->other = $make('OTHER_UPLOAD', 'Other Upload');
    }

    private function upload(DocumentRequirement $requirement, UploadedFile $file)
    {
        return $this->actingAs($this->user)->post(route('student.documents.store'), ['document_requirement_id' => $requirement->id, 'file' => $file]);
    }

    public function test_a_pdf_up_to_50_mb_is_accepted(): void
    {
        $this->upload($this->narrative, UploadedFile::fake()->create('narrative.pdf', 45 * 1024, 'application/pdf'))->assertSessionHasNoErrors();
        $this->assertSame('submitted', Document::where('document_requirement_id', $this->narrative->id)->value('status'));
    }

    public function test_over_50_mb_or_not_a_pdf_is_refused(): void
    {
        $this->upload($this->narrative, UploadedFile::fake()->create('narrative.pdf', 51 * 1024, 'application/pdf'))
            ->assertSessionHasErrors(['file' => 'The Narrative Report can be up to 50 MB.']);
        $this->upload($this->narrative, UploadedFile::fake()->image('narrative.jpg'))
            ->assertSessionHasErrors(['file' => 'The Narrative Report must be a PDF file.']);
        $this->assertSame(0, Document::count());
    }

    public function test_other_uploads_keep_their_10_mb_limit(): void
    {
        $this->upload($this->other, UploadedFile::fake()->create('x.pdf', 11 * 1024, 'application/pdf'))->assertSessionHasErrors('file');
        $this->upload($this->other, UploadedFile::fake()->image('x.jpg'))->assertSessionHasNoErrors();
    }

    public function test_the_upload_box_says_pdf_up_to_50_mb(): void
    {
        $this->actingAs($this->user)->get(route('student.documents', ['form' => $this->narrative->id]))->assertOk()
            ->assertSee('data-hint="File (PDF only — max 50MB)"', false)->assertSee('data-accept=".pdf"', false);
    }
}
