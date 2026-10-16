<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Deployment;
use App\Models\DocumentRequirement;
use App\Models\Hte;
use App\Models\HteListing;
use App\Models\Instructor;
use App\Models\Internship;
use App\Models\Program;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use App\Support\Term;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Partner Agency repository (first step of the internship process):
 * kept per program per academic year + semester by the program's
 * instructor; the coordinator views all programs, edits only their own;
 * FM-AA-INT-06 prints only name + total student-trainees.
 */
class PartnerAgencyRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private Campus $campus;

    private Program $bsit;

    private Program $bshm;

    private array $roles = [];

    private array $details = [
        'name' => 'Hundred Islands Eco Resort',
        'address' => 'Lucap, Alaminos City',
        'contact_person' => 'Mr. Ramon Cruz',
        'representative_position' => 'HR Manager',
        'contact_phone' => '0917 123 4567',
        'contact_email' => 'hr@hier.test',
        'moa_status' => 'active',
        'geofence_radius_m' => 100,
        'max_gps_accuracy_m' => 75,
        'slots' => 5,
    ];

    protected function setUp(): void
    {
        parent::setUp();
        // 2nd Semester of AY 2026-2027
        $this->travelTo(CarbonImmutable::parse('2027-01-15 09:00:00', 'Asia/Manila'));

        foreach (['coordinator', 'instructor', 'student'] as $code) {
            $this->roles[$code] = Role::forceCreate(['code' => $code, 'name' => $code]);
        }
        $this->campus = Campus::forceCreate(['code' => 'ACC', 'name' => 'PSU - Alaminos City Campus', 'status' => 'active']);
        $this->bsit = Program::forceCreate(['campus_id' => $this->campus->id, 'code' => 'BSIT', 'name' => 'Bachelor of Science in Information Technology', 'required_hours' => 486]);
        $this->bshm = Program::forceCreate(['campus_id' => $this->campus->id, 'code' => 'BSHM', 'name' => 'Bachelor of Science in Hospitality Management', 'required_hours' => 600]);
    }

    private function teacher(string $role, string $id, ?Program $program): User
    {
        $user = User::forceCreate([
            'campus_id' => $this->campus->id, 'role_id' => $this->roles[$role]->id, 'id_number' => $id,
            'name' => "{$role} {$id}", 'email' => strtolower($id).'@t.local', 'password' => Hash::make('x'),
            'status' => 'active', 'must_change_password' => false,
        ]);
        Instructor::forceCreate(['user_id' => $user->id, 'campus_id' => $this->campus->id, 'program_id' => $program?->id]);

        return $user->fresh();
    }

    private function listing(Program $program, string $name, string $ay = '2026-2027', string $sem = '2nd Semester', ?int $slots = null): HteListing
    {
        $hte = Hte::firstOrCreate(['campus_id' => $this->campus->id, 'name' => $name], ['address' => 'Somewhere', 'contact_person' => 'Ms. X', 'contact_phone' => '0900', 'moa_status' => 'pending']);

        return HteListing::forceCreate(['campus_id' => $this->campus->id, 'program_id' => $program->id, 'hte_id' => $hte->id, 'academic_year' => $ay, 'semester' => $sem, 'slots' => $slots]);
    }

    private function placedStudent(Program $program, Hte $hte, string $id, string $start = '2027-01-20'): Student
    {
        $user = User::forceCreate([
            'campus_id' => $this->campus->id, 'role_id' => $this->roles['student']->id, 'id_number' => $id,
            'name' => "Student {$id}", 'email' => strtolower($id).'@s.local', 'password' => Hash::make('x'),
            'status' => 'active', 'must_change_password' => false,
        ]);
        $student = Student::forceCreate(['user_id' => $user->id, 'campus_id' => $this->campus->id, 'program_id' => $program->id, 'hours_rendered' => 0]);
        $internship = Internship::forceCreate(['student_id' => $student->id, 'campus_id' => $this->campus->id, 'program_id' => $program->id, 'required_hours' => 486, 'status' => 'deployed']);
        Deployment::forceCreate(['internship_id' => $internship->id, 'hte_id' => $hte->id, 'status' => 'active', 'start_date' => $start, 'end_date' => '2027-04-30']);

        return $student;
    }

    public function test_term_helper(): void
    {
        $this->assertSame(['2026-2027', '2nd Semester'], Term::current());
        $this->assertSame(['2026-2027', '1st Semester'], Term::forDate(CarbonImmutable::parse('2026-12-01')));
        $this->assertSame(['2026-2027', 'Midyear'], Term::forDate(CarbonImmutable::parse('2027-06-15')));
        $this->assertSame(['2026-2027', '1st Semester'], Term::previous('2026-2027', '2nd Semester'));
        $this->assertSame(['2025-2026', 'Midyear'], Term::previous('2026-2027', '1st Semester'));
    }

    public function test_instructor_adds_a_partner_agency_with_full_details_for_their_program(): void
    {
        $instructor = $this->teacher('instructor', 'ACC-INST-001', $this->bsit);

        $this->actingAs($instructor)->post(route('instructor.htes.store'), $this->details + [
            'academic_year' => '2026-2027', 'semester' => '2nd Semester', 'remarks' => 'Accepting IT interns',
            // a program in the request is ignored - it's always the instructor's
            'program_id' => $this->bshm->id,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $listing = HteListing::with('hte')->firstOrFail();
        $this->assertSame($this->bsit->id, $listing->program_id);
        $this->assertSame('2026-2027', $listing->academic_year);
        $this->assertSame(5, $listing->slots);
        $this->assertSame('0917 123 4567', $listing->hte->contact_phone);
        $this->assertSame($instructor->id, $listing->added_by_user_id);
    }

    public function test_details_must_be_complete(): void
    {
        $instructor = $this->teacher('instructor', 'ACC-INST-001', $this->bsit);

        $this->actingAs($instructor)->post(route('instructor.htes.store'), [
            'name' => 'Half-filled Agency', 'academic_year' => '2026-2027', 'semester' => '2nd Semester',
            'moa_status' => 'pending', 'geofence_radius_m' => 100, 'max_gps_accuracy_m' => 75,
        ])->assertSessionHasErrors(['address', 'contact_person', 'representative_position', 'contact_phone', 'slots']);

        $this->assertSame(0, HteListing::count());
    }

    public function test_an_agency_already_on_campus_is_reused_not_duplicated(): void
    {
        $it = $this->teacher('instructor', 'ACC-INST-001', $this->bsit);
        $hm = $this->teacher('instructor', 'ACC-INST-002', $this->bshm);
        $term = ['academic_year' => '2026-2027', 'semester' => '2nd Semester'];

        $this->actingAs($it)->post(route('instructor.htes.store'), $this->details + $term);
        $this->actingAs($hm)->post(route('instructor.htes.store'), $this->details + $term);

        $this->assertSame(1, Hte::count());
        $this->assertSame(2, HteListing::count());

        // Same agency twice for the same program and term is refused.
        $this->actingAs($it)->post(route('instructor.htes.store'), $this->details + $term)->assertSessionHasErrors('name');
        $this->assertSame(2, HteListing::count());
    }

    public function test_instructor_sees_only_their_program_coordinator_sees_all(): void
    {
        $coordinator = $this->teacher('coordinator', 'ACC-COORD-001', $this->bsit);
        $hm = $this->teacher('instructor', 'ACC-INST-002', $this->bshm);
        $this->listing($this->bsit, 'IT Solutions Inc.');
        $this->listing($this->bshm, 'Lingayen Gulf Hotel');
        $this->listing($this->bshm, 'Old Term Hotel', '2025-2026', '2nd Semester');

        $this->actingAs($hm)->get(route('instructor.htes'))->assertOk()
            ->assertSee('Lingayen Gulf Hotel')->assertDontSee('IT Solutions Inc.')->assertDontSee('Old Term Hotel');

        $this->actingAs($coordinator)->get(route('instructor.htes'))->assertOk()
            ->assertSee('Lingayen Gulf Hotel')->assertSee('IT Solutions Inc.');

        // Other terms are a filter away.
        $this->actingAs($hm)->get(route('instructor.htes', ['academic_year' => '2025-2026', 'semester' => '2nd Semester']))
            ->assertSee('Old Term Hotel')->assertDontSee('Lingayen Gulf Hotel');

        // An instructor can't peek at another program by passing program_id.
        $this->actingAs($hm)->get(route('instructor.htes', ['program_id' => $this->bsit->id]))->assertDontSee('IT Solutions Inc.');
    }

    public function test_coordinator_edits_only_their_own_programs_entries(): void
    {
        $coordinator = $this->teacher('coordinator', 'ACC-COORD-001', $this->bsit);
        $own = $this->listing($this->bsit, 'IT Solutions Inc.');
        $other = $this->listing($this->bshm, 'Lingayen Gulf Hotel');

        $this->actingAs($coordinator)->get(route('instructor.htes.edit', $other))->assertForbidden();
        $this->actingAs($coordinator)->put(route('instructor.htes.update', $other), $this->details)->assertForbidden();
        $this->actingAs($coordinator)->delete(route('instructor.htes.destroy', $other))->assertForbidden();

        $this->actingAs($coordinator)->get(route('instructor.htes.edit', $own))->assertOk();
        $this->actingAs($coordinator)->put(route('instructor.htes.update', $own), ['name' => 'IT Solutions Inc.', 'slots' => 3] + $this->details)
            ->assertRedirect();
        $this->assertSame(3, $own->fresh()->slots);
    }

    public function test_intern_count_follows_the_program_and_term(): void
    {
        $hm = $this->teacher('instructor', 'ACC-INST-002', $this->bshm);
        $listing = $this->listing($this->bshm, 'Lingayen Gulf Hotel');
        $this->placedStudent($this->bshm, $listing->hte, '23-AC-0001');
        $this->placedStudent($this->bshm, $listing->hte, '23-AC-0002');
        $this->placedStudent($this->bsit, $listing->hte, '23-AC-0003');             // other program
        $this->placedStudent($this->bshm, $listing->hte, '23-AC-0004', '2026-09-01'); // last semester

        $counts = \App\Http\Controllers\Instructor\HteController::internCounts($this->campus->id, '2026-2027', '2nd Semester');
        $this->assertSame(2, $counts[$this->bshm->id.'|'.$listing->hte_id]);

        // A listing with interns this term can't be removed.
        $this->actingAs($hm)->delete(route('instructor.htes.destroy', $listing))->assertSessionHasErrors('listing');
        $this->assertModelExists($listing);
    }

    public function test_masterlist_prints_only_name_and_total(): void
    {
        $hm = $this->teacher('instructor', 'ACC-INST-002', $this->bshm);
        $listing = $this->listing($this->bshm, 'Lingayen Gulf Hotel');
        $listing->hte->update(['address' => 'Secret Street 99', 'contact_phone' => '0999-SECRET']);
        $this->listing($this->bsit, 'IT Solutions Inc.');
        $this->placedStudent($this->bshm, $listing->hte, '23-AC-0001');

        $response = $this->actingAs($hm)->get(route('instructor.reports.partner-industries', ['academic_year' => '2026-2027', 'semester' => '2nd Semester']))
            ->assertOk()
            ->assertSee('FM-AA-INT-06')
            ->assertSee('Lingayen Gulf Hotel')
            ->assertSee('2nd Semester')
            ->assertSee('2026-2027')
            ->assertDontSee('IT Solutions Inc.')
            ->assertDontSee('Secret Street 99')
            ->assertDontSee('0999-SECRET');

        $this->assertMatchesRegularExpression('#Lingayen Gulf Hotel</td>\s*<td class="num">1</td>#', $response->getContent());
    }

    public function test_coordinator_masterlist_for_all_programs_adds_up_shared_agencies(): void
    {
        $coordinator = $this->teacher('coordinator', 'ACC-COORD-001', $this->bsit);
        $a = $this->listing($this->bsit, 'Shared Agency');
        $this->listing($this->bshm, 'Shared Agency');
        $this->placedStudent($this->bsit, $a->hte, '23-AC-0001');
        $this->placedStudent($this->bshm, $a->hte, '23-AC-0002');

        $html = $this->actingAs($coordinator)->get(route('instructor.reports.partner-industries', ['academic_year' => '2026-2027', 'semester' => '2nd Semester']))
            ->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'Shared Agency'));
        $this->assertMatchesRegularExpression('#Shared Agency</td>\s*<td class="num">2</td>#', $html);
    }

    public function test_copy_last_terms_list(): void
    {
        $hm = $this->teacher('instructor', 'ACC-INST-002', $this->bshm);
        $this->listing($this->bshm, 'Hotel A', '2026-2027', '1st Semester');
        $this->listing($this->bshm, 'Hotel B', '2026-2027', '1st Semester');
        $this->listing($this->bshm, 'Hotel B');                                   // already this term
        $this->listing($this->bsit, 'IT Co', '2026-2027', '1st Semester');        // other program

        $this->actingAs($hm)->post(route('instructor.htes.copy'), ['academic_year' => '2026-2027', 'semester' => '2nd Semester'])
            ->assertRedirect();

        $names = HteListing::with('hte')->where('program_id', $this->bshm->id)->forTerm('2026-2027', '2nd Semester')->get()->pluck('hte.name')->sort()->values()->all();
        $this->assertSame(['Hotel A', 'Hotel B'], $names);
    }

    public function test_student_application_offers_only_their_programs_agencies_for_the_term(): void
    {
        $this->listing($this->bshm, 'Lingayen Gulf Hotel');
        $this->listing($this->bsit, 'IT Solutions Inc.');
        $this->listing($this->bshm, 'Old Term Hotel', '2025-2026', '2nd Semester');

        $requirement = DocumentRequirement::forceCreate([
            'campus_id' => $this->campus->id, 'code' => 'APPLICATION_FOR_OJT', 'name' => 'Form 1', 'stage' => 'pre_deployment',
            'audience' => 'student', 'workflow_mode' => 'print_sign', 'is_active' => true, 'is_required' => true, 'display_order' => 1,
        ]);
        \App\Models\DocumentFieldDefinition::forceCreate([
            'document_requirement_id' => $requirement->id, 'field_key' => 'hte_name', 'label' => 'Name of the Firm',
            'field_type' => 'text', 'sort_order' => 1, 'is_required' => true,
        ]);

        $user = User::forceCreate([
            'campus_id' => $this->campus->id, 'role_id' => $this->roles['student']->id, 'id_number' => '23-AC-0010',
            'name' => 'Stu', 'email' => 'stu@s.local', 'password' => Hash::make('x'), 'status' => 'active', 'must_change_password' => false,
        ]);
        $student = Student::forceCreate(['user_id' => $user->id, 'campus_id' => $this->campus->id, 'program_id' => $this->bshm->id, 'hours_rendered' => 0]);
        Internship::forceCreate(['student_id' => $student->id, 'campus_id' => $this->campus->id, 'program_id' => $this->bshm->id, 'required_hours' => 600, 'status' => 'pre_deployment']);

        $this->actingAs($user)->get(route('student.documents.fill', $requirement))->assertOk()
            ->assertSee('Lingayen Gulf Hotel')
            ->assertDontSee('IT Solutions Inc.')
            ->assertDontSee('Old Term Hotel');
    }

    public function test_old_coordinator_hte_pages_point_to_partner_agencies(): void
    {
        $coordinator = $this->teacher('coordinator', 'ACC-COORD-001', $this->bsit);

        $this->actingAs($coordinator)->get('/coordinator/htes')->assertRedirect('/instructor/partner-agencies');
        $this->actingAs($coordinator)->get(route('instructor.htes.create'))->assertOk()->assertSee('Add partner agency');
    }

    // ---------- slots ----------

    private function unplacedStudent(Program $program, string $id): Student
    {
        $user = User::forceCreate([
            'campus_id' => $this->campus->id, 'role_id' => $this->roles['student']->id, 'id_number' => $id,
            'name' => "Student {$id}", 'email' => strtolower($id).'@s.local', 'password' => Hash::make('x'),
            'status' => 'active', 'must_change_password' => false,
        ]);
        $student = Student::forceCreate(['user_id' => $user->id, 'campus_id' => $this->campus->id, 'program_id' => $program->id, 'hours_rendered' => 0]);
        Internship::forceCreate(['student_id' => $student->id, 'campus_id' => $this->campus->id, 'program_id' => $program->id, 'required_hours' => 600, 'status' => 'pre_deployment']);

        return $student->fresh();
    }

    private function application(Student $student, string $hteName): \App\Models\Document
    {
        $requirement = DocumentRequirement::firstOrCreate(
            ['campus_id' => $this->campus->id, 'code' => 'APPLICATION_FOR_OJT'],
            ['name' => 'Form 1', 'stage' => 'pre_deployment', 'audience' => 'student', 'workflow_mode' => 'print_sign', 'is_active' => true, 'is_required' => true, 'display_order' => 1]
        );
        $document = \App\Models\Document::forceCreate([
            'internship_id' => $student->currentInternship->id, 'document_requirement_id' => $requirement->id,
            'uploaded_by_user_id' => $student->user_id, 'status' => 'submitted', 'submitted_at' => now(),
        ]);
        \App\Models\DocumentFieldValue::forceCreate(['document_id' => $document->id, 'field_key' => 'hte_name', 'value' => $hteName]);

        return $document;
    }

    public function test_page_warns_when_existing_slots_are_not_enough(): void
    {
        $hm = $this->teacher('instructor', 'ACC-INST-002', $this->bshm);
        $hotel = $this->listing($this->bshm, 'Lingayen Gulf Hotel', '2026-2027', '2nd Semester', 2);
        $this->placedStudent($this->bshm, $hotel->hte, '23-AC-0001');          // 1 of 2 taken -> 1 open
        foreach (['23-AC-0010', '23-AC-0011', '23-AC-0012'] as $id) {
            $this->unplacedStudent($this->bshm, $id);                          // 3 still to place
        }

        $this->actingAs($hm)->get(route('instructor.htes'))->assertOk()
            ->assertSee('Not enough slots')
            ->assertSee('gather 2 more slots')
            ->assertSee('Add another partner agency');

        // After adding a new partner with enough slots, the warning goes away.
        $this->actingAs($hm)->post(route('instructor.htes.store'), ['name' => 'New Partner Resort', 'slots' => 3] + $this->details + [
            'academic_year' => '2026-2027', 'semester' => '2nd Semester',
        ])->assertSessionHasNoErrors();

        $this->actingAs($hm)->get(route('instructor.htes'))->assertDontSee('Not enough slots')->assertSee('New Partner Resort');
    }

    public function test_a_full_agency_is_marked_and_cannot_be_chosen_or_accepted(): void
    {
        $hm = $this->teacher('instructor', 'ACC-INST-002', $this->bshm);
        $full = $this->listing($this->bshm, 'Full Hotel', '2026-2027', '2nd Semester', 1);
        $open = $this->listing($this->bshm, 'Open Resort', '2026-2027', '2nd Semester', 3);
        $this->placedStudent($this->bshm, $full->hte, '23-AC-0001');

        $this->actingAs($hm)->get(route('instructor.htes'))->assertSee('Full');

        // Student form: full agency disabled, open one shows its free slots.
        $student = $this->unplacedStudent($this->bshm, '23-AC-0020');
        $requirementDoc = $this->application($student, $full->hte->name);
        \App\Models\DocumentFieldDefinition::forceCreate([
            'document_requirement_id' => $requirementDoc->document_requirement_id, 'field_key' => 'hte_name',
            'label' => 'Name of the Firm', 'field_type' => 'text', 'sort_order' => 1, 'is_required' => true,
        ]);
        $requirementDoc->fieldValues()->delete(); // student hasn't chosen yet
        $html = $this->actingAs($student->user)->get(route('student.documents.fill', $requirementDoc->document_requirement_id))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<option value="Full Hotel"\s+disabled#', $html);
        $this->assertStringContainsString('3 of 3 slots open', $html);

        // Accepting an application for the full agency is refused before printing.
        \App\Models\DocumentFieldValue::forceCreate(['document_id' => $requirementDoc->id, 'field_key' => 'hte_name', 'value' => 'Full Hotel']);
        $this->actingAs($hm)->post(route('instructor.documents.print', $requirementDoc))->assertSessionHasErrors('placement');
        $this->assertSame('submitted', $requirementDoc->fresh()->status);
        $this->assertNull($student->fresh()->currentInternship->deployment);

        // The open one is fine.
        $requirementDoc->fieldValues()->where('field_key', 'hte_name')->update(['value' => 'Open Resort']);
        $this->actingAs($hm)->post(route('instructor.documents.print', $requirementDoc))->assertSessionHasNoErrors();
        $this->assertSame($open->hte_id, $student->fresh()->currentInternship->deployment->hte_id);
    }
}
