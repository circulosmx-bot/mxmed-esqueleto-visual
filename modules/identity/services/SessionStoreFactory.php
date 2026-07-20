<?php
declare(strict_types=1);

namespace Identity\Services;

use Identity\Adapters\InMemorySessionStoreAdapter;
use Identity\Adapters\RejectingSessionStoreAdapter;
use Identity\Adapters\ValkeySessionStoreAdapter;
use Identity\Adapters\SessionStoreUnavailableException;
use Identity\Contracts\SessionStorePort;
use Identity\Contracts\ValkeySessionClientPort;

final class SessionStoreFactory
{
    public static function create(string $environment, array $config, ?ValkeySessionClientPort $client = null): SessionStorePort
    {
        $environment = strtolower(trim($environment));
        $driver = strtolower(trim((string)($config['driver'] ?? '')));
        if (in_array($environment, ['production', 'staging'], true)) {
            if ($driver !== 'valkey' || !$client instanceof ValkeySessionClientPort) throw new SessionStoreUnavailableException();
            return new ValkeySessionStoreAdapter($client, (string)($config['prefix'] ?? ''));
        }
        if ($environment === 'test' && $driver === 'in_memory') return new InMemorySessionStoreAdapter();
        if ($environment === 'local' && $driver === 'in_memory' && ($config['explicit_dev_flag'] ?? false) === true) return new InMemorySessionStoreAdapter();
        if ($environment === 'local' && $driver === 'valkey' && $client instanceof ValkeySessionClientPort && ($config['explicit_preview_flag'] ?? false) === true) {
            return new ValkeySessionStoreAdapter($client, (string)($config['prefix'] ?? ''));
        }
        if ($driver === 'rejecting') return new RejectingSessionStoreAdapter();
        throw new SessionStoreUnavailableException();
    }
}
