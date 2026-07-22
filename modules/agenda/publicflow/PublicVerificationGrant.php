<?php
declare(strict_types=1);

namespace Agenda\PublicFlow;

final readonly class PublicVerificationGrant
{
    private string $grantId;
    private string $grantDigest;
    private string $challengeId;
    private string $intentId;
    private string $bindingFingerprint;
    private string $issuedAt;
    private string $expiresAt;
    private ?string $consumedAt;
    private int $policyVersion;

    public function __construct(string $grantId, string $grantDigest, string $challengeId, string $intentId, string $bindingFingerprint, string $issuedAt, string $expiresAt, ?string $consumedAt = null, int $policyVersion = PublicAgendaPolicy::VERSION)
    {
        $this->grantId = PublicAgendaPolicy::identifier($grantId, 'invalid_grant');
        if (preg_match('/\A[0-9a-f]{64}\z/D', $grantDigest) !== 1 || preg_match('/\A[0-9a-f]{64}\z/D', $bindingFingerprint) !== 1) throw new PublicAgendaDomainException('invalid_grant');
        if ($policyVersion !== PublicAgendaPolicy::VERSION) throw new PublicAgendaDomainException('invalid_policy_version');
        $issued = PublicAgendaPolicy::timestamp($issuedAt, 'invalid_grant');
        $expires = PublicAgendaPolicy::timestamp($expiresAt, 'invalid_grant');
        if ($issued >= $expires) throw new PublicAgendaDomainException('invalid_grant');
        $this->grantDigest = $grantDigest;
        $this->challengeId = PublicAgendaPolicy::identifier($challengeId, 'invalid_grant');
        $this->intentId = PublicAgendaPolicy::identifier($intentId, 'invalid_grant');
        $this->bindingFingerprint = $bindingFingerprint;
        $this->issuedAt = $issued->format('Y-m-d\TH:i:s.uP');
        $this->expiresAt = $expires->format('Y-m-d\TH:i:s.uP');
        $this->consumedAt = $consumedAt === null ? null : PublicAgendaPolicy::timestamp($consumedAt, 'invalid_grant')->format('Y-m-d\TH:i:s.uP');
        $this->policyVersion = $policyVersion;
    }

    public static function issue(PublicOtpChallenge $challenge, PublicBookingIntent $intent, string $issuedAt): self
    {
        if ($challenge->intentId() !== $intent->intentId() || $challenge->bindingFingerprint() !== $intent->bindingFingerprint()) throw new PublicAgendaDomainException('binding_mismatch');
        $digest = PublicAgendaPolicy::digest(['challenge_id' => $challenge->challengeId(), 'intent_id' => $intent->intentId(), 'binding_fingerprint' => $intent->bindingFingerprint(), 'issued_at' => $issuedAt, 'policy_version' => PublicAgendaPolicy::VERSION]);
        $id = 'grant:' . substr($digest, 0, 32);
        return new self($id, $digest, $challenge->challengeId(), $intent->intentId(), $intent->bindingFingerprint(), $issuedAt, $intent->expiresAt());
    }

    public function grantId(): string { return $this->grantId; }
    public function grantDigest(): string { return $this->grantDigest; }
    public function challengeId(): string { return $this->challengeId; }
    public function intentId(): string { return $this->intentId; }
    public function bindingFingerprint(): string { return $this->bindingFingerprint; }
    public function issuedAt(): string { return $this->issuedAt; }
    public function expiresAt(): string { return $this->expiresAt; }
    public function consumedAt(): ?string { return $this->consumedAt; }
    public function policyVersion(): int { return $this->policyVersion; }
    public function consumed(): bool { return $this->consumedAt !== null; }
    public function consume(string $consumedAt): self
    {
        if ($this->consumed()) throw new PublicAgendaDomainException('grant_consumed');
        $when = PublicAgendaPolicy::timestamp($consumedAt, 'grant_consumed');
        if ($when >= PublicAgendaPolicy::timestamp($this->expiresAt, 'grant_expired')) throw new PublicAgendaDomainException('grant_expired');
        return new self($this->grantId, $this->grantDigest, $this->challengeId, $this->intentId, $this->bindingFingerprint, $this->issuedAt, $this->expiresAt, $consumedAt, $this->policyVersion);
    }
    public function toArray(): array
    {
        return ['grant_id' => $this->grantId, 'grant_digest' => $this->grantDigest, 'challenge_id' => $this->challengeId, 'intent_id' => $this->intentId, 'binding_fingerprint' => $this->bindingFingerprint, 'issued_at' => $this->issuedAt, 'expires_at' => $this->expiresAt, 'consumed_at' => $this->consumedAt, 'policy_version' => $this->policyVersion];
    }
}
