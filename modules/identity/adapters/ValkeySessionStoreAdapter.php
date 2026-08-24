<?php
declare(strict_types=1);

namespace Identity\Adapters;

use Identity\Contracts\AtomicSessionStorePort;
use Identity\Contracts\Clock;
use Identity\Contracts\SessionPolicy;
use Identity\Contracts\SessionRecord;
use Identity\Contracts\SessionState;
use Identity\Contracts\SessionStoreHealthDecision;
use Identity\Contracts\SessionStorePort;
use Identity\Contracts\SessionTokenDigest;
use Identity\Contracts\SystemClock;
use Identity\Contracts\TransactionalValkeySessionClientPort;
use Identity\Contracts\ValkeySessionClientPort;

final class ValkeySessionStoreAdapter implements SessionStorePort, AtomicSessionStorePort
{
    private const MAX_TTL = 43200;

    public function __construct(
        private ValkeySessionClientPort $client,
        private string $prefix,
        private Clock $clock = new SystemClock(),
        private int $maximumTransactionAttempts = 100,
        private int $retryWaitMicroseconds = 100000
    ) {
        $productive = preg_match('/^mxmed:(stg|prd):session:$/D', $this->prefix) === 1;
        $preview = $this->prefix === 'mxmed:gate4d:preview:session:';
        if ((!$productive && !$preview) || $this->maximumTransactionAttempts < 1 || $this->maximumTransactionAttempts > 100 || $this->retryWaitMicroseconds < 0 || $this->retryWaitMicroseconds > 100000) {
            throw new \InvalidArgumentException('invalid_session_store_configuration');
        }
        if ($productive && !$this->client instanceof TransactionalValkeySessionClientPort) throw new SessionStoreUnavailableException('transactional_session_store_required');
    }

    public function create(SessionRecord $record, ?int $maximumActiveSessions = null): void
    {
        $this->createAuthoritatively($record, $maximumActiveSessions ?? 5);
    }

    public function createAuthoritatively(SessionRecord $record, int $maximumActiveSessions): ?SessionRecord
    {
        if ($maximumActiveSessions !== 5) throw new \InvalidArgumentException('invalid_maximum_active_sessions');
        if (!$this->transactional()) return $this->previewCreate($record, $maximumActiveSessions);
        $newDigest = (string)$record->tokenDigest();
        return $this->atomicAccount($record->principal()->accountId(), [$newDigest], function (array $active) use ($record, $maximumActiveSessions): array {
            $sets = [];
            $superseded = null;
            usort($active, [self::class, 'compareOldest']);
            while (count($active) >= $maximumActiveSessions) {
                $oldest = array_shift($active);
                if (!$oldest instanceof SessionRecord) break;
                $terminal = $oldest->withState(SessionState::SUPERSEDED);
                $sets[] = $this->recordSet($terminal, true);
                $superseded ??= $terminal;
            }
            $active[] = $record;
            $sets[] = $this->recordSet($record, false);
            return ['sets' => array_merge($sets, $this->indexSet($record->principal()->accountId(), $active)), 'deletes' => [], 'result' => $superseded];
        });
    }

    public function read(SessionTokenDigest $digest): ?SessionRecord
    {
        $value = $this->client->get($this->key($digest));
        return $value === null ? null : SessionRecord::fromSerialized($value);
    }

    public function touch(SessionTokenDigest $digest, \DateTimeImmutable $at, SessionPolicy $policy): ?SessionRecord
    {
        if (!$this->transactional()) {
            $record = $this->read($digest);
            if ($record === null || $record->state() !== SessionState::ACTIVE) return null;
            $updated = $record->withLastSeen($at, $policy);
            $this->setRecord($updated, false);
            return $updated;
        }
        return $this->atomicRecord($digest, function (?SessionRecord $record) use ($at, $policy): array {
            if ($record === null || $record->state() !== SessionState::ACTIVE) return ['sets' => [], 'deletes' => [], 'result' => null];
            $updated = $record->withLastSeen($at, $policy);
            return ['sets' => [$this->recordSet($updated, false)], 'deletes' => [], 'result' => $updated];
        });
    }

    public function rotate(SessionTokenDigest $oldDigest, SessionRecord $newRecord): bool
    {
        if (!$this->transactional()) return $this->previewRotate($oldDigest, $newRecord);
        $oldValue = (string)$oldDigest;
        $newValue = (string)$newRecord->tokenDigest();
        return $this->atomicAccount($newRecord->principal()->accountId(), [$oldValue, $newValue], function (array $active, array $records) use ($oldValue, $newRecord): array {
            $old = $records[$oldValue] ?? null;
            if (!$old instanceof SessionRecord || $old->state() !== SessionState::ACTIVE || $old->principal()->accountId() !== $newRecord->principal()->accountId()) return ['sets' => [], 'deletes' => [], 'result' => false];
            $remaining = array_values(array_filter($active, static fn(SessionRecord $item): bool => (string)$item->tokenDigest() !== $oldValue));
            $remaining[] = $newRecord;
            return [
                'sets' => array_merge([$this->recordSet($old->withState(SessionState::SUPERSEDED), true), $this->recordSet($newRecord, false)], $this->indexSet($newRecord->principal()->accountId(), $remaining)),
                'deletes' => [],
                'result' => true,
            ];
        });
    }

    public function revoke(SessionTokenDigest $digest, string $reason, \DateTimeImmutable $at): bool
    {
        $observed = $this->read($digest);
        if ($observed === null) return false;
        $state = $this->terminalState($reason);
        if (!$this->transactional()) {
            $this->setRecord($observed->withState($state), true);
            $this->previewWriteIndex($observed->principal()->accountId(), array_values(array_filter($this->previewIndex($observed->principal()->accountId()), static fn(string $item): bool => $item !== (string)$digest)));
            return true;
        }
        $value = (string)$digest;
        return $this->atomicAccount($observed->principal()->accountId(), [$value], function (array $active, array $records) use ($value, $state): array {
            $record = $records[$value] ?? null;
            if (!$record instanceof SessionRecord) return ['sets' => [], 'deletes' => [], 'result' => false];
            $remaining = array_values(array_filter($active, static fn(SessionRecord $item): bool => (string)$item->tokenDigest() !== $value));
            return [
                'sets' => array_merge([$this->recordSet($record->withState($state), true)], $this->indexSet($record->principal()->accountId(), $remaining)),
                'deletes' => $remaining === [] ? [$this->indexKey($record->principal()->accountId())] : [],
                'result' => true,
            ];
        });
    }

    public function revokeOwnedAuthoritatively(SessionTokenDigest $actorDigest, \Identity\Contracts\SessionId $targetSessionId, string $reason, \DateTimeImmutable $at): ?SessionRecord
    {
        $actor = $this->read($actorDigest);
        if ($actor === null || $actor->state() !== SessionState::ACTIVE || !$this->transactional()) return null;
        $actorValue = (string)$actorDigest;
        $state = $this->terminalState($reason);
        return $this->atomicAccount($actor->principal()->accountId(), [$actorValue], function (array $active) use ($actorValue, $targetSessionId, $state): array {
            $actorStillActive = false; $target = null;
            foreach ($active as $record) {
                if ((string)$record->tokenDigest() === $actorValue) $actorStillActive = true;
                if ((string)$record->sessionId() === (string)$targetSessionId) $target = $record;
            }
            if (!$actorStillActive || !$target instanceof SessionRecord) return ['sets'=>[],'deletes'=>[],'result'=>null];
            $remaining = array_values(array_filter($active, static fn(SessionRecord $record): bool => (string)$record->sessionId() !== (string)$targetSessionId));
            return [
                'sets'=>array_merge([$this->recordSet($target->withState($state), true)], $this->indexSet($target->principal()->accountId(), $remaining)),
                'deletes'=>$remaining===[]?[$this->indexKey($target->principal()->accountId())]:[],
                'result'=>$target,
            ];
        });
    }

    public function revokeAllForAccount(string $accountId, string $reason, \DateTimeImmutable $at): int
    {
        $state = $this->terminalState($reason);
        if (!$this->transactional()) {
            $count = 0;
            foreach ($this->listActiveForAccount($accountId) as $record) { $this->setRecord($record->withState($state), true); $count++; }
            $this->previewWriteIndex($accountId, []);
            return $count;
        }
        return $this->atomicAccount($accountId, [], function (array $active) use ($accountId, $state): array {
            $sets = [];
            foreach ($active as $record) $sets[] = $this->recordSet($record->withState($state), true);
            return ['sets' => $sets, 'deletes' => [$this->indexKey($accountId)], 'result' => count($active)];
        });
    }

    public function listActiveForAccount(string $accountId): array
    {
        if (!$this->transactional()) {
            $result = [];
            foreach ($this->previewIndex($accountId) as $value) {
                $record = $this->read(new SessionTokenDigest($value));
                if ($this->isActive($record)) $result[] = $record;
            }
            return $result;
        }
        return $this->atomicAccount($accountId, [], static fn(array $active): array => ['sets' => [], 'deletes' => [], 'result' => $active]);
    }

    public function enforceMaximumActiveSessions(string $accountId, int $maximum, \DateTimeImmutable $at): ?SessionRecord
    {
        if ($maximum !== 5) throw new \InvalidArgumentException('invalid_maximum_active_sessions');
        if (!$this->transactional()) {
            $active = $this->listActiveForAccount($accountId);
            if (count($active) < $maximum) return null;
            usort($active, [self::class, 'compareOldest']);
            $oldest = $active[0]->withState(SessionState::SUPERSEDED);
            $this->setRecord($oldest, true);
            $this->previewWriteIndex($accountId, array_values(array_filter($this->previewIndex($accountId), static fn(string $value): bool => $value !== (string)$oldest->tokenDigest())));
            return $oldest;
        }
        return $this->atomicAccount($accountId, [], function (array $active) use ($accountId, $maximum): array {
            if (count($active) < $maximum) return ['sets' => [], 'deletes' => [], 'result' => null];
            usort($active, [self::class, 'compareOldest']);
            $oldest = array_shift($active)->withState(SessionState::SUPERSEDED);
            return ['sets' => array_merge([$this->recordSet($oldest, true)], $this->indexSet($accountId, $active)), 'deletes' => $active === [] ? [$this->indexKey($accountId)] : [], 'result' => $oldest];
        });
    }

    public function compareAndUpdate(SessionTokenDigest $digest, callable $updater): ?SessionRecord
    {
        if (!$this->transactional()) {
            $record = $this->read($digest);
            if ($record === null) return null;
            $updated = $updater($record);
            if (!$updated instanceof SessionRecord) throw new \InvalidArgumentException('invalid_session_compare_update');
            $this->setRecord($updated, $updated->state() !== SessionState::ACTIVE);
            return $updated;
        }
        return $this->atomicRecord($digest, function (?SessionRecord $record) use ($updater): array {
            if ($record === null) return ['sets' => [], 'deletes' => [], 'result' => null];
            $updated = $updater($record);
            if (!$updated instanceof SessionRecord) throw new \InvalidArgumentException('invalid_session_compare_update');
            return ['sets' => [$this->recordSet($updated, $updated->state() !== SessionState::ACTIVE)], 'deletes' => [], 'result' => $updated];
        });
    }

    public function healthCheck(): SessionStoreHealthDecision
    {
        try { return $this->client->ping() ? SessionStoreHealthDecision::healthyDecision() : SessionStoreHealthDecision::unavailable(); }
        catch (\Throwable) { return SessionStoreHealthDecision::unavailable(); }
    }

    private function atomicAccount(string $accountId, array $extraDigests, callable $planner): mixed
    {
        $client = $this->transactionalClient();
        $indexKey = $this->indexKey($accountId);
        for ($attempt = 1; $attempt <= $this->maximumTransactionAttempts; $attempt++) {
            try {
                if (!$client->watch([$indexKey])) throw new SessionStoreUnavailableException();
                $index = $this->decodeIndex($client->get($indexKey));
                $digests = array_values(array_unique(array_merge($index, array_map('strval', $extraDigests))));
                if ($digests !== [] && !$client->watch(array_map(fn(string $value): string => $this->key(new SessionTokenDigest($value)), $digests))) throw new SessionStoreUnavailableException();
                $records = [];
                foreach ($digests as $value) {
                    $serialized = $client->get($this->key(new SessionTokenDigest($value)));
                    if ($serialized !== null) $records[$value] = SessionRecord::fromSerialized($serialized);
                }
                $active = [];
                foreach ($index as $value) {
                    $record = $records[$value] ?? null;
                    if ($this->isActive($record) && $record->principal()->accountId() === $accountId) $active[] = $record;
                }
                $plan = $planner($active, $records);
                if (!is_array($plan) || !array_key_exists('result', $plan) || !$client->multi()) throw new SessionStoreUnavailableException();
                foreach (($plan['sets'] ?? []) as [$key, $value, $ttl]) if (!$client->set($key, $value, $ttl)) throw new SessionStoreUnavailableException();
                foreach (($plan['deletes'] ?? []) as $key) $client->delete($key);
                $result = $client->exec();
                if ($result !== false) { foreach ($result as $commandResult) if ($commandResult === false) throw new SessionStoreUnavailableException(); return $plan['result']; }
            } catch (\InvalidArgumentException $exception) { $client->unwatch(); throw $exception; }
            catch (\Throwable $exception) { $client->unwatch(); if ($attempt >= $this->maximumTransactionAttempts) throw new SessionStoreUnavailableException('session_transaction_exhausted', 0, $exception); }
            if ($attempt < $this->maximumTransactionAttempts && $this->retryWaitMicroseconds > 0) usleep($this->retryWaitMicroseconds);
        }
        throw new SessionStoreUnavailableException('session_transaction_exhausted');
    }

    private function atomicRecord(SessionTokenDigest $digest, callable $planner): mixed
    {
        $client = $this->transactionalClient();
        $key = $this->key($digest);
        for ($attempt = 1; $attempt <= $this->maximumTransactionAttempts; $attempt++) {
            try {
                if (!$client->watch([$key])) throw new SessionStoreUnavailableException();
                $serialized = $client->get($key);
                $record = $serialized === null ? null : SessionRecord::fromSerialized($serialized);
                $plan = $planner($record);
                if (!is_array($plan) || !array_key_exists('result', $plan) || !$client->multi()) throw new SessionStoreUnavailableException();
                foreach (($plan['sets'] ?? []) as [$setKey, $value, $ttl]) if (!$client->set($setKey, $value, $ttl)) throw new SessionStoreUnavailableException();
                foreach (($plan['deletes'] ?? []) as $deleteKey) $client->delete($deleteKey);
                $result = $client->exec();
                if ($result !== false) { foreach ($result as $commandResult) if ($commandResult === false) throw new SessionStoreUnavailableException(); return $plan['result']; }
            } catch (\InvalidArgumentException $exception) { $client->unwatch(); throw $exception; }
            catch (\Throwable $exception) { $client->unwatch(); if ($attempt >= $this->maximumTransactionAttempts) throw new SessionStoreUnavailableException('session_transaction_exhausted', 0, $exception); }
            if ($attempt < $this->maximumTransactionAttempts && $this->retryWaitMicroseconds > 0) usleep($this->retryWaitMicroseconds);
        }
        throw new SessionStoreUnavailableException('session_transaction_exhausted');
    }

    private function recordSet(SessionRecord $record, bool $terminal): array { return [$this->key($record->tokenDigest()), $this->encode($record), $this->ttlFor($record, $terminal)]; }
    private function indexSet(string $accountId, array $active): array
    {
        if ($active === []) return [];
        $digests = []; $latest = 0;
        foreach ($active as $record) { if (!$record instanceof SessionRecord || !$this->isActive($record) || $record->principal()->accountId() !== $accountId) continue; $digests[] = (string)$record->tokenDigest(); $latest = max($latest, $record->absoluteExpiresAt()->getTimestamp()); }
        if ($digests === []) return [];
        sort($digests, SORT_STRING);
        return [[$this->indexKey($accountId), json_encode($digests, JSON_THROW_ON_ERROR), min(self::MAX_TTL, max(1, $latest - $this->clock->now()->getTimestamp()))]];
    }
    private function ttlFor(SessionRecord $record, bool $terminal): int { $now = $this->clock->now()->getTimestamp(); $limit = $terminal ? $record->absoluteExpiresAt()->getTimestamp() : min($record->expiresAt()->getTimestamp(), $record->absoluteExpiresAt()->getTimestamp()); return min(self::MAX_TTL, max(1, $limit - $now)); }
    private function isActive(?SessionRecord $record): bool { if (!$record instanceof SessionRecord || $record->state() !== SessionState::ACTIVE) return false; $now = $this->clock->now(); return $now < $record->expiresAt() && $now < $record->absoluteExpiresAt(); }
    private static function compareOldest(SessionRecord $left, SessionRecord $right): int { return ($left->lastSeenAt() <=> $right->lastSeenAt()) ?: ($left->createdAt() <=> $right->createdAt()) ?: strcmp((string)$left->sessionId(), (string)$right->sessionId()); }
    private function terminalState(string $reason): string { return match ($reason) { 'logged_out' => SessionState::LOGGED_OUT, SessionState::IDLE_EXPIRED => SessionState::IDLE_EXPIRED, SessionState::ABSOLUTE_EXPIRED => SessionState::ABSOLUTE_EXPIRED, 'credential_version_mismatch', 'password_changed' => SessionState::CREDENTIAL_CHANGED, 'account_blocked', 'account_locked' => SessionState::ACCOUNT_LOCKED, 'account_disabled' => SessionState::ACCOUNT_DISABLED, default => SessionState::REVOKED }; }
    private function key(SessionTokenDigest $digest): string { return $this->prefix . (string)$digest; }
    private function indexKey(string $accountId): string { if ($accountId === '') throw new \InvalidArgumentException('account_id_required'); return $this->prefix . 'account-index:' . hash('sha256', $accountId); }
    private function encode(SessionRecord $record): string { $value = json_encode($record->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES); if (strlen($value) > SessionRecord::MAX_SERIALIZED_BYTES) throw new \InvalidArgumentException('session_payload_too_large'); return $value; }
    private function transactional(): bool { return $this->client instanceof TransactionalValkeySessionClientPort; }
    private function transactionalClient(): TransactionalValkeySessionClientPort { if (!$this->client instanceof TransactionalValkeySessionClientPort) throw new SessionStoreUnavailableException('transactional_session_store_required'); return $this->client; }
    private function decodeIndex(?string $value): array { if ($value === null || $value === '') return []; try { $decoded = json_decode($value, true, 16, JSON_THROW_ON_ERROR); } catch (\Throwable) { throw new \InvalidArgumentException('invalid_session_index'); } if (!is_array($decoded) || !array_is_list($decoded) || count($decoded) > 64) throw new \InvalidArgumentException('invalid_session_index'); $result = []; foreach ($decoded as $digest) { if (!is_string($digest) || preg_match('/^[a-f0-9]{64}$/D', $digest) !== 1 || isset($result[$digest])) throw new \InvalidArgumentException('invalid_session_index'); $result[$digest] = $digest; } return array_values($result); }
    private function setRecord(SessionRecord $record, bool $terminal): void { [$key, $value, $ttl] = $this->recordSet($record, $terminal); if (!$this->client->set($key, $value, $ttl)) throw new SessionStoreUnavailableException(); }
    private function previewIndex(string $accountId): array { return $this->decodeIndex($this->client->get($this->indexKey($accountId))); }
    private function previewWriteIndex(string $accountId, array $digests): void { $key = $this->indexKey($accountId); if ($digests === []) { $this->client->delete($key); return; } if (!$this->client->set($key, json_encode(array_values(array_unique($digests)), JSON_THROW_ON_ERROR), self::MAX_TTL)) throw new SessionStoreUnavailableException(); }
    private function previewCreate(SessionRecord $record, int $maximum): ?SessionRecord { $superseded = $this->enforceMaximumActiveSessions($record->principal()->accountId(), $maximum, $record->createdAt()); $this->setRecord($record, false); $this->previewWriteIndex($record->principal()->accountId(), array_merge($this->previewIndex($record->principal()->accountId()), [(string)$record->tokenDigest()])); return $superseded; }
    private function previewRotate(SessionTokenDigest $oldDigest, SessionRecord $newRecord): bool { $old = $this->read($oldDigest); if ($old === null || $old->state() !== SessionState::ACTIVE) return false; $this->setRecord($old->withState(SessionState::SUPERSEDED), true); $this->setRecord($newRecord, false); $index = array_values(array_filter($this->previewIndex($newRecord->principal()->accountId()), static fn(string $value): bool => $value !== (string)$oldDigest)); $index[] = (string)$newRecord->tokenDigest(); $this->previewWriteIndex($newRecord->principal()->accountId(), $index); return true; }
}
