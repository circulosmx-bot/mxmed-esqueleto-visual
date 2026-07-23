<?php
declare(strict_types=1);

namespace Agenda\Contracts;

interface OtpProviderPort
{
    public function providerId(): string;

    public function configured(): bool;

    public function deliver(string $channel, string $destination, string $secret, array $context = []): OtpDeliveryResult;
}

final class OtpDeliveryResult
{
    public function __construct(
        private readonly bool $accepted,
        private readonly string $reason,
        private readonly ?string $providerReference
    ) {}

    public function accepted(): bool
    {
        return $this->accepted;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public function providerReference(): ?string
    {
        return $this->providerReference;
    }

    public function toArray(): array
    {
        return [
            'accepted' => $this->accepted,
            'reason' => $this->reason,
            'provider_reference' => $this->providerReference,
        ];
    }
}

final class RejectingOtpProvider implements OtpProviderPort
{
    public function providerId(): string
    {
        return 'rejecting';
    }

    public function configured(): bool
    {
        return false;
    }

    public function deliver(string $channel, string $destination, string $secret, array $context = []): OtpDeliveryResult
    {
        return new OtpDeliveryResult(false, 'provider_not_configured', null);
    }
}
