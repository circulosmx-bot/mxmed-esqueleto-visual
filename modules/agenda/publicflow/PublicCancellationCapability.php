<?php
declare(strict_types=1);

namespace Agenda\PublicFlow;

final readonly class PublicCancellationCapability
{
    private string $capabilityId;
    private string $capabilityDigest;
    private string $intentId;
    private string $bindingFingerprint;
    private string $issuedAt;
    private string $expiresAt;
    private ?string $consumedAt;

    public function __construct(string $capabilityId, string $capabilityDigest, string $intentId, string $bindingFingerprint, string $issuedAt, string $expiresAt, ?string $consumedAt = null)
    {
        $this->capabilityId = PublicAgendaPolicy::identifier($capabilityId, 'invalid_cancellation_capability');
        if (preg_match('/\A[0-9a-f]{64}\z/D', $capabilityDigest) !== 1 || preg_match('/\A[0-9a-f]{64}\z/D', $bindingFingerprint) !== 1) throw new PublicAgendaDomainException('invalid_cancellation_capability');
        $issued = PublicAgendaPolicy::timestamp($issuedAt, 'invalid_cancellation_capability');
        $expires = PublicAgendaPolicy::timestamp($expiresAt, 'invalid_cancellation_capability');
        if ($issued >= $expires) throw new PublicAgendaDomainException('invalid_cancellation_capability');
        $this->capabilityDigest = $capabilityDigest;
        $this->intentId = PublicAgendaPolicy::identifier($intentId, 'invalid_cancellation_capability');
        $this->bindingFingerprint = $bindingFingerprint;
        $this->issuedAt = $issued->format('Y-m-d\TH:i:s.uP');
        $this->expiresAt = $expires->format('Y-m-d\TH:i:s.uP');
        $this->consumedAt = $consumedAt === null ? null : PublicAgendaPolicy::timestamp($consumedAt, 'invalid_cancellation_capability')->format('Y-m-d\TH:i:s.uP');
    }

    public static function issue(PublicBookingIntent $intent, string $issuedAt): self
    {
        $digest = PublicAgendaPolicy::digest(['intent_id' => $intent->intentId(), 'binding_fingerprint' => $intent->bindingFingerprint(), 'issued_at' => $issuedAt, 'purpose' => 'public_cancel']);
        return new self('capability:' . substr($digest, 0, 32), $digest, $intent->intentId(), $intent->bindingFingerprint(), $issuedAt, $intent->expiresAt());
    }
    public function capabilityId(): string { return $this->capabilityId; }
    public function capabilityDigest(): string { return $this->capabilityDigest; }
    public function intentId(): string { return $this->intentId; }
    public function bindingFingerprint(): string { return $this->bindingFingerprint; }
    public function issuedAt(): string { return $this->issuedAt; }
    public function expiresAt(): string { return $this->expiresAt; }
    public function consumedAt(): ?string { return $this->consumedAt; }
    public function consumed(): bool { return $this->consumedAt !== null; }
    public function consume(string $consumedAt): self
    {
        if ($this->consumed()) throw new PublicAgendaDomainException('grant_consumed');
        $when = PublicAgendaPolicy::timestamp($consumedAt, 'invalid_cancellation_capability');
        if ($when >= PublicAgendaPolicy::timestamp($this->expiresAt, 'invalid_cancellation_capability')) throw new PublicAgendaDomainException('invalid_cancellation_capability');
        return new self($this->capabilityId, $this->capabilityDigest, $this->intentId, $this->bindingFingerprint, $this->issuedAt, $this->expiresAt, $consumedAt);
    }
    public function toArray(): array
    {
        return ['capability_id' => $this->capabilityId, 'capability_digest' => $this->capabilityDigest, 'intent_id' => $this->intentId, 'binding_fingerprint' => $this->bindingFingerprint, 'issued_at' => $this->issuedAt, 'expires_at' => $this->expiresAt, 'consumed_at' => $this->consumedAt];
    }
}
