<?php
declare(strict_types=1);

namespace Identity\Adapters;

use Identity\Contracts\SessionPolicy;
use Identity\Contracts\SessionRecord;
use Identity\Contracts\SessionState;
use Identity\Contracts\SessionStoreHealthDecision;
use Identity\Contracts\SessionStorePort;
use Identity\Contracts\SessionTokenDigest;
use Identity\Contracts\ValkeySessionClientPort;

final class ValkeySessionStoreAdapter implements SessionStorePort
{
    public function __construct(private ValkeySessionClientPort $client, private string $prefix)
    {
        if (preg_match('/^mxmed:(stg|prd):session:$/', $this->prefix) !== 1) throw new \InvalidArgumentException('invalid_session_prefix');
    }
    private function key(SessionTokenDigest $digest): string { return $this->prefix . (string)$digest; }
    private function indexKey(string $accountId): string { return $this->prefix . 'account-index:' . hash('sha256', $accountId); }
    private function index(string $accountId): array { $value = $this->client->get($this->indexKey($accountId)); if ($value === null || $value === '') return []; $decoded = json_decode($value, true); return is_array($decoded) ? array_values(array_filter(array_map('strval', $decoded))) : []; }
    private function writeIndex(string $accountId, array $digests): void { if (!$this->client->set($this->indexKey($accountId), json_encode(array_values(array_unique($digests)), JSON_THROW_ON_ERROR), 43200)) throw new SessionStoreUnavailableException(); }
    private function encode(SessionRecord $record): string { $value = json_encode($record->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES); if (strlen($value) > 32768) throw new \InvalidArgumentException('session_payload_too_large'); return $value; }
    private function decode(string $value): SessionRecord
    {
        $row = json_decode($value, true, 32, JSON_THROW_ON_ERROR);
        return new SessionRecord(new \Identity\Contracts\SessionId((string)$row['session_id']), new SessionTokenDigest((string)$row['token_digest']), new \Identity\Contracts\SessionPrincipal((string)$row['account_id'], (int)$row['credential_version'], (string)$row['account_status'], (string)$row['authenticated_at']), new \DateTimeImmutable((string)$row['created_at']), new \DateTimeImmutable((string)$row['last_seen_at']), new \DateTimeImmutable((string)$row['expires_at']), new \DateTimeImmutable((string)$row['absolute_expires_at']), (string)$row['state'], (string)($row['device_label'] ?? ''), (string)($row['user_agent_hash'] ?? ''), (string)($row['ip_dimension_hash'] ?? ''));
    }
    public function create(SessionRecord $record, ?int $maximumActiveSessions = null): void
    {
        $accountId = $record->principal()->accountId(); $digests = $this->index($accountId);
        if ($maximumActiveSessions !== null) $this->enforceMaximumActiveSessions($accountId, $maximumActiveSessions, $record->createdAt());
        if (!$this->client->set($this->key($record->tokenDigest()), $this->encode($record), max(1, $record->expiresAt()->getTimestamp() - time()))) throw new SessionStoreUnavailableException();
        $this->writeIndex($accountId, array_merge($digests, [(string)$record->tokenDigest()]));
    }
    public function read(SessionTokenDigest $digest): ?SessionRecord { $value = $this->client->get($this->key($digest)); return $value === null ? null : $this->decode($value); }
    public function touch(SessionTokenDigest $digest, \DateTimeImmutable $at, SessionPolicy $policy): ?SessionRecord { $record = $this->read($digest); if ($record === null) return null; $updated = $record->withLastSeen($at, $policy); $this->create($updated); return $updated; }
    public function rotate(SessionTokenDigest $oldDigest, SessionRecord $newRecord): bool
    {
        $old = $this->read($oldDigest); if ($old === null || $old->state() !== SessionState::ACTIVE) return false;
        $superseded = $old->withState(SessionState::SUPERSEDED);
        if (!$this->client->set($this->key($oldDigest), $this->encode($superseded), max(1, $old->absoluteExpiresAt()->getTimestamp() - time()))) throw new SessionStoreUnavailableException();
        try { $this->create($newRecord); return true; } catch (\Throwable) { $this->client->set($this->key($oldDigest), $this->encode($old), max(1, $old->absoluteExpiresAt()->getTimestamp() - time())); return false; }
    }
    public function revoke(SessionTokenDigest $digest, string $reason, \DateTimeImmutable $at): bool { return $this->client->delete($this->key($digest)); }
    public function revokeAllForAccount(string $accountId, string $reason, \DateTimeImmutable $at): int
    {
        $count = 0; foreach ($this->index($accountId) as $digestValue) { $digest = new SessionTokenDigest($digestValue); $record = $this->read($digest); if ($record !== null && $record->state() === SessionState::ACTIVE) { $this->client->set($this->key($digest), $this->encode($record->withState(SessionState::REVOKED)), 43200); $count++; } } return $count;
    }
    public function listActiveForAccount(string $accountId): array
    {
        $result = []; foreach ($this->index($accountId) as $digestValue) { $record = $this->read(new SessionTokenDigest($digestValue)); if ($record !== null && $record->state() === SessionState::ACTIVE) $result[] = $record; } return $result;
    }
    public function enforceMaximumActiveSessions(string $accountId, int $maximum, \DateTimeImmutable $at): ?SessionRecord
    {
        $active = $this->listActiveForAccount($accountId); if (count($active) < $maximum) return null; usort($active, static fn(SessionRecord $a, SessionRecord $b): int => $a->lastSeenAt() <=> $b->lastSeenAt()); $oldest = $active[0]; $key = $this->key($oldest->tokenDigest()); $this->client->set($key, $this->encode($oldest->withState(SessionState::SUPERSEDED)), 43200); return $oldest->withState(SessionState::SUPERSEDED);
    }
    public function compareAndUpdate(SessionTokenDigest $digest, callable $updater): ?SessionRecord
    {
        $record = $this->read($digest); if ($record === null) return null;
        $updated = $updater($record); if (!$updated instanceof SessionRecord) throw new \InvalidArgumentException('invalid_session_compare_update');
        $this->create($updated); return $updated;
    }
    public function healthCheck(): SessionStoreHealthDecision { try { return $this->client->ping() ? SessionStoreHealthDecision::healthyDecision() : SessionStoreHealthDecision::unavailable(); } catch (\Throwable) { return SessionStoreHealthDecision::unavailable(); } }
}
