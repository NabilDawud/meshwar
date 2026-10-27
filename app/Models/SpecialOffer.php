<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SpecialOffer extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'request_id',
        'driver_id',
        'price_ils',
        'notes',
        'status',
        'client_mutation_id',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'price_ils' => 'decimal:2',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(SpecialRequest::class, 'request_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'offer_id');
    }

    public function getStatusArabicAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'قيد المراجعة',
            'accepted' => 'تم قبول العرض',
            'rejected' => 'تم قبول عرض آخر',
            default => 'غير محدد',
        };
    }

    /**
     * Privacy Policy Enforcement:
     * Rider phone is unlocked for the driver ONLY once this specific offer is accepted.
     */
    public function getRiderPhoneForDriverAttribute(): ?string
    {
        if ($this->status !== 'accepted') {
            return null;
        }

        return $this->request?->rider?->phone;
    }

    /**
     * Driver phone number for the rider to view and tap-to-call.
     */
    public function getDriverPhoneForRiderAttribute(): ?string
    {
        return $this->driver?->phone;
    }
}
