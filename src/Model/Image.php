<?php

declare(strict_types=1);

namespace Survos\Mapillary\Model;

use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Serializer\Attribute\SerializedName;

final class Image
{
    /** Original image object and unmodeled fields. May contain temporary CDN URLs. @var array<string, mixed> */
    #[Ignore]
    public array $rawData = [];

    public function __construct(
        public readonly string $id,
        #[SerializedName('captured_at')] public readonly int $capturedAt,
        #[SerializedName('is_pano')] public readonly bool $isPano,
        public readonly ?Point $geometry = null,
        #[SerializedName('computed_geometry')] public readonly ?Point $computedGeometry = null,
        public readonly ?Creator $creator = null,
        public readonly ?int $width = null,
        public readonly ?int $height = null,
        #[SerializedName('camera_type')] public readonly ?string $cameraType = null,
        public readonly ?string $sequence = null,
        #[SerializedName('quality_score')] public readonly ?float $qualityScore = null,
        #[SerializedName('thumb_2048_url')] public readonly ?string $thumb2048Url = null,
        #[SerializedName('thumb_original_url')] public readonly ?string $thumbOriginalUrl = null,
    ) {
    }

    public function capturedDate(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('@'.intdiv($this->capturedAt, 1000));
    }

    public function sourceUrl(): string
    {
        return 'https://www.mapillary.com/app/?pKey='.rawurlencode($this->id);
    }
}
