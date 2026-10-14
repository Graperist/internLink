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
    private function activeInternship(string $endDate = '2026-10-09'): array
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
        $deployment = Deployment::forceCreate([
            'internship_id' => $internship->id,
            'hte_id' => $this->hte->id,
            'start_date' => '2026-10-01',
            'end_date' => $endDate,
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

    private function verifiedDtr(Deployment $deployment, float $hours): Dtr
    {
        return Dtr::forceCreate([
            'deployment_id' => $deployment->id,
            'log_date' => '2026-10-05',
            'time_in' => '08:00:00',
            'time_out' => '17:00:00',
            'hours_logged' => $hours,
            'status' => 'approved',
            'clock_in_verified_at' => '2026-10-05 09:00:00',
            'clock_out_verified_at' => '2026-10-05 17:05:00',
        ]);
    }

    public function test_student_opening_documents_does_not_auto_complete_an_ended_deployment(): void
    {
        [$studentUser, , $internship, $deployment] = $this->activeInternship();

        $this->actingAs($studentUser)->get('/student/documents')->assertOk();

        $this->assertSame('active', $deployment->fresh()->status);
        $this->assertSame('deployed', $internship->fresh()->status);
    }

    public function test_instructor_can_confirm_only_after_end_date_and_required_verified_hours(): void
    {
        [, $instructorUser, $internship, $deployment] = $this->activeInternship();
        $this->verifiedDtr($deployment, 10);

        $this->actingAs($instructorUser)
            ->post(route('instructor.advisees.confirm-completion', $deployment))
            ->assertRedirect(route('instructor.advisees'))
            ->assertSessionHas('status');

        $this->assertSame('completed', $deployment->fresh()->status);
        $this->assertSame('completed', $internship->fresh()->status);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $internship->student->user_id,
            'type' => 'deployment_completed',
        ]);
    }

    public function test_instructor_cannot_confirm_when_verified_hours_are_insufficient(): void
    {
        [, $instructorUser, $internship, $deployment] = $this->activeInternship();
        $this->verifiedDtr($deployment, 8);

        $this->actingAs($instructorUser)
            ->from(route('instructor.advisees'))
            ->post(route('instructor.advisees.confirm-completion', $deployment))
            ->assertRedirect(route('instructor.advisees'))
            ->assertSessionHasErrors('completion');

        $this->assertSame('active', $deployment->fresh()->status);
        $this->assertSame('deployed', $internship->fresh()->status);
    }

    public function test_instructor_cannot_confirm_before_the_scheduled_end_date(): void
    {
        [, $instructorUser, $internship, $deployment] = $this->activeInternship('2026-10-20');
        $this->verifiedDtr($deployment, 10);

        $this->actingAs($instructorUser)
            ->from(route('instructor.advisees'))
            ->post(route('instructor.advisees.confirm-completion', $deployment))
            ->assertRedirect(route('instructor.advisees'))
            ->assertSessionHasErrors('completion');

        $this->assertSame('active', $deployment->fresh()->status);
        $this->assertSame('deployed', $internship->fresh()->status);
    }
}
