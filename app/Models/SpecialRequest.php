<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SpecialRequest extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'rider_id',
        'origin_name',
        'destination_name',
        'departure_date',
        'departure_window',
        'seats_needed',
        'notes',
        'status',
        'accepted_offer_id',
        'client_mutation_id',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'departure_date' => 'date:Y-m-d',
        'seats_needed' => 'integer',
    ];

    public function rider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rider_id');
    }

    public function offers(): HasMany
    {
        return $this->hasMany(SpecialOffer::class, 'request_id');
    }

    public function acceptedOffer(): BelongsTo
    {
        return $this->belongsTo(SpecialOffer::class, 'accepted_offer_id');
    }

    public function getStatusArabicAttribute(): string
    {
        return match ($this->status) {
            'open' => 'مفتوح لتلقي العروض',
            'accepted' => 'تم قبول عرض وتأكيد المشوار',
            'cancelled' => 'ملغي',
            default => 'غير محدد',
        };
    }
}
