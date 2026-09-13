<?php
declare(strict_types=1);

namespace Profiles\Controllers;

use Profiles\Repositories\PrivateProfileRepository;
use Profiles\Services\ProfileThemeCatalog;
use Profiles\Services\VerifiedPhysicianIdentityService;

require_once __DIR__ . '/../repositories/PrivateProfileRepository.php';
require_once __DIR__ . '/../services/ProfileThemeCatalog.php';
require_once __DIR__ . '/../services/VerifiedPhysicianIdentityService.php';
require_once __DIR__ . '/../services/VerifiedDoctorCredentialService.php';

final class PrivateProfileController
{
    private PrivateProfileRepository $repository;
    private ?VerifiedPhysicianIdentityService $verifiedIdentityService;

    private const EDITABLE_FIELDS = [
        'display_name',
        'professional_designation',
        'prefix',
        'gender',
        'gender_label',
        'bio_short',
        'profile_theme_key',
    ];

    private const BLOCKED_FIELDS = [
        'professional_license',
        'specialty_license',
        'specialty_primary',
        'specialty_secondary',
        'photo_url',
        'avatar_url',
        'logo_url',
        'profile_status',
        'is_public_candidate',
        'verified_identity',
        'given_names',
        'first_surname',
        'second_surname',
        'verified_at',
        'verified_by_account_id',
    ];

    public function __construct(
        PrivateProfileRepository $repository,
        ?VerifiedPhysicianIdentityService $verifiedIdentityService = null,
        private ?\Profiles\Services\VerifiedDoctorCredentialService $credentialService = null
    )
    {
        $this->repository = $repository;
        $this->verifiedIdentityService = $verifiedIdentityService;
    }

    public function showByDoctorId(string $doctorId, string $authMode = 'transitional_open'): array
    {
        $doctorId = trim($doctorId);
        if (!$this->isValidDoctorId($doctorId)) {
            return $this->error('invalid_doctor_id', 'doctor_id invalid', $authMode);
        }

        $row = $this->repository->fetchIdentity($doctorId);
        if (!is_array($row)) {
            return $this->error('profile_identity_not_found', 'profile identity not found', $authMode);
        }

        return $this->success($doctorId, $row, $authMode);
    }

    public function patchByDoctorId(string $doctorId, array $payload, string $authMode = 'transitional_open'): array
    {
        $doctorId = trim($doctorId);
        if (!$this->isValidDoctorId($doctorId)) {
            return $this->error('invalid_doctor_id', 'doctor_id invalid', $authMode);
        }
        if (!$this->isAssociative($payload)) {
            return $this->error('invalid_payload', 'payload object required', $authMode);
        }

        if (isset($payload['bio_short']) && (!is_scalar($payload['bio_short']) || mb_strlen(trim((string)$payload['bio_short']), 'UTF-8') > 150)) {
            return $this->error('validation_error', 'La Bio breve admite un máximo de 150 caracteres.', $authMode, ['field' => 'bio_short', 'max_characters' => 150]);
        }
        $prepared = $this->prepareEditablePayload($payload);
        if (!empty($prepared['unknown_fields'])) {
            return $this->error('invalid_payload', 'unsupported fields in payload', $authMode, [
                'unknown_fields' => array_values($prepared['unknown_fields']),
            ]);
        }
        if (array_key_exists('display_name', $prepared['editable']) && $this->verifiedIdentityService !== null) {
            $displayNameDecision = $this->verifiedIdentityService->validatePublicDisplayName(
                $doctorId,
                $prepared['editable']['display_name']
            );
            if (!($displayNameDecision['valid'] ?? false)) {
                return $this->error(
                    'invalid_public_display_name',
                    'El nombre público debe usar los nombres verificados seleccionados, conservar el primer apellido y mantener el orden oficial.',
                    $authMode,
                    ['field' => 'display_name']
                );
            }
            $prepared['editable']['display_name'] = $displayNameDecision['canonical_display_name'];
        }
        if (empty($prepared['editable'])) {
            $row = $this->repository->fetchIdentity($doctorId);
            if (!is_array($row)) {
                return $this->error('profile_identity_not_found', 'profile identity not found', $authMode, [
                    'blocked_fields_ignored' => array_values($prepared['blocked_fields']),
                    'editable_fields_applied' => [],
                    'no_editable_fields_applied' => true,
                ]);
            }

            return $this->success($doctorId, $row, $authMode, [
                'blocked_fields_ignored' => array_values($prepared['blocked_fields']),
                'editable_fields_applied' => [],
                'no_editable_fields_applied' => true,
            ]);
        }

        $updated = $this->repository->upsertIdentity($doctorId, $prepared['editable']);
        $metaExtra = [
            'editable_fields_applied' => array_keys($prepared['editable']),
        ];
        if (!empty($prepared['blocked_fields'])) {
            $metaExtra['blocked_fields_ignored'] = array_values($prepared['blocked_fields']);
        }
        return $this->success($doctorId, $updated, $authMode, $metaExtra);
    }

    private function prepareEditablePayload(array $payload): array
    {
        $editable = [];
        $blocked = [];
        $unknown = [];

        foreach ($payload as $key => $value) {
            $field = trim((string)$key);
            if ($field === '') {
                continue;
            }

            if (in_array($field, self::BLOCKED_FIELDS, true)) {
                $blocked[] = $field;
                continue;
            }
            if (!in_array($field, self::EDITABLE_FIELDS, true)) {
                $unknown[] = $field;
                continue;
            }

            if ($field === 'profile_theme_key') {
                if ($value === null || trim((string)$value) === '') {
                    $editable[$field] = null;
                    continue;
                }
                $clean = ProfileThemeCatalog::normalize((string)$value);
                if ($clean === null) {
                    $unknown[] = 'profile_theme_key:invalid_catalog_key';
                    continue;
                }
                $editable[$field] = $clean;
                continue;
            }
            $clean = $this->sanitizeText($value, $this->fieldMaxLength($field));
            $editable[$field] = $clean;
        }

        return [
            'editable' => $editable,
            'blocked_fields' => array_values(array_unique($blocked)),
            'unknown_fields' => array_values(array_unique($unknown)),
        ];
    }

    private function sanitizeText($value, int $maxLen): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = trim(strip_tags((string)$value));
        if ($text === '') {
            return null;
        }
        if (mb_strlen($text, 'UTF-8') > $maxLen) {
            $text = mb_substr($text, 0, $maxLen, 'UTF-8');
            $text = rtrim($text);
        }
        return $text === '' ? null : $text;
    }

    private function sanitizeTextArray(array $items, int $maxLenPerItem, int $maxItems): array
    {
        $out = [];
        foreach ($items as $item) {
            $clean = $this->sanitizeText($item, $maxLenPerItem);
            if ($clean !== null) {
                $out[] = $clean;
            }
            if (count($out) >= $maxItems) {
                break;
            }
        }
        return array_values(array_unique($out));
    }

    private function sanitizeUrlLike(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if ($value === '') {
            return null;
        }
        if (preg_match('/^https?:\/\//i', $value) === 1) {
            return $value;
        }
        if (str_starts_with($value, '/')) {
            return $value;
        }
        return null;
    }

    private function fieldMaxLength(string $field): int
    {
        switch ($field) {
            case 'professional_designation':
                return 120;
            case 'display_name':
            case 'specialty_primary':
                return 190;
            case 'prefix':
            case 'gender':
                return 32;
            case 'gender_label':
                return 64;
            case 'professional_license':
            case 'specialty_license':
                return 64;
            case 'bio_short':
                return 150;
            case 'photo_url':
            case 'avatar_url':
            case 'logo_url':
                return 2048;
            default:
                return 255;
        }
    }

    private function success(
        string $doctorId,
        array $row,
        string $authMode,
        array $metaExtra = []
    ): array
    {
        $nameReadModel = $this->physicianNameReadModel($doctorId, $this->nullableText($row['display_name'] ?? null));
        return [
            'ok' => true,
            'error' => null,
            'message' => '',
            'data' => [
                'doctor_id' => $doctorId,
                'identity_public' => [
                    'display_name' => $this->nullableText($row['display_name'] ?? null),
                    'professional_designation' => $this->nullableText($row['professional_designation'] ?? null),
                    'prefix' => $this->nullableText($row['prefix'] ?? null),
                    'gender' => $this->nullableText($row['gender'] ?? null),
                    'gender_label' => $this->nullableText($row['gender_label'] ?? null),
                    'professional_license' => $this->nullableText($row['professional_license'] ?? null),
                    'specialty_license' => $this->nullableText($row['specialty_license'] ?? null),
                    'specialty_primary' => $this->nullableText($row['specialty_primary'] ?? null),
                    'specialty_secondary' => $this->sanitizeTextArray((array)($row['specialty_secondary'] ?? []), 190, 12),
                    'bio_short' => $this->nullableText($row['bio_short'] ?? null),
                    'photo_url' => $this->nullableText($row['photo_url'] ?? null),
                    'avatar_url' => $this->nullableText($row['avatar_url'] ?? null),
                    'logo_url' => $this->nullableText($row['logo_url'] ?? null),
                    'profile_status' => $this->nullableText($row['profile_status'] ?? null) ?? 'hidden',
                    'is_public_candidate' => (bool)($row['is_public_candidate'] ?? false),
                ],
                'profile_theme' => [
                    'stored_key' => ProfileThemeCatalog::normalize($row['profile_theme_key'] ?? null),
                    'effective_key' => ProfileThemeCatalog::normalize($row['profile_theme_key'] ?? null) ?? ProfileThemeCatalog::DEFAULT_KEY,
                    'default_key' => ProfileThemeCatalog::DEFAULT_KEY,
                    'catalog' => ProfileThemeCatalog::all(),
                ],
                'verified_identity' => $nameReadModel['verified_identity'],
                'public_name_policy' => $nameReadModel['public_name_policy'],
                ...($this->credentialService?->physicianReadModel($doctorId) ?? [
                    'verified_credentials' => ['professional' => null, 'specialties' => []],
                    'primary_specialty_credential_id' => null,
                ]),
            ],
            'meta' => array_merge([
                'contract' => 'profile_private_identity_mvp',
                'version' => 'PP-7H2-A',
                'generated_at' => gmdate('c'),
                'auth_mode' => $authMode,
            ], $metaExtra),
        ];
    }

    private function physicianNameReadModel(string $doctorId, ?string $currentDisplayName): array
    {
        if ($this->verifiedIdentityService !== null) {
            return $this->verifiedIdentityService->physicianNameReadModel($doctorId, $currentDisplayName);
        }
        return [
            'verified_identity' => null,
            'public_name_policy' => [
                'verified_identity_available' => false,
                'current_display_name' => $currentDisplayName,
                'allowed_given_name_presentations' => [],
                'first_surname_required' => false,
                'second_surname_optional' => true,
                'current_display_name_policy_status' => 'NOT_APPLICABLE',
            ],
        ];
    }

    private function error(string $code, string $message, string $authMode, array $metaExtra = []): array
    {
        return [
            'ok' => false,
            'error' => $code,
            'message' => $message,
            'data' => null,
            'meta' => array_merge([
                'contract' => 'profile_private_identity_mvp',
                'version' => 'PP-7H2-A',
                'generated_at' => gmdate('c'),
                'auth_mode' => $authMode,
            ], $metaExtra),
        ];
    }

    private function isValidDoctorId(string $doctorId): bool
    {
        if ($doctorId === '' || strlen($doctorId) > 64) {
            return false;
        }
        return preg_match('/^[A-Za-z0-9._:-]+$/', $doctorId) === 1;
    }

    private function nullableText($value): ?string
    {
        $text = trim((string)($value ?? ''));
        return $text === '' ? null : $text;
    }

    private function isAssociative(array $payload): bool
    {
        if ($payload === []) {
            return true;
        }
        return array_keys($payload) !== range(0, count($payload) - 1);
    }
}
