<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Deployment;
use App\Models\Document;
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

/** While the deployment waits to start, the student sees exactly what is left. */
class DeploymentBlockersTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_student_sees_what_is_left_before_the_ojt_starts(): void
    {
        $role = Role::forceCreate(['code' => 'student', 'name' => 'student']);
        $campus = Campus::forceCreate(['code' => 'ACC', 'name' => 'PSU - Alaminos City Campus', 'status' => 'active']);
        $program = Program::forceCreate(['campus_id' => $campus->id, 'code' => 'BSIT', 'name' => 'BSIT', 'required_hours' => 486]);
        $make = fn ($code, $name, $n) => DocumentRequirement::forceCreate([
            'campus_id' => $campus->id, 'code' => $code, 'name' => $name, 'form_number' => (string) $n, 'stage' => 'pre_deployment',
            'audience' => 'student', 'workflow_mode' => 'print_sign', 'is_active' => true, 'is_required' => true, 'display_order' => $n,
        ]);
        $form1 = $make('APPLICATION_FOR_OJT', 'Form 1 – Application for Internship', 1);
        $form10 = $make('MOA', 'Form 10 – Memorandum of Agreement', 10);
        $form12 = $make('INTERNSHIP_AGREEMENT', 'Form 12 – Internship Agreement', 12);

        $user = User::forceCreate([
            'campus_id' => $campus->id, 'role_id' => $role->id, 'id_number' => '23-AC-0094', 'name' => 'Lucy Ro',
            'email' => 'lucy@t.local', 'password' => Hash::make('x'), 'status' => 'active', 'must_change_password' => false,
        ]);
        $student = Student::forceCreate(['user_id' => $user->id, 'campus_id' => $campus->id, 'program_id' => $program->id, 'hours_rendered' => 0]);
        $internship = Internship::forceCreate(['student_id' => $student->id, 'campus_id' => $campus->id, 'program_id' => $program->id, 'required_hours' => 486, 'status' => 'pre_deployment']);
        $hte = Hte::forceCreate(['campus_id' => $campus->id, 'name' => 'FAREHEAVEN BOARDING HOUSE']);
        Deployment::forceCreate(['internship_id' => $internship->id, 'hte_id' => $hte->id, 'status' => 'pending']);

        Document::forceCreate(['internship_id' => $internship->id, 'document_requirement_id' => $form1->id, 'status' => 'approved']);
        Document::forceCreate(['internship_id' => $internship->id, 'document_requirement_id' => $form12->id, 'status' => 'completed', 'file_path' => 'x.pdf']);

        $this->actingAs($user)->get(route('student.hte'))->assertOk()
            ->assertSee('Before your OJT can start')
            ->assertSee('Form 10 – Memorandum of Agreement')->assertSee('Not submitted yet')
            ->assertSee('Form 12 – Internship Agreement')->assertSee("Signed copy uploaded - waiting for your instructor&#039;s approval", false)
            ->assertDontSee('Form 1 – Application for Internship</span>', false);
        $this->actingAs($user)->get(route('student.dtr'))->assertOk()->assertSee('Before your OJT can start');

        // Everything approved: now it's the instructor's Start deployment.
        Document::query()->update(['status' => 'approved']);
        Document::forceCreate(['internship_id' => $internship->id, 'document_requirement_id' => $form10->id, 'status' => 'approved']);
        $this->actingAs($user)->get(route('student.hte'))->assertOk()
            ->assertSee('All your pre-deployment requirements are approved.')
            ->assertSee('Your subject instructor now sets your internship start and end dates');
    }
}
