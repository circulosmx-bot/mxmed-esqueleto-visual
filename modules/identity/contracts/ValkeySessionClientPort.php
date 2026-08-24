<?php
declare(strict_types=1);

namespace Identity\Contracts;

interface ValkeySessionClientPort
{
    public function ping(): bool;
    public function get(string $key): ?string;
    public function set(string $key, string $value, int $ttlSeconds): bool;
    public function delete(string $key): bool;
}

/** Productive-only extension. Preview clients deliberately do not implement it. */
interface TransactionalValkeySessionClientPort extends ValkeySessionClientPort
{
    /** @param non-empty-list<string> $keys */
    public function watch(array $keys): bool;
    public function unwatch(): void;
    public function multi(): bool;
    /** @return list<mixed>|false False means an optimistic transaction conflict. */
    public function exec(): array|false;
    public function ttl(string $key): int;
}
