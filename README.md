# survos/mapillary

Typed, read-only Mapillary API v4 image client. PHP 8.5+, Symfony components 8.1+. No kernel or environment-variable reads in the library.

```sh
composer require survos/mapillary symfony/http-client
```

```php
use Survos\Mapillary\MapillaryClient;
use Survos\Mapillary\SerializerFactory;
use Survos\Mapillary\Request\BoundingBox;
use Survos\Mapillary\Request\ImageSearch;
use Symfony\Component\HttpClient\HttpClient;

$client = new MapillaryClient(HttpClient::create(), SerializerFactory::create(), $accessToken);
$page = $client->searchImages(new ImageSearch(
    bbox: new BoundingBox(19.035, 47.490, 19.065, 47.510),
    isPano: true,
    limit: 20,
));
foreach ($page->images as $image) {
    echo $image->sourceUrl(), "\n";
}
```

`getImage($id)` retrieves metadata; `sequenceImageIds($sequence)` returns ordered IDs. `iterateImages(new ImageSearch(creatorUsername: 'username'))` follows creator cursors. Bbox searches return one bounded sample; `possiblyTruncated` flags reaching the limit. Bbox area must be less than 0.01 square degrees; coverage-tile decoding for larger searches is outside this milestone.

Nested geometry and creator are typed. Optional image fields are nullable; missing required ID, capture time or panorama flag fail on schema mismatch. `Image::rawData` retains the original image object and unknown fields; it may contain signed CDN URLs, so avoid logging it. Use image IDs and source URLs for durable identity.

The standalone client adds no caching/retries: inject HttpClient decorators. The bundle configures bounded GET retries. No default cache avoids cross-account mixing and stale signed URLs. Exceptions preserve HTTP/API codes, trace ID and Retry-After; messages are sanitized and omit the underlying transport exception. Do not dump HTTP debug traces or client objects containing credentials.

See [the supported contract](docs/api-contract.md). This is a compatible subset, not a complete SDK. Image byte downloads, uploads, OAuth, map features/detections, radius searches and vector tiles are unsupported.

Respect per-image licensing, photographer credit, and Mapillary attribution. Public image reads use the access token; not the client secret.

Tests: `composer install`, then `vendor/bin/phpunit` (or use the mono root runner with `-c lib/mapillary/phpunit.xml.dist`). Tests run offline.
