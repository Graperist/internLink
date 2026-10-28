<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\LoginSlide;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminLoginPhotosTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $coordinator;

    protected function setUp(): void
    {
        parent::setUp();
        Campus::forceCreate(['code' => 'ACC', 'name' => 'Alaminos City Campus', 'status' => 'active']);
        foreach (['coordinator', 'instructor', 'hte_representative', 'student', 'super_admin'] as $code) {
            Role::forceCreate(['code' => $code, 'name' => ucfirst($code)]);
        }
        // The Super Admin manages the photos (university-wide, no campus).
        $this->admin = User::forceCreate(['campus_id' => null, 'role_id' => Role::where('code', 'super_admin')->value('id'),
            'id_number' => 'PSU-ADMIN-001', 'name' => 'Super Admin', 'email' => 'a@t.local', 'password' => Hash::make('x'), 'status' => 'active', 'must_change_password' => false]);
        $this->coordinator = User::forceCreate(['campus_id' => Campus::first()->id, 'role_id' => Role::where('code', 'coordinator')->value('id'),
            'id_number' => 'ACC-COORD-001', 'name' => 'Coord', 'email' => 'c@t.local', 'password' => Hash::make('x'), 'status' => 'active', 'must_change_password' => false]);
    }

    protected function tearDown(): void
    {
        foreach (LoginSlide::all() as $slide) {
            if (! $slide->isBuiltIn()) {   // never the built-in photos
                @unlink(public_path($slide->path));
            }
        }
        parent::tearDown();
    }

    public function test_login_photos_are_the_super_admins(): void
    {
        $this->actingAs($this->admin)->get(route('admin.campuses.index'))->assertOk()->assertSee(route('admin.login-photos'), false);
        $this->actingAs($this->admin)->get(route('admin.login-photos'))->assertOk()
            ->assertSee('Login page photos')->assertSee('Replace')->assertSee('Adjust fit')->assertSee('cropperjs/1.6.2', false);

        // Not the coordinator's anymore: gone from their Settings, the old link goes to Settings.
        $this->actingAs($this->coordinator)->get(route('coordinator.settings'))->assertOk()
            ->assertSeeInOrder(['Campus Settings', 'Audit Trail'])->assertDontSee('Login Page Photos');
        $this->actingAs($this->coordinator)->get('/coordinator/login-photos')->assertRedirect('/coordinator/settings');
        $this->actingAs($this->coordinator)->get(route('admin.login-photos'))->assertForbidden();
        $this->actingAs($this->coordinator)->post(route('admin.login-photos.store'), ['photos' => [UploadedFile::fake()->image('a.jpg', 800, 500)]])->assertForbidden();
    }

    public function test_view_login_page_opens_a_preview_while_signed_in(): void
    {
        $this->actingAs($this->admin)->get(route('admin.login-photos'))->assertOk()
            ->assertSee(route('admin.login-photos.preview'), false);
        // The real sign-in page sends a signed-in user away; the preview shows it.
        $this->actingAs($this->admin)->get(route('login'))->assertRedirect();
        $this->actingAs($this->admin)->get(route('admin.login-photos.preview'))->assertOk()
            ->assertSee('Preview of the sign-in page')->assertSee('lx-hero-photo', false)
            ->assertSee(LoginSlide::builtIn()->first()['src'], false);
    }

    public function test_only_the_super_admin_manages_the_photos(): void
    {
        $instructor = User::forceCreate(['campus_id' => Campus::first()->id, 'role_id' => Role::where('code', 'instructor')->value('id'),
            'id_number' => 'ACC-INST-001', 'name' => 'Inst', 'email' => 'i@t.local', 'password' => Hash::make('x'), 'status' => 'active', 'must_change_password' => false]);
        \App\Models\Instructor::forceCreate(['user_id' => $instructor->id, 'campus_id' => Campus::first()->id]);
        $this->actingAs($instructor)->get(route('admin.login-photos'))->assertForbidden();
                $this->actingAs($instructor)->get(route('admin.login-photos.preview'))->assertForbidden();
                $this->actingAs($instructor)->post(route('admin.login-photos.store'), ['photos' => [UploadedFile::fake()->image('a.jpg', 800, 500)]])->assertForbidden();
        $this->assertSame(0, LoginSlide::count());
    }

    public function test_each_built_in_photo_is_listed_so_it_can_be_replaced_on_its_own(): void
    {
        $builtIn = LoginSlide::builtIn();
        $this->assertNotEmpty($builtIn);

        $this->actingAs($this->admin)->get(route('admin.login-photos'))->assertOk()->assertSee('Add photo')->assertSee('Edit caption');
        $this->assertSame($builtIn->pluck('path')->all(), LoginSlide::orderBy('position')->pluck('path')->all());
        $this->assertSame($builtIn->pluck('campus')->all(), LoginSlide::orderBy('position')->pluck('caption')->all());

        // Replace the second one (e.g. a cropped picture from the fit window).
        $second = LoginSlide::orderBy('position')->skip(1)->first();
        $builtInFile = public_path($second->path);
        $this->actingAs($this->admin)->postJson(route('admin.login-photos.replace', $second), [
            'photo' => UploadedFile::fake()->image('login-photo.jpg', 1600, 1280),
        ])->assertOk()->assertJson(['message' => 'Photo "'.$second->caption.'" replaced.']);

        $second->refresh();
        $this->assertFalse($second->isBuiltIn());
        $this->assertFileExists(public_path($second->path));
        $this->assertFileExists($builtInFile);   // the built-in file stays on disk
        $this->assertSame(2, $second->position);
        $this->assertSame($builtIn[1]['campus'], $second->caption);

        // Replacing it again deletes the previous upload.
        $previous = public_path($second->path);
        $this->actingAs($this->admin)->post(route('admin.login-photos.replace', $second), [
            'photo' => UploadedFile::fake()->image('again.jpg', 1600, 1280),
        ])->assertRedirect()->assertSessionHas('status');
        $this->assertFileDoesNotExist($previous);

        // The login page shows it in the same place, with the other campuses.
        auth()->logout();
        $this->get(route('login'))->assertOk()
            ->assertSeeInOrder([$builtIn[0]['src'], $second->fresh()->url(), $builtIn[2]['src']], false);

        // Only photos.
        $this->actingAs($this->admin)->post(route('admin.login-photos.replace', $second), [
            'photo' => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'),
        ])->assertSessionHasErrors('photo');
    }

    public function test_added_photos_join_the_list_with_caption_order_and_remove(): void
    {
        $builtInCount = LoginSlide::builtIn()->count();
        $this->actingAs($this->admin)->post(route('admin.login-photos.store'), [
            'photos' => [UploadedFile::fake()->image('one.jpg', 1200, 700), UploadedFile::fake()->image('two.png', 1200, 700)],
            'caption' => 'San Carlos Campus',
        ])->assertRedirect()->assertSessionHas('status', '2 photos added to the login page.');
        $this->assertSame($builtInCount + 2, LoginSlide::count());

        [$first, $second] = LoginSlide::orderByDesc('position')->take(2)->get()->reverse()->values()->all();
        $this->assertFileExists(public_path($first->path));
        $this->assertStringStartsWith(LoginSlide::DIR.'/', $first->path);

        // Caption and order.
        $this->actingAs($this->admin)->put(route('admin.login-photos.update', $second), ['caption' => 'Lingayen Campus'])->assertRedirect();
        $this->actingAs($this->admin)->post(route('admin.login-photos.move', $second), ['direction' => 'up'])->assertRedirect();
        $this->assertSame([$second->id, $first->id], LoginSlide::orderByDesc('position')->take(2)->get()->reverse()->pluck('id')->values()->all());

        auth()->logout();
        $this->get(route('login'))->assertOk()
            ->assertSeeInOrder([$second->url(), $first->url()], false)->assertSee('Lingayen Campus')
            ->assertDontSee('Partner with')->assertDontSee('Your Progress');

        // Removing an upload deletes its file; removing a built-in one keeps its file.
        $path = public_path($first->path);
        $this->actingAs($this->admin)->delete(route('admin.login-photos.destroy', $first))->assertRedirect();
        $this->assertFileDoesNotExist($path);
        $builtIn = LoginSlide::orderBy('position')->first();
        $this->actingAs($this->admin)->delete(route('admin.login-photos.destroy', $builtIn))->assertRedirect();
        $this->assertFileExists(public_path($builtIn->path));
        auth()->logout();
        $this->get(route('login'))->assertOk()->assertDontSee($builtIn->url(), false);
    }

    public function test_only_photos_can_be_uploaded(): void
    {
        $this->actingAs($this->admin)->post(route('admin.login-photos.store'), ['photos' => [UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf')]])
            ->assertSessionHasErrors('photos.0');
        $this->assertSame(0, LoginSlide::count());
    }
}
