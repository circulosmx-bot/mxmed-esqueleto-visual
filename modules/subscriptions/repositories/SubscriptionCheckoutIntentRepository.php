<?php
declare(strict_types=1);

namespace Subscriptions\Repositories;

use InvalidArgumentException;
use PDO;
use Throwable;
use RuntimeException;

final class SubscriptionCheckoutIntentRepository
{
    private const STATUS_PENDING = 'pending_payment';

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function create(array $snapshot): array
    {
        $uuid = $this->requiredText($snapshot['uuid'] ?? null, 'invalid_checkout_intent_payload', 36);
        $entityType = $this->requiredText($snapshot['entity_type'] ?? null, 'invalid_checkout_intent_payload', 64);
        $entityId = $this->requiredText($snapshot['entity_id'] ?? null, 'invalid_checkout_intent_payload', 64);
        $doctorId = $this->optionalText($snapshot['doctor_id'] ?? null, 64);
        $profileId = $this->optionalText($snapshot['profile_id'] ?? null, 64);
        $userId = $this->requiredInt($snapshot['user_id'] ?? null, 'invalid_checkout_intent_payload');
        $actorRole = $this->optionalText($snapshot['actor_role'] ?? null, 32);
        $planCode = $this->requiredText($snapshot['plan_code'] ?? null, 'invalid_checkout_intent_payload', 64);
        $billingPeriod = $this->requiredText($snapshot['billing_period'] ?? null, 'invalid_checkout_intent_payload', 32);
        $amountCents = $this->requiredInt($snapshot['amount_cents'] ?? null, 'pricing_snapshot_missing');
        $currency = strtoupper($this->requiredText($snapshot['currency'] ?? null, 'pricing_snapshot_missing', 3));
        $priceSource = $this->requiredText($snapshot['price_source'] ?? null, 'pricing_snapshot_missing', 128);
        $priceVersion = $this->requiredText($snapshot['price_version'] ?? null, 'pricing_snapshot_missing', 64);
        $status = $this->requiredText($snapshot['status'] ?? null, 'invalid_checkout_intent_payload', 32);
        $contractVersion = $this->requiredText($snapshot['contract_version'] ?? null, 'invalid_checkout_intent_payload', 64);
        $contractHash = $this->requiredText($snapshot['contract_hash'] ?? null, 'invalid_checkout_intent_payload', 128);
        $contractSnapshotUrl = $this->requiredText($snapshot['contract_snapshot_url'] ?? null, 'invalid_checkout_intent_payload', 255);
        $contractAcceptanceUuid = $this->requiredText(
            $snapshot['contract_acceptance_uuid'] ?? null,
            'contract_acceptance_uuid_missing',
            36
        );
        $idempotencyKeyHash = $this->optionalText($snapshot['idempotency_key_hash'] ?? null, 64);
        $requestHash = $this->optionalText($snapshot['request_hash'] ?? null, 64);
        $expiresAt = $this->requiredText($snapshot['expires_at'] ?? null, 'invalid_checkout_intent_payload', 19);
        $source = $this->requiredText($snapshot['source'] ?? null, 'invalid_checkout_intent_payload', 128);
        $notes = $this->optionalText($snapshot['notes'] ?? null, 65535);

        if ($amountCents < 0) {
            throw new InvalidArgumentException('pricing_snapshot_missing: amount_cents must be non-negative');
        }
        if ($status !== self::STATUS_PENDING) {
            throw new InvalidArgumentException('invalid_checkout_intent_payload: status must be pending_payment');
        }

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO subscription_checkout_intents (
                    uuid,
                    entity_type,
                    entity_id,
                    doctor_id,
                    profile_id,
                    user_id,
                    actor_role,
                    plan_code,
                    billing_period,
                    amount_cents,
                    currency,
                    price_source,
                    price_version,
                    status,
                    contract_version,
                    contract_hash,
                    contract_snapshot_url,
                    contract_acceptance_uuid,
                    idempotency_key_hash,
                    request_hash,
                    expires_at,
                    source,
                    notes,
                    deleted_at
                ) VALUES (
                    :uuid,
                    :entity_type,
                    :entity_id,
                    :doctor_id,
                    :profile_id,
                    :user_id,
                    :actor_role,
                    :plan_code,
                    :billing_period,
                    :amount_cents,
                    :currency,
                    :price_source,
                    :price_version,
                    :status,
                    :contract_version,
                    :contract_hash,
                    :contract_snapshot_url,
                    :contract_acceptance_uuid,
                    :idempotency_key_hash,
                    :request_hash,
                    :expires_at,
                    :source,
                    :notes,
                    NULL
                )'
            );
            $stmt->execute([
                'uuid' => $uuid,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'doctor_id' => $doctorId,
                'profile_id' => $profileId,
                'user_id' => $userId,
                'actor_role' => $actorRole,
                'plan_code' => $planCode,
                'billing_period' => $billingPeriod,
                'amount_cents' => $amountCents,
                'currency' => $currency,
                'price_source' => $priceSource,
                'price_version' => $priceVersion,
                'status' => $status,
                'contract_version' => $contractVersion,
                'contract_hash' => $contractHash,
                'contract_snapshot_url' => $contractSnapshotUrl,
                'contract_acceptance_uuid' => $contractAcceptanceUuid,
                'idempotency_key_hash' => $idempotencyKeyHash,
                'request_hash' => $requestHash,
                'expires_at' => $expiresAt,
                'source' => $source,
                'notes' => $notes,
            ]);
        } catch (Throwable $e) {
            throw new RuntimeException('checkout_intent_create_failed', 0, $e);
        }

        $created = $this->findByUuid($uuid);
        if ($created === null) {
            throw new RuntimeException('checkout_intent_lookup_failed');
        }

        return $created;
    }

    public function findByUuid(string $uuid): ?array
    {
        $uuid = trim($uuid);
        if ($uuid === '') {
            throw new InvalidArgumentException('invalid_checkout_intent_payload: uuid is required');
        }

        return $this->findOne(
            'SELECT ' . $this->selectColumns() . '
             FROM subscription_checkout_intents
             WHERE uuid = :uuid
               AND deleted_at IS NULL
             LIMIT 1',
            ['uuid' => $uuid]
        );
    }

    public function findPendingByEntity(string $entityType, string $entityId): ?array
    {
        $entityType = trim($entityType);
        $entityId = trim($entityId);
        if ($entityType === '' || $entityId === '') {
            throw new InvalidArgumentException('invalid_checkout_intent_payload: entity is required');
        }

        return $this->findOne(
            'SELECT ' . $this->selectColumns() . '
             FROM subscription_checkout_intents
             WHERE entity_type = :entity_type
               AND entity_id = :entity_id
               AND status = :status
               AND expires_at >= UTC_TIMESTAMP()
               AND deleted_at IS NULL
             ORDER BY created_at DESC, id DESC
             LIMIT 1',
            [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'status' => self::STATUS_PENDING,
            ]
        );
    }

    public function findLatestPendingPaymentByEntity(string $entityType, int $entityId): ?array
    {
        $entityType = trim($entityType);
        if ($entityType === '' || $entityId <= 0) {
            return null;
        }

        return $this->findPendingByEntity($entityType, (string)$entityId);
    }

    public function findPendingByEntityPlanAndBilling(
        string $entityType,
        string $entityId,
        string $planCode,
        string $billingPeriod
    ): ?array {
        $entityType = trim($entityType);
        $entityId = trim($entityId);
        $planCode = trim($planCode);
        $billingPeriod = trim($billingPeriod);
        if ($entityType === '' || $entityId === '' || $planCode === '' || $billingPeriod === '') {
            throw new InvalidArgumentException('invalid_checkout_intent_payload: entity, plan_code and billing_period are required');
        }

        return $this->findOne(
            'SELECT ' . $this->selectColumns() . '
             FROM subscription_checkout_intents
             WHERE entity_type = :entity_type
               AND entity_id = :entity_id
               AND plan_code = :plan_code
               AND billing_period = :billing_period
               AND status = :status
               AND expires_at >= UTC_TIMESTAMP()
               AND deleted_at IS NULL
             ORDER BY created_at DESC, id DESC
             LIMIT 1',
            [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'plan_code' => $planCode,
                'billing_period' => $billingPeriod,
                'status' => self::STATUS_PENDING,
            ]
        );
    }

    public function markActivatedAfterPayment(string $checkoutIntentUuid, string $subscriptionId, array $metadata = []): ?array
    {
        $checkoutIntentUuid = trim($checkoutIntentUuid);
        $subscriptionId = trim($subscriptionId);
        if ($checkoutIntentUuid === '' || $subscriptionId === '') {
            throw new InvalidArgumentException('invalid_checkout_intent_payload: checkout_intent_uuid and subscription_id are required');
        }

        $notes = $this->optionalText($metadata['notes'] ?? null, 65535);
        $source = $this->optionalText($metadata['source'] ?? null, 128);

        try {
            $stmt = $this->pdo->prepare(
                'UPDATE subscription_checkout_intents
                 SET status = :activated_status,
                     subscription_id = :subscription_id,
                     activated_at = UTC_TIMESTAMP(),
                     notes = COALESCE(:notes, notes),
                     source = COALESCE(:source, source)
                 WHERE uuid = :uuid
                   AND status = :pending_status
                   AND deleted_at IS NULL'
            );
            $stmt->execute([
                'uuid' => $checkoutIntentUuid,
                'subscription_id' => $subscriptionId,
                'activated_status' => 'activated',
                'pending_status' => self::STATUS_PENDING,
                'notes' => $notes,
                'source' => $source,
            ]);
        } catch (Throwable $e) {
            throw new RuntimeException('checkout_activation_transition_failed', 0, $e);
        }

        if ($stmt->rowCount() < 1) {
            return null;
        }

        return $this->findByUuid($checkoutIntentUuid);
    }

    private function findOne(string $sql, array $params): ?array
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            throw new RuntimeException('checkout_intent_lookup_failed', 0, $e);
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
            'user_id' => isset($row['user_id']) ? (int)$row['user_id'] : null,
            'actor_role' => $this->nullableString($row['actor_role'] ?? null),
            'plan_code' => (string)($row['plan_code'] ?? ''),
            'billing_period' => (string)($row['billing_period'] ?? ''),
            'amount_cents' => isset($row['amount_cents']) ? (int)$row['amount_cents'] : null,
            'currency' => (string)($row['currency'] ?? ''),
            'price_source' => $this->nullableString($row['price_source'] ?? null),
            'price_version' => $this->nullableString($row['price_version'] ?? null),
            'status' => (string)($row['status'] ?? ''),
            'contract_version' => (string)($row['contract_version'] ?? ''),
            'contract_hash' => (string)($row['contract_hash'] ?? ''),
            'contract_snapshot_url' => (string)($row['contract_snapshot_url'] ?? ''),
            'contract_acceptance_uuid' => $this->nullableString($row['contract_acceptance_uuid'] ?? null),
            'idempotency_key_hash' => $this->nullableString($row['idempotency_key_hash'] ?? null),
            'request_hash' => $this->nullableString($row['request_hash'] ?? null),
            'provider' => $this->nullableString($row['provider'] ?? null),
            'provider_checkout_id' => $this->nullableString($row['provider_checkout_id'] ?? null),
            'provider_payment_id' => $this->nullableString($row['provider_payment_id'] ?? null),
            'checkout_url' => $this->nullableString($row['checkout_url'] ?? null),
            'subscription_id' => $this->nullableString($row['subscription_id'] ?? null),
            'expires_at' => (string)($row['expires_at'] ?? ''),
            'completed_at' => $this->nullableString($row['completed_at'] ?? null),
            'cancelled_at' => $this->nullableString($row['cancelled_at'] ?? null),
            'activated_at' => $this->nullableString($row['activated_at'] ?? null),
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
                user_id,
                actor_role,
                plan_code,
                billing_period,
                amount_cents,
                currency,
                price_source,
                price_version,
                status,
                contract_version,
                contract_hash,
                contract_snapshot_url,
                contract_acceptance_uuid,
                idempotency_key_hash,
                request_hash,
                provider,
                provider_checkout_id,
                provider_payment_id,
                checkout_url,
                subscription_id,
                expires_at,
                completed_at,
                cancelled_at,
                activated_at,
                source,
                notes,
                created_at,
                updated_at,
                deleted_at';
    }

    private function requiredText($value, string $code, int $maxLength): string
    {
        $text = trim((string)($value ?? ''));
        if ($text === '' || strlen($text) > $maxLength) {
            throw new InvalidArgumentException($code);
        }
        return $text;
    }

    private function optionalText($value, int $maxLength): ?string
    {
        $text = trim((string)($value ?? ''));
        if ($text === '') {
            return null;
        }
        if (strlen($text) > $maxLength) {
            return substr($text, 0, $maxLength);
        }
        return $text;
    }

    private function requiredInt($value, string $code): int
    {
        if (is_int($value)) {
            return $value;
        }

        $text = trim((string)($value ?? ''));
        if ($text === '' || !ctype_digit($text)) {
            throw new InvalidArgumentException($code);
        }
        return (int)$text;
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
