<?php
declare(strict_types=1);

namespace Identity\Audit;

final readonly class AuthIdentifierAuditTarget
{
    public function __construct(public string $keyVersion, public string $digestHex)
    {
        if (preg_match('/^[a-z0-9][a-z0-9._-]{0,63}$/D', $keyVersion) !== 1) {
            throw new \InvalidArgumentException('invalid_auth_identifier_hmac_key_version');
        }
        if (preg_match('/^[a-f0-9]{64}$/D', $digestHex) !== 1) {
            throw new \InvalidArgumentException('invalid_auth_identifier_hmac_digest');
        }
    }

    public function targetType(): string { return 'AUTH_IDENTIFIER_HMAC'; }
    public function targetId(): string { return $this->keyVersion . ':' . $this->digestHex; }
}
