<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vehicle extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'driver_id',
        'type',
        'seat_count',
        'license_plate',
        'status',
        'photos',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'seat_count' => 'integer',
            'photos' => 'array',
        ];
    }

    /**
     * Get the driver user who owns this single vehicle.
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    /**
     * Get Arabic label for vehicle type.
     */
    public function getTypeArabicAttribute(): string
    {
        return match ($this->type) {
            'ajala' => 'عجلة',
            'bus' => 'باص / ميكروباص',
            'car' => 'سيارة',
            default => 'مركبة',
        };
    }
}
