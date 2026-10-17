<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Document;
use App\Models\DocumentFieldDefinition;
use App\Models\DocumentRequirement;
use App\Models\Hte;
use App\Models\Internship;
use App\Models\Program;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Form 15 posts the chosen partner agency as hte_id; the company name and
 * address are filled in from the HTE record.
 */
class Form15SubmitTest extends TestCase
{
    use RefreshDatabase;

    private DocumentRequirement $form15;

    private User $user;

    private Hte $hte;

    protected function setUp(): void
    {
        parent::setUp();
        $role = Role::forceCreate(['code' => 'student', 'name' => 'student']);
        $campus = Campus::forceCreate(['code' => 'ACC', 'name' => 'PSU - Alaminos City Campus', 'status' => 'active']);
        $program = Program::forceCreate(['campus_id' => $campus->id, 'code' => 'BSIT', 'name' => 'Bachelor of Science in Information Technology', 'required_hours' => 486]);
        $this->hte = Hte::forceCreate(['campus_id' => $campus->id, 'name' => 'FAREHEAVEN BOARDING HOUSE', 'address' => 'Bolaney, Alaminos City, Pangasinan']);

        // Listed for the program this term, as in Partner Agencies.
        [$academicYear, $semester] = \App\Support\Term::current();
        \App\Models\HteListing::forceCreate(['campus_id' => $campus->id, 'program_id' => $program->id, 'hte_id' => $this->hte->id, 'academic_year' => $academicYear, 'semester' => $semester, 'slots' => 5]);

        $this->form15 = DocumentRequirement::forceCreate([
            'campus_id' => $campus->id, 'code' => 'INTERNSHIP_RELEASE_FORM', 'name' => 'Form 15 – Internship Release Form', 'form_number' => '15',
            'stage' => 'pre_deployment', 'audience' => 'student', 'workflow_mode' => 'print_sign', 'is_active' => true, 'is_required' => true, 'display_order' => 110,
        ]);
        foreach ([['course', true], ['major', false], ['company_name', true], ['company_address', true], ['start_date', true], ['end_date', true], ['required_hours', true]] as $n => [$key, $req]) {
            DocumentFieldDefinition::forceCreate(['document_requirement_id' => $this->form15->id, 'field_key' => $key, 'label' => $key, 'field_type' => 'text', 'is_required' => $req, 'sort_order' => $n]);
        }

        $this->user = User::forceCreate([
            'campus_id' => $campus->id, 'role_id' => $role->id, 'id_number' => '23-AC-0094', 'name' => 'Lucy Ro',
            'email' => 'lucy@t.local', 'password' => Hash::make('x'), 'status' => 'active', 'must_change_password' => false,
        ]);
        $student = Student::forceCreate(['user_id' => $this->user->id, 'campus_id' => $campus->id, 'program_id' => $program->id, 'hours_rendered' => 0]);
        Internship::forceCreate(['student_id' => $student->id, 'campus_id' => $campus->id, 'program_id' => $program->id, 'required_hours' => 486, 'status' => 'pre_deployment']);
    }

    private function payload(array $extra = []): array
    {
        return $extra + [
            'course' => 'Bachelor of Science in Information Technology', 'major' => 'N/A',
            'hte_id' => $this->hte->id, 'company_address' => 'Bolaney, Alaminos City, Pangasinan',
            'start_date' => '2026-10-15', 'end_date' => '2026-10-20', 'required_hours' => '486',
        ];
    }

    public function test_form15_submits_with_the_chosen_partner_agency(): void
    {
        $this->actingAs($this->user)->post(route('student.documents.fill.store', $this->form15), $this->payload())
            ->assertSessionHasNoErrors()->assertRedirect();

        $document = Document::sole();
        $this->assertSame('submitted', $document->status);
        $this->assertSame('FAREHEAVEN BOARDING HOUSE', $document->fieldValue('company_name'));
        $this->assertSame('Bolaney, Alaminos City, Pangasinan', $document->fieldValue('company_address'));
        $this->assertNull($document->fieldValue('hte_id'));

        // Reopened after a return, the same agency is selected again.
        $document->update(['status' => 'returned']);
        $this->actingAs($this->user)->get(route('student.documents.fill', $this->form15))->assertOk()
            ->assertSee('FAREHEAVEN BOARDING HOUSE')
            ->assertSeeInOrder(['value="'.$this->hte->id.'"', 'selected'], false);
    }

    public function test_the_partner_agency_is_required_and_must_exist(): void
    {
        $this->actingAs($this->user)->post(route('student.documents.fill.store', $this->form15), $this->payload(['hte_id' => '']))
            ->assertSessionHasErrors(['hte_id' => 'Select your cooperating company/agency/firm.']);
        $this->actingAs($this->user)->post(route('student.documents.fill.store', $this->form15), $this->payload(['hte_id' => 999]))
            ->assertSessionHasErrors('hte_id');
        $this->assertSame(0, Document::count());
    }
}
