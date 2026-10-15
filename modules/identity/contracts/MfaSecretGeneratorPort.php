<?php
declare(strict_types=1);
namespace Identity\Contracts;

interface MfaSecretGeneratorPort
{
    /** Returns 20 bytes of unpredictable TOTP key material. */
    public function generate(): string;
}
