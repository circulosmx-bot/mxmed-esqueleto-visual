<?php
declare(strict_types=1);

namespace Subscriptions\Repositories;

use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

final class ProfileSubscriptionRepository
{
    private const STATUS_ACTIVE = 'active';
    private const STATUS_RENEWED = 'renewed';

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function createActiveFromPaidCheckout(array $snapshot): array
    {
        $subscriptionId = $this->optionalText($snapshot['subscription_id'] ?? null, 36) ?? $this->uuidV4();
        $entityType = $this->requiredText($snapshot['entity_type'] ?? null, 'invalid_profile_subscription_payload', 64);
        $entityId = $this->requiredText($snapshot['entity_id'] ?? null, 'invalid_profile_subscription_payload', 64);
        $doctorId = $this->optionalText($snapshot['doctor_id'] ?? null, 64);
        $profileId = $this->optionalText($snapshot['profile_id'] ?? null, 64);
        $planCode = $this->requiredText($snapshot['plan_code'] ?? null, 'invalid_profile_subscription_payload', 64);
        $planLabel = $this->optionalText($snapshot['plan_label'] ?? null, 120);
        $billingPeriod = $this->requiredText($snapshot['billing_period'] ?? null, 'invalid_profile_subscription_payload', 32);
        $durationDays = $this->requiredInt($snapshot['duration_days'] ?? null, 'invalid_profile_subscription_payload');
        $contractedPlanCode = $this->optionalText($snapshot['contracted_plan_code'] ?? null, 64) ?? $planCode;
        $effectivePlanCode = $this->optionalText($snapshot['effective_plan_code'] ?? null, 64) ?? $planCode;
        $contractVersion = $this->optionalText($snapshot['contract_version'] ?? null, 64);
        $contractAcceptedAt = $this->optionalText($snapshot['contract_accepted_at'] ?? null, 19);
        $contractAcceptedByUserId = $this->optionalText($snapshot['contract_accepted_by_user_id'] ?? null, 64);
        $contractAcceptanceSource = $this->optionalText($snapshot['contract_acceptance_source'] ?? null, 64);
        $contractAcceptanceIp = $this->optionalText($snapshot['contract_acceptance_ip'] ?? null, 45);
        $contractAcceptanceUserAgent = $this->optionalText($snapshot['contract_acceptance_user_agent'] ?? null, 255);
        $startsAt = $this->requiredText($snapshot['starts_at'] ?? null, 'invalid_profile_subscription_payload', 19);
        $expiresAt = $this->requiredText($snapshot['expires_at'] ?? null, 'invalid_profile_subscription_payload', 19);
        $status = $this->requiredText($snapshot['status'] ?? null, 'invalid_profile_subscription_payload', 32);
        $autoRenew = $this->optionalBoolInt($snapshot['auto_renew'] ?? null);
        $renewedFromSubscriptionId = $this->optionalText($snapshot['renewed_from_subscription_id'] ?? null, 36);
        $renewedToSubscriptionId = $this->optionalText($snapshot['renewed_to_subscription_id'] ?? null, 36);
        $source = $this->optionalText($snapshot['source'] ?? null, 64) ?? 'mxmed_subscription_activation_v1';
        $notes = $this->optionalText($snapshot['notes'] ?? null, 65535);

        if ($durationDays <= 0) {
            throw new InvalidArgumentException('invalid_profile_subscription_payload: duration_days must be positive');
        }
        if ($status !== self::STATUS_ACTIVE) {
            throw new InvalidArgumentException('invalid_profile_subscription_payload: status must be active');
        }

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO profile_subscriptions (
                    subscription_id,
                    entity_type,
                    entity_id,
                    doctor_id,
                    profile_id,
                    plan_code,
                    plan_label,
                    billing_period,
                    duration_days,
                    contracted_plan_code,
                    effective_plan_code,
                    contract_version,
                    contract_accepted_at,
                    contract_accepted_by_user_id,
                    contract_acceptance_source,
                    contract_acceptance_ip,
                    contract_acceptance_user_agent,
                    starts_at,
                    expires_at,
                    status,
                    auto_renew,
                    renewed_from_subscription_id,
                    renewed_to_subscription_id,
                    source,
                    notes,
                    deleted_at
                ) VALUES (
                    :subscription_id,
                    :entity_type,
                    :entity_id,
                    :doctor_id,
                    :profile_id,
                    :plan_code,
                    :plan_label,
                    :billing_period,
                    :duration_days,
                    :contracted_plan_code,
                    :effective_plan_code,
                    :contract_version,
                    :contract_accepted_at,
                    :contract_accepted_by_user_id,
                    :contract_acceptance_source,
                    :contract_acceptance_ip,
                    :contract_acceptance_user_agent,
                    :starts_at,
                    :expires_at,
                    :status,
                    :auto_renew,
                    :renewed_from_subscription_id,
                    :renewed_to_subscription_id,
                    :source,
                    :notes,
                    NULL
                )'
            );
            $stmt->execute([
                'subscription_id' => $subscriptionId,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'doctor_id' => $doctorId,
                'profile_id' => $profileId,
                'plan_code' => $planCode,
                'plan_label' => $planLabel,
                'billing_period' => $billingPeriod,
                'duration_days' => $durationDays,
                'contracted_plan_code' => $contractedPlanCode,
                'effective_plan_code' => $effectivePlanCode,
                'contract_version' => $contractVersion,
                'contract_accepted_at' => $contractAcceptedAt,
                'contract_accepted_by_user_id' => $contractAcceptedByUserId,
                'contract_acceptance_source' => $contractAcceptanceSource,
                'contract_acceptance_ip' => $contractAcceptanceIp,
                'contract_acceptance_user_agent' => $contractAcceptanceUserAgent,
                'starts_at' => $startsAt,
                'expires_at' => $expiresAt,
                'status' => $status,
                'auto_renew' => $autoRenew,
                'renewed_from_subscription_id' => $renewedFromSubscriptionId,
                'renewed_to_subscription_id' => $renewedToSubscriptionId,
                'source' => $source,
                'notes' => $notes,
            ]);
        } catch (Throwable $e) {
            throw new RuntimeException('profile_subscription_create_failed', 0, $e);
        }

        $created = $this->findBySubscriptionId($subscriptionId);
        if ($created === null) {
            throw new RuntimeException('profile_subscription_lookup_failed');
        }

        return $created;
    }

    public function markRenewedTo(string $subscriptionId, string $renewedToSubscriptionId, array $metadata = []): ?array
    {
        $subscriptionId = trim($subscriptionId);
        $renewedToSubscriptionId = trim($renewedToSubscriptionId);
        if ($subscriptionId === '' || $renewedToSubscriptionId === '') {
            throw new InvalidArgumentException('invalid_profile_subscription_payload: subscription ids are required');
        }

        $notes = $this->optionalText($metadata['notes'] ?? null, 65535);

        try {
            $stmt = $this->pdo->prepare(
                'UPDATE profile_subscriptions
                 SET status = :renewed_status,
                     renewed_to_subscription_id = :renewed_to_subscription_id,
                     notes = CASE
                         WHEN :notes_guard IS NULL THEN notes
                         WHEN notes IS NULL OR notes = "" THEN :notes_value
                         ELSE CONCAT(notes, "\n", :notes_append)
                     END
                 WHERE subscription_id = :subscription_id
                   AND renewed_to_subscription_id IS NULL
                   AND status IN ("active", "expiring_soon", "grace_period")
                   AND deleted_at IS NULL'
            );
            $stmt->execute([
                'subscription_id' => $subscriptionId,
                'renewed_to_subscription_id' => $renewedToSubscriptionId,
                'renewed_status' => self::STATUS_RENEWED,
                'notes_guard' => $notes,
                'notes_value' => $notes,
                'notes_append' => $notes,
            ]);
        } catch (Throwable $e) {
            throw new RuntimeException('profile_subscription_upgrade_link_failed', 0, $e);
        }

        if ($stmt->rowCount() < 1) {
            return null;
        }

        return $this->findBySubscriptionId($subscriptionId);
    }

    public function findBySubscriptionId(string $subscriptionId): ?array
    {
        $subscriptionId = trim($subscriptionId);
        if ($subscriptionId === '') {
            throw new InvalidArgumentException('invalid_profile_subscription_payload: subscription_id is required');
        }

        return $this->findOne(
            'SELECT ' . $this->selectColumns() . '
             FROM profile_subscriptions
             WHERE subscription_id = :subscription_id
               AND deleted_at IS NULL
             LIMIT 1',
            ['subscription_id' => $subscriptionId]
        );
    }

    private function findOne(string $sql, array $params): ?array
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            throw new RuntimeException('profile_subscription_lookup_failed', 0, $e);
        }

        return is_array($row) ? $this->normalizeRow($row) : null;
    }

    private function normalizeRow(array $row): array
    {
        return [
            'id' => isset($row['id']) ? (int)$row['id'] : null,
            'subscription_id' => (string)($row['subscription_id'] ?? ''),
            'entity_type' => (string)($row['entity_type'] ?? ''),
            'entity_id' => (string)($row['entity_id'] ?? ''),
            'doctor_id' => $this->nullableString($row['doctor_id'] ?? null),
            'profile_id' => $this->nullableString($row['profile_id'] ?? null),
            'plan_code' => (string)($row['plan_code'] ?? ''),
            'plan_label' => $this->nullableString($row['plan_label'] ?? null),
            'billing_period' => (string)($row['billing_period'] ?? ''),
            'duration_days' => isset($row['duration_days']) ? (int)$row['duration_days'] : null,
            'contracted_plan_code' => (string)($row['contracted_plan_code'] ?? ''),
            'effective_plan_code' => (string)($row['effective_plan_code'] ?? ''),
            'contract_version' => $this->nullableString($row['contract_version'] ?? null),
            'contract_accepted_at' => $this->nullableString($row['contract_accepted_at'] ?? null),
            'contract_accepted_by_user_id' => $this->nullableString($row['contract_accepted_by_user_id'] ?? null),
            'contract_acceptance_source' => $this->nullableString($row['contract_acceptance_source'] ?? null),
            'contract_acceptance_ip' => $this->nullableString($row['contract_acceptance_ip'] ?? null),
            'contract_acceptance_user_agent' => $this->nullableString($row['contract_acceptance_user_agent'] ?? null),
            'starts_at' => $this->nullableString($row['starts_at'] ?? null),
            'expires_at' => $this->nullableString($row['expires_at'] ?? null),
            'grace_starts_at' => $this->nullableString($row['grace_starts_at'] ?? null),
            'grace_ends_at' => $this->nullableString($row['grace_ends_at'] ?? null),
            'status' => (string)($row['status'] ?? ''),
            'auto_renew' => isset($row['auto_renew']) ? (int)$row['auto_renew'] : null,
            'cancelled_at' => $this->nullableString($row['cancelled_at'] ?? null),
            'renewed_from_subscription_id' => $this->nullableString($row['renewed_from_subscription_id'] ?? null),
            'renewed_to_subscription_id' => $this->nullableString($row['renewed_to_subscription_id'] ?? null),
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
                subscription_id,
                entity_type,
                entity_id,
                doctor_id,
                profile_id,
                plan_code,
                plan_label,
                billing_period,
                duration_days,
                contracted_plan_code,
                effective_plan_code,
                contract_version,
                contract_accepted_at,
                contract_accepted_by_user_id,
                contract_acceptance_source,
                contract_acceptance_ip,
                contract_acceptance_user_agent,
                starts_at,
                expires_at,
                grace_starts_at,
                grace_ends_at,
                status,
                auto_renew,
                cancelled_at,
                renewed_from_subscription_id,
                renewed_to_subscription_id,
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

    private function optionalBoolInt($value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }
        if (is_int($value)) {
            return $value === 1 ? 1 : 0;
        }

        $text = trim((string)$value);
        return $text === '1' || strtolower($text) === 'true' ? 1 : 0;
    }

    private function nullableString($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = (string)$value;
        return $text === '' ? null : $text;
    }

    private function uuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
