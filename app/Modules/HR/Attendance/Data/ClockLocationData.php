<?php

declare(strict_types=1);

namespace App\Modules\HR\Attendance\Data;

/**
 * Where the phone was when its owner clocked in or out (degrees, WGS 84) and how precise the reading was (metres).
 * Used only for the geofence check; it is not stored.
 */
final readonly class ClockLocationData
{
    private const EARTH_RADIUS_METERS = 6_371_000;

    public function __construct(
        public float $latitude,
        public float $longitude,
        public ?float $accuracy = null,
    ) {}

    /** Great-circle (haversine) distance to a point, in metres. */
    public function distanceTo(float $latitude, float $longitude): float
    {
        $dLat = deg2rad($latitude - $this->latitude);
        $dLng = deg2rad($longitude - $this->longitude);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($this->latitude)) * cos(deg2rad($latitude)) * sin($dLng / 2) ** 2;

        return 2 * self::EARTH_RADIUS_METERS * asin(min(1.0, sqrt($a)));
    }
}
