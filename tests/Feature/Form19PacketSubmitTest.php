<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Deployment;
use App\Models\Document;
use App\Models\DocumentEntry;
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

/** The Form 19 log page opens and each week can be submitted on its own. */
class Form19PacketSubmitTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_form19_page_opens_and_a_packet_is_submitted(): void
    {
        $role = Role::forceCreate(['code' => 'student', 'name' => 'student']);
        $campus = Campus::forceCreate(['code' => 'ACC', 'name' => 'PSU - Alaminos City Campus', 'status' => 'active']);
        $program = Program::forceCreate(['campus_id' => $campus->id, 'code' => 'BSIT', 'name' => 'BSIT', 'required_hours' => 486]);
        $form19 = DocumentRequirement::forceCreate([
            'campus_id' => $campus->id, 'code' => 'WEEKLY_REPORT', 'name' => 'Form 19 – Internship/Practicum Weekly Report', 'form_number' => '19',
            'stage' => 'active', 'audience' => 'student', 'workflow_mode' => 'print_sign', 'is_active' => true, 'is_required' => true,
            'is_repeating' => true, 'entry_cadence' => 'daily', 'display_order' => 260,
        ]);
        $user = User::forceCreate([
            'campus_id' => $campus->id, 'role_id' => $role->id, 'id_number' => '23-AC-0094', 'name' => 'Lucy Ro',
            'email' => 'lucy@t.local', 'password' => Hash::make('x'), 'status' => 'active', 'must_change_password' => false,
        ]);
        $student = Student::forceCreate(['user_id' => $user->id, 'campus_id' => $campus->id, 'program_id' => $program->id, 'hours_rendered' => 0]);
        $internship = Internship::forceCreate(['student_id' => $student->id, 'campus_id' => $campus->id, 'program_id' => $program->id, 'required_hours' => 486, 'status' => 'deployed']);
        $hte = Hte::forceCreate(['campus_id' => $campus->id, 'name' => 'FAREHEAVEN BOARDING HOUSE']);
        Deployment::forceCreate(['internship_id' => $internship->id, 'hte_id' => $hte->id, 'status' => 'active', 'start_date' => now()->subWeek(), 'end_date' => now()->addMonths(3)]);

        $packet = Document::forceCreate(['internship_id' => $internship->id, 'document_requirement_id' => $form19->id, 'packet_number' => 1, 'status' => 'draft']);
        DocumentEntry::forceCreate(['document_id' => $packet->id, 'entry_date' => now()->subDays(2)->toDateString()]);

        $this->actingAs($user)->get(route('student.documents.log', $form19))->assertOk()
            ->assertSee('Submit week')
            ->assertSee(route('student.documents.log.packet.submit', [$form19, 1]), false);

        $this->actingAs($user)->post(route('student.documents.log.packet.submit', [$form19, 1]))
            ->assertRedirect(route('student.documents.log', $form19));
        $this->assertSame('submitted', $packet->fresh()->status);
    }
}
