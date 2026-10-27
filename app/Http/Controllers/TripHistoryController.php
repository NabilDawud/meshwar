<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\SpecialRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

class TripHistoryController extends Controller
{
    /**
     * Retrieve completed trip history for the authenticated rider.
     * Includes both completed transit bookings and completed special charter trips.
     */
    public function riderHistory(Request $request): JsonResponse
    {
        $user = Auth::user();

        // 1. Completed transit bookings
        $bookings = Booking::where('rider_id', $user->id)
            ->where('status', 'completed')
            ->with(['route.driver.vehicle', 'rating'])
            ->get()
            ->map(function (Booking $b) {
                $driver = $b->route?->driver;
                $vehicle = $driver?->vehicle;
                $rating = $b->rating;

                return [
                    'id' => $b->id,
                    'trip_type' => 'line',
                    'type_arabic' => 'خط سير منتظم',
                    'origin' => $b->pickup_stop,
                    'destination' => $b->dropoff_stop,
                    'seats' => $b->seats,
                    'fare_ils' => (float) $b->fare_ils,
                    'completed_at' => $b->updated_at->toISOString(),
                    'date_arabic' => $b->updated_at->format('Y-m-d H:i'),
                    'driver' => $driver ? [
                        'id' => $driver->id,
                        'name' => $driver->name,
                        'rating' => $driver->average_rating,
                    ] : null,
                    'vehicle' => $vehicle ? [
                        'type' => $vehicle->type,
                        'type_arabic' => $vehicle->type_arabic,
                        'license_plate' => $vehicle->license_plate,
                        'photo' => $vehicle->photos[0] ?? '/images/sample-ajala.svg',
                    ] : null,
                    'rating' => $rating ? [
                        'id' => $rating->id,
                        'rating' => $rating->rating,
                        'comment' => $rating->comment,
                        'created_at' => $rating->created_at->toISOString(),
                    ] : null,
                    'can_rate' => $rating === null,
                ];
            });

        // 2. Completed special requests
        $specialRequests = SpecialRequest::where('rider_id', $user->id)
            ->where('status', 'completed')
            ->with(['acceptedOffer.driver.vehicle', 'rating'])
            ->get()
            ->map(function (SpecialRequest $sr) {
                $offer = $sr->acceptedOffer;
                $driver = $offer?->driver;
                $vehicle = $driver?->vehicle;
                $rating = $sr->rating;

                return [
                    'id' => $sr->id,
                    'trip_type' => 'special',
                    'type_arabic' => 'مشوار خاص (مراسلة)',
                    'origin' => $sr->origin_name,
                    'destination' => $sr->destination_name,
                    'seats' => $sr->seats_needed,
                    'fare_ils' => (float) ($offer?->price_ils ?? 0),
                    'completed_at' => $sr->updated_at->toISOString(),
                    'date_arabic' => $sr->updated_at->format('Y-m-d H:i'),
                    'driver' => $driver ? [
                        'id' => $driver->id,
                        'name' => $driver->name,
                        'rating' => $driver->average_rating,
                    ] : null,
                    'vehicle' => $vehicle ? [
                        'type' => $vehicle->type,
                        'type_arabic' => $vehicle->type_arabic,
                        'license_plate' => $vehicle->license_plate,
                        'photo' => $vehicle->photos[0] ?? '/images/sample-car.svg',
                    ] : null,
                    'rating' => $rating ? [
                        'id' => $rating->id,
                        'rating' => $rating->rating,
                        'comment' => $rating->comment,
                        'created_at' => $rating->created_at->toISOString(),
                    ] : null,
                    'can_rate' => $rating === null,
                ];
            });

        // Merge and sort latest first
        $trips = $bookings->concat($specialRequests)
            ->sortByDesc('completed_at')
            ->values();

        $totalSpent = $trips->sum('fare_ils');

        if ($request->has('page')) {
            $page = max(1, (int) $request->query('page', 1));
            $perPage = max(1, (int) $request->query('per_page', 10));

            $paginated = new LengthAwarePaginator(
                $trips->forPage($page, $perPage)->values(),
                $trips->count(),
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );

            return response()->json([
                'ok' => true,
                'total_trips' => $trips->count(),
                'total_spent_ils' => (float) $totalSpent,
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'total' => $paginated->total(),
                'per_page' => $paginated->perPage(),
                'from' => $paginated->firstItem(),
                'to' => $paginated->lastItem(),
                'trips' => $paginated->values(),
            ]);
        }

        return response()->json([
            'ok' => true,
            'total_trips' => $trips->count(),
            'total_spent_ils' => (float) $totalSpent,
            'trips' => $trips,
        ]);
    }

    /**
     * Retrieve completed trip history for the authenticated driver.
     * Includes statistics on total trips, earnings in ILS, and received ratings.
     */
    public function driverHistory(Request $request): JsonResponse
    {
        $user = Auth::user();

        if ($user->role !== 'driver') {
            return response()->json(['error' => 'حساب السائق مطلوب لعرض هذا السجل'], 403);
        }

        // 1. Completed bookings on driver's transit route
        $bookings = Booking::whereHas('route', fn ($q) => $q->where('driver_id', $user->id))
            ->where('status', 'completed')
            ->with(['rider', 'rating'])
            ->get()
            ->map(function (Booking $b) {
                $rating = $b->rating;

                return [
                    'id' => $b->id,
                    'trip_type' => 'line',
                    'type_arabic' => 'خط سير منتظم',
                    'rider_name' => $b->rider->name,
                    'origin' => $b->pickup_stop,
                    'destination' => $b->dropoff_stop,
                    'seats' => $b->seats,
                    'fare_ils' => (float) $b->fare_ils,
                    'completed_at' => $b->updated_at->toISOString(),
                    'date_arabic' => $b->updated_at->format('Y-m-d H:i'),
                    'rating' => $rating ? [
                        'rating' => $rating->rating,
                        'comment' => $rating->comment,
                        'created_at' => $rating->created_at->toISOString(),
                    ] : null,
                ];
            });

        // 2. Completed special requests driven by this driver
        $specialRequests = SpecialRequest::where('status', 'completed')
            ->whereHas('acceptedOffer', fn ($q) => $q->where('driver_id', $user->id))
            ->with(['rider', 'rating', 'acceptedOffer'])
            ->get()
            ->map(function (SpecialRequest $sr) {
                $rating = $sr->rating;
                $offer = $sr->acceptedOffer;

                return [
                    'id' => $sr->id,
                    'trip_type' => 'special',
                    'type_arabic' => 'مشوار خاص (مراسلة)',
                    'rider_name' => $sr->rider->name,
                    'origin' => $sr->origin_name,
                    'destination' => $sr->destination_name,
                    'seats' => $sr->seats_needed,
                    'fare_ils' => (float) ($offer?->price_ils ?? 0),
                    'completed_at' => $sr->updated_at->toISOString(),
                    'date_arabic' => $sr->updated_at->format('Y-m-d H:i'),
                    'rating' => $rating ? [
                        'rating' => $rating->rating,
                        'comment' => $rating->comment,
                        'created_at' => $rating->created_at->toISOString(),
                    ] : null,
                ];
            });

        $trips = $bookings->concat($specialRequests)
            ->sortByDesc('completed_at')
            ->values();

        $totalEarned = $trips->sum('fare_ils');

        if ($request->has('page')) {
            $page = max(1, (int) $request->query('page', 1));
            $perPage = max(1, (int) $request->query('per_page', 10));

            $paginated = new LengthAwarePaginator(
                $trips->forPage($page, $perPage)->values(),
                $trips->count(),
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );

            return response()->json([
                'ok' => true,
                'stats' => [
                    'total_completed_trips' => $trips->count(),
                    'total_earned_ils' => (float) $totalEarned,
                    'average_rating' => $user->average_rating,
                    'ratings_count' => $user->ratings_count,
                ],
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'total' => $paginated->total(),
                'per_page' => $paginated->perPage(),
                'from' => $paginated->firstItem(),
                'to' => $paginated->lastItem(),
                'trips' => $paginated->values(),
            ]);
        }

        return response()->json([
            'ok' => true,
            'stats' => [
                'total_completed_trips' => $trips->count(),
                'total_earned_ils' => (float) $totalEarned,
                'average_rating' => $user->average_rating,
                'ratings_count' => $user->ratings_count,
            ],
            'trips' => $trips,
        ]);
    }
}
