<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\DriverRoute;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DriverRouteController extends Controller
{
    /**
     * Get the authenticated driver's route.
     */
    public function show(): JsonResponse
    {
        $user = Auth::user();

        if ($user->role !== 'driver') {
            return response()->json(['error' => 'حساب السائق مطلوب'], 403);
        }

        $route = $user->driverRoute;

        return response()->json([
            'ok' => true,
            'route' => $route,
            'has_route' => $route !== null,
        ]);
    }

    /**
     * Store or update the authenticated driver's single main route with ordered stops and leg fares.
     */
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();

        if ($user->role !== 'driver') {
            return response()->json(['error' => 'حساب السائق مطلوب'], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'start_point' => ['required', 'string', 'max:150'],
            'first_trip_time' => ['required', 'string', 'regex:/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/'],
            'status' => ['nullable', 'string', 'in:on_route,temporary_route,off_route,skip_work'],
            'is_two_way' => ['nullable', 'boolean'],
            'stops' => ['required', 'array', 'min:2'],
            'stops.*.order' => ['required', 'integer'],
            'stops.*.name' => ['required', 'string'],
            'stops.*.lat' => ['required', 'numeric', 'between:31.1,31.7'],
            'stops.*.lng' => ['required', 'numeric', 'between:34.1,34.6'],
            'stops.*.fare_ils' => ['nullable', 'numeric', 'min:0'],
        ], [
            'name.required' => 'اسم مسار الخط مطلوب.',
            'start_point.required' => 'نقطة الانطلاق الأولى مطلوبة.',
            'first_trip_time.required' => 'توقيت أول رحلة صباحية مطلوب.',
            'stops.required' => 'يجب تحديد محطات الخط.',
            'stops.min' => 'يجب اختيار محطتين على الأقل (نقطة بداية ونقطة نهاية).',
        ]);

        $route = DriverRoute::updateOrCreate(
            ['driver_id' => $user->id],
            [
                'name' => $validated['name'],
                'start_point' => $validated['start_point'],
                'first_trip_time' => $validated['first_trip_time'],
                'status' => $validated['status'] ?? 'on_route',
                'is_two_way' => $request->has('is_two_way') ? $request->boolean('is_two_way') : true,
                'stops' => $validated['stops'],
            ]
        );

        return response()->json([
            'ok' => true,
            'message' => 'تم حفظ خط السير والمحطات بنجاح',
            'route' => $route,
        ]);
    }

    /**
     * Update operational status (on_route, temporary_route, off_route, skip_work).
     */
    public function updateStatus(Request $request): JsonResponse
    {
        $user = Auth::user();

        if ($user->role !== 'driver') {
            return response()->json(['error' => 'حساب السائق مطلوب'], 403);
        }

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:on_route,temporary_route,off_route,skip_work'],
        ]);

        $route = $user->driverRoute;
        if (! $route) {
            return response()->json(['error' => 'لا يوجد خط سير مسجل بعد'], 404);
        }

        $route->update(['status' => $validated['status']]);

        return response()->json([
            'ok' => true,
            'message' => 'تم تحديث حالة المسار التشغيلية',
            'status' => $route->status,
            'status_arabic' => $route->status_arabic,
        ]);
    }

    /**
     * Update active trip direction (outbound vs return).
     */
    public function updateDirection(Request $request): JsonResponse
    {
        $user = Auth::user();

        if ($user->role !== 'driver') {
            return response()->json(['error' => 'حساب السائق مطلوب'], 403);
        }

        $route = $user->driverRoute;
        if (! $route) {
            return response()->json(['error' => 'لا يوجد خط سير مسجل بعد'], 404);
        }

        $validated = $request->validate([
            'direction' => ['required', 'string', 'in:outbound,return'],
        ], [
            'direction.required' => 'يجب تحديد اتجاه الرحلة.',
            'direction.in' => 'الاتجاه يجب أن يكون إما رحلة الذهاب أو رحلة العودة.',
        ]);

        $route->update(['active_direction' => $validated['direction']]);

        return response()->json([
            'ok' => true,
            'message' => 'تم تحديث اتجاه الرحلة الحالي',
            'active_direction' => $route->active_direction,
            'active_direction_arabic' => $route->active_direction_arabic,
        ]);
    }

    /**
     * Broadcast live GPS position during active trip.
     * Only updates when driver is online and trip is active.
     */
    public function updatePosition(Request $request): JsonResponse
    {
        $user = Auth::user();

        if ($user->role !== 'driver') {
            return response()->json(['error' => 'حساب السائق مطلوب'], 403);
        }

        $route = $user->driverRoute;
        if (! $route) {
            return response()->json(['error' => 'لا يوجد خط سير مسجل'], 404);
        }

        $validated = $request->validate([
            'lat' => ['required', 'numeric', 'between:31.1,31.7'],
            'lng' => ['required', 'numeric', 'between:34.1,34.6'],
            'landmark' => ['nullable', 'string', 'max:150'],
            'is_trip_active' => ['nullable', 'boolean'],
        ]);

        $lat = round((float) $validated['lat'], 5);
        $lng = round((float) $validated['lng'], 5);

        $route->update([
            'last_latitude' => $lat,
            'last_longitude' => $lng,
            'last_landmark' => $validated['landmark'] ?? $route->last_landmark,
            'is_trip_active' => $validated['is_trip_active'] ?? $route->is_trip_active,
            'last_position_at' => now(),
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'تم تحديث الموقع المباشر بنجاح',
            'position' => [
                'lat' => (float) $route->last_latitude,
                'lng' => (float) $route->last_longitude,
                'landmark' => $route->last_landmark,
                'last_position_at' => $route->last_position_at?->toISOString(),
                'position_age_arabic' => $route->position_age_arabic,
                'is_trip_active' => (bool) $route->is_trip_active,
            ],
        ]);
    }
}
