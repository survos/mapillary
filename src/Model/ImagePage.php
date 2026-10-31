<?php

declare(strict_types=1);

namespace Survos\Mapillary\Model;

final readonly class ImagePage
{
    /** @param list<Image> $images */
    public function __construct(public array $images, public ?string $after, public bool $possiblyTruncated)
    {
        foreach ($images as $image) {
            if (!$image instanceof Image) {
                throw new \InvalidArgumentException('ImagePage accepts Image objects only.');
            }
        }
    }
}
