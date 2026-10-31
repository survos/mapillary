<?php

declare(strict_types=1);

namespace Survos\Mapillary\Model;

final readonly class Point
{
    /** @var list<float|int> */
    public array $coordinates;

    /** @param list<mixed> $coordinates Raw GeoJSON coordinates, validated below. */
    public function __construct(public string $type, array $coordinates)
    {
        if ('Point' !== $type || !array_is_list($coordinates) || count($coordinates) < 2 || count($coordinates) > 3) {
            throw new \InvalidArgumentException('Expected a GeoJSON Point with two or three coordinates.');
        }
        foreach ($coordinates as $coordinate) {
            if ((!is_int($coordinate) && !is_float($coordinate)) || !is_finite((float) $coordinate)) {
                throw new \InvalidArgumentException('Point coordinates must be finite numbers.');
            }
        }
        if (abs($coordinates[0]) > 180 || abs($coordinates[1]) > 90) {
            throw new \InvalidArgumentException('Point coordinates are outside geographic range.');
        }
        $this->coordinates = $coordinates;
    }

    public function longitude(): float
    {
        return (float) $this->coordinates[0];
    }

    public function latitude(): float
    {
        return (float) $this->coordinates[1];
    }
}
