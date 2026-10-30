<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DriverRoute extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'driver_id',
        'name',
        'start_point',
        'first_trip_time',
        'status',
        'is_active',
        'is_two_way',
        'active_direction',
        'is_trip_active',
        'last_latitude',
        'last_longitude',
        'last_landmark',
        'last_position_at',
        'stops',
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'is_two_way' => true,
        'active_direction' => 'outbound',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stops' => 'array',
            'is_active' => 'boolean',
            'is_two_way' => 'boolean',
            'is_trip_active' => 'boolean',
            'last_latitude' => 'float',
            'last_longitude' => 'float',
            'last_position_at' => 'datetime',
        ];
    }

    /**
     * Get the driver user who operates this route.
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    /**
     * Get bookings on this route.
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'route_id');
    }

    /**
     * Get confirmed active bookings on this route.
     */
    public function confirmedBookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'route_id')->where('status', 'confirmed');
    }

    /**
     * Get weekly schedule assignments using this route.
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(DriverSchedule::class, 'route_id');
    }

    /**
     * Get total seats currently booked.
     */
    public function bookedSeatsCount(): int
    {
        return (int) $this->confirmedBookings()->sum('seats');
    }

    /**
     * Calculate remaining free seats based on driver's vehicle capacity.
     */
    public function freeSeatsCount(): int
    {
        $vehicle = $this->driver?->vehicle;
        $totalCapacity = $vehicle ? (int) $vehicle->seat_count : 0;

        return max(0, $totalCapacity - $this->bookedSeatsCount());
    }

    /**
     * Calculate age of last recorded position in minutes.
     */
    public function getPositionAgeMinutesAttribute(): ?int
    {
        if (! $this->last_position_at) {
            return null;
        }

        return max(0, (int) now()->diffInMinutes($this->last_position_at));
    }

    /**
     * Format position age into clear, natural Arabic text (strictly NO speculative ETAs).
     */
    public function getPositionAgeArabicAttribute(): string
    {
        $minutes = $this->position_age_minutes;

        if ($minutes === null) {
            return 'غير متوفر';
        }

        if ($minutes === 0) {
            return 'الآن (أقل من دقيقة)';
        }

        if ($minutes === 1) {
            return 'منذ دقيقة واحدة';
        }

        if ($minutes === 2) {
            return 'منذ دقيقتين';
        }

        if ($minutes <= 10) {
            return "منذ {$minutes} دقائق";
        }

        return "منذ {$minutes} دقيقة";
    }

    /**
     * Get Arabic label for operational status.
     */
    public function getStatusArabicAttribute(): string
    {
        return match ($this->status) {
            'on_route' => 'عامل على الخط المعتاد',
            'temporary_route' => 'مسار مؤقت',
            'off_route' => 'خارج الخط حالياً',
            'skip_work' => 'إجازة / متوقف اليوم',
            default => 'غير محدد',
        };
    }

    /**
     * Determine if this route is a two-way round-trip corridor.
     */
    public function isTwoWay(): bool
    {
        return (bool) ($this->is_two_way ?? true);
    }

    /**
     * Determine if the active trip is currently in the outbound direction.
     */
    public function isOutbound(): bool
    {
        return ($this->active_direction ?? 'outbound') === 'outbound';
    }

    /**
     * Determine if the active trip is currently in the return direction.
     */
    public function isReturn(): bool
    {
        return ($this->active_direction ?? 'outbound') === 'return';
    }

    /**
     * Find a stop's index and payload by landmark name or partial match.
     *
     * @return array{index: int, stop: array<string, mixed>}|null
     */
    public function findStopMatch(?string $name): ?array
    {
        if (! $name || empty($this->stops) || ! is_array($this->stops)) {
            return null;
        }

        $trimmed = trim($name);

        foreach ($this->stops as $index => $stop) {
            $stopName = trim($stop['name'] ?? '');
            if ($stopName === $trimmed) {
                return ['index' => $index, 'stop' => $stop];
            }
        }

        foreach ($this->stops as $index => $stop) {
            $stopName = trim($stop['name'] ?? '');
            if ($stopName !== '' && (str_contains($stopName, $trimmed) || str_contains($trimmed, $stopName))) {
                return ['index' => $index, 'stop' => $stop];
            }
        }

        return null;
    }

    /**
     * Calculate dynamic segment fare between pickup and dropoff stops.
     * Symmetrical across outbound and return directions.
     */
    public function calculateSegmentFare(?string $pickup, ?string $dropoff, int $seats = 1): float
    {
        $seats = max(1, $seats);
        $stops = $this->stops ?? [];

        if (empty($stops) || ! is_array($stops)) {
            return round(5.0 * $seats, 2);
        }

        $pickupMatch = $this->findStopMatch($pickup);
        $dropoffMatch = $this->findStopMatch($dropoff);

        $pickupFare = null;
        if ($pickupMatch !== null) {
            $pickupFare = (float) ($pickupMatch['stop']['fare_ils'] ?? 0.0);
        } elseif ($pickup === null || trim($pickup) === '' || trim($pickup) === trim($this->start_point ?? '')) {
            $pickupFare = (float) ($stops[0]['fare_ils'] ?? 0.0);
        }

        $dropoffFare = null;
        if ($dropoffMatch !== null) {
            $dropoffFare = (float) ($dropoffMatch['stop']['fare_ils'] ?? 0.0);
        } elseif ($dropoff === null || trim($dropoff) === '') {
            $lastStop = end($stops);
            $dropoffFare = (float) ($lastStop['fare_ils'] ?? 5.0);
        }

        // If both stops resolved
        if ($pickupFare !== null && $dropoffFare !== null) {
            $unitFare = abs($dropoffFare - $pickupFare);
        } elseif ($dropoffFare !== null) {
            $unitFare = $dropoffFare;
        } elseif ($pickupFare !== null) {
            $unitFare = $pickupFare;
        } else {
            $lastStop = end($stops);
            $unitFare = (float) ($lastStop['fare_ils'] ?? 5.0);
        }

        // If unit fare evaluates to 0 (same stop or zero fare), ensure base minimum fare (2.0 ILS)
        if ($unitFare <= 0) {
            $unitFare = 2.0;
        }

        return round($unitFare * $seats, 2);
    }

    /**
     * Get human-friendly Arabic label for the active trip heading.
     */
    public function getActiveDirectionArabicAttribute(): string
    {
        return match ($this->active_direction ?? 'outbound') {
            'return' => 'رحلة العودة',
            default => 'رحلة الذهاب',
        };
    }
}
