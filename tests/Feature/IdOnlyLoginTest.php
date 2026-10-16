<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Role;
use App\Models\User;
use App\Support\AccountResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Sign-in asks only for the ID and password: the campus comes from the
 * code inside the ID and the role comes from the account.
 */
class IdOnlyLoginTest extends TestCase
{
    use RefreshDatabase;

    private Campus $acc;

    private Campus $bc;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['coordinator', 'instructor', 'hte_representative', 'student'] as $code) {
            Role::forceCreate(['code' => $code, 'name' => $code]);
        }

        $this->acc = Campus::forceCreate(['code' => 'ACC', 'name' => 'Pangasinan State University - Alaminos City Campus', 'status' => 'active']);
        $this->bc = Campus::forceCreate(['code' => 'BC', 'name' => 'Pangasinan State University - Bayambang Campus', 'status' => 'active']);
    }

    private function makeUser(string $role, Campus $campus, string $id): User
    {
        return User::forceCreate([
            'campus_id' => $campus->id,
            'role_id' => Role::where('code', $role)->value('id'),
            'id_number' => $id,
            'name' => "{$role} {$id}",
            'email' => strtolower(str_replace('-', '.', $id)).'.'.$campus->code.'@test.local',
            'password' => Hash::make('secret-pass'),
            'status' => 'active',
            'must_change_password' => false,
        ]);
    }

    public function test_login_page_has_no_campus_or_role_pickers(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('name="campus_id"', false)
            ->assertDontSee('name="role"', false)
            ->assertSee('name="id_number"', false)
            ->assertSee('Alaminos City Campus');
    }

    public function test_each_role_lands_on_its_own_dashboard(): void
    {
        $cases = [
            ['student', '23-AC-0092', 'student.dashboard'],
            ['instructor', 'ACC-INST-001', 'instructor.dashboard'],
            ['coordinator', 'ACC-COORD-001', 'instructor.dashboard'], // the coordinator uses the shared portal
            ['hte_representative', 'ACC-HTE-001', 'hte.dashboard'],
        ];

        foreach ($cases as [$role, $id, $route]) {
            $user = $this->makeUser($role, $this->acc, $id);

            $this->post('/login', ['id_number' => $id, 'password' => 'secret-pass'])
                ->assertRedirect(route($route));
            $this->assertAuthenticatedAs($user);

            $this->post('/logout');
        }
    }

    public function test_id_is_matched_case_and_space_insensitively(): void
    {
        $user = $this->makeUser('student', $this->acc, '23-AC-0092');

        $this->post('/login', ['id_number' => '  23-ac-0092 ', 'password' => 'secret-pass'])
            ->assertRedirect(route('student.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_password_and_unknown_id_get_the_same_message(): void
    {
        $this->makeUser('student', $this->acc, '23-AC-0092');

        $wrong = $this->post('/login', ['id_number' => '23-AC-0092', 'password' => 'nope'])
            ->assertSessionHasErrors('id_number');
        $wrongMsg = session('errors')->first('id_number');

        $this->post('/login', ['id_number' => '23-AC-9999', 'password' => 'nope'])
            ->assertSessionHasErrors('id_number');

        $this->assertSame($wrongMsg, session('errors')->first('id_number'));
        $this->assertGuest();
    }

    public function test_campus_is_read_from_student_and_employee_ids(): void
    {
        $this->assertSame($this->acc->id, AccountResolver::campusFromId('23-AC-0092')?->id);
        $this->assertSame($this->acc->id, AccountResolver::campusFromId('ACC-COORD-001')?->id);
        $this->assertSame($this->bc->id, AccountResolver::campusFromId('bc-inst-004')?->id);
        $this->assertNull(AccountResolver::campusFromId('99-ZZ-0001'));
    }

    public function test_same_id_on_two_campuses_is_settled_by_the_campus_in_the_id(): void
    {
        // Legacy data from when IDs were only unique per campus.
        $accUser = $this->makeUser('instructor', $this->acc, 'ACC-INST-001');
        $this->makeUser('instructor', $this->bc, 'ACC-INST-001');

        $this->assertSame($accUser->id, AccountResolver::findUser('ACC-INST-001')?->id);
    }

    public function test_inactive_accounts_are_blocked(): void
    {
        $user = $this->makeUser('student', $this->acc, '23-AC-0092');
        $user->forceFill(['status' => 'inactive'])->save();

        $this->post('/login', ['id_number' => '23-AC-0092', 'password' => 'secret-pass'])
            ->assertSessionHasErrors('id_number');
        $this->assertGuest();
    }
}
