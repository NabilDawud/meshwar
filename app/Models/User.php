<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'phone', 'role', 'google_id', 'avatar', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the single vehicle registered by this driver.
     */
    public function vehicle(): HasOne
    {
        return $this->hasOne(Vehicle::class, 'driver_id');
    }

    /**
     * Get the single main route registered by this driver.
     */
    public function driverRoute(): HasOne
    {
        return $this->hasOne(DriverRoute::class, 'driver_id');
    }

    /**
     * Get bookings made by this rider.
     */
    public function riderBookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'rider_id');
    }

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
}
