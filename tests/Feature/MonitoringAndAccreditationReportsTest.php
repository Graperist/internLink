<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Deployment;
use App\Models\Hte;
use App\Models\HteListing;
use App\Models\Instructor;
use App\Models\Internship;
use App\Models\Program;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Generate Reports: FM-AA-INT-16 Site Monitoring Report and FM-AA-INT-24 Accreditation Instrument. */
class MonitoringAndAccreditationReportsTest extends TestCase
{
    use RefreshDatabase;

    private array $roles = [];

    private Campus $campus;

    private Program $bsit;

    private Program $bshm;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['coordinator', 'instructor', 'student'] as $code) {
            $this->roles[$code] = Role::forceCreate(['code' => $code, 'name' => $code]);
        }
        $this->campus = Campus::forceCreate(['code' => 'ACC', 'name' => 'PSU - Alaminos City Campus', 'status' => 'active',
            'ced_name' => 'Dr. Ced Name', 'college_dean_name' => 'Dr. Dean Name']);
        $this->bsit = Program::forceCreate(['campus_id' => $this->campus->id, 'code' => 'BSIT', 'name' => 'BSIT', 'required_hours' => 486]);
        $this->bshm = Program::forceCreate(['campus_id' => $this->campus->id, 'code' => 'BSHM', 'name' => 'BSHM', 'required_hours' => 600]);
    }

    private function teacher(string $role, string $id, Program $program): User
    {
        $user = User::forceCreate(['campus_id' => $this->campus->id, 'role_id' => $this->roles[$role]->id, 'id_number' => $id,
            'name' => "Teacher {$id}", 'email' => strtolower($id).'@t.local', 'password' => Hash::make('x'), 'status' => 'active', 'must_change_password' => false]);
        Instructor::forceCreate(['user_id' => $user->id, 'campus_id' => $this->campus->id, 'program_id' => $program->id]);

        return $user->fresh();
    }

    private function agency(Program $program, string $name): Hte
    {
        $hte = Hte::forceCreate(['campus_id' => $this->campus->id, 'name' => $name, 'address' => "{$name} Street", 'moa_status' => 'active']);
        HteListing::forceCreate(['campus_id' => $this->campus->id, 'program_id' => $program->id, 'hte_id' => $hte->id, 'academic_year' => '2026-2027', 'semester' => '2nd Semester', 'slots' => 5]);

        return $hte;
    }

    private function place(Program $program, Hte $hte, string $id): void
    {
        $user = User::forceCreate(['campus_id' => $this->campus->id, 'role_id' => $this->roles['student']->id, 'id_number' => $id,
            'name' => "Student {$id}", 'email' => strtolower($id).'@s.local', 'password' => Hash::make('x'), 'status' => 'active', 'must_change_password' => false]);
        $student = Student::forceCreate(['user_id' => $user->id, 'campus_id' => $this->campus->id, 'program_id' => $program->id, 'hours_rendered' => 0]);
        $internship = Internship::forceCreate(['student_id' => $student->id, 'campus_id' => $this->campus->id, 'program_id' => $program->id, 'required_hours' => 486, 'status' => 'deployed']);
        Deployment::forceCreate(['internship_id' => $internship->id, 'hte_id' => $hte->id, 'status' => 'active', 'start_date' => '2027-01-20', 'end_date' => '2027-04-30']);
    }

    public function test_both_forms_are_on_generate_reports(): void
    {
        $instructor = $this->teacher('instructor', 'ACC-INST-001', $this->bsit);
        $this->agency($this->bsit, 'BSIT Agency');
        $this->agency($this->bshm, 'BSHM Agency');

        $this->actingAs($instructor)->get(route('instructor.reports'))->assertOk()
            ->assertSeeInOrder(['FM-AA-INT-09', 'FM-AA-INT-06', 'FM-AA-INT-05', 'FM-AA-INT-16', 'FM-AA-INT-24'])
            ->assertSee(route('instructor.reports.site-monitoring'), false)
            ->assertSee(route('instructor.reports.accreditation'), false)
            ->assertSee('BSIT Agency')->assertDontSee('BSHM Agency');   // only their program's agencies
    }

    public function test_site_monitoring_report_lists_the_trainees_of_the_company(): void
    {
        $instructor = $this->teacher('instructor', 'ACC-INST-001', $this->bsit);
        $coordinator = User::forceCreate(['campus_id' => $this->campus->id, 'role_id' => $this->roles['coordinator']->id, 'id_number' => 'ACC-COORD-001',
            'name' => 'Coord Name', 'email' => 'c@t.local', 'password' => Hash::make('x'), 'status' => 'active', 'must_change_password' => false]);
        $acme = $this->agency($this->bsit, 'Acme Corp');
        $other = $this->agency($this->bsit, 'Other Corp');
        $this->place($this->bsit, $acme, '23-AC-0001');
        $this->place($this->bsit, $acme, '23-AC-0002');
        $this->place($this->bsit, $other, '23-AC-0003');
        $this->place($this->bshm, $acme, '23-AC-0009');   // other program

        $this->actingAs($instructor)->get(route('instructor.reports.site-monitoring', ['hte_id' => $acme->id, 'monitoring_date' => '2027-02-10']))->assertOk()
            ->assertSee('FM-AA-INT-16')->assertSee('Internship Site Monitoring Report')
            ->assertSee('Acme Corp')->assertSee('Acme Corp Street')->assertSee('February 10, 2027')
            ->assertSee('Student 23-AC-0001')->assertSee('Student 23-AC-0002')
            ->assertDontSee('Student 23-AC-0003')->assertDontSee('Student 23-AC-0009')
            ->assertSeeInOrder(["Training Supervisor's Feedback", 'Attendance and Punctuality', 'Performance', 'General Attitude',
                "Student-Trainee's Feedback", 'Training', 'Working Condition', "Supervisor/Employer's Treatment",
                'Problems Met and Actions Taken', 'Problems Met', 'Actions Taken'], false)
            ->assertSee('Teacher ACC-INST-001')->assertSee('Coord Name')->assertSee('Dr. Ced Name');

        // All companies: one page each.
        $html = $this->actingAs($instructor)->get(route('instructor.reports.site-monitoring'))->assertOk()->getContent();
        $this->assertSame(2, substr_count($html, '<div class="page">'));

        // Not their trainees' company.
        $this->actingAs($instructor)->get(route('instructor.reports.site-monitoring', ['hte_id' => 999]))->assertNotFound();
    }

    public function test_accreditation_instrument_for_an_agency_or_blank(): void
    {
        $instructor = $this->teacher('instructor', 'ACC-INST-001', $this->bsit);
        $mine = $this->agency($this->bsit, 'Hundred Islands Eco Resort');
        $theirs = $this->agency($this->bshm, 'Lingayen Gulf Hotel');

        $this->actingAs($instructor)->get(route('instructor.reports.accreditation', ['hte_id' => $mine->id]))->assertOk()
            ->assertSee('FM-AA-INT-24')->assertSee('Accreditation Instrument of PSU Partner Agencies as Host Training Establishment')
            ->assertSee('Hundred Islands Eco Resort')
            ->assertSeeInOrder(['COMPLIANT', 'NON-', 'REMARKS', '1. A legitimate business entity', '3. In operation for a minimum of five (5) years.', '8. Known to be a reputable'], false)
            ->assertSeeInOrder(['Prepared by:', 'Teacher ACC-INST-001', 'Internship Instructor', 'Reviewed by:', 'Campus Internship Coordinator',
                'Recommended:', 'Dr. Dean Name', 'College Dean', 'Approved:', 'Dr. Ced Name', 'Campus Executive Director'], false);

        $this->actingAs($instructor)->get(route('instructor.reports.accreditation'))->assertOk()
            ->assertSee('8. Known to be a reputable')->assertDontSee('Name of Partner Agency');
        $this->actingAs($instructor)->get(route('instructor.reports.accreditation', ['hte_id' => $theirs->id]))->assertNotFound();

        // The coordinator can print it for any agency of the campus.
        $coordinator = $this->teacher('coordinator', 'ACC-COORD-001', $this->bsit);
        $this->actingAs($coordinator)->get(route('instructor.reports.accreditation', ['hte_id' => $theirs->id]))->assertOk()->assertSee('Lingayen Gulf Hotel');
    }

    public function test_the_coordinator_has_one_generate_reports_page_for_every_program(): void
    {
        $coordinator = $this->teacher('coordinator', 'ACC-COORD-001', $this->bsit);
        $acme = $this->agency($this->bsit, 'Acme Corp');
        $this->place($this->bsit, $acme, '23-AC-0001');
        $this->place($this->bshm, $acme, '23-AC-0002');

        // One sidebar item; the old Campus Exports page leads here.
        $this->actingAs($coordinator)->get(route('instructor.dashboard'))->assertOk()
            ->assertSee('Generate Reports')->assertDontSee('Campus Exports');
        $this->actingAs($coordinator)->get(route('coordinator.reports'))->assertRedirect(route('instructor.reports'));

        // Campus summary on top, the official forms below, a program picker on each, no CSV report cards.
        $this->actingAs($coordinator)->get(route('instructor.reports'))->assertOk()
            ->assertSeeInOrder(['Total Enrolled Interns', 'Program Summary', 'Export Intern Roster (CSV)', 'FM-AA-INT-09', 'FM-AA-INT-24'])
            ->assertSee('name="program_id"', false)->assertSee('All programs')
            ->assertDontSee('Documents Compliance Report')->assertDontSee('Approved Student Documents')->assertDontSee('Generate &amp; Download', false);
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('coordinator.reports.export-hte'));

        // Every program, or one.
        $this->actingAs($coordinator)->get(route('instructor.reports.student-trainees'))->assertOk()
            ->assertSee('Student 23-AC-0001')->assertSee('Student 23-AC-0002');
        $this->actingAs($coordinator)->get(route('instructor.reports.site-monitoring', ['program_id' => $this->bshm->id]))->assertOk()
            ->assertSee('Student 23-AC-0002')->assertDontSee('Student 23-AC-0001');
        $this->actingAs($coordinator)->get(route('instructor.reports.directory', ['program_id' => $this->bsit->id]))->assertOk()
            ->assertSee('Student 23-AC-0001')->assertDontSee('Student 23-AC-0002');

        // An instructor has no picker, and the choice is ignored for them.
        $instructor = $this->teacher('instructor', 'ACC-INST-001', $this->bsit);
        $this->actingAs($instructor)->get(route('instructor.reports'))->assertOk()
            ->assertDontSee('name="program_id"', false)->assertDontSee('Program Summary');
        $this->actingAs($instructor)->get(route('instructor.reports.student-trainees', ['program_id' => $this->bshm->id]))->assertOk()
            ->assertSee('Student 23-AC-0001')->assertDontSee('Student 23-AC-0002');
    }
}
