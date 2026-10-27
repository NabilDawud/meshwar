<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripRating extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'rider_id',
        'driver_id',
        'booking_id',
        'special_request_id',
        'rating',
        'comment',
        'client_mutation_id',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'rating' => 'integer',
    ];

    public function rider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rider_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }

    public function specialRequest(): BelongsTo
    {
        return $this->belongsTo(SpecialRequest::class, 'special_request_id');
    }
}
