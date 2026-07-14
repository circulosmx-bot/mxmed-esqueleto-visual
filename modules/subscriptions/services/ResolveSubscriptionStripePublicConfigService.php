<?php
declare(strict_types=1);

namespace Subscriptions\Services;

use RuntimeException;
use Throwable;

final class ResolveSubscriptionStripePublicConfigException extends RuntimeException
{
    private int $status;
    private string $errorCode;
    private string $publicMessage;

    public function __construct(int $status, string $errorCode, string $publicMessage, ?Throwable $previous = null)
    {
        parent::__construct($publicMessage, 0, $previous);
        $this->status = $status;
        $this->errorCode = $errorCode;
        $this->publicMessage = $publicMessage;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function publicMessage(): string
    {
        return $this->publicMessage;
    }
}

final class ResolveSubscriptionStripePublicConfigService
{
    private const PROVIDER = 'stripe';
    private const PUBLIC_ERROR_MESSAGE = 'Por el momento no fue posible habilitar el formulario de pago seguro. Intentalo nuevamente en unos minutos.';

    public function resolve(?string $publishableKey, array $livemodeExpectation): array
    {
        $key = trim((string)($publishableKey ?? ''));
        if ($key === '') {
            throw $this->error(503, 'stripe_publishable_key_missing');
        }

        $mode = $this->publishableKeyMode($key);
        if ($mode === 'invalid') {
            throw $this->error(500, 'stripe_publishable_key_invalid');
        }

        if (!(bool)($livemodeExpectation['ok'] ?? false) || !array_key_exists('expected_livemode', $livemodeExpectation)) {
            throw $this->error(500, 'stripe_livemode_mismatch');
        }

        $expectedLivemode = (bool)$livemodeExpectation['expected_livemode'];
        $keyLivemode = $mode === 'live';
        if ($keyLivemode !== $expectedLivemode) {
            throw $this->error(500, 'stripe_livemode_mismatch');
        }

        return [
            'provider' => self::PROVIDER,
            'publishable_key' => $key,
            'livemode' => $keyLivemode,
            'payment_element_enabled' => true,
        ];
    }

    private function publishableKeyMode(string $key): string
    {
        if (str_starts_with($key, 'pk_test_')) {
            return 'test';
        }

        if (str_starts_with($key, 'pk_live_')) {
            return 'live';
        }

        return 'invalid';
    }

    private function error(int $status, string $code): ResolveSubscriptionStripePublicConfigException
    {
        return new ResolveSubscriptionStripePublicConfigException($status, $code, self::PUBLIC_ERROR_MESSAGE);
    }
}
