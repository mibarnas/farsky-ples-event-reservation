<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Location;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EventCreateTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        // seatmap.svg has seats 0-49.
        $this->location = Location::factory()->create(['svg_map' => '/sedenie/seatmap.svg', 'places_total' => 50]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Farský ples',
            'url_slug' => 'farsky-ples',
            'start_time' => now()->addMonth()->format('Y-m-d\TH:i'),
            'registration_start' => now()->format('Y-m-d\TH:i'),
            'registration_end' => now()->addWeeks(3)->format('Y-m-d\TH:i'),
            'location_id' => $this->location->id,
            'contact_name' => 'Organizátor',
            'contact_email' => 'org@example.com',
            'bank_account' => 'SK0000000000000000000000',
            'multiple_reservations_per_ticket' => true,
            'tables_enabled' => true,
            'tables' => [
                ['name' => 'Murano', 'seats' => [3, 1, 2]],
                ['name' => 'Burano', 'seats' => [4, 5]],
            ],
            'tickets' => [
                ['title' => 'Vstupenka', 'price' => 25, 'reservations' => 1],
                ['title' => 'Pár', 'price' => 45, 'reservations' => 2],
            ],
        ], $overrides);
    }

    private function reserve(Event $event, int $seat): Reservation
    {
        $order = Order::create([
            'event_id' => $event->id,
            'status' => 'paid',
            'name' => 'Customer',
            'email' => 'customer@example.com',
            'phone' => '0902000000',
            'variable_symbol' => (string) random_int(1000000000, 9999999999),
            'url_slug' => (string) Str::uuid(),
        ]);

        return Reservation::create(['order_id' => $order->id, 'seat_number' => $seat, 'guest_name' => "Guest {$seat}"]);
    }

    public function test_create_page_lists_locations_with_their_seats()
    {
        $this->actingAs($this->user)
            ->get(route('event.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('EventCreate')
                ->where('locations.0.svg_map', '/sedenie/seatmap.svg')
                ->has('locations.0.valid_seats', 50));
    }

    public function test_event_is_created_with_tables_and_tickets()
    {
        $this->actingAs($this->user)
            ->post(route('event.store'), $this->payload())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('event.manage', 'farsky-ples'));

        $event = Event::where('url_slug', 'farsky-ples')->firstOrFail();

        $this->assertSame([
            ['name' => 'Murano', 'seats' => [1, 2, 3]],
            ['name' => 'Burano', 'seats' => [4, 5]],
        ], json_decode($event->additional_information, true)['tables']);
        $this->assertSame(50, $event->seats_total);
        $this->assertEqualsCanonicalizing(['Vstupenka', 'Pár'], $event->tickets()->pluck('title')->all());
        $this->assertSame(2, $event->tickets()->where('title', 'Pár')->value('reservations'));
    }

    public function test_reservation_shows_table_name_from_wizard_setup()
    {
        $this->actingAs($this->user)->post(route('event.store'), $this->payload());
        $event = Event::where('url_slug', 'farsky-ples')->firstOrFail();

        $this->assertSame('Stôl Burano', $this->reserve($event, 5)->computeAdditionalInformation());
        $this->assertSame('', $this->reserve($event, 30)->computeAdditionalInformation());
    }

    public function test_event_without_tables_has_no_additional_information()
    {
        $this->actingAs($this->user)
            ->post(route('event.store'), $this->payload(['tables_enabled' => false, 'tables' => [], 'tickets' => []]))
            ->assertSessionHasNoErrors();

        $event = Event::where('url_slug', 'farsky-ples')->firstOrFail();
        $this->assertNull($event->additional_information);
        $this->assertSame(0, $event->tickets()->count());
    }

    public function test_seat_in_two_tables_is_rejected()
    {
        $this->actingAs($this->user)
            ->post(route('event.store'), $this->payload(['tables' => [
                ['name' => 'Murano', 'seats' => [1, 2]],
                ['name' => 'Burano', 'seats' => [2, 3]],
            ]]))
            ->assertSessionHasErrors('tables.1.seats');

        $this->assertDatabaseCount('events', 0);
    }

    public function test_seat_missing_from_the_map_is_rejected()
    {
        $this->actingAs($this->user)
            ->post(route('event.store'), $this->payload(['tables' => [
                ['name' => 'Murano', 'seats' => [49, 50]],
            ]]))
            ->assertSessionHasErrors('tables.0.seats');
    }

    public function test_duplicate_table_names_are_rejected()
    {
        $this->actingAs($this->user)
            ->post(route('event.store'), $this->payload(['tables' => [
                ['name' => 'Murano', 'seats' => [1]],
                ['name' => 'Murano', 'seats' => [2]],
            ]]))
            ->assertSessionHasErrors('tables.0.name');
    }

    public function test_invalid_ticket_is_rejected_and_nothing_is_saved()
    {
        $this->actingAs($this->user)
            ->post(route('event.store'), $this->payload(['tickets' => [
                ['title' => '', 'price' => -1, 'reservations' => 0],
            ]]))
            ->assertSessionHasErrors(['tickets.0.title', 'tickets.0.price', 'tickets.0.reservations']);

        $this->assertDatabaseCount('events', 0);
        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_guest_cannot_create_event()
    {
        $this->post(route('event.store'), $this->payload())->assertRedirect(route('login'));
        $this->assertDatabaseCount('events', 0);
    }

    public function test_edit_page_receives_current_tables()
    {
        $event = Event::factory()->create([
            'user_id' => $this->user->id,
            'location_id' => $this->location->id,
            'additional_information' => json_encode(['tables' => [['name' => 'Lido', 'seats' => [7, 8]]]]),
        ]);

        $this->actingAs($this->user)
            ->get(route('event.edit', $event->url_slug))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('EventEdit')
                ->where('tables.0.name', 'Lido')
                ->where('tables.0.seats', [7, 8]));
    }

    private function updatePayload(Event $event, array $overrides = []): array
    {
        return array_merge([
            'title' => $event->title,
            'url_slug' => $event->url_slug,
            'start_time' => now()->addMonth()->format('Y-m-d\TH:i'),
            'registration_start' => now()->format('Y-m-d\TH:i'),
            'registration_end' => now()->addWeeks(3)->format('Y-m-d\TH:i'),
            'contact_name' => $event->contact_name,
            'contact_email' => $event->contact_email,
            'bank_account' => $event->bank_account,
        ], $overrides);
    }

    public function test_update_can_rename_and_remove_tables_keeping_other_settings()
    {
        $event = Event::factory()->create([
            'user_id' => $this->user->id,
            'location_id' => $this->location->id,
            'additional_information' => json_encode(['tables' => [['name' => 'Lido', 'seats' => [7]]], 'other' => 'kept']),
        ]);

        $this->actingAs($this->user)
            ->put(route('event.update', $event->url_slug), $this->updatePayload($event, [
                'tables_enabled' => true,
                'tables' => [['name' => 'Lido Nord', 'seats' => [7, 9]]],
            ]))
            ->assertSessionHasNoErrors();

        $config = json_decode($event->fresh()->additional_information, true);
        $this->assertSame([['name' => 'Lido Nord', 'seats' => [7, 9]]], $config['tables']);
        $this->assertSame('kept', $config['other']);

        $this->actingAs($this->user)
            ->put(route('event.update', $event->url_slug), $this->updatePayload($event, ['tables_enabled' => false]))
            ->assertSessionHasNoErrors();

        $this->assertSame(['other' => 'kept'], json_decode($event->fresh()->additional_information, true));
    }

    public function test_update_without_table_fields_leaves_tables_untouched()
    {
        $tables = json_encode(['tables' => [['name' => 'Lido', 'seats' => [7]]]]);
        $event = Event::factory()->create([
            'user_id' => $this->user->id,
            'location_id' => $this->location->id,
            'additional_information' => $tables,
        ]);

        $this->actingAs($this->user)
            ->put(route('event.update', $event->url_slug), $this->updatePayload($event))
            ->assertSessionHasNoErrors();

        $this->assertSame($tables, $event->fresh()->additional_information);
    }
}
