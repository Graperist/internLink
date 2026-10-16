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

    private function listing(Program $program, string $name, string $ay = '2026-2027', string $sem = '2nd Semester'): HteListing
    {
        $hte = Hte::firstOrCreate(['campus_id' => $this->campus->id, 'name' => $name], ['address' => 'Somewhere', 'contact_person' => 'Ms. X', 'contact_phone' => '0900', 'moa_status' => 'pending']);

        return HteListing::forceCreate(['campus_id' => $this->campus->id, 'program_id' => $program->id, 'hte_id' => $hte->id, 'academic_year' => $ay, 'semester' => $sem]);
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
            'academic_year' => '2026-2027', 'semester' => '2nd Semester', 'slots' => 5, 'remarks' => 'Accepting IT interns',
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
        ])->assertSessionHasErrors(['address', 'contact_person', 'representative_position', 'contact_phone']);

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
        $this->actingAs($coordinator)->put(route('instructor.htes.update', $own), ['name' => 'IT Solutions Inc.'] + $this->details + ['slots' => 3])
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
}
