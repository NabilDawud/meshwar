<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\SpecialRequest;
use App\Models\TripRating;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TripRatingController extends Controller
{
    /**
     * Submit a 1-5 star rating and optional feedback for a completed trip.
     * Supports offline idempotency with client_mutation_id.
     */
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'trip_type' => ['required', 'string', 'in:line,special'],
            'trip_id' => ['required', 'integer'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:500'],
            'client_mutation_id' => ['nullable', 'string', 'max:64'],
        ]);

        $mutationId = $validated['client_mutation_id'] ?? null;

        // Offline idempotency
        if ($mutationId) {
            $existing = TripRating::where('rider_id', $user->id)
                ->where('client_mutation_id', $mutationId)
                ->first();

            if ($existing) {
                return response()->json([
                    'ok' => true,
                    'already_synced' => true,
                    'message' => 'تم استرجاع تقييم الرحلة مسبقاً',
                    'rating' => [
                        'id' => $existing->id,
                        'rating' => $existing->rating,
                        'comment' => $existing->comment,
                        'created_at' => $existing->created_at->toISOString(),
                    ],
                ]);
            }
        }

        $bookingId = null;
        $specialRequestId = null;
        $driverId = null;

        if ($validated['trip_type'] === 'line') {
            /** @var Booking $booking */
            $booking = Booking::with('route.driver')->findOrFail($validated['trip_id']);

            if ($booking->rider_id !== $user->id) {
                return response()->json(['error' => 'غير مصرح لك بتقييم هذه الرحلة'], 403);
            }

            if ($booking->status !== 'completed') {
                return response()->json(['error' => 'لا يمكن تقييم رحلة غير مكتملة'], 422);
            }

            if ($booking->rating) {
                return response()->json(['error' => 'تم تقييم هذه الرحلة مسبقاً'], 422);
            }

            $bookingId = $booking->id;
            $driverId = $booking->route?->driver_id;
        } else {
            /** @var SpecialRequest $specialRequest */
            $specialRequest = SpecialRequest::with('acceptedOffer.driver')->findOrFail($validated['trip_id']);

            if ($specialRequest->rider_id !== $user->id) {
                return response()->json(['error' => 'غير مصرح لك بتقييم هذا المشوار'], 403);
            }

            if ($specialRequest->status !== 'completed') {
                return response()->json(['error' => 'لا يمكن تقييم مشوار غير مكتمل'], 422);
            }

            if ($specialRequest->rating) {
                return response()->json(['error' => 'تم تقييم هذا المشوار مسبقاً'], 422);
            }

            $specialRequestId = $specialRequest->id;
            $driverId = $specialRequest->acceptedOffer?->driver_id;
        }

        if (! $driverId) {
            return response()->json(['error' => 'تعذر تحديد سائق الرحلة'], 422);
        }

        $tripRating = TripRating::create([
            'rider_id' => $user->id,
            'driver_id' => $driverId,
            'booking_id' => $bookingId,
            'special_request_id' => $specialRequestId,
            'rating' => $validated['rating'],
            'comment' => $validated['comment'] ?? null,
            'client_mutation_id' => $mutationId,
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'شكراً لك! تم تسجيل تقييمك للسائق بنجاح',
            'rating' => [
                'id' => $tripRating->id,
                'rating' => $tripRating->rating,
                'comment' => $tripRating->comment,
                'created_at' => $tripRating->created_at->toISOString(),
            ],
        ], 201);
    }
}
