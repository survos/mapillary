<?php

declare(strict_types=1);

namespace Survos\Mapillary;

use Survos\Mapillary\Model\Image;
use Survos\Mapillary\Model\ImagePage;
use Survos\Mapillary\Request\ImageSearch;

interface MapillaryClientInterface
{
    public function searchImages(ImageSearch $search, ?string $after = null): ImagePage;

    /** Creator-only cursor pagination; bbox queries return one bounded page. @return \Generator<int, Image> */
    public function iterateImages(ImageSearch $search): \Generator;

    public function getImage(string $id): Image;

    /** @return list<string> */
    public function sequenceImageIds(string $sequenceId): array;
}
