<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ACTIVE_RENTAL_SESSION_KEY = 'admin.rental_id';

    private ?Collection $activeRentals = null;

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
        ];
    }

    public function rentals(): BelongsToMany
    {
        return $this->belongsToMany(Rental::class, 'rental_users')
            ->using(RentalUser::class)
            ->withPivot(['role', 'status'])
            ->withTimestamps();
    }

    /**
     * Rentals this user can currently manage.
     *
     * @return Collection<int, Rental>
     */
    public function activeRentals(): Collection
    {
        return $this->activeRentals ??= $this->rentals()->wherePivot('status', 'active')->orderBy('rentals.name')->get();
    }

    /**
     * The rental the admin is working on: the one picked in the switcher when the
     * user still has access to it, otherwise their first active rental.
     */
    public function currentRental(): ?Rental
    {
        $rentals = $this->activeRentals();

        return $rentals->firstWhere('id', session(self::ACTIVE_RENTAL_SESSION_KEY)) ?? $rentals->first();
    }
}
