<?php
declare(strict_types=1);

namespace Subscriptions\Services;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Subscriptions\Repositories\SubscriptionContractAcceptanceRepository;
use Throwable;

final class SubscriptionPendingPaymentAcceptanceException extends RuntimeException
{
    private string $errorCode;

    public function __construct(string $errorCode, string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->errorCode = $errorCode;
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }
}

final class CreateSubscriptionPendingPaymentAcceptanceService
{
    private const ACCEPTANCE_STATUS = 'accepted_pending_payment';
    private const CHECKOUT_SOURCE = 'checkout_intent';

    private SubscriptionContractAcceptanceRepository $acceptanceRepository;

    public function __construct(SubscriptionContractAcceptanceRepository $acceptanceRepository)
    {
        $this->acceptanceRepository = $acceptanceRepository;
    }

    public function createPendingPaymentAcceptance(array $input): array
    {
        $this->assertForcedValueIsAbsentOrNull(
            $input,
            'subscription_id',
            'pending_payment_acceptance_unexpected_subscription_id',
            'subscription_id must be null for pending payment acceptance'
        );
        $this->assertForcedTextIfPresent(
            $input,
            'status',
            self::ACCEPTANCE_STATUS,
            'pending_payment_acceptance_unexpected_status',
            'status must be accepted_pending_payment for pending payment acceptance'
        );
        $this->assertForcedTextIfPresent(
            $input,
            'source',
            self::CHECKOUT_SOURCE,
            'pending_payment_acceptance_unexpected_source',
            'source must be checkout_intent for pending payment acceptance'
        );
        $this->assertForcedTextIfPresent(
            $input,
            'acceptance_source',
            self::CHECKOUT_SOURCE,
            'acceptance_source_invalid',
            'acceptance_source must be checkout_intent'
        );

        $entityType = strtolower($this->requiredText($input['entity_type'] ?? null, 'invalid_pending_payment_acceptance_payload', 'entity_type is required', 64));
        $entityId = $this->requiredText($input['entity_id'] ?? null, 'invalid_pending_payment_acceptance_payload', 'entity_id is required', 64);
        $doctorId = $this->optionalText($input['doctor_id'] ?? null, 64);
        $profileId = $this->optionalText($input['profile_id'] ?? null, 64);
        $actorUserId = $this->requiredNumericText(
            $input['actor_user_id'] ?? ($input['user_id'] ?? null),
            'invalid_pending_payment_acceptance_payload',
            'actor_user_id is required'
        );
        $actorRole = $this->optionalText($input['actor_role'] ?? null, 32);
        $operatorId = $this->optionalNumericText($input['operator_id'] ?? null);
        $planCode = strtolower($this->requiredText($input['plan_code'] ?? null, 'invalid_pending_payment_acceptance_payload', 'plan_code is required', 64));
        $billingPeriod = strtolower($this->requiredText($input['billing_period'] ?? null, 'invalid_pending_payment_acceptance_payload', 'billing_period is required', 32));
        $durationDays = $this->optionalNonNegativeInt($input['duration_days'] ?? null);
        $contractVersion = $this->requiredText($input['contract_version'] ?? null, 'contract_invalid', 'contract_version is required', 64);
        $contractHash = $this->requiredText($input['contract_hash'] ?? null, 'contract_invalid', 'contract_hash is required', 128);
        $contractSnapshotUrl = $this->requiredText(
            $input['contract_snapshot_url'] ?? null,
            'contract_invalid',
            'contract_snapshot_url is required',
            512
        );
        $contractTitle = $this->optionalText($input['contract_title'] ?? null, 255);
        $ipAddress = $this->optionalText($input['ip_address'] ?? null, 45);
        $userAgent = $this->optionalText($input['user_agent'] ?? null, 512);
        $acceptedAt = $this->acceptedAt($input['accepted_at'] ?? null);
        $acceptanceUuid = $this->generateUuidV4();

        $data = [
            'uuid' => $acceptanceUuid,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'doctor_id' => $doctorId,
            'profile_id' => $profileId,
            'subscription_id' => null,
            'plan_code' => $planCode,
            'billing_period' => $billingPeriod,
            'duration_days' => $durationDays,
            'contract_version' => $contractVersion,
            'contract_hash' => $contractHash,
            'contract_snapshot_url' => $contractSnapshotUrl,
            'contract_title' => $contractTitle,
            'accepted_at' => $acceptedAt,
            'accepted_by_user_id' => (int)$actorUserId,
            'accepted_by_actor_role' => $actorRole,
            'accepted_by_operator_id' => $operatorId !== null ? (int)$operatorId : null,
            'acceptance_source' => self::CHECKOUT_SOURCE,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'status' => self::ACCEPTANCE_STATUS,
            'source' => self::CHECKOUT_SOURCE,
            'notes' => 'Created by pending payment acceptance service.',
        ];

        try {
            $this->acceptanceRepository->insert($data);
        } catch (Throwable $e) {
            throw new SubscriptionPendingPaymentAcceptanceException(
                'contract_acceptance_create_failed',
                'contract acceptance could not be created',
                $e
            );
        }

        return [
            'contract_acceptance_uuid' => $acceptanceUuid,
            'status' => self::ACCEPTANCE_STATUS,
            'subscription_id' => null,
            'source' => self::CHECKOUT_SOURCE,
            'contract_version' => $contractVersion,
            'contract_hash' => $contractHash,
            'contract_snapshot_url' => $contractSnapshotUrl,
            'accepted_at' => $acceptedAt,
            'created_at' => $acceptedAt,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'doctor_id' => $doctorId,
            'profile_id' => $profileId,
            'plan_code' => $planCode,
            'billing_period' => $billingPeriod,
        ];
    }

    private function assertForcedValueIsAbsentOrNull(array $input, string $field, string $code, string $message): void
    {
        if (array_key_exists($field, $input) && $input[$field] !== null) {
            throw new SubscriptionPendingPaymentAcceptanceException($code, $message);
        }
    }

    private function assertForcedTextIfPresent(
        array $input,
        string $field,
        string $expected,
        string $code,
        string $message
    ): void {
        if (!array_key_exists($field, $input) || $input[$field] === null) {
            return;
        }

        $text = strtolower(trim((string)$input[$field]));
        if ($text !== $expected) {
            throw new SubscriptionPendingPaymentAcceptanceException($code, $message);
        }
    }

    private function requiredText($value, string $code, string $message, int $maxLength): string
    {
        $text = trim((string)($value ?? ''));
        if ($text === '' || strlen($text) > $maxLength) {
            throw new SubscriptionPendingPaymentAcceptanceException($code, $message);
        }
        return $text;
    }

    private function requiredNumericText($value, string $code, string $message): string
    {
        $text = trim((string)($value ?? ''));
        if ($text === '' || !ctype_digit($text)) {
            throw new SubscriptionPendingPaymentAcceptanceException($code, $message);
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

    private function optionalNumericText($value): ?string
    {
        $text = trim((string)($value ?? ''));
        if ($text === '') {
            return null;
        }
        if (!ctype_digit($text)) {
            throw new SubscriptionPendingPaymentAcceptanceException(
                'invalid_pending_payment_acceptance_payload',
                'operator_id must be numeric'
            );
        }
        return $text;
    }

    private function optionalNonNegativeInt($value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }
        if (!is_numeric($value) || (int)$value < 0) {
            throw new SubscriptionPendingPaymentAcceptanceException(
                'invalid_pending_payment_acceptance_payload',
                'duration_days must be a non-negative integer'
            );
        }
        return (int)$value;
    }

    private function acceptedAt($value): string
    {
        if ($value === null || trim((string)$value) === '') {
            return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        }

        try {
            return (new DateTimeImmutable((string)$value, new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        } catch (Throwable $e) {
            throw new SubscriptionPendingPaymentAcceptanceException(
                'invalid_pending_payment_acceptance_payload',
                'accepted_at is invalid',
                $e
            );
        }
    }

    private function generateUuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
