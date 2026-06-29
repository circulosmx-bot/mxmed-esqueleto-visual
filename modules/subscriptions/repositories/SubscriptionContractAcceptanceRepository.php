<?php
declare(strict_types=1);

namespace Subscriptions\Repositories;

use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

final class SubscriptionContractAcceptanceRepository
{
    private const STATUS_ACCEPTED_PENDING_PAYMENT = 'accepted_pending_payment';

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function insert(array $data): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO subscription_contract_acceptances (
                uuid,
                entity_type,
                entity_id,
                doctor_id,
                profile_id,
                subscription_id,
                plan_code,
                billing_period,
                duration_days,
                contract_version,
                contract_hash,
                contract_snapshot_url,
                contract_title,
                accepted_at,
                accepted_by_user_id,
                accepted_by_actor_role,
                accepted_by_operator_id,
                acceptance_source,
                ip_address,
                user_agent,
                status,
                source,
                notes,
                deleted_at
            ) VALUES (
                :uuid,
                :entity_type,
                :entity_id,
                :doctor_id,
                :profile_id,
                :subscription_id,
                :plan_code,
                :billing_period,
                :duration_days,
                :contract_version,
                :contract_hash,
                :contract_snapshot_url,
                :contract_title,
                :accepted_at,
                :accepted_by_user_id,
                :accepted_by_actor_role,
                :accepted_by_operator_id,
                :acceptance_source,
                :ip_address,
                :user_agent,
                :status,
                :source,
                :notes,
                NULL
            )'
        );

        $stmt->execute([
            'uuid' => $data['uuid'],
            'entity_type' => $data['entity_type'],
            'entity_id' => $data['entity_id'],
            'doctor_id' => $data['doctor_id'],
            'profile_id' => $data['profile_id'],
            'subscription_id' => $data['subscription_id'],
            'plan_code' => $data['plan_code'],
            'billing_period' => $data['billing_period'],
            'duration_days' => $data['duration_days'],
            'contract_version' => $data['contract_version'],
            'contract_hash' => $data['contract_hash'],
            'contract_snapshot_url' => $data['contract_snapshot_url'],
            'contract_title' => $data['contract_title'],
            'accepted_at' => $data['accepted_at'],
            'accepted_by_user_id' => $data['accepted_by_user_id'],
            'accepted_by_actor_role' => $data['accepted_by_actor_role'],
            'accepted_by_operator_id' => $data['accepted_by_operator_id'],
            'acceptance_source' => $data['acceptance_source'],
            'ip_address' => $data['ip_address'],
            'user_agent' => $data['user_agent'],
            'status' => $data['status'],
            'source' => $data['source'],
            'notes' => $data['notes'],
        ]);
    }

    public function findByUuid(string $acceptanceUuid): ?array
    {
        $acceptanceUuid = trim($acceptanceUuid);
        if ($acceptanceUuid === '') {
            throw new InvalidArgumentException('invalid_contract_acceptance_payload: uuid is required');
        }

        return $this->findOne(
            'SELECT ' . $this->selectColumns() . '
             FROM subscription_contract_acceptances
             WHERE uuid = :uuid
               AND deleted_at IS NULL
             LIMIT 1',
            ['uuid' => $acceptanceUuid]
        );
    }

    public function findPendingPaymentByUuid(string $contractAcceptanceUuid): ?array
    {
        $contractAcceptanceUuid = trim($contractAcceptanceUuid);
        if ($contractAcceptanceUuid === '') {
            return null;
        }

        return $this->findOne(
            'SELECT ' . $this->selectColumns() . '
             FROM subscription_contract_acceptances
             WHERE uuid = :uuid
               AND status = :status
               AND deleted_at IS NULL
             LIMIT 1',
            [
                'uuid' => $contractAcceptanceUuid,
                'status' => self::STATUS_ACCEPTED_PENDING_PAYMENT,
            ]
        );
    }

    public function findPendingPaymentByEntity(string $entityType, int $entityId): ?array
    {
        $entityType = trim(strtolower($entityType));
        if ($entityType === '' || $entityId <= 0) {
            return null;
        }

        return $this->findOne(
            'SELECT ' . $this->selectColumns() . '
             FROM subscription_contract_acceptances
             WHERE entity_type = :entity_type
               AND entity_id = :entity_id
               AND status = :status
               AND deleted_at IS NULL
             ORDER BY created_at DESC, id DESC
             LIMIT 1',
            [
                'entity_type' => $entityType,
                'entity_id' => (string)$entityId,
                'status' => self::STATUS_ACCEPTED_PENDING_PAYMENT,
            ]
        );
    }

    public function linkSubscriptionId(string $acceptanceUuid, string $subscriptionId): ?array
    {
        $acceptanceUuid = trim($acceptanceUuid);
        $subscriptionId = trim($subscriptionId);
        if ($acceptanceUuid === '' || $subscriptionId === '') {
            throw new InvalidArgumentException('invalid_contract_acceptance_payload: uuid and subscription_id are required');
        }

        try {
            $stmt = $this->pdo->prepare(
                'UPDATE subscription_contract_acceptances
                 SET subscription_id = :subscription_id
                 WHERE uuid = :uuid
                   AND status = :status
                   AND subscription_id IS NULL
                   AND deleted_at IS NULL'
            );
            $stmt->execute([
                'uuid' => $acceptanceUuid,
                'subscription_id' => $subscriptionId,
                'status' => self::STATUS_ACCEPTED_PENDING_PAYMENT,
            ]);
        } catch (Throwable $e) {
            throw new RuntimeException('contract_acceptance_subscription_link_failed', 0, $e);
        }

        if ($stmt->rowCount() < 1) {
            return null;
        }

        return $this->findByUuid($acceptanceUuid);
    }

    private function findOne(string $sql, array $params): ?array
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            throw new RuntimeException('contract_acceptance_lookup_failed', 0, $e);
        }

        return is_array($row) ? $this->normalizeRow($row) : null;
    }

    private function normalizeRow(array $row): array
    {
        return [
            'id' => isset($row['id']) ? (int)$row['id'] : null,
            'uuid' => (string)($row['uuid'] ?? ''),
            'entity_type' => (string)($row['entity_type'] ?? ''),
            'entity_id' => (string)($row['entity_id'] ?? ''),
            'doctor_id' => $this->nullableString($row['doctor_id'] ?? null),
            'profile_id' => $this->nullableString($row['profile_id'] ?? null),
            'subscription_id' => $this->nullableString($row['subscription_id'] ?? null),
            'plan_code' => (string)($row['plan_code'] ?? ''),
            'billing_period' => (string)($row['billing_period'] ?? ''),
            'duration_days' => isset($row['duration_days']) ? (int)$row['duration_days'] : null,
            'contract_version' => (string)($row['contract_version'] ?? ''),
            'contract_hash' => $this->nullableString($row['contract_hash'] ?? null),
            'contract_snapshot_url' => $this->nullableString($row['contract_snapshot_url'] ?? null),
            'contract_title' => $this->nullableString($row['contract_title'] ?? null),
            'accepted_at' => (string)($row['accepted_at'] ?? ''),
            'accepted_by_user_id' => isset($row['accepted_by_user_id']) ? (int)$row['accepted_by_user_id'] : null,
            'accepted_by_actor_role' => $this->nullableString($row['accepted_by_actor_role'] ?? null),
            'accepted_by_operator_id' => isset($row['accepted_by_operator_id']) ? (int)$row['accepted_by_operator_id'] : null,
            'acceptance_source' => (string)($row['acceptance_source'] ?? ''),
            'ip_address' => $this->nullableString($row['ip_address'] ?? null),
            'user_agent' => $this->nullableString($row['user_agent'] ?? null),
            'status' => (string)($row['status'] ?? ''),
            'source' => (string)($row['source'] ?? ''),
            'notes' => $this->nullableString($row['notes'] ?? null),
            'created_at' => (string)($row['created_at'] ?? ''),
            'updated_at' => (string)($row['updated_at'] ?? ''),
            'deleted_at' => $this->nullableString($row['deleted_at'] ?? null),
        ];
    }

    private function selectColumns(): string
    {
        return 'id,
                uuid,
                entity_type,
                entity_id,
                doctor_id,
                profile_id,
                subscription_id,
                plan_code,
                billing_period,
                duration_days,
                contract_version,
                contract_hash,
                contract_snapshot_url,
                contract_title,
                accepted_at,
                accepted_by_user_id,
                accepted_by_actor_role,
                accepted_by_operator_id,
                acceptance_source,
                ip_address,
                user_agent,
                status,
                source,
                notes,
                created_at,
                updated_at,
                deleted_at';
    }

    private function nullableString($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = (string)$value;
        return $text === '' ? null : $text;
    }
}
