<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReservationSeatChangeTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->event = Event::factory()->create(['seats_total' => 50]);
    }

    private function reserve(Event $event, int $seat, string $status = 'paid'): Reservation
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

        return Reservation::create(['order_id' => $order->id, 'seat_number' => $seat, 'guest_name' => "Guest {$seat}"]);
    }

    private function changeSeat(Reservation $reservation, $seat, ?User $user = null, ?Event $event = null)
    {
        return $this->actingAs($user ?? $this->event->user)
            ->put(route('reservation.change-seat', [
                'event' => ($event ?? $this->event)->url_slug,
                'reservation' => $reservation->id,
            ]), ['seat_number' => $seat]);
    }

    public function test_owner_can_open_seat_management_page()
    {
        $this->reserve($this->event, 5);
        $this->reserve($this->event, 6, 'cancelled');

        $this->actingAs($this->event->user)
            ->get(route('event.seats', $this->event->url_slug))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('event/Seats')
                ->has('reservations', 1)
                ->where('reservations.0.seat_number', 5));
    }

    public function test_staff_cannot_open_seat_management_page()
    {
        $staff = User::factory()->create();
        $this->event->users()->attach($staff->id, ['role' => 'staff']);

        $this->actingAs($staff)
            ->get(route('event.seats', $this->event->url_slug))
            ->assertForbidden();
    }

    public function test_guest_can_be_moved_to_a_free_seat()
    {
        $reservation = $this->reserve($this->event, 5);

        $this->changeSeat($reservation, 12)->assertSessionHas('success');

        $this->assertSame(12, $reservation->fresh()->seat_number);
    }

    public function test_guests_swap_when_target_seat_is_taken()
    {
        $first = $this->reserve($this->event, 5);
        $second = $this->reserve($this->event, 12, 'pending');

        $this->changeSeat($first, 12)->assertSessionHas('success');

        $this->assertSame(12, $first->fresh()->seat_number);
        $this->assertSame(5, $second->fresh()->seat_number);
    }

    public function test_cancelled_reservation_on_target_seat_is_not_swapped()
    {
        $reservation = $this->reserve($this->event, 5);
        $cancelled = $this->reserve($this->event, 12, 'cancelled');

        $this->changeSeat($reservation, 12)->assertSessionHas('success');

        $this->assertSame(12, $reservation->fresh()->seat_number);
        $this->assertSame(12, $cancelled->fresh()->seat_number);
    }

    public function test_reservation_on_another_event_is_not_swapped()
    {
        $reservation = $this->reserve($this->event, 5);
        $otherEventReservation = $this->reserve(Event::factory()->create(), 12);

        $this->changeSeat($reservation, 12)->assertSessionHas('success');

        $this->assertSame(12, $otherEventReservation->fresh()->seat_number);
    }

    public function test_manager_can_change_seats()
    {
        $manager = User::factory()->create();
        $this->event->users()->attach($manager->id, ['role' => 'manager']);
        $reservation = $this->reserve($this->event, 5);

        $this->changeSeat($reservation, 6, $manager)->assertSessionHas('success');

        $this->assertSame(6, $reservation->fresh()->seat_number);
    }

    public function test_staff_cannot_change_seats()
    {
        $staff = User::factory()->create();
        $this->event->users()->attach($staff->id, ['role' => 'staff']);
        $reservation = $this->reserve($this->event, 5);

        $this->changeSeat($reservation, 6, $staff)->assertForbidden();

        $this->assertSame(5, $reservation->fresh()->seat_number);
    }

    public function test_unrelated_user_cannot_change_seats()
    {
        $reservation = $this->reserve($this->event, 5);

        $this->changeSeat($reservation, 6, User::factory()->create())->assertForbidden();
    }

    public function test_reservation_must_belong_to_the_event_in_the_url()
    {
        $otherEvent = Event::factory()->create(['user_id' => $this->event->user_id]);
        $reservation = $this->reserve($otherEvent, 5);

        $this->changeSeat($reservation, 6, null, $this->event)->assertNotFound();

        $this->assertSame(5, $reservation->fresh()->seat_number);
    }

    public function test_seat_must_exist_on_the_map()
    {
        $reservation = $this->reserve($this->event, 5);

        // seatmap.svg defines seats 0-49
        $this->changeSeat($reservation, 999)->assertSessionHasErrors('seat_number');

        $this->assertSame(5, $reservation->fresh()->seat_number);
    }

    public function test_seats_of_cancelled_order_cannot_be_changed()
    {
        $reservation = $this->reserve($this->event, 5, 'cancelled');

        $this->changeSeat($reservation, 6)->assertSessionHas('error');

        $this->assertSame(5, $reservation->fresh()->seat_number);
    }
}
