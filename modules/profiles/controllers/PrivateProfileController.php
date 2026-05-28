<?php
declare(strict_types=1);

namespace Profiles\Controllers;

use Profiles\Repositories\PrivateProfileRepository;

require_once __DIR__ . '/../repositories/PrivateProfileRepository.php';

final class PrivateProfileController
{
    private PrivateProfileRepository $repository;

    private const EDITABLE_FIELDS = [
        'display_name',
        'prefix',
        'gender',
        'gender_label',
        'professional_license',
        'specialty_license',
        'specialty_primary',
        'specialty_secondary',
        'bio_short',
        'photo_url',
        'avatar_url',
        'logo_url',
    ];

    private const BLOCKED_FIELDS = [
        'profile_status',
        'is_public_candidate',
    ];

    public function __construct(PrivateProfileRepository $repository)
    {
        $this->repository = $repository;
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

        $prepared = $this->prepareEditablePayload($payload);
        if (!empty($prepared['unknown_fields'])) {
            return $this->error('invalid_payload', 'unsupported fields in payload', $authMode, [
                'unknown_fields' => array_values($prepared['unknown_fields']),
            ]);
        }
        if (empty($prepared['editable'])) {
            return $this->error('invalid_payload', 'no editable fields provided', $authMode, [
                'blocked_fields' => array_values($prepared['blocked_fields']),
            ]);
        }

        $updated = $this->repository->upsertIdentity($doctorId, $prepared['editable']);
        $metaExtra = [];
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

            if ($field === 'specialty_secondary') {
                if (!is_array($value)) {
                    $unknown[] = $field;
                    continue;
                }
                $editable['specialty_secondary_json'] = json_encode(
                    $this->sanitizeTextArray($value, 190, 12),
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                );
                if ($editable['specialty_secondary_json'] === false) {
                    $editable['specialty_secondary_json'] = '[]';
                }
                continue;
            }

            $clean = $this->sanitizeText($value, $this->fieldMaxLength($field));
            if (in_array($field, ['photo_url', 'avatar_url', 'logo_url'], true)) {
                $clean = $this->sanitizeUrlLike($clean);
            }
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
                return 1500;
            case 'photo_url':
            case 'avatar_url':
            case 'logo_url':
                return 2048;
            default:
                return 255;
        }
    }

    private function success(string $doctorId, array $row, string $authMode, array $metaExtra = []): array
    {
        return [
            'ok' => true,
            'error' => null,
            'message' => '',
            'data' => [
                'doctor_id' => $doctorId,
                'identity_public' => [
                    'display_name' => $this->nullableText($row['display_name'] ?? null),
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
            ],
            'meta' => array_merge([
                'contract' => 'profile_private_identity_mvp',
                'version' => 'PP-7H2-A',
                'generated_at' => gmdate('c'),
                'auth_mode' => $authMode,
            ], $metaExtra),
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
