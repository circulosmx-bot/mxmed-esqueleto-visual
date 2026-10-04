-- URINE-FLUIDS-CAT03A: seven fixed-scope orderables; data only, no specimen contract.
-- Idempotent insert. Existing canonical identities and aliases are never changed.
INSERT INTO clinical_study_types (study_type_key,display_name_es,category_key,aliases_json,is_active,seed_provenance) VALUES
  ('urine_creatinine_spot','Creatinina en orina (muestra aislada)','LABORATORIO','[]',1,'URINE-FLUIDS-CAT03A:2026_10_03_20'),
  ('urine_sodium_spot','Sodio en orina (muestra aislada)','LABORATORIO','[]',1,'URINE-FLUIDS-CAT03A:2026_10_03_20'),
  ('urine_potassium_spot','Potasio en orina (muestra aislada)','LABORATORIO','[]',1,'URINE-FLUIDS-CAT03A:2026_10_03_20'),
  ('urine_pregnancy_qualitative','Prueba de embarazo en orina (cualitativa)','LABORATORIO','[]',1,'URINE-FLUIDS-CAT03A:2026_10_03_20'),
  ('csf_glucose','Glucosa en líquido cefalorraquídeo (LCR)','LABORATORIO','[]',1,'URINE-FLUIDS-CAT03A:2026_10_03_20'),
  ('csf_total_protein','Proteínas totales en líquido cefalorraquídeo (LCR)','LABORATORIO','[]',1,'URINE-FLUIDS-CAT03A:2026_10_03_20'),
  ('post_vasectomy_semen_check','Control de semen posvasectomía','LABORATORIO','[]',1,'URINE-FLUIDS-CAT03A:2026_10_03_20')
ON DUPLICATE KEY UPDATE study_type_key=study_type_key;
