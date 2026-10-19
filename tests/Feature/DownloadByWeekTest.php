<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Deployment;
use App\Models\Document;
use App\Models\DocumentEntry;
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

/** Form 17 (Time Frame) and Form 19 (Weekly Report) can be downloaded one week at a time. */
class DownloadByWeekTest extends TestCase
{
    use RefreshDatabase;

    private User $studentUser;
    private User $instructorUser;
    private Internship $internship;
    private Deployment $deployment;
    private Campus $campus;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-19 12:00:00'));
        $roles = [];
        foreach (['coordinator', 'instructor', 'hte_representative', 'student'] as $code) {
            $roles[$code] = Role::forceCreate(['code' => $code, 'name' => ucfirst($code)]);
        }
        $this->campus = Campus::forceCreate(['code' => 'ACC', 'name' => 'Alaminos City Campus', 'status' => 'active']);
        $program = Program::forceCreate(['campus_id' => $this->campus->id, 'code' => 'BSIT', 'name' => 'BSIT', 'required_hours' => 486]);
        $mk = fn ($role, $id, $name) => User::forceCreate([
            'campus_id' => $this->campus->id, 'role_id' => $roles[$role]->id, 'id_number' => $id, 'name' => $name,
            'email' => "{$id}@t.local", 'password' => Hash::make('x'), 'status' => 'active', 'must_change_password' => false,
        ]);
        $this->instructorUser = $mk('instructor', 'I-1', 'Prof Ian');
        Instructor::forceCreate(['user_id' => $this->instructorUser->id, 'campus_id' => $this->campus->id, 'program_id' => $program->id]);
        $this->studentUser = $mk('student', 'S-1', 'Lucy Ro');
        $student = Student::forceCreate(['user_id' => $this->studentUser->id, 'campus_id' => $this->campus->id, 'program_id' => $program->id, 'hours_rendered' => 0]);
        $this->internship = Internship::forceCreate(['student_id' => $student->id, 'campus_id' => $this->campus->id, 'program_id' => $program->id, 'required_hours' => 486, 'status' => 'deployed']);
        $hte = Hte::forceCreate(['campus_id' => $this->campus->id, 'name' => 'Fareheaven']);
        $this->deployment = Deployment::forceCreate(['internship_id' => $this->internship->id, 'hte_id' => $hte->id, 'status' => 'active', 'start_date' => '2026-10-01']);

        // Week 1 starts on the first DTR day: Thu Oct 01 .. Wed Oct 07; Week 2: Oct 08 .. 14.
        foreach (['2026-10-01' => 8, '2026-10-02' => 7, '2026-10-09' => 5] as $date => $hours) {
            Dtr::forceCreate(['deployment_id' => $this->deployment->id, 'log_date' => $date, 'am_in' => '08:00', 'am_out' => '12:00',
                'time_in' => '08:00', 'hours_logged' => $hours, 'status' => 'approved']);
        }
    }

    private function requirement(string $code, string $name, string $number): DocumentRequirement
    {
        return DocumentRequirement::forceCreate([
            'campus_id' => $this->campus->id, 'code' => $code, 'name' => $name, 'form_number' => $number, 'stage' => 'active',
            'audience' => 'student', 'workflow_mode' => 'print_sign', 'is_active' => true, 'is_required' => true,
            'is_repeating' => true, 'entry_cadence' => 'daily', 'display_order' => (int) $number,
        ]);
    }

    public function test_form_17_downloads_one_week(): void
    {
        $this->requirement('INTERNSHIP_TIME_FRAME', 'Form 17 – Internship Time Frame', '17');

        // The student's Time Frame page and the instructor's monitoring list the weeks.
        $this->actingAs($this->studentUser)->get('/student/dtr')->assertOk()
            ->assertSee('Download by week')->assertSee('Week 1')->assertSee('Week 2')
            ->assertSee(route('student.dtr.form', ['download' => 1, 'week' => '2026-10-08']));
        $this->actingAs($this->instructorUser)->get('/instructor/monitoring?student='.$this->internship->student_id)->assertOk()
            ->assertSee(route('instructor.monitoring.form', ['student' => $this->internship->student_id, 'download' => 1, 'week' => '2026-10-01']));

        // Week 1 only: Oct 01 + Oct 02, no Oct 09.
        $this->actingAs($this->studentUser)->get(route('student.dtr.form', ['download' => 1, 'week' => '2026-10-01']))->assertOk()
            ->assertSee('Oct 01, 2026')->assertSee('Oct 02, 2026')->assertDontSee('Oct 09, 2026')
            ->assertSee('Week 1 (Oct 01 to Oct 07, 2026)', false);
        // Any day of the week picks that week; the instructor gets the same.
        $this->actingAs($this->instructorUser)->get(route('instructor.monitoring.form', ['student' => $this->internship->student_id, 'download' => 1, 'week' => '2026-10-11']))->assertOk()
            ->assertSee('Oct 09, 2026')->assertDontSee('Oct 01, 2026')->assertSee('Week 2 (Oct 08 to Oct 14, 2026)', false);
        // Without a week: the whole form.
        $this->actingAs($this->studentUser)->get(route('student.dtr.form', ['download' => 1]))->assertOk()
            ->assertSee('Oct 01, 2026')->assertSee('Oct 09, 2026');
    }

    public function test_form_19_packet_downloads_one_week(): void
    {
        $form19 = $this->requirement('WEEKLY_REPORT', 'Form 19 – Internship/Practicum Weekly Report', '19');
        $packet = Document::forceCreate(['internship_id' => $this->internship->id, 'document_requirement_id' => $form19->id, 'packet_number' => 1, 'status' => 'printed']);
        foreach (['2026-10-01', '2026-10-02', '2026-10-09'] as $date) {
            DocumentEntry::forceCreate(['document_id' => $packet->id, 'entry_date' => $date]);
        }

        $this->actingAs($this->studentUser)->get(route('student.documents.log', $form19))->assertOk()
            ->assertSee('Download by week')
            ->assertSee(route('student.documents.printable', ['document' => $packet, 'download' => 1, 'week' => '2026-10-08']));

        $all = $this->actingAs($this->studentUser)->get(route('student.documents.printable', ['document' => $packet, 'download' => 1]))->getContent();
        $week2 = $this->actingAs($this->studentUser)->get(route('student.documents.printable', ['document' => $packet, 'download' => 1, 'week' => '2026-10-08']))
            ->assertOk()->assertSee('Packet 1 - Week 2', false)->getContent();
        $this->assertSame(2, substr_count($all, 'class="entries-table"'));     // a page per week
        $this->assertSame(1, substr_count($week2, 'class="entries-table"'));   // just week 2
    }
}
