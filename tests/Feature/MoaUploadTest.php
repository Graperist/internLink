<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Models\DocumentRequirement;
use App\Models\Instructor;
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

/**
 * Form 10 - MOA: processed outside InternLink; the student uploads scans of
 * the signed MOA (several files, several submissions).
 */
class MoaUploadTest extends TestCase
{
    use RefreshDatabase;

    private DocumentRequirement $moa;

    private Student $student;

    private User $instructor;

    private User $coordinator;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();

        $roles = [];
        foreach (['coordinator', 'instructor', 'student'] as $code) {
            $roles[$code] = Role::forceCreate(['code' => $code, 'name' => $code]);
        }
        $campus = Campus::forceCreate(['code' => 'ACC', 'name' => 'PSU - Alaminos City Campus', 'status' => 'active']);
        $program = Program::forceCreate(['campus_id' => $campus->id, 'code' => 'BSIT', 'name' => 'BSIT', 'required_hours' => 486]);
        // As it was before: switched off and the coordinator's. The migration turns it on for students.
        $this->moa = DocumentRequirement::forceCreate([
            'campus_id' => $campus->id, 'code' => 'MOA', 'name' => 'Form 10 – Memorandum of Agreement (Outside InternLink Scope)', 'form_number' => '10',
            'stage' => 'pre_deployment', 'audience' => 'coordinator', 'workflow_mode' => 'upload', 'is_active' => false, 'is_required' => false, 'display_order' => 80,
        ]);
        (require database_path('migrations/2026_10_16_600000_enable_moa_upload.php'))->up();
        $this->moa->refresh();

        $mk = fn ($role, $id) => User::forceCreate([
            'campus_id' => $campus->id, 'role_id' => $roles[$role]->id, 'id_number' => $id, 'name' => "{$role} {$id}",
            'email' => strtolower($id).'@t.local', 'password' => Hash::make('x'), 'status' => 'active', 'must_change_password' => false,
        ]);
        $this->instructor = $mk('instructor', 'ACC-INST-001');
        Instructor::forceCreate(['user_id' => $this->instructor->id, 'campus_id' => $campus->id, 'program_id' => $program->id]);
        $this->coordinator = $mk('coordinator', 'ACC-COORD-001');
        $this->student = Student::forceCreate(['user_id' => $mk('student', '23-AC-0093')->id, 'campus_id' => $campus->id, 'program_id' => $program->id, 'hours_rendered' => 0]);
        Internship::forceCreate(['student_id' => $this->student->id, 'campus_id' => $campus->id, 'program_id' => $program->id, 'required_hours' => 486, 'status' => 'pre_deployment']);
    }

    private function upload(array $names)
    {
        return $this->actingAs($this->student->user)->post(route('student.documents.moa', $this->moa), [
            'files' => array_map(fn ($n) => UploadedFile::fake()->create($n, 50, 'application/pdf'), $names),
        ]);
    }

    public function test_the_moa_is_a_student_pre_deployment_upload(): void
    {
        $this->assertSame('Form 10 – Memorandum of Agreement', $this->moa->name);
        $this->assertTrue((bool) $this->moa->is_active);
        $this->assertTrue((bool) $this->moa->is_required);
        $this->assertSame('student', $this->moa->audience);

        $this->actingAs($this->student->user)->get(route('student.documents', ['form' => $this->moa->id]))->assertOk()
            ->assertSee('Form 10 – Memorandum of Agreement')
            ->assertSee('Upload Scanned MOA')
            ->assertSee('name="files[]"', false)
            ->assertSee(route('student.documents.moa', $this->moa), false);
    }

    public function test_several_files_and_several_submissions_are_allowed(): void
    {
        $this->upload(['moa-page1.pdf', 'moa-page2.pdf'])->assertSessionHasNoErrors();
        $document = Document::sole();
        $this->assertSame('submitted', $document->status);
        $this->assertSame(['moa_scan_1', 'moa_scan_2'], $document->attachments()->orderBy('id')->pluck('kind')->all());

        // Approved, then the student adds another scan: kept, and it goes back for review.
        $this->actingAs($this->instructor)->post(route('instructor.documents.review', $document), ['decision' => 'approved'])->assertRedirect();
        $this->assertSame('approved', $document->fresh()->status);

        $this->upload(['moa-page3.pdf'])->assertSessionHasNoErrors();
        $this->assertSame('submitted', $document->fresh()->status);
        $this->assertSame(3, $document->attachments()->count());
        $this->assertSame('Signed MOA - scan 3', DocumentAttachment::where('kind', 'moa_scan_3')->sole()->label());
    }

    public function test_the_upload_needs_a_pdf_or_image(): void
    {
        $this->actingAs($this->student->user)->post(route('student.documents.moa', $this->moa), [])->assertSessionHasErrors('files');
        $this->actingAs($this->student->user)->post(route('student.documents.moa', $this->moa), [
            'files' => [UploadedFile::fake()->create('virus.exe', 10)],
        ])->assertSessionHasErrors('files.0');
        $this->assertSame(0, Document::count());
    }

    public function test_the_instructor_sees_the_scans_and_the_repository_keeps_them(): void
    {
        $this->upload(['moa-page1.pdf', 'moa-page2.pdf']);
        $document = Document::sole();
        $scan = $document->attachments()->orderBy('id')->first();

        $this->actingAs($this->instructor)->get(route('instructor.documents.show', $document))->assertOk()
            ->assertSee('Signed MOA - scan 1')->assertSee('Signed MOA - scan 2')
            ->assertSee('<iframe src="'.route('instructor.documents.attachment', $scan).'"', false);
        $this->actingAs($this->instructor)->get(route('instructor.documents', ['student' => $this->student->id, 'form' => $this->moa->id]))->assertOk()
            ->assertSee('Signed MOA - scan 2');

        $this->actingAs($this->instructor)->post(route('instructor.documents.review', $document), ['decision' => 'approved']);

        $this->actingAs($this->coordinator)->get(route('coordinator.repository'))->assertOk()->assertSee('2 files');
        $this->actingAs($this->coordinator)->get(route('coordinator.repository', ['student' => $this->student->id]))->assertOk()
            ->assertSeeInOrder(['Form 10 – Memorandum of Agreement', 'Signed MOA - scan 2'])
            ->assertSee('Pre-deployment 1 / 1 passed');
    }

    public function test_the_single_file_upload_path_is_closed_for_the_moa(): void
    {
        $this->actingAs($this->student->user)->post(route('student.documents.store'), [
            'document_requirement_id' => $this->moa->id,
            'file' => UploadedFile::fake()->create('moa.pdf', 10, 'application/pdf'),
        ])->assertStatus(422);
    }
}
