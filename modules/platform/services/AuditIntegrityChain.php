<?php
declare(strict_types=1);

namespace Platform\Services;

use Platform\Contracts\AuditEventEnvelope;

/** Tamper-evident SHA-256 chaining, without external signing or key material. */
final class AuditIntegrityChain
{
    public function __construct(private readonly AuditEventCanonicalizer $canonicalizer = new AuditEventCanonicalizer()) {}

    public function calculateHash(AuditEventEnvelope $event, string $previousHash): string
    {
        self::assertHash($previousHash);
        return hash('sha256', $previousHash . $this->canonicalizer->canonicalizeEnvelope($event));
    }

    public function seal(AuditEventEnvelope $event, string $previousHash): AuditEventEnvelope
    {
        return $event->withIntegrity($previousHash, $this->calculateHash($event, $previousHash));
    }

    private static function assertHash(string $hash): void
    {
        if (preg_match('/^[a-f0-9]{64}$/i', $hash) !== 1) throw new \InvalidArgumentException('invalid_audit_chain_hash');
    }
}
