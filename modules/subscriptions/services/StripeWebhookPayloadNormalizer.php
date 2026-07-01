<?php
declare(strict_types=1);

namespace Subscriptions\Services;

final class StripeWebhookPayloadNormalizer
{
    private const PROVIDER = 'stripe';
    private const PAYMENT_INTENT_OBJECT = 'payment_intent';
    private const MAX_SUMMARY_LENGTH = 4096;

    public function normalize(array $eventPayload, string $rawBody, array $options = []): array
    {
        $eventId = $this->cleanText($eventPayload['id'] ?? null, 128);
        if ($eventId === null) {
            return $this->error('stripe_event_id_missing', 400, [
                'event_type' => $this->cleanText($eventPayload['type'] ?? null, 128),
            ]);
        }

        $eventType = $this->cleanText($eventPayload['type'] ?? null, 128);
        if ($eventType === null) {
            return $this->error('stripe_event_type_missing', 400, [
                'provider_event_id' => $eventId,
            ]);
        }

        $paymentIntent = $eventPayload['data']['object'] ?? null;
        if (!is_array($paymentIntent)) {
            return $this->error('stripe_event_data_object_missing', 400, [
                'provider_event_id' => $eventId,
                'provider_event_type' => $eventType,
            ]);
        }

        $objectType = $this->cleanText($paymentIntent['object'] ?? null, 64);
        if ($objectType !== self::PAYMENT_INTENT_OBJECT) {
            return $this->error('stripe_payment_intent_object_invalid', 400, [
                'provider_event_id' => $eventId,
                'provider_event_type' => $eventType,
                'object_type' => $objectType,
            ]);
        }

        $paymentIntentId = $this->cleanText($paymentIntent['id'] ?? null, 128);
        if ($paymentIntentId === null) {
            return $this->error('stripe_payment_intent_id_missing', 400, [
                'provider_event_id' => $eventId,
                'provider_event_type' => $eventType,
            ]);
        }

        $providerStatus = $this->cleanText($paymentIntent['status'] ?? null, 64);
        if ($providerStatus === null) {
            return $this->error('stripe_payment_intent_status_missing', 400, [
                'provider_event_id' => $eventId,
                'provider_event_type' => $eventType,
                'provider_payment_id' => $paymentIntentId,
            ]);
        }

        $amountCents = $this->amountCents($paymentIntent);
        if ($amountCents === null) {
            return $this->error('stripe_payment_intent_amount_missing', 400, [
                'provider_event_id' => $eventId,
                'provider_event_type' => $eventType,
                'provider_payment_id' => $paymentIntentId,
            ]);
        }

        $currency = $this->currency($paymentIntent['currency'] ?? null);
        if ($currency === null) {
            return $this->error('stripe_payment_intent_currency_missing', 400, [
                'provider_event_id' => $eventId,
                'provider_event_type' => $eventType,
                'provider_payment_id' => $paymentIntentId,
            ]);
        }

        $livemode = array_key_exists('livemode', $eventPayload) ? (bool)$eventPayload['livemode'] : null;
        if (array_key_exists('expected_livemode', $options)
            && $options['expected_livemode'] !== null
            && $livemode !== null
            && (bool)$options['expected_livemode'] !== $livemode
        ) {
            return $this->error('stripe_livemode_mismatch', 422, [
                'provider_event_id' => $eventId,
                'provider_event_type' => $eventType,
                'provider_payment_id' => $paymentIntentId,
                'livemode' => $livemode,
                'expected_livemode' => (bool)$options['expected_livemode'],
            ]);
        }

        $metadata = $this->mxmedMetadata($paymentIntent['metadata'] ?? null);
        $expectedCurrency = $this->currency($options['expected_currency'] ?? 'MXN');
        $environment = $this->cleanText($options['environment'] ?? null, 64);
        $createdAt = $eventPayload['created'] ?? null;
        $now = gmdate('Y-m-d H:i:s');
        $data = [
            'provider' => self::PROVIDER,
            'provider_event_id' => $eventId,
            'provider_event_type' => $eventType,
            'provider_event_created_at' => $createdAt,
            'provider_payment_id' => $paymentIntentId,
            'provider_status' => $providerStatus,
            'amount_cents' => $amountCents,
            'currency' => $currency,
            'livemode' => $livemode,
            'api_version' => $this->cleanText($eventPayload['api_version'] ?? null, 64),
            'event_hash' => hash('sha256', $rawBody),
            'payload_text_sanitized' => $this->payloadSummary($eventId, $eventType, $paymentIntent, $metadata, $livemode, $eventPayload),
            'raw_event_reference' => $eventId,
            'received_at' => $now,
            'signature_validated_at' => $now,
        ];

        return [
            'ok' => true,
            'provider' => self::PROVIDER,
            'data' => $data,
            'metadata' => $metadata,
            'normalized_status_preview' => $this->normalizedStatusPreview($eventType, $providerStatus),
            'internal_event_type_preview' => $this->internalEventTypePreview($eventType, $providerStatus),
            'log_context' => [
                'provider' => self::PROVIDER,
                'provider_event_id' => $eventId,
                'provider_event_type' => $eventType,
                'provider_payment_id' => $paymentIntentId,
                'livemode' => $livemode,
                'api_version' => $data['api_version'],
                'expected_currency' => $expectedCurrency,
                'environment' => $environment,
            ],
        ];
    }

    private function error(string $code, int $httpStatus, array $logContext = []): array
    {
        $safeContext = [
            'provider' => self::PROVIDER,
            'error_code' => $code,
        ];
        foreach ($logContext as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $safeContext[$key] = is_scalar($value) ? $value : null;
        }

        return [
            'ok' => false,
            'provider' => self::PROVIDER,
            'error_code' => $code,
            'code' => $code,
            'http_status' => $httpStatus,
            'safe_message' => 'stripe webhook payload is invalid',
            'message' => 'stripe webhook payload is invalid',
            'log_context' => array_filter($safeContext, static fn($value): bool => $value !== null),
        ];
    }

    private function amountCents(array $paymentIntent): ?int
    {
        $value = $paymentIntent['amount'] ?? ($paymentIntent['amount_received'] ?? null);
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

    private function payloadSummary(
        string $eventId,
        string $eventType,
        array $paymentIntent,
        array $metadata,
        ?bool $livemode,
        array $eventPayload
    ): ?string {
        $summary = [
            'event_id' => $eventId,
            'event_type' => $eventType,
            'object_id' => $this->cleanText($paymentIntent['id'] ?? null, 128),
            'object_type' => $this->cleanText($paymentIntent['object'] ?? null, 64),
            'status' => $this->cleanText($paymentIntent['status'] ?? null, 64),
            'amount' => $paymentIntent['amount'] ?? ($paymentIntent['amount_received'] ?? null),
            'currency' => $this->currency($paymentIntent['currency'] ?? null),
            'livemode' => $livemode,
            'api_version' => $this->cleanText($eventPayload['api_version'] ?? null, 64),
            'metadata' => $metadata === [] ? null : $metadata,
        ];
        $summary = array_filter($summary, static fn($value): bool => $value !== null && $value !== '');
        $json = json_encode($summary, JSON_UNESCAPED_SLASHES);
        if (!is_string($json) || $json === '') {
            return null;
        }

        return strlen($json) > self::MAX_SUMMARY_LENGTH ? substr($json, 0, self::MAX_SUMMARY_LENGTH) : $json;
    }

    private function mxmedMetadata($metadata): array
    {
        if (!is_array($metadata)) {
            return [];
        }

        $safe = [];
        foreach ($metadata as $key => $value) {
            $keyText = $this->cleanText($key, 64);
            if ($keyText === null || strpos($keyText, 'mxmed_') !== 0 || !is_scalar($value)) {
                continue;
            }

            $valueText = $this->cleanText($value, 255);
            if ($valueText !== null) {
                $safe[$keyText] = $valueText;
            }
        }

        return $safe;
    }

    private function normalizedStatusPreview(string $eventType, string $providerStatus): ?string
    {
        $eventType = strtolower($eventType);
        $providerStatus = strtolower($providerStatus);
        if ($eventType === 'payment_intent.succeeded' || in_array($providerStatus, ['succeeded', 'paid'], true)) {
            return 'paid';
        }
        if ($eventType === 'payment_intent.payment_failed' || $providerStatus === 'failed') {
            return 'failed';
        }
        if ($eventType === 'payment_intent.canceled'
            || in_array($providerStatus, ['canceled', 'cancelled'], true)
        ) {
            return 'cancelled';
        }

        return null;
    }

    private function internalEventTypePreview(string $eventType, string $providerStatus): ?string
    {
        $status = $this->normalizedStatusPreview($eventType, $providerStatus);
        if ($status === 'paid') {
            return 'payment_intent_confirm';
        }
        if ($status === 'failed') {
            return 'payment_intent_failed';
        }
        if ($status === 'cancelled') {
            return 'payment_intent_cancelled';
        }

        return null;
    }

    private function cleanText($value, int $maxLength): ?string
    {
        $text = trim((string)($value ?? ''));
        if ($text === '') {
            return null;
        }

        return strlen($text) > $maxLength ? substr($text, 0, $maxLength) : $text;
    }
}
