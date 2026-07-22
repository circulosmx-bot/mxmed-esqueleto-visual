<?php
declare(strict_types=1);

namespace Agenda\PublicFlow;

final readonly class PublicOtpChallenge
{
    private string $challengeId;
    private string $intentId;
    private string $bindingFingerprint;
    private string $credentialHash;
    private string $state;
    private int $attemptsUsed;
    private int $maxAttempts;
    private string $createdAt;
    private string $expiresAt;
    private ?string $verifiedAt;
    private ?string $consumedAt;
    private int $policyVersion;
    private ?string $grantDigest;

    public function __construct(
        string $challengeId,
        string $intentId,
        string $bindingFingerprint,
        string $credentialHash,
        string $state,
        int $attemptsUsed,
        int $maxAttempts,
        string $createdAt,
        string $expiresAt,
        ?string $verifiedAt = null,
        ?string $consumedAt = null,
        int $policyVersion = PublicAgendaPolicy::VERSION,
        ?string $grantDigest = null
    ) {
        $this->challengeId = PublicAgendaPolicy::identifier($challengeId, 'invalid_challenge');
        $this->intentId = PublicAgendaPolicy::identifier($intentId, 'invalid_challenge');
        if (preg_match('/\A[0-9a-f]{64}\z/D', $bindingFingerprint) !== 1) throw new PublicAgendaDomainException('invalid_binding_fingerprint');
        if ($credentialHash === '' || strlen($credentialHash) > 255 || preg_match('/[\x00-\x1F\x7F]/', $credentialHash) === 1) {
            throw new PublicAgendaDomainException('invalid_credential_hash');
        }
        $credentialInfo = password_get_info($credentialHash);
        if (($credentialInfo['algo'] ?? 0) === 0) throw new PublicAgendaDomainException('invalid_credential_hash');
        if (!PublicAgendaPolicy::isState($state)) throw new PublicAgendaDomainException('invalid_challenge_state');
        if ($maxAttempts !== PublicAgendaPolicy::OTP_MAX_ATTEMPTS || $attemptsUsed < 0 || $attemptsUsed > $maxAttempts) {
            throw new PublicAgendaDomainException('invalid_attempt_count');
        }
        if ($state === 'locked' && $attemptsUsed !== $maxAttempts) throw new PublicAgendaDomainException('invalid_challenge_state');
        if ($state === 'verified' && $verifiedAt === null) throw new PublicAgendaDomainException('invalid_challenge_state');
        if ($state === 'consumed' && ($verifiedAt === null || $consumedAt === null || $grantDigest === null)) {
            throw new PublicAgendaDomainException('invalid_challenge_state');
        }
        if ($policyVersion !== PublicAgendaPolicy::VERSION) throw new PublicAgendaDomainException('invalid_policy_version');
        $created = PublicAgendaPolicy::timestamp($createdAt, 'invalid_challenge');
        $expires = PublicAgendaPolicy::timestamp($expiresAt, 'invalid_challenge');
        if ($created >= $expires) throw new PublicAgendaDomainException('invalid_challenge');
        $verified = $verifiedAt === null ? null : PublicAgendaPolicy::timestamp($verifiedAt, 'invalid_challenge')->format('Y-m-d\TH:i:s.uP');
        $consumed = $consumedAt === null ? null : PublicAgendaPolicy::timestamp($consumedAt, 'invalid_challenge')->format('Y-m-d\TH:i:s.uP');
        if ($grantDigest !== null && preg_match('/\A[0-9a-f]{64}\z/D', $grantDigest) !== 1) throw new PublicAgendaDomainException('invalid_challenge');
        $this->bindingFingerprint = $bindingFingerprint;
        $this->credentialHash = $credentialHash;
        $this->state = $state;
        $this->attemptsUsed = $attemptsUsed;
        $this->maxAttempts = $maxAttempts;
        $this->createdAt = $created->format('Y-m-d\TH:i:s.uP');
        $this->expiresAt = $expires->format('Y-m-d\TH:i:s.uP');
        $this->verifiedAt = $verified;
        $this->consumedAt = $consumed;
        $this->policyVersion = $policyVersion;
        $this->grantDigest = $grantDigest;
    }

    public function challengeId(): string { return $this->challengeId; }
    public function intentId(): string { return $this->intentId; }
    public function bindingFingerprint(): string { return $this->bindingFingerprint; }
    public function credentialHash(): string { return $this->credentialHash; }
    public function state(): string { return $this->state; }
    public function attemptsUsed(): int { return $this->attemptsUsed; }
    public function maxAttempts(): int { return $this->maxAttempts; }
    public function createdAt(): string { return $this->createdAt; }
    public function expiresAt(): string { return $this->expiresAt; }
    public function verifiedAt(): ?string { return $this->verifiedAt; }
    public function consumedAt(): ?string { return $this->consumedAt; }
    public function policyVersion(): int { return $this->policyVersion; }
    public function grantDigest(): ?string { return $this->grantDigest; }
    public function terminal(): bool { return in_array($this->state, ['expired', 'locked', 'consumed'], true); }

    public function withAttempt(int $attempts, string $state): self
    {
        return new self($this->challengeId, $this->intentId, $this->bindingFingerprint, $this->credentialHash, $state, $attempts, $this->maxAttempts, $this->createdAt, $this->expiresAt, $this->verifiedAt, $this->consumedAt, $this->policyVersion, $this->grantDigest);
    }

    public function verified(string $verifiedAt, string $grantDigest): self
    {
        return new self($this->challengeId, $this->intentId, $this->bindingFingerprint, $this->credentialHash, 'verified', $this->attemptsUsed, $this->maxAttempts, $this->createdAt, $this->expiresAt, $verifiedAt, null, $this->policyVersion, $grantDigest);
    }

    public function consumed(string $consumedAt): self
    {
        if ($this->state !== 'verified' || $this->grantDigest === null) throw new PublicAgendaDomainException('challenge_consumed');
        return new self($this->challengeId, $this->intentId, $this->bindingFingerprint, $this->credentialHash, 'consumed', $this->attemptsUsed, $this->maxAttempts, $this->createdAt, $this->expiresAt, $this->verifiedAt, $consumedAt, $this->policyVersion, $this->grantDigest);
    }

    public function toArray(): array
    {
        return [
            'challenge_id' => $this->challengeId,
            'intent_id' => $this->intentId,
            'binding_fingerprint' => $this->bindingFingerprint,
            'credential_hash' => $this->credentialHash,
            'state' => $this->state,
            'attempts_used' => $this->attemptsUsed,
            'max_attempts' => $this->maxAttempts,
            'created_at' => $this->createdAt,
            'expires_at' => $this->expiresAt,
            'verified_at' => $this->verifiedAt,
            'consumed_at' => $this->consumedAt,
            'policy_version' => $this->policyVersion,
            'grant_digest' => $this->grantDigest,
        ];
    }
}
