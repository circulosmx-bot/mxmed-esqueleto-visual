<?php
declare(strict_types=1);

namespace Subscriptions\Repositories;

use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

final class SubscriptionPaymentIntentRepository
{
    private const INITIAL_STATUSES = ['created', 'pending_provider'];
    private const TERMINAL_STATUSES = ['failed', 'cancelled', 'paid'];

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findByUuid(string $uuid): ?array
    {
        $uuid = trim($uuid);
        if ($uuid === '') {
            throw new InvalidArgumentException('invalid_payment_intent_payload: uuid is required');
        }

        return $this->findOne(
            'SELECT ' . $this->selectColumns() . '
             FROM subscription_payment_intents
             WHERE uuid = :uuid
               AND deleted_at IS NULL
             LIMIT 1',
            ['uuid' => $uuid]
        );
    }

    public function findByCheckoutIntentUuid(string $checkoutIntentUuid): ?array
    {
        $checkoutIntentUuid = trim($checkoutIntentUuid);
        if ($checkoutIntentUuid === '') {
            throw new InvalidArgumentException('invalid_payment_intent_payload: checkout_intent_uuid is required');
        }

        return $this->findOne(
            'SELECT ' . $this->selectColumns() . '
             FROM subscription_payment_intents
             WHERE checkout_intent_uuid = :checkout_intent_uuid
               AND deleted_at IS NULL
             ORDER BY id DESC
             LIMIT 1',
            ['checkout_intent_uuid' => $checkoutIntentUuid]
        );
    }

    public function findActiveByCheckoutIntentUuid(string $checkoutIntentUuid): ?array
    {
        $checkoutIntentUuid = trim($checkoutIntentUuid);
        if ($checkoutIntentUuid === '') {
            throw new InvalidArgumentException('invalid_payment_intent_payload: checkout_intent_uuid is required');
        }

        return $this->findOne(
            'SELECT ' . $this->selectColumns() . '
             FROM subscription_payment_intents
             WHERE checkout_intent_uuid = :checkout_intent_uuid
               AND normalized_status NOT IN (:failed_status, :cancelled_status, :paid_status)
               AND deleted_at IS NULL
             ORDER BY id DESC
             LIMIT 1',
            [
                'checkout_intent_uuid' => $checkoutIntentUuid,
                'failed_status' => self::TERMINAL_STATUSES[0],
                'cancelled_status' => self::TERMINAL_STATUSES[1],
                'paid_status' => self::TERMINAL_STATUSES[2],
            ]
        );
    }

    public function findByProviderPaymentId(string $provider, string $providerPaymentId): ?array
    {
        $provider = trim($provider);
        $providerPaymentId = trim($providerPaymentId);
        if ($provider === '' || $providerPaymentId === '') {
            throw new InvalidArgumentException('invalid_payment_intent_payload: provider and provider_payment_id are required');
        }

        return $this->findOne(
            'SELECT ' . $this->selectColumns() . '
             FROM subscription_payment_intents
             WHERE provider = :provider
               AND provider_payment_id = :provider_payment_id
               AND deleted_at IS NULL
             LIMIT 1',
            [
                'provider' => $provider,
                'provider_payment_id' => $providerPaymentId,
            ]
        );
    }

    public function findByProviderCheckoutId(string $provider, string $providerCheckoutId): ?array
    {
        $provider = trim($provider);
        $providerCheckoutId = trim($providerCheckoutId);
        if ($provider === '' || $providerCheckoutId === '') {
            throw new InvalidArgumentException('invalid_payment_intent_payload: provider and provider_checkout_id are required');
        }

        return $this->findOne(
            'SELECT ' . $this->selectColumns() . '
             FROM subscription_payment_intents
             WHERE provider = :provider
               AND provider_checkout_id = :provider_checkout_id
               AND deleted_at IS NULL
             ORDER BY id DESC
             LIMIT 1',
            [
                'provider' => $provider,
                'provider_checkout_id' => $providerCheckoutId,
            ]
        );
    }

    public function create(array $input): array
    {
        $uuid = $this->requiredText($input['uuid'] ?? null, 'invalid_payment_intent_payload', 36);
        $checkoutIntentUuid = $this->requiredText(
            $input['checkout_intent_uuid'] ?? null,
            'invalid_payment_intent_payload',
            36
        );
        $provider = $this->requiredText($input['provider'] ?? null, 'invalid_payment_intent_payload', 64);
        $providerPaymentId = $this->requiredText(
            $input['provider_payment_id'] ?? null,
            'invalid_payment_intent_payload',
            128
        );
        $providerCheckoutId = $this->optionalText($input['provider_checkout_id'] ?? null, 128);
        $normalizedStatus = $this->requiredText($input['normalized_status'] ?? null, 'invalid_payment_intent_payload', 32);
        $providerStatus = $this->optionalText($input['provider_status'] ?? null, 64);
        $amountCents = $this->requiredInt($input['amount_cents'] ?? null, 'invalid_payment_intent_payload');
        $currency = strtoupper($this->requiredText($input['currency'] ?? null, 'invalid_payment_intent_payload', 3));
        $createdAtProvider = $this->optionalText($input['created_at_provider'] ?? null, 19);
        $expiresAt = $this->optionalText($input['expires_at'] ?? null, 19);
        $paidAt = $this->optionalText($input['paid_at'] ?? null, 19);
        $failedAt = $this->optionalText($input['failed_at'] ?? null, 19);
        $cancelledAt = $this->optionalText($input['cancelled_at'] ?? null, 19);
        $source = $this->requiredText($input['source'] ?? null, 'invalid_payment_intent_payload', 128);
        $notes = $this->optionalText($input['notes'] ?? null, 65535);

        if ($amountCents < 0) {
            throw new InvalidArgumentException('invalid_payment_intent_payload: amount_cents must be non-negative');
        }
        if (!in_array($normalizedStatus, self::INITIAL_STATUSES, true)) {
            throw new InvalidArgumentException('invalid_payment_intent_payload: normalized_status must be initial');
        }

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO subscription_payment_intents (
                    uuid,
                    checkout_intent_uuid,
                    provider,
                    provider_payment_id,
                    provider_checkout_id,
                    normalized_status,
                    provider_status,
                    amount_cents,
                    currency,
                    created_at_provider,
                    expires_at,
                    paid_at,
                    failed_at,
                    cancelled_at,
                    source,
                    notes,
                    deleted_at
                ) VALUES (
                    :uuid,
                    :checkout_intent_uuid,
                    :provider,
                    :provider_payment_id,
                    :provider_checkout_id,
                    :normalized_status,
                    :provider_status,
                    :amount_cents,
                    :currency,
                    :created_at_provider,
                    :expires_at,
                    :paid_at,
                    :failed_at,
                    :cancelled_at,
                    :source,
                    :notes,
                    NULL
                )'
            );
            $stmt->execute([
                'uuid' => $uuid,
                'checkout_intent_uuid' => $checkoutIntentUuid,
                'provider' => $provider,
                'provider_payment_id' => $providerPaymentId,
                'provider_checkout_id' => $providerCheckoutId,
                'normalized_status' => $normalizedStatus,
                'provider_status' => $providerStatus,
                'amount_cents' => $amountCents,
                'currency' => $currency,
                'created_at_provider' => $createdAtProvider,
                'expires_at' => $expiresAt,
                'paid_at' => $paidAt,
                'failed_at' => $failedAt,
                'cancelled_at' => $cancelledAt,
                'source' => $source,
                'notes' => $notes,
            ]);
        } catch (Throwable $e) {
            throw new RuntimeException('payment_intent_create_failed', 0, $e);
        }

        $created = $this->findByUuid($uuid);
        if ($created === null) {
            throw new RuntimeException('payment_intent_lookup_failed');
        }

        return $created;
    }

    private function findOne(string $sql, array $params): ?array
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            throw new RuntimeException('payment_intent_lookup_failed', 0, $e);
        }

        return is_array($row) ? $this->normalizeRow($row) : null;
    }

    private function normalizeRow(array $row): array
    {
        return [
            'id' => isset($row['id']) ? (int)$row['id'] : null,
            'uuid' => (string)($row['uuid'] ?? ''),
            'checkout_intent_uuid' => (string)($row['checkout_intent_uuid'] ?? ''),
            'provider' => (string)($row['provider'] ?? ''),
            'provider_payment_id' => (string)($row['provider_payment_id'] ?? ''),
            'provider_checkout_id' => $this->nullableString($row['provider_checkout_id'] ?? null),
            'normalized_status' => (string)($row['normalized_status'] ?? ''),
            'provider_status' => $this->nullableString($row['provider_status'] ?? null),
            'amount_cents' => isset($row['amount_cents']) ? (int)$row['amount_cents'] : null,
            'currency' => (string)($row['currency'] ?? ''),
            'created_at_provider' => $this->nullableString($row['created_at_provider'] ?? null),
            'expires_at' => $this->nullableString($row['expires_at'] ?? null),
            'paid_at' => $this->nullableString($row['paid_at'] ?? null),
            'failed_at' => $this->nullableString($row['failed_at'] ?? null),
            'cancelled_at' => $this->nullableString($row['cancelled_at'] ?? null),
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
                provider,
                provider_payment_id,
                provider_checkout_id,
                normalized_status,
                provider_status,
                amount_cents,
                currency,
                created_at_provider,
                expires_at,
                paid_at,
                failed_at,
                cancelled_at,
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
