<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $root;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.admin_email' => 'root@example.com']);
        $this->root = User::factory()->create(['email' => 'root@example.com']);
    }

    private function setAdmin(User $actor, User $target, bool $isAdmin)
    {
        return $this->actingAs($actor)->put(route('admin.user-admin', $target), ['is_admin' => $isAdmin]);
    }

    public function test_root_admin_can_promote_a_user_who_then_gets_dashboard_access()
    {
        $user = User::factory()->create();

        $this->setAdmin($this->root, $user, true)->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertTrue($user->fresh()->isAdmin());
        $this->actingAs($user->fresh())->get(route('admin'))->assertOk();
    }

    public function test_promoted_admin_can_promote_and_demote_others()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $other = User::factory()->create();

        $this->setAdmin($admin, $other, true);
        $this->assertTrue($other->fresh()->is_admin);

        $this->setAdmin($admin, $other, false);
        $this->assertFalse($other->fresh()->is_admin);
        $this->actingAs($other->fresh())->get(route('admin'))->assertForbidden();
    }

    public function test_root_admin_cannot_be_demoted()
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->setAdmin($admin, $this->root, false)->assertSessionHas('error');

        $this->assertTrue($this->root->fresh()->isAdmin());
    }

    public function test_admin_cannot_demote_themselves()
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->setAdmin($admin, $admin, false)->assertSessionHas('error');

        $this->assertTrue($admin->fresh()->is_admin);
    }

    public function test_regular_users_cannot_promote_anyone()
    {
        $user = User::factory()->create();

        $this->setAdmin($user, $user, true)->assertForbidden();

        $this->assertFalse((bool) $user->fresh()->is_admin);
    }

    public function test_is_admin_cannot_be_set_through_profile_update()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => 'Name',
            'email' => $user->email,
            'is_admin' => true,
        ]);

        $this->assertFalse((bool) $user->fresh()->is_admin);
    }

    public function test_promoted_admin_still_cannot_edit_events_they_do_not_collaborate_on()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $event = Event::factory()->create();

        $this->actingAs($admin);
        $this->get(route('event.manage', $event->url_slug))->assertOk();
        $this->get(route('event.edit', $event->url_slug))->assertForbidden();
        $this->put(route('event.update', $event->url_slug), ['title' => 'Hacked'])->assertForbidden();
        $this->assertNotSame('Hacked', $event->fresh()->title);
    }
}
