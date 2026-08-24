<?php
declare(strict_types=1);

namespace Identity\Contracts;

interface SessionStorePort
{
    public function create(SessionRecord $record, ?int $maximumActiveSessions = null): void;
    public function read(SessionTokenDigest $digest): ?SessionRecord;
    public function touch(SessionTokenDigest $digest, \DateTimeImmutable $at, SessionPolicy $policy): ?SessionRecord;
    public function rotate(SessionTokenDigest $oldDigest, SessionRecord $newRecord): bool;
    public function revoke(SessionTokenDigest $digest, string $reason, \DateTimeImmutable $at): bool;
    public function revokeAllForAccount(string $accountId, string $reason, \DateTimeImmutable $at): int;
    public function listActiveForAccount(string $accountId): array;
    public function enforceMaximumActiveSessions(string $accountId, int $maximum, \DateTimeImmutable $at): ?SessionRecord;
    /** @param callable(SessionRecord):SessionRecord $updater */
    public function compareAndUpdate(SessionTokenDigest $digest, callable $updater): ?SessionRecord;
    public function healthCheck(): SessionStoreHealthDecision;
}

/** Productive store result needed to disclose an atomic oldest-session supersede. */
interface AtomicSessionStorePort extends SessionStorePort
{
    public function createAuthoritatively(SessionRecord $record, int $maximumActiveSessions): ?SessionRecord;
    public function revokeOwnedAuthoritatively(SessionTokenDigest $actorDigest, SessionId $targetSessionId, string $reason, \DateTimeImmutable $at): ?SessionRecord;
}
