<?php

namespace App\Http\Controllers;

use App\Models\SpecialOffer;
use App\Models\SpecialRequest;
use App\Notifications\MishwarNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SpecialRequestController extends Controller
{
    /**
     * List special requests:
     * - For rider: list of their own requests with offers count.
     * - For driver: list of open requests available to bid on.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();

        if ($user->role === 'driver') {
            $tab = $request->query('tab', 'all');

            $query = SpecialRequest::query();

            if ($tab === 'accepted') {
                $query->where(function ($q) use ($user) {
                    $q->whereHas('offers', fn ($sub) => $sub->where('driver_id', $user->id)->where('status', 'accepted'))
                        ->orWhere(function ($sub) use ($user) {
                            $sub->where('status', 'accepted')
                                ->whereHas('offers', fn ($o) => $o->where('driver_id', $user->id));
                        });
                });
            } elseif ($tab === 'open') {
                $query->where('status', 'open');
            } else {
                $query->where(function ($q) use ($user) {
                    $q->where('status', 'open')
                        ->orWhereHas('offers', fn ($sub) => $sub->where('driver_id', $user->id));
                });
            }

            $query->with(['rider:id,name,phone', 'offers' => function ($q) use ($user) {
                $q->where('driver_id', $user->id);
            }])
                ->withCount('offers')
                ->orderBy('id', 'desc');

            $acceptedTotal = SpecialRequest::whereHas('offers', fn ($sub) => $sub->where('driver_id', $user->id)->where('status', 'accepted'))->count();
            $openTotal = SpecialRequest::where('status', 'open')->count();

            if ($request->has('page')) {
                $paginated = $query->paginate(max(1, (int) $request->query('per_page', 8)));
                $mapped = collect($paginated->items())->map(function (SpecialRequest $r) {
                    $myOffer = $r->offers->first();
                    $isAcceptedDriver = ($myOffer && $myOffer->status === 'accepted') || ($r->accepted_offer_id && $myOffer && $r->accepted_offer_id === $myOffer->id);

                    return [
                        'id' => $r->id,
                        'origin_name' => $r->origin_name,
                        'destination_name' => $r->destination_name,
                        'departure_date' => $r->departure_date->format('Y-m-d'),
                        'departure_window' => $r->departure_window,
                        'seats_needed' => $r->seats_needed,
                        'notes' => $r->notes,
                        'status' => $r->status,
                        'status_arabic' => $r->status_arabic,
                        'rider_name' => $r->rider->name,
                        'rider_phone' => $isAcceptedDriver ? $r->rider->phone : null,
                        'can_call_rider' => $isAcceptedDriver,
                        'is_accepted' => $isAcceptedDriver,
                        'offers_count' => $r->offers_count,
                        'my_offer' => $myOffer ? [
                            'id' => $myOffer->id,
                            'price_ils' => (float) $myOffer->price_ils,
                            'notes' => $myOffer->notes,
                            'status' => $myOffer->status,
                            'status_arabic' => $myOffer->status_arabic,
                        ] : null,
                        'created_at' => $r->created_at->toISOString(),
                    ];
                });

                return response()->json([
                    'ok' => true,
                    'count' => $paginated->count(),
                    'accepted_jobs_count' => $acceptedTotal,
                    'open_opportunities_count' => $openTotal,
                    'current_page' => $paginated->currentPage(),
                    'last_page' => $paginated->lastPage(),
                    'total' => $paginated->total(),
                    'per_page' => $paginated->perPage(),
                    'from' => $paginated->firstItem(),
                    'to' => $paginated->lastItem(),
                    'requests' => $mapped,
                ]);
            }

            $requests = $query->get()->map(function (SpecialRequest $r) {
                $myOffer = $r->offers->first();
                $isAcceptedDriver = ($myOffer && $myOffer->status === 'accepted') || ($r->accepted_offer_id && $myOffer && $r->accepted_offer_id === $myOffer->id);

                return [
                    'id' => $r->id,
                    'origin_name' => $r->origin_name,
                    'destination_name' => $r->destination_name,
                    'departure_date' => $r->departure_date->format('Y-m-d'),
                    'departure_window' => $r->departure_window,
                    'seats_needed' => $r->seats_needed,
                    'notes' => $r->notes,
                    'status' => $r->status,
                    'status_arabic' => $r->status_arabic,
                    'rider_name' => $r->rider->name,
                    'rider_phone' => $isAcceptedDriver ? $r->rider->phone : null,
                    'can_call_rider' => $isAcceptedDriver,
                    'is_accepted' => $isAcceptedDriver,
                    'offers_count' => $r->offers_count,
                    'my_offer' => $myOffer ? [
                        'id' => $myOffer->id,
                        'price_ils' => (float) $myOffer->price_ils,
                        'notes' => $myOffer->notes,
                        'status' => $myOffer->status,
                        'status_arabic' => $myOffer->status_arabic,
                    ] : null,
                    'created_at' => $r->created_at->toISOString(),
                ];
            });

            return response()->json([
                'ok' => true,
                'count' => $requests->count(),
                'accepted_jobs_count' => $acceptedTotal,
                'open_opportunities_count' => $openTotal,
                'requests' => $requests,
            ]);
        }

        // Rider's own requests
        $query = SpecialRequest::where('rider_id', $user->id)
            ->with(['offers.driver.vehicle'])
            ->orderBy('id', 'desc');

        if ($request->has('page')) {
            $paginated = $query->paginate(max(1, (int) $request->query('per_page', 10)));
            $mapped = collect($paginated->items())->map(fn (SpecialRequest $r) => $this->formatRequestForRider($r));

            return response()->json([
                'ok' => true,
                'count' => $paginated->count(),
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'total' => $paginated->total(),
                'per_page' => $paginated->perPage(),
                'from' => $paginated->firstItem(),
                'to' => $paginated->lastItem(),
                'requests' => $mapped,
            ]);
        }

        $requests = $query->get()->map(fn (SpecialRequest $r) => $this->formatRequestForRider($r));

        return response()->json([
            'ok' => true,
            'count' => $requests->count(),
            'requests' => $requests,
        ]);
    }

    /**
     * Rider creates a private charter request.
     * Supports offline outbox mutation with client_mutation_id.
     */
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();

        if ($user->role === 'driver') {
            return response()->json([
                'ok' => false,
                'error' => 'حساب السائق مخصص لتقديم عروض الأسعار على الطلبات، ولا يمكن نشر طلب مشوار خاص بنفس الحساب.',
            ], 403);
        }

        $validated = $request->validate([
            'origin_name' => ['required', 'string', 'max:150'],
            'destination_name' => ['required', 'string', 'max:150'],
            'departure_date' => ['required', 'date'],
            'departure_window' => ['required', 'string', 'max:100'],
            'seats_needed' => ['nullable', 'integer', 'min:1', 'max:20'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'client_mutation_id' => ['nullable', 'string', 'max:64'],
        ]);

        $mutationId = $validated['client_mutation_id'] ?? null;

        if ($mutationId) {
            $existing = SpecialRequest::where('rider_id', $user->id)
                ->where('client_mutation_id', $mutationId)
                ->with(['offers.driver.vehicle'])
                ->first();

            if ($existing) {
                return response()->json([
                    'ok' => true,
                    'already_synced' => true,
                    'message' => 'تم استرجاع الطلب مسبقاً',
                    'request' => $this->formatRequestForRider($existing),
                ]);
            }
        }

        $specialRequest = SpecialRequest::create([
            'rider_id' => $user->id,
            'origin_name' => $validated['origin_name'],
            'destination_name' => $validated['destination_name'],
            'departure_date' => $validated['departure_date'],
            'departure_window' => $validated['departure_window'],
            'seats_needed' => (int) ($validated['seats_needed'] ?? 1),
            'notes' => $validated['notes'] ?? null,
            'status' => 'open',
            'client_mutation_id' => $mutationId,
        ]);

        $specialRequest->load(['offers.driver.vehicle']);

        return response()->json([
            'ok' => true,
            'message' => 'تم نشر طلب المشوار الخاص بنجاح، ستصلك عروض السائقين قريباً',
            'request' => $this->formatRequestForRider($specialRequest),
        ], 201);
    }

    /**
     * Show details of a special request with side-by-side offers comparison.
     */
    public function show(int $id): JsonResponse
    {
        $user = Auth::user();

        /** @var SpecialRequest $specialRequest */
        $specialRequest = SpecialRequest::with(['rider', 'offers.driver.vehicle', 'offers.messages'])->findOrFail($id);

        if ($user->role === 'driver') {
            $myOffer = $specialRequest->offers->where('driver_id', $user->id)->first();

            // Privacy Rule: Rider phone revealed ONLY if driver's offer is accepted
            $isAcceptedDriver = ($myOffer && $myOffer->status === 'accepted') || ($specialRequest->accepted_offer_id && $myOffer && $specialRequest->accepted_offer_id === $myOffer->id);
            $riderPhone = $isAcceptedDriver ? $specialRequest->rider->phone : null;

            return response()->json([
                'ok' => true,
                'request' => [
                    'id' => $specialRequest->id,
                    'origin_name' => $specialRequest->origin_name,
                    'destination_name' => $specialRequest->destination_name,
                    'departure_date' => $specialRequest->departure_date->format('Y-m-d'),
                    'departure_window' => $specialRequest->departure_window,
                    'seats_needed' => $specialRequest->seats_needed,
                    'notes' => $specialRequest->notes,
                    'status' => $specialRequest->status,
                    'status_arabic' => $specialRequest->status_arabic,
                    'rider_name' => $specialRequest->rider->name,
                    'rider_phone' => $riderPhone,
                    'can_call_rider' => ! empty($riderPhone),
                    'is_accepted' => $isAcceptedDriver,
                    'created_at' => $specialRequest->created_at->toISOString(),
                    'my_offer' => $myOffer ? [
                        'id' => $myOffer->id,
                        'price_ils' => (float) $myOffer->price_ils,
                        'notes' => $myOffer->notes,
                        'status' => $myOffer->status,
                        'status_arabic' => $myOffer->status_arabic,
                        'messages_count' => $myOffer->messages->count(),
                    ] : null,
                ],
            ]);
        }

        if ($specialRequest->rider_id !== $user->id) {
            return response()->json(['error' => 'غير مصرح لك بعرض هذا الطلب'], 403);
        }

        return response()->json([
            'ok' => true,
            'request' => $this->formatRequestForRider($specialRequest),
        ]);
    }

    /**
     * Rider accepts one driver's offer:
     * - Books the trip
     * - Closes the request
     * - Marks accepted offer as 'accepted'
     * - Rejects other offers
     * - Unlocks rider phone to the chosen driver
     */
    public function acceptOffer(int $requestId, int $offerId): JsonResponse
    {
        $user = Auth::user();

        /** @var SpecialRequest $specialRequest */
        $specialRequest = SpecialRequest::findOrFail($requestId);

        if ($specialRequest->rider_id !== $user->id) {
            return response()->json(['error' => 'غير مصرح لك بقبول عروض هذا الطلب'], 403);
        }

        if ($specialRequest->status !== 'open') {
            return response()->json(['error' => 'هذا الطلب غير متاح لقبول العروض حالياً'], 422);
        }

        /** @var SpecialOffer $chosenOffer */
        $chosenOffer = SpecialOffer::where('request_id', $specialRequest->id)->findOrFail($offerId);

        DB::transaction(function () use ($specialRequest, $chosenOffer) {
            // 1. Close request and record accepted offer
            $specialRequest->update([
                'status' => 'accepted',
                'accepted_offer_id' => $chosenOffer->id,
            ]);

            // 2. Mark chosen offer as accepted
            $chosenOffer->update([
                'status' => 'accepted',
            ]);

            // 3. Mark all other offers as rejected (tells other drivers)
            SpecialOffer::where('request_id', $specialRequest->id)
                ->where('id', '!=', $chosenOffer->id)
                ->update([
                    'status' => 'rejected',
                ]);
        });

        $specialRequest->load(['offers.driver.vehicle', 'offers.messages']);

        // 4. Notify winning driver
        $winningDriver = $chosenOffer->driver;
        if ($winningDriver) {
            $winningDriver->notify(new MishwarNotification(
                title: '🎉 تهانينا! تم قبول عرضك للمشوار الخاص',
                message: "وافق الراكب {$user->name} على عرضك بقيمة {$chosenOffer->price_ils} ₪. تم فك قفل رقم الهاتف للتنسيق المباشر.",
                type: 'offer',
                icon: '🎉',
                actionUrl: route('driver.offers'),
                extra: ['request_id' => $specialRequest->id, 'offer_id' => $chosenOffer->id]
            ));
        }

        // 5. Notify other drivers that the request was closed
        $otherOffers = SpecialOffer::where('request_id', $specialRequest->id)
            ->where('id', '!=', $chosenOffer->id)
            ->with('driver')
            ->get();

        foreach ($otherOffers as $other) {
            if ($other->driver) {
                $other->driver->notify(new MishwarNotification(
                    title: 'تم إغلاق طلب المشوار الخاص ℹ️',
                    message: "تم اختيار عرض آخر وإغلاق الطلب ({$specialRequest->origin_name} إلى {$specialRequest->destination_name}). نشكرك على تقديم عرضك.",
                    type: 'offer',
                    icon: 'ℹ️',
                    actionUrl: route('driver.offers'),
                    extra: ['request_id' => $specialRequest->id]
                ));
            }
        }

        return response()->json([
            'ok' => true,
            'message' => 'تم قبول العرض بنجاح وتأكيد المشوار الخاص',
            'request' => $this->formatRequestForRider($specialRequest),
        ]);
    }

    /**
     * Mark a special charter trip as completed after arrival.
     */
    public function complete(int $id): JsonResponse
    {
        $user = Auth::user();

        /** @var SpecialRequest $specialRequest */
        $specialRequest = SpecialRequest::with(['acceptedOffer'])->findOrFail($id);

        $driverId = $specialRequest->acceptedOffer?->driver_id;

        if ($specialRequest->rider_id !== $user->id && $driverId !== $user->id) {
            return response()->json(['error' => 'غير مصرح لك بإنهاء هذا المشوار'], 403);
        }

        if ($specialRequest->status !== 'accepted') {
            return response()->json(['error' => 'لا يمكن إنهاء مشوار غير مؤكد'], 422);
        }

        $specialRequest->update([
            'status' => 'completed',
        ]);

        // Notify rider to rate the charter trip if completed by driver
        $rider = $specialRequest->rider;
        if ($rider && $user->id !== $rider->id) {
            $rider->notify(new MishwarNotification(
                title: 'اكتمل مشوارك الخاص بنجاح! ⭐',
                message: "وصلت بالسلامة مع السائق {$specialRequest->acceptedOffer?->driver?->name}. شاركنا تقييمك للمشوار.",
                type: 'rating',
                icon: '⭐',
                actionUrl: route('rides.history'),
                extra: ['special_request_id' => $specialRequest->id]
            ));
        }

        return response()->json([
            'ok' => true,
            'message' => 'تم إنهاء المشوار الخاص وتأكيد الوصول بنجاح',
            'request_id' => $specialRequest->id,
            'status' => 'completed',
            'status_arabic' => 'مكتمل (تم الوصول)',
        ]);
    }

    /**
     * Format request for rider view with full side-by-side comparison of driver offers.
     *
     * @return array<string, mixed>
     */
    protected function formatRequestForRider(SpecialRequest $request): array
    {
        $offers = $request->offers->map(function (SpecialOffer $offer) {
            $driver = $offer->driver;
            $vehicle = $driver?->vehicle;

            return [
                'id' => $offer->id,
                'price_ils' => (float) $offer->price_ils,
                'notes' => $offer->notes,
                'status' => $offer->status,
                'status_arabic' => $offer->status_arabic,
                'created_at' => $offer->created_at->toISOString(),
                'messages_count' => $offer->messages ? $offer->messages->count() : 0,
                'driver' => [
                    'id' => $driver->id,
                    'name' => $driver->name,
                    'phone' => $driver->phone, // Visible to rider on offers for tap-to-call
                    'can_call' => ! empty($driver->phone),
                    'rating' => (float) $driver->average_rating,
                    'ratings_count' => (int) $driver->ratings_count,
                ],
                'vehicle' => $vehicle ? [
                    'type' => $vehicle->type,
                    'type_arabic' => $vehicle->type_arabic,
                    'seat_count' => $vehicle->seat_count,
                    'license_plate' => $vehicle->license_plate,
                    'photos' => $vehicle->photos ?? [],
                    'photo' => $vehicle->photos[0] ?? '/images/sample-ajala.svg',
                ] : null,
            ];
        })->values();

        return [
            'id' => $request->id,
            'origin_name' => $request->origin_name,
            'destination_name' => $request->destination_name,
            'departure_date' => $request->departure_date->format('Y-m-d'),
            'departure_window' => $request->departure_window,
            'seats_needed' => $request->seats_needed,
            'notes' => $request->notes,
            'status' => $request->status,
            'status_arabic' => $request->status_arabic,
            'accepted_offer_id' => $request->accepted_offer_id,
            'created_at' => $request->created_at->toISOString(),
            'offers_count' => $offers->count(),
            'offers' => $offers,
        ];
    }
}
