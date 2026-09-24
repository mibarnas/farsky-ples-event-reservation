<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Event extends Model
{
    use HasFactory;

    private $plugins = [
        'tables' => \App\Http\Plugins\TablesPlugin::class,
    ];

    protected $fillable = [
        'user_id',
        'seats_total',
        'title',
        'url_slug',
        'description',
        'start_time',
        'registration_start',
        'registration_end',
        'contact_email',
        'contact_phone',
        'contact_name',
        'bank_account',
        'location_id',
        'multiple_reservations_per_ticket',
        'background_image_path',
        'logo_image_path',
        'overline',
        'additional_information',
    ];

    protected $casts = [
        'seats_total' => 'integer',
        'start_time' => 'datetime',
        'registration_start' => 'datetime',
        'registration_end' => 'datetime',
        'user_id' => 'integer',
        'location_id' => 'integer',
    ];

    /**
     * The table setup ({"name", "seats"} entries) stored in additional_information,
     * read by TablesPlugin and used to label tables on the seat map.
     *
     * @return list<array{name: string, seats: list<int>}>
     */
    public function tableSetup(): array
    {
        return json_decode((string) $this->additional_information, true)['tables'] ?? [];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function reservations(): HasManyThrough
    {
        return $this->hasManyThrough(Reservation::class, Order::class);
    }

    /**
     * Users attached to this event via the pivot table (with a `role` pivot column).
     * This includes managers and other users who can control tickets.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function getRouteKeyName(): string
    {
        return 'url_slug';
    }

    /**
     * Whether the public can currently place orders: inside the registration
     * window and before the event itself starts.
     */
    public function isRegistrationOpen(): bool
    {
        $now = now();

        return $now->gte($this->registration_start)
            && $now->lte($this->registration_end)
            && $now->lt($this->start_time);
    }

    /**
     * The user's role on this event: 'owner', 'manager', 'staff', or null when they have no access.
     */
    public function roleFor(?User $user): ?string
    {
        if (! $user) {
            return null;
        }

        if ($this->user_id === $user->id) {
            return 'owner';
        }

        return $this->users()->whereKey($user->id)->first()?->pivot->role;
    }

    public function computeReservedSeats() {
        $totalReservedSeats = 0;
        foreach ($this->orders as $order) {
            if ($order->status === 'cancelled') {
                continue;
            }

            $totalReservedSeats += $order->reservations->count();
        }

        return $totalReservedSeats;
    }

    public function getPlugins(): array
    {
        return $this->plugins;
    }
}
