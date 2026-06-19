-- Seed candidato para validar el read-model de ruta canonica publica.
-- No activa router, canonical, JSON-LD real ni robots index.

INSERT INTO public_profile_seo_routes (
  entity_type,
  entity_id,
  profile_type,
  profile_slug,
  canonical_path,
  canonical_state_slug,
  canonical_city_slug,
  canonical_specialty_slug,
  status,
  route_enabled,
  canonical_enabled,
  source,
  version
) VALUES (
  'doctor',
  '1',
  'doctor',
  'dra-leticia-munoz-romo',
  '/aguascalientes/aguascalientes/medicos/dra-leticia-munoz-romo',
  'aguascalientes',
  'aguascalientes',
  NULL,
  'candidate',
  0,
  0,
  'derived_public_url_builder',
  'seo-route-v1'
)
ON DUPLICATE KEY UPDATE
  profile_type = VALUES(profile_type),
  profile_slug = VALUES(profile_slug),
  canonical_path = VALUES(canonical_path),
  canonical_state_slug = VALUES(canonical_state_slug),
  canonical_city_slug = VALUES(canonical_city_slug),
  canonical_specialty_slug = VALUES(canonical_specialty_slug),
  status = 'candidate',
  route_enabled = 0,
  canonical_enabled = 0,
  source = VALUES(source),
  version = VALUES(version),
  updated_at = CURRENT_TIMESTAMP;
