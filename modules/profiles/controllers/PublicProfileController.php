<?php
declare(strict_types=1);

namespace Profiles\Controllers;

use Profiles\Repositories\PublicProfileRepository;
use function Agenda\Helpers\ConsultorioMap\buildConsultorioPublicMapPayload;

require_once __DIR__ . '/../repositories/PublicProfileRepository.php';
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
        $scheduleRows = is_array($snapshot['schedule_rows'] ?? null) ? $snapshot['schedule_rows'] : [];

        $plan = [
            'plan_code' => 'free',
            'plan_label' => 'Gratuito',
            'is_paid' => false,
            'is_active' => true,
            'expires_at' => null,
            'grace_status' => null,
            'features' => [
                'contact' => false,
                'public_agenda' => false,
                'reviews' => false,
                'promotions' => false,
            ],
        ];

        $publicVisibility = [
            'show_contact_buttons' => false,
            'show_phone' => false,
            'show_whatsapp' => false,
            'show_internal_message' => false,
            'show_public_agenda' => false,
            'show_map_gps' => false,
            'show_reviews' => false,
            'show_promotions' => false,
            'show_claim_button' => false,
            'show_video_consultation' => false,
            'show_ai_claims' => false,
            'show_consultation_fee' => false,
            'show_accepted_insurances' => false,
        ];

        $consultorios = $this->mapConsultorios(
            is_array($snapshot['consultorios'] ?? null) ? $snapshot['consultorios'] : [],
            $scheduleRows,
            $publicVisibility
        );

        $schedule = $this->buildSchedule($scheduleRows);
        $hasMinimumPublicData = $this->hasMinimumPublicData($identity, $professional);
        $isPublic = $hasMinimumPublicData;
        $profileStatus = $hasMinimumPublicData ? 'active' : 'hidden';

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
                'created_origin' => null,
                'last_public_update_at' => null,
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
            'contact' => [
                'phone' => null,
                'whatsapp' => null,
                'internal_message_enabled' => false,
                'contact_cta_label' => null,
                'contact_restriction_reason' => 'source_not_ready',
            ],
            'agenda_public' => [
                'enabled' => false,
                'availability_endpoint' => '/api/agenda/index.php/public/availability',
                'booking_flow' => 'public_agenda_existing',
                'requires_otp' => true,
                'allowed_consultorios' => [],
                'allowed_modalities' => [],
                'blocked_by_plan_reason' => 'source_not_ready',
            ],
            'commercial_visibility' => [
                'consultation_fee' => null,
                'payment_methods' => [],
                'accepted_insurances' => [],
                'commercial_restriction_reason' => 'source_not_ready',
            ],
            'reviews' => [
                'enabled' => false,
                'visible' => false,
                'rating_avg' => null,
                'review_count' => 0,
                'reviews_preview' => [],
                'doctor_can_reply' => false,
                'doctor_can_archive' => false,
            ],
            'claim' => [
                'show_claim_button' => false,
                'claim_url' => null,
                'claim_status' => null,
                'claim_allowed' => false,
                'claim_blocked_reason' => 'source_not_ready',
            ],
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
            'feature_flags' => [
                'has_public_profile' => $isPublic,
                'has_public_contact' => false,
                'has_public_agenda' => false,
                'has_reviews' => false,
                'has_promotions' => false,
                'has_video_consultation' => false,
                'has_ai_agent' => false,
                'has_ai_profile_writer' => false,
                'has_ai_prescription_safety' => false,
                'has_commercial_profile_data' => false,
                'has_insurance_affiliations' => false,
                'has_ecosystem_links' => false,
            ],
        ];

        return [
            'ok' => true,
            'error' => null,
            'message' => '',
            'data' => $data,
            'meta' => [
                'contract' => 'profile_public_mvp',
                'version' => 'PP-4B',
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
            $phones = $this->decodeJsonArray($row['telefonos_json'] ?? null);
            $phonePublic = null;
            if ((bool)($publicVisibility['show_phone'] ?? false) && !empty($phones)) {
                $phonePublic = trim((string)$phones[0]);
                if ($phonePublic === '') {
                    $phonePublic = null;
                }
            }
            $whatsappPublic = null;
            if ((bool)($publicVisibility['show_whatsapp'] ?? false)) {
                $whatsappPublic = $this->firstNonEmpty($row['whatsapp'] ?? null);
            }

            $title = $this->firstNonEmpty($row['titulo'] ?? null);
            if ($title === null) {
                $title = 'Consultorio principal';
            }

            $windows = $scheduleByConsultorio[$consultorioId] ?? [];
            $summary = $this->buildScheduleSummary($windows);

            $mapped[] = [
                'consultorio_id' => $consultorioId,
                'public_name' => $title,
                'address' => $this->firstNonEmpty($mapPayload['address_compact'] ?? null),
                'city' => $this->firstNonEmpty($row['municipio'] ?? null),
                'state' => $this->firstNonEmpty($row['estado'] ?? null),
                'municipality' => $this->firstNonEmpty($row['municipio'] ?? null),
                'postal_code' => $this->firstNonEmpty($row['cp'] ?? null),
                'phone_public' => $phonePublic,
                'whatsapp_public' => $whatsappPublic,
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

    private function hasMinimumPublicData(array $identity, array $professional): bool
    {
        return $this->firstNonEmpty($identity['display_name'] ?? null) !== null
            && $this->firstNonEmpty($professional['professional_license'] ?? null) !== null;
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
                'version' => 'PP-4B',
                'generated_at' => gmdate('c'),
            ],
        ];
    }
}
