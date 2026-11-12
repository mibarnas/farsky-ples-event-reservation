<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.admin_email' => 'admin@example.com']);
        $this->admin = User::factory()->create(['email' => 'Admin@Example.com']);
    }

    private function reserve(Event $event, int $seat, string $status = 'paid'): void
    {
        $order = Order::create([
            'event_id' => $event->id,
            'status' => $status,
            'name' => 'Customer',
            'email' => 'customer@example.com',
            'phone' => '0902000000',
            'variable_symbol' => (string) random_int(1000000000, 9999999999),
            'url_slug' => (string) Str::uuid(),
        ]);

        Reservation::create(['order_id' => $order->id, 'seat_number' => $seat, 'guest_name' => "Guest {$seat}"]);
    }

    public function test_guests_are_redirected_to_login()
    {
        $this->get(route('admin'))->assertRedirect(route('login'));
    }

    public function test_regular_users_are_forbidden()
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin'))
            ->assertForbidden();
    }

    public function test_admin_can_view_dashboard_with_all_events_and_users()
    {
        $event = Event::factory()->create();
        Event::factory()->create();
        $this->reserve($event, 1);
        $this->reserve($event, 2);
        $this->reserve($event, 3, 'cancelled');

        $this->actingAs($this->admin)
            ->get(route('admin'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin')
                ->where('stats.events', 2)
                ->where('stats.orders', 2)
                ->where('stats.reservations', 2)
                ->has('events', 2)
                ->has('users', User::count())
                ->where('events', fn ($events) => collect($events)->firstWhere('id', $event->id)['reserved_seats'] === 2));
    }

    public function test_is_admin_flag_is_shared_with_frontend()
    {
        $this->actingAs($this->admin)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('auth.isAdmin', true));

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('auth.isAdmin', false));
    }

    public function test_admin_can_view_but_not_edit_events_they_are_not_collaborating_on()
    {
        $event = Event::factory()->create();

        $this->actingAs($this->admin);

        // Read-only dashboard access (role "admin"), but no editing
        $this->get(route('event.manage', $event->url_slug))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('event.user_role', 'admin')
                ->where('event.can_change_redirect', true)
                ->where('allUsers', []));
        $this->get(route('event.edit', $event->url_slug))->assertForbidden();
        $this->put(route('event.update', $event->url_slug), ['title' => 'Hacked'])->assertForbidden();

        $this->assertNotSame('Hacked', $event->fresh()->title);
    }

    public function test_admin_can_manage_event_once_added_as_collaborator()
    {
        $event = Event::factory()->create();
        $event->users()->attach($this->admin->id, ['role' => 'manager']);

        $this->actingAs($this->admin)
            ->get(route('event.manage', $event->url_slug))
            ->assertOk();
    }
}
