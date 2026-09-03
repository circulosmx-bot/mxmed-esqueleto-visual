# MXMED media storage, image optimization, and semantic metadata

Status: accepted architecture contract

Scope: product media architecture; implementation is deferred

## Decision

Media optimization is a product requirement, not only a frontend performance
technique. It must control storage, delivery bandwidth, cost, privacy, safety,
accessibility, and public product quality.

The system may accept large source uploads, but ordinary public originals are
temporary processing inputs rather than permanent public assets. For example,
21 photographs of 22 MB each (about 462 MB) should be reduced to the smallest
set of product-appropriate derivatives, potentially only a few megabytes in
total.

`MEDIA_STORAGE_OPTIMIZATION_IS_PRODUCT_REQUIREMENT=true`

## Storage authority and media records

MariaDB/MySQL remains authoritative for structured product data and media
records: ownership, purpose, classification, object key, controlled semantic
metadata, format, dimensions, byte size, checksum, processing state, sort
order, lifecycle state, and timestamps.

Large binary assets must not normally be stored as BLOBs in relational profile
tables. Production binaries are intended for object storage, expected to be
Amazon S3 or the source-authoritative AWS object-storage boundary selected by a
later implementation slice.

`OBJECT_STORAGE_INTENDED_FOR_BINARY_MEDIA=true`

`DATABASE_INTENDED_FOR_MEDIA_REFERENCES_AND_METADATA=true`

## Required classification

Every media purpose must explicitly declare one classification. There is no
implicit public default.

| Classification | Typical content | Delivery expectation |
| --- | --- | --- |
| `PUBLIC` | Approved physician portraits, logos, facilities, consultorio photographs, galleries, and promotions | Optimized public derivatives only |
| `PRIVATE` | Unpublished drafts and non-public user material | Authenticated access |
| `INTERNAL` | Administrative, support, moderation, and review material | Authorized internal access |
| `SENSITIVE` | Signatures, identity or credential evidence, and patient/clinical documents | Separately governed private access |

Digitized signatures are never public by default.

`MEDIA_CLASSIFICATION_REQUIRED=true`

`DIGITIZED_SIGNATURE_PUBLIC_BY_DEFAULT=false`

## Public raster processing lifecycle

Ordinary `PUBLIC` raster media follows this lifecycle:

1. upload;
2. temporary quarantine;
3. validate;
4. safely decode;
5. read only required orientation metadata;
6. apply orientation to pixels;
7. strip source metadata;
8. normalize color and format;
9. resize to product dimensions;
10. generate only required optimized variants;
11. generate platform-controlled public semantic metadata;
12. verify outputs;
13. persist optimized media;
14. persist metadata and object references;
15. delete the temporary original.

The original user file must not be published directly.

`PUBLIC_SOURCE_FILE_DIRECT_PUBLISH=false`

## Original retention

After all required derivatives for an ordinary public profile or gallery image
are successfully generated and verified, the temporary original is deleted by
default. Retention requires an explicit media-purpose rule.

This default does not apply blindly to signatures, legal or identity evidence,
clinical material, or any purpose for which fidelity or legal requirements
demand the original. Those purposes require their own retention rules.

`PUBLIC_MEDIA_DEFAULT_LONG_TERM_ORIGINAL_RETENTION=false`

## Dimensions, variants, formats, and byte budgets

Optimization is dimension-first. The processor first reduces an image such as
8000 x 6000 to the largest dimensions the product actually uses, then searches
for the highest practical visual quality within the variant's byte budget. A
single encoder-quality percentage must not govern every image.

Initial architectural targets are:

| Variant | Approximate byte target |
| --- | ---: |
| Thumbnail | 15-60 KB |
| Card / avatar | 30-100 KB |
| Main profile display | 80-200 KB |
| Standard gallery | 150-300 KB |
| Large public web image | approximately 300 KB maximum |

These are variant-specific targets, not a universal `ALL_IMAGES_MAX_300_KB`
rule. A narrowly justified higher ceiling may be used only when demonstrated
visual requirements demand it.

Only variants used by real UI surfaces are generated. Thumbnail, card,
profile, and gallery-large are conceptual examples, not a mandatory four-copy
set. Near-identical copies are not retained without demonstrated need.

WebP is the preferred initial format for public photographs because it balances
compression, browser support, and processing cost. AVIF remains an evaluation
candidate. The platform must not automatically store JPEG, WebP, and AVIF for
every photograph. PNG remains suitable where transparency or graphic content
requires it. User-uploaded SVG must not be published without a dedicated
sanitization policy because it may contain active content.

`PUBLIC_MEDIA_DIMENSION_FIRST_OPTIMIZATION=true`

`PUBLIC_MEDIA_MAX_BYTES_BY_VARIANT=true`

`PUBLIC_LARGE_IMAGE_TARGET_MAX_APPROX_KB=300`

`MEDIA_VARIANT_COUNT_MINIMIZED_BY_USE=true`

## Source metadata and controlled public metadata

Metadata received in an uploaded file is untrusted. Processing may read only
what is necessary for safe transformation, especially orientation, and must
apply that orientation before stripping unnecessary metadata from published
derivatives.

The default strip set includes GPS coordinates, device model and serial data,
capture time unless explicitly required, editing software, camera/lens data,
unknown EXIF/IPTC/XMP, embedded thumbnails, and other source-private metadata.
User-provided EXIF/IPTC values must not be copied blindly.

After stripping, México Médico may create its own validated public semantic
metadata. The platform is authoritative for what is published.

`SOURCE_METADATA_STRIPPING_REQUIRED=true`

`PUBLIC_SOURCE_GPS_METADATA_PRESERVED=false`

`PLATFORM_CONTROLLED_PUBLIC_METADATA_ALLOWED=true`

## Public image semantics, accessibility, and SEO/AI

Public image meaning is conveyed in this priority order:

1. page and entity context;
2. structured data and canonical entity relationship;
3. meaningful alt text;
4. descriptive filename;
5. useful visible caption or contextual text;
6. primary-image signals such as `primaryImageOfPage` or `og:image`;
7. `ImageObject` linkage;
8. truthful, safe rights/authorship IPTC metadata;
9. original EXIF metadata, which is normally removed.

Embedded metadata is not the primary SEO strategy. All public signals must be
consistent with the same canonical entity facts expressed by the HTML page,
profile DTO, canonical URL, structured data, alt text, filename, caption, and
media record. Unsupported or contradictory claims are prohibited.

`PUBLIC_IMAGE_SEMANTIC_CONTEXT_PRIORITY=true`

`PUBLIC_ENTITY_SEMANTIC_CONSISTENCY_REQUIRED=true`

### Filenames

Optimized public objects may expose meaningful, deterministic names based on
verified public facts. For example,
`juan-perez-cardiologo-aguascalientes.webp` is preferable to `IMG_8382.JPG`.
Names must not keyword-stuff or manufacture claims such as "best" or "number
one."

`PUBLIC_MEDIA_FILENAME_DESCRIPTIVE_ALLOWED=true`

`PUBLIC_MEDIA_FILENAME_KEYWORD_STUFFING=false`

### Alt text and captions

Renderers must support platform-controlled alt text that describes the actual
image, serves accessibility, and identifies the relevant entity or context
when useful. Gallery assets must not automatically repeat identical alt text.
Examples include a physician portrait, a consultorio, and its reception area,
each described according to its real content.

Captions are optional. They are used only when they improve UX, accessibility,
or semantic clarity; visible captions are not forced beneath every image. The
page must still make the relationship among physician, specialty, location,
and image clear.

`PUBLIC_MEDIA_ALT_TEXT_REQUIRED_WHERE_MEANINGFUL=true`

`PUBLIC_MEDIA_ALT_DUPLICATE_SPAM=false`

`PUBLIC_MEDIA_CAPTION_CONTEXT_SUPPORTED=true`

### Structured entity and primary image

Public profile rendering must be able to relate a physician to an image and an
`ImageObject`, including supported content URL, caption, dimensions, and
representative/primary relationship. Each physician page must be able to name
one preferred primary image for consistent entity `image`, `ImageObject`,
`primaryImageOfPage`, and Open Graph signals. Actual markup is deferred and may
include only facts the system supports.

`PUBLIC_MEDIA_STRUCTURED_ENTITY_LINKAGE_REQUIRED=true`

`PUBLIC_PROFILE_PRIMARY_IMAGE_CONCEPT_REQUIRED=true`

### Embedded IPTC and watermarks

Embedded metadata in an optimized public derivative is optional and
whitelist-based. Candidate fields are Creator, Credit Line, Copyright Notice,
and rights/license URL. Values must be truthful: neither México Médico nor a
physician is declared creator or rights holder without authority. IPTC fields
must not be used for keyword stuffing.

Visible watermarks are not an SEO requirement and the platform must not burn a
physician name, specialty, or location into every image for search ranking.
Branding, attribution, or protection may be evaluated separately.

`PUBLIC_EMBEDDED_METADATA_WHITELIST_ONLY=true`

`PUBLIC_RIGHTS_METADATA_MUST_BE_TRUTHFUL=true`

`VISIBLE_WATERMARK_REQUIRED_FOR_SEO=false`

## Controlled semantic fields

Future media records should support validated, platform-managed fields such as
`alt_text`, `caption`, `public_title`, `semantic_subject`, `is_primary`, purpose,
and classification. These fields are data, not arbitrary unsanitized HTML.

`PUBLIC_SEMANTIC_METADATA_DATABASE_SUPPORT_EXPECTED=true`

## Input safety

Validation must inspect decoded content, not merely the filename extension.
Implementation must bound source bytes, MIME/type, decoded format, width,
height, pixel count, batch count, and decoding time/cost, while rejecting
corrupt media, pixel bombs, decompression bombs, and malformed images. Exact
limits require implementation and runtime evidence.

`PUBLIC_INPUT_PIXEL_BOMB_PROTECTION_REQUIRED=true`

## Processing efficiency and large batches

The implementation must evaluate libvips or an equivalent memory-efficient
processor before selecting GD, ImageMagick, or another technology. No processor
or runtime is selected by this ADR.

Large batches such as 21 files of 22 MB must not depend on one long synchronous
HTTP request for final processing. Processing must be bounded and independently
trackable per asset, allowing the UX to expose uploading, processing, ready,
and failed states. The queue or worker mechanism is deferred.

`MEMORY_EFFICIENT_IMAGE_PROCESSING_REQUIRED=true`

`LARGE_BATCH_SYNCHRONOUS_FINAL_PROCESSING_DISCOURAGED=true`

## Sensitive media policy

Sensitive and documentary media does not inherit public-image behavior. Its
separate policy must cover private object storage, authenticated authorization,
encryption, no anonymous URL, controlled temporary retrieval, conservative
transformation when fidelity matters, purpose-specific original retention, and
no public CDN exposure by default.

`SENSITIVE_MEDIA_SEPARATE_POLICY_REQUIRED=true`

## Public delivery direction

The preferred future delivery boundary is a private object origin behind a
controlled CDN or equivalent delivery layer:

`PRIVATE OBJECT ORIGIN -> CONTROLLED DELIVERY LAYER -> PUBLIC WEB`

The entire storage bucket must not be anonymously readable by default. This ADR
does not create S3 or CloudFront resources.

`PUBLIC_BUCKET_ANONYMOUS_READ_DEFAULT=false`

## Integrity and privacy-aware duplicate detection

Processing must produce a cryptographic checksum, preferably SHA-256 or an
equivalent, for integrity, output verification, and bounded duplicate
detection. Duplicate handling must respect profile, tenant/user ownership, and
privacy boundaries. Naive global cross-user deduplication is not the default.

`MEDIA_CHECKSUM_REQUIRED=true`

`GLOBAL_CROSS_USER_DEDUP_DEFAULT=false`

## Storage and delivery cost principle

The architecture minimizes long-term object bytes, CDN and browser transfer,
backup footprint, unnecessary variants, repeated processing, and originals
without product value. Illustratively, 10,000 physicians with 20 images at 250
KB is about 50 GB, while the same count at 22 MB is about 4.4 TB. This comparison
explains the architecture; it is not a production-volume claim.

`MEDIA_STORAGE_COST_AWARENESS_REQUIRED=true`

## Deferred implementation decisions

The media-upload implementation slice must decide, using implementation and
runtime evidence:

- exact S3 bucket topology;
- exact CloudFront topology;
- exact media database schema;
- exact upload-byte and megapixel limits;
- exact batch maximum;
- exact processing worker technology;
- exact libvips binding/runtime;
- WebP adaptive quality-search algorithm;
- AVIF adoption;
- exact CDN caching policy;
- signed/private URL implementation;
- sensitive-document retention rules;
- SVG sanitization implementation.

No upload processor, database migration, worker, bucket, CDN, or rendering
change is established by this document.

`IMPLEMENTATION_DEFERRED=true`
