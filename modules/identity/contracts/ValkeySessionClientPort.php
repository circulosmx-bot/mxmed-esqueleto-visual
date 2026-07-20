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
