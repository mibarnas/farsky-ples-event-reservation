<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Validator as ValidatorInstance;
use Inertia\Inertia;

/**
 * Seat plan editor: adds seats to an event on top of its location's SVG map.
 * The seats are stored per event in additional_information.extra_seats, so the
 * shared location map (and other events using it) stay untouched.
 */
class SeatPlanController extends Controller
{
    public function edit(Request $request, Event $event)
    {
        $this->authorizeManage($request, $event);

        return Inertia::render('event/SeatPlan', [
            'event' => $event->only(['id', 'title', 'url_slug', 'seats_total']),
            'svgMap' => $event->location?->svg_map,
            'tables' => $event->tableSetup(),
            'baseSeats' => $event->location?->seatNumbers() ?? [],
            'extraSeats' => $event->extraSeats(),
            'reservedSeats' => $this->reservedSeats($event),
        ]);
    }

    public function update(Request $request, Event $event)
    {
        $this->authorizeManage($request, $event);

        $validator = Validator::make($request->all(), [
            'extra_seats' => 'present|array|max:1000',
            'extra_seats.*.seat' => 'required|integer|min:0|max:99999|distinct',
            'extra_seats.*.x' => 'required|numeric|between:-100000,100000',
            'extra_seats.*.y' => 'required|numeric|between:-100000,100000',
        ], [
            'extra_seats.*.seat.distinct' => 'Číslo miesta :input je použité viackrát.',
        ]);

        $baseSeats = $event->location?->seatNumbers() ?? [];
        $newSeats = collect((array) $request->input('extra_seats', []))
            ->map(fn ($seat) => [
                'seat' => (int) ($seat['seat'] ?? 0),
                'x' => round((float) ($seat['x'] ?? 0), 1),
                'y' => round((float) ($seat['y'] ?? 0), 1),
            ])
            ->sortBy('seat')
            ->values()
            ->all();

        $bounds = $event->location?->mapBounds();

        $validator->after(function (ValidatorInstance $validator) use ($baseSeats, $newSeats, $bounds) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            // A seat outside the map would be invisible and impossible to click
            if ($bounds) {
                $outside = array_column(array_filter($newSeats, fn ($seat) => $seat['x'] < $bounds['x']
                    || $seat['x'] > $bounds['x'] + $bounds['width']
                    || $seat['y'] < $bounds['y']
                    || $seat['y'] > $bounds['y'] + $bounds['height']), 'seat');

                if ($outside !== []) {
                    $validator->errors()->add('extra_seats', 'Miesta '.implode(', ', $outside).' sú mimo mapy sedenia.');
                }
            }

            $clashes = array_intersect(array_column($newSeats, 'seat'), $baseSeats);
            if ($clashes !== []) {
                $validator->errors()->add('extra_seats', 'Miesta '.implode(', ', $clashes).' už na mape sedenia existujú.');
            }
        });

        $validator->validate();

        $result = DB::transaction(function () use ($event, $newSeats) {
            // Same lock as order creation, so nobody can book a seat while it is being removed
            $event = Event::whereKey($event->id)->lockForUpdate()->first();

            $oldSeats = array_column($event->extraSeats(), 'seat');
            $removed = array_values(array_diff($oldSeats, array_column($newSeats, 'seat')));

            $occupied = array_values(array_intersect($removed, $this->reservedSeats($event)));
            if ($occupied !== []) {
                return ['error' => 'Miesta '.implode(', ', $occupied).' majú aktívnu rezerváciu, preto ich nie je možné odstrániť. Najprv hostí presuňte.'];
            }

            $config = json_decode((string) $event->additional_information, true) ?: [];

            if ($newSeats === []) {
                unset($config['extra_seats']);
            } else {
                $config['extra_seats'] = $newSeats;
            }

            // Removed seats can no longer belong to a table
            if ($removed !== [] && ! empty($config['tables'])) {
                $config['tables'] = array_values(array_filter(array_map(function ($table) use ($removed) {
                    $table['seats'] = array_values(array_diff($table['seats'], $removed));

                    return $table;
                }, $config['tables']), fn ($table) => $table['seats'] !== []));

                if ($config['tables'] === []) {
                    unset($config['tables']);
                }
            }

            // Keep the number of seats on sale in step with the seats on the map
            $delta = count($newSeats) - count($oldSeats);

            $event->update([
                'additional_information' => $config === [] ? null : json_encode($config, JSON_UNESCAPED_UNICODE),
                'seats_total' => max(1, $event->seats_total + $delta),
            ]);

            return ['delta' => $delta];
        });

        if (isset($result['error'])) {
            return back()->with('error', $result['error']);
        }

        $delta = $result['delta'];
        $message = 'Plánik sedenia bol uložený.';
        if ($delta !== 0) {
            $message .= ' Počet miest na predaj bol '.($delta > 0 ? 'zvýšený' : 'znížený').' o '.abs($delta).'.';
        }

        return redirect()->route('event.seat-plan', $event->url_slug)->with('success', $message);
    }

    private function authorizeManage(Request $request, Event $event): void
    {
        if (! in_array($event->roleFor($request->user()), ['owner', 'manager'], true)) {
            abort(403, 'Nemáte oprávnenie na úpravu plániku sedenia.');
        }
    }

    /**
     * Seat numbers held by active (not cancelled) reservations of the event.
     *
     * @return list<int>
     */
    private function reservedSeats(Event $event): array
    {
        return Reservation::whereHas('order', function ($query) use ($event) {
            $query->where('event_id', $event->id)
                ->where('status', '!=', 'cancelled');
        })
            ->pluck('seat_number')
            ->map(fn ($seat) => (int) $seat)
            ->unique()
            ->values()
            ->all();
    }
}
