<?php
declare(strict_types=1);

namespace Profiles\Controllers;

use Profiles\Repositories\PublicProfileRepository;
use Profiles\Services\PublicProfileEligibility;
use Profiles\Services\PublicProfilePlanCapabilities;
use function Agenda\Helpers\ConsultorioMap\buildConsultorioPublicMapPayload;

require_once __DIR__ . '/../repositories/PublicProfileRepository.php';
require_once __DIR__ . '/../services/PublicProfileEligibility.php';
require_once __DIR__ . '/../services/PublicProfilePlanCapabilities.php';
require_once __DIR__ . '/../../agenda/helpers/consultorio_map.php';

final class PublicProfileController
{
    private PublicProfileRepository $repository;

    public function __construct(PublicProfileRepository $repository)
    {
        $this->repository = $repository;
    }

    public function showByDoctorId(string $doctorId, ?string $planOverride = null): array
    {
        $doctorId = trim($doctorId);
        if (!$this->isValidDoctorId($doctorId)) {
            return $this->error('invalid_doctor_id', 'doctor_id invalid');
        }

        $effectivePlanCode = null;
        if ($planOverride !== null) {
            $effectivePlanCode = PublicProfilePlanCapabilities::normalizePlanCode($planOverride);
        }

        $snapshot = $this->repository->resolvePublicDoctorProfile($doctorId);
        if (!(bool)($snapshot['exists'] ?? false)) {
            return $this->error('profile_not_found', 'public profile not found');
        }

        $identity = (array)($snapshot['identity'] ?? []);
        $professional = (array)($snapshot['professional'] ?? []);
        $specialties = (array)($snapshot['specialties'] ?? []);
        $profileSource = (array)($snapshot['profile_source'] ?? []);
        $planSource = (array)($snapshot['plan_source'] ?? []);
        $ownershipSource = (array)($snapshot['ownership_source'] ?? []);
        if ($effectivePlanCode === null) {
            $effectivePlanCode = PublicProfilePlanCapabilities::normalizePlanCode($planSource['plan_code'] ?? null);
        }
        $planSourceName = $planOverride !== null
            ? 'dev_override'
            : ($planSource['source'] ?? 'default_free');
        $scheduleRows = is_array($snapshot['schedule_rows'] ?? null) ? $snapshot['schedule_rows'] : [];
        $consultorioRows = is_array($snapshot['consultorios'] ?? null) ? $snapshot['consultorios'] : [];
        $publicContactPoints = is_array($snapshot['public_contact_points'] ?? null) ? $snapshot['public_contact_points'] : [];
        $publicContact = $this->buildPublicContactPayload(
            $publicContactPoints,
            $effectivePlanCode
        );
        $membershipAuthorityReady = (bool)($ownershipSource['source_ready'] ?? false);
        $profileIsAdministered = $membershipAuthorityReady
            ? (bool)($ownershipSource['is_administered'] ?? true)
            : false;
        $hasPublicConsultorioContact = $this->hasPublicConsultorioContact($consultorioRows);
        $consultorioContactContract = PublicProfilePlanCapabilities::build($effectivePlanCode, [
            'public_contact_source_ready' => $hasPublicConsultorioContact,
        ]);
        $consultorioPublicVisibility = (array)$consultorioContactContract['public_visibility'];

        $planContext = [
            'plan_source' => $planSourceName,
            'has_public_profile' => false,
            'is_claimed' => false,
            'profile_is_administered' => $profileIsAdministered,
            'ownership_source_ready' => true,
            'public_contact_source_ready' => (bool)($publicContact['has_public_contact'] ?? false),
            'claim_source_ready' => false,
            'commercial_source_ready' => false,
        ];
        $planContract = PublicProfilePlanCapabilities::build($effectivePlanCode, $planContext);
        $publicVisibility = (array)$planContract['public_visibility'];

        $consultorios = $this->mapConsultorios(
            $consultorioRows,
            $scheduleRows,
            $consultorioPublicVisibility
        );
        $geoContext = $this->buildGeoContext($consultorios);

        $schedule = $this->buildSchedule($scheduleRows);
        $hasMinimumPublicData = PublicProfileEligibility::hasMinimumPublicData(
            $identity,
            $professional,
            $specialties,
            $consultorios
        );
        $sourceStatus = $this->normalizeProfileStatus($profileSource['profile_status'] ?? null);
        $isPublicCandidate = (bool)($profileSource['is_public_candidate'] ?? false);
        $isPublic = $hasMinimumPublicData && $isPublicCandidate && $sourceStatus === 'active';
        $profileStatus = $isPublic ? 'active' : 'hidden';
        $planContext['has_public_profile'] = $isPublic;
        $planContract = PublicProfilePlanCapabilities::build($effectivePlanCode, $planContext);
        $plan = (array)$planContract['plan'];
        $publicVisibility = (array)$planContract['public_visibility'];
        $contact = $this->mergeContactCapabilities((array)$planContract['contact'], $publicContact, $publicVisibility);
        $featureFlags = (array)$planContract['feature_flags'];
        $featureFlags['has_public_contact'] = (bool)($contact['has_public_contact'] ?? false);
        $featureFlags['has_public_phone'] = (bool)($contact['has_public_phone'] ?? false);
        $featureFlags['has_public_whatsapp'] = (bool)($contact['has_public_whatsapp'] ?? false);
        $featureFlags['has_public_email'] = (bool)($contact['has_public_email'] ?? false);

        $city = $this->firstNonEmpty($consultorios[0]['city'] ?? null);
        $displayName = $this->firstNonEmpty($identity['display_name'] ?? null);
        $title = null;
        $description = null;
        $h1 = null;
        if ($displayName !== null && $city !== null) {
            $title = sprintf('%s en %s | Mexico Medico', $displayName, $city);
            $description = sprintf('Perfil profesional de %s en %s.', $displayName, $city);
            $h1 = $displayName;
        } elseif ($displayName !== null) {
            $title = sprintf('%s | Mexico Medico', $displayName);
            $description = sprintf('Perfil profesional de %s.', $displayName);
            $h1 = $displayName;
        }
        $sanitizedSpecialties = $this->sanitizeSpecialties($specialties);
        $publicNavigationTaxonomy = $this->buildPublicNavigationTaxonomy();
        $publicUrlContext = $this->buildPublicUrlContext(
            $doctorId,
            $displayName,
            $geoContext,
            $publicNavigationTaxonomy,
            $sanitizedSpecialties,
            $identity
        );
        $publicCanonicalRoute = $this->buildPublicCanonicalRoute(
            'doctor',
            $doctorId,
            $this->repository->findPublicCanonicalRoute('doctor', $doctorId),
            $publicUrlContext
        );
        $seo = [
            'title' => $title,
            'description' => $description,
            'h1' => $h1,
            'canonical_url' => null,
            'robots' => 'noindex,nofollow',
            'og_title' => $title,
            'og_description' => $description,
            'og_image' => $this->firstNonEmpty($identity['photo_url'] ?? null),
            'breadcrumb' => [],
        ];
        $canonicalRenderGuard = $this->buildCanonicalRenderGuard($publicCanonicalRoute, $seo);
        $publicBreadcrumbs = $this->buildPublicBreadcrumbs($publicUrlContext, $geoContext);
        $jsonLdRenderGuard = $this->buildJsonLdRenderGuard(
            $publicBreadcrumbs,
            $canonicalRenderGuard,
            $publicCanonicalRoute,
            $seo
        );
        $publicRouteGuard = $this->buildPublicRouteGuard(
            $publicUrlContext,
            $publicCanonicalRoute,
            $canonicalRenderGuard,
            $jsonLdRenderGuard,
            $seo
        );
        $seoActivationSummary = $this->buildSeoActivationSummary(
            $publicUrlContext,
            $publicCanonicalRoute,
            $publicBreadcrumbs,
            $canonicalRenderGuard,
            $jsonLdRenderGuard,
            $publicRouteGuard,
            $seo
        );

        $data = [
            'profile' => [
                'profile_id' => null,
                'doctor_id' => $doctorId,
                'slug' => null,
                'canonical_url' => null,
                'profile_type' => 'doctor',
                'status' => $profileStatus,
                'is_public' => $isPublic,
                'created_origin' => ((bool)($profileSource['has_canonical_row'] ?? false) ? 'profiles_doctors' : null),
                'last_public_update_at' => $this->firstNonEmpty($profileSource['last_public_update_at'] ?? null),
            ],
            'plan' => $plan,
            'public_visibility' => $publicVisibility,
            'identity' => [
                'display_name' => $displayName,
                'prefix' => $this->firstNonEmpty($identity['prefix'] ?? null),
                'gender_label' => $this->firstNonEmpty($identity['gender_label'] ?? null),
                'photo_url' => $this->firstNonEmpty($identity['photo_url'] ?? null),
                'avatar_url' => $this->firstNonEmpty($identity['avatar_url'] ?? null),
                'logo_url' => $this->sanitizePublicLogoUrl($identity['logo_url'] ?? null),
            ],
            'professional' => [
                'professional_license' => $this->firstNonEmpty($professional['professional_license'] ?? null),
                'specialty_license' => $this->firstNonEmpty($professional['specialty_license'] ?? null),
                'specialty_primary' => $this->firstNonEmpty($professional['specialty_primary'] ?? null),
                'bio_short' => $this->firstNonEmpty($professional['bio_short'] ?? null),
                'bio_long' => null,
                'education' => [],
                'certifications' => [],
                'professional_associations' => [],
                'languages' => [],
                'years_experience' => null,
                'services' => [],
                'conditions_treated' => [],
            ],
            'specialties' => $sanitizedSpecialties,
            'consultorios' => $consultorios,
            'geo_context' => $geoContext,
            'public_navigation_taxonomy' => $publicNavigationTaxonomy,
            'public_url_context' => $publicUrlContext,
            'public_canonical_route' => $publicCanonicalRoute,
            'canonical_render_guard' => $canonicalRenderGuard,
            'public_breadcrumbs' => $publicBreadcrumbs,
            'json_ld_render_guard' => $jsonLdRenderGuard,
            'public_route_guard' => $publicRouteGuard,
            'seo_activation_summary' => $seoActivationSummary,
            'schedule' => $schedule,
            'contact' => $contact,
            'agenda_public' => (array)$planContract['agenda_public'],
            'commercial_visibility' => (array)$planContract['commercial_visibility'],
            'reviews' => (array)$planContract['reviews'],
            'claim' => (array)$planContract['claim'],
            'seo' => $seo,
            'json_ld' => null,
            'ecosystem_links' => [
                'medical_groups' => [],
                'insurers' => [],
                'labs' => [],
                'imaging_centers' => [],
                'pharma_partners' => [],
            ],
            'feature_flags' => $featureFlags,
        ];

        return [
            'ok' => true,
            'error' => null,
            'message' => '',
            'data' => $data,
            'meta' => [
                'contract' => 'profile_public_mvp',
                'version' => 'PP-7D',
                'generated_at' => gmdate('c'),
            ],
        ];
    }

    private function mapConsultorios(array $rows, array $scheduleRows, array $publicVisibility): array
    {
        $scheduleByConsultorio = $this->groupScheduleByConsultorio($scheduleRows);
        $mapped = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $consultorioId = trim((string)($row['consultorio_id'] ?? ''));
            if ($consultorioId === '') {
                continue;
            }

            $mapPayload = buildConsultorioPublicMapPayload($row);
            $hasConfirmedCoords = (bool)($mapPayload['public_map_has_confirmed_coords'] ?? false);

            $title = $this->firstNonEmpty($row['titulo'] ?? null);
            if ($title === null) {
                $title = 'Consultorio principal';
            }
            $brandName = $this->firstNonEmpty($row['grupo_nombre'] ?? null) ?? $title;

            $windows = $scheduleByConsultorio[$consultorioId] ?? [];
            $summary = $this->buildScheduleSummary($windows);
            $publicContact = $this->extractPublicConsultorioContact($row);
            $showPhone = (bool)($publicVisibility['show_phone'] ?? false);
            $showWhatsapp = (bool)($publicVisibility['show_whatsapp'] ?? false);

            $mapped[] = [
                'consultorio_id' => $consultorioId,
                'public_name' => $title,
                'brand_name' => $brandName,
                'brand_logo_url' => $this->sanitizePublicLogoUrl($row['logo_url'] ?? null),
                'address' => $this->buildPublicConsultorioAddress($row, $mapPayload),
                'city' => $this->firstNonEmpty($row['municipio'] ?? null),
                'state' => $this->firstNonEmpty($row['estado'] ?? null),
                'municipality' => $this->firstNonEmpty($row['municipio'] ?? null),
                'postal_code' => $this->firstNonEmpty($row['cp'] ?? null),
                'phone_public' => $showPhone ? $publicContact['phone'] : null,
                'whatsapp_public' => $showWhatsapp ? $publicContact['whatsapp'] : null,
                'lat' => $hasConfirmedCoords ? ($mapPayload['lat'] ?? null) : null,
                'lng' => $hasConfirmedCoords ? ($mapPayload['lng'] ?? null) : null,
                'map_embed_url' => $this->firstNonEmpty($mapPayload['public_map_iframe_url'] ?? null),
                'map_can_open_gps' => ((bool)($publicVisibility['show_map_gps'] ?? false) && $hasConfirmedCoords),
                'is_public' => true,
                'is_active' => true,
                'photos' => [],
                'schedule_summary' => $summary,
                'modalities' => [],
            ];
        }
        return $mapped;
    }

    private function hasPublicConsultorioContact(array $rows): bool
    {
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $contact = $this->extractPublicConsultorioContact($row);
            if ($contact['phone'] !== null || $contact['whatsapp'] !== null) {
                return true;
            }
        }
        return false;
    }

    /**
     * Consultorio phone fields are administrator-managed public sede data.
     * Project only the first regular phone and WhatsApp; urgency and raw JSON
     * remain outside the public DTO.
     *
     * @return array{phone:?string,whatsapp:?string}
     */
    private function extractPublicConsultorioContact(array $row): array
    {
        $phone = null;
        $rawPhones = $row['telefonos_json'] ?? null;
        if (is_string($rawPhones) && trim($rawPhones) !== '') {
            $decoded = json_decode($rawPhones, true);
            $rawPhones = is_array($decoded) ? $decoded : [];
        }
        if (is_array($rawPhones)) {
            foreach ($rawPhones as $candidate) {
                $phone = $this->sanitizePublicConsultorioPhone($candidate);
                if ($phone !== null) {
                    break;
                }
            }
        }

        return [
            'phone' => $phone,
            'whatsapp' => $this->sanitizePublicConsultorioPhone($row['whatsapp'] ?? null),
        ];
    }

    private function sanitizePublicConsultorioPhone($value): ?string
    {
        if (!is_string($value) && !is_numeric($value)) {
            return null;
        }
        $display = trim((string)$value);
        if ($display === '') {
            return null;
        }
        $digits = preg_replace('/\D/', '', $display);
        if (!is_string($digits) || strlen($digits) < 7 || strlen($digits) > 16) {
            return null;
        }
        return $display;
    }

    private function buildPublicNavigationTaxonomy(): array
    {
        $sections = [
            [
                'key' => 'medical_specialists',
                'label' => 'Especialistas Médicos',
                'profile_type' => 'doctor',
                'sort_order' => 10,
                'items' => [
                    ['Cardiología', 'cardiologos'],
                    ['Ginecología', 'ginecologos'],
                    ['Pediatría', 'pediatras'],
                    ['Dermatología', 'dermatologos'],
                    ['Otorrinolaringología', 'otorrinolaringologos'],
                    ['Endocrinología', 'endocrinologos'],
                    ['Traumatología y Ortopedia', 'traumatologos-ortopedistas'],
                    ['Neurología', 'neurologos'],
                    ['Oftalmología', 'oftalmologos'],
                    ['Urología', 'urologos'],
                ],
            ],
            [
                'key' => 'dental_specialists',
                'label' => 'Especialistas Dentales',
                'profile_type' => 'dentist',
                'sort_order' => 20,
                'items' => [
                    ['Odontología general', 'odontologia-general'],
                    ['Ortodoncia', 'ortodoncia'],
                    ['Endodoncia', 'endodoncia'],
                    ['Periodoncia', 'periodoncia'],
                    ['Odontopediatría', 'odontopediatria'],
                    ['Cirugía maxilofacial', 'cirugia-maxilofacial'],
                    ['Implantología', 'implantologia'],
                ],
            ],
            [
                'key' => 'other_services',
                'label' => 'Otros servicios',
                'profile_type' => 'service',
                'sort_order' => 30,
                'items' => [
                    ['Psicología', 'psicologia'],
                    ['Nutrición', 'nutricion'],
                    ['Fisioterapia', 'fisioterapia'],
                    ['Enfermería', 'enfermeria'],
                    ['Terapias y rehabilitación', 'terapias-rehabilitacion'],
                ],
            ],
            [
                'key' => 'hospitals',
                'label' => 'Hospitales',
                'profile_type' => 'hospital',
                'sort_order' => 40,
                'items' => [
                    ['Hospitales generales', 'hospitales-generales'],
                    ['Hospitales privados', 'hospitales-privados'],
                    ['Urgencias', 'urgencias'],
                    ['Maternidad', 'maternidad'],
                    ['Cirugía', 'cirugia'],
                ],
            ],
            [
                'key' => 'clinics',
                'label' => 'Clínicas',
                'profile_type' => 'clinic',
                'sort_order' => 50,
                'items' => [
                    ['Clínicas generales', 'clinicas-generales'],
                    ['Clínicas dentales', 'clinicas-dentales'],
                    ['Clínicas de especialidad', 'clinicas-especialidad'],
                    ['Centros de diagnóstico', 'centros-diagnostico'],
                    ['Rehabilitación', 'rehabilitacion'],
                ],
            ],
            [
                'key' => 'laboratories',
                'label' => 'Laboratorios',
                'profile_type' => 'laboratory',
                'sort_order' => 60,
                'items' => [
                    ['Laboratorios clínicos', 'laboratorios-clinicos'],
                    ['Imagenología', 'imagenologia'],
                    ['Rayos X', 'rayos-x'],
                    ['Ultrasonido', 'ultrasonido'],
                    ['Resonancia magnética', 'resonancia-magnetica'],
                    ['Tomografía', 'tomografia'],
                ],
            ],
        ];

        $contractSections = [];
        foreach ($sections as $section) {
            $sectionKey = (string)$section['key'];
            $sectionSource = 'controlled_navigation_taxonomy';
            $profileType = (string)$section['profile_type'];
            $items = [];

            foreach ($section['items'] as $index => $item) {
                $items[] = [
                    'label' => $item[0],
                    'slug' => $item[1],
                    'profile_type' => $profileType,
                    'source' => $sectionSource,
                    'enabled' => true,
                    'sort_order' => (($index + 1) * 10),
                    'route_enabled' => false,
                    'url' => null,
                ];
            }

            $contractSections[$sectionKey] = [
                'key' => $sectionKey,
                'label' => $section['label'],
                'enabled' => true,
                'sort_order' => $section['sort_order'],
                'source' => $sectionSource,
                'items' => $items,
            ];
        }

        return [
            'source' => 'controlled_navigation_taxonomy',
            'version' => 'nav-taxonomy-v1',
            'route_generation' => 'disabled',
            'sections' => $contractSections,
        ];
    }

    private function buildPublicUrlContext(
        string $doctorId,
        ?string $displayName,
        array $geoContext,
        array $publicNavigationTaxonomy,
        array $specialties,
        array $identity
    ): array {
        $warnings = [
            'seo_routes_not_implemented',
            'canonical_url_missing',
            'canonical_slug_missing',
            'slug_history_missing',
            'canonical_pending',
        ];

        $stateName = $this->firstNonEmpty($geoContext['state_name'] ?? null);
        $stateSlug = $this->firstNonEmpty($geoContext['state_slug'] ?? null);
        $cityName = $this->firstNonEmpty($geoContext['city_name'] ?? null);
        $citySlug = $this->firstNonEmpty($geoContext['city_slug'] ?? null);
        $hasGeoPath = ($stateSlug !== null && $citySlug !== null);
        if ($hasGeoPath) {
            $this->appendWarning($warnings, 'geo_slug_transient');
        } else {
            $this->appendWarning($warnings, 'geo_context_incomplete');
        }

        $profileSlug = $displayName !== null ? $this->slugifyPublicUrlText($displayName) : null;
        if ($profileSlug !== null) {
            $this->appendWarning($warnings, 'profile_slug_transient');
        }

        $primarySpecialty = $this->resolvePrimarySpecialtyForUrl($specialties);
        $specialtyName = $primarySpecialty['name'] ?? null;
        $specialtySlug = $primarySpecialty['slug'] ?? null;
        $matchedNavigationItem = $this->findNavigationItemForSpecialty(
            $publicNavigationTaxonomy,
            $specialtyName,
            $specialtySlug
        );
        $listingSlug = $this->firstNonEmpty($matchedNavigationItem['slug'] ?? null) ?? $specialtySlug;
        $listingLabel = $this->resolveListingLabel(
            $this->firstNonEmpty($matchedNavigationItem['label'] ?? null) ?? $specialtyName,
            $listingSlug
        );
        if ($listingSlug !== null) {
            $this->appendWarning($warnings, 'specialty_slug_transient');
        }

        $gender = $this->resolveProfileGenderForUrl($identity, $displayName);
        $singularSpecialty = $this->deriveSingularSpecialtySlug($specialtyName, $specialtySlug, $gender);
        $singularSlug = $this->firstNonEmpty($singularSpecialty['slug'] ?? null);
        if ($singularSlug !== null) {
            $this->appendWarning($warnings, 'specialty_singular_transient');
            $this->appendWarning($warnings, 'singular_specialty_not_canonical');
            if ((bool)($singularSpecialty['is_gendered'] ?? false)) {
                $this->appendWarning($warnings, 'gendered_specialty_slug_not_canonical');
            }
        }

        $preferredCandidateUrl = ($hasGeoPath && $singularSlug !== null && $profileSlug !== null)
            ? $this->buildCandidatePath($stateSlug, $citySlug, $singularSlug, $profileSlug)
            : null;
        $fallbackCandidateUrl = ($hasGeoPath && $profileSlug !== null)
            ? $this->buildCandidatePath($stateSlug, $citySlug, 'medicos', $profileSlug)
            : null;
        $profileListingCandidateUrl = ($hasGeoPath && $listingSlug !== null)
            ? $this->buildCandidatePath($stateSlug, $citySlug, $listingSlug)
            : null;

        $legacyCandidates = [];
        if ($listingSlug !== null && $citySlug !== null && $profileSlug !== null) {
            $legacyCandidates[] = $this->buildCandidatePath($listingSlug, $citySlug, $profileSlug);
            if ($stateSlug !== null) {
                $legacyCandidates[] = $this->buildCandidatePath($listingSlug, $stateSlug, $citySlug, $profileSlug);
            }
            $this->appendWarning($warnings, 'legacy_url_pattern_detected');
        }
        $legacyCandidates = array_values(array_filter($legacyCandidates, static fn($url): bool => is_string($url) && $url !== ''));

        return [
            'source' => 'derived_public_url_builder',
            'version' => 'public-url-v1',
            'route_generation' => 'candidate_only',
            'route_enabled' => false,
            'canonical_enabled' => false,
            'profile' => [
                'current_url' => '/profiles/doctor.php?doctor_id=' . rawurlencode($doctorId),
                'transient_profile_slug' => $profileSlug,
                'preferred_candidate_url' => $preferredCandidateUrl,
                'fallback_candidate_url' => $fallbackCandidateUrl,
                'legacy_candidates' => $legacyCandidates,
                'route_enabled' => false,
                'canonical_enabled' => false,
                'reason_disabled' => 'seo_routes_not_implemented',
            ],
            'listings' => $this->buildListingUrlCandidates($publicNavigationTaxonomy, $stateSlug, $citySlug),
            'breadcrumbs' => $this->buildPublicUrlBreadcrumbCandidates(
                $stateName,
                $stateSlug,
                $cityName,
                $citySlug,
                $listingLabel,
                $profileListingCandidateUrl,
                $displayName,
                $preferredCandidateUrl ?? $fallbackCandidateUrl
            ),
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    private function buildPublicCanonicalRoute(
        string $entityType,
        string $entityId,
        ?array $routeRow,
        array $publicUrlContext
    ): array {
        $candidatePath = $this->firstNonEmpty($publicUrlContext['profile']['fallback_candidate_url'] ?? null);

        if (!is_array($routeRow)) {
            return [
                'source' => 'public_profile_seo_routes',
                'version' => 'canonical-route-readmodel-v1',
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'found' => false,
                'is_persisted' => false,
                'activation_state' => 'not_persisted',
                'status' => null,
                'is_candidate' => false,
                'is_active' => false,
                'is_blocked' => false,
                'profile_slug' => null,
                'canonical_path' => null,
                'canonical_url' => null,
                'canonical_state_slug' => null,
                'canonical_city_slug' => null,
                'canonical_specialty_slug' => null,
                'route_enabled' => false,
                'canonical_enabled' => false,
                'can_render_canonical' => false,
                'can_route' => false,
                'candidate_path_from_builder' => $candidatePath,
                'warnings' => [
                    'canonical_route_not_persisted',
                    'canonical_not_enabled',
                    'route_not_enabled',
                    'robots_noindex_active',
                ],
                'blocking_reasons' => [
                    'canonical_route_not_persisted',
                    'route_disabled',
                    'canonical_disabled',
                    'robots_noindex_active',
                    'seo_router_not_implemented',
                ],
            ];
        }

        $routeEnabled = ((int)($routeRow['route_enabled'] ?? 0) === 1);
        $canonicalEnabled = ((int)($routeRow['canonical_enabled'] ?? 0) === 1);
        $status = $this->firstNonEmpty($routeRow['status'] ?? null);
        $isCandidate = ($status === 'candidate');
        $isActive = ($status === 'active');
        $isBlocked = ($status === 'blocked');
        $activationState = match ($status) {
            'candidate' => 'persisted_candidate',
            'reserved' => 'persisted_reserved',
            'active' => 'persisted_active_pending_router',
            'blocked' => 'persisted_blocked',
            'retired' => 'persisted_retired',
            default => 'unknown_status',
        };

        $warnings = [$isCandidate ? 'canonical_route_candidate_only' : 'canonical_route_not_active', 'robots_noindex_active'];
        if (!$canonicalEnabled) {
            $warnings[] = 'canonical_not_enabled';
        } else {
            $warnings[] = 'canonical_rendering_not_enabled';
        }
        if (!$routeEnabled) {
            $warnings[] = 'route_not_enabled';
        } else {
            $warnings[] = 'seo_router_not_implemented';
        }

        $blockingReasons = [];
        if ($isCandidate) {
            $blockingReasons[] = 'status_candidate';
        } elseif (!$isActive) {
            $blockingReasons[] = $status ? ('status_' . $status) : 'status_unknown';
        }
        if (!$routeEnabled) {
            $blockingReasons[] = 'route_disabled';
        }
        if (!$canonicalEnabled) {
            $blockingReasons[] = 'canonical_disabled';
        }
        $blockingReasons[] = 'robots_noindex_active';
        $blockingReasons[] = 'seo_router_not_implemented';
        if ($canonicalEnabled) {
            $blockingReasons[] = 'canonical_render_not_enabled';
        }

        return [
            'source' => 'public_profile_seo_routes',
            'version' => 'canonical-route-readmodel-v1',
            'entity_type' => $this->firstNonEmpty($routeRow['entity_type'] ?? null) ?? $entityType,
            'entity_id' => $this->firstNonEmpty($routeRow['entity_id'] ?? null) ?? $entityId,
            'found' => true,
            'is_persisted' => true,
            'activation_state' => $activationState,
            'status' => $status,
            'is_candidate' => $isCandidate,
            'is_active' => $isActive,
            'is_blocked' => $isBlocked,
            'profile_slug' => $this->firstNonEmpty($routeRow['profile_slug'] ?? null),
            'canonical_path' => $this->firstNonEmpty($routeRow['canonical_path'] ?? null),
            'canonical_url' => null,
            'canonical_state_slug' => $this->firstNonEmpty($routeRow['canonical_state_slug'] ?? null),
            'canonical_city_slug' => $this->firstNonEmpty($routeRow['canonical_city_slug'] ?? null),
            'canonical_specialty_slug' => $this->firstNonEmpty($routeRow['canonical_specialty_slug'] ?? null),
            'route_enabled' => $routeEnabled,
            'canonical_enabled' => $canonicalEnabled,
            'can_render_canonical' => false,
            'can_route' => false,
            'candidate_path_from_builder' => $candidatePath,
            'warnings' => array_values(array_unique($warnings)),
            'blocking_reasons' => array_values(array_unique($blockingReasons)),
        ];
    }

    private function buildCanonicalRenderGuard(array $publicCanonicalRoute, array $seo): array
    {
        $status = $this->firstNonEmpty($publicCanonicalRoute['status'] ?? null);
        $candidatePath = $this->firstNonEmpty($publicCanonicalRoute['canonical_path'] ?? null)
            ?? $this->firstNonEmpty($publicCanonicalRoute['candidate_path_from_builder'] ?? null);
        $routePersisted = (bool)($publicCanonicalRoute['found'] ?? false);
        $statusActive = ($status === 'active');
        $routeEnabled = ((bool)($publicCanonicalRoute['route_enabled'] ?? false) === true);
        $canonicalEnabled = ((bool)($publicCanonicalRoute['canonical_enabled'] ?? false) === true);
        $robots = strtolower((string)($seo['robots'] ?? ''));
        $robotsIndexAllowed = ($robots !== '' && !str_contains($robots, 'noindex'));
        $seoRouterEnabled = false;
        $canonicalRendererEnabled = false;

        $blockingReasons = [];
        if (!$routePersisted) {
            $blockingReasons[] = 'canonical_route_not_persisted';
        }
        if (!$statusActive) {
            $blockingReasons[] = $status ? ('status_' . $status) : 'status_unknown';
        }
        if (!$routeEnabled) {
            $blockingReasons[] = 'route_disabled';
        }
        if (!$canonicalEnabled) {
            $blockingReasons[] = 'canonical_disabled';
        }
        if (!$robotsIndexAllowed) {
            $blockingReasons[] = 'robots_noindex_active';
        }
        if (!$seoRouterEnabled) {
            $blockingReasons[] = 'seo_router_not_implemented';
        }
        if (!$canonicalRendererEnabled) {
            $blockingReasons[] = 'canonical_renderer_not_enabled';
        }

        return [
            'source' => 'public_canonical_route',
            'version' => 'canonical-render-guard-v1',
            'enabled' => false,
            'can_render' => false,
            'candidate_path' => $candidatePath,
            'canonical_url' => null,
            'requires' => [
                'route_persisted' => $routePersisted,
                'status_active' => $statusActive,
                'route_enabled' => $routeEnabled,
                'canonical_enabled' => $canonicalEnabled,
                'robots_index_allowed' => $robotsIndexAllowed,
                'seo_router_enabled' => $seoRouterEnabled,
                'canonical_renderer_enabled' => $canonicalRendererEnabled,
            ],
            'blocking_reasons' => array_values(array_unique($blockingReasons)),
        ];
    }

    private function buildJsonLdRenderGuard(
        array $publicBreadcrumbs,
        array $canonicalRenderGuard,
        array $publicCanonicalRoute,
        array $seo
    ): array {
        $breadcrumbCandidate = is_array($publicBreadcrumbs['json_ld_candidate'] ?? null)
            ? $publicBreadcrumbs['json_ld_candidate']
            : [];
        $breadcrumbItems = is_array($breadcrumbCandidate['items'] ?? null)
            ? $breadcrumbCandidate['items']
            : [];
        $breadcrumbAvailable = ($breadcrumbCandidate !== [] && count($breadcrumbItems) > 0);
        $breadcrumbEnabled = (bool)($breadcrumbCandidate['enabled'] ?? false);
        $breadcrumbScriptRenderEnabled = (bool)($breadcrumbCandidate['script_render_enabled'] ?? false);
        $canonicalReady = (
            (bool)($publicCanonicalRoute['found'] ?? false)
            && (bool)($canonicalRenderGuard['can_render'] ?? false)
            && $this->firstNonEmpty($canonicalRenderGuard['canonical_url'] ?? null) !== null
        );
        $canonicalRenderEnabled = (bool)($canonicalRenderGuard['can_render'] ?? false);
        $routeEnabled = (bool)($publicCanonicalRoute['route_enabled'] ?? false);
        $robots = strtolower((string)($seo['robots'] ?? ''));
        $robotsIndexAllowed = ($robots !== '' && !str_contains($robots, 'noindex'));
        $jsonLdRendererEnabled = false;

        $blockingReasons = [];
        if (!$canonicalReady) {
            $blockingReasons[] = 'canonical_not_ready';
        }
        if (!$canonicalRenderEnabled) {
            $blockingReasons[] = 'canonical_render_disabled';
        }
        if (!$routeEnabled) {
            $blockingReasons[] = 'route_disabled';
        }
        if (!$robotsIndexAllowed) {
            $blockingReasons[] = 'robots_noindex_active';
        }
        if (!$breadcrumbEnabled) {
            $blockingReasons[] = 'breadcrumb_jsonld_disabled';
        }
        if (!$jsonLdRendererEnabled) {
            $blockingReasons[] = 'jsonld_renderer_not_enabled';
        }

        return [
            'source' => 'public_breadcrumbs',
            'version' => 'jsonld-render-guard-v1',
            'enabled' => false,
            'can_render' => false,
            'json_ld' => null,
            'script_render_enabled' => false,
            'candidate_sources' => [
                'breadcrumb_list' => [
                    'available' => $breadcrumbAvailable,
                    'source' => 'public_breadcrumbs.json_ld_candidate',
                    'enabled' => $breadcrumbEnabled,
                    'script_render_enabled' => $breadcrumbScriptRenderEnabled,
                    'item_count' => count($breadcrumbItems),
                ],
                'profile' => [
                    'available' => false,
                    'reason' => 'profile_jsonld_not_implemented',
                ],
            ],
            'requires' => [
                'canonical_ready' => $canonicalReady,
                'canonical_render_enabled' => $canonicalRenderEnabled,
                'route_enabled' => $routeEnabled,
                'robots_index_allowed' => $robotsIndexAllowed,
                'breadcrumb_jsonld_enabled' => $breadcrumbEnabled,
                'jsonld_renderer_enabled' => $jsonLdRendererEnabled,
            ],
            'blocking_reasons' => array_values(array_unique($blockingReasons)),
        ];
    }

    private function buildPublicRouteGuard(
        array $publicUrlContext,
        array $publicCanonicalRoute,
        array $canonicalRenderGuard,
        array $jsonLdRenderGuard,
        array $seo
    ): array {
        $status = $this->firstNonEmpty($publicCanonicalRoute['status'] ?? null);
        $candidatePath = $this->firstNonEmpty($publicCanonicalRoute['canonical_path'] ?? null)
            ?? $this->firstNonEmpty($publicUrlContext['profile']['fallback_candidate_url'] ?? null);
        $currentUrl = $this->firstNonEmpty($publicUrlContext['profile']['current_url'] ?? null);
        $routeGeneration = $this->firstNonEmpty($publicUrlContext['route_generation'] ?? null) ?? 'candidate_only';
        $routePersisted = (bool)($publicCanonicalRoute['found'] ?? false);
        $statusActive = ($status === 'active');
        $routeEnabled = (
            (bool)($publicUrlContext['route_enabled'] ?? false)
            && (bool)($publicCanonicalRoute['route_enabled'] ?? false)
            && (bool)($publicCanonicalRoute['can_route'] ?? false)
        );
        $seoRouterEnabled = false;
        $canonicalReady = (
            (bool)($canonicalRenderGuard['can_render'] ?? false)
            && $this->firstNonEmpty($canonicalRenderGuard['canonical_url'] ?? null) !== null
        );
        $robots = strtolower((string)($seo['robots'] ?? ''));
        $robotsIndexAllowed = ($robots !== '' && !str_contains($robots, 'noindex'));
        $redirectPolicyReady = false;

        $blockingReasons = [];
        if (!$routePersisted) {
            $blockingReasons[] = 'canonical_route_not_persisted';
        }
        if (!$statusActive) {
            $blockingReasons[] = $status ? ('status_' . $status) : 'status_unknown';
        }
        if (!$routeEnabled) {
            $blockingReasons[] = 'route_disabled';
        }
        if (!$seoRouterEnabled) {
            $blockingReasons[] = 'seo_router_not_implemented';
        }
        if (!$canonicalReady) {
            $blockingReasons[] = 'canonical_not_ready';
        }
        if (!$robotsIndexAllowed) {
            $blockingReasons[] = 'robots_noindex_active';
        }
        if (!$redirectPolicyReady) {
            $blockingReasons[] = 'redirect_policy_not_implemented';
        }

        return [
            'source' => 'public_canonical_route',
            'version' => 'public-route-guard-v1',
            'enabled' => false,
            'can_route' => false,
            'route_url' => null,
            'route_type' => 'profile',
            'candidate_path' => $candidatePath,
            'current_url' => $currentUrl,
            'route_generation' => $routeGeneration,
            'requires' => [
                'route_persisted' => $routePersisted,
                'status_active' => $statusActive,
                'route_enabled' => $routeEnabled,
                'seo_router_enabled' => $seoRouterEnabled,
                'canonical_ready' => $canonicalReady,
                'robots_index_allowed' => $robotsIndexAllowed,
                'redirect_policy_ready' => $redirectPolicyReady,
            ],
            'blocking_reasons' => array_values(array_unique($blockingReasons)),
        ];
    }

    private function buildSeoActivationSummary(
        array $publicUrlContext,
        array $publicCanonicalRoute,
        array $publicBreadcrumbs,
        array $canonicalRenderGuard,
        array $jsonLdRenderGuard,
        array $publicRouteGuard,
        array $seo
    ): array {
        $robots = $this->firstNonEmpty($seo['robots'] ?? null) ?? 'noindex,nofollow';
        $robotsIndexAllowed = !str_contains(strtolower($robots), 'noindex');
        $routeEnabled = (bool)($publicRouteGuard['enabled'] ?? false);
        $canRoute = (
            (bool)($publicRouteGuard['can_route'] ?? false)
            && (bool)($publicCanonicalRoute['can_route'] ?? false)
        );
        $canonicalEnabled = (bool)($canonicalRenderGuard['enabled'] ?? false);
        $canRenderCanonical = (bool)($canonicalRenderGuard['can_render'] ?? false);
        $jsonLdEnabled = (bool)($jsonLdRenderGuard['enabled'] ?? false);
        $canRenderJsonLd = (bool)($jsonLdRenderGuard['can_render'] ?? false);
        $isPublicRouteActive = ($routeEnabled && $canRoute);
        $isCanonicalActive = ($canonicalEnabled && $canRenderCanonical);
        $isJsonLdActive = ($jsonLdEnabled && $canRenderJsonLd);

        $blockingReasons = [];
        if (!$isPublicRouteActive) {
            $blockingReasons[] = 'public_route_not_active';
        }
        if (!$isCanonicalActive) {
            $blockingReasons[] = 'canonical_not_active';
        }
        if (!$isJsonLdActive) {
            $blockingReasons[] = 'json_ld_not_active';
        }
        if (!$robotsIndexAllowed) {
            $blockingReasons[] = 'robots_noindex_active';
        }
        if (!(bool)(($publicRouteGuard['requires']['seo_router_enabled'] ?? false))) {
            $blockingReasons[] = 'seo_router_not_implemented';
        }

        return [
            'source' => 'seo_activation_guards',
            'version' => 'seo-activation-summary-v1',
            'overall_state' => 'not_active',
            'is_indexable' => false,
            'is_public_route_active' => false,
            'is_canonical_active' => false,
            'is_json_ld_active' => false,
            'robots' => $robots,
            'current_url' => $this->firstNonEmpty($publicUrlContext['profile']['current_url'] ?? null),
            'candidate_route' => $this->firstNonEmpty($publicRouteGuard['candidate_path'] ?? null),
            'active_url' => null,
            'components' => [
                'route' => [
                    'guard' => 'public_route_guard',
                    'state' => 'blocked',
                    'enabled' => $routeEnabled,
                    'can_route' => $canRoute,
                ],
                'canonical' => [
                    'guard' => 'canonical_render_guard',
                    'state' => 'blocked',
                    'enabled' => $canonicalEnabled,
                    'can_render' => $canRenderCanonical,
                ],
                'json_ld' => [
                    'guard' => 'json_ld_render_guard',
                    'state' => 'blocked',
                    'enabled' => $jsonLdEnabled,
                    'can_render' => $canRenderJsonLd,
                ],
                'breadcrumbs' => [
                    'visual_render_enabled' => (bool)($publicBreadcrumbs['render_enabled'] ?? false),
                    'json_ld_enabled' => (bool)($publicBreadcrumbs['json_ld_enabled'] ?? false),
                    'route_enabled' => (bool)($publicBreadcrumbs['route_enabled'] ?? false),
                ],
            ],
            'blocking_reasons' => array_values(array_unique($blockingReasons)),
        ];
    }

    private function buildPublicBreadcrumbs(array $publicUrlContext, array $geoContext = []): array
    {
        $sourceItems = is_array($publicUrlContext['breadcrumbs'] ?? null)
            ? array_values(array_filter($publicUrlContext['breadcrumbs'], static fn($item): bool => is_array($item)))
            : [];
        $lastIndex = count($sourceItems) - 1;
        $items = [];
        $stateSlug = $this->firstNonEmpty($geoContext['state_slug'] ?? null);
        $citySlug = $this->firstNonEmpty($geoContext['city_slug'] ?? null);
        $canCompareGeoSlugs = ($stateSlug !== null && $citySlug !== null);
        $sameGeoBySlug = ($canCompareGeoSlugs && $stateSlug === $citySlug);
        $deduplicatedSameGeo = false;

        foreach ($sourceItems as $index => $item) {
            $label = $this->firstNonEmpty($item['label'] ?? null);
            if ($label === null) {
                continue;
            }

            $previousLabel = $items !== [] ? $items[count($items) - 1]['label'] : null;
            $sameLabelAsPrevious = (
                $previousLabel !== null
                && $this->slugifyPublicUrlText($previousLabel) === $this->slugifyPublicUrlText($label)
            );
            $shouldSkipDuplicateCity = (
                $index === 2
                && (
                    ($canCompareGeoSlugs && $sameGeoBySlug)
                    || (!$canCompareGeoSlugs && $sameLabelAsPrevious)
                )
            );

            if ($shouldSkipDuplicateCity) {
                $deduplicatedSameGeo = true;
                continue;
            }

            $items[] = [
                'label' => $label,
                'candidate_url' => $this->firstNonEmpty($item['candidate_url'] ?? null),
                'url' => null,
                'route_enabled' => false,
                'is_current' => array_key_exists('is_current', $item) ? (bool)$item['is_current'] : ($index === $lastIndex),
                'position' => count($items) + 1,
            ];
        }

        $warnings = [
            'seo_routes_not_implemented',
            'canonical_pending',
            'route_disabled',
            'json_ld_not_enabled',
        ];
        $contextWarnings = is_array($publicUrlContext['warnings'] ?? null) ? $publicUrlContext['warnings'] : [];
        foreach ($contextWarnings as $warning) {
            $warningText = $this->firstNonEmpty($warning);
            if ($warningText !== null) {
                $this->appendWarning($warnings, $warningText);
            }
        }
        if ($deduplicatedSameGeo) {
            $this->appendWarning($warnings, 'same_geo_breadcrumb_deduplicated');
        }

        return [
            'source' => 'public_url_context',
            'version' => 'breadcrumb-v1',
            'display_policy' => $deduplicatedSameGeo ? 'deduplicate_same_geo' : 'standard_geo_hierarchy',
            'render_enabled' => true,
            'json_ld_enabled' => false,
            'route_enabled' => false,
            'items' => $items,
            'json_ld_candidate' => $this->buildBreadcrumbJsonLdCandidate($items),
            'warnings' => $warnings,
        ];
    }

    private function buildBreadcrumbJsonLdCandidate(array $breadcrumbItems): array
    {
        $items = [];
        foreach ($breadcrumbItems as $index => $breadcrumbItem) {
            if (!is_array($breadcrumbItem)) {
                continue;
            }

            $name = $this->firstNonEmpty($breadcrumbItem['label'] ?? null);
            if ($name === null) {
                continue;
            }

            $position = (int)($breadcrumbItem['position'] ?? 0);
            if ($position < 1) {
                $position = count($items) + 1;
            }

            $items[] = [
                'position' => $position,
                'name' => $name,
                'candidate_item' => $this->firstNonEmpty($breadcrumbItem['candidate_url'] ?? null),
                'item' => null,
                'route_enabled' => false,
            ];
        }

        return [
            'source' => 'public_breadcrumbs',
            'version' => 'breadcrumb-jsonld-candidate-v1',
            'enabled' => false,
            'script_render_enabled' => false,
            'schema_type' => 'BreadcrumbList',
            'context' => 'https://schema.org',
            'items' => $items,
            'warnings' => [
                'json_ld_not_enabled',
                'json_ld_script_not_rendered',
                'route_disabled',
                'canonical_pending',
                'seo_routes_not_implemented',
            ],
        ];
    }

    private function buildListingUrlCandidates(array $publicNavigationTaxonomy, ?string $stateSlug, ?string $citySlug): array
    {
        $sections = (array)($publicNavigationTaxonomy['sections'] ?? []);
        $hasGeoPath = ($stateSlug !== null && $citySlug !== null);
        $listingCandidates = [];

        foreach ($sections as $section) {
            if (!is_array($section) || !(bool)($section['enabled'] ?? false)) {
                continue;
            }

            $sectionKey = $this->firstNonEmpty($section['key'] ?? null);
            $sectionLabel = $this->firstNonEmpty($section['label'] ?? null);
            $items = is_array($section['items'] ?? null) ? $section['items'] : [];
            foreach ($items as $item) {
                if (!is_array($item) || !(bool)($item['enabled'] ?? false)) {
                    continue;
                }

                $itemSlug = $this->firstNonEmpty($item['slug'] ?? null);
                $listingCandidates[] = [
                    'section_key' => $sectionKey,
                    'section_label' => $sectionLabel,
                    'label' => $this->firstNonEmpty($item['label'] ?? null),
                    'slug' => $itemSlug,
                    'profile_type' => $this->firstNonEmpty($item['profile_type'] ?? null),
                    'candidate_url' => ($hasGeoPath && $itemSlug !== null)
                        ? $this->buildCandidatePath($stateSlug, $citySlug, $itemSlug)
                        : null,
                    'route_enabled' => false,
                    'source' => 'derived_public_url_builder',
                ];
            }
        }

        return $listingCandidates;
    }

    private function buildPublicUrlBreadcrumbCandidates(
        ?string $stateName,
        ?string $stateSlug,
        ?string $cityName,
        ?string $citySlug,
        ?string $listingLabel,
        ?string $listingCandidateUrl,
        ?string $displayName,
        ?string $profileCandidateUrl
    ): array {
        $breadcrumbs = [
            [
                'label' => 'México Médico',
                'candidate_url' => '/',
                'route_enabled' => false,
            ],
        ];

        if ($stateName !== null) {
            $breadcrumbs[] = [
                'label' => $stateName,
                'candidate_url' => $stateSlug !== null ? $this->buildCandidatePath($stateSlug) : null,
                'route_enabled' => false,
            ];
        }

        if ($cityName !== null) {
            $breadcrumbs[] = [
                'label' => $cityName,
                'candidate_url' => ($stateSlug !== null && $citySlug !== null)
                    ? $this->buildCandidatePath($stateSlug, $citySlug)
                    : null,
                'route_enabled' => false,
            ];
        }

        if ($listingLabel !== null) {
            $breadcrumbs[] = [
                'label' => $listingLabel,
                'candidate_url' => $listingCandidateUrl,
                'route_enabled' => false,
            ];
        }

        if ($displayName !== null) {
            $breadcrumbs[] = [
                'label' => $displayName,
                'candidate_url' => $profileCandidateUrl,
                'route_enabled' => false,
                'is_current' => true,
            ];
        }

        return $breadcrumbs;
    }

    private function resolvePrimarySpecialtyForUrl(array $specialties): array
    {
        $fallback = [];
        foreach ($specialties as $specialty) {
            if (!is_array($specialty)) {
                continue;
            }
            $name = $this->firstNonEmpty($specialty['name_es'] ?? null);
            if ($name === null) {
                continue;
            }
            $candidate = [
                'name' => $name,
                'slug' => $this->firstNonEmpty($specialty['slug'] ?? null) ?? $this->slugifyPublicUrlText($name),
                'name_plural_es' => $this->firstNonEmpty($specialty['name_plural_es'] ?? null),
            ];
            if ((bool)($specialty['is_primary'] ?? false)) {
                return $candidate;
            }
            if ($fallback === []) {
                $fallback = $candidate;
            }
        }

        return $fallback;
    }

    private function findNavigationItemForSpecialty(array $publicNavigationTaxonomy, ?string $specialtyName, ?string $specialtySlug): array
    {
        $specialtyNameSlug = $specialtyName !== null ? $this->slugifyPublicUrlText($specialtyName) : null;
        $sections = (array)($publicNavigationTaxonomy['sections'] ?? []);
        foreach ($sections as $section) {
            if (!is_array($section) || (string)($section['key'] ?? '') !== 'medical_specialists') {
                continue;
            }
            $items = is_array($section['items'] ?? null) ? $section['items'] : [];
            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $itemLabel = $this->firstNonEmpty($item['label'] ?? null);
                $itemSlug = $this->firstNonEmpty($item['slug'] ?? null);
                $itemLabelSlug = $itemLabel !== null ? $this->slugifyPublicUrlText($itemLabel) : null;
                if (
                    ($specialtyNameSlug !== null && $itemLabelSlug === $specialtyNameSlug)
                    || ($specialtySlug !== null && $itemLabelSlug === $specialtySlug)
                    || ($specialtySlug !== null && $itemSlug === $specialtySlug)
                ) {
                    return [
                        'label' => $itemLabel,
                        'slug' => $itemSlug,
                    ];
                }
            }
        }

        return [];
    }

    private function resolveListingLabel(?string $label, ?string $slug): ?string
    {
        $slugMap = [
            'cardiologos' => 'Cardiólogos',
            'ginecologos' => 'Ginecólogos',
            'pediatras' => 'Pediatras',
            'dermatologos' => 'Dermatólogos',
            'otorrinolaringologos' => 'Otorrinolaringólogos',
            'endocrinologos' => 'Endocrinólogos',
            'traumatologos-ortopedistas' => 'Traumatólogos y ortopedistas',
            'neurologos' => 'Neurólogos',
            'oftalmologos' => 'Oftalmólogos',
            'urologos' => 'Urólogos',
        ];

        if ($slug !== null && isset($slugMap[$slug])) {
            return $slugMap[$slug];
        }

        return $label;
    }

    private function deriveSingularSpecialtySlug(?string $specialtyName, ?string $specialtySlug, ?string $gender): array
    {
        $key = $specialtyName !== null ? $this->slugifyPublicUrlText($specialtyName) : null;
        if ($key === null) {
            $key = $specialtySlug;
        }

        $map = [
            'cardiologia' => ['masculine' => 'cardiologo', 'feminine' => 'cardiologa'],
            'ginecologia' => ['masculine' => 'ginecologo', 'feminine' => 'ginecologa'],
            'pediatria' => ['neutral' => 'pediatra'],
            'dermatologia' => ['masculine' => 'dermatologo', 'feminine' => 'dermatologa'],
            'otorrinolaringologia' => ['masculine' => 'otorrinolaringologo', 'feminine' => 'otorrinolaringologa'],
            'endocrinologia' => ['masculine' => 'endocrinologo', 'feminine' => 'endocrinologa'],
            'neurologia' => ['masculine' => 'neurologo', 'feminine' => 'neurologa'],
            'oftalmologia' => ['masculine' => 'oftalmologo', 'feminine' => 'oftalmologa'],
            'urologia' => ['masculine' => 'urologo', 'feminine' => 'urologa'],
        ];

        if ($key === null || !isset($map[$key])) {
            return ['slug' => null, 'is_gendered' => false];
        }

        $entry = $map[$key];
        if (isset($entry['neutral'])) {
            return ['slug' => $entry['neutral'], 'is_gendered' => false];
        }

        if ($gender === 'feminine' && isset($entry['feminine'])) {
            return ['slug' => $entry['feminine'], 'is_gendered' => true];
        }

        return ['slug' => $entry['masculine'] ?? null, 'is_gendered' => true];
    }

    private function resolveProfileGenderForUrl(array $identity, ?string $displayName): ?string
    {
        $genderLabel = $this->firstNonEmpty($identity['gender_label'] ?? null);
        if ($genderLabel !== null) {
            $genderKey = $this->slugifyPublicUrlText($genderLabel);
            if (in_array($genderKey, ['femenino', 'mujer', 'female'], true)) {
                return 'feminine';
            }
            if (in_array($genderKey, ['masculino', 'hombre', 'male'], true)) {
                return 'masculine';
            }
        }

        $prefix = $this->firstNonEmpty($identity['prefix'] ?? null);
        $prefixKey = $prefix !== null ? $this->slugifyPublicUrlText($prefix) : '';
        if (in_array($prefixKey, ['dra', 'doctora'], true)) {
            return 'feminine';
        }
        if (in_array($prefixKey, ['dr', 'doctor'], true)) {
            return 'masculine';
        }

        $nameKey = $displayName !== null ? $this->slugifyPublicUrlText($displayName) : '';
        if (strpos($nameKey, 'dra-') === 0 || strpos($nameKey, 'doctora-') === 0) {
            return 'feminine';
        }
        if (strpos($nameKey, 'dr-') === 0 || strpos($nameKey, 'doctor-') === 0) {
            return 'masculine';
        }

        return null;
    }

    private function slugifyPublicUrlText(?string $value): ?string
    {
        return $this->slugifyGeoText($value);
    }

    private function buildCandidatePath(?string ...$segments): ?string
    {
        $clean = [];
        foreach ($segments as $segment) {
            $part = $this->firstNonEmpty($segment);
            if ($part === null) {
                return null;
            }
            $clean[] = trim($part, '/');
        }

        return '/' . implode('/', $clean);
    }

    private function appendWarning(array &$warnings, string $warning): void
    {
        if (!in_array($warning, $warnings, true)) {
            $warnings[] = $warning;
        }
    }

    private function buildGeoContext(array $consultorios): array
    {
        $sourceConsultorio = $this->firstGeoConsultorio($consultorios);
        if ($sourceConsultorio === null) {
            return [
                'country_label' => 'México',
                'state_name' => null,
                'state_slug' => null,
                'city_name' => null,
                'city_slug' => null,
                'source' => 'national_default',
                'source_consultorio_id' => null,
                'is_national' => true,
                'available_locations' => [],
            ];
        }

        $stateName = $this->firstNonEmpty($sourceConsultorio['state'] ?? null);
        $cityName = $this->resolveConsultorioCity($sourceConsultorio);

        return [
            'country_label' => 'México',
            'state_name' => $stateName,
            'state_slug' => $this->slugifyGeoText($stateName),
            'city_name' => $cityName,
            'city_slug' => $this->slugifyGeoText($cityName),
            'source' => 'profile_consultorio_primary',
            'source_consultorio_id' => $this->firstNonEmpty($sourceConsultorio['consultorio_id'] ?? null),
            'is_national' => false,
            'available_locations' => $this->buildAvailableGeoLocations($consultorios),
        ];
    }

    private function firstGeoConsultorio(array $consultorios): ?array
    {
        foreach ($consultorios as $consultorio) {
            if (!is_array($consultorio)) {
                continue;
            }
            if (!(bool)($consultorio['is_public'] ?? false) || !(bool)($consultorio['is_active'] ?? false)) {
                continue;
            }
            if ($this->firstNonEmpty($consultorio['state'] ?? null) !== null || $this->resolveConsultorioCity($consultorio) !== null) {
                return $consultorio;
            }
        }
        return null;
    }

    private function buildAvailableGeoLocations(array $consultorios): array
    {
        $grouped = [];
        foreach ($consultorios as $consultorio) {
            if (!is_array($consultorio)) {
                continue;
            }
            if (!(bool)($consultorio['is_public'] ?? false) || !(bool)($consultorio['is_active'] ?? false)) {
                continue;
            }

            $stateName = $this->firstNonEmpty($consultorio['state'] ?? null);
            $cityName = $this->resolveConsultorioCity($consultorio);
            if ($stateName === null && $cityName === null) {
                continue;
            }

            $stateSlug = $this->slugifyGeoText($stateName);
            $citySlug = $this->slugifyGeoText($cityName);
            $key = ($stateSlug ?? '_') . '|' . ($citySlug ?? '_');
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'state_name' => $stateName,
                    'state_slug' => $stateSlug,
                    'city_name' => $cityName,
                    'city_slug' => $citySlug,
                    'consultorio_ids' => [],
                ];
            }

            $consultorioId = $this->firstNonEmpty($consultorio['consultorio_id'] ?? null);
            if ($consultorioId !== null && !in_array($consultorioId, $grouped[$key]['consultorio_ids'], true)) {
                $grouped[$key]['consultorio_ids'][] = $consultorioId;
            }
        }

        return array_values($grouped);
    }

    private function resolveConsultorioCity(array $consultorio): ?string
    {
        return $this->firstNonEmpty($consultorio['city'] ?? null)
            ?? $this->firstNonEmpty($consultorio['municipality'] ?? null);
    }

    private function slugifyGeoText(?string $value): ?string
    {
        $text = $this->firstNonEmpty($value);
        if ($text === null) {
            return null;
        }

        $text = strtr($text, [
            'Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ä' => 'A', 'Ã' => 'A', 'Å' => 'A',
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a',
            'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'Ó' => 'O', 'Ò' => 'O', 'Ô' => 'O', 'Ö' => 'O', 'Õ' => 'O',
            'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
            'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'Ñ' => 'N', 'ñ' => 'n', 'Ç' => 'C', 'ç' => 'c',
        ]);
        $text = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
        $text = trim($text, '-');

        return $text === '' ? null : $text;
    }

    private function buildPublicContactPayload(array $rows, string $planCode): array
    {
        $contact = [
            'phone' => null,
            'whatsapp' => null,
            'email' => null,
            'has_public_contact' => false,
            'has_public_phone' => false,
            'has_public_whatsapp' => false,
            'has_public_email' => false,
            'source' => 'doctor_contact_points',
        ];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $visibilityPlanMin = $this->firstNonEmpty($row['visibility_plan_min'] ?? null);
            if ($visibilityPlanMin !== null && !PublicProfilePlanCapabilities::planMeetsMinimum($planCode, $visibilityPlanMin)) {
                continue;
            }

            $type = strtolower(trim((string)($row['type'] ?? '')));
            if (!in_array($type, ['phone', 'whatsapp', 'email'], true)) {
                continue;
            }
            if (!$this->isPublicContactRow($row)) {
                continue;
            }

            $value = $this->sanitizePublicContactValue($type, $row);
            if ($value === null) {
                continue;
            }

            if ($type === 'phone' && $contact['phone'] === null) {
                $contact['phone'] = $value;
                $contact['has_public_phone'] = true;
            } elseif ($type === 'whatsapp' && $contact['whatsapp'] === null) {
                $contact['whatsapp'] = $value;
                $contact['has_public_whatsapp'] = true;
            } elseif ($type === 'email' && $contact['email'] === null) {
                $contact['email'] = $value;
                $contact['has_public_email'] = true;
            }
        }

        $contact['has_public_contact'] = (
            (bool)$contact['has_public_phone']
            || (bool)$contact['has_public_whatsapp']
            || (bool)$contact['has_public_email']
        );
        return $contact;
    }

    private function mergeContactCapabilities(array $base, array $publicContact, array $publicVisibility): array
    {
        $showContactButtons = (bool)($publicVisibility['show_contact_buttons'] ?? false);
        $showPhone = $showContactButtons && (bool)($publicVisibility['show_phone'] ?? false) && (bool)($publicContact['has_public_phone'] ?? false);
        $showWhatsapp = $showContactButtons && (bool)($publicVisibility['show_whatsapp'] ?? false) && (bool)($publicContact['has_public_whatsapp'] ?? false);
        $showEmail = $showContactButtons && (bool)($publicContact['has_public_email'] ?? false);
        $hasPublicContact = $showPhone || $showWhatsapp || $showEmail;

        $base['show_contact_buttons'] = $hasPublicContact;
        $base['phone'] = $showPhone ? ($publicContact['phone'] ?? null) : null;
        $base['whatsapp'] = $showWhatsapp ? ($publicContact['whatsapp'] ?? null) : null;
        $base['email'] = $showEmail ? ($publicContact['email'] ?? null) : null;
        $base['has_public_contact'] = $hasPublicContact;
        $base['has_public_phone'] = $showPhone;
        $base['has_public_whatsapp'] = $showWhatsapp;
        $base['has_public_email'] = $showEmail;
        $base['source'] = 'doctor_contact_points';
        if (!$hasPublicContact && ($base['contact_restriction_reason'] ?? null) === null) {
            $base['contact_restriction_reason'] = 'no_public_contact';
        }
        return $base;
    }

    private function isPublicContactRow(array $row): bool
    {
        $scope = strtolower(trim((string)($row['scope'] ?? '')));
        return ((int)($row['is_public'] ?? 0) === 1)
            && ((int)($row['use_for_public_profile'] ?? 0) === 1)
            && ((int)($row['use_for_security'] ?? 0) === 0)
            && ((int)($row['use_for_platform_admin'] ?? 0) === 0)
            && strtolower(trim((string)($row['status'] ?? ''))) === 'active'
            && in_array($scope, ['public', 'public_profile'], true);
    }

    private function sanitizePublicContactValue(string $type, array $row): ?string
    {
        if ($type === 'email') {
            $email = strtolower(trim((string)($row['normalized_value'] ?? $row['value'] ?? '')));
            return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
        }

        $raw = trim((string)($row['normalized_value'] ?? ''));
        if ($raw === '') {
            $raw = trim((string)($row['value'] ?? ''));
        }
        $startsWithPlus = str_starts_with($raw, '+');
        $digits = preg_replace('/\D/', '', $raw);
        if (!is_string($digits) || strlen($digits) < 7 || strlen($digits) > 16) {
            return null;
        }
        return $startsWithPlus ? '+' . $digits : $digits;
    }

    private function buildPublicConsultorioAddress(array $row, array $mapPayload): ?string
    {
        $street = $this->firstNonEmpty($row['calle'] ?? null);
        $numExt = $this->formatPublicNumExt($row['num_ext'] ?? null);
        $streetWithNumber = trim((string)($street ?? '') . ($numExt !== '' ? ' ' . $numExt : ''));

        $parts = [
            $streetWithNumber,
            $this->firstNonEmpty($row['colonia'] ?? null),
            $this->firstNonEmpty($row['cp'] ?? null),
            $this->firstNonEmpty($row['municipio'] ?? null),
            $this->firstNonEmpty($row['estado'] ?? null),
            'México',
        ];
        $parts = array_values(array_filter($parts, static fn($part): bool => trim((string)$part) !== ''));
        if (!empty($parts)) {
            return implode(', ', $parts);
        }

        return $this->firstNonEmpty($mapPayload['address_compact'] ?? null);
    }

    private function formatPublicNumExt($value): string
    {
        $numExt = trim((string)($value ?? ''));
        if ($numExt === '') {
            return '';
        }

        $numExt = preg_replace('/^#+\s*/', '#', $numExt) ?? $numExt;
        return str_starts_with($numExt, '#') ? $numExt : '#' . $numExt;
    }

    private function buildSchedule(array $rows): array
    {
        $byConsultorio = $this->groupScheduleByConsultorio($rows);
        $byDay = [];
        for ($w = 1; $w <= 7; $w++) {
            $byDay[(string)$w] = [];
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $weekday = (int)($row['weekday'] ?? 0);
            if ($weekday < 1 || $weekday > 7) {
                continue;
            }
            $byDay[(string)$weekday][] = [
                'consultorio_id' => trim((string)($row['consultorio_id'] ?? '')),
                'start_time' => trim((string)($row['start_time'] ?? '')),
                'end_time' => trim((string)($row['end_time'] ?? '')),
            ];
        }

        return [
            'timezone' => 'America/Mexico_City',
            'source' => empty($rows) ? 'none' : 'schedule',
            'by_day' => $byDay,
            'by_consultorio' => $byConsultorio,
            'public_notes' => null,
            'hide_days_without_availability' => true,
        ];
    }

    private function groupScheduleByConsultorio(array $rows): array
    {
        $grouped = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $consultorioId = trim((string)($row['consultorio_id'] ?? ''));
            $weekday = (int)($row['weekday'] ?? 0);
            $start = trim((string)($row['start_time'] ?? ''));
            $end = trim((string)($row['end_time'] ?? ''));
            if ($consultorioId === '' || $weekday < 1 || $weekday > 7 || $start === '' || $end === '') {
                continue;
            }
            if (!isset($grouped[$consultorioId])) {
                $grouped[$consultorioId] = [];
            }
            if (!isset($grouped[$consultorioId][(string)$weekday])) {
                $grouped[$consultorioId][(string)$weekday] = [];
            }
            $grouped[$consultorioId][(string)$weekday][] = [
                'start_time' => $start,
                'end_time' => $end,
            ];
        }
        return $grouped;
    }

    private function buildScheduleSummary(array $windows): array
    {
        $summary = [];
        foreach ($windows as $weekday => $slots) {
            if (!is_array($slots) || empty($slots)) {
                continue;
            }
            $summary[] = [
                'weekday' => (int)$weekday,
                'windows' => array_values(array_map(static function (array $slot): array {
                    return [
                        'start_time' => trim((string)($slot['start_time'] ?? '')),
                        'end_time' => trim((string)($slot['end_time'] ?? '')),
                    ];
                }, $slots)),
            ];
        }
        return $summary;
    }

    private function decodeJsonArray($value): array
    {
        if (is_array($value)) {
            return array_values($value);
        }
        if (!is_string($value) || trim($value) === '') {
            return [];
        }
        $decoded = json_decode($value, true);
        if (!is_array($decoded)) {
            return [];
        }
        $out = [];
        foreach ($decoded as $item) {
            $text = trim((string)$item);
            if ($text !== '') {
                $out[] = $text;
            }
        }
        return array_values($out);
    }

    private function sanitizeSpecialties(array $specialties): array
    {
        $clean = [];
        foreach ($specialties as $row) {
            if (!is_array($row)) {
                continue;
            }
            $name = $this->firstNonEmpty($row['name_es'] ?? null);
            if ($name === null) {
                continue;
            }
            $clean[] = [
                'specialty_id' => $this->firstNonEmpty($row['specialty_id'] ?? null),
                'name_es' => $name,
                'name_plural_es' => $this->firstNonEmpty($row['name_plural_es'] ?? null),
                'slug' => $this->firstNonEmpty($row['slug'] ?? null),
                'schema_medical_specialty' => $this->firstNonEmpty($row['schema_medical_specialty'] ?? null),
                'is_primary' => (bool)($row['is_primary'] ?? false),
            ];
        }
        return $clean;
    }

    private function normalizeProfileStatus($value): string
    {
        $status = strtolower(trim((string)($value ?? '')));
        $allowed = ['draft', 'pending_review', 'active', 'hidden', 'suspended', 'removed'];
        if (in_array($status, $allowed, true)) {
            return $status;
        }
        return 'hidden';
    }

    private function isValidDoctorId(string $doctorId): bool
    {
        if ($doctorId === '') {
            return false;
        }
        if (strlen($doctorId) > 64) {
            return false;
        }
        return (bool)preg_match('/^[A-Za-z0-9._:-]+$/', $doctorId);
    }

    private function firstNonEmpty($value): ?string
    {
        $v = trim((string)($value ?? ''));
        return $v === '' ? null : $v;
    }

    private function sanitizePublicLogoUrl($value): ?string
    {
        $logoUrl = $this->firstNonEmpty($value);
        if ($logoUrl === null) {
            return null;
        }

        if (preg_match('#^data:image/(?:png|jpe?g|gif|webp);base64,[A-Za-z0-9+/=\r\n]+$#i', $logoUrl) === 1) {
            return $logoUrl;
        }

        if (str_starts_with($logoUrl, '/') && !str_starts_with($logoUrl, '//')) {
            return $logoUrl;
        }

        $scheme = strtolower((string)(parse_url($logoUrl, PHP_URL_SCHEME) ?? ''));
        if (in_array($scheme, ['http', 'https'], true) && filter_var($logoUrl, FILTER_VALIDATE_URL) !== false) {
            return $logoUrl;
        }

        return null;
    }

    private function error(string $code, string $message): array
    {
        return [
            'ok' => false,
            'error' => $code,
            'message' => $message,
            'data' => null,
            'meta' => [
                'contract' => 'profile_public_mvp',
                'version' => 'PP-7D',
                'generated_at' => gmdate('c'),
            ],
        ];
    }
}
