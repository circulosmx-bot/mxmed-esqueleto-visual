<?php
declare(strict_types=1);

namespace Identity\Contracts;

final class PasswordHash
{
    private const MIN_MEMORY_COST = 32768;
    private const MIN_TIME_COST = 2;
    private const MIN_THREADS = 1;

    public static function assertAvailable(): void
    {
        if (!defined('PASSWORD_ARGON2ID')) {
            throw new \RuntimeException('ARGON2ID_RUNTIME_UNAVAILABLE');
        }
    }

    /** @return array{memory_cost:int,time_cost:int,threads:int} */
    public static function options(): array
    {
        self::assertAvailable();
        $memory = self::envInt('MXMED_IDENTITY_ARGON2_MEMORY_COST', 65536);
        $time = self::envInt('MXMED_IDENTITY_ARGON2_TIME_COST', 2);
        $threads = self::envInt('MXMED_IDENTITY_ARGON2_THREADS', 1);
        if ($memory < self::MIN_MEMORY_COST || $time < self::MIN_TIME_COST || $threads < self::MIN_THREADS) {
            throw new \RuntimeException('argon2id_unsafe_configuration');
        }
        return ['memory_cost' => $memory, 'time_cost' => $time, 'threads' => $threads];
    }

    public static function hash(string $password): string
    {
        self::assertAvailable();
        $hash = password_hash($password, PASSWORD_ARGON2ID, self::options());
        if (!is_string($hash) || $hash === '') throw new \RuntimeException('argon2id_hash_failed');
        return $hash;
    }

    public static function verify(string $password, string $hash): bool
    {
        self::assertAvailable();
        return password_verify($password, $hash);
    }

    public static function needsRehash(string $hash): bool
    {
        self::assertAvailable();
        return password_needs_rehash($hash, PASSWORD_ARGON2ID, self::options());
    }

    public static function dummyHash(): string
    {
        static $hash;
        if (!is_string($hash)) $hash = self::hash('mxmed-dummy-verification-value');
        return $hash;
    }

    private static function envInt(string $name, int $default): int
    {
        $raw = getenv($name);
        return $raw === false || trim($raw) === '' ? $default : (int)$raw;
    }
}
