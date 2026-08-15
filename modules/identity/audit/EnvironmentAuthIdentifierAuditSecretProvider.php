<?php
declare(strict_types=1);

namespace Identity\Audit;

use Identity\Audit\Contracts\AuthIdentifierAuditSecretProvider;

/** Local/dev process-environment provider with a fixed, non-configurable namespace. */
final class EnvironmentAuthIdentifierAuditSecretProvider implements AuthIdentifierAuditSecretProvider
{
    public const SECRET_ENV = 'MXMED_AUTH_IDENTIFIER_HMAC_SECRET';
    public const VERSION_ENV = 'MXMED_AUTH_IDENTIFIER_HMAC_KEY_VERSION';
    public const NAMESPACE = 'audit-auth-identifier';
    private const VERSION_PATTERN = '/^auth-identifier-local-v[0-9]{3,}$/D';

    public function currentAuthIdentifierAuditKey(): array
    {
        $secret = getenv(self::SECRET_ENV);
        if ($secret === false) {
            throw new \RuntimeException('auth_identifier_secret_missing');
        }
        if (strlen($secret) < 32) {
            throw new \RuntimeException('auth_identifier_secret_too_short');
        }

        $version = getenv(self::VERSION_ENV);
        if ($version === false || preg_match(self::VERSION_PATTERN, $version) !== 1) {
            throw new \RuntimeException('auth_identifier_key_version_invalid');
        }

        return ['namespace' => self::NAMESPACE, 'version' => $version, 'secret' => $secret];
    }
}
