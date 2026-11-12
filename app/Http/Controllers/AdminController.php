<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only overview for the application administrator.
 *
 * Being admin grants read-only access to every event dashboard and the
 * per-event "redirect when ended" switch; all other event permissions still
 * come from ownership / collaborator roles.
 */
class AdminController extends Controller
{
    public function index(): Response
    {
        $events = Event::query()
            ->with(['user:id,name,email', 'location:id,address'])
            ->withCount('users as collaborators_count')
            ->withCount([
                'orders as orders_count' => fn ($q) => $q->where('status', '!=', 'cancelled'),
                'reservations as reserved_seats' => fn ($q) => $q->where('orders.status', '!=', 'cancelled'),
            ])
            ->orderByDesc('start_time')
            ->get()
            ->map(fn (Event $event) => [
                'id' => $event->id,
                'title' => $event->title,
                'url_slug' => $event->url_slug,
                'start_time' => $event->start_time,
                'seats_total' => $event->seats_total,
                'reserved_seats' => $event->reserved_seats,
                'orders_count' => $event->orders_count,
                'collaborators_count' => $event->collaborators_count,
                'location' => $event->location ? preg_replace('/\r?\n|\r/', ', ', $event->location->address) : null,
                'owner' => $event->user ? ['name' => $event->user->name, 'email' => $event->user->email] : null,
            ]);

        $users = User::query()
            ->withCount(['events', 'sharedEvents'])
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'email', 'is_admin', 'email_verified_at', 'created_at'])
            ->each(function (User $user) {
                $user->is_root_admin = $user->isRootAdmin();
                $user->is_admin = $user->isAdmin();
            });

        return Inertia::render('Admin', [
            'stats' => [
                'users' => $users->count(),
                'events' => $events->count(),
                'orders' => Order::where('status', '!=', 'cancelled')->count(),
                'reservations' => Reservation::whereHas('order', fn ($q) => $q->where('status', '!=', 'cancelled'))->count(),
            ],
            'events' => $events,
            'users' => $users,
            'aliasEventId' => Setting::aliasEvent()?->id,
            'aliasDomains' => config('app.alias_domains'),
        ]);
    }

    /**
     * Choose which event the alias domain(s) redirect to. This is an app-level
     * setting and does not change anything about the event itself.
     */
    public function updateAliasEvent(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'event_id' => ['nullable', 'integer', 'exists:events,id'],
        ]);

        Setting::putValue(Setting::ALIAS_EVENT_ID, isset($validated['event_id']) ? (string) $validated['event_id'] : null);

        return back()->with('success', 'Presmerovanie bolo uložené.');
    }

    /**
     * Grant or revoke admin access. The ADMIN_EMAIL user is permanent, and admins
     * cannot demote themselves (prevents accidentally locking yourself out).
     */
    public function updateUserAdmin(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'is_admin' => ['required', 'boolean'],
        ]);

        if ($user->isRootAdmin()) {
            return back()->with('error', 'Hlavného administrátora nie je možné zmeniť.');
        }

        if ($user->is($request->user()) && ! $validated['is_admin']) {
            return back()->with('error', 'Nemôžete odobrať administrátorské práva sami sebe.');
        }

        $user->forceFill(['is_admin' => $validated['is_admin']])->save();

        return back()->with('success', $validated['is_admin']
            ? "{$user->name} je teraz administrátor."
            : "{$user->name} už nie je administrátor.");
    }
}
