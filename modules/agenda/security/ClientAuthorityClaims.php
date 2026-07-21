<?php
declare(strict_types=1);

namespace Agenda\Security;

/**
 * Untrusted request claims are retained only as mismatch diagnostics. Values are
 * never copied into an authority or public response.
 */
final readonly class ClientAuthorityClaims
{
    public function __construct(
        private ?string $actorRole = null,
        private ?string $actorId = null,
        private ?string $operatorId = null,
        private ?string $doctorId = null,
        private ?string $channelOrigin = null,
        private ?string $source = 'client_claim'
    ) {
        if ($this->source !== 'client_claim') throw new \InvalidArgumentException('client_claim_source_invalid');
        foreach ([$this->actorRole, $this->actorId, $this->operatorId, $this->doctorId, $this->channelOrigin] as $value) {
            if ($value !== null && (trim($value) === '' || strlen($value) > 128 || preg_match('/[\x00-\x1F\x7F]/', $value) === 1)) {
                throw new \InvalidArgumentException('client_claim_invalid');
            }
        }
    }

    public static function none(): self { return new self(); }
    public function trusted(): bool { return false; }
    public function actorRole(): ?string { return $this->actorRole; }
    public function actorId(): ?string { return $this->actorId; }
    public function operatorId(): ?string { return $this->operatorId; }
    public function doctorId(): ?string { return $this->doctorId; }
    public function channelOrigin(): ?string { return $this->channelOrigin; }

    /** @return array{role_mismatch:bool,actor_mismatch:bool,profile_mismatch:bool,operator_mismatch:bool,attempt_detected:bool} */
    public function mismatchAgainst(string $role, string $accountId, string $profileId, ?OperatorBinding $binding): array
    {
        $roleMismatch = $this->actorRole !== null && $this->actorRole !== $role;
        $actorMismatch = $this->actorId !== null && $this->actorId !== $accountId;
        $profileMismatch = $this->doctorId !== null && $this->doctorId !== $profileId;
        $operatorMismatch = $this->operatorId !== null && ($binding === null || $this->operatorId !== $binding->operatorId());
        return [
            'role_mismatch' => $roleMismatch,
            'actor_mismatch' => $actorMismatch,
            'profile_mismatch' => $profileMismatch,
            'operator_mismatch' => $operatorMismatch,
            'attempt_detected' => $roleMismatch || $actorMismatch || $profileMismatch || $operatorMismatch,
        ];
    }

    /** @return array{trusted:false,role_mismatch:bool,actor_mismatch:bool,profile_mismatch:bool,operator_mismatch:bool,attempt_detected:bool} */
    public function diagnostic(array $mismatch): array
    {
        return [
            'trusted' => false,
            'role_mismatch' => (bool)($mismatch['role_mismatch'] ?? false),
            'actor_mismatch' => (bool)($mismatch['actor_mismatch'] ?? false),
            'profile_mismatch' => (bool)($mismatch['profile_mismatch'] ?? false),
            'operator_mismatch' => (bool)($mismatch['operator_mismatch'] ?? false),
            'attempt_detected' => (bool)($mismatch['attempt_detected'] ?? false),
        ];
    }
}
