<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\DriverSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DriverScheduleController extends Controller
{
    /**
     * Day definitions ordered from Saturday (start of week in Palestine) to Friday.
     */
    protected const DAYS_ORDER = [
        6 => 'السبت',
        0 => 'الأحد',
        1 => 'الإثنين',
        2 => 'الثلاثاء',
        3 => 'الأربعاء',
        4 => 'الخميس',
        5 => 'الجمعة',
    ];

    /**
     * Retrieve the authenticated driver's 7-day schedule.
     */
    public function index(): JsonResponse
    {
        $user = Auth::user();

        if ($user->role !== 'driver') {
            return response()->json(['error' => 'حساب السائق مطلوب'], 403);
        }

        $existing = $user->schedules()->with('route')->get()->keyBy('day_of_week');
        $activeRoute = $user->driverRoute;

        $schedules = [];
        foreach (self::DAYS_ORDER as $dayNum => $dayName) {
            if ($existing->has($dayNum)) {
                $sched = $existing->get($dayNum);
                $schedules[] = [
                    'id' => $sched->id,
                    'day_of_week' => $dayNum,
                    'day_name_arabic' => $dayName,
                    'route_id' => $sched->route_id,
                    'route_name' => $sched->route?->name,
                    'start_time' => $sched->start_time ?? '07:00',
                    'end_time' => $sched->end_time ?? '16:00',
                    'is_day_off' => (bool) $sched->is_day_off,
                ];
            } else {
                // Friday defaults to day off; other days default to active route
                $isFriday = ($dayNum === 5);
                $schedules[] = [
                    'id' => null,
                    'day_of_week' => $dayNum,
                    'day_name_arabic' => $dayName,
                    'route_id' => $isFriday ? null : $activeRoute?->id,
                    'route_name' => $isFriday ? null : $activeRoute?->name,
                    'start_time' => '07:00',
                    'end_time' => '16:00',
                    'is_day_off' => $isFriday,
                ];
            }
        }

        return response()->json([
            'ok' => true,
            'schedules' => $schedules,
        ]);
    }

    /**
     * Bulk save or update the driver's weekly schedule matrix.
     */
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();

        if ($user->role !== 'driver') {
            return response()->json(['error' => 'حساب السائق مطلوب'], 403);
        }

        $validated = $request->validate([
            'schedules' => ['required', 'array', 'min:1'],
            'schedules.*.day_of_week' => ['required', 'integer', 'between:0,6'],
            'schedules.*.route_id' => ['nullable', 'exists:driver_routes,id'],
            'schedules.*.start_time' => ['nullable', 'string', 'regex:/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/'],
            'schedules.*.end_time' => ['nullable', 'string', 'regex:/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/'],
            'schedules.*.is_day_off' => ['nullable', 'boolean'],
        ]);

        foreach ($validated['schedules'] as $item) {
            DriverSchedule::updateOrCreate(
                [
                    'driver_id' => $user->id,
                    'day_of_week' => $item['day_of_week'],
                ],
                [
                    'route_id' => ! empty($item['is_day_off']) ? null : ($item['route_id'] ?? null),
                    'start_time' => $item['start_time'] ?? '07:00',
                    'end_time' => $item['end_time'] ?? '16:00',
                    'is_day_off' => (bool) ($item['is_day_off'] ?? false),
                ]
            );
        }

        return response()->json([
            'ok' => true,
            'message' => 'تم حفظ جدول مواعيد ومسارات الأسبوع بنجاح',
        ]);
    }
}
