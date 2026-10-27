<?php

namespace App\Services;

use App\Models\DriverRoute;
use Illuminate\Support\Collection;

class TripPlannerService
{
    public function __construct(
        protected ?AiTransitAssistantService $aiAssistant = null
    ) {
        $this->aiAssistant = $aiAssistant ?? new AiTransitAssistantService;
    }

    /**
     * Plan a transit trip from origin to destination.
     * Supports direct corridor routes, 1-transfer connections, and "no route".
     *
     * @param  string  $from  Origin stop name
     * @param  string  $to  Destination stop name
     * @param  string|null  $preferredTime  Optional departure time (HH:MM)
     * @param  Collection|null  $networkRoutes  Optional fixed routes collection for deterministic unit testing
     * @return array<string, mixed>
     */
    public function plan(string $from, string $to, ?string $preferredTime = null, ?Collection $networkRoutes = null): array
    {
        $from = trim($from);
        $to = trim($to);

        if ($from === '' || $to === '' || $from === $to) {
            return [
                'ok' => true,
                'found' => false,
                'message' => 'يرجى اختيار نقطتي انطلاق ووصول مختلفتين',
                'itineraries' => [],
            ];
        }

        // Use supplied routes or query active database routes with their active vehicles
        $routes = $networkRoutes ?? DriverRoute::query()
            ->whereIn('status', ['on_route', 'temporary_route'])
            ->with(['driver.vehicle'])
            ->get();

        $directItineraries = $this->findDirectRoutes($routes, $from, $to, $preferredTime);
        $transferItineraries = $this->findTransferRoutes($routes, $from, $to, $preferredTime);

        $itineraries = array_merge($directItineraries, $transferItineraries);

        if (empty($itineraries)) {
            return [
                'ok' => true,
                'found' => false,
                'message' => 'لا يوجد مسار متاح بين هاتين النقطتين في شبكة المواصلات الحالية',
                'from' => $from,
                'to' => $to,
                'itineraries' => [],
            ];
        }

        // Sort: direct routes first, then by earliest departure time / lowest fare
        usort($itineraries, function ($a, $b) {
            if ($a['transfers'] !== $b['transfers']) {
                return $a['transfers'] <=> $b['transfers'];
            }

            return $a['total_fare_ils'] <=> $b['total_fare_ils'];
        });

        // Add optional AI advice to top itinerary if enabled
        $topItinerary = $itineraries[0];
        $aiAdvice = $this->aiAssistant->generateAdvice($topItinerary, $from, $to);

        return [
            'ok' => true,
            'found' => true,
            'from' => $from,
            'to' => $to,
            'count' => count($itineraries),
            'ai_advice' => $aiAdvice,
            'itineraries' => $itineraries,
        ];
    }

    /**
     * Find direct non-transfer routes between origin and destination.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function findDirectRoutes(Collection $routes, string $from, string $to, ?string $preferredTime): array
    {
        $results = [];

        foreach ($routes as $route) {
            $stops = $route->stops ?? [];
            if (count($stops) < 2) {
                continue;
            }

            $fromIndex = $this->findStopIndex($stops, $from);
            $toIndex = $this->findStopIndex($stops, $to);

            // Forward direction
            if ($fromIndex !== null && $toIndex !== null && $fromIndex < $toIndex) {
                $segmentStops = array_slice($stops, $fromIndex, ($toIndex - $fromIndex) + 1);
                $fare = $this->calculateSegmentFare($segmentStops);

                $results[] = [
                    'id' => 'direct_'.$route->id,
                    'type' => 'direct',
                    'transfers' => 0,
                    'total_fare_ils' => $fare,
                    'route_id' => $route->id,
                    'route_name' => $route->name,
                    'vehicle' => $this->formatVehicleData($route),
                    'departure_time' => $route->first_trip_time,
                    'from_stop' => $stops[$fromIndex]['name'],
                    'to_stop' => $stops[$toIndex]['name'],
                    'intermediate_stops_count' => count($segmentStops) - 2,
                    'stops' => $segmentStops,
                ];
            }
        }

        return $results;
    }

    /**
     * Find 2-vehicle / 1-transfer routes connecting origin and destination via a shared junction stop.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function findTransferRoutes(Collection $routes, string $from, string $to, ?string $preferredTime): array
    {
        $results = [];

        foreach ($routes as $route1) {
            $stops1 = $route1->stops ?? [];
            $fromIdx = $this->findStopIndex($stops1, $from);

            if ($fromIdx === null) {
                continue;
            }

            foreach ($routes as $route2) {
                if ($route1 === $route2 || ($route1->id && $route2->id && $route1->id === $route2->id)) {
                    continue;
                }

                $stops2 = $route2->stops ?? [];
                $toIdx = $this->findStopIndex($stops2, $to);

                if ($toIdx === null) {
                    continue;
                }

                // Find a common transfer stop T where T is after from in Route 1 and before to in Route 2
                for ($t1 = $fromIdx + 1; $t1 < count($stops1); $t1++) {
                    $transferCandidate = $stops1[$t1];
                    $t2 = $this->findStopIndex($stops2, $transferCandidate['name']);

                    if ($t2 !== null && $t2 < $toIdx) {
                        // Found a valid 1-transfer connection!
                        $leg1Stops = array_slice($stops1, $fromIdx, ($t1 - $fromIdx) + 1);
                        $leg2Stops = array_slice($stops2, $t2, ($toIdx - $t2) + 1);

                        $fare1 = $this->calculateSegmentFare($leg1Stops);
                        $fare2 = $this->calculateSegmentFare($leg2Stops);
                        $totalFare = $fare1 + $fare2;

                        // Calculate realistic transfer wait duration in minutes
                        $waitMinutes = $this->calculateTransferWait($route1->first_trip_time, $route2->first_trip_time);

                        $results[] = [
                            'id' => 'transfer_'.$route1->id.'_'.$route2->id.'_'.$t1,
                            'type' => 'one_transfer',
                            'transfers' => 1,
                            'total_fare_ils' => $totalFare,
                            'transfer_junction' => $transferCandidate['name'],
                            'transfer_wait_minutes' => $waitMinutes,
                            'transfer_wait_arabic' => "انتظار {$waitMinutes} دقيقة للتبديل",
                            'leg_1' => [
                                'route_id' => $route1->id,
                                'route_name' => $route1->name,
                                'vehicle' => $this->formatVehicleData($route1),
                                'departure_time' => $route1->first_trip_time,
                                'from' => $stops1[$fromIdx]['name'],
                                'to' => $transferCandidate['name'],
                                'fare_ils' => $fare1,
                                'stops' => $leg1Stops,
                            ],
                            'leg_2' => [
                                'route_id' => $route2->id,
                                'route_name' => $route2->name,
                                'vehicle' => $this->formatVehicleData($route2),
                                'departure_time' => $route2->first_trip_time,
                                'from' => $transferCandidate['name'],
                                'to' => $stops2[$toIdx]['name'],
                                'fare_ils' => $fare2,
                                'stops' => $leg2Stops,
                            ],
                        ];

                        // Keep best transfer junction for this route pair
                        break;
                    }
                }
            }
        }

        return $results;
    }

    /**
     * Find index of a stop by name or ID.
     */
    protected function findStopIndex(array $stops, string $nameOrId): ?int
    {
        $search = mb_strtolower(trim($nameOrId));

        foreach ($stops as $idx => $stop) {
            $stopName = mb_strtolower(trim($stop['name'] ?? ''));
            $stopId = mb_strtolower(trim($stop['id'] ?? ''));

            if ($stopName === $search || $stopId === $search || str_contains($stopName, $search) || str_contains($search, $stopName)) {
                return $idx;
            }
        }

        return null;
    }

    /**
     * Calculate leg fare between stops based on configured fare_ils.
     */
    protected function calculateSegmentFare(array $segmentStops): int
    {
        if (count($segmentStops) < 2) {
            return 0;
        }

        $lastStop = end($segmentStops);
        $firstStop = reset($segmentStops);

        $lastFare = (int) ($lastStop['fare_ils'] ?? 0);
        $firstFare = (int) ($firstStop['fare_ils'] ?? 0);

        $diff = abs($lastFare - $firstFare);

        return $diff > 0 ? $diff : max(5, $lastFare);
    }

    /**
     * Calculate minutes of wait time between two connecting vehicles.
     */
    protected function calculateTransferWait(string $time1, string $time2): int
    {
        $t1Minutes = $this->timeToMinutes($time1);
        $t2Minutes = $this->timeToMinutes($time2);

        $diff = $t2Minutes - $t1Minutes;

        // If transfer vehicle departs later in morning, wait difference; otherwise default realistic buffer 15-20 min
        if ($diff >= 10 && $diff <= 120) {
            return $diff;
        }

        return 15;
    }

    protected function timeToMinutes(string $timeStr): int
    {
        $parts = explode(':', $timeStr);
        if (count($parts) >= 2) {
            return ((int) $parts[0] * 60) + (int) $parts[1];
        }

        return 420; // 07:00 AM default
    }

    /**
     * Format minimal vehicle summary for the itinerary.
     */
    protected function formatVehicleData(DriverRoute $route): array
    {
        $vehicle = $route->driver?->vehicle;

        return [
            'type' => $vehicle?->type ?? 'ajala',
            'type_arabic' => $vehicle?->type_arabic ?? 'عجلة',
            'seat_count' => $vehicle?->seat_count ?? 10,
            'photo' => $vehicle?->photos[0] ?? '/images/sample-ajala.svg',
            'license_plate' => $vehicle?->license_plate ?? 'غزة',
            'driver_name' => $route->driver?->name ?? 'سائق مشوار',
            'is_trip_active' => (bool) $route->is_trip_active,
            'last_landmark' => $route->last_landmark ?? $route->start_point,
            'position_age_arabic' => $route->position_age_arabic,
        ];
    }
}
