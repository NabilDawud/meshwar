<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\LocationShare;
use App\Notifications\MishwarNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LocationShareController extends Controller
{
    /**
     * Driver requests a booked rider to share their live location to facilitate pickup.
     */
    public function requestShare(Request $request): JsonResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'booking_id' => ['required', 'exists:bookings,id'],
        ]);

        /** @var Booking $booking */
        $booking = Booking::with('route')->findOrFail($validated['booking_id']);

        if ($booking->route->driver_id !== $user->id) {
            return response()->json(['error' => 'غير مصرح لك بطلب موقع هذا الراكب'], 403);
        }

        if ($booking->status !== 'confirmed') {
            return response()->json(['error' => 'لا يمكن طلب الموقع لحجز غير مؤكد'], 422);
        }

        $share = LocationShare::updateOrCreate(
            ['booking_id' => $booking->id],
            [
                'rider_id' => $booking->rider_id,
                'driver_id' => $user->id,
                'status' => 'requested',
                'expires_at' => now()->addMinutes(15),
            ]
        );

        // Notify rider of location share request
        $rider = $booking->rider;
        if ($rider) {
            $rider->notify(new MishwarNotification(
                title: 'طلب مشاركة الموقع المباشر 📍',
                message: "طلب السائق {$user->name} مشاركة موقعك المباشر لمدة 15 دقيقة لتسهيل نقطة اللقاء.",
                type: 'location',
                icon: '📍',
                actionUrl: route('rider.bookings'),
                extra: ['booking_id' => $booking->id, 'share_id' => $share->id]
            ));
        }

        return response()->json([
            'ok' => true,
            'message' => 'تم إرسال طلب مشاركة الموقع للراكب بنجاح',
            'share_id' => $share->id,
            'status' => 'requested',
            'status_arabic' => 'بانتظار موافقة الراكب',
        ]);
    }

    /**
     * Rider explicitly responds to driver's location sharing request (Yes or No).
     */
    public function respond(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();

        /** @var LocationShare $share */
        $share = LocationShare::findOrFail($id);

        if ($share->rider_id !== $user->id) {
            return response()->json(['error' => 'غير مصرح لك بالرد على هذا الطلب'], 403);
        }

        $validated = $request->validate([
            'action' => ['required', 'string', 'in:accept,reject'],
        ]);

        if ($validated['action'] === 'accept') {
            $share->update([
                'status' => 'active',
                'expires_at' => now()->addMinutes(15),
            ]);

            // Notify driver of location sharing approval
            $driver = $share->driver;
            if ($driver) {
                $driver->notify(new MishwarNotification(
                    title: 'تمت الموافقة على مشاركة الموقع 📍',
                    message: "وافق الراكب {$user->name} على مشاركة موقعه المباشر معك لمدة 15 دقيقة.",
                    type: 'location',
                    icon: '📍',
                    actionUrl: route('driver.manifest'),
                    extra: ['share_id' => $share->id, 'booking_id' => $share->booking_id]
                ));
            }

            return response()->json([
                'ok' => true,
                'status' => 'active',
                'message' => 'أنت تشارك موقعك المباشر الآن مع السائق',
                'expires_at' => $share->expires_at->toISOString(),
            ]);
        }

        // Rider chose NO: refuse sharing and purge any coordinates
        $share->update([
            'status' => 'rejected',
            'last_latitude' => null,
            'last_longitude' => null,
        ]);

        // Notify driver of refusal
        $driver = $share->driver;
        if ($driver) {
            $driver->notify(new MishwarNotification(
                title: 'رفض مشاركة الموقع ℹ️',
                message: "اعتذر الراكب {$user->name} عن مشاركة موقعه المباشر.",
                type: 'location',
                icon: 'ℹ️',
                actionUrl: route('driver.manifest'),
                extra: ['share_id' => $share->id, 'booking_id' => $share->booking_id]
            ));
        }

        return response()->json([
            'ok' => true,
            'status' => 'rejected',
            'message' => 'تم رفض مشاركة الموقع',
        ]);
    }

    /**
     * Rider sends periodic GPS coordinates while live sharing is active.
     */
    public function updatePosition(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();

        /** @var LocationShare $share */
        $share = LocationShare::findOrFail($id);

        if ($share->rider_id !== $user->id) {
            return response()->json(['error' => 'غير مصرح لك بتحديث هذا الموقع'], 403);
        }

        // Privacy Policy Enforcement: refuse updates if not active or expired
        if (! $share->isActive()) {
            return response()->json([
                'error' => 'مشاركة الموقع غير نشطة أو انتهت صلاحيتها تلقائياً',
                'is_expired' => $share->isExpired(),
            ], 403);
        }

        $validated = $request->validate([
            'lat' => ['required', 'numeric', 'between:31.1,31.7'],
            'lng' => ['required', 'numeric', 'between:34.1,34.6'],
        ]);

        // Quantize coordinate to 5 decimals (~1.1m precision) per gaza-geo-sync standard
        $lat = round((float) $validated['lat'], 5);
        $lng = round((float) $validated['lng'], 5);

        $share->update([
            'last_latitude' => $lat,
            'last_longitude' => $lng,
            'last_shared_at' => now(),
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'تم تحديث موقع الراكب',
        ]);
    }

    /**
     * Terminate live location sharing (either manually by rider/driver, or automatically on trip start).
     * Privacy Policy Enforcement: Coordinates are strictly purged from the record on termination.
     */
    public function end(int $id): JsonResponse
    {
        $user = Auth::user();

        /** @var LocationShare $share */
        $share = LocationShare::findOrFail($id);

        if ($share->rider_id !== $user->id && $share->driver_id !== $user->id) {
            return response()->json(['error' => 'غير مصرح لك بإنهاء مشاركة الموقع'], 403);
        }

        // Privacy purge: wipe stored GPS coordinates
        $share->update([
            'status' => 'ended',
            'last_latitude' => null,
            'last_longitude' => null,
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'تم إنهاء مشاركة الموقع وحذف الإحداثيات بنجاح',
            'status' => 'ended',
        ]);
    }
}
