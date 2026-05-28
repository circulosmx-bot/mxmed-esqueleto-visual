-- Seed demo minimo para QA local de perfil publico medico.
-- doctor_id=1 se usa como caso de prueba transicional PP-7D.

INSERT INTO `profiles_doctors` (
  `doctor_id`,
  `display_name`,
  `prefix`,
  `gender_label`,
  `professional_license`,
  `specialty_license`,
  `specialty_primary`,
  `specialty_secondary_json`,
  `bio_short`,
  `photo_url`,
  `avatar_url`,
  `logo_url`,
  `profile_status`,
  `is_public_candidate`
) VALUES (
  '1',
  'Dra. Leticia Muñoz Alfaro',
  'Dra.',
  'Femenino',
  '0123456',
  '6543210',
  'Medicina Interna',
  '["Cirugía Gastrointestinal y Laparoscópica"]',
  'Médico especialista con atención profesional y enfoque integral.',
  NULL,
  NULL,
  NULL,
  'active',
  1
)
ON DUPLICATE KEY UPDATE
  `display_name` = VALUES(`display_name`),
  `prefix` = VALUES(`prefix`),
  `gender_label` = VALUES(`gender_label`),
  `professional_license` = VALUES(`professional_license`),
  `specialty_license` = VALUES(`specialty_license`),
  `specialty_primary` = VALUES(`specialty_primary`),
  `specialty_secondary_json` = VALUES(`specialty_secondary_json`),
  `bio_short` = VALUES(`bio_short`),
  `photo_url` = VALUES(`photo_url`),
  `avatar_url` = VALUES(`avatar_url`),
  `logo_url` = VALUES(`logo_url`),
  `profile_status` = VALUES(`profile_status`),
  `is_public_candidate` = VALUES(`is_public_candidate`);
