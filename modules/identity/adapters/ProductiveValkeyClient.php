<?php
declare(strict_types=1);

namespace Identity\Adapters;

use Identity\Contracts\TransactionalValkeySessionClientPort;
use Identity\Contracts\ValkeySessionClientPort;

/** Productive phpredis boundary. Construction is connection-free; commands connect lazily. */
final class ProductiveValkeyClient implements ValkeySessionClientPort, TransactionalValkeySessionClientPort
{
    private ?\Redis $redis = null;
    private bool $inTransaction = false;

    public function __construct(
        private string $endpoint,
        private int $port,
        private string $username,
        private string $password,
        private float $connectTimeoutSeconds = 2.0,
        private float $readTimeoutSeconds = 2.0,
        private bool $tlsRequired = true
    ) {
        if (
            preg_match('/^[A-Za-z0-9.-]{1,253}$/D', $this->endpoint) !== 1
            || filter_var($this->endpoint, FILTER_VALIDATE_IP) !== false
            || $this->port !== 6379
            || preg_match('/^[A-Za-z0-9_.-]{1,128}$/D', $this->username) !== 1
            || $this->password === ''
            || $this->connectTimeoutSeconds <= 0.0
            || $this->connectTimeoutSeconds > 5.0
            || $this->readTimeoutSeconds <= 0.0
            || $this->readTimeoutSeconds > 5.0
            || !$this->tlsRequired
        ) throw new \InvalidArgumentException('productive_valkey_configuration_unavailable');
    }

    public function ping(): bool
    {
        try { $pong = $this->connection()->ping(); return $pong === true || $pong === '+PONG'; }
        catch (\Throwable $exception) { $this->disconnect(); throw new SessionStoreUnavailableException('session_store_unavailable', 0, $exception); }
    }

    public function get(string $key): ?string
    {
        $this->assertKey($key);
        try {
            $value = $this->connection()->get($key);
            if ($value === false) return null;
            if (!is_string($value)) throw new \RuntimeException('unexpected_valkey_response');
            return $value;
        } catch (\Throwable $exception) { $this->disconnect(); throw new SessionStoreUnavailableException('session_store_unavailable', 0, $exception); }
    }

    public function set(string $key, string $value, int $ttlSeconds): bool
    {
        $this->assertKey($key);
        if ($ttlSeconds < 1 || $ttlSeconds > 43200) throw new \InvalidArgumentException('invalid_session_ttl');
        try {
            $result = $this->connection()->set($key, $value, ['ex' => $ttlSeconds]);
            return $this->inTransaction ? $result instanceof \Redis : $result === true;
        } catch (\Throwable $exception) { $this->disconnect(); throw new SessionStoreUnavailableException('session_store_unavailable', 0, $exception); }
    }

    public function delete(string $key): bool
    {
        $this->assertKey($key);
        try {
            $result = $this->connection()->del($key);
            return $this->inTransaction ? $result instanceof \Redis : is_int($result);
        } catch (\Throwable $exception) { $this->disconnect(); throw new SessionStoreUnavailableException('session_store_unavailable', 0, $exception); }
    }

    public function watch(array $keys): bool
    {
        if ($keys === []) throw new \InvalidArgumentException('session_watch_keys_required');
        foreach ($keys as $key) $this->assertKey((string)$key);
        try { return $this->connection()->watch(...array_values(array_unique($keys))); }
        catch (\Throwable $exception) { $this->disconnect(); throw new SessionStoreUnavailableException('session_store_unavailable', 0, $exception); }
    }

    public function unwatch(): void
    {
        try { if ($this->redis !== null) $this->redis->unwatch(); $this->inTransaction = false; }
        catch (\Throwable) { $this->disconnect(); }
    }

    public function multi(): bool
    {
        try {
            $result = $this->connection()->multi(\Redis::MULTI);
            $this->inTransaction = $result instanceof \Redis;
            return $this->inTransaction;
        } catch (\Throwable $exception) { $this->disconnect(); throw new SessionStoreUnavailableException('session_store_unavailable', 0, $exception); }
    }

    public function exec(): array|false
    {
        try {
            $result = $this->connection()->exec();
            $this->inTransaction = false;
            if ($result === false) return false;
            if (!is_array($result)) throw new \RuntimeException('unexpected_valkey_response');
            return array_values($result);
        } catch (\Throwable $exception) {
            $this->inTransaction = false;
            $this->disconnect();
            throw new SessionStoreUnavailableException('session_store_unavailable', 0, $exception);
        }
    }

    public function ttl(string $key): int
    {
        $this->assertKey($key);
        try { return (int)$this->connection()->ttl($key); }
        catch (\Throwable $exception) { $this->disconnect(); throw new SessionStoreUnavailableException('session_store_unavailable', 0, $exception); }
    }

    private function connection(): \Redis
    {
        if ($this->redis instanceof \Redis) return $this->redis;
        if (!class_exists(\Redis::class)) throw new SessionStoreUnavailableException('session_store_unavailable');
        $redis = new \Redis();
        $context = [
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'peer_name' => $this->endpoint,
                'SNI_enabled' => true,
            ],
        ];
        try {
            $connected = $redis->connect(
                'tls://' . $this->endpoint,
                $this->port,
                $this->connectTimeoutSeconds,
                null,
                0,
                $this->readTimeoutSeconds,
                $context
            );
            if (!$connected || !$redis->auth([$this->username, $this->password])) throw new \RuntimeException('productive_valkey_connection_failed');
            $this->redis = $redis;
            return $redis;
        } catch (\Throwable $exception) {
            try { $redis->close(); } catch (\Throwable) {}
            throw new SessionStoreUnavailableException('session_store_unavailable', 0, $exception);
        }
    }

    private function assertKey(string $key): void
    {
        if (preg_match('/^mxmed:(stg|prd):session:[A-Za-z0-9:_-]{32,160}$/D', $key) !== 1) throw new \InvalidArgumentException('invalid_session_key');
    }

    private function disconnect(): void
    {
        if ($this->redis !== null) {
            try { $this->redis->close(); } catch (\Throwable) {}
        }
        $this->redis = null;
        $this->inTransaction = false;
    }
}
