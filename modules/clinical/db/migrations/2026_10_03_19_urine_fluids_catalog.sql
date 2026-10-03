-- ORD-COMP01 / URINE-FLUIDS-CAT01: atomic orderables; no specimen/timing subsystem.
-- Idempotent additions only; no existing identity or schema is changed.
INSERT INTO clinical_study_types (study_type_key,display_name_es,category_key,aliases_json,is_active,seed_provenance) VALUES
  ('urine_albumin_creatinine_panel','Albúmina y creatinina urinarias con relación (muestra aislada)','LABORATORIO','["Relación albúmina/creatinina", "ACR urinaria"]',1,'URINE-FLUIDS-CAT01:2026_10_03_19'),
  ('urine_protein_creatinine_panel','Proteínas y creatinina urinarias con relación (muestra aislada)','LABORATORIO','["Relación proteína/creatinina", "PCR urinaria"]',1,'URINE-FLUIDS-CAT01:2026_10_03_19'),
  ('urine_osmolality','Osmolalidad urinaria','LABORATORIO','["Osmolaridad urinaria"]',1,'URINE-FLUIDS-CAT01:2026_10_03_19'),
  ('csf_cell_count','Recuento celular y diferencial en LCR','LABORATORIO','["Citometría de líquido cefalorraquídeo"]',1,'URINE-FLUIDS-CAT01:2026_10_03_19'),
  ('synovial_crystals','Cristales en líquido sinovial','LABORATORIO','["Cristales sinoviales"]',1,'URINE-FLUIDS-CAT01:2026_10_03_19'),
  ('semen_analysis','Espermatobioscopía básica','LABORATORIO','["Análisis de semen", "Espermatograma"]',1,'URINE-FLUIDS-CAT01:2026_10_03_19')
ON DUPLICATE KEY UPDATE study_type_key=study_type_key;
