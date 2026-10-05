-- FUNC-CAT02B: add GI physiology category and exactly two approved functional studies.
-- Existing rows, order snapshots, results and provider offerings are not changed.
SET @func_cat02b_has_category := (SELECT COUNT(*) FROM information_schema.CHECK_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='ck_study_type_category'
    AND CHECK_CLAUSE LIKE '%FUNCION_DIGESTIVA%');
SET @func_cat02b_sql := IF(@func_cat02b_has_category=0,
  'ALTER TABLE clinical_study_types DROP CHECK ck_study_type_category, ADD CONSTRAINT ck_study_type_category CHECK (category_key IN (''LABORATORIO'',''IMAGEN'',''CARDIOVASCULAR'',''OFTALMOLOGIA'',''NEUROFISIOLOGIA'',''FUNCION_PULMONAR'',''FUNCION_DIGESTIVA'',''AUDIOLOGIA'',''DENTAL'',''PATOLOGIA'',''ENDOSCOPIA'',''PROCEDIMIENTOS_DIAGNOSTICOS'',''SUENO'',''GENETICA'',''OTROS''))',
  'DO 0');
PREPARE func_cat02b_category_stmt FROM @func_cat02b_sql;
EXECUTE func_cat02b_category_stmt;
DEALLOCATE PREPARE func_cat02b_category_stmt;

INSERT INTO clinical_study_types
  (study_type_key,display_name_es,category_key,aliases_json,is_active,seed_provenance) VALUES
  ('esophageal_manometry','Manometría esofágica','FUNCION_DIGESTIVA','["Manometría esofágica de alta resolución"]',1,'FUNC-CAT02B:2026_10_05_28'),
  ('esophageal_ph_monitoring','Monitoreo de pH esofágico','FUNCION_DIGESTIVA','["pHmetría esofágica","pH-impedancia","Impedancia-pH"]',1,'FUNC-CAT02B:2026_10_05_28')
ON DUPLICATE KEY UPDATE study_type_key=study_type_key;
