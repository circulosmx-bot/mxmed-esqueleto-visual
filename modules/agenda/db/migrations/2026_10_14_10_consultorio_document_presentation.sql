-- DOC-REC-STD03A: canonical per-consultorio presentation authority.
-- Existing rows remain private and unordered until the physician explicitly saves a decision.
-- This migration is additive; applying it to a non-disposable database requires Director approval.

ALTER TABLE `consultorios`
  ADD COLUMN `document_is_primary` TINYINT(1) NOT NULL DEFAULT 0
    AFTER `geocode_updated_at`,
  ADD COLUMN `document_display_order` SMALLINT UNSIGNED NULL DEFAULT NULL
    AFTER `document_is_primary`,
  ADD COLUMN `document_publication_enabled` TINYINT(1) NOT NULL DEFAULT 0
    AFTER `document_display_order`,
  ADD COLUMN `document_primary_slot` TINYINT
    GENERATED ALWAYS AS (
      CASE WHEN `document_is_primary` = 1 THEN 1 ELSE NULL END
    ) STORED
    AFTER `document_publication_enabled`,
  ADD CONSTRAINT `chk_consultorios_document_is_primary`
    CHECK (`document_is_primary` IN (0, 1)),
  ADD CONSTRAINT `chk_consultorios_document_publication`
    CHECK (`document_publication_enabled` IN (0, 1)),
  ADD CONSTRAINT `chk_consultorios_document_display_order`
    CHECK (`document_display_order` IS NULL OR `document_display_order` > 0),
  ADD UNIQUE KEY `uq_consultorios_document_primary`
    (`doctor_id`, `document_primary_slot`),
  ADD UNIQUE KEY `uq_consultorios_document_order`
    (`doctor_id`, `document_display_order`),
  ADD KEY `idx_consultorios_document_footer`
    (`doctor_id`, `document_publication_enabled`, `document_is_primary`, `document_display_order`);

