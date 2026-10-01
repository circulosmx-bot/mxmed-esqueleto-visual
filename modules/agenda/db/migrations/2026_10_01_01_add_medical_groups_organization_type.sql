-- PROV03A: preserve every group_id and all existing group relationships.
-- The nullable intermediate column permits an explicit historical backfill;
-- new writes must always specify a recognized organization type.
ALTER TABLE medical_groups
  ADD COLUMN organization_type_key VARCHAR(48) NULL AFTER group_id;

UPDATE medical_groups
   SET organization_type_key = 'MEDICAL_GROUP'
 WHERE organization_type_key IS NULL;

ALTER TABLE medical_groups
  MODIFY COLUMN organization_type_key VARCHAR(48) NOT NULL,
  ADD CONSTRAINT ck_medical_groups_organization_type
    CHECK (organization_type_key IN (
      'MEDICAL_GROUP', 'LABORATORY', 'DIAGNOSTIC_CENTER', 'CLINIC',
      'HOSPITAL', 'DENTAL_ORGANIZATION', 'OTHER_HEALTHCARE_ORGANIZATION'
    )),
  ADD KEY idx_medical_groups_type_status (organization_type_key, status);
