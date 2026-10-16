<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\DocumentRequirement;
use App\Models\Internship;
use App\Models\Program;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** My Documents lists the forms by form number: 1, 2, 3, 6, 11, 12, 15, 18. */
class StudentFormOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_forms_are_listed_by_form_number_without_a_repeated_label(): void
    {
        $role = Role::forceCreate(['code' => 'student', 'name' => 'student']);
        $campus = Campus::forceCreate(['code' => 'ACC', 'name' => 'PSU - Alaminos City Campus', 'status' => 'active']);
        $program = Program::forceCreate(['campus_id' => $campus->id, 'code' => 'BSIT', 'name' => 'BSIT', 'required_hours' => 486]);

        // Created out of order, with display orders that disagree, as on a
        // database seeded before the forms were renumbered.
        $forms = [
            ['18', 'Training Agreement and Liability Waiver', 5], ['1', 'Application for Internship', 1],
            ['11', 'Internship Resume', 2], ['6', 'Endorsement Letter (Rev. 2, 2024)', 8],
            ['3', 'Consent and Waiver', 7], ['12', 'Internship Agreement', 3],
            ['2', 'Certification of Units Earned for Practicum/Internship', 6], ['15', 'Internship Release Form', 4],
        ];
        foreach ($forms as [$number, $title, $order]) {
            DocumentRequirement::forceCreate([
                'campus_id' => $campus->id, 'code' => 'F'.$number, 'name' => "Form {$number} – {$title}", 'form_number' => $number,
                'stage' => 'pre_deployment', 'audience' => 'student', 'workflow_mode' => 'upload',
                'is_active' => true, 'is_required' => true, 'display_order' => $order,
            ]);
        }

        $user = User::forceCreate([
            'campus_id' => $campus->id, 'role_id' => $role->id, 'id_number' => '23-AC-0094', 'name' => 'Lucy Ro',
            'email' => 'lucy@t.local', 'password' => Hash::make('x'), 'status' => 'active', 'must_change_password' => false,
        ]);
        $student = Student::forceCreate(['user_id' => $user->id, 'campus_id' => $campus->id, 'program_id' => $program->id, 'hours_rendered' => 0]);
        Internship::forceCreate(['student_id' => $student->id, 'campus_id' => $campus->id, 'program_id' => $program->id, 'required_hours' => 486, 'status' => 'pre_deployment']);

        $response = $this->actingAs($user)->get(route('student.documents'))->assertOk();

        $response->assertSeeInOrder([
            'Form 1 – Application', 'Form 2 – Certification', 'Form 3 – Consent', 'Form 6 – Endorsement',
            'Form 11 – Internship Resume', 'Form 12 – Internship Agreement', 'Form 15 – Internship Release', 'Form 18 – Training',
        ]);
        $this->assertStringNotContainsString('<span class="text-secondary me-1">Form 1</span>', $response->getContent());
    }
}
