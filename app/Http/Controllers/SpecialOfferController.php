<?php

namespace App\Http\Controllers;

use App\Models\SpecialOffer;
use App\Models\SpecialRequest;
use App\Notifications\MishwarNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SpecialOfferController extends Controller
{
    /**
     * Driver submits a priced offer on an open special request.
     * Supports offline outbox mutation with client_mutation_id.
     */
    public function store(Request $request, int $requestId): JsonResponse
    {
        $user = Auth::user();

        if ($user->role !== 'driver') {
            return response()->json(['error' => 'حساب السائق مطلوب لتقديم عروض الأسعار'], 403);
        }

        /** @var SpecialRequest $specialRequest */
        $specialRequest = SpecialRequest::findOrFail($requestId);

        if ($specialRequest->status !== 'open') {
            return response()->json(['error' => 'عذراً، هذا الطلب لم يعد متاحاً لتلقي العروض'], 422);
        }

        $validated = $request->validate([
            'price_ils' => ['required', 'numeric', 'min:5', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:500'],
            'client_mutation_id' => ['nullable', 'string', 'max:64'],
        ]);

        $mutationId = $validated['client_mutation_id'] ?? null;

        // Offline idempotency
        if ($mutationId) {
            $existing = SpecialOffer::where('driver_id', $user->id)
                ->where('client_mutation_id', $mutationId)
                ->first();

            if ($existing) {
                return response()->json([
                    'ok' => true,
                    'already_synced' => true,
                    'message' => 'تم استرجاع عرض السعر مسبقاً',
                    'offer' => [
                        'id' => $existing->id,
                        'price_ils' => (float) $existing->price_ils,
                        'notes' => $existing->notes,
                        'status' => $existing->status,
                        'status_arabic' => $existing->status_arabic,
                    ],
                ]);
            }
        }

        // Create or update driver's offer on this request
        $offer = SpecialOffer::updateOrCreate(
            [
                'request_id' => $specialRequest->id,
                'driver_id' => $user->id,
            ],
            [
                'price_ils' => $validated['price_ils'],
                'notes' => $validated['notes'] ?? null,
                'status' => 'pending',
                'client_mutation_id' => $mutationId,
            ]
        );

        // Notify rider of new offer
        $rider = $specialRequest->rider;
        if ($rider) {
            $rider->notify(new MishwarNotification(
                title: 'عرض سعر جديد لمشوارك الخاص 💼',
                message: "قدم السائق {$user->name} عرضاً بقيمة {$offer->price_ils} ₪ لطلبك ({$specialRequest->origin_name} إلى {$specialRequest->destination_name}).",
                type: 'offer',
                icon: '💼',
                actionUrl: route('rides.requests'),
                extra: ['request_id' => $specialRequest->id, 'offer_id' => $offer->id]
            ));
        }

        return response()->json([
            'ok' => true,
            'message' => 'تم إرسال عرض السعر للراكب بنجاح',
            'offer' => [
                'id' => $offer->id,
                'request_id' => $specialRequest->id,
                'price_ils' => (float) $offer->price_ils,
                'notes' => $offer->notes,
                'status' => $offer->status,
                'status_arabic' => $offer->status_arabic,
            ],
        ], 201);
    }
}
