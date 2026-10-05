-- LAB-CAT05C: exactly two canonical laboratory panel orderables. Safe to re-run.
INSERT INTO clinical_study_types
  (study_type_key,display_name_es,category_key,aliases_json,is_active,seed_provenance) VALUES
  ('panel_quimica_6','Química sanguínea de 6 elementos','LABORATORIO',
   '["QS6","Química sanguínea 6","Química 6","6 elementos"]',1,'LAB-CAT05C:2026_10_04_25_lab_cat05c_panels.sql'),
  ('arterial_blood_gas','Gasometría arterial','LABORATORIO',
   '["GSA","ABG","Gas arterial","Gases arteriales"]',1,'LAB-CAT05C:2026_10_04_25_lab_cat05c_panels.sql')
ON DUPLICATE KEY UPDATE study_type_key=study_type_key;
