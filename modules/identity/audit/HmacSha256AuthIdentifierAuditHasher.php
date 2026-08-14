<?php
declare(strict_types=1);

namespace Identity\Audit;

use Identity\Audit\Contracts\AuthIdentifierAuditSecretProvider;

final class HmacSha256AuthIdentifierAuditHasher
{
    public const REQUIRED_NAMESPACE = 'audit-auth-identifier';

    public function __construct(private AuthIdentifierAuditSecretProvider $secrets) {}

    public function hashCanonicalIdentifier(string $canonicalAuthIdentifier): AuthIdentifierAuditTarget
    {
        if ($canonicalAuthIdentifier === '' || trim($canonicalAuthIdentifier) !== $canonicalAuthIdentifier) {
            throw new \InvalidArgumentException('canonical_auth_identifier_required');
        }
        $key = $this->secrets->currentAuthIdentifierAuditKey();
        if (($key['namespace'] ?? null) !== self::REQUIRED_NAMESPACE) {
            throw new \RuntimeException('dedicated_auth_identifier_hmac_namespace_required');
        }
        $version = $key['version'] ?? null;
        $secret = $key['secret'] ?? null;
        if (!is_string($version) || $version === '') throw new \RuntimeException('auth_identifier_hmac_key_version_required');
        if (!is_string($secret) || strlen($secret) < 32) throw new \RuntimeException('auth_identifier_hmac_secret_minimum_256_bits');
        return new AuthIdentifierAuditTarget($version, hash_hmac('sha256', $canonicalAuthIdentifier, $secret));
    }
}
