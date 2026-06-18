<?php
declare(strict_types=1);

namespace Profiles\Controllers;

use Profiles\Repositories\PublicProfileRepository;
use Profiles\Services\PublicProfilePlanCapabilities;
use function Agenda\Helpers\ConsultorioMap\buildConsultorioPublicMapPayload;

require_once __DIR__ . '/../repositories/PublicProfileRepository.php';
require_once __DIR__ . '/../services/PublicProfilePlanCapabilities.php';
require_once __DIR__ . '/../../agenda/helpers/consultorio_map.php';

final class PublicProfileController
{
    private PublicProfileRepository $repository;

    public function __construct(PublicProfileRepository $repository)
    {
        $this->repository = $repository;
    }

    public function showByDoctorId(string $doctorId): array
    {
        $doctorId = trim($doctorId);
        if (!$this->isValidDoctorId($doctorId)) {
            return $this->error('invalid_doctor_id', 'doctor_id invalid');
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
        $scheduleRows = is_array($snapshot['schedule_rows'] ?? null) ? $snapshot['schedule_rows'] : [];
        $publicContactPoints = is_array($snapshot['public_contact_points'] ?? null) ? $snapshot['public_contact_points'] : [];
        $publicContact = $this->buildPublicContactPayload(
            $publicContactPoints,
            PublicProfilePlanCapabilities::normalizePlanCode($planSource['plan_code'] ?? null)
        );

        $planContext = [
            'plan_source' => $planSource['source'] ?? 'default_free',
            'has_public_profile' => false,
            'is_claimed' => false,
            'public_contact_source_ready' => (bool)($publicContact['has_public_contact'] ?? false),
            'claim_source_ready' => false,
            'commercial_source_ready' => false,
        ];
        $planContract = PublicProfilePlanCapabilities::build($planSource['plan_code'] ?? null, $planContext);
        $publicVisibility = (array)$planContract['public_visibility'];

        $consultorios = $this->mapConsultorios(
            is_array($snapshot['consultorios'] ?? null) ? $snapshot['consultorios'] : [],
            $scheduleRows,
            $publicVisibility
        );

        $schedule = $this->buildSchedule($scheduleRows);
        $hasMinimumPublicData = $this->hasMinimumPublicData($identity, $professional, $specialties, $consultorios);
        $sourceStatus = $this->normalizeProfileStatus($profileSource['profile_status'] ?? null);
        $isPublicCandidate = (bool)($profileSource['is_public_candidate'] ?? false);
        $isPublic = $hasMinimumPublicData && $isPublicCandidate && $sourceStatus === 'active';
        $profileStatus = $isPublic ? 'active' : 'hidden';
        $planContext['has_public_profile'] = $isPublic;
        $planContract = PublicProfilePlanCapabilities::build($planSource['plan_code'] ?? null, $planContext);
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

        $data = [
            'profile' => [
                'profile_id' => null,
                'doctor_id' => $doctorId,
                'slug' => null,
                'canonical_url' => null,
                'profile_type' => 'doctor',
                'status' => $profileStatus,
                'ownership_status' => null,
                'is_claimed' => false,
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
                'logo_url' => $this->firstNonEmpty($identity['logo_url'] ?? null),
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
            'specialties' => $this->sanitizeSpecialties($specialties),
            'consultorios' => $consultorios,
            'schedule' => $schedule,
            'contact' => $contact,
            'agenda_public' => (array)$planContract['agenda_public'],
            'commercial_visibility' => (array)$planContract['commercial_visibility'],
            'reviews' => (array)$planContract['reviews'],
            'claim' => (array)$planContract['claim'],
            'seo' => [
                'title' => $title,
                'description' => $description,
                'h1' => $h1,
                'canonical_url' => null,
                'robots' => 'noindex,nofollow',
                'og_title' => $title,
                'og_description' => $description,
                'og_image' => $this->firstNonEmpty($identity['photo_url'] ?? null),
                'breadcrumb' => [],
            ],
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

            $windows = $scheduleByConsultorio[$consultorioId] ?? [];
            $summary = $this->buildScheduleSummary($windows);

            $mapped[] = [
                'consultorio_id' => $consultorioId,
                'public_name' => $title,
                'address' => $this->buildPublicConsultorioAddress($row, $mapPayload),
                'city' => $this->firstNonEmpty($row['municipio'] ?? null),
                'state' => $this->firstNonEmpty($row['estado'] ?? null),
                'municipality' => $this->firstNonEmpty($row['municipio'] ?? null),
                'postal_code' => $this->firstNonEmpty($row['cp'] ?? null),
                'phone_public' => null,
                'whatsapp_public' => null,
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

    private function hasMinimumPublicData(array $identity, array $professional, array $specialties, array $consultorios): bool
    {
        if ($this->firstNonEmpty($identity['display_name'] ?? null) === null) {
            return false;
        }
        if ($this->firstNonEmpty($professional['professional_license'] ?? null) === null) {
            return false;
        }
        if (!$this->hasPrimarySpecialty($professional, $specialties)) {
            return false;
        }
        return $this->hasPublicConsultorio($consultorios);
    }

    private function hasPrimarySpecialty(array $professional, array $specialties): bool
    {
        if ($this->firstNonEmpty($professional['specialty_primary'] ?? null) !== null) {
            return true;
        }
        foreach ($specialties as $specialty) {
            if (!is_array($specialty)) {
                continue;
            }
            if ($this->firstNonEmpty($specialty['name_es'] ?? null) !== null) {
                return true;
            }
        }
        return false;
    }

    private function hasPublicConsultorio(array $consultorios): bool
    {
        foreach ($consultorios as $consultorio) {
            if (!is_array($consultorio)) {
                continue;
            }
            $id = $this->firstNonEmpty($consultorio['consultorio_id'] ?? null);
            $isPublic = (bool)($consultorio['is_public'] ?? false);
            $isActive = (bool)($consultorio['is_active'] ?? false);
            if ($id !== null && $isPublic && $isActive) {
                return true;
            }
        }
        return false;
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
