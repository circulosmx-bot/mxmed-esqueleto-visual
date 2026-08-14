<?php
declare(strict_types=1);
namespace Platform\Repositories;
interface CanonicalAuditTransactionPort
{
    public function begin(): void;
    public function ensureHead(string $streamKey,string $genesisHash,string $hashVersion): void;
    /** @return array{last_sequence_number:int,last_event_hash:string,hash_version:?string,updated_at:?string} */
    public function lockHead(string $streamKey): array;
    public function assertLegacyHeadMatchesLatest(string $streamKey,int $sequenceNumber,string $eventHash): void;
    public function insertEvent(array $row): int;
    public function updateHead(string $streamKey,int $expectedSequence,string $expectedHash,?string $expectedHashVersion,?string $expectedUpdatedAt,int $newSequence,string $newHash,string $newHashVersion,string $newUpdatedAt): int;
    public function commit(): void;
    public function rollBack(): void;
}
