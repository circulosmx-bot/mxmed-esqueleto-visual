<?php
declare(strict_types=1);

namespace Identity\Services;

use Identity\Adapters\InMemorySessionStoreAdapter;
use Identity\Adapters\RejectingSessionStoreAdapter;
use Identity\Adapters\ValkeySessionStoreAdapter;
use Identity\Adapters\SessionStoreUnavailableException;
use Identity\Contracts\SessionStorePort;
use Identity\Contracts\Clock;
use Identity\Contracts\SystemClock;
use Identity\Contracts\TransactionalValkeySessionClientPort;
use Identity\Contracts\ValkeySessionClientPort;

final class SessionStoreFactory
{
    public static function create(string $environment, array $config, ?ValkeySessionClientPort $client = null, ?Clock $clock = null): SessionStorePort
    {
        $environment = strtolower(trim($environment));
        $driver = strtolower(trim((string)($config['driver'] ?? '')));
        if (in_array($environment, ['production', 'staging'], true)) {
            $expectedPrefix = $environment === 'production' ? 'mxmed:prd:session:' : 'mxmed:stg:session:';
            if (
                $driver !== 'valkey'
                || !$client instanceof TransactionalValkeySessionClientPort
                || (string)($config['prefix'] ?? '') !== $expectedPrefix
                || (int)($config['idle_ttl'] ?? 0) !== 3600
                || (int)($config['absolute_ttl'] ?? 0) !== 43200
                || (int)($config['touch_interval'] ?? 0) !== 300
                || (int)($config['maximum_active'] ?? 0) !== 5
                || ($config['tls_required'] ?? null) !== true
                || ($config['lock_enabled'] ?? null) !== true
                || (int)($config['lock_timeout_seconds'] ?? 0) !== 10
                || (int)($config['lock_wait_microseconds'] ?? 0) !== 100000
            ) throw new SessionStoreUnavailableException();
            return new ValkeySessionStoreAdapter($client, $expectedPrefix, $clock ?? new SystemClock(), 100, 100000);
        }
        if ($environment === 'test' && $driver === 'in_memory') return new InMemorySessionStoreAdapter();
        if ($environment === 'local' && $driver === 'in_memory' && ($config['explicit_dev_flag'] ?? false) === true) return new InMemorySessionStoreAdapter();
        if ($environment === 'local' && $driver === 'valkey' && $client instanceof ValkeySessionClientPort && ($config['explicit_preview_flag'] ?? false) === true) {
            return new ValkeySessionStoreAdapter($client, (string)($config['prefix'] ?? ''), $clock ?? new SystemClock());
        }
        if ($driver === 'rejecting') return new RejectingSessionStoreAdapter();
        throw new SessionStoreUnavailableException();
    }
}
