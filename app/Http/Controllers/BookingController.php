<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\DriverRoute;
use App\Models\User;
use App\Notifications\MishwarNotification;
use App\Services\RouteDriftService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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

        if ($user->role === 'driver') {
            return response()->json([
                'ok' => false,
                'error' => 'حساب السائق مخصص لإدارة الرحلات واستقبال الركاب، ولا يمكن حجز مقاعد كراكب بنفس الحساب.',
            ], 403);
        }

        $validated = $request->validate([
            'route_id' => ['required', 'exists:driver_routes,id'],
            'travel_date' => ['nullable', 'date', 'after_or_equal:today'],
            'seats' => ['nullable', 'integer', 'min:1', 'max:10'],
            'pickup_stop' => ['nullable', 'string', 'max:150'],
            'dropoff_stop' => ['nullable', 'string', 'max:150'],
            'fare_ils' => ['nullable', 'numeric', 'min:0'],
            'client_mutation_id' => ['nullable', 'string', 'max:64'],
        ]);

        $seatsRequested = (int) ($validated['seats'] ?? 1);
        $mutationId = $validated['client_mutation_id'] ?? null;
        $travelDate = ! empty($validated['travel_date'])
            ? Carbon::parse($validated['travel_date'])->toDateString()
            : today()->toDateString();
        $isToday = ($travelDate === today()->toDateString());

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

        return DB::transaction(function () use ($user, $validated, $seatsRequested, $mutationId, $travelDate, $isToday) {
            /** @var DriverRoute $route */
            $route = DriverRoute::with('driver.vehicle')->lockForUpdate()->findOrFail($validated['route_id']);
            $vehicle = $route->driver?->vehicle;

            $totalCapacity = $vehicle ? (int) $vehicle->seat_count : 0;

            // Atomic sum of already confirmed seats for this travel_date
            $bookedSeatsQuery = Booking::where('route_id', $route->id)
                ->where('status', 'confirmed')
                ->lockForUpdate();

            if ($isToday) {
                $bookedSeatsQuery->where(function ($q) use ($travelDate) {
                    $q->whereDate('travel_date', $travelDate)
                        ->orWhereNull('travel_date');
                });
            } else {
                $bookedSeatsQuery->whereDate('travel_date', $travelDate);
            }

            $bookedSeats = (int) $bookedSeatsQuery->sum('seats');

            $freeSeats = max(0, $totalCapacity - $bookedSeats);

            if ($freeSeats < $seatsRequested) {
                return response()->json([
                    'ok' => false,
                    'error' => "عذراً، لم تتبقَ مقاعد كافية على هذه الرحلة (المقاعد المتاحة: {$freeSeats})",
                    'free_seats' => $freeSeats,
                ], 422);
            }

            $fare = isset($validated['fare_ils']) && is_numeric($validated['fare_ils'])
                ? (float) $validated['fare_ils']
                : null;

            if ($fare === null || $fare <= 0) {
                // Auto-calculate fare from route stops based on pickup/dropoff stops and seats count
                $pickup = $validated['pickup_stop'] ?? null;
                $dropoff = $validated['dropoff_stop'] ?? null;
                $fare = $route->calculateSegmentFare($pickup, $dropoff, $seatsRequested);
            }

            /** @var Booking $booking */
            $booking = Booking::create([
                'rider_id' => $user->id,
                'route_id' => $route->id,
                'travel_date' => $travelDate,
                'seats' => $seatsRequested,
                'pickup_stop' => $validated['pickup_stop'] ?? $route->start_point,
                'dropoff_stop' => $validated['dropoff_stop'] ?? null,
                'fare_ils' => $fare,
                'status' => 'confirmed',
                'client_mutation_id' => $mutationId,
            ]);

            $booking->load(['route.driver.vehicle']);

            // Notify driver of the new booking
            $driver = $route->driver;
            if ($driver) {
                $dateLabel = $isToday ? 'اليوم' : "تاريخ {$travelDate}";
                $notifTitle = $isToday ? 'حجز مقعد جديد 🚐' : 'حجز مسبق جديد 📅';
                $driver->notify(new MishwarNotification(
                    title: $notifTitle,
                    message: "قام {$user->name} بحجز {$seatsRequested} مقاعد لـ ({$dateLabel}) على رحلتك ({$booking->pickup_stop} إلى {$booking->dropoff_stop}).",
                    type: 'booking',
                    icon: $isToday ? '🚐' : '📅',
                    actionUrl: route('driver.manifest', ['date' => $travelDate]),
                    extra: ['booking_id' => $booking->id, 'travel_date' => $travelDate]
                ));
            }

            return response()->json([
                'ok' => true,
                'message' => 'تم تأكيد حجزك بنجاح',
                'booking' => $this->formatBookingForRider($booking),
            ], 201);
        });
    }

    /**
     * Get pre-formatted bookings collection for a rider.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getRiderBookings(User $user): Collection
    {
        return Booking::where('rider_id', $user->id)
            ->with(['route.driver.vehicle', 'locationShare'])
            ->latest()
            ->get()
            ->map(fn (Booking $b) => $this->formatBookingForRider($b));
    }

    /**
     * Render the rider's bookings page with preloaded initial bookings.
     */
    public function riderBookingsPage(): View
    {
        $user = Auth::user();
        $initialBookings = $this->getRiderBookings($user);

        return view('rider.bookings', [
            'initialBookings' => $initialBookings,
        ]);
    }

    /**
     * Get the authenticated rider's bookings with driver contact and drift warning.
     */
    public function riderBookings(): JsonResponse
    {
        $user = Auth::user();
        $bookings = $this->getRiderBookings($user);

        return response()->json([
            'ok' => true,
            'count' => $bookings->count(),
            'bookings' => $bookings,
        ]);
    }

    /**
     * Driver views manifest of booked riders for pre-trip confirmation.
     */
    public function driverManifest(Request $request): JsonResponse
    {
        $user = Auth::user();

        if ($user->role !== 'driver') {
            return response()->json(['error' => 'حساب السائق مطلوب'], 403);
        }

        $dateParam = trim((string) $request->query('date', ''));
        $targetDate = $dateParam !== '' ? $dateParam : today()->toDateString();
        try {
            $parsedDate = Carbon::parse($targetDate);
        } catch (\Exception $e) {
            $parsedDate = today();
            $targetDate = $parsedDate->toDateString();
        }

        $dayOfWeek = $parsedDate->dayOfWeek;
        $isToday = $parsedDate->isToday();
        $todayDate = today()->toDateString();
        $tomorrowDate = today()->addDay()->toDateString();

        // Check driver's schedule for this day to pick scheduled route if available
        $schedule = $user->schedules()->where('day_of_week', $dayOfWeek)->first();
        $route = ($schedule && $schedule->route_id)
            ? DriverRoute::find($schedule->route_id)
            : ($user->driverRoute ?? $user->driverRoutes()->first());

        if (! $route) {
            return response()->json(['error' => 'لا يوجد مسار مسجل للسائق'], 404);
        }

        $bookingsQuery = Booking::where('route_id', $route->id)
            ->with(['rider', 'locationShare'])
            ->latest();

        if ($isToday) {
            $bookingsQuery->where(function ($q) use ($targetDate) {
                $q->whereDate('travel_date', $targetDate)
                    ->orWhereNull('travel_date');
            });
        } else {
            $bookingsQuery->whereDate('travel_date', $targetDate);
        }

        $bookings = $bookingsQuery
            ->get()
            ->map(function (Booking $b) {
                // Privacy Enforcement: phone unlocked if status is confirmed, boarded, or completed
                $canCall = in_array($b->status, ['confirmed', 'boarded', 'completed']);
                $share = $b->locationShare;

                return [
                    'id' => $b->id,
                    'rider_id' => $b->rider_id,
                    'rider_name' => $b->rider->name,
                    'rider_phone' => $canCall ? $b->rider->phone : null,
                    'can_call' => $canCall,
                    'seats' => $b->seats,
                    'travel_date' => $b->travel_date ? Carbon::parse($b->travel_date)->toDateString() : $b->created_at->toDateString(),
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

        // Calculate counts for manifest day tabs
        $todayCount = (int) Booking::where('route_id', $route->id)
            ->whereIn('status', ['confirmed', 'boarded'])
            ->where(function ($q) use ($todayDate) {
                $q->whereDate('travel_date', $todayDate)
                    ->orWhereNull('travel_date');
            })
            ->count();

        $tomorrowCount = (int) Booking::where('route_id', $route->id)
            ->whereIn('status', ['confirmed', 'boarded'])
            ->whereDate('travel_date', $tomorrowDate)
            ->count();

        $vehicle = $user->vehicle;
        $totalCapacity = $vehicle ? (int) $vehicle->seat_count : 0;

        $targetBookedSeatsQuery = Booking::where('route_id', $route->id)
            ->where('status', 'confirmed');

        if ($isToday) {
            $targetBookedSeatsQuery->where(function ($q) use ($targetDate) {
                $q->whereDate('travel_date', $targetDate)
                    ->orWhereNull('travel_date');
            });
        } else {
            $targetBookedSeatsQuery->whereDate('travel_date', $targetDate);
        }

        $targetBookedSeats = (int) $targetBookedSeatsQuery->sum('seats');
        $targetFreeSeats = max(0, $totalCapacity - $targetBookedSeats);

        return response()->json([
            'ok' => true,
            'route_id' => $route->id,
            'route_name' => $route->name,
            'target_date' => $targetDate,
            'is_today' => $isToday,
            'today_count' => $todayCount,
            'tomorrow_count' => $tomorrowCount,
            'is_trip_active' => (bool) $route->is_trip_active,
            'booked_seats' => $targetBookedSeats,
            'free_seats' => $targetFreeSeats,
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

        // Notify rider of cancellation
        $rider = $booking->rider;
        if ($rider) {
            $rider->notify(new MishwarNotification(
                title: 'إلغاء الحجز من قِبل السائق ⚠️',
                message: "تم إلغاء حجزك على رحلة {$booking->route->name}. السبب: {$reason}",
                type: 'booking',
                icon: '⚠️',
                actionUrl: route('rider.bookings'),
                extra: ['booking_id' => $booking->id, 'status' => 'cancelled_by_driver']
            ));
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

        // Transition booking to boarded status
        $booking->update([
            'status' => 'boarded',
        ]);

        // When boarded, end location sharing as rider is now in the vehicle
        if ($booking->locationShare) {
            $booking->locationShare->update([
                'status' => 'ended',
                'last_latitude' => null,
                'last_longitude' => null,
            ]);
        }

        // Notify rider of confirmed boarding
        $rider = $booking->rider;
        if ($rider) {
            $rider->notify(new MishwarNotification(
                title: 'تم تأكيد صعودك للمركبة 🚐',
                message: "أكد السائق {$user->name} صعودك للمركبة. نتمنى لك رحلة آمنة ومريحة.",
                type: 'booking',
                icon: '✅',
                actionUrl: route('rider.bookings'),
                extra: ['booking_id' => $booking->id, 'status' => 'boarded']
            ));
        }

        return response()->json([
            'ok' => true,
            'message' => 'تم تأكيد صعود الراكب للمركبة بنجاح',
            'booking_id' => $booking->id,
            'status' => 'boarded',
            'status_arabic' => 'تم الصعود للمركبة',
        ]);
    }

    /**
     * Mark a booking as completed after reaching destination.
     */
    public function completeBooking(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();

        /** @var Booking $booking */
        $booking = Booking::with('route')->findOrFail($id);

        if ($booking->route->driver_id !== $user->id && $booking->rider_id !== $user->id) {
            return response()->json(['error' => 'غير مصرح لك بإنهاء هذا الحجز'], 403);
        }

        $validated = $request->validate([
            'fare_ils' => ['nullable', 'numeric', 'min:0'],
        ]);

        $updates = ['status' => 'completed'];

        if (isset($validated['fare_ils']) && is_numeric($validated['fare_ils'])) {
            $updates['fare_ils'] = (float) $validated['fare_ils'];
        } elseif (($booking->fare_ils === null || (float) $booking->fare_ils <= 0) && $booking->route) {
            $updates['fare_ils'] = $booking->route->calculateSegmentFare(
                $booking->pickup_stop,
                $booking->dropoff_stop,
                max(1, (int) $booking->seats)
            );
        }

        $booking->update($updates);

        if ($booking->locationShare) {
            $booking->locationShare->update([
                'status' => 'ended',
                'last_latitude' => null,
                'last_longitude' => null,
            ]);
        }

        // Notify rider to rate the trip
        $rider = $booking->rider;
        if ($rider && $user->id !== $rider->id) {
            $rider->notify(new MishwarNotification(
                title: 'اكتملت رحلتك بنجاح! ⭐',
                message: "وصلت بالسلامة مع السائق {$booking->route?->driver?->name}. شاركنا تقييمك للرحلة لمساعدتنا في تحسين الخدمة.",
                type: 'rating',
                icon: '⭐',
                actionUrl: route('rides.history'),
                extra: ['booking_id' => $booking->id]
            ));
        }

        return response()->json([
            'ok' => true,
            'message' => 'تم إنهاء الرحلة وتأكيد الوصول بنجاح',
            'booking_id' => $booking->id,
            'status' => 'completed',
            'status_arabic' => 'مكتمل',
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

        // Route drift calculation ONLY needed for active rides (confirmed or boarded)
        $isRideActive = in_array($booking->status, ['confirmed', 'boarded']);
        $driftInfo = ($isRideActive && $route) ? $this->driftService->checkDrift($route) : ['drifting' => false];

        // Privacy Rule: driver phone is disclosed when booking is confirmed, boarded, or completed
        $driverPhone = in_array($booking->status, ['confirmed', 'boarded', 'completed']) ? $driver?->phone : null;

        $locationShare = $booking->locationShare;

        return [
            'id' => $booking->id,
            'travel_date' => $booking->travel_date ? Carbon::parse($booking->travel_date)->toDateString() : $booking->created_at->toDateString(),
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
