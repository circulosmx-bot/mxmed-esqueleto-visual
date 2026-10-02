-- DENTAL-CAT02: seven source-audited canonical studies. No table or historical-order rewrite.
-- Carpal and generic craniofacial projection remain unresolved and are deliberately absent.
-- Existing keys retain their identity on re-run; aliases are catalog search terms only.
INSERT INTO clinical_study_types
  (study_type_key,display_name_es,category_key,aliases_json,is_active,seed_provenance) VALUES
  ('dental_cbct','Tomografía dental y maxilofacial de haz cónico','IMAGEN','["Cone Beam","CBCT","Tomografía Cone Beam","Tomografía dental"]',1,'DENTAL-CAT02:2026_10_02_17_dental_cat02_catalog.sql'),
  ('dental_panoramic_xray','Radiografía panorámica dental','IMAGEN','["Ortopantomografía","Rx panorámica","Panorámica dental"]',1,'DENTAL-CAT02:2026_10_02_17_dental_cat02_catalog.sql'),
  ('dental_cephalometric_xray','Radiografía cefalométrica lateral','IMAGEN','["Rx lateral","Lateral de cráneo","Radiografía lateral de cráneo"]',1,'DENTAL-CAT02:2026_10_02_17_dental_cat02_catalog.sql'),
  ('tmj_comparative_xray','Radiografía comparativa de articulaciones temporomandibulares','IMAGEN','["Rx ATM","ATM comparativa","Radiografía ATM"]',1,'DENTAL-CAT02:2026_10_02_17_dental_cat02_catalog.sql'),
  ('dental_intraoral_scan','Escaneo intraoral dental','DENTAL','["Escaneo intraoral","Impresión digital intraoral"]',1,'DENTAL-CAT02:2026_10_02_17_dental_cat02_catalog.sql'),
  ('dental_clinical_photographs','Fotografías clínicas odontológicas','DENTAL','["Fotografías intraorales","Fotografías extraorales","Registros fotográficos dentales"]',1,'DENTAL-CAT02:2026_10_02_17_dental_cat02_catalog.sql'),
  ('dental_study_model','Modelo de estudio dental','DENTAL','["Modelo dental","Modelos de estudio","Modelo de estudio odontológico"]',1,'DENTAL-CAT02:2026_10_02_17_dental_cat02_catalog.sql')
ON DUPLICATE KEY UPDATE study_type_key=study_type_key;
