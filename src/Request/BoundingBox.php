<?php

declare(strict_types=1);

namespace Survos\Mapillary\Request;

final readonly class BoundingBox
{
    public function __construct(public float $west, public float $south, public float $east, public float $north)
    {
        foreach ([$west, $south, $east, $north] as $coordinate) {
            if (!is_finite($coordinate)) {
                throw new \InvalidArgumentException('Bounding box coordinates must be finite.');
            }
        }
        if ($west < -180 || $east > 180 || $south < -90 || $north > 90 || $west >= $east || $south >= $north) {
            throw new \InvalidArgumentException('Expected west,south,east,north in geographic order and range.');
        }
        if (($east - $west) * ($north - $south) >= 0.01) {
            throw new \InvalidArgumentException('Mapillary entity searches require a bounding box smaller than 0.01 square degrees; use coverage tiles for larger areas.');
        }
    }

    public static function fromString(string $value): self
    {
        $parts = explode(',', $value);
        if (4 !== count($parts) || array_filter($parts, static fn (string $part): bool => !is_numeric(trim($part)))) {
            throw new \InvalidArgumentException('Expected four numeric coordinates: west,south,east,north.');
        }

        return new self(...array_map(floatval(...), $parts));
    }

    public function toQuery(): string
    {
        return implode(',', [$this->west, $this->south, $this->east, $this->north]);
    }
}
