<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\DriverRoute;
use App\Models\DriverSchedule;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class DriverScheduleController extends Controller
{
    /**
     * Day definitions ordered from Saturday (start of week in Palestine) to Friday.
     */
    public const DAYS_ORDER = [
        6 => 'السبت',
        0 => 'الأحد',
        1 => 'الإثنين',
        2 => 'الثلاثاء',
        3 => 'الأربعاء',
        4 => 'الخميس',
        5 => 'الجمعة',
    ];

    /**
     * Clean short day names without the leading "الـ" prefix.
     */
    public const DAYS_SHORT = [
        6 => 'سبت',
        0 => 'أحد',
        1 => 'إثنين',
        2 => 'ثلاثاء',
        3 => 'أربعاء',
        4 => 'خميس',
        5 => 'جمعة',
    ];

    /**
     * 2-letter day abbreviations for compact mobile displays.
     */
    public const DAYS_ABBR = [
        6 => 'سب',
        0 => 'أح',
        1 => 'اث',
        2 => 'ثل',
        3 => 'أر',
        4 => 'خم',
        5 => 'جم',
    ];

    /**
     * Retrieve the authenticated driver's 7-day schedule matrix.
     *
     * @param  Collection|null  $preloadedSchedules
     * @param  DriverRoute|null  $preloadedRoute
     * @return array<int, array{id: ?int, day_of_week: int, day_name_arabic: string, day_short_name: string, day_abbr: string, route_id: ?int, route_name: ?string, start_time: string, end_time: string, is_day_off: bool}>
     */
    public static function getDriverWeeklySchedule(User $user, $preloadedSchedules = null, $preloadedRoute = null): array
    {
        if ($preloadedSchedules !== null) {
            $existing = $preloadedSchedules->keyBy('day_of_week');
        } else {
            $existing = $user->schedules()->with('route')->get()->keyBy('day_of_week');
        }

        $activeRoute = $preloadedRoute ?? ($user->driverRoute ?? $user->driverRoutes()->first());

        $schedules = [];
        foreach (self::DAYS_ORDER as $dayNum => $dayName) {
            $shortName = self::DAYS_SHORT[$dayNum] ?? $dayName;
            $abbr = self::DAYS_ABBR[$dayNum] ?? $dayName;

            if ($existing->has($dayNum)) {
                $sched = $existing->get($dayNum);
                $schedules[] = [
                    'id' => $sched->id,
                    'day_of_week' => $dayNum,
                    'day_name_arabic' => $dayName,
                    'day_short_name' => $shortName,
                    'day_abbr' => $abbr,
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
                    'day_short_name' => $shortName,
                    'day_abbr' => $abbr,
                    'route_id' => $isFriday ? null : $activeRoute?->id,
                    'route_name' => $isFriday ? null : $activeRoute?->name,
                    'start_time' => '07:00',
                    'end_time' => '16:00',
                    'is_day_off' => $isFriday,
                ];
            }
        }

        return $schedules;
    }

    /**
     * Render the dedicated mobile-optimized weekly schedule management page.
     */
    public function showView(): View
    {
        /** @var User $user */
        $user = Auth::user();

        if ($user->role !== 'driver') {
            abort(403, 'غير مصرح - حساب السائق مطلوب');
        }

        $user->load(['driverRoutes', 'schedules.route']);
        $routes = $user->driverRoutes;
        $activeRoute = $routes->firstWhere('is_active', true) ?? $routes->first();
        $schedules = self::getDriverWeeklySchedule($user, $user->schedules, $activeRoute);

        return view('driver.schedule', compact('routes', 'schedules', 'activeRoute'));
    }

    /**
     * Retrieve the authenticated driver's 7-day schedule.
     */
    public function index(): JsonResponse
    {
        $user = Auth::user();

        if ($user->role !== 'driver') {
            return response()->json(['error' => 'حساب السائق مطلوب'], 403);
        }

        return response()->json([
            'ok' => true,
            'schedules' => self::getDriverWeeklySchedule($user),
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
