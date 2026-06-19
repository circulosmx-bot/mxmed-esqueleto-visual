-- Ruta canonica actual por entidad publicable.
-- Aliases e historial 301 deben modelarse en una tabla posterior.

CREATE TABLE IF NOT EXISTS public_profile_seo_routes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

  entity_type VARCHAR(40) NOT NULL,
  entity_id VARCHAR(64) NOT NULL,

  profile_type VARCHAR(40) NOT NULL,

  profile_slug VARCHAR(180) NOT NULL,
  canonical_path VARCHAR(255) NOT NULL,

  canonical_state_slug VARCHAR(120) NOT NULL,
  canonical_city_slug VARCHAR(120) NOT NULL,
  canonical_specialty_slug VARCHAR(120) NULL DEFAULT NULL,

  status VARCHAR(30) NOT NULL DEFAULT 'candidate',

  route_enabled TINYINT(1) NOT NULL DEFAULT 0,
  canonical_enabled TINYINT(1) NOT NULL DEFAULT 0,

  source VARCHAR(60) NOT NULL DEFAULT 'derived_public_url_builder',
  version VARCHAR(60) NOT NULL DEFAULT 'seo-route-v1',

  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,

  PRIMARY KEY (id),

  UNIQUE KEY uniq_public_profile_seo_routes_entity (entity_type, entity_id),
  UNIQUE KEY uq_public_profile_seo_routes_canonical_path (canonical_path),

  KEY idx_public_profile_seo_routes_profile_type_status (profile_type, status),
  KEY idx_public_profile_seo_routes_geo_status (canonical_state_slug, canonical_city_slug, status),
  KEY idx_public_profile_seo_routes_profile_slug (profile_slug),
  KEY idx_public_profile_seo_routes_specialty_geo (canonical_specialty_slug, canonical_state_slug, canonical_city_slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
