<?php
declare(strict_types=1);

namespace Subscriptions\Repositories;

use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

final class SubscriptionPaymentRouteRepository
{
    private const STATUS_CREATED_NO_PROVIDER = 'created_no_provider';
    public const STATUS_CHECKOUT_CREATED_NO_PROVIDER = 'checkout_created_no_provider';

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function create(array $snapshot): array
    {
        $uuid = $this->requiredText($snapshot['uuid'] ?? null, 'invalid_payment_route_payload', 36);
        $entityType = $this->requiredText($snapshot['entity_type'] ?? null, 'invalid_payment_route_payload', 64);
        $entityId = $this->requiredText($snapshot['entity_id'] ?? null, 'invalid_payment_route_payload', 64);
        $routeType = $this->requiredText($snapshot['route_type'] ?? null, 'invalid_payment_route_payload', 64);
        $billingPeriod = $this->requiredText($snapshot['billing_period'] ?? null, 'invalid_payment_route_payload', 32);
        $paymentMethodFamily = $this->requiredText($snapshot['payment_method_family'] ?? null, 'invalid_payment_route_payload', 32);
        $autoRenewStatus = $this->requiredText($snapshot['auto_renew_status'] ?? null, 'invalid_payment_route_payload', 64);
        $amountCents = $this->requiredInt($snapshot['amount_cents'] ?? null, 'invalid_payment_route_payload');
        $currency = strtoupper($this->requiredText($snapshot['currency'] ?? null, 'invalid_payment_route_payload', 3));
        $amountSource = $this->requiredText($snapshot['amount_source'] ?? null, 'invalid_payment_route_payload', 64);
        $status = $this->requiredText($snapshot['status'] ?? null, 'invalid_payment_route_payload', 64);
        $providerStatus = $this->requiredText($snapshot['provider_status'] ?? null, 'invalid_payment_route_payload', 64);
        $nextActionType = $this->requiredText($snapshot['next_action_type'] ?? null, 'invalid_payment_route_payload', 96);
        $requestHash = $this->requiredText($snapshot['request_hash'] ?? null, 'invalid_payment_route_payload', 64);
        $expiresAt = $this->requiredText($snapshot['expires_at'] ?? null, 'invalid_payment_route_payload', 19);

        if ($status !== self::STATUS_CREATED_NO_PROVIDER) {
            throw new InvalidArgumentException('invalid_payment_route_payload: status must be created_no_provider');
        }
        if ($amountCents < 0) {
            throw new InvalidArgumentException('invalid_payment_route_payload: amount_cents must be non-negative');
        }

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO subscription_payment_routes (
                    uuid,
                    entity_type,
                    entity_id,
                    doctor_id,
                    profile_id,
                    user_id,
                    actor_role,
                    route_type,
                    current_plan_code,
                    target_plan_code,
                    billing_period,
                    payment_method_family,
                    auto_renew_requested,
                    auto_renew_status,
                    amount_cents,
                    currency,
                    amount_source,
                    frontend_amount_cents,
                    amount_mismatch,
                    current_price_cents,
                    target_price_cents,
                    adjustment_amount_cents,
                    renewal_amount_cents,
                    remaining_days,
                    period_days,
                    renewal_duration_days,
                    current_expires_at,
                    estimated_next_expires_at,
                    status,
                    provider,
                    provider_status,
                    next_action_type,
                    next_action_enabled,
                    idempotency_key,
                    idempotency_key_hash,
                    request_hash,
                    frontend_summary_snapshot_json,
                    server_preview_snapshot_json,
                    warnings_json,
                    reasons_json,
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
                    :route_type,
                    :current_plan_code,
                    :target_plan_code,
                    :billing_period,
                    :payment_method_family,
                    :auto_renew_requested,
                    :auto_renew_status,
                    :amount_cents,
                    :currency,
                    :amount_source,
                    :frontend_amount_cents,
                    :amount_mismatch,
                    :current_price_cents,
                    :target_price_cents,
                    :adjustment_amount_cents,
                    :renewal_amount_cents,
                    :remaining_days,
                    :period_days,
                    :renewal_duration_days,
                    :current_expires_at,
                    :estimated_next_expires_at,
                    :status,
                    :provider,
                    :provider_status,
                    :next_action_type,
                    :next_action_enabled,
                    :idempotency_key,
                    :idempotency_key_hash,
                    :request_hash,
                    :frontend_summary_snapshot_json,
                    :server_preview_snapshot_json,
                    :warnings_json,
                    :reasons_json,
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
                'doctor_id' => $this->optionalText($snapshot['doctor_id'] ?? null, 64),
                'profile_id' => $this->optionalText($snapshot['profile_id'] ?? null, 64),
                'user_id' => $this->optionalInt($snapshot['user_id'] ?? null),
                'actor_role' => $this->optionalText($snapshot['actor_role'] ?? null, 32),
                'route_type' => $routeType,
                'current_plan_code' => $this->optionalText($snapshot['current_plan_code'] ?? null, 64),
                'target_plan_code' => $this->optionalText($snapshot['target_plan_code'] ?? null, 64),
                'billing_period' => $billingPeriod,
                'payment_method_family' => $paymentMethodFamily,
                'auto_renew_requested' => $this->boolInt($snapshot['auto_renew_requested'] ?? false),
                'auto_renew_status' => $autoRenewStatus,
                'amount_cents' => $amountCents,
                'currency' => $currency,
                'amount_source' => $amountSource,
                'frontend_amount_cents' => $this->optionalInt($snapshot['frontend_amount_cents'] ?? null),
                'amount_mismatch' => $this->boolInt($snapshot['amount_mismatch'] ?? false),
                'current_price_cents' => $this->optionalInt($snapshot['current_price_cents'] ?? null),
                'target_price_cents' => $this->optionalInt($snapshot['target_price_cents'] ?? null),
                'adjustment_amount_cents' => $this->optionalInt($snapshot['adjustment_amount_cents'] ?? null),
                'renewal_amount_cents' => $this->optionalInt($snapshot['renewal_amount_cents'] ?? null),
                'remaining_days' => $this->optionalInt($snapshot['remaining_days'] ?? null),
                'period_days' => $this->optionalInt($snapshot['period_days'] ?? null),
                'renewal_duration_days' => $this->optionalInt($snapshot['renewal_duration_days'] ?? null),
                'current_expires_at' => $this->optionalText($snapshot['current_expires_at'] ?? null, 19),
                'estimated_next_expires_at' => $this->optionalText($snapshot['estimated_next_expires_at'] ?? null, 19),
                'status' => $status,
                'provider' => $this->optionalText($snapshot['provider'] ?? null, 64),
                'provider_status' => $providerStatus,
                'next_action_type' => $nextActionType,
                'next_action_enabled' => $this->boolInt($snapshot['next_action_enabled'] ?? false),
                'idempotency_key' => $this->optionalText($snapshot['idempotency_key'] ?? null, 128),
                'idempotency_key_hash' => $this->optionalText($snapshot['idempotency_key_hash'] ?? null, 64),
                'request_hash' => $requestHash,
                'frontend_summary_snapshot_json' => $this->optionalText($snapshot['frontend_summary_snapshot_json'] ?? null, 65535),
                'server_preview_snapshot_json' => $this->optionalText($snapshot['server_preview_snapshot_json'] ?? null, 65535),
                'warnings_json' => $this->optionalText($snapshot['warnings_json'] ?? null, 65535),
                'reasons_json' => $this->optionalText($snapshot['reasons_json'] ?? null, 65535),
                'expires_at' => $expiresAt,
                'source' => $this->optionalText($snapshot['source'] ?? 'mxmed_subscription_payment_route_v1', 128),
                'notes' => $this->optionalText($snapshot['notes'] ?? null, 65535),
            ]);
        } catch (Throwable $e) {
            throw new RuntimeException('payment_route_create_failed', 0, $e);
        }

        $created = $this->findByUuid($uuid);
        if ($created === null) {
            throw new RuntimeException('payment_route_lookup_failed');
        }

        return $created;
    }

    public function findActiveConflict(
        string $entityType,
        string $entityId,
        string $routeType,
        ?string $currentPlanCode,
        ?string $targetPlanCode,
        string $billingPeriod
    ): ?array {
        return $this->findOne(
            'SELECT ' . $this->selectColumns() . '
             FROM subscription_payment_routes
             WHERE entity_type = :entity_type
               AND entity_id = :entity_id
               AND route_type = :route_type
               AND COALESCE(current_plan_code, \'\') = :current_plan_code
               AND COALESCE(target_plan_code, \'\') = :target_plan_code
               AND billing_period = :billing_period
               AND status = :status
               AND expires_at >= UTC_TIMESTAMP()
               AND deleted_at IS NULL
             ORDER BY created_at DESC, id DESC
             LIMIT 1',
            [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'route_type' => $routeType,
                'current_plan_code' => $currentPlanCode ?? '',
                'target_plan_code' => $targetPlanCode ?? '',
                'billing_period' => $billingPeriod,
                'status' => self::STATUS_CREATED_NO_PROVIDER,
            ]
        );
    }

    public function findByUuid(string $uuid): ?array
    {
        $uuid = trim($uuid);
        if ($uuid === '') {
            throw new InvalidArgumentException('invalid_payment_route_payload: uuid is required');
        }

        return $this->findOne(
            'SELECT ' . $this->selectColumns() . '
             FROM subscription_payment_routes
             WHERE uuid = :uuid
               AND deleted_at IS NULL
             LIMIT 1',
            ['uuid' => $uuid]
        );
    }

    public function markCheckoutCreated(string $paymentRouteUuid, string $checkoutIntentUuid): ?array
    {
        $paymentRouteUuid = trim($paymentRouteUuid);
        $checkoutIntentUuid = trim($checkoutIntentUuid);
        if ($paymentRouteUuid === '' || $checkoutIntentUuid === '') {
            throw new InvalidArgumentException('invalid_payment_route_payload: route and checkout uuid are required');
        }

        try {
            $stmt = $this->pdo->prepare(
                'UPDATE subscription_payment_routes
                 SET status = :next_status,
                     checkout_intent_uuid = :checkout_intent_uuid,
                     checkout_created_at = UTC_TIMESTAMP(),
                     consumed_at = UTC_TIMESTAMP(),
                     provider_status = :provider_status,
                     next_action_type = :next_action_type,
                     next_action_enabled = 0
                 WHERE uuid = :uuid
                   AND status = :current_status
                   AND deleted_at IS NULL'
            );
            $stmt->execute([
                'uuid' => $paymentRouteUuid,
                'checkout_intent_uuid' => $checkoutIntentUuid,
                'current_status' => self::STATUS_CREATED_NO_PROVIDER,
                'next_status' => self::STATUS_CHECKOUT_CREATED_NO_PROVIDER,
                'provider_status' => 'not_created',
                'next_action_type' => 'payment_intent_provider_pending',
            ]);
        } catch (Throwable $e) {
            throw new RuntimeException('payment_route_checkout_link_failed', 0, $e);
        }

        if ($stmt->rowCount() < 1) {
            return null;
        }

        return $this->findByUuid($paymentRouteUuid);
    }

    private function findOne(string $sql, array $params): ?array
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            throw new RuntimeException('payment_route_lookup_failed', 0, $e);
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
            'route_type' => (string)($row['route_type'] ?? ''),
            'current_plan_code' => $this->nullableString($row['current_plan_code'] ?? null),
            'target_plan_code' => $this->nullableString($row['target_plan_code'] ?? null),
            'billing_period' => (string)($row['billing_period'] ?? ''),
            'payment_method_family' => (string)($row['payment_method_family'] ?? ''),
            'auto_renew_requested' => (bool)((int)($row['auto_renew_requested'] ?? 0)),
            'auto_renew_status' => (string)($row['auto_renew_status'] ?? ''),
            'amount_cents' => isset($row['amount_cents']) ? (int)$row['amount_cents'] : 0,
            'currency' => (string)($row['currency'] ?? ''),
            'amount_source' => (string)($row['amount_source'] ?? ''),
            'frontend_amount_cents' => $this->nullableInt($row['frontend_amount_cents'] ?? null),
            'amount_mismatch' => (bool)((int)($row['amount_mismatch'] ?? 0)),
            'current_price_cents' => $this->nullableInt($row['current_price_cents'] ?? null),
            'target_price_cents' => $this->nullableInt($row['target_price_cents'] ?? null),
            'adjustment_amount_cents' => $this->nullableInt($row['adjustment_amount_cents'] ?? null),
            'renewal_amount_cents' => $this->nullableInt($row['renewal_amount_cents'] ?? null),
            'remaining_days' => $this->nullableInt($row['remaining_days'] ?? null),
            'period_days' => $this->nullableInt($row['period_days'] ?? null),
            'renewal_duration_days' => $this->nullableInt($row['renewal_duration_days'] ?? null),
            'current_expires_at' => $this->nullableString($row['current_expires_at'] ?? null),
            'estimated_next_expires_at' => $this->nullableString($row['estimated_next_expires_at'] ?? null),
            'status' => (string)($row['status'] ?? ''),
            'provider' => $this->nullableString($row['provider'] ?? null),
            'provider_status' => (string)($row['provider_status'] ?? ''),
            'next_action_type' => (string)($row['next_action_type'] ?? ''),
            'next_action_enabled' => (bool)((int)($row['next_action_enabled'] ?? 0)),
            'checkout_intent_uuid' => $this->nullableString($row['checkout_intent_uuid'] ?? null),
            'checkout_created_at' => $this->nullableString($row['checkout_created_at'] ?? null),
            'consumed_at' => $this->nullableString($row['consumed_at'] ?? null),
            'idempotency_key' => $this->nullableString($row['idempotency_key'] ?? null),
            'idempotency_key_hash' => $this->nullableString($row['idempotency_key_hash'] ?? null),
            'request_hash' => (string)($row['request_hash'] ?? ''),
            'frontend_summary_snapshot_json' => $this->nullableString($row['frontend_summary_snapshot_json'] ?? null),
            'server_preview_snapshot_json' => $this->nullableString($row['server_preview_snapshot_json'] ?? null),
            'warnings_json' => $this->nullableString($row['warnings_json'] ?? null),
            'reasons_json' => $this->nullableString($row['reasons_json'] ?? null),
            'expires_at' => (string)($row['expires_at'] ?? ''),
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
                route_type,
                current_plan_code,
                target_plan_code,
                billing_period,
                payment_method_family,
                auto_renew_requested,
                auto_renew_status,
                amount_cents,
                currency,
                amount_source,
                frontend_amount_cents,
                amount_mismatch,
                current_price_cents,
                target_price_cents,
                adjustment_amount_cents,
                renewal_amount_cents,
                remaining_days,
                period_days,
                renewal_duration_days,
                current_expires_at,
                estimated_next_expires_at,
                status,
                provider,
                provider_status,
                next_action_type,
                next_action_enabled,
                checkout_intent_uuid,
                checkout_created_at,
                consumed_at,
                idempotency_key,
                idempotency_key_hash,
                request_hash,
                frontend_summary_snapshot_json,
                server_preview_snapshot_json,
                warnings_json,
                reasons_json,
                expires_at,
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

    private function optionalInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_int($value)) {
            return $value;
        }

        $text = trim((string)$value);
        return ctype_digit($text) ? (int)$text : null;
    }

    private function nullableInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (int)$value;
    }

    private function nullableString($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = (string)$value;
        return $text === '' ? null : $text;
    }

    private function boolInt($value): int
    {
        return (bool)$value ? 1 : 0;
    }
}
