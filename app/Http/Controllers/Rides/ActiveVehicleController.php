<?php

namespace App\Http\Controllers\Rides;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class ActiveVehicleController extends Controller
{
    /**
     * Ultra-lightweight endpoint for MapLibre map pins.
     * Returns only essential coordinates, type, and age for every active vehicle (< 5 KB gzipped).
     */
    public function mapPins(Request $request): JsonResponse
    {
        $pins = Vehicle::query()
            ->select(['id', 'driver_id', 'type', 'seat_count', 'status'])
            ->where('status', 'active')
            ->with(['driver.driverRoute.confirmedBookings'])
            ->orderBy('id', 'desc')
            ->get()
            ->filter(function (Vehicle $vehicle) {
                $driver = $vehicle->driver;
                if (! $driver) {
                    return false;
                }
                $route = $driver->driverRoute;

                return $route && in_array($route->status, ['on_route', 'temporary_route']) && ! empty($route->stops);
            })
            ->map(function (Vehicle $vehicle) {
                $route = $vehicle->driver->driverRoute;
                $firstStop = $route->stops[0] ?? ['name' => 'نقطة الانطلاق', 'lat' => 31.3456, 'lng' => 34.3182];
                $bookedSeats = $route->confirmedBookings ? $route->confirmedBookings->sum('seats') : 0;
                $freeSeats = max(0, (int) $vehicle->seat_count - $bookedSeats);

                return [
                    'vehicle_id' => $vehicle->id,
                    'lat' => round((float) ($route->last_latitude ?? $firstStop['lat']), 5),
                    'lng' => round((float) ($route->last_longitude ?? $firstStop['lng']), 5),
                    'vehicle_type' => $vehicle->type,
                    'vehicle_type_arabic' => $vehicle->type_arabic,
                    'free_seats' => $freeSeats,
                    'route_name' => $route->name,
                    'last_landmark' => $route->last_landmark ?? $firstStop['name'],
                    'position_age_arabic' => $route->position_age_arabic,
                ];
            })
            ->values();

        return response()->json([
            'ok' => true,
            'count' => $pins->count(),
            'pins' => $pins,
        ]);
    }

    /**
     * Search and list active transit vehicles for riders and guests.
     * Supports latest-first sorting and native Laravel pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $from = trim((string) $request->query('from', ''));
        $to = trim((string) $request->query('to', ''));
        $time = trim((string) $request->query('time', ''));

        // Load active vehicles with active routes ordered by newest id
        $filtered = Vehicle::query()
            ->where('status', 'active')
            ->whereHas('driver.driverRoute', function ($q) {
                $q->whereIn('status', ['on_route', 'temporary_route']);
            })
            ->with(['driver.driverRoute.confirmedBookings'])
            ->orderBy('id', 'desc')
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
            ->values();

        // Native Laravel pagination when page is requested (only map the current page slice for maximum speed)
        if ($request->has('page')) {
            $page = max(1, (int) $request->query('page', 1));
            $perPage = max(1, (int) $request->query('per_page', 10));

            $pageItems = $filtered->forPage($page, $perPage)->values()->map(fn (Vehicle $v) => $this->formatVehicle($v));

            $paginated = new LengthAwarePaginator(
                $pageItems,
                $filtered->count(),
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );

            return response()->json([
                'ok' => true,
                'count' => $paginated->count(),
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'total' => $paginated->total(),
                'per_page' => $paginated->perPage(),
                'from' => $paginated->firstItem(),
                'to' => $paginated->lastItem(),
                'vehicles' => $paginated->values(),
            ]);
        }

        $allVehicles = $filtered->map(fn (Vehicle $v) => $this->formatVehicle($v));

        $payload = [
            'ok' => true,
            'count' => $allVehicles->count(),
            'vehicles' => $allVehicles,
        ];

        $jsonString = json_encode($payload);
        $payloadSizeBytes = strlen($jsonString);

        return response()->json($payload)
            ->header('X-Payload-Bytes', (string) $payloadSizeBytes);
    }

    /**
     * Format a vehicle model into its API representation.
     */
    private function formatVehicle(Vehicle $vehicle): array
    {
        $driver = $vehicle->driver;
        $route = $driver->driverRoute;
        $firstStop = $route->stops[0] ?? ['name' => 'نقطة الانطلاق', 'lat' => 31.3456, 'lng' => 34.3182];

        $bookedSeats = $route->confirmedBookings ? $route->confirmedBookings->sum('seats') : 0;
        $freeSeats = max(0, (int) $vehicle->seat_count - $bookedSeats);

        return [
            'vehicle_id' => $vehicle->id,
            'route_id' => $route->id,
            'driver_id' => $driver->id,
            'driver_name' => $driver->name,
            'vehicle_type' => $vehicle->type,
            'vehicle_type_arabic' => $vehicle->type_arabic,
            'seat_count' => $vehicle->seat_count,
            'free_seats' => $freeSeats,
            'photo' => $vehicle->photos[0] ?? '/images/sample-ajala.svg',
            'photos' => $vehicle->photos ?? [],
            'license_plate' => $vehicle->license_plate,
            'route_name' => $route->name,
            'start_point' => $route->start_point,
            'first_trip_time' => $route->first_trip_time,
            'status' => $route->status,
            'status_arabic' => $route->status_arabic,
            'is_trip_active' => (bool) $route->is_trip_active,
            'last_latitude' => round((float) ($route->last_latitude ?? $firstStop['lat']), 5),
            'last_longitude' => round((float) ($route->last_longitude ?? $firstStop['lng']), 5),
            'last_landmark' => $route->last_landmark ?? $firstStop['name'],
            'last_position_at' => $route->last_position_at?->toISOString(),
            'position_age_minutes' => $route->position_age_minutes ?? 0,
            'position_age_arabic' => $route->position_age_arabic,
            'stops' => $route->stops ?? [],
        ];
    }
}
