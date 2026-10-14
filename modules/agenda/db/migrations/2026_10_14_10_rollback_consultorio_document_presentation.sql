-- DOC-REC-STD03A recovery rollback.
-- Export configured values before rollback if the application has started using them.

ALTER TABLE `consultorios`
  DROP INDEX `idx_consultorios_document_footer`,
  DROP INDEX `uq_consultorios_document_order`,
  DROP INDEX `uq_consultorios_document_primary`,
  DROP CONSTRAINT `chk_consultorios_document_display_order`,
  DROP CONSTRAINT `chk_consultorios_document_publication`,
  DROP CONSTRAINT `chk_consultorios_document_is_primary`,
  DROP COLUMN `document_primary_slot`,
  DROP COLUMN `document_publication_enabled`,
  DROP COLUMN `document_display_order`,
  DROP COLUMN `document_is_primary`;

