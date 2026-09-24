<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ReservationController extends Controller
{
    /**
     * Show reservation details when a QR code is scanned.
     */
    public function show(Request $request, $qrCode)
    {
        $user = $request->user();

        // Find the reservation by QR code
        $reservation = Reservation::where('qr_code', $qrCode)
            ->with([
                'order.event.location',
                'order.event.user',
                'order.reservations',
                'order.tickets'
            ])
            ->firstOrFail();

        $event = $reservation->order->event;

        // Check if user has access to this event (owner, manager, or staff)
        $hasAccess = $event->user_id === $user->id ||
                     $event->users()->where('user_id', $user->id)->exists();

        if (!$hasAccess) {
            abort(403, 'Nemáte oprávnenie na zobrazenie tejto rezervácie.');
        }

        // Determine user role
        $userRole = 'owner';
        if ($event->user_id !== $user->id) {
            $pivotRole = $event->users()->where('user_id', $user->id)->first();
            $userRole = $pivotRole ? $pivotRole->pivot->role : 'staff';
        }

        // Prepare tickets data with amounts from pivot
        $tickets = $reservation->order->tickets->map(function ($ticket) {
            return [
                'id' => $ticket->id,
                'title' => $ticket->title,
                'price' => $ticket->price,
                'amount' => $ticket->pivot->amount ?? 1,
            ];
        });

        // Prepare reservations data
        $reservations = $reservation->order->reservations->map(function ($res) {
            return [
                'id' => $res->id,
                'seat_number' => $res->seat_number,
                'guest_name' => $res->guest_name,
                'qr_code' => $res->qr_code,
                'additional_info' => $res->computeAdditionalInformation(),
            ];
        });

        return Inertia::render('reservation/Show', [
            'reservation' => [
                'id' => $reservation->id,
                'seat_number' => $reservation->seat_number,
                'guest_name' => $reservation->guest_name,
                'qr_code' => $reservation->qr_code,
                'additional_info' => $reservation->computeAdditionalInformation(),
            ],
            'order' => [
                'id' => $reservation->order->id,
                'name' => $reservation->order->name,
                'email' => $reservation->order->email,
                'phone' => $reservation->order->phone,
                'status' => $reservation->order->status,
                'variable_symbol' => $reservation->order->variable_symbol,
                'payment_note' => $reservation->order->payment_note,
            ],
            'event' => [
                'id' => $event->id,
                'title' => $event->title,
                'start_time' => $event->start_time,
                'url_slug' => $event->url_slug,
                'seats_total' => $event->seats_total,
                'contact_name' => $event->contact_name,
                'contact_email' => $event->contact_email,
                'contact_phone' => $event->contact_phone,
                'bank_account' => $event->bank_account,
                'location' => $event->location ? $event->location->name : '',
                'multiple_reservations_per_ticket' => $event->multiple_reservations_per_ticket ?? false,
            ],
            'location' => $event->location ? [
                'id' => $event->location->id,
                'address' => $event->location->address,
            ] : null,
            'tickets' => $tickets,
            'reservations' => $reservations,
            'userRole' => $userRole,
        ]);
    }

    /**
     * Seat management page: the seat map with all active reservations, used to
     * move guests to free seats or swap two guests.
     */
    public function seats(Request $request, Event $event)
    {
        if (! in_array($event->roleFor($request->user()), ['owner', 'manager'], true)) {
            abort(403, 'Nemáte oprávnenie na zmenu miest.');
        }

        $reservations = Reservation::with('order')
            ->whereHas('order', function ($query) use ($event) {
                $query->where('event_id', $event->id)
                      ->where('status', '!=', 'cancelled');
            })
            ->orderBy('seat_number')
            ->get()
            ->map(fn (Reservation $reservation) => [
                'id' => $reservation->id,
                'seat_number' => $reservation->seat_number,
                'guest_name' => $reservation->guest_name,
                'order_name' => $reservation->order->name,
                'order_status' => $reservation->order->status,
            ]);

        return Inertia::render('event/Seats', [
            'event' => $event->only(['id', 'title', 'url_slug', 'seats_total']),
            'svgMap' => $event->location?->svg_map,
            'reservations' => $reservations,
        ]);
    }

    /**
     * Move a guest to another seat. If an active reservation already holds the
     * target seat, the two guests swap seats.
     */
    public function changeSeat(Request $request, Event $event, Reservation $reservation)
    {
        if (! in_array($event->roleFor($request->user()), ['owner', 'manager'], true)) {
            abort(403, 'Nemáte oprávnenie na zmenu miest.');
        }

        if ($reservation->order->event_id !== $event->id) {
            abort(404);
        }

        if ($reservation->order->status === 'cancelled') {
            return back()->with('error', 'Miesta v zrušenej objednávke nie je možné meniť.');
        }

        $validSeats = $event->location?->seatNumbers();

        $validated = $request->validate([
            'seat_number' => array_filter(['required', 'integer', 'min:0', $validSeats !== null ? Rule::in($validSeats) : null]),
        ], [
            'seat_number.in' => 'Toto miesto neexistuje na mape sedenia.',
        ]);

        $targetSeat = (int) $validated['seat_number'];

        if ($targetSeat === $reservation->seat_number) {
            return back()->withErrors(['seat_number' => 'Hosť už sedí na tomto mieste.']);
        }

        $message = DB::transaction(function () use ($event, $reservation, $targetSeat) {
            // Same lock as order creation, so a new order cannot grab the seat mid-move
            Event::whereKey($event->id)->lockForUpdate()->first();

            $reservation->refresh();
            $fromSeat = $reservation->seat_number;

            $occupant = Reservation::where('seat_number', $targetSeat)
                ->whereKeyNot($reservation->id)
                ->whereHas('order', function ($query) use ($event) {
                    $query->where('event_id', $event->id)
                          ->where('status', '!=', 'cancelled');
                })
                ->first();

            $reservation->update(['seat_number' => $targetSeat]);

            if ($occupant) {
                $occupant->update(['seat_number' => $fromSeat]);

                return "Hostia {$reservation->guest_name} a {$occupant->guest_name} si vymenili miesta {$fromSeat} a {$targetSeat}.";
            }

            return "Hosť {$reservation->guest_name} bol presunutý z miesta {$fromSeat} na miesto {$targetSeat}.";
        });

        return back()->with('success', $message);
    }
}
