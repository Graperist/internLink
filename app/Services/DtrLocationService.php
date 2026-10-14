<?php

namespace App\Services;

use App\Models\Hte;
use Carbon\Carbon;

class DtrLocationService
{
    /**
     * Evaluate a device location against the HTE's server-side geofence.
     * The server calculates the distance; client-provided distance is never trusted.
     */
    public function evaluate(Hte $hte, float $latitude, float $longitude, float $accuracy, ?int $capturedAtMs = null): array
    {
        $flags = [];

        if ($hte->latitude === null || $hte->longitude === null) {
            return [
                'status' => 'unconfigured',
                'distance_m' => null,
                'flags' => ['hte_location_unconfigured'],
                'captured_at' => $this->capturedAt($capturedAtMs),
            ];
        }

        $distance = $this->distanceMeters(
            (float) $hte->latitude,
            (float) $hte->longitude,
            $latitude,
            $longitude
        );

        $capturedAt = $this->capturedAt($capturedAtMs);

        // A low-accuracy reading is a REVIEW condition, not an automatic
        // rejection, as long as the captured point is still inside the HTE
        // geofence. The HTE supervisor can review and verify the attendance.
        if ($accuracy > (float) ($hte->max_gps_accuracy_m ?? 75)) {
            $flags[] = 'low_accuracy';
        }

        if ($capturedAtMs !== null) {
            if ($capturedAt === null) {
                $flags[] = 'invalid_location_timestamp';
            } else {
                $seconds = abs(now()->diffInSeconds($capturedAt, false));
                if ($seconds > 120) {
                    $flags[] = 'stale_location_timestamp';
                }
            }
        }

        if ($distance > (int) ($hte->geofence_radius_m ?? 100)) {
            $flags[] = 'outside_geofence';
        }

        return [
            'status' => empty($flags) ? 'verified' : 'review',
            'distance_m' => round($distance, 2),
            'flags' => $flags,
            'captured_at' => $capturedAt,
        ];
    }

    public function distanceMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000;
        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);
        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLon = deg2rad($lon2 - $lon1);

        $a = sin($deltaLat / 2) ** 2
            + cos($lat1Rad) * cos($lat2Rad) * sin($deltaLon / 2) ** 2;

        return $earthRadius * (2 * atan2(sqrt($a), sqrt(1 - $a)));
    }

    private function capturedAt(?int $capturedAtMs): ?Carbon
    {
        if ($capturedAtMs === null || $capturedAtMs <= 0) {
            return null;
        }

        try {
            return Carbon::createFromTimestampMs($capturedAtMs);
        } catch (\Throwable) {
            return null;
        }
    }
}
