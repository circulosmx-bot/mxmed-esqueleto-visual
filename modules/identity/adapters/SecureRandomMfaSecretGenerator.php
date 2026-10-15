<?php
declare(strict_types=1);
namespace Identity\Adapters;

use Identity\Contracts\MfaSecretGeneratorPort;

final class SecureRandomMfaSecretGenerator implements MfaSecretGeneratorPort
{
    public function generate(): string { return random_bytes(20); }
}
