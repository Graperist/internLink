<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Deployment;
use App\Models\DocumentRequirement;
use App\Models\Dtr;
use App\Models\Hte;
use App\Models\Internship;
use App\Models\Notification;
use App\Models\Program;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DeploymentAutoCompleteTest extends TestCase
{
    use RefreshDatabase;

    private Campus $campus;
    private Program $program;
    private Hte $hte;
    private array $roles = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-04 22:52:00'));

        foreach (['coordinator', 'student'] as $code) {
            $this->roles[$code] = Role::forceCreate(['code' => $code, 'name' => ucfirst($code)]);
        }
        $this->campus = Campus::forceCreate(['code' => 'AAA', 'name' => 'A', 'status' => 'active']);
        $this->program = Program::forceCreate(['campus_id' => $this->campus->id, 'code' => 'BSIT', 'name' => 'BSIT', 'required_hours' => 300]);
        $this->hte = Hte::forceCreate(['campus_id' => $this->campus->id, 'name' => 'HTE']);

        foreach (['PRE_X' => 'pre_deployment', 'POST_X' => 'post_deployment'] as $code => $stage) {
            DocumentRequirement::forceCreate([
                'campus_id' => $this->campus->id, 'code' => $code, 'name' => $code, 'stage' => $stage, 'audience' => 'student',
                'workflow_mode' => 'upload', 'is_active' => true, 'is_required' => true, 'display_order' => 1,
            ]);
        }
    }

    private function user(string $role, string $id): User
    {
        return User::forceCreate([
            'campus_id' => $this->campus->id, 'role_id' => $this->roles[$role]->id, 'id_number' => $id, 'name' => "$role $id",
            'email' => "$id@t.local", 'password' => Hash::make('x'), 'status' => 'active', 'must_change_password' => false,
        ]);
    }

    /** @return array{0: User, 1: Internship, 2: Deployment} */
    private function deployed(string $endDate, string $id = 'S-1', string $internshipStatus = 'deployed'): array
    {
        $user = $this->user('student', $id);
        $student = Student::forceCreate(['user_id' => $user->id, 'campus_id' => $this->campus->id, 'program_id' => $this->program->id]);
        $internship = Internship::forceCreate([
            'student_id' => $student->id, 'campus_id' => $this->campus->id, 'program_id' => $this->program->id, 'status' => $internshipStatus,
        ]);
        $deployment = Deployment::forceCreate([
            'internship_id' => $internship->id, 'hte_id' => $this->hte->id,
            'start_date' => '2026-10-03', 'end_date' => $endDate, 'status' => 'active',
        ]);

        return [$user, $internship, $deployment];
    }

    public function test_coordinator_page_completes_an_ended_deployment_and_its_internship(): void
    {
        [$student, $internship, $deployment] = $this->deployed('2026-10-03'); // ended yesterday
        $coordinator = $this->user('coordinator', 'C-1');

        $this->actingAs($coordinator)->get('/coordinator/deployments')->assertOk();

        $this->assertSame('completed', $deployment->fresh()->status);
        $this->assertSame('completed', $internship->fresh()->status);
        $this->assertDatabaseHas('notifications', ['user_id' => $student->id, 'type' => 'deployment_completed']);
    }

    public function test_student_opening_documents_unlocks_post_deployment_forms(): void
    {
        [$student, , $deployment] = $this->deployed('2026-10-03');

        $this->actingAs($student)->get('/student/documents')->assertOk()->assertSee('POST_X');
        $this->assertSame('completed', $deployment->fresh()->status);
    }

    public function test_deployments_ending_today_or_later_stay_active(): void
    {
        [, , $endsToday] = $this->deployed('2026-10-04', 'S-1');
        [, , $endsLater] = $this->deployed('2026-10-19', 'S-2');
        [, , $noEnd] = $this->deployed('2026-10-19', 'S-3');
        $noEnd->update(['end_date' => null]);

        $this->artisan('internlink:complete-ended-deployments')->assertSuccessful();

        $this->assertSame('active', $endsToday->fresh()->status);
        $this->assertSame('active', $endsLater->fresh()->status);
        $this->assertSame('active', $noEnd->fresh()->status);
    }

    public function test_a_student_clocked_in_right_now_is_not_cut_off(): void
    {
        [, , $deployment] = $this->deployed('2026-10-03');
        Dtr::forceCreate([
            'deployment_id' => $deployment->id, 'log_date' => '2026-10-04', 'time_in' => '12:13:00',
            'time_out' => null, 'hours_logged' => 0, 'status' => 'pending',
        ]);

        $this->artisan('internlink:complete-ended-deployments')->assertSuccessful();
        $this->assertSame('active', $deployment->fresh()->status);

        Dtr::where('deployment_id', $deployment->id)->update(['time_out' => '17:00:00', 'hours_logged' => 5]);
        $this->artisan('internlink:complete-ended-deployments')->assertSuccessful();
        $this->assertSame('completed', $deployment->fresh()->status);
    }

    public function test_it_is_idempotent_and_does_not_notify_twice(): void
    {
        [$student, , $deployment] = $this->deployed('2026-10-03');

        $this->artisan('internlink:complete-ended-deployments')->assertSuccessful();
        $this->artisan('internlink:complete-ended-deployments')->assertSuccessful();

        $this->assertSame(1, Notification::where('user_id', $student->id)->where('type', 'deployment_completed')->count());
    }

    public function test_a_terminated_internship_is_never_overwritten(): void
    {
        [, $internship, $deployment] = $this->deployed('2026-10-03', 'S-1', 'terminated');

        $this->artisan('internlink:complete-ended-deployments')->assertSuccessful();

        $this->assertSame('terminated', $internship->fresh()->status);
    }

    public function test_the_job_is_scheduled(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('internlink:complete-ended-deployments')->assertSuccessful();
    }
}
