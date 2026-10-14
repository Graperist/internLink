<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeocodingService
{
    /**
     * Resolves a free-text address into [latitude, longitude], or null if
     * it couldn't be resolved. Uses Nominatim (OpenStreetMap's free
     * geocoding API - no API key needed, unlike Google's), since this app
     * has no billing account set up for a paid geocoding service.
     *
     * IMPORTANT - this cannot be tested from the development sandbox this
     * was built in, since that environment's network access is
     * restricted to a small allowlist that does not include Nominatim.
     * This must be verified against the real address data once deployed
     * - if Philippine addresses don't resolve reliably (rural or very
     * new addresses are the likely failure case for a free geocoder),
     * that's a real limitation to know about, not a bug to chase blindly.
     *
     * Nominatim's usage policy requires a descriptive User-Agent and caps
     * requests to roughly one per second - both respected here. This is
     * only ever called when a coordinator adds or edits an HTE, which is
     * an infrequent admin action, not a per-request or per-user API call.
     */
    public static function geocode(string $address): ?array
    {
        if (blank($address)) {
            return null;
        }

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'PSU-InternLink/1.0 (internship management system; contact: coordinator@psu.edu.ph)',
            ])
                ->timeout(10)
                ->get('https://nominatim.openstreetmap.org/search', [
                    'q' => $address,
                    'format' => 'json',
                    'limit' => 1,
                ]);

            if (! $response->successful()) {
                Log::warning('Geocoding request failed', ['address' => $address, 'status' => $response->status()]);

                return null;
            }

            $results = $response->json();

            if (empty($results)) {
                Log::info('Geocoding found no match', ['address' => $address]);

                return null;
            }

            return [
                'latitude' => (float) $results[0]['lat'],
                'longitude' => (float) $results[0]['lon'],
            ];
        } catch (\Throwable $e) {
            Log::error('Geocoding request threw an exception', ['address' => $address, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Parses coordinates the coordinator pasted in directly - either a
     * plain "lat, lng" pair (what you get by right-clicking a spot on
     * Google Maps, which copies the coordinates straight to the
     * clipboard - the most reliable method, works even for addresses
     * that don't exist as a searchable place) or a full Google Maps URL
     * with the coordinates embedded in it (e.g., .../@16.123,120.456,17z
     * or ?q=16.123,120.456). Returns null if nothing recognizable is
     * found, rather than guessing.
     *
     * Deliberately does NOT handle shortened share links
     * (maps.app.goo.gl/...) - those don't contain visible coordinates at
     * all and would need a network request just to expand, which isn't
     * worth the complexity for what a coordinator can just as easily get
     * by copying the coordinates directly instead of the share link.
     */
    public static function parseManualCoordinates(string $input): ?array
    {
        $input = trim($input);

        if (blank($input)) {
            return null;
        }

        // Plain "lat, lng" or "lat,lng" pair
        if (preg_match('/^(-?\d+\.\d+)\s*,\s*(-?\d+\.\d+)$/', $input, $matches)) {
            return self::validRange((float) $matches[1], (float) $matches[2]);
        }

        // Coordinates embedded in a Google Maps URL, e.g. @16.123,120.456,17z or ?q=16.123,120.456
        if (preg_match('/[@?](?:q=)?(-?\d+\.\d+),(-?\d+\.\d+)/', $input, $matches)) {
            return self::validRange((float) $matches[1], (float) $matches[2]);
        }

        return null;
    }

    private static function validRange(float $lat, float $lng): ?array
    {
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return null;
        }

        return ['latitude' => $lat, 'longitude' => $lng];
    }
}
