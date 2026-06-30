<?php
declare(strict_types=1);

namespace Subscriptions\Repositories;

use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

final class SubscriptionPaymentEventRepository
{
    private const MOCK_PROVIDER = 'mxmed_mock';
    private const CONFIRM_MOCK_EVENT_TYPE = 'payment_intent_confirm';
    private const PROCESSING_STATUS_PROCESSED = 'processed';
    private const PROCESSING_STATUS_FAILED = 'failed';

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findConfirmMockByPaymentIntentUuid(string $paymentIntentUuid): ?array
    {
        $paymentIntentUuid = trim($paymentIntentUuid);
        if ($paymentIntentUuid === '') {
            throw new InvalidArgumentException('invalid_payment_event_payload: payment_intent_uuid is required');
        }

        return $this->findOne(
            'SELECT ' . $this->selectColumns() . '
             FROM subscription_payment_events
             WHERE payment_intent_uuid = :payment_intent_uuid
               AND provider = :provider
               AND event_type = :event_type
               AND deleted_at IS NULL
             ORDER BY id DESC
             LIMIT 1',
            [
                'payment_intent_uuid' => $paymentIntentUuid,
                'provider' => self::MOCK_PROVIDER,
                'event_type' => self::CONFIRM_MOCK_EVENT_TYPE,
            ]
        );
    }

    public function findProcessedConfirmByPaymentIntentUuid(string $paymentIntentUuid): ?array
    {
        $paymentIntentUuid = trim($paymentIntentUuid);
        if ($paymentIntentUuid === '') {
            return null;
        }

        return $this->findOne(
            'SELECT ' . $this->selectColumns() . '
             FROM subscription_payment_events
             WHERE payment_intent_uuid = :payment_intent_uuid
               AND event_type = :event_type
               AND processing_status = :processing_status
               AND deleted_at IS NULL
             ORDER BY processed_at DESC, id DESC
             LIMIT 1',
            [
                'payment_intent_uuid' => $paymentIntentUuid,
                'event_type' => self::CONFIRM_MOCK_EVENT_TYPE,
                'processing_status' => self::PROCESSING_STATUS_PROCESSED,
            ]
        );
    }

    public function findProcessedConfirmByCheckoutIntentUuid(string $checkoutIntentUuid): ?array
    {
        $checkoutIntentUuid = trim($checkoutIntentUuid);
        if ($checkoutIntentUuid === '') {
            return null;
        }

        return $this->findOne(
            'SELECT ' . $this->selectColumns() . '
             FROM subscription_payment_events
             WHERE checkout_intent_uuid = :checkout_intent_uuid
               AND event_type = :event_type
               AND processing_status = :processing_status
               AND deleted_at IS NULL
             ORDER BY processed_at DESC, id DESC
             LIMIT 1',
            [
                'checkout_intent_uuid' => $checkoutIntentUuid,
                'event_type' => self::CONFIRM_MOCK_EVENT_TYPE,
                'processing_status' => self::PROCESSING_STATUS_PROCESSED,
            ]
        );
    }

    public function create(array $input): array
    {
        $uuid = $this->requiredText($input['uuid'] ?? null, 'invalid_payment_event_payload', 36);
        $checkoutIntentUuid = $this->optionalText($input['checkout_intent_uuid'] ?? null, 36);
        $paymentIntentUuid = $this->optionalText($input['payment_intent_uuid'] ?? null, 36);
        $provider = $this->requiredText($input['provider'] ?? null, 'invalid_payment_event_payload', 64);
        $providerEventId = $this->requiredText($input['provider_event_id'] ?? null, 'invalid_payment_event_payload', 128);
        $providerPaymentId = $this->optionalText($input['provider_payment_id'] ?? null, 128);
        $eventType = $this->requiredText($input['event_type'] ?? null, 'invalid_payment_event_payload', 128);
        $providerStatus = $this->optionalText($input['provider_status'] ?? null, 64);
        $normalizedStatus = $this->optionalText($input['normalized_status'] ?? null, 32);
        $amountCents = $this->optionalInt($input['amount_cents'] ?? null, 'invalid_payment_event_payload');
        $currency = $this->optionalCurrency($input['currency'] ?? null);
        $eventHash = $this->requiredText($input['event_hash'] ?? null, 'invalid_payment_event_payload', 64);
        $signatureValidatedAt = $this->optionalText($input['signature_validated_at'] ?? null, 19);
        $receivedAt = $this->requiredText($input['received_at'] ?? null, 'invalid_payment_event_payload', 19);
        $processedAt = $this->optionalText($input['processed_at'] ?? null, 19);
        $processingStatus = $this->requiredText(
            $input['processing_status'] ?? null,
            'invalid_payment_event_payload',
            32
        );
        $errorMessage = $this->optionalText($input['error_message'] ?? null, 65535);
        $payloadTextSanitized = $this->optionalText($input['payload_text_sanitized'] ?? null, 65535);
        $source = $this->requiredText($input['source'] ?? null, 'invalid_payment_event_payload', 128);
        $notes = $this->optionalText($input['notes'] ?? null, 65535);

        if ($amountCents !== null && $amountCents < 0) {
            throw new InvalidArgumentException('invalid_payment_event_payload: amount_cents must be non-negative');
        }

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO subscription_payment_events (
                    uuid,
                    checkout_intent_uuid,
                    payment_intent_uuid,
                    provider,
                    provider_event_id,
                    provider_payment_id,
                    event_type,
                    provider_status,
                    normalized_status,
                    amount_cents,
                    currency,
                    event_hash,
                    signature_validated_at,
                    received_at,
                    processed_at,
                    processing_status,
                    error_message,
                    payload_text_sanitized,
                    source,
                    notes,
                    deleted_at
                ) VALUES (
                    :uuid,
                    :checkout_intent_uuid,
                    :payment_intent_uuid,
                    :provider,
                    :provider_event_id,
                    :provider_payment_id,
                    :event_type,
                    :provider_status,
                    :normalized_status,
                    :amount_cents,
                    :currency,
                    :event_hash,
                    :signature_validated_at,
                    :received_at,
                    :processed_at,
                    :processing_status,
                    :error_message,
                    :payload_text_sanitized,
                    :source,
                    :notes,
                    NULL
                )'
            );
            $stmt->execute([
                'uuid' => $uuid,
                'checkout_intent_uuid' => $checkoutIntentUuid,
                'payment_intent_uuid' => $paymentIntentUuid,
                'provider' => $provider,
                'provider_event_id' => $providerEventId,
                'provider_payment_id' => $providerPaymentId,
                'event_type' => $eventType,
                'provider_status' => $providerStatus,
                'normalized_status' => $normalizedStatus,
                'amount_cents' => $amountCents,
                'currency' => $currency,
                'event_hash' => $eventHash,
                'signature_validated_at' => $signatureValidatedAt,
                'received_at' => $receivedAt,
                'processed_at' => $processedAt,
                'processing_status' => $processingStatus,
                'error_message' => $errorMessage,
                'payload_text_sanitized' => $payloadTextSanitized,
                'source' => $source,
                'notes' => $notes,
            ]);
        } catch (Throwable $e) {
            throw new RuntimeException('payment_event_create_failed', 0, $e);
        }

        $created = $this->findByUuid($uuid);
        if ($created === null) {
            throw new RuntimeException('payment_event_lookup_failed');
        }

        return $created;
    }

    public function createProcessedProviderEvent(array $input): array
    {
        $event = $input;
        $event['processing_status'] = self::PROCESSING_STATUS_PROCESSED;
        $event['processed_at'] = $this->processedAt($event);
        $event['error_message'] = null;

        return $this->create($event);
    }

    public function createFailedProviderEvent(array $input): array
    {
        $event = $input;
        $event['processing_status'] = self::PROCESSING_STATUS_FAILED;
        $event['processed_at'] = $this->processedAt($event);
        $event['error_message'] = $this->requiredText(
            $event['error_message'] ?? null,
            'invalid_payment_event_payload',
            65535
        );

        return $this->create($event);
    }

    public function findByUuid(string $uuid): ?array
    {
        $uuid = trim($uuid);
        if ($uuid === '') {
            throw new InvalidArgumentException('invalid_payment_event_payload: uuid is required');
        }

        return $this->findOne(
            'SELECT ' . $this->selectColumns() . '
             FROM subscription_payment_events
             WHERE uuid = :uuid
               AND deleted_at IS NULL
             LIMIT 1',
            ['uuid' => $uuid]
        );
    }

    public function findByProviderEventId(string $provider, string $providerEventId): ?array
    {
        $provider = trim($provider);
        $providerEventId = trim($providerEventId);
        if ($provider === '' || $providerEventId === '') {
            throw new InvalidArgumentException('invalid_payment_event_payload: provider and provider_event_id are required');
        }

        return $this->findOne(
            'SELECT ' . $this->selectColumns() . '
             FROM subscription_payment_events
             WHERE provider = :provider
               AND provider_event_id = :provider_event_id
               AND deleted_at IS NULL
             LIMIT 1',
            [
                'provider' => $provider,
                'provider_event_id' => $providerEventId,
            ]
        );
    }

    public function findByEventHash(string $provider, string $eventHash): ?array
    {
        $provider = trim($provider);
        $eventHash = trim($eventHash);
        if ($provider === '' || $eventHash === '') {
            throw new InvalidArgumentException('invalid_payment_event_payload: provider and event_hash are required');
        }

        return $this->findOne(
            'SELECT ' . $this->selectColumns() . '
             FROM subscription_payment_events
             WHERE provider = :provider
               AND event_hash = :event_hash
               AND deleted_at IS NULL
             ORDER BY id DESC
             LIMIT 1',
            [
                'provider' => $provider,
                'event_hash' => $eventHash,
            ]
        );
    }

    private function findOne(string $sql, array $params): ?array
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            throw new RuntimeException('payment_event_lookup_failed', 0, $e);
        }

        return is_array($row) ? $this->normalizeRow($row) : null;
    }

    private function normalizeRow(array $row): array
    {
        return [
            'id' => isset($row['id']) ? (int)$row['id'] : null,
            'uuid' => (string)($row['uuid'] ?? ''),
            'checkout_intent_uuid' => $this->nullableString($row['checkout_intent_uuid'] ?? null),
            'payment_intent_uuid' => $this->nullableString($row['payment_intent_uuid'] ?? null),
            'provider' => (string)($row['provider'] ?? ''),
            'provider_event_id' => (string)($row['provider_event_id'] ?? ''),
            'provider_payment_id' => $this->nullableString($row['provider_payment_id'] ?? null),
            'event_type' => (string)($row['event_type'] ?? ''),
            'provider_status' => $this->nullableString($row['provider_status'] ?? null),
            'normalized_status' => $this->nullableString($row['normalized_status'] ?? null),
            'amount_cents' => isset($row['amount_cents']) ? (int)$row['amount_cents'] : null,
            'currency' => $this->nullableString($row['currency'] ?? null),
            'event_hash' => (string)($row['event_hash'] ?? ''),
            'signature_validated_at' => $this->nullableString($row['signature_validated_at'] ?? null),
            'received_at' => (string)($row['received_at'] ?? ''),
            'processed_at' => $this->nullableString($row['processed_at'] ?? null),
            'processing_status' => (string)($row['processing_status'] ?? ''),
            'error_message' => $this->nullableString($row['error_message'] ?? null),
            'payload_text_sanitized' => $this->nullableString($row['payload_text_sanitized'] ?? null),
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
                checkout_intent_uuid,
                payment_intent_uuid,
                provider,
                provider_event_id,
                provider_payment_id,
                event_type,
                provider_status,
                normalized_status,
                amount_cents,
                currency,
                event_hash,
                signature_validated_at,
                received_at,
                processed_at,
                processing_status,
                error_message,
                payload_text_sanitized,
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

    private function optionalInt($value, string $code): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_int($value)) {
            return $value;
        }

        $text = trim((string)$value);
        if ($text === '' || !ctype_digit($text)) {
            throw new InvalidArgumentException($code);
        }
        return (int)$text;
    }

    private function optionalCurrency($value): ?string
    {
        $currency = $this->optionalText($value, 3);
        return $currency === null ? null : strtoupper($currency);
    }

    private function processedAt(array $input): string
    {
        $processedAt = $this->optionalText($input['processed_at'] ?? null, 19);
        if ($processedAt !== null) {
            return $processedAt;
        }

        return $this->requiredText($input['received_at'] ?? null, 'invalid_payment_event_payload', 19);
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
