<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    private function openEvent(array $attributes = []): Event
    {
        return Event::factory()->create(array_merge([
            'seats_total' => 50,
            'registration_start' => now()->subDay(),
            'registration_end' => now()->addDay(),
            'start_time' => now()->addDays(2),
        ], $attributes));
    }

    private function orderPayload(Ticket $ticket, array $overrides = []): array
    {
        return array_merge([
            'name' => 'Ján Novák',
            'email' => 'jan@example.com',
            'phone' => '0902123456',
            'tickets' => [$ticket->id => 1],
            'seats' => [3, 4],
            'guests' => ['Ján Novák', 'Mária Nováková'],
        ], $overrides);
    }

    private function makeOrder(Event $event, string $status, array $seats): Order
    {
        $order = Order::create([
            'event_id' => $event->id,
            'status' => $status,
            'name' => 'Existing',
            'email' => 'existing@example.com',
            'phone' => '0902000000',
            'variable_symbol' => (string) random_int(1000000000, 9999999999),
            'url_slug' => (string) Str::uuid(),
        ]);

        foreach ($seats as $seat) {
            Reservation::create(['order_id' => $order->id, 'seat_number' => $seat, 'guest_name' => "Guest {$seat}"]);
        }

        return $order;
    }

    public function test_order_page_is_available_during_registration()
    {
        $event = $this->openEvent();

        $this->get(route('order.create', $event->url_slug))->assertOk();
    }

    public function test_order_page_redirects_after_registration_ended()
    {
        $event = $this->openEvent(['registration_end' => now()->subHour()]);

        $this->get(route('order.create', $event->url_slug))
            ->assertRedirect(route('event.show', $event->url_slug))
            ->assertSessionHas('error');
    }

    public function test_order_page_redirects_before_registration_started()
    {
        $event = $this->openEvent(['registration_start' => now()->addHour()]);

        $this->get(route('order.create', $event->url_slug))
            ->assertRedirect(route('event.show', $event->url_slug));
    }

    public function test_order_page_redirects_once_event_has_started()
    {
        $event = $this->openEvent([
            'start_time' => now()->subHour(),
            'registration_end' => now()->addDay(),
        ]);

        $this->get(route('order.create', $event->url_slug))
            ->assertRedirect(route('event.show', $event->url_slug));
    }

    public function test_order_cannot_be_submitted_after_event_has_passed()
    {
        $event = $this->openEvent([
            'registration_start' => now()->subWeek(),
            'registration_end' => now()->subDays(3),
            'start_time' => now()->subDays(2),
        ]);
        $ticket = Ticket::factory()->create(['event_id' => $event->id, 'reservations' => 2]);

        $this->post(route('order.store', $event->url_slug), $this->orderPayload($ticket))
            ->assertRedirect(route('event.show', $event->url_slug));

        $this->assertSame(0, Order::count());
    }

    public function test_valid_order_is_created()
    {
        $event = $this->openEvent();
        $ticket = Ticket::factory()->create(['event_id' => $event->id, 'reservations' => 2]);

        $this->post(route('order.store', $event->url_slug), $this->orderPayload($ticket))
            ->assertSessionHasNoErrors();

        $order = Order::with('reservations', 'tickets')->sole();
        $this->assertSame('pending', $order->status);
        $this->assertEqualsCanonicalizing([3, 4], $order->reservations->pluck('seat_number')->all());
        $this->assertSame(1, $order->tickets->sole()->pivot->amount);
    }

    public function test_more_seats_than_paid_for_are_rejected()
    {
        $event = $this->openEvent();
        $ticket = Ticket::factory()->create(['event_id' => $event->id, 'reservations' => 1]);

        $this->post(route('order.store', $event->url_slug), $this->orderPayload($ticket, [
            'seats' => [3, 4, 5],
            'guests' => ['A', 'B', 'C'],
        ]))->assertSessionHasErrors('seats');

        $this->assertSame(0, Order::count());
    }

    public function test_ticket_from_another_event_is_rejected()
    {
        $event = $this->openEvent();
        $foreignTicket = Ticket::factory()->create(['event_id' => $this->openEvent()->id, 'reservations' => 2]);

        $this->post(route('order.store', $event->url_slug), $this->orderPayload($foreignTicket))
            ->assertSessionHasErrors('tickets');

        $this->assertSame(0, Order::count());
    }

    public function test_duplicate_seats_in_one_order_are_rejected()
    {
        $event = $this->openEvent();
        $ticket = Ticket::factory()->create(['event_id' => $event->id, 'reservations' => 2]);

        $this->post(route('order.store', $event->url_slug), $this->orderPayload($ticket, ['seats' => [3, 3]]))
            ->assertSessionHasErrors('seats.0');

        $this->assertSame(0, Order::count());
    }

    public function test_seat_not_on_the_map_is_rejected()
    {
        $event = $this->openEvent();
        $ticket = Ticket::factory()->create(['event_id' => $event->id, 'reservations' => 2]);

        // seatmap.svg defines seats 0-49
        $this->post(route('order.store', $event->url_slug), $this->orderPayload($ticket, ['seats' => [3, 999]]))
            ->assertSessionHasErrors('seats.1');

        $this->assertSame(0, Order::count());
    }

    public function test_guest_names_must_match_seats()
    {
        $event = $this->openEvent();
        $ticket = Ticket::factory()->create(['event_id' => $event->id, 'reservations' => 2]);

        $this->post(route('order.store', $event->url_slug), $this->orderPayload($ticket, ['guests' => ['Only one']]))
            ->assertSessionHasErrors('guests');
    }

    public function test_already_reserved_seat_is_rejected()
    {
        $event = $this->openEvent();
        $ticket = Ticket::factory()->create(['event_id' => $event->id, 'reservations' => 2]);
        $this->makeOrder($event, 'pending', [4]);

        $this->post(route('order.store', $event->url_slug), $this->orderPayload($ticket))
            ->assertSessionHasErrors('seats');

        $this->assertSame(1, Order::count());
    }

    public function test_cancelled_order_cannot_be_confirmed()
    {
        $event = $this->openEvent();
        $order = $this->makeOrder($event, 'cancelled', [4]);

        $this->actingAs($event->user)
            ->post(route('order.confirm.post', $order->url_slug))
            ->assertSessionHas('error');

        $this->assertSame('cancelled', $order->fresh()->status);
    }

    public function test_pending_order_can_be_confirmed()
    {
        $event = $this->openEvent();
        $order = $this->makeOrder($event, 'pending', [4]);

        $this->actingAs($event->user)
            ->post(route('order.confirm.post', $order->url_slug))
            ->assertSessionHas('success');

        $this->assertSame('paid', $order->fresh()->status);
    }

    public function test_outsider_cannot_confirm_order()
    {
        $event = $this->openEvent();
        $order = $this->makeOrder($event, 'pending', [4]);

        $this->actingAs(User::factory()->create())
            ->post(route('order.confirm.post', $order->url_slug))
            ->assertForbidden();
    }
}
