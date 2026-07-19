<?php
declare(strict_types=1);

namespace Subscriptions\Services;

require_once __DIR__ . '/ProfileApprovalOwnershipAdapter.php';

use PDO;
use PDOException;
use RuntimeException;

final class SubscriptionEntityResolverService
{
    private const ENTITY_TYPE_DOCTOR = 'doctor';
    private const SOURCE_PROFILES_DOCTORS = 'profiles_doctors';
    private const ERROR_ENTITY_TYPE_INVALID = 'entity_type_invalid';
    private const ERROR_ENTITY_ID_INVALID = 'entity_id_invalid';
    private const ERROR_ENTITY_NOT_FOUND = 'entity_not_found';
    private const ERROR_ENTITY_NOT_CONTRACTABLE = 'entity_not_contractable';
    private const ERROR_ENTITY_VALIDATION_UNAVAILABLE = 'entity_validation_unavailable';

    private PDO $pdo;
    private array $actorContext;
    private ProfileApprovalOwnershipAdapter $approvalOwnershipAdapter;

    public function __construct(
        PDO $pdo,
        array $actorContext = [],
        ?ProfileApprovalOwnershipAdapter $approvalOwnershipAdapter = null
    ) {
        $this->pdo = $pdo;
        $this->actorContext = $actorContext;
        $this->approvalOwnershipAdapter = $approvalOwnershipAdapter ?? new ProfileApprovalOwnershipAdapter();
    }

    public function resolveForReadModel(string $entityType, string $entityId): array
    {
        try {
            $resolved = $this->resolveForCheckout($entityType, $entityId);
        } catch (RuntimeException $e) {
            $resolved = $this->snapshot(
                strtolower(trim($entityType)),
                trim($entityId),
                false,
                false,
                null,
                self::SOURCE_PROFILES_DOCTORS,
                'profile_validation_unavailable',
                self::ERROR_ENTITY_VALIDATION_UNAVAILABLE
            );
        }
        $resolved['profile_type'] = strtolower(trim($entityType));
        return $resolved;
    }

    public function resolveForCheckout(string $entityType, string $entityId): array
    {
        $normalizedType = strtolower(trim($entityType));
        $normalizedId = trim($entityId);

        if ($normalizedType !== self::ENTITY_TYPE_DOCTOR) {
            return $this->snapshot(
                $normalizedType,
                $normalizedId,
                false,
                false,
                null,
                null,
                'unsupported_entity_type',
                self::ERROR_ENTITY_TYPE_INVALID
            );
        }

        if (!$this->isValidEntityId($normalizedId)) {
            return $this->snapshot(
                self::ENTITY_TYPE_DOCTOR,
                $normalizedId,
                false,
                false,
                null,
                self::SOURCE_PROFILES_DOCTORS,
                'invalid_doctor_id',
                self::ERROR_ENTITY_ID_INVALID
            );
        }

        try {
            $available = $this->availableProfileColumns();
            $optionalColumns = [];
            foreach (['approval_status', 'approval_source', 'owner_user_id', 'ownership_status', 'ownership_source'] as $column) {
                if (in_array($column, $available, true)) {
                    $optionalColumns[] = $column;
                }
            }
            $optionalSelect = $optionalColumns !== []
                ? ",\n                    " . implode(",\n                    ", $optionalColumns)
                : '';
            $stmt = $this->pdo->prepare(
                'SELECT
                    doctor_id,
                    display_name,
                    profile_status,
                    is_public_candidate' . $optionalSelect . '
                 FROM profiles_doctors
                 WHERE doctor_id = :doctor_id
                 LIMIT 1'
            );
            $stmt->execute(['doctor_id' => $normalizedId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new RuntimeException(self::ERROR_ENTITY_VALIDATION_UNAVAILABLE, 0, $e);
        }

        if (!is_array($row)) {
            return $this->snapshot(
                self::ENTITY_TYPE_DOCTOR,
                $normalizedId,
                false,
                false,
                null,
                self::SOURCE_PROFILES_DOCTORS,
                'doctor_not_found',
                self::ERROR_ENTITY_NOT_FOUND
            );
        }

        $policy = $this->approvalOwnershipAdapter->adapt(
            $row + ['profile_type' => self::ENTITY_TYPE_DOCTOR],
            $this->actorContext
        );

        return $this->snapshot(
            self::ENTITY_TYPE_DOCTOR,
            (string)($row['doctor_id'] ?? $normalizedId),
            true,
            (bool)$policy['purchase_allowed'],
            $this->nullableText($row['display_name'] ?? null),
            self::SOURCE_PROFILES_DOCTORS,
            $policy['denial_reason'],
            $policy['denial_reason'],
            $this->nullableText($row['profile_status'] ?? null),
            ((int)($row['is_public_candidate'] ?? 0) === 1),
            $policy
        );
    }

    private function isValidEntityId(string $entityId): bool
    {
        if ($entityId === '' || strlen($entityId) > 64) {
            return false;
        }

        return preg_match('/^[A-Za-z0-9._:-]+$/', $entityId) === 1;
    }

    private function snapshot(
        string $entityType,
        string $entityId,
        bool $entityExists,
        bool $entityIsContractable,
        ?string $label,
        ?string $source,
        ?string $reason,
        ?string $error,
        ?string $profileStatus = null,
        ?bool $isPublicCandidate = null,
        array $policy = []
    ): array {
        return [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'entity_exists' => $entityExists,
            'entity_is_contractable' => $entityIsContractable,
            'label' => $label,
            'source' => $source,
            'reason' => $reason,
            'error' => $error,
            'profile_status' => $profileStatus,
            'is_public_candidate' => $isPublicCandidate,
            'profile_type' => $policy['profile_type'] ?? ($entityType === self::ENTITY_TYPE_DOCTOR ? 'doctor' : null),
            'approval_state' => $policy['approval_state'] ?? null,
            'approval_source' => $policy['approval_source'] ?? null,
            'ownership_state' => $policy['ownership_state'] ?? null,
            'ownership_source' => $policy['ownership_source'] ?? null,
            'admin_allowed' => (bool)($policy['admin_allowed'] ?? false),
            'purchase_allowed' => (bool)($policy['purchase_allowed'] ?? false),
            'denial_reason' => $policy['denial_reason'] ?? $reason,
        ];
    }

    private function availableProfileColumns(): array
    {
        try {
            $stmt = $this->pdo->query('SHOW COLUMNS FROM profiles_doctors');
            $rows = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (PDOException $e) {
            return [];
        }
        $columns = [];
        foreach ($rows as $row) {
            $field = trim((string)($row['Field'] ?? ''));
            if ($field !== '') {
                $columns[] = $field;
            }
        }
        return $columns;
    }

    private function nullableText($value): ?string
    {
        $text = trim((string)($value ?? ''));

        return $text !== '' ? $text : null;
    }
}
