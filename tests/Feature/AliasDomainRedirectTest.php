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
