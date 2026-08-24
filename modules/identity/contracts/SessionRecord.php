<?php
declare(strict_types=1);

namespace Identity\Contracts;

final class SessionRecord
{
    public const SERIALIZATION_VERSION = 1;
    public const MAX_SERIALIZED_BYTES = 32768;
    private const SERIALIZED_KEYS = [
        'serialization_version',
        'session_id',
        'token_digest',
        'account_id',
        'credential_version',
        'account_status',
        'authenticated_at',
        'created_at',
        'last_seen_at',
        'expires_at',
        'absolute_expires_at',
        'state',
        'device_label',
        'user_agent_hash',
        'ip_dimension_hash',
    ];

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
        AccountStatus::assertValid($this->principal->accountStatus());
        if ($this->deviceLabel !== '' && (strlen($this->deviceLabel) > 80 || preg_match('/[\x00-\x1F\x7F]/', $this->deviceLabel) === 1)) throw new \InvalidArgumentException('invalid_device_label');
        if (
            $this->absoluteExpiresAt < $this->createdAt
            || $this->lastSeenAt < $this->createdAt
            || $this->expiresAt < $this->lastSeenAt
            || $this->expiresAt > $this->absoluteExpiresAt
            || $this->absoluteExpiresAt->getTimestamp() - $this->createdAt->getTimestamp() !== 43200
        ) throw new \InvalidArgumentException('invalid_session_times');
        foreach ([$this->userAgentHash, $this->ipDimensionHash] as $hash) {
            if ($hash !== '' && preg_match('/^[a-f0-9]{64}$/D', $hash) !== 1) throw new \InvalidArgumentException('invalid_session_dimension_hash');
        }
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
    public function toArray(): array { return ['serialization_version'=>self::SERIALIZATION_VERSION,'session_id'=>(string)$this->sessionId,'token_digest'=>(string)$this->tokenDigest,'account_id'=>$this->principal->accountId(),'credential_version'=>$this->principal->credentialVersion(),'account_status'=>$this->principal->accountStatus(),'authenticated_at'=>$this->principal->authenticatedAt(),'created_at'=>$this->createdAt->format(DATE_ATOM),'last_seen_at'=>$this->lastSeenAt->format(DATE_ATOM),'expires_at'=>$this->expiresAt->format(DATE_ATOM),'absolute_expires_at'=>$this->absoluteExpiresAt->format(DATE_ATOM),'state'=>$this->state,'device_label'=>$this->deviceLabel,'user_agent_hash'=>$this->userAgentHash,'ip_dimension_hash'=>$this->ipDimensionHash]; }

    public static function fromSerialized(string $value): self
    {
        if ($value === '' || strlen($value) > self::MAX_SERIALIZED_BYTES) throw new \InvalidArgumentException('invalid_session_payload_size');
        try { $row = json_decode($value, true, 32, JSON_THROW_ON_ERROR); } catch (\Throwable) { throw new \InvalidArgumentException('invalid_session_payload'); }
        if (!is_array($row) || array_is_list($row)) throw new \InvalidArgumentException('invalid_session_payload');
        $keys = array_keys($row);
        sort($keys, SORT_STRING);
        $expected = self::SERIALIZED_KEYS;
        sort($expected, SORT_STRING);
        if ($keys !== $expected) throw new \InvalidArgumentException('invalid_session_payload_schema');
        if (!is_int($row['serialization_version']) || $row['serialization_version'] !== self::SERIALIZATION_VERSION) throw new \InvalidArgumentException('unknown_session_serialization_version');
        foreach (['session_id','token_digest','account_id','account_status','authenticated_at','created_at','last_seen_at','expires_at','absolute_expires_at','state','device_label','user_agent_hash','ip_dimension_hash'] as $field) {
            if (!is_string($row[$field])) throw new \InvalidArgumentException('invalid_session_payload_type');
        }
        if (!is_int($row['credential_version'])) throw new \InvalidArgumentException('invalid_session_payload_type');
        try {
            return new self(
                new SessionId($row['session_id']),
                new SessionTokenDigest($row['token_digest']),
                new SessionPrincipal($row['account_id'], $row['credential_version'], $row['account_status'], $row['authenticated_at']),
                self::strictDate($row['created_at']),
                self::strictDate($row['last_seen_at']),
                self::strictDate($row['expires_at']),
                self::strictDate($row['absolute_expires_at']),
                $row['state'],
                $row['device_label'],
                $row['user_agent_hash'],
                $row['ip_dimension_hash']
            );
        } catch (\Throwable $exception) {
            throw new \InvalidArgumentException('invalid_session_payload', 0, $exception);
        }
    }

    private static function strictDate(string $value): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat(DATE_ATOM, $value);
        $errors = \DateTimeImmutable::getLastErrors();
        if (!$date || ($errors !== false && ($errors['warning_count'] !== 0 || $errors['error_count'] !== 0)) || $date->format(DATE_ATOM) !== $value) {
            throw new \InvalidArgumentException('invalid_session_timestamp');
        }
        return $date;
    }
}
