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
}
