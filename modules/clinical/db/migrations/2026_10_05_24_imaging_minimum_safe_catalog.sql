-- IMG-CAT02B: exactly 15 IMG-CAT01-approved general imaging identities.
-- Data only; idempotent rerun leaves existing rows and issued snapshots untouched.
INSERT INTO clinical_study_types
  (study_type_key,display_name_es,category_key,aliases_json,is_active,seed_provenance) VALUES
  ('rx_hip','Radiografía de cadera','IMAGEN','["RX cadera", "Rayos X de cadera"]',1,'IMG-CAT02B:2026_10_05_24'),
  ('ct_sinuses','TAC de senos paranasales','IMAGEN','["TC de senos paranasales", "Tomografía de senos paranasales"]',1,'IMG-CAT02B:2026_10_05_24'),
  ('mr_lumbar_spine','Resonancia magnética de columna lumbar','IMAGEN','["RM lumbar", "RMN lumbar", "Resonancia lumbar"]',1,'IMG-CAT02B:2026_10_05_24'),
  ('rx_foot','Radiografía de pie','IMAGEN','["RX pie", "Rayos X de pie"]',1,'IMG-CAT02B:2026_10_05_24'),
  ('rx_wrist','Radiografía de muñeca','IMAGEN','["RX muñeca", "Rayos X de muñeca"]',1,'IMG-CAT02B:2026_10_05_24'),
  ('rx_elbow','Radiografía de codo','IMAGEN','["RX codo", "Rayos X de codo"]',1,'IMG-CAT02B:2026_10_05_24'),
  ('mr_cervical_spine','Resonancia magnética de columna cervical','IMAGEN','["RM cervical", "RMN cervical"]',1,'IMG-CAT02B:2026_10_05_24'),
  ('ct_neck','TAC de cuello','IMAGEN','["TC cuello", "Tomografía de cuello"]',1,'IMG-CAT02B:2026_10_05_24'),
  ('cta_head_neck','Angio-TC de cabeza y cuello','IMAGEN','["AngioTAC de cabeza y cuello", "CTA cabeza y cuello", "Angiotomografía de cabeza y cuello"]',1,'IMG-CAT02B:2026_10_05_24'),
  ('mra_brain','Angio-RM cerebral','IMAGEN','["Angio-RM cerebral", "MRA cerebral", "Angiorresonancia cerebral"]',1,'IMG-CAT02B:2026_10_05_24'),
  ('mr_pelvis','Resonancia magnética de pelvis','IMAGEN','["RM pelvis", "RMN pelvis"]',1,'IMG-CAT02B:2026_10_05_24'),
  ('breast_tomosynthesis','Tomosíntesis mamaria','IMAGEN','["Tomosintesis mamaria", "Mastografía 3D"]',1,'IMG-CAT02B:2026_10_05_24'),
  ('rx_tspine','Radiografía de columna torácica','IMAGEN','["RX columna torácica", "RX dorsal"]',1,'IMG-CAT02B:2026_10_05_24'),
  ('nm_renal_scan','Gammagrama renal','IMAGEN','["Gammagrafía renal"]',1,'IMG-CAT02B:2026_10_05_24'),
  ('nm_myocardial_perfusion','Perfusión miocárdica nuclear','IMAGEN','["Gammagrama de perfusión miocárdica", "Perfusión miocárdica"]',1,'IMG-CAT02B:2026_10_05_24')
ON DUPLICATE KEY UPDATE study_type_key=study_type_key;
