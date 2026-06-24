<?php
declare(strict_types=1);

namespace Subscriptions\Repositories;

use PDO;
use PDOException;

final class SubscriptionWriteIdempotencyRepository
{
    private const DUPLICATE_KEY_SQLSTATE = '23000';

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findByScope(
        string $idempotencyKeyHash,
        string $userId,
        string $entityType,
        string $entityId,
        string $operation
    ): ?array {
        $stmt = $this->pdo->prepare(
            'SELECT *
             FROM subscription_write_idempotency_keys
             WHERE idempotency_key_hash = :idempotency_key_hash
               AND user_id = :user_id
               AND entity_type = :entity_type
               AND entity_id = :entity_id
               AND operation = :operation
               AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([
            'idempotency_key_hash' => $idempotencyKeyHash,
            'user_id' => (int)$userId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'operation' => $operation,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function insertProcessing(array $data): bool
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO subscription_write_idempotency_keys (
                uuid,
                idempotency_key_hash,
                request_hash,
                entity_type,
                entity_id,
                doctor_id,
                profile_id,
                user_id,
                actor_role,
                operation,
                status,
                locked_at,
                expires_at,
                source,
                deleted_at
            ) VALUES (
                :uuid,
                :idempotency_key_hash,
                :request_hash,
                :entity_type,
                :entity_id,
                :doctor_id,
                :profile_id,
                :user_id,
                :actor_role,
                :operation,
                \'processing\',
                NOW(),
                DATE_ADD(NOW(), INTERVAL 1 DAY),
                \'mxmed_subscription_idempotency_v1\',
                NULL
            )'
        );

        try {
            $stmt->execute([
                'uuid' => $data['uuid'],
                'idempotency_key_hash' => $data['idempotency_key_hash'],
                'request_hash' => $data['request_hash'],
                'entity_type' => $data['entity_type'],
                'entity_id' => $data['entity_id'],
                'doctor_id' => $data['doctor_id'],
                'profile_id' => $data['profile_id'],
                'user_id' => (int)$data['user_id'],
                'actor_role' => $data['actor_role'],
                'operation' => $data['operation'],
            ]);
        } catch (PDOException $e) {
            if ($e->getCode() === self::DUPLICATE_KEY_SQLSTATE) {
                return false;
            }
            throw $e;
        }

        return true;
    }

    public function markCompleted(
        string $uuid,
        string $subscriptionId,
        string $contractAcceptanceUuid,
        int $httpStatus,
        ?string $responseBodyText
    ): void {
        $this->markCompletedWithResponse(
            $uuid,
            $subscriptionId,
            $contractAcceptanceUuid,
            $httpStatus,
            $responseBodyText
        );
    }

    public function markCompletedWithResponse(
        string $uuid,
        ?string $subscriptionId,
        ?string $contractAcceptanceUuid,
        int $httpStatus,
        ?string $responseBodyText
    ): void {
        $stmt = $this->pdo->prepare(
            'UPDATE subscription_write_idempotency_keys
             SET status = \'completed\',
                 subscription_id = :subscription_id,
                 contract_acceptance_uuid = :contract_acceptance_uuid,
                 response_http_status = :response_http_status,
                 response_body_text = :response_body_text,
                 completed_at = NOW()
             WHERE uuid = :uuid'
        );
        $stmt->execute([
            'uuid' => $uuid,
            'subscription_id' => $subscriptionId,
            'contract_acceptance_uuid' => $contractAcceptanceUuid,
            'response_http_status' => $httpStatus,
            'response_body_text' => $responseBodyText,
        ]);
    }

    public function markFailed(string $uuid, int $httpStatus): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE subscription_write_idempotency_keys
             SET status = \'failed\',
                 response_http_status = :response_http_status,
                 completed_at = NOW()
             WHERE uuid = :uuid
               AND status = \'processing\''
        );
        $stmt->execute([
            'uuid' => $uuid,
            'response_http_status' => $httpStatus,
        ]);
    }
}
