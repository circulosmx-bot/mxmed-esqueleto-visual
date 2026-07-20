<?php
declare(strict_types=1);

namespace Identity\Adapters;

use Identity\Contracts\SessionRecord;
use Identity\Contracts\SessionStoreHealthDecision;
use Identity\Contracts\SessionStorePort;
use Identity\Contracts\SessionTokenDigest;
use Identity\Contracts\SessionPolicy;

final class RejectingSessionStoreAdapter implements SessionStorePort
{
    private function fail(): never { throw new SessionStoreUnavailableException(); }
    public function create(SessionRecord $record, ?int $maximumActiveSessions = null): void { $this->fail(); }
    public function read(SessionTokenDigest $digest): ?SessionRecord { $this->fail(); }
    public function touch(SessionTokenDigest $digest, \DateTimeImmutable $at, SessionPolicy $policy): ?SessionRecord { $this->fail(); }
    public function rotate(SessionTokenDigest $oldDigest, SessionRecord $newRecord): bool { $this->fail(); }
    public function revoke(SessionTokenDigest $digest, string $reason, \DateTimeImmutable $at): bool { $this->fail(); }
    public function revokeAllForAccount(string $accountId, string $reason, \DateTimeImmutable $at): int { $this->fail(); }
    public function listActiveForAccount(string $accountId): array { $this->fail(); }
    public function enforceMaximumActiveSessions(string $accountId, int $maximum, \DateTimeImmutable $at): ?SessionRecord { $this->fail(); }
    public function compareAndUpdate(SessionTokenDigest $digest, callable $updater): ?SessionRecord { $this->fail(); }
    public function healthCheck(): SessionStoreHealthDecision { return SessionStoreHealthDecision::unavailable(); }
}
