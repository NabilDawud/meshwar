<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Booking extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'rider_id',
        'route_id',
        'seats',
        'pickup_stop',
        'dropoff_stop',
        'fare_ils',
        'status',
        'cancellation_reason',
        'client_mutation_id',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'seats' => 'integer',
        'fare_ils' => 'decimal:2',
    ];

    /**
     * The rider who made the reservation.
     */
    public function rider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rider_id');
    }

    /**
     * The driver route on which the seat was reserved.
     */
    public function route(): BelongsTo
    {
        return $this->belongsTo(DriverRoute::class, 'route_id');
    }

    /**
     * Active or requested location share for this booking.
     */
    public function locationShare(): HasOne
    {
        return $this->hasOne(LocationShare::class, 'booking_id');
    }

    /**
     * Human-readable Arabic label for booking status.
     */
    public function getStatusArabicAttribute(): string
    {
        return match ($this->status) {
            'confirmed' => 'مؤكد',
            'cancelled_by_driver' => 'ملغي من السائق لعدم الجاهزية',
            'cancelled_by_rider' => 'ملغي من الراكب',
            'completed' => 'مكتمل',
            default => 'غير محدد',
        };
    }

    /**
     * Privacy Policy Enforcement:
     * Rider's phone number is revealed to the driver ONLY if the booking is confirmed.
     */
    public function getRiderPhoneForDriverAttribute(): ?string
    {
        if ($this->status !== 'confirmed') {
            return null;
        }

        return $this->rider?->phone;
    }

    /**
     * Privacy Policy Enforcement:
     * Driver's phone number is revealed to the rider ONLY if the booking is confirmed.
     */
    public function getDriverPhoneForRiderAttribute(): ?string
    {
        if ($this->status !== 'confirmed') {
            return null;
        }

        return $this->route?->driver?->phone;
    }
}
