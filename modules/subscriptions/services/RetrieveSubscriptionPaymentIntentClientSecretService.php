<?php
declare(strict_types=1);

namespace Subscriptions\Services;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Throwable;

final class RetrieveSubscriptionPaymentIntentClientSecretException extends RuntimeException
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

final class RetrieveSubscriptionPaymentIntentClientSecretService
{
    private const PROVIDER_STRIPE = 'stripe';
    private const STATUS_PAYMENT_INTENT_CREATED = 'created';
    private const PROVIDER_STATUS_REQUIRES_PAYMENT_METHOD = 'requires_payment_method';
    private const CHECKOUT_STATUS_PENDING_PAYMENT = 'pending_payment';
    private const ROUTE_STATUS_CHECKOUT_CREATED_NO_PROVIDER = 'checkout_created_no_provider';
    private const BILLING_PERIOD_ANNUAL = 'annual';
    private const ROUTE_TYPES_ALLOWED = ['new_subscription', 'upgrade_subscription'];

    private const NOT_FOUND_MESSAGE = 'No encontramos una operación de pago disponible.';
    private const CONFLICT_MESSAGE = 'La operación de pago ya no está disponible para completarse.';
    private const PROVIDER_MESSAGE = 'No pudimos preparar de forma segura esta operación. Inténtalo nuevamente.';
    private const MISMATCH_MESSAGE = 'No pudimos verificar de forma segura esta operación de pago.';

    private $paymentIntentRepository;
    private $checkoutIntentRepository;
    private $paymentRouteRepository;
    private $stripeProvider;

    public function __construct(
        $paymentIntentRepository,
        $checkoutIntentRepository,
        $paymentRouteRepository,
        $stripeProvider
    ) {
        $this->paymentIntentRepository = $paymentIntentRepository;
        $this->checkoutIntentRepository = $checkoutIntentRepository;
        $this->paymentRouteRepository = $paymentRouteRepository;
        $this->stripeProvider = $stripeProvider;
    }

    public function retrieve(array $context, string $paymentIntentUuid): array
    {
        $entityType = $this->requiredContextText($context['entity_type'] ?? null, 64);
        $entityId = $this->requiredContextText($context['entity_id'] ?? null, 64);
        $paymentIntentUuid = trim($paymentIntentUuid);
        if (!$this->validUuid($paymentIntentUuid)) {
            throw new RetrieveSubscriptionPaymentIntentClientSecretException(
                422,
                'invalid_payment_intent_uuid',
                'La operación de pago no es válida.'
            );
        }

        $paymentIntent = $this->findPaymentIntent($paymentIntentUuid);
        if ($paymentIntent === null) {
            throw $this->notFound();
        }
        $this->assertOptionalEntityMatches($paymentIntent, $entityType, $entityId);

        $checkoutIntentUuid = $this->cleanText($paymentIntent['checkout_intent_uuid'] ?? null, 36);
        if ($checkoutIntentUuid === null) {
            throw $this->notFound();
        }

        $checkoutIntent = $this->findCheckoutIntent($checkoutIntentUuid);
        if ($checkoutIntent === null) {
            throw $this->notFound();
        }
        $this->assertEntityMatches($checkoutIntent, $entityType, $entityId);
        $this->assertLocalPaymentIntentEligible($paymentIntent);
        $this->assertCheckoutEligible($checkoutIntent, $paymentIntent);

        $paymentRouteUuid = $this->cleanText($checkoutIntent['payment_route_uuid'] ?? null, 36);
        if ($paymentRouteUuid === null) {
            throw $this->conflict('payment_route_not_available');
        }

        $paymentRoute = $this->findPaymentRoute($paymentRouteUuid);
        if ($paymentRoute === null) {
            throw $this->conflict('payment_route_not_available');
        }
        $this->assertEntityMatches($paymentRoute, $entityType, $entityId);
        $this->assertPaymentRouteEligible($paymentRoute, $checkoutIntent);

        $providerPaymentId = $this->cleanText($paymentIntent['provider_payment_id'] ?? null, 128);
        if ($providerPaymentId === null || preg_match('/^pi_[A-Za-z0-9_]+$/', $providerPaymentId) !== 1) {
            throw $this->conflict('payment_intent_provider_reference_invalid');
        }

        $remote = $this->retrieveRemotePaymentIntent($providerPaymentId);
        $this->assertRemoteMatchesLocal($remote, $paymentIntent, $checkoutIntent, $entityType, $entityId);

        $clientSecret = trim((string)($remote['client_secret'] ?? ''));
        if ($clientSecret === '') {
            throw $this->providerUnavailable('payment_provider_unavailable');
        }

        return [
            'client_secret' => $clientSecret,
        ];
    }

    private function assertLocalPaymentIntentEligible(array $paymentIntent): void
    {
        if ((string)($paymentIntent['provider'] ?? '') !== self::PROVIDER_STRIPE) {
            throw $this->conflict('payment_intent_provider_invalid');
        }
        if ((string)($paymentIntent['normalized_status'] ?? '') !== self::STATUS_PAYMENT_INTENT_CREATED) {
            throw $this->conflict('payment_intent_state_not_allowed');
        }
        if ((string)($paymentIntent['provider_status'] ?? '') !== self::PROVIDER_STATUS_REQUIRES_PAYMENT_METHOD) {
            throw $this->conflict('payment_intent_provider_state_not_allowed');
        }
        if ((int)($paymentIntent['amount_cents'] ?? 0) <= 0 || trim((string)($paymentIntent['currency'] ?? '')) === '') {
            throw $this->conflict('payment_intent_snapshot_invalid');
        }
    }

    private function assertCheckoutEligible(array $checkoutIntent, array $paymentIntent): void
    {
        if ((string)($checkoutIntent['uuid'] ?? '') !== (string)($paymentIntent['checkout_intent_uuid'] ?? '')) {
            throw $this->notFound();
        }
        if ((string)($checkoutIntent['status'] ?? '') !== self::CHECKOUT_STATUS_PENDING_PAYMENT) {
            throw $this->conflict('checkout_intent_state_not_allowed');
        }
        if ($this->cleanText($checkoutIntent['subscription_id'] ?? null, 36) !== null
            || $this->cleanText($checkoutIntent['activated_at'] ?? null, 32) !== null
        ) {
            throw $this->conflict('checkout_intent_already_activated');
        }
        $this->assertNotExpired($checkoutIntent['expires_at'] ?? null, 'checkout_intent_expired');
        if ((int)($checkoutIntent['amount_cents'] ?? -1) !== (int)($paymentIntent['amount_cents'] ?? -2)) {
            throw $this->conflict('payment_intent_checkout_amount_mismatch');
        }
        if (strtoupper((string)($checkoutIntent['currency'] ?? '')) !== strtoupper((string)($paymentIntent['currency'] ?? ''))) {
            throw $this->conflict('payment_intent_checkout_currency_mismatch');
        }
    }

    private function assertPaymentRouteEligible(array $paymentRoute, array $checkoutIntent): void
    {
        if ((string)($paymentRoute['checkout_intent_uuid'] ?? '') !== (string)($checkoutIntent['uuid'] ?? '')) {
            throw $this->notFound();
        }
        if ((string)($paymentRoute['status'] ?? '') !== self::ROUTE_STATUS_CHECKOUT_CREATED_NO_PROVIDER) {
            throw $this->conflict('payment_route_state_not_allowed');
        }
        $this->assertNotExpired($paymentRoute['expires_at'] ?? null, 'payment_route_expired');
        if (!in_array((string)($paymentRoute['route_type'] ?? ''), self::ROUTE_TYPES_ALLOWED, true)) {
            throw $this->conflict('payment_route_type_not_allowed');
        }
        if ((string)($paymentRoute['billing_period'] ?? '') !== self::BILLING_PERIOD_ANNUAL) {
            throw $this->conflict('payment_route_billing_period_not_allowed');
        }
        if ((int)($paymentRoute['amount_cents'] ?? -1) !== (int)($checkoutIntent['amount_cents'] ?? -2)) {
            throw $this->conflict('payment_route_checkout_amount_mismatch');
        }
        if (strtoupper((string)($paymentRoute['currency'] ?? '')) !== strtoupper((string)($checkoutIntent['currency'] ?? ''))) {
            throw $this->conflict('payment_route_checkout_currency_mismatch');
        }
    }

    private function assertRemoteMatchesLocal(
        array $remote,
        array $paymentIntent,
        array $checkoutIntent,
        string $entityType,
        string $entityId
    ): void {
        if ((string)($remote['id'] ?? '') !== (string)($paymentIntent['provider_payment_id'] ?? '')) {
            throw $this->providerMismatch();
        }
        if ((int)($remote['amount'] ?? -1) !== (int)($paymentIntent['amount_cents'] ?? -2)) {
            throw $this->providerMismatch();
        }
        if (strtolower((string)($remote['currency'] ?? '')) !== strtolower((string)($paymentIntent['currency'] ?? ''))) {
            throw $this->providerMismatch();
        }
        if ((bool)($remote['livemode'] ?? true) !== false) {
            throw $this->providerMismatch();
        }
        if ((string)($remote['status'] ?? '') !== self::PROVIDER_STATUS_REQUIRES_PAYMENT_METHOD) {
            throw $this->providerMismatch();
        }

        $metadata = is_array($remote['metadata'] ?? null) ? $remote['metadata'] : [];
        $expected = [
            'mxmed_checkout_intent_uuid' => (string)($checkoutIntent['uuid'] ?? ''),
            'mxmed_payment_intent_uuid' => (string)($paymentIntent['uuid'] ?? ''),
            'mxmed_entity_type' => $entityType,
            'mxmed_entity_id' => $entityId,
            'mxmed_plan_code' => (string)($checkoutIntent['plan_code'] ?? ''),
            'mxmed_billing_period' => (string)($checkoutIntent['billing_period'] ?? ''),
        ];
        foreach ($expected as $key => $value) {
            if ($value === '' || (string)($metadata[$key] ?? '') !== $value) {
                throw $this->providerMismatch();
            }
        }
    }

    private function assertEntityMatches(array $record, string $entityType, string $entityId): void
    {
        if ((string)($record['entity_type'] ?? '') !== $entityType || (string)($record['entity_id'] ?? '') !== $entityId) {
            throw $this->notFound();
        }
    }

    private function assertOptionalEntityMatches(array $record, string $entityType, string $entityId): void
    {
        $recordEntityType = $this->cleanText($record['entity_type'] ?? null, 64);
        $recordEntityId = $this->cleanText($record['entity_id'] ?? null, 64);
        if ($recordEntityType === null && $recordEntityId === null) {
            return;
        }
        if ($recordEntityType !== $entityType || $recordEntityId !== $entityId) {
            throw $this->notFound();
        }
    }

    private function assertNotExpired($expiresAt, string $code): void
    {
        $expiresAtText = $this->cleanText($expiresAt, 32);
        if ($expiresAtText === null) {
            throw $this->conflict($code);
        }

        try {
            $expiresAtDate = new DateTimeImmutable($expiresAtText, new DateTimeZone('UTC'));
            $nowDate = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        } catch (Throwable $e) {
            throw $this->conflict($code);
        }

        if ($expiresAtDate < $nowDate) {
            throw $this->conflict($code);
        }
    }

    private function retrieveRemotePaymentIntent(string $providerPaymentId): array
    {
        try {
            $remote = $this->stripeProvider->retrieveClientSecret($providerPaymentId);
        } catch (Throwable $e) {
            throw $this->providerUnavailable('payment_provider_unavailable', $e);
        }
        if (!is_array($remote)) {
            throw $this->providerUnavailable('payment_provider_unavailable');
        }

        return $remote;
    }

    private function findPaymentIntent(string $paymentIntentUuid): ?array
    {
        try {
            $record = $this->paymentIntentRepository->findByUuid($paymentIntentUuid);
        } catch (Throwable $e) {
            throw $this->providerUnavailable('payment_intent_lookup_unavailable', $e);
        }

        return is_array($record) ? $record : null;
    }

    private function findCheckoutIntent(string $checkoutIntentUuid): ?array
    {
        try {
            $record = $this->checkoutIntentRepository->findByUuid($checkoutIntentUuid);
        } catch (Throwable $e) {
            throw $this->providerUnavailable('checkout_intent_lookup_unavailable', $e);
        }

        return is_array($record) ? $record : null;
    }

    private function findPaymentRoute(string $paymentRouteUuid): ?array
    {
        try {
            $record = $this->paymentRouteRepository->findByUuid($paymentRouteUuid);
        } catch (Throwable $e) {
            throw $this->providerUnavailable('payment_route_lookup_unavailable', $e);
        }

        return is_array($record) ? $record : null;
    }

    private function requiredContextText($value, int $maxLength): string
    {
        $text = trim((string)($value ?? ''));
        if ($text === '' || strlen($text) > $maxLength) {
            throw $this->notFound();
        }

        return $text;
    }

    private function cleanText($value, int $maxLength): ?string
    {
        $text = trim((string)($value ?? ''));
        if ($text === '' || strlen($text) > $maxLength) {
            return null;
        }

        return $text;
    }

    private function validUuid(string $uuid): bool
    {
        return preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $uuid) === 1;
    }

    private function notFound(): RetrieveSubscriptionPaymentIntentClientSecretException
    {
        return new RetrieveSubscriptionPaymentIntentClientSecretException(
            404,
            'payment_intent_not_found',
            self::NOT_FOUND_MESSAGE
        );
    }

    private function conflict(string $code): RetrieveSubscriptionPaymentIntentClientSecretException
    {
        return new RetrieveSubscriptionPaymentIntentClientSecretException(
            409,
            $code,
            self::CONFLICT_MESSAGE
        );
    }

    private function providerUnavailable(string $code, ?Throwable $previous = null): RetrieveSubscriptionPaymentIntentClientSecretException
    {
        return new RetrieveSubscriptionPaymentIntentClientSecretException(
            502,
            $code,
            self::PROVIDER_MESSAGE,
            $previous
        );
    }

    private function providerMismatch(): RetrieveSubscriptionPaymentIntentClientSecretException
    {
        return new RetrieveSubscriptionPaymentIntentClientSecretException(
            502,
            'payment_intent_provider_mismatch',
            self::MISMATCH_MESSAGE
        );
    }
}
