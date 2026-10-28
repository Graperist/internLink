<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Deployment;
use App\Models\Hte;
use App\Models\Instructor;
use App\Models\Internship;
use App\Models\Program;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Document Review and My Students can be filtered by partner agency. */
class PartnerAgencyFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $instructor;

    private Hte $fareheaven;

    private Hte $cityHall;

    protected function setUp(): void
    {
        parent::setUp();
        $roles = [];
        foreach (['instructor', 'student'] as $code) {
            $roles[$code] = Role::forceCreate(['code' => $code, 'name' => $code]);
        }
        $campus = Campus::forceCreate(['code' => 'ACC', 'name' => 'ACC', 'status' => 'active']);
        $program = Program::forceCreate(['campus_id' => $campus->id, 'code' => 'BSIT', 'name' => 'BSIT', 'required_hours' => 486]);
        $this->fareheaven = Hte::forceCreate(['campus_id' => $campus->id, 'name' => 'Fareheaven Boarding House']);
        $this->cityHall = Hte::forceCreate(['campus_id' => $campus->id, 'name' => 'Alaminos City Hall']);
        Hte::forceCreate(['campus_id' => $campus->id, 'name' => 'Nobody Placed Here']);

        $mk = fn ($role, $id, $name) => User::forceCreate([
            'campus_id' => $campus->id, 'role_id' => $roles[$role]->id, 'id_number' => $id, 'name' => $name,
            'email' => strtolower($id).'@t.local', 'password' => Hash::make('x'), 'status' => 'active', 'must_change_password' => false,
        ]);
        $this->instructor = $mk('instructor', 'ACC-INST-001', 'Instructor');
        Instructor::forceCreate(['user_id' => $this->instructor->id, 'campus_id' => $campus->id, 'program_id' => $program->id]);

        foreach ([['23-AC-0001', 'Ana Fare', $this->fareheaven], ['23-AC-0002', 'Ben City', $this->cityHall], ['23-AC-0003', 'Cora None', null]] as [$id, $name, $hte]) {
            $student = Student::forceCreate(['user_id' => $mk('student', $id, $name)->id, 'campus_id' => $campus->id, 'program_id' => $program->id, 'hours_rendered' => 0]);
            $internship = Internship::forceCreate(['student_id' => $student->id, 'campus_id' => $campus->id, 'program_id' => $program->id, 'required_hours' => 486, 'status' => $hte ? 'deployed' : 'pre_deployment']);
            if ($hte) {
                Deployment::forceCreate(['internship_id' => $internship->id, 'hte_id' => $hte->id, 'start_date' => '2026-10-01', 'status' => 'active']);
            }
        }
    }

    public function test_document_review_filters_by_partner_agency(): void
    {
        $this->actingAs($this->instructor)->get(route('instructor.documents'))->assertOk()
            ->assertSee('All partner agencies')
            ->assertSee('Fareheaven Boarding House')->assertSee('Alaminos City Hall')
            // only agencies their students are placed at
            ->assertDontSee('Nobody Placed Here')
            ->assertSee('Ana Fare')->assertSee('Ben City')->assertSee('Cora None');

        $this->actingAs($this->instructor)->get(route('instructor.documents', ['hte_id' => $this->fareheaven->id]))->assertOk()
            ->assertSee('Ana Fare')->assertDontSee('Ben City')->assertDontSee('Cora None');

        $this->actingAs($this->instructor)->get(route('instructor.documents', ['hte_id' => 'none']))->assertOk()
            ->assertSee('Cora None')->assertDontSee('Ana Fare')->assertDontSee('Ben City');
    }

    public function test_my_students_filters_by_partner_agency(): void
    {
        $this->actingAs($this->instructor)->get(route('instructor.advisees'))->assertOk()
            ->assertSee('All partner agencies')->assertDontSee('Nobody Placed Here')
            ->assertSee('Ana Fare')->assertSee('Ben City')->assertSee('Cora None');

        $this->actingAs($this->instructor)->get(route('instructor.advisees', ['hte_id' => $this->cityHall->id]))->assertOk()
            ->assertSee('Ben City')->assertDontSee('Ana Fare')->assertDontSee('Cora None');

        $this->actingAs($this->instructor)->get(route('instructor.advisees', ['hte_id' => 'none']))->assertOk()
            ->assertSee('Cora None')->assertDontSee('Ana Fare')->assertDontSee('Ben City');
    }
}
