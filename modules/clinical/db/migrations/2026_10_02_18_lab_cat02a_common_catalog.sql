-- LAB-CAT02A: only safe common single-order laboratory identities and clear source aliases.
-- No order/result/schema changes. Idempotent seed; existing immutable keys retain their identity.
INSERT INTO clinical_study_types
  (study_type_key,display_name_es,category_key,aliases_json,is_active,seed_provenance) VALUES
  ('lab_coombs_directo','Coombs directo','LABORATORIO','["Prueba de antiglobulina directa", "DAT"]',1,'LAB-CAT02A:2026_10_02_18_lab_cat02a_common_catalog.sql'),
  ('lab_coombs_indirecto','Coombs indirecto','LABORATORIO','["Prueba de antiglobulina indirecta", "IAT"]',1,'LAB-CAT02A:2026_10_02_18_lab_cat02a_common_catalog.sql'),
  ('lab_frotis_sanguineo','Frotis sanguíneo','LABORATORIO','["Frotis de sangre periférica"]',1,'LAB-CAT02A:2026_10_02_18_lab_cat02a_common_catalog.sql'),
  ('lab_reticulocitos','Reticulocitos','LABORATORIO','["Recuento de reticulocitos"]',1,'LAB-CAT02A:2026_10_02_18_lab_cat02a_common_catalog.sql'),
  ('lab_tiempo_de_protrombina','Tiempo de protrombina','LABORATORIO','["TP", "PT"]',1,'LAB-CAT02A:2026_10_02_18_lab_cat02a_common_catalog.sql'),
  ('lab_tiempo_de_trombina','Tiempo de trombina','LABORATORIO','["TT"]',1,'LAB-CAT02A:2026_10_02_18_lab_cat02a_common_catalog.sql'),
  ('lab_amonio','Amonio','LABORATORIO','["Amoníaco plasmático"]',1,'LAB-CAT02A:2026_10_02_18_lab_cat02a_common_catalog.sql'),
  ('lab_litio','Litio','LABORATORIO','["Nivel de litio"]',1,'LAB-CAT02A:2026_10_02_18_lab_cat02a_common_catalog.sql'),
  ('lab_cpk','Creatina cinasa total','LABORATORIO','["CPK", "CK total"]',1,'LAB-CAT02A:2026_10_02_18_lab_cat02a_common_catalog.sql'),
  ('lab_ck_mb','Creatina cinasa MB','LABORATORIO','["CK-MB", "CK MB"]',1,'LAB-CAT02A:2026_10_02_18_lab_cat02a_common_catalog.sql'),
  ('lab_calprotectina_cuantificada','Calprotectina fecal cuantitativa','LABORATORIO','["Calprotectina cuantificada", "Calprotectina fecal"]',1,'LAB-CAT02A:2026_10_02_18_lab_cat02a_common_catalog.sql'),
  ('lab_ag_helicobacter_pylori','Antígeno de Helicobacter pylori en heces','LABORATORIO','["Ag. Helicobacter pylori", "Antígeno H. pylori en heces"]',1,'LAB-CAT02A:2026_10_02_18_lab_cat02a_common_catalog.sql')
ON DUPLICATE KEY UPDATE study_type_key=study_type_key;

UPDATE clinical_study_types SET aliases_json=JSON_ARRAY_APPEND(aliases_json, '$', '% de saturación de transferrina')
 WHERE study_type_key='transferrin_sat' AND JSON_CONTAINS(aliases_json, JSON_QUOTE('% de saturación de transferrina'))=0;
UPDATE clinical_study_types SET aliases_json=JSON_ARRAY_APPEND(aliases_json, '$', 'Acs. anti-VHC')
 WHERE study_type_key='hcv_ab' AND JSON_CONTAINS(aliases_json, JSON_QUOTE('Acs. anti-VHC'))=0;
UPDATE clinical_study_types SET aliases_json=JSON_ARRAY_APPEND(aliases_json, '$', 'Acs. anti-antígeno de superficie')
 WHERE study_type_key='anti_hbs' AND JSON_CONTAINS(aliases_json, JSON_QUOTE('Acs. anti-antígeno de superficie'))=0;
UPDATE clinical_study_types SET aliases_json=JSON_ARRAY_APPEND(aliases_json, '$', 'Acs. anti-peroxidasa')
 WHERE study_type_key='anti_tpo' AND JSON_CONTAINS(aliases_json, JSON_QUOTE('Acs. anti-peroxidasa'))=0;
UPDATE clinical_study_types SET aliases_json=JSON_ARRAY_APPEND(aliases_json, '$', 'Acs. anti-péptido cíclicos citrulinados')
 WHERE study_type_key='anti_ccp' AND JSON_CONTAINS(aliases_json, JSON_QUOTE('Acs. anti-péptido cíclicos citrulinados'))=0;
UPDATE clinical_study_types SET aliases_json=JSON_ARRAY_APPEND(aliases_json, '$', 'Acs. anti-tiroglobulina')
 WHERE study_type_key='anti_tg' AND JSON_CONTAINS(aliases_json, JSON_QUOTE('Acs. anti-tiroglobulina'))=0;
UPDATE clinical_study_types SET aliases_json=JSON_ARRAY_APPEND(aliases_json, '$', 'Ag. de superficie del VHB')
 WHERE study_type_key='hbsag' AND JSON_CONTAINS(aliases_json, JSON_QUOTE('Ag. de superficie del VHB'))=0;
UPDATE clinical_study_types SET aliases_json=JSON_ARRAY_APPEND(aliases_json, '$', 'Alanino aminotransferasa')
 WHERE study_type_key='alt' AND JSON_CONTAINS(aliases_json, JSON_QUOTE('Alanino aminotransferasa'))=0;
UPDATE clinical_study_types SET aliases_json=JSON_ARRAY_APPEND(aliases_json, '$', 'Aspartato aminotransferasa')
 WHERE study_type_key='ast' AND JSON_CONTAINS(aliases_json, JSON_QUOTE('Aspartato aminotransferasa'))=0;
UPDATE clinical_study_types SET aliases_json=JSON_ARRAY_APPEND(aliases_json, '$', 'Capacidad de fijación total de hierro (transferrina)')
 WHERE study_type_key='tibc' AND JSON_CONTAINS(aliases_json, JSON_QUOTE('Capacidad de fijación total de hierro (transferrina)'))=0;
UPDATE clinical_study_types SET aliases_json=JSON_ARRAY_APPEND(aliases_json, '$', 'Citometría hemática')
 WHERE study_type_key='cbc' AND JSON_CONTAINS(aliases_json, JSON_QUOTE('Citometría hemática'))=0;
UPDATE clinical_study_types SET aliases_json=JSON_ARRAY_APPEND(aliases_json, '$', 'Colesterol HDL')
 WHERE study_type_key='hdl' AND JSON_CONTAINS(aliases_json, JSON_QUOTE('Colesterol HDL'))=0;
UPDATE clinical_study_types SET aliases_json=JSON_ARRAY_APPEND(aliases_json, '$', 'Colesterol LDL')
 WHERE study_type_key='ldl' AND JSON_CONTAINS(aliases_json, JSON_QUOTE('Colesterol LDL'))=0;
UPDATE clinical_study_types SET aliases_json=JSON_ARRAY_APPEND(aliases_json, '$', 'Complemento C3')
 WHERE study_type_key='c3' AND JSON_CONTAINS(aliases_json, JSON_QUOTE('Complemento C3'))=0;
UPDATE clinical_study_types SET aliases_json=JSON_ARRAY_APPEND(aliases_json, '$', 'Complemento C4')
 WHERE study_type_key='c4' AND JSON_CONTAINS(aliases_json, JSON_QUOTE('Complemento C4'))=0;
UPDATE clinical_study_types SET aliases_json=JSON_ARRAY_APPEND(aliases_json, '$', 'Curva de tolerancia glucosa')
 WHERE study_type_key='ogtt' AND JSON_CONTAINS(aliases_json, JSON_QUOTE('Curva de tolerancia glucosa'))=0;
UPDATE clinical_study_types SET aliases_json=JSON_ARRAY_APPEND(aliases_json, '$', 'Examen general de orina')
 WHERE study_type_key='urinalysis' AND JSON_CONTAINS(aliases_json, JSON_QUOTE('Examen general de orina'))=0;
UPDATE clinical_study_types SET aliases_json=JSON_ARRAY_APPEND(aliases_json, '$', 'Fosfatasa alcalina')
 WHERE study_type_key='alp' AND JSON_CONTAINS(aliases_json, JSON_QUOTE('Fosfatasa alcalina'))=0;
UPDATE clinical_study_types SET aliases_json=JSON_ARRAY_APPEND(aliases_json, '$', 'Gamaglutamil transpeptidasa')
 WHERE study_type_key='ggt' AND JSON_CONTAINS(aliases_json, JSON_QUOTE('Gamaglutamil transpeptidasa'))=0;
UPDATE clinical_study_types SET aliases_json=JSON_ARRAY_APPEND(aliases_json, '$', 'Hemoglobina glicosilada')
 WHERE study_type_key='hba1c' AND JSON_CONTAINS(aliases_json, JSON_QUOTE('Hemoglobina glicosilada'))=0;
UPDATE clinical_study_types SET aliases_json=JSON_ARRAY_APPEND(aliases_json, '$', 'Hormona estimulante de tiroides')
 WHERE study_type_key='tsh' AND JSON_CONTAINS(aliases_json, JSON_QUOTE('Hormona estimulante de tiroides'))=0;
UPDATE clinical_study_types SET aliases_json=JSON_ARRAY_APPEND(aliases_json, '$', 'Hormona folículo estimulante')
 WHERE study_type_key='fsh' AND JSON_CONTAINS(aliases_json, JSON_QUOTE('Hormona folículo estimulante'))=0;
UPDATE clinical_study_types SET aliases_json=JSON_ARRAY_APPEND(aliases_json, '$', 'Hormona luteinizante')
 WHERE study_type_key='lh' AND JSON_CONTAINS(aliases_json, JSON_QUOTE('Hormona luteinizante'))=0;
UPDATE clinical_study_types SET aliases_json=JSON_ARRAY_APPEND(aliases_json, '$', 'Microalbúmina')
 WHERE study_type_key='microalbumin' AND JSON_CONTAINS(aliases_json, JSON_QUOTE('Microalbúmina'))=0;
UPDATE clinical_study_types SET aliases_json=JSON_ARRAY_APPEND(aliases_json, '$', 'Proteína C reactiva H.S.')
 WHERE study_type_key='crp_hs' AND JSON_CONTAINS(aliases_json, JSON_QUOTE('Proteína C reactiva H.S.'))=0;
UPDATE clinical_study_types SET aliases_json=JSON_ARRAY_APPEND(aliases_json, '$', 'Sangre oculta en heces (FOB)')
 WHERE study_type_key='fecal_occult_blood' AND JSON_CONTAINS(aliases_json, JSON_QUOTE('Sangre oculta en heces (FOB)'))=0;
UPDATE clinical_study_types SET aliases_json=JSON_ARRAY_APPEND(aliases_json, '$', 'Tiempo de tromboplastina parcial activado')
 WHERE study_type_key='aptt' AND JSON_CONTAINS(aliases_json, JSON_QUOTE('Tiempo de tromboplastina parcial activado'))=0;
UPDATE clinical_study_types SET aliases_json=JSON_ARRAY_APPEND(aliases_json, '$', 'Tiroxina libre')
 WHERE study_type_key='ft4' AND JSON_CONTAINS(aliases_json, JSON_QUOTE('Tiroxina libre'))=0;
UPDATE clinical_study_types SET aliases_json=JSON_ARRAY_APPEND(aliases_json, '$', 'Triyodotironina libre')
 WHERE study_type_key='ft3' AND JSON_CONTAINS(aliases_json, JSON_QUOTE('Triyodotironina libre'))=0;
UPDATE clinical_study_types SET aliases_json=JSON_ARRAY_APPEND(aliases_json, '$', 'Velocidad de sed. globular')
 WHERE study_type_key='esr' AND JSON_CONTAINS(aliases_json, JSON_QUOTE('Velocidad de sed. globular'))=0;
