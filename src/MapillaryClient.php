<?php

declare(strict_types=1);

namespace Survos\Mapillary;

use Survos\Mapillary\Exception\MapillaryException;
use Survos\Mapillary\Model\Image;
use Survos\Mapillary\Model\ImagePage;
use Survos\Mapillary\Request\ImageSearch;
use Survos\Mapillary\Request\NearbySearch;
use Symfony\Component\Serializer\Encoder\DecoderInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class MapillaryClient implements MapillaryClientInterface
{
    public const DEFAULT_BASE_URI = 'https://graph.mapillary.com/';
    public const IMAGE_FIELDS = 'id,captured_at,is_pano,geometry,computed_geometry,creator,width,height,camera_type,sequence,quality_score,thumb_2048_url,thumb_original_url';
    private HttpClientInterface $httpClient;

    public function __construct(
        HttpClientInterface $httpClient,
        private SerializerInterface&DenormalizerInterface&DecoderInterface $serializer,
        #[\SensitiveParameter] private string $accessToken,
        string $baseUri = self::DEFAULT_BASE_URI,
    ) {
        $parts = parse_url($baseUri);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host']) || !in_array($parts['scheme'], ['http', 'https'], true) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new \InvalidArgumentException('Base URI must be absolute HTTP(S), without credentials, query or fragment.');
        }
        if ('' === trim($accessToken) || preg_match('/\s/', $accessToken)) {
            throw new \InvalidArgumentException('Access token must be nonempty and contain no whitespace.');
        }
        $this->httpClient = $httpClient->withOptions([
            'base_uri' => rtrim($baseUri, '/').'/',
            'headers' => ['Authorization' => 'OAuth '.$accessToken, 'Accept' => 'application/json'],
            'max_redirects' => 0,
            'timeout' => 30,
        ]);
    }

    public function searchImages(ImageSearch $search, ?string $after = null): ImagePage
    {
        if (null !== $after && !$search->supportsPagination()) {
            throw new \InvalidArgumentException('Cursor pagination is supported only for creator-only searches.');
        }
        $query = $search->toQuery() + ['fields' => self::IMAGE_FIELDS];
        if (null !== $after) {
            $query['after'] = $after;
        }
        $payload = $this->request('images', $query);
        $images = array_map($this->image(...), $this->collection($payload));
        // Never follow paging.next: it can carry access_token and an arbitrary origin.
        $cursor = $payload['paging']['cursors']['after'] ?? null;
        $hasNext = isset($payload['paging']['next']);
        if (null !== $cursor && !is_string($cursor)) {
            throw new MapillaryException('Mapillary returned an invalid pagination cursor.');
        }
        if ($hasNext && null === $cursor && $search->supportsPagination()) {
            throw new MapillaryException('Mapillary pagination is missing its next cursor.');
        }

        return new ImagePage($images, $search->supportsPagination() && $hasNext ? $cursor : null, !$search->supportsPagination() && count($images) >= $search->limit);
    }

    public function iterateImages(ImageSearch $search): \Generator
    {
        $after = null;
        $seen = [];
        do {
            $page = $this->searchImages($search, $after);
            yield from $page->images;
            $after = $page->after;
            if (null !== $after) {
                if (isset($seen[$after])) {
                    throw new MapillaryException('Mapillary repeated a pagination cursor.');
                }
                $seen[$after] = true;
            }
        } while (null !== $after);
    }

    public function nearbyImages(NearbySearch $search): ImagePage
    {
        $payload = $this->request('images', $search->toQuery() + ['fields' => self::IMAGE_FIELDS]);
        $images = array_map($this->image(...), $this->collection($payload));

        return new ImagePage($images, null, count($images) >= $search->limit);
    }

    public function getImage(string $id): Image
    {
        if (!ctype_digit($id)) {
            throw new \InvalidArgumentException('Image ID must contain digits only.');
        }

        return $this->image($this->request($id, ['fields' => self::IMAGE_FIELDS]));
    }

    public function sequenceImageIds(string $sequenceId): array
    {
        if ('' === trim($sequenceId)) {
            throw new \InvalidArgumentException('Sequence ID must not be empty.');
        }
        $ids = [];
        foreach ($this->collection($this->request('image_ids', ['sequence_id' => $sequenceId])) as $image) {
            if (!isset($image['id']) || !is_string($image['id'])) {
                throw new MapillaryException('Mapillary sequence contains an invalid image ID.');
            }
            $ids[] = $image['id'];
        }

        return $ids;
    }

    /** @param array<string, float|int|string> $query @return array<string, mixed> */
    private function request(string $path, array $query): array
    {
        try {
            $response = $this->httpClient->request('GET', $path, ['query' => $query]);
            $status = $response->getStatusCode();
            $body = $response->getContent(false);
            $headers = $response->getHeaders(false);
        } catch (TransportExceptionInterface) {
            throw new MapillaryException('Mapillary transport failed.');
        }
        try {
            $payload = $this->serializer->decode($body, 'json');
        } catch (\Symfony\Component\Serializer\Exception\ExceptionInterface) {
            throw new MapillaryException('Mapillary returned invalid JSON.', $status);
        }
        if (!is_array($payload)) {
            throw new MapillaryException('Mapillary response must be a JSON object.', $status);
        }
        if ($status < 200 || $status >= 300 || isset($payload['error'])) {
            $error = $payload['error'] ?? [];
            if (!is_array($error)) {
                $error = [];
            }
            // Fixed text avoids reflecting credentials or signed URLs from provider errors.
            throw new MapillaryException(sprintf('Mapillary request failed (HTTP %d, API code %s).', $status, isset($error['code']) ? (int) $error['code'] : 'unknown'), $status, isset($error['code']) ? (int) $error['code'] : null, isset($error['error_subcode']) ? (int) $error['error_subcode'] : null, isset($error['fbtrace_id']) ? (string) $error['fbtrace_id'] : null, $headers['retry-after'][0] ?? null);
        }

        return $payload;
    }

    /** @param array<string, mixed> $payload @return list<array<string, mixed>> */
    private function collection(array $payload): array
    {
        if (!isset($payload['data']) || !is_array($payload['data']) || !array_is_list($payload['data'])) {
            throw new MapillaryException('Mapillary response is missing a data collection.');
        }
        foreach ($payload['data'] as $item) {
            if (!is_array($item)) {
                throw new MapillaryException('Mapillary collection contains an invalid image.');
            }
        }

        return $payload['data'];
    }

    /** @param array<string, mixed> $payload */
    private function image(array $payload): Image
    {
        try {
            /** @var Image $image */
            $image = $this->serializer->denormalize($payload, Image::class, 'json', ['require_all_properties' => true]);
        } catch (\Symfony\Component\Serializer\Exception\ExceptionInterface|\InvalidArgumentException) {
            throw new MapillaryException('Mapillary image metadata does not match the expected schema.');
        }
        $image->rawData = $payload;

        return $image;
    }
}
