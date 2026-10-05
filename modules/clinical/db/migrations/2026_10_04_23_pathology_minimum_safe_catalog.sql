-- PATH-CAT02B: exactly 11 PATH-CAT01-approved pathology examination identities.
-- Data only; reruns preserve existing snapshots and catalog edits.
INSERT INTO clinical_study_types
  (study_type_key,display_name_es,category_key,aliases_json,is_active,seed_provenance) VALUES
  ('histopath_biopsy','Estudio histopatológico de biopsia','PATOLOGIA','["Histopatológico de biopsia","Patología de biopsia"]',1,'PATH-CAT02B:2026_10_04_23'),
  ('histopath_resection','Estudio histopatológico de pieza quirúrgica','PATOLOGIA','["Pieza quirúrgica","Resección para patología"]',1,'PATH-CAT02B:2026_10_04_23'),
  ('cyto_fna','Citología por aspiración con aguja fina','PATOLOGIA','["PAAF","BAAF","Citología aspirativa"]',1,'PATH-CAT02B:2026_10_04_23'),
  ('cyto_bronchial_brushing','Citología de cepillado bronquial','PATOLOGIA','["Citología de cepillado"]',1,'PATH-CAT02B:2026_10_04_23'),
  ('cyto_bronchial_washing','Citología de lavado bronquial','PATOLOGIA','["Citología de lavado"]',1,'PATH-CAT02B:2026_10_04_23'),
  ('ihc_single_marker','Inmunohistoquímica — marcador individual','PATOLOGIA','["IHQ","IHQ por anticuerpo"]',1,'PATH-CAT02B:2026_10_04_23'),
  ('ihc_breast_profile','Perfil inmunohistoquímico mamario ER PR HER2 Ki-67','PATOLOGIA','["Perfil IHQ mama"]',1,'PATH-CAT02B:2026_10_04_23'),
  ('histochemical_special_stain','Tinción histoquímica especial','PATOLOGIA','["Histoquímica especial","Tinciones especiales"]',1,'PATH-CAT02B:2026_10_04_23'),
  ('if_renal','Inmunofluorescencia directa renal','PATOLOGIA','["IF renal"]',1,'PATH-CAT02B:2026_10_04_23'),
  ('if_skin','Inmunofluorescencia directa cutánea','PATOLOGIA','["IF piel"]',1,'PATH-CAT02B:2026_10_04_23'),
  ('pathology_outside_review','Revisión externa de laminillas o bloques','PATOLOGIA','["Segunda opinión de patología"]',1,'PATH-CAT02B:2026_10_04_23')
ON DUPLICATE KEY UPDATE study_type_key=study_type_key;
