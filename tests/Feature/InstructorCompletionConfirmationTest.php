<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Deployment;
use App\Models\DocumentRequirement;
use App\Models\Dtr;
use App\Models\Hte;
use App\Models\Instructor;
use App\Models\Internship;
use App\Models\Program;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InstructorCompletionConfirmationTest extends TestCase
{
    use RefreshDatabase;

    private Campus $campus;
    private Program $program;
    private Hte $hte;
    private array $roles = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-10 12:00:00', 'Asia/Manila'));

        foreach (['instructor', 'student'] as $code) {
            $this->roles[$code] = Role::forceCreate(['code' => $code, 'name' => ucfirst($code)]);
        }

        $this->campus = Campus::forceCreate(['code' => 'AAA', 'name' => 'A', 'status' => 'active']);
        $this->program = Program::forceCreate([
            'campus_id' => $this->campus->id,
            'code' => 'BSIT',
            'name' => 'BSIT',
            'required_hours' => 10,
        ]);
        $this->hte = Hte::forceCreate(['campus_id' => $this->campus->id, 'name' => 'HTE']);

        DocumentRequirement::forceCreate([
            'campus_id' => $this->campus->id,
            'code' => 'PRE_X',
            'name' => 'Pre-OJT Form',
            'stage' => 'pre_deployment',
            'audience' => 'student',
            'workflow_mode' => 'upload',
            'is_active' => true,
            'is_required' => true,
            'display_order' => 1,
        ]);
    }

    private function user(string $role, string $id): User
    {
        return User::forceCreate([
            'campus_id' => $this->campus->id,
            'role_id' => $this->roles[$role]->id,
            'id_number' => $id,
            'name' => "$role $id",
            'email' => "$id@t.local",
            'password' => Hash::make('x'),
            'status' => 'active',
            'must_change_password' => false,
        ]);
    }

    /** @return array{0: User, 1: User, 2: Internship, 3: Deployment} */
    private function activeInternship(): array
    {
        $studentUser = $this->user('student', 'S-1');
        $student = Student::forceCreate([
            'user_id' => $studentUser->id,
            'campus_id' => $this->campus->id,
            'program_id' => $this->program->id,
            'hours_rendered' => 0,
        ]);
        $internship = Internship::forceCreate([
            'student_id' => $student->id,
            'campus_id' => $this->campus->id,
            'program_id' => $this->program->id,
            'required_hours' => 10,
            'status' => 'deployed',
        ]);
        // Started with no end date: it runs until the required hours are reached.
        $deployment = Deployment::forceCreate([
            'internship_id' => $internship->id,
            'hte_id' => $this->hte->id,
            'start_date' => '2026-10-01',
            'end_date' => null,
            'status' => 'active',
        ]);

        $instructorUser = $this->user('instructor', 'I-1');
        Instructor::forceCreate([
            'user_id' => $instructorUser->id,
            'campus_id' => $this->campus->id,
            'program_id' => $this->program->id,
        ]);

        return [$studentUser, $instructorUser, $internship, $deployment];
    }

    /** A day the student entered in Time Frame (it counts right away). */
    private function day(Deployment $deployment, string $date, float $hours): Dtr
    {
        return Dtr::forceCreate([
            'deployment_id' => $deployment->id,
            'log_date' => $date,
            'hours_logged' => $hours,
            'approved_hours' => $hours,
            'status' => 'approved',
        ]);
    }

    public function test_opening_documents_does_not_complete_the_internship(): void
    {
        [$studentUser, , $internship, $deployment] = $this->activeInternship();

        $this->actingAs($studentUser)->get(route('student.documents'))->assertOk();

        $this->assertSame('active', $deployment->fresh()->status);
        $this->assertSame('deployed', $internship->fresh()->status);
    }

    public function test_instructor_confirms_completion_once_the_required_hours_are_reached(): void
    {
        [$studentUser, $instructorUser, $internship, $deployment] = $this->activeInternship();
        $this->day($deployment, '2026-10-02', 6);
        $this->day($deployment, '2026-10-06', 4);

        $this->actingAs($instructorUser)->get(route('instructor.advisees'))->assertOk()
            ->assertSee('Rendered: 10 / 10 hrs')->assertSee('Confirm completion');
        $this->actingAs($instructorUser)->post(route('instructor.advisees.confirm-completion', $deployment))
            ->assertRedirect()->assertSessionHasNoErrors();

        $deployment->refresh();
        $this->assertSame('completed', $deployment->status);
        $this->assertSame('2026-10-06', $deployment->end_date->toDateString());   // the last day entered
        $this->assertSame('completed', $internship->fresh()->status);
        $this->assertDatabaseHas('notifications', ['user_id' => $studentUser->id, 'title' => 'Internship completion confirmed']);
    }

    public function test_instructor_cannot_confirm_before_the_required_hours(): void
    {
        [, $instructorUser, $internship, $deployment] = $this->activeInternship();
        $this->day($deployment, '2026-10-02', 7.5);

        $this->actingAs($instructorUser)->get(route('instructor.advisees'))->assertOk()
            ->assertSee('Rendered: 7.5 / 10 hrs')->assertDontSee('>Confirm completion<', false)
            ->assertSee('Completion unlocks once the student');
        $this->actingAs($instructorUser)->post(route('instructor.advisees.confirm-completion', $deployment))
            ->assertSessionHasErrors('completion');

        $this->assertSame('active', $deployment->fresh()->status);
        $this->assertNull($deployment->fresh()->end_date);
        $this->assertSame('deployed', $internship->fresh()->status);
    }
}
