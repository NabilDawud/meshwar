<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\DriverRoute;
use App\Services\RouteDriftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    public function __construct(
        protected RouteDriftService $driftService
    ) {}

    /**
     * Reserve a seat on a transit route.
     * Atomic transaction with lockForUpdate to strictly enforce vehicle seat capacity.
     */
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'route_id' => ['required', 'exists:driver_routes,id'],
            'seats' => ['nullable', 'integer', 'min:1', 'max:10'],
            'pickup_stop' => ['nullable', 'string', 'max:150'],
            'dropoff_stop' => ['nullable', 'string', 'max:150'],
            'fare_ils' => ['nullable', 'numeric', 'min:0'],
            'client_mutation_id' => ['nullable', 'string', 'max:64'],
        ]);

        $seatsRequested = (int) ($validated['seats'] ?? 1);
        $mutationId = $validated['client_mutation_id'] ?? null;

        // Offline idempotency: If this mutation was already processed, return existing booking
        if ($mutationId) {
            $existing = Booking::where('rider_id', $user->id)
                ->where('client_mutation_id', $mutationId)
                ->with(['route.driver.vehicle'])
                ->first();

            if ($existing) {
                return response()->json([
                    'ok' => true,
                    'already_synced' => true,
                    'message' => 'تم استرجاع الحجز المؤكد مسبقاً',
                    'booking' => $this->formatBookingForRider($existing),
                ]);
            }
        }

        return DB::transaction(function () use ($user, $validated, $seatsRequested, $mutationId) {
            /** @var DriverRoute $route */
            $route = DriverRoute::with('driver.vehicle')->lockForUpdate()->findOrFail($validated['route_id']);
            $vehicle = $route->driver?->vehicle;

            $totalCapacity = $vehicle ? (int) $vehicle->seat_count : 0;

            // Atomic sum of already confirmed seats
            $bookedSeats = (int) Booking::where('route_id', $route->id)
                ->where('status', 'confirmed')
                ->lockForUpdate()
                ->sum('seats');

            $freeSeats = max(0, $totalCapacity - $bookedSeats);

            if ($freeSeats < $seatsRequested) {
                return response()->json([
                    'ok' => false,
                    'error' => "عذراً، لم تتبقَ مقاعد كافية على هذه الرحلة (المقاعد المتاحة: {$freeSeats})",
                    'free_seats' => $freeSeats,
                ], 422);
            }

            /** @var Booking $booking */
            $booking = Booking::create([
                'rider_id' => $user->id,
                'route_id' => $route->id,
                'seats' => $seatsRequested,
                'pickup_stop' => $validated['pickup_stop'] ?? $route->start_point,
                'dropoff_stop' => $validated['dropoff_stop'] ?? null,
                'fare_ils' => $validated['fare_ils'] ?? null,
                'status' => 'confirmed',
                'client_mutation_id' => $mutationId,
            ]);

            $booking->load(['route.driver.vehicle']);

            return response()->json([
                'ok' => true,
                'message' => 'تم تأكيد حجزك بنجاح',
                'booking' => $this->formatBookingForRider($booking),
            ], 201);
        });
    }

    /**
     * Get the authenticated rider's bookings with driver contact and drift warning.
     */
    public function riderBookings(): JsonResponse
    {
        $user = Auth::user();

        $bookings = Booking::where('rider_id', $user->id)
            ->with(['route.driver.vehicle', 'locationShare'])
            ->latest()
            ->get()
            ->map(fn (Booking $b) => $this->formatBookingForRider($b));

        return response()->json([
            'ok' => true,
            'count' => $bookings->count(),
            'bookings' => $bookings,
        ]);
    }

    /**
     * Driver views manifest of booked riders for pre-trip confirmation.
     */
    public function driverManifest(): JsonResponse
    {
        $user = Auth::user();

        if ($user->role !== 'driver') {
            return response()->json(['error' => 'حساب السائق مطلوب'], 403);
        }

        $route = $user->driverRoute;
        if (! $route) {
            return response()->json(['error' => 'لا يوجد مسار مسجل للسائق'], 404);
        }

        $bookings = Booking::where('route_id', $route->id)
            ->with(['rider', 'locationShare'])
            ->latest()
            ->get()
            ->map(function (Booking $b) {
                // Privacy Enforcement: phone unlocked only if status === 'confirmed'
                $canCall = $b->status === 'confirmed';
                $share = $b->locationShare;

                return [
                    'id' => $b->id,
                    'rider_id' => $b->rider_id,
                    'rider_name' => $b->rider->name,
                    'rider_phone' => $canCall ? $b->rider->phone : null,
                    'can_call' => $canCall,
                    'seats' => $b->seats,
                    'pickup_stop' => $b->pickup_stop,
                    'dropoff_stop' => $b->dropoff_stop,
                    'fare_ils' => $b->fare_ils,
                    'status' => $b->status,
                    'status_arabic' => $b->status_arabic,
                    'cancellation_reason' => $b->cancellation_reason,
                    'created_at' => $b->created_at->toISOString(),
                    'location_share' => $share ? [
                        'id' => $share->id,
                        'status' => $share->status,
                        'status_arabic' => $share->status_arabic,
                        'is_active' => $share->isActive(),
                        'last_latitude' => $share->isActive() ? $share->last_latitude : null,
                        'last_longitude' => $share->isActive() ? $share->last_longitude : null,
                        'last_shared_at' => $share->last_shared_at?->toISOString(),
                    ] : null,
                ];
            });

        return response()->json([
            'ok' => true,
            'route_name' => $route->name,
            'is_trip_active' => (bool) $route->is_trip_active,
            'booked_seats' => $route->bookedSeatsCount(),
            'free_seats' => $route->freeSeatsCount(),
            'manifest' => $bookings,
        ]);
    }

    /**
     * Driver cancels an unready rider before starting the trip.
     */
    public function cancelByDriver(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();

        /** @var Booking $booking */
        $booking = Booking::with('route')->findOrFail($id);

        if ($booking->route->driver_id !== $user->id) {
            return response()->json(['error' => 'غير مصرح لك بإلغاء هذا الحجز'], 403);
        }

        $reason = $request->input('reason', 'تم إلغاء حجزك من قِبل السائق لعدم الجاهزية عند بدء الرحلة');

        $booking->update([
            'status' => 'cancelled_by_driver',
            'cancellation_reason' => $reason,
        ]);

        // Automatically end any active location sharing
        if ($booking->locationShare) {
            $booking->locationShare->update([
                'status' => 'ended',
                'last_latitude' => null,
                'last_longitude' => null,
            ]);
        }

        return response()->json([
            'ok' => true,
            'message' => 'تم إلغاء الحجز لعدم جاهزية الراكب',
            'booking_id' => $booking->id,
            'status' => 'cancelled_by_driver',
        ]);
    }

    /**
     * Driver marks rider confirmed / boarded at pickup.
     */
    public function confirmByDriver(int $id): JsonResponse
    {
        $user = Auth::user();

        /** @var Booking $booking */
        $booking = Booking::with('route')->findOrFail($id);

        if ($booking->route->driver_id !== $user->id) {
            return response()->json(['error' => 'غير مصرح لك بتأكيد هذا الحجز'], 403);
        }

        // When boarded, end location sharing as rider is now in the vehicle
        if ($booking->locationShare) {
            $booking->locationShare->update([
                'status' => 'ended',
                'last_latitude' => null,
                'last_longitude' => null,
            ]);
        }

        return response()->json([
            'ok' => true,
            'message' => 'تم تأكيد صعود الراكب للمركبة',
            'booking_id' => $booking->id,
            'status' => 'confirmed',
        ]);
    }

    /**
     * Format a booking payload for the rider, applying strict privacy gating.
     *
     * @return array<string, mixed>
     */
    protected function formatBookingForRider(Booking $booking): array
    {
        $route = $booking->route;
        $driver = $route?->driver;
        $vehicle = $driver?->vehicle;

        $driftInfo = $route ? $this->driftService->checkDrift($route) : ['drifting' => false];

        // Privacy Rule: driver phone is disclosed ONLY when booking is confirmed
        $driverPhone = ($booking->status === 'confirmed') ? $driver?->phone : null;

        $locationShare = $booking->locationShare;

        return [
            'id' => $booking->id,
            'status' => $booking->status,
            'status_arabic' => $booking->status_arabic,
            'cancellation_reason' => $booking->cancellation_reason,
            'seats' => $booking->seats,
            'pickup_stop' => $booking->pickup_stop,
            'dropoff_stop' => $booking->dropoff_stop,
            'fare_ils' => $booking->fare_ils,
            'created_at' => $booking->created_at->toISOString(),
            'route' => $route ? [
                'id' => $route->id,
                'name' => $route->name,
                'is_trip_active' => (bool) $route->is_trip_active,
                'last_landmark' => $route->last_landmark,
                'position_age_arabic' => $route->position_age_arabic,
                'drifting' => $driftInfo['drifting'],
                'drift_alert' => $driftInfo['alert'] ?? null,
            ] : null,
            'driver' => $driver ? [
                'name' => $driver->name,
                'phone' => $driverPhone,
                'can_call' => ! empty($driverPhone),
            ] : null,
            'vehicle' => $vehicle ? [
                'type' => $vehicle->type,
                'type_arabic' => $vehicle->type_arabic,
                'photos' => $vehicle->photos,
            ] : null,
            'location_share' => $locationShare ? [
                'id' => $locationShare->id,
                'status' => $locationShare->status,
                'status_arabic' => $locationShare->status_arabic,
                'is_active' => $locationShare->isActive(),
                'expires_at' => $locationShare->expires_at->toISOString(),
            ] : null,
        ];
    }
}
