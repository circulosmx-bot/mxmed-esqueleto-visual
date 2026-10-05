-- DENTAL-CAT03C: activate four source-audited intraoral studies without changing historical orders.
INSERT INTO clinical_study_types
  (study_type_key,display_name_es,category_key,aliases_json,is_active,seed_provenance) VALUES
  ('dental_periapical_xray','Radiografía periapical','IMAGEN','["Rx periapical","Radiografías periapicales","Radiografía dental periapical"]',1,'DENTAL-CAT03C:2026_10_05_26_dental_cat03c_minimum.sql'),
  ('dental_bitewing_xray','Radiografía interproximal de aleta de mordida','IMAGEN','["Bitewing","Radiografía bitewing","Aleta de mordida","Radiografía interproximal"]',1,'DENTAL-CAT03C:2026_10_05_26_dental_cat03c_minimum.sql'),
  ('dental_occlusal_xray','Radiografía oclusal dental','IMAGEN','["Rx oclusal","Oclusal superior","Oclusal inferior","Radiografía oclusal"]',1,'DENTAL-CAT03C:2026_10_05_26_dental_cat03c_minimum.sql'),
  ('dental_full_periapical_series','Serie radiográfica intraoral de boca completa','IMAGEN','["Serie periapical completa","Serie radiográfica dental","Serie de 14 imágenes","Serie de 16 imágenes","Serie de 18 imágenes"]',1,'DENTAL-CAT03C:2026_10_05_26_dental_cat03c_minimum.sql')
ON DUPLICATE KEY UPDATE study_type_key=study_type_key;
