<?php
declare(strict_types=1);

namespace Profiles\Services;

use DateTimeImmutable;
use Profiles\Repositories\VerifiedPhysicianIdentityRepository;
use RuntimeException;

require_once __DIR__ . '/../repositories/VerifiedPhysicianIdentityRepository.php';

final class VerifiedPhysicianIdentityService
{
    private const SOURCE_TYPES = [
        'admission_approved',
        'internal_provisioning',
        'governed_correction',
        'synthetic_test',
    ];

    public function __construct(
        private VerifiedPhysicianIdentityRepository $repository,
        private bool $allowSyntheticFixtures = false
    ) {
    }

    public function readForPhysician(string $doctorId): ?array
    {
        $doctorId = $this->doctorId($doctorId);
        $identity = $this->repository->findByDoctorId($doctorId);
        if ($identity === null) {
            return null;
        }

        return [
            'given_names' => $identity['given_names'],
            'first_surname' => $identity['first_surname'],
            'second_surname' => $identity['second_surname'],
            'full_name' => $this->fullName($identity),
            'verified_at' => $identity['verified_at'],
            'source' => $identity['source_type'],
        ];
    }

    public function provisionFromTrustedAuthority(array $identity, array $provenance): array
    {
        $sourceType = strtolower(trim((string)($provenance['source_type'] ?? '')));
        if (!in_array($sourceType, self::SOURCE_TYPES, true)) {
            throw new RuntimeException('verified_identity_invalid_source_type');
        }

        $verifiedBy = $this->nullableIdentifier($provenance['verified_by_account_id'] ?? null);
        if ($sourceType === 'synthetic_test') {
            if (!$this->allowSyntheticFixtures || $verifiedBy !== null) {
                throw new RuntimeException('verified_identity_synthetic_source_forbidden');
            }
        } elseif ($verifiedBy === null || !$this->repository->isActiveInternalApprover($verifiedBy)) {
            throw new RuntimeException('verified_identity_untrusted_approver');
        }

        $record = [
            'doctor_id' => $this->doctorId((string)($identity['doctor_id'] ?? '')),
            'given_names' => $this->requiredText($identity['given_names'] ?? null, 190, 'given_names'),
            'first_surname' => $this->requiredText($identity['first_surname'] ?? null, 120, 'first_surname'),
            'second_surname' => $this->optionalText($identity['second_surname'] ?? null, 120, 'second_surname'),
            'source_type' => $sourceType,
            'source_reference' => $this->requiredText($provenance['source_reference'] ?? null, 190, 'source_reference'),
            'verified_at' => $this->timestamp((string)($provenance['verified_at'] ?? '')),
            'verified_by_account_id' => $verifiedBy,
        ];

        return $this->repository->create($record);
    }

    private function fullName(array $identity): string
    {
        return implode(' ', array_values(array_filter([
            $identity['given_names'] ?? null,
            $identity['first_surname'] ?? null,
            $identity['second_surname'] ?? null,
        ], static fn($value): bool => is_string($value) && trim($value) !== '')));
    }

    private function doctorId(string $doctorId): string
    {
        $doctorId = trim($doctorId);
        if ($doctorId === '' || strlen($doctorId) > 64 || preg_match('/^[A-Za-z0-9._:-]+$/', $doctorId) !== 1) {
            throw new RuntimeException('verified_identity_invalid_doctor_id');
        }
        return $doctorId;
    }

    private function requiredText($value, int $maxLength, string $field): string
    {
        $text = $this->cleanText($value, $maxLength, $field);
        if ($text === null) {
            throw new RuntimeException('verified_identity_' . $field . '_required');
        }
        return $text;
    }

    private function optionalText($value, int $maxLength, string $field): ?string
    {
        return $this->cleanText($value, $maxLength, $field);
    }

    private function cleanText($value, int $maxLength, string $field): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!is_scalar($value)) {
            throw new RuntimeException('verified_identity_' . $field . '_invalid');
        }
        $text = preg_replace('/\s+/u', ' ', trim(strip_tags((string)$value)));
        $text = is_string($text) ? $text : '';
        if ($text === '') {
            return null;
        }
        if (mb_strlen($text, 'UTF-8') > $maxLength) {
            throw new RuntimeException('verified_identity_' . $field . '_too_long');
        }
        return $text;
    }

    private function nullableIdentifier($value): ?string
    {
        $identifier = trim((string)($value ?? ''));
        if ($identifier === '') {
            return null;
        }
        if (strlen($identifier) > 64 || preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]*$/', $identifier) !== 1) {
            throw new RuntimeException('verified_identity_invalid_approver');
        }
        return $identifier;
    }

    private function timestamp(string $value): string
    {
        $value = trim($value);
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s.u', $value)
            ?: DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value);
        $errors = DateTimeImmutable::getLastErrors();
        if ($parsed === false || (is_array($errors) && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0))) {
            throw new RuntimeException('verified_identity_invalid_verified_at');
        }
        return $parsed->format('Y-m-d H:i:s.u');
    }
}
