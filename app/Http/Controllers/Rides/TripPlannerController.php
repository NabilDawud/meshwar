<?php

namespace App\Http\Controllers\Rides;

use App\Http\Controllers\Controller;
use App\Models\DriverRoute;
use App\Services\TripPlannerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TripPlannerController extends Controller
{
    /**
     * Plan a trip across Gaza transit network using deterministic graph search.
     * Fully accessible to guests and riders without authentication.
     */
    public function plan(Request $request, TripPlannerService $planner): JsonResponse
    {
        $from = (string) $request->query('from', '');
        $to = (string) $request->query('to', '');
        $time = $request->query('time');

        $result = $planner->plan($from, $to, $time ?: null);

        $payloadBytes = strlen(json_encode($result));

        return response()->json($result)
            ->header('X-Payload-Bytes', (string) $payloadBytes);
    }

    /**
     * Return lightweight route graph for client-side offline pathfinding in browser.
     * Strict byte budget (< 10 KB).
     */
    public function graph(): JsonResponse
    {
        $routes = DriverRoute::query()
            ->whereIn('status', ['on_route', 'temporary_route'])
            ->with(['driver.vehicle'])
            ->get()
            ->map(function ($route) {
                $vehicle = $route->driver?->vehicle;

                return [
                    'id' => $route->id,
                    'name' => $route->name,
                    'departure_time' => $route->first_trip_time,
                    'status' => $route->status,
                    'vehicle' => [
                        'type' => $vehicle?->type ?? 'ajala',
                        'type_arabic' => $vehicle?->type_arabic ?? 'عجلة',
                        'seat_count' => $vehicle?->seat_count ?? 10,
                        'photo' => $vehicle?->photos[0] ?? '/images/sample-ajala.svg',
                        'driver_name' => $route->driver?->name ?? 'سائق مشوار',
                    ],
                    'stops' => $route->stops ?? [],
                ];
            });

        $payload = [
            'ok' => true,
            'count' => $routes->count(),
            'routes' => $routes,
        ];

        $payloadBytes = strlen(json_encode($payload));

        return response()->json($payload)
            ->header('X-Payload-Bytes', (string) $payloadBytes);
    }
}
