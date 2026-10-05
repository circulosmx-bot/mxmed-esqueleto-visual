-- PROC-CAT02B: permit the approved additive category in the existing CHECK.
-- The VARCHAR(32) column is unchanged; existing category values remain valid.
SET @proc_cat02b_has_category := (SELECT COUNT(*) FROM information_schema.CHECK_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='ck_study_type_category'
    AND CHECK_CLAUSE LIKE '%PROCEDIMIENTOS_DIAGNOSTICOS%');
SET @proc_cat02b_sql := IF(@proc_cat02b_has_category=0,
  'ALTER TABLE clinical_study_types DROP CHECK ck_study_type_category, ADD CONSTRAINT ck_study_type_category CHECK (category_key IN (''LABORATORIO'',''IMAGEN'',''CARDIOVASCULAR'',''OFTALMOLOGIA'',''NEUROFISIOLOGIA'',''FUNCION_PULMONAR'',''AUDIOLOGIA'',''DENTAL'',''PATOLOGIA'',''ENDOSCOPIA'',''PROCEDIMIENTOS_DIAGNOSTICOS'',''SUENO'',''GENETICA'',''OTROS''))',
  'DO 0');
PREPARE proc_cat02b_category_stmt FROM @proc_cat02b_sql;
EXECUTE proc_cat02b_category_stmt;
DEALLOCATE PREPARE proc_cat02b_category_stmt;

-- Exactly three approved diagnostic-only procedures. Idempotent data insert.
INSERT INTO clinical_study_types
  (study_type_key,display_name_es,category_key,aliases_json,is_active,seed_provenance) VALUES
  ('colposcopy_diagnostic','Colposcopia diagnóstica','PROCEDIMIENTOS_DIAGNOSTICOS','["colposcopía","videocolposcopia"]',1,'PROC-CAT02B:2026_10_05_27'),
  ('hysteroscopy_diagnostic','Histeroscopia diagnóstica','PROCEDIMIENTOS_DIAGNOSTICOS','["histeroscopía diagnóstica","histeroscopia de consultorio"]',1,'PROC-CAT02B:2026_10_05_27'),
  ('cystoscopy_diagnostic','Cistoscopia diagnóstica','PROCEDIMIENTOS_DIAGNOSTICOS','["cistouretroscopia diagnóstica","videocistoscopia simple"]',1,'PROC-CAT02B:2026_10_05_27')
ON DUPLICATE KEY UPDATE study_type_key=study_type_key;
