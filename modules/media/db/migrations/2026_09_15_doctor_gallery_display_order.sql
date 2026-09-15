ALTER TABLE media_assets
    ADD COLUMN display_order INT UNSIGNED NULL AFTER alt_text,
    ADD KEY idx_media_assets_gallery_order (owner_type, owner_id, purpose, status, display_order, created_at);

UPDATE media_assets AS asset
JOIN (
    SELECT media_id,
           ROW_NUMBER() OVER (
               PARTITION BY owner_type, owner_id, purpose
               ORDER BY created_at ASC, media_id ASC
           ) AS canonical_order
      FROM media_assets
     WHERE owner_type = 'PHYSICIAN'
       AND purpose = 'DOCTOR_GALLERY'
       AND classification = 'PUBLIC'
       AND status = 'READY'
) AS ordered ON ordered.media_id = asset.media_id
SET asset.display_order = ordered.canonical_order;

ALTER TABLE media_assets
    ADD CONSTRAINT chk_media_assets_display_order
    CHECK (display_order IS NULL OR display_order > 0);
