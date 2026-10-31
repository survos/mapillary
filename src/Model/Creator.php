<?php

declare(strict_types=1);

namespace Survos\Mapillary\Model;

final readonly class Creator
{
    public function __construct(public string $id, public string $username)
    {
    }
}
