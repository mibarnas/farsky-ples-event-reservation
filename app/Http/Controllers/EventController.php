<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Event;
use App\Models\Location;
use App\Models\Setting;
use Inertia\Inertia;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Validator as ValidatorInstance;

class EventController extends Controller
{
    /**
     * Show the form for creating a new event.
     */
    public function create()
    {
        return Inertia::render('EventCreate', [
            'locations' => $this->locationsWithSeats(),
        ]);
    }

    /**
     * Store a newly created event in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'url_slug' => 'required|string|max:255|unique:events,url_slug',
            'description' => 'nullable|string',
            'start_time' => 'required|date',
            'registration_start' => 'required|date',
            'registration_end' => 'required|date|after:registration_start',
            'seats_total' => 'nullable|integer|min:1',
            'location_id' => 'required|exists:locations,id',
            'contact_name' => 'required|string|max:255',
            'contact_email' => 'required|email|max:255',
            'contact_phone' => 'nullable|string|max:255',
            'bank_account' => 'required|string|max:255',
            'multiple_reservations_per_ticket' => 'boolean',
            'background_image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,webp,svg|max:2048',
            'overline' => 'nullable|string|max:255',
            'tickets' => 'nullable|array',
            'tickets.*.title' => 'required|string|max:255',
            'tickets.*.price' => 'required|numeric|min:0',
            'tickets.*.reservations' => 'required|integer|min:1',
            ...$this->tableRules(),
        ]);

        $validator->after(function (ValidatorInstance $validator) use ($request) {
            $location = Location::find($request->input('location_id'));
            if ($location) {
                $this->validateTableSeats($validator, $request, $location->seatNumbers());
            }
        });

        $validated = $validator->validate();

        // If seats_total is not provided, infer it from the location
        if (empty($validated['seats_total'])) {
            $location = Location::findOrFail($validated['location_id']);
            $validated['seats_total'] = $location->places_total;
        }

        // Handle background image upload
        $backgroundImagePath = null;
        if ($request->hasFile('background_image')) {
            $backgroundImagePath = $request->file('background_image')->store('events/backgrounds', 'public');
        }

        // Handle logo upload
        $logoPath = null;
        if ($request->hasFile('logo')) {
            $logoPath = $request->file('logo')->store('events/logos', 'public');
        }

        $event = DB::transaction(function () use ($request, $validated, $backgroundImagePath, $logoPath) {
            $event = Event::create([
                'user_id' => $request->user()->id,
                'title' => $validated['title'],
                'url_slug' => $validated['url_slug'],
                'description' => $validated['description'] ?? null,
                'start_time' => $validated['start_time'],
                'registration_start' => $validated['registration_start'],
                'registration_end' => $validated['registration_end'],
                'seats_total' => $validated['seats_total'],
                'location_id' => $validated['location_id'],
                'contact_name' => $validated['contact_name'],
                'contact_email' => $validated['contact_email'],
                'contact_phone' => $validated['contact_phone'] ?? null,
                'bank_account' => $validated['bank_account'],
                'multiple_reservations_per_ticket' => $validated['multiple_reservations_per_ticket'] ?? false,
                'background_image_path' => $backgroundImagePath,
                'logo_image_path' => $logoPath,
                'overline' => $validated['overline'] ?? null,
                'additional_information' => $this->buildAdditionalInformation(null, $request),
            ]);

            foreach ($validated['tickets'] ?? [] as $ticket) {
                $event->tickets()->create([
                    'title' => $ticket['title'],
                    'price' => $ticket['price'],
                    'reservations' => $ticket['reservations'],
                ]);
            }

            return $event;
        });

        return redirect()->route('event.manage', $event->url_slug)
            ->with('success', 'Podujatie bolo úspešne vytvorené!');
    }

    /**
     * Show the event management page with tickets and orders.
     */
    public function manage(Request $request, $url_slug)
    {
        $event = Event::where('url_slug', $url_slug)
            ->with([
                'location',
                'tickets',
                'orders.tickets',
                'orders.reservations',
                'users' // Load collaborators
            ])
            ->firstOrFail();

        // Collaborators manage the event; application admins may look in (read-only,
        // apart from the "redirect when ended" switch).
        $user = $request->user();
        if (! $event->canBeViewedBy($user)) {
            abort(403, 'Nemáte oprávnenie na správu tohto podujatia.');
        }

        $userRole = $event->roleFor($user) ?? 'admin';

        $eventData = $event->toArray();
        $eventData['user_role'] = $userRole;
        $eventData['reserved_seats'] = $event->computeReservedSeats();
        $eventData['can_change_redirect'] = $event->canChangeRedirectBy($user);

        // Get all users for the collaborator selection (only if owner)
        $allUsers = [];
        if ($userRole === 'owner') {
            $allUsers = \App\Models\User::select('id', 'name', 'email')
                ->where('id', '!=', $event->user_id)
                ->orderBy('name')
                ->get();
        }

        return Inertia::render('EventManage', [
            'event' => $eventData,
            'allUsers' => $allUsers,
        ]);
    }

    /**
     * Show the print view for the event seat map.
     */
    public function print(Request $request, $url_slug)
    {
        $event = Event::where('url_slug', $url_slug)
            ->with([
                'location',
                'orders' => function($query) {
                    $query->whereIn('status', ['paid', 'pending'])
                          ->with('reservations');
                }
            ])
            ->firstOrFail();

        if (! $event->canBeViewedBy($request->user())) {
            abort(403, 'Nemáte oprávnenie na zobrazenie tohto podujatia.');
        }

        // Collect all reservations with seat numbers and guest names
        $reservations = $event->orders
            ->flatMap(function($order) {
                return $order->reservations->map(function($reservation) use ($order) {
                    return [
                        'seat_number' => $reservation->seat_number,
                        'guest_name' => $reservation->guest_name,
                        'additional_information' => $reservation->computeAdditionalInformation(),
                        'order_name' => $order->name,
                        'order_email' => $order->email,
                        'order_status' => $order->status,
                    ];
                });
            })
            ->sortBy('seat_number');

        return view('event.print', [
            'event' => $event,
            'reservations' => $reservations,
        ]);
    }

    /**
     * Show the form for editing an event.
     */
    public function edit(Request $request, $url_slug)
    {
        $event = Event::where('url_slug', $url_slug)->firstOrFail();

        // Check if user has access to edit this event
        $user = $request->user();
        $hasAccess = $event->user_id === $user->id ||
                     $event->users()->where('user_id', $user->id)->whereIn('role', ['owner', 'manager'])->exists();

        if (!$hasAccess) {
            abort(403, 'Nemáte oprávnenie na úpravu tohto podujatia.');
        }

        return Inertia::render('EventEdit', [
            'event' => $event,
            'locations' => $this->locationsWithSeats(),
            'tables' => $event->tableSetup(),
            'extraSeats' => $event->extraSeats(),
        ]);
    }

    /**
     * Update the event in storage.
     */
    public function update(Request $request, $url_slug)
    {
        $event = Event::where('url_slug', $url_slug)->firstOrFail();

        // Check if user has access to edit this event
        $user = $request->user();
        $hasAccess = $event->user_id === $user->id ||
                     $event->users()->where('user_id', $user->id)->whereIn('role', ['owner', 'manager'])->exists();

        if (!$hasAccess) {
            abort(403, 'Nemáte oprávnenie na úpravu tohto podujatia.');
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'url_slug' => 'required|string|max:255|unique:events,url_slug,' . $event->id,
            'description' => 'nullable|string',
            'start_time' => 'required|date',
            'registration_start' => 'required|date',
            'registration_end' => 'required|date|after:registration_start',
            'contact_name' => 'required|string|max:255',
            'contact_email' => 'required|email|max:255',
            'contact_phone' => 'nullable|string|max:255',
            'bank_account' => 'required|string|max:255',
            'multiple_reservations_per_ticket' => 'boolean',
            'background_image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'remove_background_image' => 'boolean',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,webp,svg|max:2048',
            'remove_logo' => 'boolean',
            'overline' => 'nullable|string|max:255',
            ...$this->tableRules(),
        ]);

        $validator->after(function (ValidatorInstance $validator) use ($request, $event) {
            if ($request->has('tables_enabled')) {
                $this->validateTableSeats($validator, $request, $event->seatNumbers());
            }
        });

        $validated = $validator->validate();

        // Handle background image upload
        $backgroundImagePath = $event->background_image_path;

        // If user wants to remove the background image
        if ($request->boolean('remove_background_image')) {
            if ($backgroundImagePath) {
                Storage::disk('public')->delete($backgroundImagePath);
            }
            $backgroundImagePath = null;
        }

        // If a new image is uploaded
        if ($request->hasFile('background_image')) {
            // Delete old image if exists
            if ($backgroundImagePath) {
                Storage::disk('public')->delete($backgroundImagePath);
            }
            $backgroundImagePath = $request->file('background_image')->store('events/backgrounds', 'public');
        }

        // Handle logo upload
        $logoPath = $event->logo_image_path;

        // If user wants to remove the logo
        if ($request->boolean('remove_logo')) {
            if ($logoPath) {
                Storage::disk('public')->delete($logoPath);
            }
            $logoPath = null;
        }

        // If a new logo is uploaded
        if ($request->hasFile('logo')) {
            // Delete old logo if exists
            if ($logoPath) {
                Storage::disk('public')->delete($logoPath);
            }
            $logoPath = $request->file('logo')->store('events/logos', 'public');
        }

        $event->update([
            'title' => $validated['title'],
            'url_slug' => $validated['url_slug'],
            'description' => !empty($validated['description']) ? $validated['description'] : null,
            'start_time' => $validated['start_time'],
            'registration_start' => $validated['registration_start'],
            'registration_end' => $validated['registration_end'],
            'contact_name' => $validated['contact_name'],
            'contact_email' => $validated['contact_email'],
            'contact_phone' => !empty($validated['contact_phone']) ? $validated['contact_phone'] : null,
            'bank_account' => $validated['bank_account'],
            'multiple_reservations_per_ticket' => $validated['multiple_reservations_per_ticket'] ?? false,
            'background_image_path' => $backgroundImagePath,
            'logo_image_path' => $logoPath,
            'overline' => $validated['overline'] ?? null,
            // Older clients that don't send tables_enabled leave the table setup untouched.
            'additional_information' => $request->has('tables_enabled')
                ? $this->buildAdditionalInformation($event->additional_information, $request)
                : $event->additional_information,
        ]);

        return redirect()->route('event.manage', $event->url_slug)
            ->with('success', 'Podujatie bolo úspešne aktualizované!');
    }

    /**
     * Switch whether visitors of this event are sent to the current alias-domain
     * event once it has ended. Owners, managers and application admins only.
     */
    public function updateRedirectWhenEnded(Request $request, Event $event)
    {
        if (! $event->canChangeRedirectBy($request->user())) {
            abort(403, 'Nemáte oprávnenie na úpravu tohto podujatia.');
        }

        $validated = $request->validate([
            'redirect_when_ended' => ['required', 'boolean'],
        ]);

        $event->forceFill(['redirect_when_ended' => $validated['redirect_when_ended']])->save();

        return back()->with('success', $validated['redirect_when_ended']
            ? 'Po skončení bude podujatie presmerované na aktuálne podujatie.'
            : 'Presmerovanie po skončení bolo vypnuté.');
    }

    public function show(Request $request, $url_slug)
    {
        $event = Event::where('url_slug', $url_slug)
            ->with(['tickets', 'location', 'orders' => function($query) {
                $query->where('status', '!=', 'cancelled')
                      ->with('tickets');
            }])
            ->firstOrFail();

        // Visitors can still land on a finished event through an old bookmark, a
        // shared link or a redirect their browser cached earlier (e.g. a 301 from
        // the alias domain). Send them to the event currently selected for the
        // alias domain instead. Only when the event has this switched on; people
        // working on the event (and admins) still see the page.
        $current = $event->redirect_when_ended ? Setting::aliasEvent() : null;
        $user = $request->user();
        if ($current
            && ! $current->is($event)
            && $event->hasEnded()
            && ! $current->hasEnded()
            && ! $event->canBeViewedBy($user)) {
            return redirect()->route('event.show', $current->url_slug)
                ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        }

        $tickets = $event->tickets;

        $totalReservedSeats = $event->computeReservedSeats();

        return Inertia::render('event/Show', [
            'event' => array_merge($event->only([
                'id',
                'title',
                'overline',
                'start_time',
                'url_slug',
                'seats_total',
                'registration_start',
                'registration_end',
                'user_id',
                'description',
                'multiple_reservations_per_ticket',
                'background_image_path',
                'logo_image_path'
            ])),
            'location' => $event->location,
            'tickets' => $tickets,
            'places_left' => $event->seats_total - $totalReservedSeats,
        ]);
    }

    /**
     * Locations for the create/edit forms, with the seat numbers of their SVG map
     * so the table editor can show the map and validate seat assignments.
     */
    private function locationsWithSeats()
    {
        return Location::select('id', 'name', 'address', 'places_total', 'svg_map')->get()
            ->map(fn (Location $location) => [
                'id' => $location->id,
                'name' => $location->name,
                'address' => $location->address,
                'places_total' => $location->places_total,
                'svg_map' => $location->svg_map,
                'valid_seats' => $location->seatNumbers(),
            ]);
    }

    /**
     * Validation rules for the table setup (stored in additional_information, read by TablesPlugin).
     */
    private function tableRules(): array
    {
        return [
            'tables_enabled' => 'boolean',
            'tables' => 'nullable|array',
            'tables.*.name' => 'required|string|max:255|distinct',
            'tables.*.seats' => 'required|array|min:1',
            'tables.*.seats.*' => 'integer|min:0',
        ];
    }

    /**
     * Reject seats shared by several tables and seats that don't exist on the seat map.
     *
     * @param  list<int>|null  $validSeats  null when the map can't be read (seats are then not checked)
     */
    private function validateTableSeats(ValidatorInstance $validator, Request $request, ?array $validSeats): void
    {
        if (! $request->boolean('tables_enabled') || $validator->errors()->isNotEmpty()) {
            return;
        }

        $owners = [];

        foreach ((array) $request->input('tables', []) as $index => $table) {
            foreach ((array) ($table['seats'] ?? []) as $seat) {
                $seat = (int) $seat;

                if (isset($owners[$seat]) && $owners[$seat] !== $index) {
                    $validator->errors()->add("tables.$index.seats", "Miesto $seat je už priradené k stolu „{$request->input("tables.{$owners[$seat]}.name")}“.");
                    continue;
                }
                $owners[$seat] = $index;

                if ($validSeats !== null && ! in_array($seat, $validSeats, true)) {
                    $validator->errors()->add("tables.$index.seats", "Miesto $seat na mape sedenia neexistuje.");
                }
            }
        }
    }

    /**
     * Merge the submitted tables into the event's additional_information JSON,
     * keeping any other keys that may be stored there.
     */
    private function buildAdditionalInformation(?string $existing, Request $request): ?string
    {
        $config = json_decode((string) $existing, true) ?: [];
        unset($config['tables']);

        if ($request->boolean('tables_enabled')) {
            $tables = collect((array) $request->input('tables', []))
                ->map(function ($table) {
                    $seats = array_values(array_unique(array_map('intval', (array) $table['seats'])));
                    sort($seats);

                    return ['name' => trim($table['name']), 'seats' => $seats];
                })
                ->values()
                ->all();

            if (! empty($tables)) {
                $config['tables'] = $tables;
            }
        }

        return empty($config) ? null : json_encode($config, JSON_UNESCAPED_UNICODE);
    }
}
