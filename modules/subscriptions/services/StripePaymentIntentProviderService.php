<?php
declare(strict_types=1);

namespace Subscriptions\Services;

use RuntimeException;

final class StripePaymentIntentProviderService
{
    public const PROVIDER = 'stripe';

    private const API_URL = 'https://api.stripe.com/v1/payment_intents';
    private const DEFAULT_PROVIDER_STATUS = 'requires_payment_method';
    private const MAX_METADATA_VALUE_LENGTH = 500;

    public function create(array $input): array
    {
        $checkoutIntentUuid = $this->requiredText(
            $input['checkout_intent_uuid'] ?? null,
            'stripe_payment_intent_create_failed: checkout_intent_uuid is required',
            128
        );
        $paymentIntentUuid = $this->requiredText(
            $input['payment_intent_uuid'] ?? null,
            'stripe_payment_intent_create_failed: payment_intent_uuid is required',
            128
        );
        $amountCents = $this->requiredPositiveInt($input['amount_cents'] ?? null);
        $currency = strtoupper($this->requiredText(
            $input['currency'] ?? null,
            'stripe_payment_intent_create_failed: currency is required',
            3
        ));
        $provider = $this->provider($input['provider'] ?? self::PROVIDER);
        $source = $this->optionalText($input['source'] ?? 'stripe_payment_intent_provider', 128)
            ?? 'stripe_payment_intent_provider';
        $idempotencyKey = $this->optionalText($input['provider_idempotency_key'] ?? null, 255);

        $secretKey = $this->stripeSecretKey();
        $metadata = $this->metadata($input, $checkoutIntentUuid, $paymentIntentUuid);
        $payload = [
            'amount' => $amountCents,
            'currency' => strtolower($currency),
            'automatic_payment_methods' => [
                'enabled' => 'true',
            ],
            'metadata' => $metadata,
        ];

        $response = $this->request($secretKey, $payload, $idempotencyKey);
        $this->assertProviderResponse($response, $amountCents, $currency);

        $createdAtProvider = null;
        if (isset($response['created']) && is_int($response['created']) && $response['created'] > 0) {
            $createdAtProvider = gmdate('Y-m-d H:i:s', $response['created']);
        }

        return [
            'provider' => $provider,
            'provider_payment_id' => (string)$response['id'],
            'provider_checkout_id' => null,
            'provider_status' => (string)($response['status'] ?? self::DEFAULT_PROVIDER_STATUS),
            'normalized_status' => $this->normalizedStatus((string)($response['status'] ?? self::DEFAULT_PROVIDER_STATUS)),
            'amount_cents' => $amountCents,
            'currency' => $currency,
            'checkout_intent_uuid' => $checkoutIntentUuid,
            'created_at_provider' => $createdAtProvider,
            'source' => $source,
            'raw_response' => $this->safeResponseSummary($response),
        ];
    }

    private function request(string $secretKey, array $payload, ?string $idempotencyKey): array
    {
        $headers = [
            'Authorization: Basic ' . base64_encode($secretKey . ':'),
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json',
        ];
        if ($idempotencyKey !== null) {
            $headers[] = 'Idempotency-Key: ' . $idempotencyKey;
        }

        $body = http_build_query($this->flatten($payload), '', '&', PHP_QUERY_RFC3986);
        if (function_exists('curl_init')) {
            return $this->requestWithCurl($headers, $body);
        }

        return $this->requestWithStream($headers, $body);
    }

    private function requestWithCurl(array $headers, string $body): array
    {
        $handle = curl_init(self::API_URL);
        if ($handle === false) {
            throw new RuntimeException('stripe_provider_unavailable: stripe http client is unavailable');
        }

        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_TIMEOUT => 20,
        ]);

        $raw = curl_exec($handle);
        $httpStatus = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($handle);
        $this->closeCurlHandle($handle);

        if (!is_string($raw) || $raw === '') {
            throw new RuntimeException(
                'stripe_payment_intent_create_failed: stripe returned an empty response'
            );
        }
        if ($curlError !== '') {
            throw new RuntimeException('stripe_payment_intent_create_failed: stripe request failed');
        }

        return $this->decodeResponse($raw, $httpStatus);
    }

    private function closeCurlHandle($handle): void
    {
        if (PHP_VERSION_ID >= 80500) {
            return;
        }

        curl_close($handle);
    }

    private function requestWithStream(array $headers, string $body): array
    {
        if (!filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
            throw new RuntimeException('stripe_provider_unavailable: stripe http client is unavailable');
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $body,
                'timeout' => 20,
                'ignore_errors' => true,
            ],
        ]);
        $raw = file_get_contents(self::API_URL, false, $context);
        if (!is_string($raw) || $raw === '') {
            throw new RuntimeException(
                'stripe_payment_intent_create_failed: stripe returned an empty response'
            );
        }

        $httpStatus = 0;
        $headersList = $http_response_header ?? [];
        foreach ($headersList as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})\b/', (string)$header, $matches) === 1) {
                $httpStatus = (int)$matches[1];
            }
        }

        return $this->decodeResponse($raw, $httpStatus);
    }

    private function decodeResponse(string $raw, int $httpStatus): array
    {
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('stripe_payment_intent_create_failed: stripe response is invalid');
        }
        if ($httpStatus < 200 || $httpStatus >= 300) {
            $code = $this->optionalText($decoded['error']['code'] ?? null, 96)
                ?? $this->optionalText($decoded['error']['type'] ?? null, 96)
                ?? 'stripe_error';
            throw new RuntimeException('stripe_payment_intent_create_failed: ' . $code);
        }

        return $decoded;
    }

    private function assertProviderResponse(array $response, int $amountCents, string $currency): void
    {
        if ((string)($response['object'] ?? '') !== 'payment_intent') {
            throw new RuntimeException('stripe_payment_intent_create_failed: stripe object is invalid');
        }
        $providerPaymentId = trim((string)($response['id'] ?? ''));
        if ($providerPaymentId === '' || strpos($providerPaymentId, 'pi_') !== 0) {
            throw new RuntimeException('stripe_payment_intent_create_failed: stripe payment intent id is invalid');
        }
        if ((bool)($response['livemode'] ?? false)) {
            throw new RuntimeException('stripe_live_mode_not_allowed: stripe live mode is not allowed');
        }
        if ((int)($response['amount'] ?? -1) !== $amountCents) {
            throw new RuntimeException('stripe_payment_intent_create_failed: stripe amount mismatch');
        }
        if (strtoupper((string)($response['currency'] ?? '')) !== $currency) {
            throw new RuntimeException('stripe_payment_intent_create_failed: stripe currency mismatch');
        }
    }

    private function stripeSecretKey(): string
    {
        $secretKey = $this->envValue('STRIPE_SECRET_KEY');
        if ($secretKey === '') {
            throw new RuntimeException('stripe_secret_key_missing: STRIPE_SECRET_KEY is required');
        }
        if (strpos($secretKey, 'sk_live_') === 0 || strpos($secretKey, 'rk_live_') === 0) {
            throw new RuntimeException('stripe_live_mode_not_allowed: stripe live mode is not allowed');
        }
        if (strpos($secretKey, 'sk_test_') !== 0 && strpos($secretKey, 'rk_test_') !== 0) {
            throw new RuntimeException('stripe_provider_unavailable: STRIPE_SECRET_KEY must be a test key');
        }

        return $secretKey;
    }

    private function metadata(array $input, string $checkoutIntentUuid, string $paymentIntentUuid): array
    {
        $metadata = [
            'mxmed_checkout_intent_uuid' => $checkoutIntentUuid,
            'mxmed_payment_intent_uuid' => $paymentIntentUuid,
            'mxmed_entity_type' => $this->metadataValue($input['entity_type'] ?? ''),
            'mxmed_entity_id' => $this->metadataValue($input['entity_id'] ?? ''),
            'mxmed_plan_code' => $this->metadataValue($input['plan_code'] ?? ''),
            'mxmed_billing_period' => $this->metadataValue($input['billing_period'] ?? ''),
            'mxmed_environment' => $this->metadataValue($this->environment()),
        ];

        return array_filter($metadata, static fn($value): bool => $value !== '');
    }

    private function normalizedStatus(string $providerStatus): string
    {
        if (in_array($providerStatus, ['processing', 'requires_action', 'requires_confirmation', 'requires_capture'], true)) {
            return 'pending_provider';
        }

        return 'created';
    }

    private function safeResponseSummary(array $response): array
    {
        return [
            'object' => (string)($response['object'] ?? ''),
            'id' => (string)($response['id'] ?? ''),
            'status' => (string)($response['status'] ?? ''),
            'amount' => isset($response['amount']) ? (int)$response['amount'] : null,
            'currency' => strtoupper((string)($response['currency'] ?? '')),
            'livemode' => (bool)($response['livemode'] ?? false),
            'created' => isset($response['created']) ? (int)$response['created'] : null,
        ];
    }

    private function flatten(array $payload, string $prefix = ''): array
    {
        $flat = [];
        foreach ($payload as $key => $value) {
            $path = $prefix === '' ? (string)$key : $prefix . '[' . (string)$key . ']';
            if (is_array($value)) {
                $flat += $this->flatten($value, $path);
                continue;
            }
            $flat[$path] = $value;
        }

        return $flat;
    }

    private function provider($value): string
    {
        $provider = $this->requiredText($value, 'stripe_payment_intent_create_failed: provider is required', 64);
        if ($provider !== self::PROVIDER) {
            throw new RuntimeException('payment_provider_invalid: provider is invalid');
        }

        return $provider;
    }

    private function requiredText($value, string $message, int $maxLength): string
    {
        $text = trim((string)($value ?? ''));
        if ($text === '' || strlen($text) > $maxLength) {
            throw new RuntimeException($message);
        }

        return $text;
    }

    private function optionalText($value, int $maxLength): ?string
    {
        $text = trim((string)($value ?? ''));
        if ($text === '') {
            return null;
        }

        return strlen($text) > $maxLength ? substr($text, 0, $maxLength) : $text;
    }

    private function requiredPositiveInt($value): int
    {
        if (is_int($value)) {
            $amount = $value;
        } else {
            $text = trim((string)($value ?? ''));
            if ($text === '' || !ctype_digit($text)) {
                throw new RuntimeException('stripe_payment_intent_create_failed: amount is required');
            }
            $amount = (int)$text;
        }

        if ($amount <= 0) {
            throw new RuntimeException('stripe_payment_intent_create_failed: amount must be positive');
        }

        return $amount;
    }

    private function metadataValue($value): string
    {
        $text = trim((string)($value ?? ''));
        if ($text === '') {
            return '';
        }

        return strlen($text) > self::MAX_METADATA_VALUE_LENGTH
            ? substr($text, 0, self::MAX_METADATA_VALUE_LENGTH)
            : $text;
    }

    private function envValue(string $name): string
    {
        $value = getenv($name);
        if ($value !== false && trim((string)$value) !== '') {
            return trim((string)$value);
        }

        foreach ([$_ENV[$name] ?? null, $_SERVER[$name] ?? null] as $candidate) {
            if ($candidate !== null && trim((string)$candidate) !== '') {
                return trim((string)$candidate);
            }
        }

        return '';
    }

    private function environment(): string
    {
        return $this->envValue('MXMED_ENV')
            ?: ($this->envValue('APP_ENV') ?: ($this->envValue('ENVIRONMENT') ?: 'local'));
    }
}
