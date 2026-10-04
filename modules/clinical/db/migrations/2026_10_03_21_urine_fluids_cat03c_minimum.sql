-- URINE-FLUIDS-CAT03C-IMPL: 17 matrix-approved identities; data only, idempotent.
INSERT INTO clinical_study_types (study_type_key,display_name_es,category_key,aliases_json,is_active,seed_provenance) VALUES
  ('body_fluid_cell_count','Recuento celular y diferencial en líquido corporal','LABORATORIO','[]',1,'URINE-FLUIDS-CAT03C-IMPL:2026_10_03_21'),
  ('body_fluid_glucose','Glucosa en líquido corporal','LABORATORIO','[]',1,'URINE-FLUIDS-CAT03C-IMPL:2026_10_03_21'),
  ('body_fluid_total_protein','Proteínas totales en líquido corporal','LABORATORIO','[]',1,'URINE-FLUIDS-CAT03C-IMPL:2026_10_03_21'),
  ('body_fluid_albumin','Albúmina en líquido corporal','LABORATORIO','[]',1,'URINE-FLUIDS-CAT03C-IMPL:2026_10_03_21'),
  ('body_fluid_ldh','LDH en líquido corporal','LABORATORIO','[]',1,'URINE-FLUIDS-CAT03C-IMPL:2026_10_03_21'),
  ('body_fluid_amylase','Amilasa en líquido corporal','LABORATORIO','[]',1,'URINE-FLUIDS-CAT03C-IMPL:2026_10_03_21'),
  ('body_fluid_triglycerides','Triglicéridos en líquido corporal','LABORATORIO','[]',1,'URINE-FLUIDS-CAT03C-IMPL:2026_10_03_21'),
  ('body_fluid_cholesterol','Colesterol en líquido corporal','LABORATORIO','[]',1,'URINE-FLUIDS-CAT03C-IMPL:2026_10_03_21'),
  ('sterile_body_fluid_bacterial_culture','Cultivo bacteriano de líquido estéril','LABORATORIO','[]',1,'URINE-FLUIDS-CAT03C-IMPL:2026_10_03_21'),
  ('serous_fluid_cytology','Citología de líquido seroso','PATOLOGIA','[]',1,'URINE-FLUIDS-CAT03C-IMPL:2026_10_03_21'),
  ('urine_cytology','Citología urinaria','PATOLOGIA','[]',1,'URINE-FLUIDS-CAT03C-IMPL:2026_10_03_21'),
  ('csf_lactate','Lactato en LCR','LABORATORIO','[]',1,'URINE-FLUIDS-CAT03C-IMPL:2026_10_03_21'),
  ('csf_oligoclonal_bands','Bandas oligoclonales en LCR y suero','LABORATORIO','[]',1,'URINE-FLUIDS-CAT03C-IMPL:2026_10_03_21'),
  ('csf_cryptococcal_antigen','Antígeno criptocócico en LCR','LABORATORIO','[]',1,'URINE-FLUIDS-CAT03C-IMPL:2026_10_03_21'),
  ('csf_vdrl','VDRL en LCR','LABORATORIO','[]',1,'URINE-FLUIDS-CAT03C-IMPL:2026_10_03_21'),
  ('csf_meningitis_encephalitis_panel','Panel molecular de meningitis/encefalitis en LCR','LABORATORIO','[]',1,'URINE-FLUIDS-CAT03C-IMPL:2026_10_03_21'),
  ('csf_cytology','Citología de LCR','PATOLOGIA','[]',1,'URINE-FLUIDS-CAT03C-IMPL:2026_10_03_21')
ON DUPLICATE KEY UPDATE study_type_key=study_type_key;
