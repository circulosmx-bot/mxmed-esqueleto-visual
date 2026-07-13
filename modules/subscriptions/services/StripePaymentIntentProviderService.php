<?php
declare(strict_types=1);

namespace Subscriptions\Services;

use RuntimeException;

final class StripePaymentIntentProviderFailure extends RuntimeException
{
    private const STAGES = [
        'configuration',
        'transport',
        'provider_http',
        'response_decode',
        'response_validation',
    ];

    private string $stage;
    private string $errorCode;
    private ?int $providerHttpStatus;
    private ?string $providerErrorType;
    private ?string $providerErrorCode;
    private ?string $providerErrorParam;
    private bool $curlFailed;
    private ?int $curlErrno;
    private bool $responseDecodeFailed;
    private bool $responseValidationFailed;

    public function __construct(
        string $stage,
        string $errorCode,
        array $diagnostics = [],
        ?string $publicErrorCode = null
    ) {
        $this->stage = in_array($stage, self::STAGES, true) ? $stage : 'transport';
        $this->errorCode = self::safeCode($errorCode, 'stripe_payment_intent_create_failed');
        $this->providerHttpStatus = self::safeHttpStatus($diagnostics['provider_http_status'] ?? null);
        $this->providerErrorType = self::safeProviderText($diagnostics['provider_error_type'] ?? null);
        $this->providerErrorCode = self::safeProviderText($diagnostics['provider_error_code'] ?? null);
        $this->providerErrorParam = self::safeProviderText($diagnostics['provider_error_param'] ?? null);
        $this->curlFailed = (bool)($diagnostics['curl_failed'] ?? false);
        $this->curlErrno = self::safeCurlErrno($diagnostics['curl_errno'] ?? null);
        $this->responseDecodeFailed = (bool)($diagnostics['response_decode_failed'] ?? false);
        $this->responseValidationFailed = (bool)($diagnostics['response_validation_failed'] ?? false);

        parent::__construct(self::safeCode($publicErrorCode ?? $this->errorCode, 'stripe_payment_intent_create_failed'));
    }

    public function safeDiagnostics(): array
    {
        return [
            'stage' => $this->stage,
            'error_code' => $this->errorCode,
            'provider_http_status' => $this->providerHttpStatus,
            'provider_error_type' => $this->providerErrorType,
            'provider_error_code' => $this->providerErrorCode,
            'provider_error_param' => $this->providerErrorParam,
            'curl_failed' => $this->curlFailed,
            'curl_errno' => $this->curlErrno,
            'response_decode_failed' => $this->responseDecodeFailed,
            'response_validation_failed' => $this->responseValidationFailed,
        ];
    }

    private static function safeCode($value, string $fallback): string
    {
        $text = trim((string)($value ?? ''));
        if ($text === '' || preg_match('/^[A-Za-z0-9_.:-]{1,96}$/', $text) !== 1) {
            return $fallback;
        }

        return $text;
    }

    private static function safeProviderText($value): ?string
    {
        $text = trim((string)($value ?? ''));
        if ($text === '') {
            return null;
        }
        if (strlen($text) > 96) {
            $text = substr($text, 0, 96);
        }

        return preg_match('/^[A-Za-z0-9_.:\-\[\]]{1,96}$/', $text) === 1 ? $text : null;
    }

    private static function safeHttpStatus($value): ?int
    {
        $status = is_int($value) ? $value : (int)trim((string)($value ?? ''));
        return $status >= 100 && $status <= 599 ? $status : null;
    }

    private static function safeCurlErrno($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $errno = is_int($value) ? $value : (int)trim((string)$value);

        return $errno >= 0 ? $errno : null;
    }
}

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
                'allow_redirects' => 'never',
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

    public function retrieveClientSecret(string $providerPaymentId): array
    {
        $providerPaymentId = $this->requiredText(
            $providerPaymentId,
            'stripe_payment_intent_retrieve_failed: payment intent id is required',
            128
        );
        if (preg_match('/^pi_[A-Za-z0-9_]+$/', $providerPaymentId) !== 1) {
            throw new RuntimeException('stripe_payment_intent_retrieve_failed: payment intent id is invalid');
        }

        $response = $this->retrieve($this->stripeSecretKey(), $providerPaymentId);
        $this->assertRetrieveResponse($response, $providerPaymentId);

        return [
            'id' => (string)$response['id'],
            'status' => (string)($response['status'] ?? ''),
            'amount' => isset($response['amount']) ? (int)$response['amount'] : null,
            'currency' => strtolower((string)($response['currency'] ?? '')),
            'livemode' => (bool)($response['livemode'] ?? false),
            'metadata' => is_array($response['metadata'] ?? null) ? $response['metadata'] : [],
            'client_secret' => (string)($response['client_secret'] ?? ''),
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
            return $this->requestWithCurl(self::API_URL, $headers, 'POST', $body);
        }

        return $this->requestWithStream(self::API_URL, $headers, 'POST', $body);
    }

    private function retrieve(string $secretKey, string $providerPaymentId): array
    {
        $headers = [
            'Authorization: Basic ' . base64_encode($secretKey . ':'),
            'Accept: application/json',
        ];
        $url = self::API_URL . '/' . rawurlencode($providerPaymentId);

        if (function_exists('curl_init')) {
            return $this->requestWithCurl($url, $headers, 'GET');
        }

        return $this->requestWithStream($url, $headers, 'GET');
    }

    private function requestWithCurl(string $url, array $headers, string $method, ?string $body = null): array
    {
        $handle = curl_init($url);
        if ($handle === false) {
            throw new StripePaymentIntentProviderFailure(
                'transport',
                'stripe_provider_unavailable',
                [
                    'curl_failed' => true,
                ],
                'stripe_provider_unavailable'
            );
        }

        $options = [
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_TIMEOUT => 20,
        ];
        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = (string)$body;
        } else {
            $options[CURLOPT_HTTPGET] = true;
        }
        curl_setopt_array($handle, $options);

        $raw = curl_exec($handle);
        $httpStatus = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $curlErrno = function_exists('curl_errno') ? (int)curl_errno($handle) : 0;
        $this->closeCurlHandle($handle);

        if ($curlErrno !== 0) {
            throw new StripePaymentIntentProviderFailure(
                'transport',
                'stripe_transport_failed',
                [
                    'curl_failed' => true,
                    'curl_errno' => $curlErrno,
                ],
                'stripe_payment_intent_request_failed'
            );
        }
        if (!is_string($raw) || $raw === '') {
            throw new StripePaymentIntentProviderFailure(
                'transport',
                'stripe_empty_response',
                [
                    'curl_failed' => false,
                ],
                'stripe_payment_intent_request_failed'
            );
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

    private function requestWithStream(string $url, array $headers, string $method, ?string $body = null): array
    {
        if (!filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
            throw new StripePaymentIntentProviderFailure(
                'transport',
                'stripe_provider_unavailable',
                [],
                'stripe_provider_unavailable'
            );
        }

        $httpOptions = [
            'method' => $method,
            'header' => implode("\r\n", $headers),
            'timeout' => 20,
            'ignore_errors' => true,
        ];
        if ($method === 'POST') {
            $httpOptions['content'] = (string)$body;
        }
        $context = stream_context_create([
            'http' => $httpOptions,
        ]);
        $raw = file_get_contents($url, false, $context);
        if (!is_string($raw) || $raw === '') {
            throw new StripePaymentIntentProviderFailure(
                'transport',
                'stripe_empty_response',
                [],
                'stripe_payment_intent_request_failed'
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
            throw new StripePaymentIntentProviderFailure(
                'response_decode',
                'stripe_response_decode_failed',
                [
                    'response_decode_failed' => true,
                ],
                'stripe_payment_intent_create_failed'
            );
        }
        if ($httpStatus < 200 || $httpStatus >= 300) {
            throw new StripePaymentIntentProviderFailure(
                'provider_http',
                'stripe_provider_http_error',
                [
                    'provider_http_status' => $httpStatus,
                    'provider_error_type' => $decoded['error']['type'] ?? null,
                    'provider_error_code' => $decoded['error']['code'] ?? null,
                    'provider_error_param' => $decoded['error']['param'] ?? null,
                ],
                'stripe_payment_intent_create_failed'
            );
        }

        return $decoded;
    }

    private function assertProviderResponse(array $response, int $amountCents, string $currency): void
    {
        if ((string)($response['object'] ?? '') !== 'payment_intent') {
            throw $this->responseValidationFailure('stripe_object_invalid');
        }
        $providerPaymentId = trim((string)($response['id'] ?? ''));
        if ($providerPaymentId === '' || strpos($providerPaymentId, 'pi_') !== 0) {
            throw $this->responseValidationFailure('stripe_payment_intent_id_invalid');
        }
        if ((bool)($response['livemode'] ?? false)) {
            throw $this->responseValidationFailure('stripe_live_mode_not_allowed', 'stripe_live_mode_not_allowed');
        }
        if ((int)($response['amount'] ?? -1) !== $amountCents) {
            throw $this->responseValidationFailure('stripe_amount_mismatch');
        }
        if (strtoupper((string)($response['currency'] ?? '')) !== $currency) {
            throw $this->responseValidationFailure('stripe_currency_mismatch');
        }
    }

    private function responseValidationFailure(
        string $errorCode,
        string $publicErrorCode = 'stripe_payment_intent_create_failed'
    ): StripePaymentIntentProviderFailure {
        return new StripePaymentIntentProviderFailure(
            'response_validation',
            $errorCode,
            [
                'response_validation_failed' => true,
            ],
            $publicErrorCode
        );
    }

    private function assertRetrieveResponse(array $response, string $providerPaymentId): void
    {
        if ((string)($response['object'] ?? '') !== 'payment_intent') {
            throw new RuntimeException('stripe_payment_intent_retrieve_failed: stripe object is invalid');
        }
        if ((string)($response['id'] ?? '') !== $providerPaymentId) {
            throw new RuntimeException('stripe_payment_intent_retrieve_failed: stripe payment intent id mismatch');
        }
    }

    private function stripeSecretKey(): string
    {
        $secretKey = $this->envValue('STRIPE_SECRET_KEY');
        if ($secretKey === '') {
            throw new StripePaymentIntentProviderFailure(
                'configuration',
                'stripe_secret_key_missing',
                [],
                'stripe_secret_key_missing'
            );
        }
        if (strpos($secretKey, 'sk_live_') === 0 || strpos($secretKey, 'rk_live_') === 0) {
            throw new StripePaymentIntentProviderFailure(
                'configuration',
                'stripe_live_mode_not_allowed',
                [],
                'stripe_live_mode_not_allowed'
            );
        }
        if (strpos($secretKey, 'sk_test_') !== 0 && strpos($secretKey, 'rk_test_') !== 0) {
            throw new StripePaymentIntentProviderFailure(
                'configuration',
                'stripe_provider_unavailable',
                [],
                'stripe_provider_unavailable'
            );
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
