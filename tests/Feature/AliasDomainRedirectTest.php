<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AliasDomainRedirectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.url' => 'https://listky.example.sk',
            'app.alias_domains' => ['example.sk', 'www.example.sk'],
            'app.admin_email' => 'admin@example.com',
        ]);
    }

    public function test_alias_domain_redirects_to_selected_event()
    {
        $event = Event::factory()->create();
        Setting::putValue(Setting::ALIAS_EVENT_ID, (string) $event->id);

        $this->get('http://example.sk/')
            ->assertRedirect("https://listky.example.sk/event/{$event->url_slug}");

        $this->get('http://www.example.sk/anything/else')
            ->assertRedirect("https://listky.example.sk/event/{$event->url_slug}");
    }

    public function test_alias_redirect_is_not_cacheable()
    {
        $event = Event::factory()->create();
        Setting::putValue(Setting::ALIAS_EVENT_ID, (string) $event->id);

        $response = $this->get('http://example.sk/');
        $response->assertStatus(302);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_ended_event_redirects_to_current_alias_event()
    {
        $old = Event::factory()->create(['start_time' => now()->subMonths(2), 'redirect_when_ended' => true]);
        $current = Event::factory()->create();
        Setting::putValue(Setting::ALIAS_EVENT_ID, (string) $current->id);

        $response = $this->get("https://listky.example.sk/event/{$old->url_slug}");
        $response->assertRedirect("https://listky.example.sk/event/{$current->url_slug}");
        $response->assertStatus(302);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_ended_event_stays_visible_to_its_organizer()
    {
        $old = Event::factory()->create(['start_time' => now()->subMonths(2), 'redirect_when_ended' => true]);
        $current = Event::factory()->create();
        Setting::putValue(Setting::ALIAS_EVENT_ID, (string) $current->id);

        $this->actingAs($old->user)
            ->get("https://listky.example.sk/event/{$old->url_slug}")
            ->assertOk();
    }

    public function test_upcoming_event_is_not_redirected()
    {
        $other = Event::factory()->create(['redirect_when_ended' => true]);
        $current = Event::factory()->create();
        Setting::putValue(Setting::ALIAS_EVENT_ID, (string) $current->id);

        $this->get("https://listky.example.sk/event/{$other->url_slug}")->assertOk();
    }

    public function test_ended_event_is_not_redirected_without_alias_selection()
    {
        $old = Event::factory()->create(['start_time' => now()->subMonths(2), 'redirect_when_ended' => true]);

        $this->get("https://listky.example.sk/event/{$old->url_slug}")->assertOk();
    }

    public function test_ended_event_is_not_redirected_when_switched_off()
    {
        $old = Event::factory()->create(['start_time' => now()->subMonths(2)]);
        $current = Event::factory()->create();
        Setting::putValue(Setting::ALIAS_EVENT_ID, (string) $current->id);

        $this->get("https://listky.example.sk/event/{$old->url_slug}")->assertOk();
    }

    public function test_admin_can_open_any_event_dashboard_and_toggle_redirect()
    {
        $admin = User::factory()->create(['email' => 'admin@example.com']);
        $event = Event::factory()->create();

        $this->actingAs($admin)
            ->get(route('event.manage', $event->url_slug))
            ->assertOk();

        $this->actingAs($admin)
            ->put(route('event.redirect-when-ended', $event->url_slug), ['redirect_when_ended' => true])
            ->assertSessionHasNoErrors();
        $this->assertTrue($event->fresh()->redirect_when_ended);
    }

    public function test_admin_still_cannot_edit_foreign_event()
    {
        $admin = User::factory()->create(['email' => 'admin@example.com']);
        $event = Event::factory()->create();

        $this->actingAs($admin)
            ->get(route('event.edit', $event->url_slug))
            ->assertForbidden();
    }

    public function test_owner_can_toggle_redirect_but_staff_and_strangers_cannot()
    {
        $event = Event::factory()->create();
        $staff = User::factory()->create();
        $event->users()->attach($staff->id, ['role' => 'staff']);

        $this->actingAs($event->user)
            ->put(route('event.redirect-when-ended', $event->url_slug), ['redirect_when_ended' => true])
            ->assertSessionHasNoErrors();
        $this->assertTrue($event->fresh()->redirect_when_ended);

        $this->actingAs($staff)
            ->put(route('event.redirect-when-ended', $event->url_slug), ['redirect_when_ended' => false])
            ->assertForbidden();

        $this->actingAs(User::factory()->create())
            ->get(route('event.manage', $event->url_slug))
            ->assertForbidden();

        $this->assertTrue($event->fresh()->redirect_when_ended);
    }

    public function test_alias_domain_falls_back_to_main_homepage_without_selection()
    {
        $this->get('http://example.sk/')->assertRedirect('https://listky.example.sk');
    }

    public function test_alias_domain_falls_back_when_selected_event_was_deleted()
    {
        Setting::putValue(Setting::ALIAS_EVENT_ID, '999999');

        $this->get('http://example.sk/')->assertRedirect('https://listky.example.sk');
    }

    public function test_main_domain_is_not_redirected()
    {
        $this->get('https://listky.example.sk/')->assertOk();
    }

    public function test_admin_can_select_and_clear_alias_event()
    {
        $admin = User::factory()->create(['email' => 'admin@example.com']);
        $event = Event::factory()->create();

        $this->actingAs($admin)
            ->put(route('admin.alias-event'), ['event_id' => $event->id])
            ->assertSessionHasNoErrors();
        $this->assertSame($event->id, Setting::aliasEvent()?->id);

        $this->actingAs($admin)
            ->put(route('admin.alias-event'), ['event_id' => null])
            ->assertSessionHasNoErrors();
        $this->assertNull(Setting::aliasEvent());
    }

    public function test_admin_cannot_select_nonexistent_event()
    {
        $admin = User::factory()->create(['email' => 'admin@example.com']);

        $this->actingAs($admin)
            ->put(route('admin.alias-event'), ['event_id' => 999999])
            ->assertSessionHasErrors('event_id');
    }

    public function test_regular_users_cannot_select_alias_event()
    {
        $event = Event::factory()->create();

        $this->actingAs(User::factory()->create())
            ->put(route('admin.alias-event'), ['event_id' => $event->id])
            ->assertForbidden();

        $this->assertNull(Setting::aliasEvent());
    }
}
