<?php
declare(strict_types=1);

namespace Identity\Adapters;

use Identity\Contracts\SessionRecord;
use Identity\Contracts\SessionState;
use Identity\Contracts\SessionStoreHealthDecision;
use Identity\Contracts\SessionStorePort;
use Identity\Contracts\SessionTokenDigest;
use Identity\Contracts\SessionPolicy;

final class InMemorySessionStoreAdapter implements SessionStorePort
{
    /** @var array<string, SessionRecord> */
    private array $records = [];
    public function create(SessionRecord $record, ?int $maximumActiveSessions = null): void
    {
        if ($maximumActiveSessions !== null) $this->enforceMaximumActiveSessions($record->principal()->accountId(), $maximumActiveSessions, $record->createdAt());
        $this->records[(string)$record->tokenDigest()] = $record;
    }
    public function read(SessionTokenDigest $digest): ?SessionRecord { return $this->records[(string)$digest] ?? null; }
    public function touch(SessionTokenDigest $digest, \DateTimeImmutable $at, SessionPolicy $policy): ?SessionRecord
    {
        $record = $this->read($digest);
        if ($record === null || $record->state() !== SessionState::ACTIVE) return null;
        $updated = $record->withLastSeen($at, $policy);
        $this->records[(string)$digest] = $updated;
        return $updated;
    }
    public function rotate(SessionTokenDigest $oldDigest, SessionRecord $newRecord): bool
    {
        $old = $this->read($oldDigest);
        if ($old === null || $old->state() !== SessionState::ACTIVE) return false;
        $this->records[(string)$oldDigest] = $old->withState(SessionState::SUPERSEDED);
        $this->records[(string)$newRecord->tokenDigest()] = $newRecord;
        return true;
    }
    public function revoke(SessionTokenDigest $digest, string $reason, \DateTimeImmutable $at): bool
    {
        $record = $this->read($digest);
        if ($record === null) return false;
        $state = $reason === 'logged_out' ? SessionState::LOGGED_OUT : SessionState::REVOKED;
        $this->records[(string)$digest] = $record->withState($state);
        return true;
    }
    public function revokeAllForAccount(string $accountId, string $reason, \DateTimeImmutable $at): int
    {
        $count = 0;
        foreach ($this->records as $key => $record) {
            if ($record->principal()->accountId() === $accountId && $record->state() === SessionState::ACTIVE) {
                $this->records[$key] = $record->withState(SessionState::REVOKED);
                $count++;
            }
        }
        return $count;
    }
    public function listActiveForAccount(string $accountId): array
    {
        return array_values(array_filter($this->records, static fn(SessionRecord $r): bool => $r->principal()->accountId() === $accountId && $r->state() === SessionState::ACTIVE));
    }
    public function enforceMaximumActiveSessions(string $accountId, int $maximum, \DateTimeImmutable $at): ?SessionRecord
    {
        $active = $this->listActiveForAccount($accountId);
        if (count($active) < $maximum) return null;
        usort($active, static fn(SessionRecord $a, SessionRecord $b): int => $a->lastSeenAt() <=> $b->lastSeenAt());
        $oldest = $active[0];
        $key = (string)$oldest->tokenDigest();
        $this->records[$key] = $oldest->withState(SessionState::SUPERSEDED);
        return $this->records[$key];
    }
    public function compareAndUpdate(SessionTokenDigest $digest, callable $updater): ?SessionRecord
    {
        $current = $this->read($digest);
        if ($current === null) return null;
        $updated = $updater($current);
        if (!$updated instanceof SessionRecord) throw new \InvalidArgumentException('invalid_session_compare_update');
        $this->records[(string)$digest] = $updated;
        return $updated;
    }
    public function healthCheck(): SessionStoreHealthDecision { return SessionStoreHealthDecision::healthyDecision(); }
}
