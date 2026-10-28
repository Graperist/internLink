<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\DocumentRequirement;
use App\Models\Internship;
use App\Models\Program;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** My Documents lists the forms by form number: 1, 2, 3, 6, 11, 12, 15, 18. */
class StudentFormOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_forms_are_listed_by_form_number_without_a_repeated_label(): void
    {
        $role = Role::forceCreate(['code' => 'student', 'name' => 'student']);
        $campus = Campus::forceCreate(['code' => 'ACC', 'name' => 'PSU - Alaminos City Campus', 'status' => 'active']);
        $program = Program::forceCreate(['campus_id' => $campus->id, 'code' => 'BSIT', 'name' => 'BSIT', 'required_hours' => 486]);

        // Created out of order, with display orders that disagree, as on a
        // database seeded before the forms were renumbered.
        $forms = [
            ['18', 'Training Agreement and Liability Waiver', 5], ['1', 'Application for Internship', 1],
            ['11', 'Internship Resume', 2], ['6', 'Endorsement Letter', 8],
            ['3', 'Consent and Waiver', 7], ['12', 'Internship Agreement', 3],
            ['2', 'Certification of Units Earned for Practicum/Internship', 6], ['15', 'Internship Release Form', 4],
        ];
        foreach ($forms as [$number, $title, $order]) {
            DocumentRequirement::forceCreate([
                'campus_id' => $campus->id, 'code' => 'F'.$number, 'name' => "Form {$number} – {$title}", 'form_number' => $number,
                'stage' => 'pre_deployment', 'audience' => 'student', 'workflow_mode' => 'upload',
                'is_active' => true, 'is_required' => true, 'display_order' => $order,
            ]);
        }

        $user = User::forceCreate([
            'campus_id' => $campus->id, 'role_id' => $role->id, 'id_number' => '23-AC-0094', 'name' => 'Lucy Ro',
            'email' => 'lucy@t.local', 'password' => Hash::make('x'), 'status' => 'active', 'must_change_password' => false,
        ]);
        $student = Student::forceCreate(['user_id' => $user->id, 'campus_id' => $campus->id, 'program_id' => $program->id, 'hours_rendered' => 0]);
        Internship::forceCreate(['student_id' => $student->id, 'campus_id' => $campus->id, 'program_id' => $program->id, 'required_hours' => 486, 'status' => 'pre_deployment']);

        $response = $this->actingAs($user)->get(route('student.documents'))->assertOk();

        $response->assertSeeInOrder([
            'Form 1 – Application', 'Form 2 – Certification', 'Form 3 – Consent', 'Form 6 – Endorsement',
            'Form 11 – Internship Resume', 'Form 12 – Internship Agreement', 'Form 15 – Internship Release', 'Form 18 – Training',
        ]);
        $this->assertStringNotContainsString('<span class="text-secondary me-1">Form 1</span>', $response->getContent());
    }

    public function test_the_endorsement_letter_is_renamed(): void
    {
        $campus = Campus::forceCreate(['code' => 'ACC', 'name' => 'PSU - Alaminos City Campus', 'status' => 'active']);
        $form6 = DocumentRequirement::forceCreate([
            'campus_id' => $campus->id, 'code' => 'ENDORSEMENT_LETTER', 'name' => 'Form 6 – Endorsement Letter (Rev. 2, 2024)', 'form_number' => '6',
            'stage' => 'pre_deployment', 'audience' => 'student', 'workflow_mode' => 'print_sign', 'is_active' => true, 'is_required' => true, 'display_order' => 70,
        ]);

        (require database_path('migrations/2026_10_16_500000_rename_endorsement_letter.php'))->up();

        $this->assertSame('Form 6 – Endorsement Letter', $form6->fresh()->name);
    }

    public function test_stages_go_pre_deployment_then_active_then_post_deployment(): void
    {
        $role = Role::forceCreate(['code' => 'student', 'name' => 'student']);
        $campus = Campus::forceCreate(['code' => 'ACC', 'name' => 'PSU - Alaminos City Campus', 'status' => 'active']);
        $program = Program::forceCreate(['campus_id' => $campus->id, 'code' => 'BSIT', 'name' => 'BSIT', 'required_hours' => 486]);
        foreach ([['NARRATIVE_REPORT', 'Narrative Report', null, 'post_deployment'], ['WEEKLY_REPORT', 'Form 19 – Weekly Report', '19', 'active'],
                  ['APPLICATION_FOR_OJT', 'Form 1 – Application for Internship', '1', 'pre_deployment'], ['STUDENT_FEEDBACK_FORM', 'Form 20 – Feedback', '20', 'post_deployment']] as [$code, $name, $number, $stage]) {
            DocumentRequirement::forceCreate([
                'campus_id' => $campus->id, 'code' => $code, 'name' => $name, 'form_number' => $number, 'stage' => $stage,
                'audience' => 'student', 'workflow_mode' => 'upload', 'is_active' => true, 'is_required' => true, 'display_order' => 1,
            ]);
        }
        $user = User::forceCreate([
            'campus_id' => $campus->id, 'role_id' => $role->id, 'id_number' => '23-AC-0093', 'name' => 'Jasmine',
            'email' => 'j@t.local', 'password' => Hash::make('x'), 'status' => 'active', 'must_change_password' => false,
        ]);
        $student = Student::forceCreate(['user_id' => $user->id, 'campus_id' => $campus->id, 'program_id' => $program->id, 'hours_rendered' => 0]);
        // Completed, so every stage is listed.
        $internship = Internship::forceCreate(['student_id' => $student->id, 'campus_id' => $campus->id, 'program_id' => $program->id, 'required_hours' => 486, 'status' => 'completed']);
        $hte = \App\Models\Hte::forceCreate(['campus_id' => $campus->id, 'name' => 'Hundred Islands Resort']);
        \App\Models\Deployment::forceCreate(['internship_id' => $internship->id, 'hte_id' => $hte->id, 'status' => 'completed']);

        $this->actingAs($user)->get(route('student.documents'))->assertOk()
            ->assertSeeInOrder(['Form 1 – Application', 'Form 19 – Weekly Report', 'Narrative Report'])
            ->assertDontSee('Form 20 – Feedback');   // an evaluation: on My HTE, not My Documents
    }

    public function test_my_documents_shows_the_list_and_one_open_form(): void
    {
        \Illuminate\Support\Facades\Storage::fake();
        $role = Role::forceCreate(['code' => 'student', 'name' => 'student']);
        $campus = Campus::forceCreate(['code' => 'ACC', 'name' => 'PSU - Alaminos City Campus', 'status' => 'active']);
        $program = Program::forceCreate(['campus_id' => $campus->id, 'code' => 'BSIT', 'name' => 'BSIT', 'required_hours' => 486]);
        $make = fn ($code, $name, $number) => DocumentRequirement::forceCreate([
            'campus_id' => $campus->id, 'code' => $code, 'name' => $name, 'form_number' => $number, 'stage' => 'pre_deployment',
            'audience' => 'student', 'workflow_mode' => 'upload', 'is_active' => true, 'is_required' => true, 'display_order' => 1,
        ]);
        $form1 = $make('F1', 'Form 1 – Application for Internship', '1');
        $form2 = $make('F2', 'Form 2 – Certification of Units', '2');
        $form3 = $make('F3', 'Form 3 – Consent and Waiver', '3');
        $user = User::forceCreate([
            'campus_id' => $campus->id, 'role_id' => $role->id, 'id_number' => '23-AC-0093', 'name' => 'Jasmine',
            'email' => 'j@t.local', 'password' => Hash::make('x'), 'status' => 'active', 'must_change_password' => false,
        ]);
        $student = Student::forceCreate(['user_id' => $user->id, 'campus_id' => $campus->id, 'program_id' => $program->id, 'hours_rendered' => 0]);
        $internship = Internship::forceCreate(['student_id' => $student->id, 'campus_id' => $campus->id, 'program_id' => $program->id, 'required_hours' => 486, 'status' => 'pre_deployment']);

        \Illuminate\Support\Facades\Storage::put('f/form1.pdf', 'pdf');
        $doc1 = \App\Models\Document::forceCreate(['internship_id' => $internship->id, 'document_requirement_id' => $form1->id, 'status' => 'approved', 'file_path' => 'f/form1.pdf', 'original_filename' => 'form1.pdf', 'submitted_at' => now()]);
        $doc2 = \App\Models\Document::forceCreate(['internship_id' => $internship->id, 'document_requirement_id' => $form2->id, 'status' => 'returned', 'submitted_at' => now()]);
        $instRole = Role::forceCreate(['code' => 'instructor', 'name' => 'instructor']);
        $instUser = User::forceCreate([
            'campus_id' => $campus->id, 'role_id' => $instRole->id, 'id_number' => 'ACC-INST-001', 'name' => 'Inst',
            'email' => 'i@t.local', 'password' => Hash::make('x'), 'status' => 'active', 'must_change_password' => false,
        ]);
        $instructor = \App\Models\Instructor::forceCreate(['user_id' => $instUser->id, 'campus_id' => $campus->id, 'program_id' => $program->id]);
        \App\Models\DocumentReview::forceCreate(['document_id' => $doc2->id, 'instructor_id' => $instructor->id, 'decision' => 'returned', 'remarks' => 'Wrong school year', 'reviewed_at' => now()]);

        // Opens on the first form that needs the student: the returned Form 2.
        $this->actingAs($user)->get(route('student.documents'))->assertOk()
            ->assertSeeInOrder(['Form 1 – Application', 'Form 2 – Certification', 'Form 3 – Consent'])
            ->assertSee('Returned for correction:')->assertSee('Wrong school year')
            ->assertSee('Upload Corrected File');

        // Opening Form 1 lists its file, without a picture preview of the upload.
        $this->actingAs($user)->get(route('student.documents', ['form' => $form1->id]))->assertOk()
            ->assertSee('form1.pdf')
            ->assertDontSee('<iframe', false)
            ->assertSee('Approved. Nothing else to do on this form.');
        $this->actingAs($user)->get(route('student.documents.view', $doc1))->assertOk();

        // Another student can't open it.
        $other = User::forceCreate([
            'campus_id' => $campus->id, 'role_id' => $role->id, 'id_number' => '23-AC-0094', 'name' => 'Lucy',
            'email' => 'l@t.local', 'password' => Hash::make('x'), 'status' => 'active', 'must_change_password' => false,
        ]);
        Student::forceCreate(['user_id' => $other->id, 'campus_id' => $campus->id, 'program_id' => $program->id, 'hours_rendered' => 0]);
        $this->actingAs($other)->get(route('student.documents.view', $doc1))->assertForbidden();
    }
}
