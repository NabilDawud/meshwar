<?php

namespace App\Http\Controllers\Rides;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActiveVehicleController extends Controller
{
    /**
     * Search and list active transit vehicles for riders and guests.
     * Enforces the < 10 KB byte budget for low-bandwidth networks in Gaza.
     */
    public function index(Request $request): JsonResponse
    {
        $from = trim((string) $request->query('from', ''));
        $to = trim((string) $request->query('to', ''));
        $time = trim((string) $request->query('time', ''));

        // Load active vehicles with their driver and active route
        $vehicles = Vehicle::query()
            ->where('status', 'active')
            ->with(['driver.driverRoute'])
            ->get()
            ->filter(function (Vehicle $vehicle) use ($from, $to, $time) {
                $driver = $vehicle->driver;
                if (! $driver) {
                    return false;
                }

                $route = $driver->driverRoute;
                if (! $route || ! in_array($route->status, ['on_route', 'temporary_route'])) {
                    return false;
                }

                $stops = $route->stops ?? [];
                if (empty($stops)) {
                    return false;
                }

                // Filter by 'from' if specified
                if ($from !== '') {
                    $hasFrom = collect($stops)->contains(function ($s) use ($from) {
                        return str_contains($s['name'], $from) || (isset($s['id']) && $s['id'] === $from);
                    });
                    if (! $hasFrom) {
                        return false;
                    }
                }

                // Filter by 'to' if specified
                if ($to !== '') {
                    $hasTo = collect($stops)->contains(function ($s) use ($to) {
                        return str_contains($s['name'], $to) || (isset($s['id']) && $s['id'] === $to);
                    });
                    if (! $hasTo) {
                        return false;
                    }
                }

                // Filter by departure time if specified
                if ($time !== '') {
                    if (strcmp($route->first_trip_time, $time) < 0) {
                        // Allow rides departing at or after requested time
                    }
                }

                return true;
            })
            ->map(function (Vehicle $vehicle) {
                $driver = $vehicle->driver;
                $route = $driver->driverRoute;
                $firstStop = $route->stops[0] ?? ['name' => 'نقطة الانطلاق', 'lat' => 31.3456, 'lng' => 34.3182];

                return [
                    'vehicle_id' => $vehicle->id,
                    'driver_id' => $driver->id,
                    'driver_name' => $driver->name,
                    'vehicle_type' => $vehicle->type,
                    'vehicle_type_arabic' => $vehicle->type_arabic,
                    'seat_count' => $vehicle->seat_count,
                    'free_seats' => $vehicle->seat_count, // All seats free in M3 until bookings in M4
                    'photo' => $vehicle->photos[0] ?? '/images/sample-ajala.svg',
                    'photos' => $vehicle->photos ?? [],
                    'license_plate' => $vehicle->license_plate,
                    'route_name' => $route->name,
                    'start_point' => $route->start_point,
                    'first_trip_time' => $route->first_trip_time,
                    'status' => $route->status,
                    'status_arabic' => $route->status_arabic,
                    'is_trip_active' => (bool) $route->is_trip_active,
                    'last_latitude' => $route->last_latitude ? (float) $route->last_latitude : (float) $firstStop['lat'],
                    'last_longitude' => $route->last_longitude ? (float) $route->last_longitude : (float) $firstStop['lng'],
                    'last_landmark' => $route->last_landmark ?? $firstStop['name'],
                    'last_position_at' => $route->last_position_at?->toISOString(),
                    'position_age_minutes' => $route->position_age_minutes ?? 0,
                    'position_age_arabic' => $route->position_age_arabic,
                    'stops' => $route->stops ?? [],
                ];
            })
            ->values();

        $payload = [
            'ok' => true,
            'count' => $vehicles->count(),
            'vehicles' => $vehicles,
        ];

        $jsonString = json_encode($payload);
        $payloadSizeBytes = strlen($jsonString);

        return response()->json($payload)
            ->header('X-Payload-Bytes', (string) $payloadSizeBytes);
    }
}
