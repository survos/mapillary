<?php

declare(strict_types=1);

namespace Survos\Mapillary\Exception;

final class MapillaryException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $statusCode = null,
        public readonly ?int $apiCode = null,
        public readonly ?int $apiSubcode = null,
        public readonly ?string $traceId = null,
        public readonly ?string $retryAfter = null,
    ) {
        // No previous HTTP exception: its URL/trace can expose credentials.
        parent::__construct($message);
    }
}
