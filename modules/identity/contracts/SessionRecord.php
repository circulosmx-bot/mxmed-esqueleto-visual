<?php
declare(strict_types=1);

namespace Identity\Contracts;

final class SessionRecord
{
    public function __construct(
        private SessionId $sessionId,
        private SessionTokenDigest $tokenDigest,
        private SessionPrincipal $principal,
        private \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $lastSeenAt,
        private \DateTimeImmutable $expiresAt,
        private \DateTimeImmutable $absoluteExpiresAt,
        private string $state = SessionState::ACTIVE,
        private string $deviceLabel = '',
        private string $userAgentHash = '',
        private string $ipDimensionHash = ''
    ) {
        SessionState::assertValid($this->state);
        if ($this->deviceLabel !== '' && (strlen($this->deviceLabel) > 80 || preg_match('/[\x00-\x1F\x7F]/', $this->deviceLabel) === 1)) throw new \InvalidArgumentException('invalid_device_label');
        if ($this->absoluteExpiresAt < $this->createdAt || $this->lastSeenAt < $this->createdAt) throw new \InvalidArgumentException('invalid_session_times');
    }

    public function sessionId(): SessionId { return $this->sessionId; }
    public function tokenDigest(): SessionTokenDigest { return $this->tokenDigest; }
    public function principal(): SessionPrincipal { return $this->principal; }
    public function createdAt(): \DateTimeImmutable { return $this->createdAt; }
    public function lastSeenAt(): \DateTimeImmutable { return $this->lastSeenAt; }
    public function expiresAt(): \DateTimeImmutable { return $this->expiresAt; }
    public function absoluteExpiresAt(): \DateTimeImmutable { return $this->absoluteExpiresAt; }
    public function state(): string { return $this->state; }
    public function deviceLabel(): string { return $this->deviceLabel; }
    public function userAgentHash(): string { return $this->userAgentHash; }
    public function ipDimensionHash(): string { return $this->ipDimensionHash; }

    public function withState(string $state): self { return new self($this->sessionId, $this->tokenDigest, $this->principal, $this->createdAt, $this->lastSeenAt, $this->expiresAt, $this->absoluteExpiresAt, $state, $this->deviceLabel, $this->userAgentHash, $this->ipDimensionHash); }
    public function withLastSeen(\DateTimeImmutable $lastSeen, SessionPolicy $policy): self { $idle = $lastSeen->modify('+' . $policy->idleTtlSeconds() . ' seconds'); $expires = $idle < $this->absoluteExpiresAt ? $idle : $this->absoluteExpiresAt; return new self($this->sessionId, $this->tokenDigest, $this->principal, $this->createdAt, $lastSeen, $expires, $this->absoluteExpiresAt, $this->state, $this->deviceLabel, $this->userAgentHash, $this->ipDimensionHash); }
    public function toArray(): array { return ['session_id'=>(string)$this->sessionId,'token_digest'=>(string)$this->tokenDigest,'account_id'=>$this->principal->accountId(),'credential_version'=>$this->principal->credentialVersion(),'account_status'=>$this->principal->accountStatus(),'authenticated_at'=>$this->principal->authenticatedAt(),'created_at'=>$this->createdAt->format(DATE_ATOM),'last_seen_at'=>$this->lastSeenAt->format(DATE_ATOM),'expires_at'=>$this->expiresAt->format(DATE_ATOM),'absolute_expires_at'=>$this->absoluteExpiresAt->format(DATE_ATOM),'state'=>$this->state,'device_label'=>$this->deviceLabel,'user_agent_hash'=>$this->userAgentHash,'ip_dimension_hash'=>$this->ipDimensionHash]; }
}
