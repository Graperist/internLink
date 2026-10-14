<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Deployment;
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

class PostDeploymentVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function setUpStudent(string $internshipStatus, string $deploymentStatus): User
    {
        $role = Role::forceCreate(['code' => 'student', 'name' => 'Student']);
        $campus = Campus::forceCreate(['code' => 'AAA', 'name' => 'A', 'status' => 'active']);
        $program = Program::forceCreate(['campus_id' => $campus->id, 'code' => 'BSIT', 'name' => 'BSIT', 'required_hours' => 300]);
        $hte = Hte::forceCreate(['campus_id' => $campus->id, 'name' => 'HTE']);
        $user = User::forceCreate([
            'campus_id' => $campus->id, 'role_id' => $role->id, 'id_number' => 'S-1', 'name' => 'Stu',
            'email' => 's@t.local', 'password' => Hash::make('x'), 'status' => 'active', 'must_change_password' => false,
        ]);
        $student = Student::forceCreate(['user_id' => $user->id, 'campus_id' => $campus->id, 'program_id' => $program->id]);
        $internship = Internship::forceCreate([
            'student_id' => $student->id, 'campus_id' => $campus->id, 'program_id' => $program->id, 'status' => $internshipStatus,
        ]);
        Deployment::forceCreate(['internship_id' => $internship->id, 'hte_id' => $hte->id, 'status' => $deploymentStatus]);

        foreach (['PRE_X' => 'pre_deployment', 'POST_X' => 'post_deployment'] as $code => $stage) {
            DocumentRequirement::forceCreate([
                'campus_id' => $campus->id, 'code' => $code, 'name' => $code, 'stage' => $stage, 'audience' => 'student',
                'workflow_mode' => 'upload', 'is_active' => true, 'is_required' => true, 'display_order' => 1,
            ]);
        }

        return $user;
    }

    public function test_hidden_while_still_deployed(): void
    {
        $user = $this->setUpStudent('deployed', 'active');
        $this->actingAs($user)->get('/student/documents')->assertOk()->assertSee('PRE_X')->assertDontSee('POST_X');
    }

    public function test_shown_when_both_statuses_are_completed(): void
    {
        $user = $this->setUpStudent('completed', 'completed');
        $this->actingAs($user)->get('/student/documents')->assertOk()->assertSee('POST_X');
    }

    public function test_shown_when_only_the_deployment_is_completed(): void
    {
        // The drifted case: deployment finished but the internship row still says "deployed".
        $user = $this->setUpStudent('deployed', 'completed');
        $this->actingAs($user)->get('/student/documents')->assertOk()->assertSee('POST_X');
    }
}
