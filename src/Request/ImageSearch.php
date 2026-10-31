<?php

declare(strict_types=1);

namespace Survos\Mapillary\Request;

final readonly class ImageSearch
{
    public function __construct(
        public ?BoundingBox $bbox = null,
        public ?string $creatorUsername = null,
        public ?bool $isPano = true,
        public int $limit = 20,
        public ?\DateTimeImmutable $startCapturedAt = null,
        public ?\DateTimeImmutable $endCapturedAt = null,
    ) {
        if (null !== $creatorUsername && '' === trim($creatorUsername)) {
            throw new \InvalidArgumentException('Creator username must not be blank.');
        }
        if (null === $bbox && null === $creatorUsername) {
            throw new \InvalidArgumentException('Image search requires a bounding box or creator username.');
        }
        if ($limit < 1 || $limit > 2000) {
            throw new \InvalidArgumentException('Search limit must be between 1 and 2000.');
        }
        if (null !== $startCapturedAt && null !== $endCapturedAt && $startCapturedAt > $endCapturedAt) {
            throw new \InvalidArgumentException('Capture start must be before capture end.');
        }
    }

    /** @return array<string, int|string> */
    public function toQuery(): array
    {
        $query = ['limit' => $this->limit];
        if (null !== $this->bbox) {
            $query['bbox'] = $this->bbox->toQuery();
        }
        if (null !== $this->creatorUsername) {
            $query['creator_username'] = $this->creatorUsername;
        }
        if (null !== $this->isPano) {
            $query['is_pano'] = $this->isPano ? 'true' : 'false';
        }
        foreach (['start_captured_at' => $this->startCapturedAt, 'end_captured_at' => $this->endCapturedAt] as $key => $date) {
            if (null !== $date) {
                $query[$key] = $date->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
            }
        }

        return $query;
    }

    public function supportsPagination(): bool
    {
        return null !== $this->creatorUsername && null === $this->bbox;
    }
}
