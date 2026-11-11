<?php

namespace Tests\Feature;

use App\Mail\TestMail;
use App\Models\Event;
use App\Models\Location;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class SeatPlanEditorTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        // seatmap.svg defines seats 0-49
        $this->event = Event::factory()->create([
            'seats_total' => 50,
            'location_id' => Location::factory()->create(['svg_map' => '/sedenie/seatmap.svg']),
        ]);
    }

    private function reserve(int $seat, string $status = 'paid'): Reservation
    {
        $order = Order::create([
            'event_id' => $this->event->id,
            'status' => $status,
            'name' => 'Customer',
            'email' => 'customer@example.com',
            'phone' => '0902000000',
            'variable_symbol' => (string) random_int(1000000000, 9999999999),
            'url_slug' => (string) Str::uuid(),
        ]);

        return Reservation::create(['order_id' => $order->id, 'seat_number' => $seat, 'guest_name' => "Guest {$seat}"]);
    }

    private function saveSeats(array $seats, ?User $user = null)
    {
        return $this->actingAs($user ?? $this->event->user)
            ->put(route('event.seat-plan.update', $this->event->url_slug), ['extra_seats' => $seats]);
    }

    public function test_new_location_map_is_available()
    {
        $location = Location::where('svg_map', '/sedenie/vajnory-parket-dole.svg')->first();

        $this->assertNotNull($location);
        $this->assertSame(range(1, 144), collect($location->seatNumbers())->sort()->values()->all());
    }

    public function test_owner_can_open_editor()
    {
        $this->actingAs($this->event->user)
            ->get(route('event.seat-plan', $this->event->url_slug))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('event/SeatPlan')
                ->has('baseSeats', 50)
                ->has('extraSeats', 0));
    }

    public function test_staff_cannot_edit_seat_plan()
    {
        $staff = User::factory()->create();
        $this->event->users()->attach($staff->id, ['role' => 'staff']);

        $this->actingAs($staff)->get(route('event.seat-plan', $this->event->url_slug))->assertForbidden();
        $this->saveSeats([['seat' => 50, 'x' => 10, 'y' => 10]], $staff)->assertForbidden();
    }

    public function test_added_seat_is_stored_counted_and_bookable()
    {
        $this->saveSeats([['seat' => 50, 'x' => 640, 'y' => 400]])->assertSessionHas('success');

        $event = $this->event->fresh();
        $this->assertSame([['seat' => 50, 'x' => 640.0, 'y' => 400.0]], $event->extraSeats());
        $this->assertSame(51, $event->seats_total);
        $this->assertContains(50, $event->seatNumbers());

        // Guests can be moved onto the new seat
        $reservation = $this->reserve(5);
        $this->actingAs($event->user)
            ->put(route('reservation.change-seat', ['event' => $event->url_slug, 'reservation' => $reservation->id]), ['seat_number' => 50])
            ->assertSessionHas('success');
        $this->assertSame(50, $reservation->fresh()->seat_number);
    }

    public function test_seat_that_exists_on_the_map_is_rejected()
    {
        $this->saveSeats([['seat' => 10, 'x' => 640, 'y' => 400]])->assertSessionHasErrors('extra_seats');

        $this->assertSame([], $this->event->fresh()->extraSeats());
    }

    public function test_seat_outside_the_map_is_rejected()
    {
        // seatmap.svg has viewBox="0 0 700 480"
        $this->saveSeats([['seat' => 50, 'x' => 650, 'y' => 900]])->assertSessionHasErrors('extra_seats');
        $this->saveSeats([['seat' => 50, 'x' => -5, 'y' => 100]])->assertSessionHasErrors('extra_seats');

        $this->assertSame([], $this->event->fresh()->extraSeats());
        $this->assertSame(['x' => 0.0, 'y' => 0.0, 'width' => 700.0, 'height' => 480.0], $this->event->location->mapBounds());
    }

    public function test_duplicate_seat_numbers_are_rejected()
    {
        $this->saveSeats([
            ['seat' => 60, 'x' => 640, 'y' => 400],
            ['seat' => 60, 'x' => 640, 'y' => 440],
        ])->assertSessionHasErrors('extra_seats.1.seat');
    }

    public function test_reserved_added_seat_cannot_be_removed()
    {
        $this->saveSeats([['seat' => 50, 'x' => 640, 'y' => 400]]);
        $this->reserve(50);

        $this->saveSeats([])->assertSessionHas('error');

        $this->assertCount(1, $this->event->fresh()->extraSeats());
    }

    public function test_removing_seat_drops_it_from_tables_and_seat_count()
    {
        $this->saveSeats([
            ['seat' => 50, 'x' => 640, 'y' => 400],
            ['seat' => 51, 'x' => 640, 'y' => 440],
        ]);
        $this->event->refresh();
        $this->event->update(['additional_information' => json_encode([
            ...json_decode($this->event->additional_information, true),
            'tables' => [['name' => 'A', 'seats' => [1, 50]], ['name' => 'B', 'seats' => [51]]],
        ])]);

        $this->saveSeats([['seat' => 50, 'x' => 640, 'y' => 400]])->assertSessionHas('success');

        $event = $this->event->fresh();
        $this->assertSame([['name' => 'A', 'seats' => [1, 50]]], $event->tableSetup());
        $this->assertSame(51, $event->seats_total);
    }

    public function test_test_mail_is_sent_to_logged_in_user()
    {
        Mail::fake();
        $staff = User::factory()->create();
        $this->event->users()->attach($staff->id, ['role' => 'staff']);

        $this->actingAs($staff)
            ->post(route('event.test-mail', $this->event->url_slug))
            ->assertSessionHas('success');

        Mail::assertSent(TestMail::class, fn (TestMail $mail) => $mail->hasTo($staff->email)
            && str_starts_with($mail->qrPng, "\x89PNG"));
    }

    public function test_test_mail_requires_event_access()
    {
        Mail::fake();

        $this->actingAs(User::factory()->create())
            ->post(route('event.test-mail', $this->event->url_slug))
            ->assertForbidden();

        Mail::assertNothingSent();
    }

    public function test_test_mail_renders()
    {
        $html = (new TestMail($this->event->load('location'), $this->event->user))->render();

        $this->assertStringContainsString('Testovací e-mail', $html);
        $this->assertStringContainsString($this->event->title, $html);
    }
}
