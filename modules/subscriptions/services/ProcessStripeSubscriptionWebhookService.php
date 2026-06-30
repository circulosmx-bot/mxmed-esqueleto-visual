<?php
declare(strict_types=1);

namespace Subscriptions\Services;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;
use Subscriptions\Repositories\SubscriptionPaymentEventRepository;
use Subscriptions\Repositories\SubscriptionPaymentIntentRepository;
use Throwable;

final class ProcessStripeSubscriptionWebhookService
{
    private const PROVIDER = 'stripe';
    private const SOURCE = 'stripe_webhook';

    private const STATUS_PAID = 'paid';
    private const STATUS_FAILED = 'failed';
    private const STATUS_CANCELLED = 'cancelled';

    private const EVENT_TYPE_CONFIRM = 'payment_intent_confirm';
    private const EVENT_TYPE_FAILED = 'payment_intent_failed';
    private const EVENT_TYPE_CANCELLED = 'payment_intent_cancelled';
    private const EVENT_TYPE_STATUS = 'payment_intent_status';

    private const TERMINAL_STATUSES = [
        self::STATUS_PAID,
        self::STATUS_FAILED,
        self::STATUS_CANCELLED,
    ];

    private PDO $pdo;
    private SubscriptionPaymentIntentRepository $paymentIntentRepository;
    private SubscriptionPaymentEventRepository $paymentEventRepository;

    public function __construct(
        PDO $pdo,
        SubscriptionPaymentIntentRepository $paymentIntentRepository,
        SubscriptionPaymentEventRepository $paymentEventRepository
    ) {
        $this->pdo = $pdo;
        $this->paymentIntentRepository = $paymentIntentRepository;
        $this->paymentEventRepository = $paymentEventRepository;
    }

    public function process(array $input): array
    {
        $event = $this->normalizeInput($input);
        if ($event['reason'] !== null) {
            return $this->response($event, [
                'processed' => false,
                'reason' => $event['reason'],
                'http_status_recommended' => 422,
            ]);
        }

        try {
            return $this->processNormalized($event);
        } catch (Throwable $e) {
            return $this->response($event, [
                'processed' => false,
                'reason' => 'stripe_webhook_processing_failed',
                'http_status_recommended' => 500,
            ]);
        }
    }

    private function processNormalized(array $event): array
    {
        $existingEvent = $this->paymentEventRepository->findByProviderEventId(
            self::PROVIDER,
            (string)$event['provider_event_id']
        );
        if ($existingEvent !== null) {
            if ((string)($existingEvent['event_hash'] ?? '') !== (string)$event['event_hash']) {
                return $this->response($event, [
                    'processed' => false,
                    'conflict' => true,
                    'reason' => 'stripe_event_conflict',
                    'payment_event' => $existingEvent,
                    'http_status_recommended' => 409,
                ]);
            }

            return $this->response($event, [
                'processed' => (string)($existingEvent['processing_status'] ?? '') === 'processed',
                'duplicate' => true,
                'payment_event' => $existingEvent,
                'normalized_status' => $existingEvent['normalized_status'] ?? null,
                'processing_status' => $existingEvent['processing_status'] ?? null,
                'http_status_recommended' => 200,
            ]);
        }

        $paymentIntent = $this->paymentIntentRepository->findActiveByProviderPaymentId(
            self::PROVIDER,
            (string)$event['provider_payment_id']
        );
        if ($paymentIntent === null) {
            $paymentEvent = $this->createFailedEvent($event, null, 'stripe_payment_intent_not_found');

            return $this->response($event, [
                'processed' => false,
                'reason' => 'stripe_payment_intent_not_found',
                'payment_event' => $paymentEvent,
                'processing_status' => 'failed',
                'http_status_recommended' => 404,
            ]);
        }

        $amountMismatch = $this->amountMismatch($paymentIntent, $event);
        if ($amountMismatch !== null) {
            $paymentEvent = $this->createFailedEvent($event, $paymentIntent, $amountMismatch);

            return $this->response($event, [
                'processed' => false,
                'reason' => $amountMismatch,
                'payment_intent' => $paymentIntent,
                'payment_event' => $paymentEvent,
                'processing_status' => 'failed',
                'http_status_recommended' => 422,
            ]);
        }

        $transition = $this->transitionForEvent($event);
        if ($transition === null) {
            $paymentEvent = $this->createFailedEvent($event, $paymentIntent, 'stripe_status_not_actionable');

            return $this->response($event, [
                'processed' => false,
                'reason' => 'stripe_status_not_actionable',
                'payment_intent' => $paymentIntent,
                'payment_event' => $paymentEvent,
                'processing_status' => 'failed',
                'http_status_recommended' => 200,
            ]);
        }

        $stateConflict = $this->stateConflict($paymentIntent, $transition['normalized_status']);
        if ($stateConflict !== null) {
            $paymentEvent = $this->createFailedEvent($event, $paymentIntent, $stateConflict);

            return $this->response($event, [
                'processed' => false,
                'reason' => $stateConflict,
                'payment_intent' => $paymentIntent,
                'payment_event' => $paymentEvent,
                'processing_status' => 'failed',
                'http_status_recommended' => 409,
            ]);
        }

        return $this->applyTransition($event, $paymentIntent, $transition);
    }

    private function applyTransition(array $event, array $paymentIntent, array $transition): array
    {
        $transactionOpen = false;

        try {
            $this->pdo->beginTransaction();
            $transactionOpen = true;

            $paymentEvent = $this->paymentEventRepository->createProcessedProviderEvent([
                'uuid' => $this->generateUuidV4(),
                'checkout_intent_uuid' => (string)($paymentIntent['checkout_intent_uuid'] ?? ''),
                'payment_intent_uuid' => (string)($paymentIntent['uuid'] ?? ''),
                'provider' => self::PROVIDER,
                'provider_event_id' => (string)$event['provider_event_id'],
                'provider_payment_id' => (string)$event['provider_payment_id'],
                'event_type' => (string)$transition['event_type'],
                'provider_status' => (string)$event['provider_status'],
                'normalized_status' => (string)$transition['normalized_status'],
                'amount_cents' => (int)$event['amount_cents'],
                'currency' => (string)$event['currency'],
                'event_hash' => (string)$event['event_hash'],
                'signature_validated_at' => (string)$event['signature_validated_at'],
                'received_at' => (string)$event['received_at'],
                'processed_at' => $this->now(),
                'payload_text_sanitized' => $event['payload_text_sanitized'],
                'source' => self::SOURCE,
                'notes' => $this->safeNotes($event),
            ]);

            $transitionInput = [
                'provider' => self::PROVIDER,
                'provider_status' => (string)$event['provider_status'],
                'source' => self::SOURCE,
                'notes' => $this->safeNotes($event),
            ];

            $timestampKey = (string)$transition['timestamp_key'];
            $transitionInput[$timestampKey] = (string)$event['transitioned_at'];

            $updatedPaymentIntent = $this->transitionPaymentIntent(
                (string)($paymentIntent['uuid'] ?? ''),
                (string)$transition['normalized_status'],
                $transitionInput
            );
            if ($updatedPaymentIntent === null) {
                throw new RuntimeException('stripe_payment_intent_transition_failed');
            }

            $this->pdo->commit();
            $transactionOpen = false;

            return $this->response($event, [
                'processed' => true,
                'payment_intent' => $updatedPaymentIntent,
                'payment_event' => $paymentEvent,
                'normalized_status' => $updatedPaymentIntent['normalized_status'] ?? $transition['normalized_status'],
                'processing_status' => $paymentEvent['processing_status'] ?? 'processed',
                'http_status_recommended' => 200,
            ]);
        } catch (Throwable $e) {
            if ($transactionOpen && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    private function transitionPaymentIntent(string $paymentIntentUuid, string $normalizedStatus, array $input): ?array
    {
        if ($normalizedStatus === self::STATUS_PAID) {
            return $this->paymentIntentRepository->markProviderPaid($paymentIntentUuid, $input);
        }
        if ($normalizedStatus === self::STATUS_FAILED) {
            return $this->paymentIntentRepository->markProviderFailed($paymentIntentUuid, $input);
        }

        return $this->paymentIntentRepository->markProviderCanceled($paymentIntentUuid, $input);
    }

    private function createFailedEvent(array $event, ?array $paymentIntent, string $reason): array
    {
        return $this->paymentEventRepository->createFailedProviderEvent([
            'uuid' => $this->generateUuidV4(),
            'checkout_intent_uuid' => $paymentIntent['checkout_intent_uuid'] ?? null,
            'payment_intent_uuid' => $paymentIntent['uuid'] ?? null,
            'provider' => self::PROVIDER,
            'provider_event_id' => (string)$event['provider_event_id'],
            'provider_payment_id' => (string)$event['provider_payment_id'],
            'event_type' => self::EVENT_TYPE_STATUS,
            'provider_status' => (string)$event['provider_status'],
            'normalized_status' => null,
            'amount_cents' => $event['amount_cents'],
            'currency' => $event['currency'],
            'event_hash' => (string)$event['event_hash'],
            'signature_validated_at' => (string)$event['signature_validated_at'],
            'received_at' => (string)$event['received_at'],
            'processed_at' => $this->now(),
            'error_message' => $reason,
            'payload_text_sanitized' => $event['payload_text_sanitized'],
            'source' => self::SOURCE,
            'notes' => $this->safeNotes($event),
        ]);
    }

    private function normalizeInput(array $input): array
    {
        $event = [
            'provider' => self::PROVIDER,
            'provider_event_id' => $this->cleanText($input['provider_event_id'] ?? null, 128),
            'provider_event_type' => $this->cleanText($input['provider_event_type'] ?? null, 128),
            'provider_payment_id' => $this->cleanText($input['provider_payment_id'] ?? null, 128),
            'provider_status' => $this->cleanText($input['provider_status'] ?? null, 64),
            'amount_cents' => $this->optionalInt($input['amount_cents'] ?? null),
            'currency' => $this->currency($input['currency'] ?? null),
            'event_hash' => $this->cleanText($input['event_hash'] ?? null, 64),
            'payload_text_sanitized' => $this->cleanText($input['payload_text_sanitized'] ?? null, 65535),
            'raw_event_reference' => $this->cleanText($input['raw_event_reference'] ?? null, 255),
            'livemode' => isset($input['livemode']) ? (bool)$input['livemode'] : null,
            'api_version' => $this->cleanText($input['api_version'] ?? null, 64),
            'received_at' => $this->timestamp($input['received_at'] ?? null),
            'signature_validated_at' => $this->timestamp($input['signature_validated_at'] ?? null),
            'transitioned_at' => $this->timestamp($input['provider_event_created_at'] ?? null),
            'reason' => null,
        ];

        $provider = $this->cleanText($input['provider'] ?? self::PROVIDER, 64);
        if ($provider !== self::PROVIDER) {
            $event['reason'] = 'stripe_provider_invalid';
            return $event;
        }

        $required = [
            'provider_event_id' => 'stripe_event_id_missing',
            'provider_event_type' => 'stripe_event_type_missing',
            'provider_payment_id' => 'stripe_payment_id_missing',
            'provider_status' => 'stripe_status_missing',
            'event_hash' => 'stripe_event_hash_missing',
        ];
        foreach ($required as $field => $reason) {
            if ($event[$field] === null) {
                $event['reason'] = $reason;
                return $event;
            }
        }
        if ($event['amount_cents'] === null) {
            $event['reason'] = 'stripe_amount_missing';
            return $event;
        }
        if ($event['currency'] === null) {
            $event['reason'] = 'stripe_currency_missing';
            return $event;
        }

        return $event;
    }

    private function transitionForEvent(array $event): ?array
    {
        $eventType = strtolower((string)$event['provider_event_type']);
        $providerStatus = strtolower((string)$event['provider_status']);

        if ($eventType === 'payment_intent.succeeded' || in_array($providerStatus, ['succeeded', 'paid'], true)) {
            return [
                'normalized_status' => self::STATUS_PAID,
                'event_type' => self::EVENT_TYPE_CONFIRM,
                'timestamp_key' => 'paid_at',
            ];
        }

        if ($eventType === 'payment_intent.payment_failed' || $providerStatus === 'failed') {
            return [
                'normalized_status' => self::STATUS_FAILED,
                'event_type' => self::EVENT_TYPE_FAILED,
                'timestamp_key' => 'failed_at',
            ];
        }

        if ($eventType === 'payment_intent.canceled'
            || in_array($providerStatus, ['canceled', 'cancelled'], true)
        ) {
            return [
                'normalized_status' => self::STATUS_CANCELLED,
                'event_type' => self::EVENT_TYPE_CANCELLED,
                'timestamp_key' => 'cancelled_at',
            ];
        }

        return null;
    }

    private function amountMismatch(array $paymentIntent, array $event): ?string
    {
        if ((int)($paymentIntent['amount_cents'] ?? -1) !== (int)$event['amount_cents']) {
            return 'stripe_amount_mismatch';
        }
        if (strtoupper((string)($paymentIntent['currency'] ?? '')) !== (string)$event['currency']) {
            return 'stripe_currency_mismatch';
        }

        return null;
    }

    private function stateConflict(array $paymentIntent, string $targetStatus): ?string
    {
        $currentStatus = (string)($paymentIntent['normalized_status'] ?? '');
        if ($currentStatus === $targetStatus) {
            return null;
        }
        if (in_array($currentStatus, self::TERMINAL_STATUSES, true)) {
            return 'stripe_payment_intent_state_conflict';
        }

        return null;
    }

    private function response(array $event, array $overrides): array
    {
        $paymentIntent = $overrides['payment_intent'] ?? null;
        $paymentEvent = $overrides['payment_event'] ?? null;

        return [
            'processed' => (bool)($overrides['processed'] ?? false),
            'duplicate' => (bool)($overrides['duplicate'] ?? false),
            'conflict' => (bool)($overrides['conflict'] ?? false),
            'provider' => self::PROVIDER,
            'provider_event_id' => $event['provider_event_id'] ?? null,
            'provider_event_type' => $event['provider_event_type'] ?? null,
            'payment_intent_uuid' => is_array($paymentIntent) ? ($paymentIntent['uuid'] ?? null) : ($paymentEvent['payment_intent_uuid'] ?? null),
            'checkout_intent_uuid' => is_array($paymentIntent) ? ($paymentIntent['checkout_intent_uuid'] ?? null) : ($paymentEvent['checkout_intent_uuid'] ?? null),
            'payment_event_uuid' => is_array($paymentEvent) ? ($paymentEvent['uuid'] ?? null) : null,
            'normalized_status' => $overrides['normalized_status'] ?? null,
            'processing_status' => $overrides['processing_status'] ?? null,
            'reason' => $overrides['reason'] ?? null,
            'http_status_recommended' => (int)($overrides['http_status_recommended'] ?? 200),
        ];
    }

    private function timestamp($value): string
    {
        $text = trim((string)($value ?? ''));
        if ($text === '') {
            return $this->now();
        }

        try {
            if (ctype_digit($text)) {
                $date = new DateTimeImmutable('@' . $text);
            } else {
                $date = new DateTimeImmutable($text);
            }

            return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        } catch (Throwable $e) {
            return substr($text, 0, 19);
        }
    }

    private function safeNotes(array $event): ?string
    {
        $notes = [
            'provider_event_type' => $event['provider_event_type'] ?? null,
            'raw_event_reference' => $event['raw_event_reference'] ?? null,
            'livemode' => $event['livemode'] ?? null,
            'api_version' => $event['api_version'] ?? null,
        ];
        $notes = array_filter($notes, static fn($value): bool => $value !== null && $value !== '');
        if ($notes === []) {
            return null;
        }

        $json = json_encode($notes, JSON_UNESCAPED_SLASHES);
        return is_string($json) ? substr($json, 0, 65535) : null;
    }

    private function cleanText($value, int $maxLength): ?string
    {
        $text = trim((string)($value ?? ''));
        if ($text === '') {
            return null;
        }

        return strlen($text) > $maxLength ? substr($text, 0, $maxLength) : $text;
    }

    private function optionalInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_int($value)) {
            return $value >= 0 ? $value : null;
        }

        $text = trim((string)$value);
        if ($text === '' || !ctype_digit($text)) {
            return null;
        }

        return (int)$text;
    }

    private function currency($value): ?string
    {
        $currency = $this->cleanText($value, 3);
        return $currency === null ? null : strtoupper($currency);
    }

    private function now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    }

    private function generateUuidV4(): string
    {
        try {
            $data = random_bytes(16);
        } catch (Throwable $e) {
            throw new RuntimeException('uuid_generation_failed', 0, $e);
        }

        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
