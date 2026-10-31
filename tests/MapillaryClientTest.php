<?php

declare(strict_types=1);

namespace Survos\Mapillary\Tests;

use PHPUnit\Framework\TestCase;
use Survos\Mapillary\Exception\MapillaryException;
use Survos\Mapillary\MapillaryClient;
use Survos\Mapillary\Model\Creator;
use Survos\Mapillary\Model\Point;
use Survos\Mapillary\Request\BoundingBox;
use Survos\Mapillary\Request\ImageSearch;
use Survos\Mapillary\SerializerFactory;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class MapillaryClientTest extends TestCase
{
    private const TOKEN = 'MLY|test-only';

    /** @return array<string, mixed> */
    private static function image(string $id = '123'): array
    {
        return [
            'id' => $id, 'captured_at' => 1700000000123, 'is_pano' => true,
            'geometry' => ['type' => 'Point', 'coordinates' => [19.05, 47.50]],
            'creator' => ['id' => '99', 'username' => 'photographer'],
            'width' => 7680, 'height' => 3840, 'camera_type' => 'equirectangular',
            'thumb_original_url' => 'https://cdn.example/image.jpg?temporary=signature',
            'quality_score' => null, 'future_field' => 'preserved',
        ];
    }

    public function testTypedSearchAndAuthenticationOnCustomBaseUri(): void
    {
        $http = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            self::assertSame('GET', $method);
            self::assertStringStartsWith('https://example.test/prefix/images?', $url);
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
            self::assertSame('19.035,47.49,19.065,47.51', $query['bbox']);
            self::assertSame('true', $query['is_pano']);
            self::assertSame('2025-01-01T00:00:00Z', $query['start_captured_at']);
            self::assertArrayNotHasKey('access_token', $query);
            self::assertContains('Authorization: OAuth '.self::TOKEN, $options['headers']);
            self::assertSame(0, $options['max_redirects']);

            return new MockResponse(json_encode(['data' => [self::image()]], JSON_THROW_ON_ERROR));
        });
        $client = new MapillaryClient($http, SerializerFactory::create(), self::TOKEN, 'https://example.test/prefix/');
        $page = $client->searchImages(new ImageSearch(new BoundingBox(19.035, 47.49, 19.065, 47.51), limit: 1, startCapturedAt: new \DateTimeImmutable('2025-01-01T01:00:00+01:00')));
        self::assertTrue($page->possiblyTruncated);
        self::assertNull($page->after);
        $image = $page->images[0];
        self::assertInstanceOf(Point::class, $image->geometry);
        self::assertSame(19.05, $image->geometry->longitude());
        self::assertInstanceOf(Creator::class, $image->creator);
        self::assertSame('photographer', $image->creator->username);
        self::assertNull($image->qualityScore);
        self::assertNull($image->computedGeometry);
        self::assertSame('preserved', $image->rawData['future_field']);
        self::assertSame('2023-11-14', $image->capturedDate()->format('Y-m-d'));
    }

    public function testCreatorPaginationRebuildsRequestWithoutFollowingNextUrl(): void
    {
        $calls = 0;
        $http = new MockHttpClient(function (string $method, string $url) use (&$calls): MockResponse {
            ++$calls;
            self::assertStringStartsWith(MapillaryClient::DEFAULT_BASE_URI.'images?', $url);
            self::assertStringNotContainsString('stolen', $url);
            if (1 === $calls) {
                return new MockResponse(json_encode(['data' => [self::image('1')], 'paging' => ['cursors' => ['after' => 'cursor+2'], 'next' => 'https://evil.example/?access_token=stolen']], JSON_THROW_ON_ERROR));
            }
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
            self::assertSame('cursor+2', $query['after']);

            return new MockResponse(json_encode(['data' => [self::image('2')]], JSON_THROW_ON_ERROR));
        });
        $client = new MapillaryClient($http, SerializerFactory::create(), self::TOKEN);
        $images = iterator_to_array($client->iterateImages(new ImageSearch(creatorUsername: 'photographer')), false);
        self::assertSame(['1', '2'], array_column($images, 'id'));
        self::assertSame(2, $calls);
    }

    public function testApiErrorPreservesCodesWithoutCredentialLeak(): void
    {
        $http = new MockHttpClient(new MockResponse(json_encode(['error' => ['message' => 'bad '.self::TOKEN, 'code' => 4, 'error_subcode' => 1349210, 'fbtrace_id' => 'trace']], JSON_THROW_ON_ERROR), ['http_code' => 429, 'response_headers' => ['retry-after: 60']]));
        try {
            (new MapillaryClient($http, SerializerFactory::create(), self::TOKEN))->getImage('123');
            self::fail('Expected API failure.');
        } catch (MapillaryException $error) {
            self::assertSame(429, $error->statusCode);
            self::assertSame(4, $error->apiCode);
            self::assertSame(1349210, $error->apiSubcode);
            self::assertSame('60', $error->retryAfter);
            self::assertStringNotContainsString(self::TOKEN, $error->getMessage());
            self::assertNull($error->getPrevious());
        }
    }

    public function testSequenceIdsAreTypedAndFieldsAreNotSent(): void
    {
        $http = new MockHttpClient(function (string $method, string $url): MockResponse {
            self::assertStringContainsString('image_ids?sequence_id=seq', $url);
            self::assertStringNotContainsString('fields=', $url);

            return new MockResponse('{"data":[{"id":"1"},{"id":"2"}]}');
        });
        self::assertSame(['1', '2'], (new MapillaryClient($http, SerializerFactory::create(), self::TOKEN))->sequenceImageIds('seq'));
    }

    public function testMissingRequiredImageFieldFails(): void
    {
        $image = self::image();
        unset($image['is_pano']);
        $client = new MapillaryClient(new MockHttpClient(new MockResponse(json_encode($image, JSON_THROW_ON_ERROR))), SerializerFactory::create(), self::TOKEN);
        $this->expectException(MapillaryException::class);
        $client->getImage('123');
    }

    public function testUnknownCameraTypeIsPreserved(): void
    {
        $image = self::image();
        $image['camera_type'] = 'future-projection';
        $client = new MapillaryClient(new MockHttpClient(new MockResponse(json_encode($image, JSON_THROW_ON_ERROR))), SerializerFactory::create(), self::TOKEN);
        self::assertSame('future-projection', $client->getImage('123')->cameraType);
    }

    public function testMalformedJsonIsSanitized(): void
    {
        $client = new MapillaryClient(new MockHttpClient(new MockResponse('not json '.self::TOKEN)), SerializerFactory::create(), self::TOKEN);
        $this->expectException(MapillaryException::class);
        $this->expectExceptionMessage('invalid JSON');
        $client->getImage('123');
    }

    public function testLargeBoundingBoxIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new BoundingBox(19.0, 47.0, 20.0, 48.0);
    }

    public function testBoundingBoxCannotUseCursor(): void
    {
        $client = new MapillaryClient(new MockHttpClient(), SerializerFactory::create(), self::TOKEN);
        $this->expectException(\InvalidArgumentException::class);
        $client->searchImages(new ImageSearch(new BoundingBox(19.03, 47.49, 19.06, 47.51)), 'cursor');
    }
}
