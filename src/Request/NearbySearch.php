<?php

declare(strict_types=1);

namespace Survos\Mapillary\Request;

/** Mapillary's radius endpoint ranks by proximity, recency and preference for panoramas. */
final readonly class NearbySearch
{
    public function __construct(public float $latitude, public float $longitude, public int $radius = 50, public int $limit = 20)
    {
        if (!is_finite($latitude) || !is_finite($longitude) || abs($latitude) > 90 || abs($longitude) > 180) {
            throw new \InvalidArgumentException('Nearby coordinates must be finite and within geographic range.');
        }
        if ($radius < 1 || $radius > 50 || $limit < 1 || $limit > 100) {
            throw new \InvalidArgumentException('Nearby radius must be 1–50 meters and limit 1–100.');
        }
    }

    /** @return array<string, float|int> */
    public function toQuery(): array
    {
        return ['lat' => $this->latitude, 'lng' => $this->longitude, 'radius' => $this->radius, 'limit' => $this->limit];
    }
}
