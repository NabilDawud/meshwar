<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocationShare extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'booking_id',
        'rider_id',
        'driver_id',
        'status',
        'last_latitude',
        'last_longitude',
        'last_shared_at',
        'expires_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'last_latitude' => 'decimal:7',
        'last_longitude' => 'decimal:7',
        'last_shared_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rider_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    /**
     * Check if the temporary location share has reached its 15-minute expiration.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Privacy Policy Enforcement:
     * Location coordinates are returned ONLY when the status is strictly 'active' AND not expired.
     * When ended, rejected, or expired, coordinates are purged / hidden.
     */
    public function isActive(): bool
    {
        return $this->status === 'active' && ! $this->isExpired();
    }

    /**
     * Human-readable Arabic label for location share status.
     */
    public function getStatusArabicAttribute(): string
    {
        if ($this->status === 'active' && $this->isExpired()) {
            return 'انتهت الصلاحية تلقائياً';
        }

        return match ($this->status) {
            'requested' => 'بانتظار موافقة الراكب',
            'active' => 'مشاركة نشطة',
            'rejected' => 'تم رفض المشاركة',
            'ended' => 'تم إنهاء المشاركة',
            default => 'غير محدد',
        };
    }
}
