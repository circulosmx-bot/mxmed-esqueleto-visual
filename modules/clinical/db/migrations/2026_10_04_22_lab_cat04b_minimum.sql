-- LAB-CAT04B: exact 20 new identities + 8 existing identity search/display fixes from CAT04A-R1.
-- Data only; safe to re-run. No historical order snapshot mutation.
INSERT INTO clinical_study_types
  (study_type_key,display_name_es,category_key,aliases_json,is_active,seed_provenance) VALUES
  ('crp_standard','Proteína C reactiva convencional','LABORATORIO','["CRP convencional", "Proteína C reactiva estándar"]',1,'LAB-CAT04B:2026_10_04_22_lab_cat04b_minimum.sql'),
  ('psa_total','Antígeno prostático específico total','LABORATORIO','["PSA total", "APE total", "Antígeno prostático total"]',1,'LAB-CAT04B:2026_10_04_22_lab_cat04b_minimum.sql'),
  ('insulin_serum','Insulina sérica','LABORATORIO','["Insulina", "Insulina en suero"]',1,'LAB-CAT04B:2026_10_04_22_lab_cat04b_minimum.sql'),
  ('cortisol_serum','Cortisol sérico','LABORATORIO','["Cortisol", "Cortisol en suero"]',1,'LAB-CAT04B:2026_10_04_22_lab_cat04b_minimum.sql'),
  ('syphilis_vdrl_serum','VDRL sérico','LABORATORIO','["VDRL en suero", "Prueba VDRL sérica"]',1,'LAB-CAT04B:2026_10_04_22_lab_cat04b_minimum.sql'),
  ('ldh_serum','Lactato deshidrogenasa sérica','LABORATORIO','["LDH sérica", "DHL sérica", "Lactato deshidrogenasa en suero"]',1,'LAB-CAT04B:2026_10_04_22_lab_cat04b_minimum.sql'),
  ('psa_free','Antígeno prostático específico libre','LABORATORIO','["PSA libre", "APE libre", "Antígeno prostático libre"]',1,'LAB-CAT04B:2026_10_04_22_lab_cat04b_minimum.sql'),
  ('afp_serum','Alfa-fetoproteína sérica','LABORATORIO','["AFP", "Alfa fetoproteína"]',1,'LAB-CAT04B:2026_10_04_22_lab_cat04b_minimum.sql'),
  ('cea_serum','Antígeno carcinoembrionario sérico','LABORATORIO','["CEA", "Antígeno carcinoembrionario"]',1,'LAB-CAT04B:2026_10_04_22_lab_cat04b_minimum.sql'),
  ('ca125_serum','Antígeno CA 125 sérico','LABORATORIO','["CA 125", "CA-125"]',1,'LAB-CAT04B:2026_10_04_22_lab_cat04b_minimum.sql'),
  ('ca199_serum','Antígeno CA 19-9 sérico','LABORATORIO','["CA 19-9", "CA19-9"]',1,'LAB-CAT04B:2026_10_04_22_lab_cat04b_minimum.sql'),
  ('ca153_serum','Antígeno CA 15-3 sérico','LABORATORIO','["CA 15-3", "CA15-3"]',1,'LAB-CAT04B:2026_10_04_22_lab_cat04b_minimum.sql'),
  ('acth_plasma','Hormona adrenocorticotropa plasmática','LABORATORIO','["ACTH", "Corticotropina plasmática"]',1,'LAB-CAT04B:2026_10_04_22_lab_cat04b_minimum.sql'),
  ('pth_intact','Paratohormona intacta','LABORATORIO','["PTH intacta", "Hormona paratiroidea intacta"]',1,'LAB-CAT04B:2026_10_04_22_lab_cat04b_minimum.sql'),
  ('dheas_serum','Sulfato de dehidroepiandrosterona','LABORATORIO','["DHEA-S", "DHEAS"]',1,'LAB-CAT04B:2026_10_04_22_lab_cat04b_minimum.sql'),
  ('anti_ds_dna','Anticuerpos anti-DNA de doble cadena','LABORATORIO','["anti-dsDNA", "anti-DNA nativo"]',1,'LAB-CAT04B:2026_10_04_22_lab_cat04b_minimum.sql'),
  ('procalcitonin_serum','Procalcitonina sérica','LABORATORIO','["PCT", "Procalcitonina en suero"]',1,'LAB-CAT04B:2026_10_04_22_lab_cat04b_minimum.sql'),
  ('total_t3','Triyodotironina total','LABORATORIO','["T3 total"]',1,'LAB-CAT04B:2026_10_04_22_lab_cat04b_minimum.sql'),
  ('bicarbonate_serum','Bicarbonato / CO2 total sérico','LABORATORIO','["HCO3 sérico", "CO2 total sérico", "Bicarbonato sérico"]',1,'LAB-CAT04B:2026_10_04_22_lab_cat04b_minimum.sql'),
  ('apo_b','Apolipoproteína B','LABORATORIO','["ApoB", "Apo B", "Apolipoproteína B en suero"]',1,'LAB-CAT04B:2026_10_04_22_lab_cat04b_minimum.sql')
ON DUPLICATE KEY UPDATE study_type_key=study_type_key;
UPDATE clinical_study_types SET aliases_json=JSON_ARRAY_APPEND(aliases_json, '$', 'T4L') WHERE study_type_key='ft4' AND category_key='LABORATORIO' AND JSON_CONTAINS(aliases_json, JSON_QUOTE('T4L'))=0;
UPDATE clinical_study_types SET aliases_json=JSON_ARRAY_APPEND(aliases_json, '$', 'T3L') WHERE study_type_key='ft3' AND category_key='LABORATORIO' AND JSON_CONTAINS(aliases_json, JSON_QUOTE('T3L'))=0;
UPDATE clinical_study_types SET aliases_json=JSON_ARRAY_APPEND(aliases_json, '$', 'Virus de inmunodeficiencia humana') WHERE study_type_key='hiv_ag_ac' AND category_key='LABORATORIO' AND JSON_CONTAINS(aliases_json, JSON_QUOTE('Virus de inmunodeficiencia humana'))=0;
UPDATE clinical_study_types SET aliases_json=JSON_ARRAY_APPEND(aliases_json, '$', 'Anticuerpos anti-CCP') WHERE study_type_key='anti_ccp' AND category_key='LABORATORIO' AND JSON_CONTAINS(aliases_json, JSON_QUOTE('Anticuerpos anti-CCP'))=0;
UPDATE clinical_study_types SET aliases_json=JSON_ARRAY_APPEND(aliases_json, '$', 'Curva de tolerancia oral a la glucosa') WHERE study_type_key='ogtt' AND category_key='LABORATORIO' AND JSON_CONTAINS(aliases_json, JSON_QUOTE('Curva de tolerancia oral a la glucosa'))=0;
UPDATE clinical_study_types SET display_name_es='Hemoglobina glicosilada (HbA1c)' WHERE study_type_key='hba1c' AND category_key='LABORATORIO' AND display_name_es IN ('HbA1c','Hemoglobina glicosilada (HbA1c)');
UPDATE clinical_study_types SET aliases_json=JSON_ARRAY_APPEND(aliases_json, '$', 'Hemoglobina glucosilada') WHERE study_type_key='hba1c' AND category_key='LABORATORIO' AND JSON_CONTAINS(aliases_json, JSON_QUOTE('Hemoglobina glucosilada'))=0;
UPDATE clinical_study_types SET aliases_json=JSON_ARRAY_APPEND(aliases_json, '$', 'Hemoglobina A1c') WHERE study_type_key='hba1c' AND category_key='LABORATORIO' AND JSON_CONTAINS(aliases_json, JSON_QUOTE('Hemoglobina A1c'))=0;
UPDATE clinical_study_types SET display_name_es='Velocidad de sedimentación globular (VSG)' WHERE study_type_key='esr' AND category_key='LABORATORIO' AND display_name_es IN ('VSG','Velocidad de sedimentación globular (VSG)');
UPDATE clinical_study_types SET aliases_json=JSON_ARRAY_APPEND(aliases_json, '$', 'Velocidad de sedimentación globular') WHERE study_type_key='esr' AND category_key='LABORATORIO' AND JSON_CONTAINS(aliases_json, JSON_QUOTE('Velocidad de sedimentación globular'))=0;
UPDATE clinical_study_types SET display_name_es='Proteína C reactiva ultrasensible (PCR-us)' WHERE study_type_key='crp_hs' AND category_key='LABORATORIO' AND display_name_es IN ('PCR ultrasensible','Proteína C reactiva ultrasensible (PCR-us)');
UPDATE clinical_study_types SET aliases_json=JSON_ARRAY_APPEND(aliases_json, '$', 'hs-CRP') WHERE study_type_key='crp_hs' AND category_key='LABORATORIO' AND JSON_CONTAINS(aliases_json, JSON_QUOTE('hs-CRP'))=0;
UPDATE clinical_study_types SET aliases_json=JSON_ARRAY_APPEND(aliases_json, '$', 'PCR-us') WHERE study_type_key='crp_hs' AND category_key='LABORATORIO' AND JSON_CONTAINS(aliases_json, JSON_QUOTE('PCR-us'))=0;
