<?php
declare(strict_types=1);
namespace Platform\Repositories;

/** Canonical SQL writer participating in a caller-owned authority transaction. */
final class JoinedPdoCanonicalAuditTransactionAdapter implements CanonicalAuditTransactionPort
{
    private PdoCanonicalAuditTransactionAdapter $inner;
    public function __construct(private \PDO $pdo) { $this->inner = new PdoCanonicalAuditTransactionAdapter($pdo); }
    public function begin(): void { $this->assertActive(); }
    // The outer service alone commits publication and audit together.
    public function commit(): void { $this->assertActive(); }
    public function rollBack(): void { $this->inner->rollBack(); }
    private function assertActive(): void { if (!$this->pdo->inTransaction()) throw new \RuntimeException('audit_outer_transaction_required'); }
    public function ensureHead(string $streamKey,string $genesisHash,string $hashVersion): void { $this->assertActive();$this->inner->ensureHead($streamKey,$genesisHash,$hashVersion); }
    public function lockHead(string $streamKey): array { $this->assertActive();return $this->inner->lockHead($streamKey); }
    public function assertLegacyHeadMatchesLatest(string $streamKey,int $sequenceNumber,string $eventHash): void { $this->assertActive();$this->inner->assertLegacyHeadMatchesLatest($streamKey,$sequenceNumber,$eventHash); }
    public function insertEvent(array $row): int { $this->assertActive();return $this->inner->insertEvent($row); }
    public function updateHead(string $streamKey,int $expectedSequence,string $expectedHash,?string $expectedHashVersion,?string $expectedUpdatedAt,int $newSequence,string $newHash,string $newHashVersion,string $newUpdatedAt): int
    {
        $this->assertActive();
        return $this->inner->updateHead($streamKey,$expectedSequence,$expectedHash,$expectedHashVersion,$expectedUpdatedAt,$newSequence,$newHash,$newHashVersion,$newUpdatedAt);
    }
}
