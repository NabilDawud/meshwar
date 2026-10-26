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
