<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverSchedule extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'driver_id',
        'day_of_week',
        'route_id',
        'start_time',
        'end_time',
        'is_day_off',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'is_day_off' => 'boolean',
        ];
    }

    /**
     * Driver who owns this schedule entry.
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    /**
     * Assigned route for this day of week.
     */
    public function route(): BelongsTo
    {
        return $this->belongsTo(DriverRoute::class, 'route_id');
    }

    /**
     * Human-friendly Arabic name for the day of the week.
     */
    public function getDayNameArabicAttribute(): string
    {
        return match ($this->day_of_week) {
            0 => 'الأحد',
            1 => 'الإثنين',
            2 => 'الثلاثاء',
            3 => 'الأربعاء',
            4 => 'الخميس',
            5 => 'الجمعة',
            6 => 'السبت',
            default => 'غير محدد',
        };
    }
}
