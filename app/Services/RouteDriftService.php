<?php

namespace App\Services;

use App\Models\DriverRoute;

class RouteDriftService
{
    /**
     * Buffer distance in meters beyond which a vehicle is considered drifting off-corridor.
     */
    protected const DRIFT_BUFFER_METERS = 1000.0;

    /**
     * Check if a route's vehicle has drifted from its designated corridor stops.
     *
     * @return array{drifting: bool, distance_meters: float, alert?: string}
     */
    public function checkDrift(DriverRoute $route): array
    {
        // Only evaluate drift if the trip is actively running and GPS is recorded
        if (! $route->is_trip_active || ! $route->last_latitude || ! $route->last_longitude) {
            return [
                'drifting' => false,
                'distance_meters' => 0.0,
            ];
        }

        $stops = $route->stops ?? [];
        if (empty($stops)) {
            return [
                'drifting' => false,
                'distance_meters' => 0.0,
            ];
        }

        $minDistance = PHP_FLOAT_MAX;
        $vLat = (float) $route->last_latitude;
        $vLng = (float) $route->last_longitude;

        foreach ($stops as $stop) {
            $sLat = (float) ($stop['lat'] ?? 0);
            $sLng = (float) ($stop['lng'] ?? 0);
            if ($sLat && $sLng) {
                $dist = $this->haversineDistance($vLat, $vLng, $sLat, $sLng);
                if ($dist < $minDistance) {
                    $minDistance = $dist;
                }
            }
        }

        $drifting = $minDistance > self::DRIFT_BUFFER_METERS;

        $result = [
            'drifting' => $drifting,
            'distance_meters' => round($minDistance, 1),
        ];

        if ($drifting) {
            $result['alert'] = 'المركبة غيرت مسارها حالياً — اضغط للاتصال بالسائق';
        }

        return $result;
    }

    /**
     * Calculate spherical distance in meters between two coordinates.
     */
    protected function haversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000; // meters

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
