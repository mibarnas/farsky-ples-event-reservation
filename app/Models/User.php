<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /**
     * Events this user is attached to via the pivot table (with role information).
     * This includes managers and owners recorded in the pivot.
     */
    public function sharedEvents(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Whether this user is the permanent administrator configured via ADMIN_EMAIL.
     * They are always admin and cannot be demoted from the dashboard.
     */
    public function isRootAdmin(): bool
    {
        $adminEmail = config('app.admin_email');

        return filled($adminEmail)
            && strcasecmp(trim($adminEmail), trim($this->email)) === 0;
    }

    /**
     * Whether this user can access the admin dashboard: the root admin, or anyone
     * promoted there (is_admin). It grants no event permissions.
     */
    public function isAdmin(): bool
    {
        return $this->isRootAdmin() || (bool) $this->is_admin;
    }
}
