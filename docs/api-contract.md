# Mapillary image API contract — supported subset

Source: https://www.mapillary.com/developer/api-documentation/
Retrieved: 2026-10-31 from the live rendered official documentation (changelog through June 26, 2026).
This is a contract summary, not an OpenAPI schema or a complete SDK.

- Entity origin: `https://graph.mapillary.com`; use `Authorization: OAuth <access token>`.
- `GET /images`: selected fields and either `bbox=west,south,east,north` or `creator_username`.
- Bounding-box area must be **strictly less than 0.01 square degrees**. Bbox searches do not support cursor pagination. Vector tiles are recommended at city scale; not implemented here.
- `is_pano=true|false` requires bbox or creator_username. Limit 1–2000 (server default 2000).
- Capture filters use ISO 8601; response `captured_at` is integer Unix milliseconds.
- Creator searches support `paging.cursors.after` with `paging.next`. Rebuild requests with the cursor, never reuse the credential-bearing next URL.
- `GET /{image_id}` returns image metadata. `GET /image_ids?sequence_id=...` returns a data collection of string IDs, ordered by capture time; it ignores fields.
- Geometry is GeoJSON Point; creator contains string ID and username. Camera types include perspective, fisheye, equirectangular and spherical. Preserve future strings.
- Width/height describe the original upload. Thumbnail URLs, including original-width rendition, are temporary, not durable identity. Quality score may be null; some fields may be absent.
- Rate limits: entity 60,000/min/app; search 10,000/min/app; tiles 50,000/day. Limits are not throughput targets. Rate error uses code 4 and can include error_subcode, fbtrace_id and is_transient.
- Unsupported: OAuth exchange/refresh, uploads, image byte downloads, radius search, vector-tile decoding, detection/map-feature queries, and city-scale inventory.

Terms: https://www.mapillary.com/terms (effective February 15, 2024).
User imagery is generally CC BY-SA 4.0 unless stated otherwise. Preserve source and photographer; self-hosted imagery also needs Mapillary logo/link attribution. API access is separate from content licensing.
