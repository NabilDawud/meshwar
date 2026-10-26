<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'is_trip_active',
        'last_latitude',
        'last_longitude',
        'last_landmark',
        'last_position_at',
        'stops',
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
}
