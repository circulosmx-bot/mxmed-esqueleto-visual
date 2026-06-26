<?php
declare(strict_types=1);

namespace Subscriptions\Services;

use InvalidArgumentException;

final class SubscriptionPaymentIntentMockProvider
{
    public const PROVIDER = 'mxmed_mock';
    public const DEFAULT_NORMALIZED_STATUS = 'created';
    public const DEFAULT_PROVIDER_STATUS = 'mock_created';

    private const PROVIDER_PAYMENT_ID_PREFIX = 'mxmed_mock_pi_';
    private const PROVIDER_CHECKOUT_ID_PREFIX = 'mxmed_mock_chk_';
    private const ALLOWED_INITIAL_STATUSES = [
        'created',
        'pending_provider',
    ];

    public function create(array $input): array
    {
        $checkoutIntentUuid = $this->requiredText(
            $input['checkout_intent_uuid'] ?? null,
            'invalid_payment_intent_payload: checkout_intent_uuid is required'
        );
        $amountCents = $this->requiredPositiveInt($input['amount_cents'] ?? null);
        $currency = strtoupper($this->requiredText(
            $input['currency'] ?? null,
            'invalid_payment_intent_payload: currency is required'
        ));
        $provider = $this->provider($input['provider'] ?? self::PROVIDER);
        $normalizedStatus = $this->normalizedStatus($input['normalized_status'] ?? self::DEFAULT_NORMALIZED_STATUS);
        $source = $this->optionalText($input['source'] ?? 'payment_intent_mock');

        $token = $this->stableToken([
            'checkout_intent_uuid' => $checkoutIntentUuid,
            'amount_cents' => $amountCents,
            'currency' => $currency,
            'provider' => $provider,
            'source' => $source,
        ]);
        $providerPaymentId = self::PROVIDER_PAYMENT_ID_PREFIX . $token;
        $providerCheckoutId = self::PROVIDER_CHECKOUT_ID_PREFIX . $this->stableToken([
            'checkout_intent_uuid' => $checkoutIntentUuid,
            'provider' => $provider,
        ]);

        return [
            'provider' => $provider,
            'provider_payment_id' => $providerPaymentId,
            'provider_checkout_id' => $providerCheckoutId,
            'provider_status' => self::DEFAULT_PROVIDER_STATUS,
            'normalized_status' => $normalizedStatus,
            'amount_cents' => $amountCents,
            'currency' => $currency,
            'checkout_intent_uuid' => $checkoutIntentUuid,
            'source' => $source,
            'raw_response' => [
                'mock' => true,
                'provider' => $provider,
                'provider_payment_id' => $providerPaymentId,
                'provider_checkout_id' => $providerCheckoutId,
                'provider_status' => self::DEFAULT_PROVIDER_STATUS,
                'normalized_status' => $normalizedStatus,
                'external_calls' => false,
                'real_payment' => false,
            ],
        ];
    }

    private function provider($value): string
    {
        $provider = $this->requiredText($value, 'invalid_payment_intent_payload: provider is required');
        if ($provider !== self::PROVIDER) {
            throw new InvalidArgumentException('invalid_payment_intent_payload: provider must be mxmed_mock');
        }

        return $provider;
    }

    private function normalizedStatus($value): string
    {
        $status = $this->requiredText($value, 'invalid_payment_intent_payload: normalized_status is required');
        if ($status === 'paid') {
            throw new InvalidArgumentException('invalid_payment_intent_payload: paid status is not allowed for mock create');
        }
        if (!in_array($status, self::ALLOWED_INITIAL_STATUSES, true)) {
            throw new InvalidArgumentException('invalid_payment_intent_payload: normalized_status must be initial');
        }

        return $status;
    }

    private function requiredText($value, string $message): string
    {
        $text = trim((string)($value ?? ''));
        if ($text === '') {
            throw new InvalidArgumentException($message);
        }

        return $text;
    }

    private function optionalText($value): string
    {
        $text = trim((string)($value ?? ''));
        return $text !== '' ? $text : 'payment_intent_mock';
    }

    private function requiredPositiveInt($value): int
    {
        if (is_int($value)) {
            $amount = $value;
        } else {
            $text = trim((string)($value ?? ''));
            if ($text === '' || !ctype_digit($text)) {
                throw new InvalidArgumentException('invalid_payment_intent_payload: amount_cents is required');
            }
            $amount = (int)$text;
        }

        if ($amount <= 0) {
            throw new InvalidArgumentException('invalid_payment_intent_payload: amount_cents must be positive');
        }

        return $amount;
    }

    private function stableToken(array $payload): string
    {
        ksort($payload);
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return substr(hash('sha256', $json !== false ? $json : ''), 0, 32);
    }
}
