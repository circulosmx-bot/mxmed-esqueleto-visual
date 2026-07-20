<?php
declare(strict_types=1);

namespace Identity\Contracts;

final class CredentialVersion
{
    public static function assertValid(int $version): int
    {
        if ($version < 1) throw new \InvalidArgumentException('invalid_credential_version');
        return $version;
    }

    public static function next(int $version): int
    {
        self::assertValid($version);
        return $version + 1;
    }
}
