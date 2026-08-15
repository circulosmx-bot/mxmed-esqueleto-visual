<?php
declare(strict_types=1);

namespace Platform\Services;

use Platform\Contracts\AuditSecretProvider;

/** Local/dev process-environment provider. It never persists or prints key material. */
final class EnvironmentAuditSecretProvider implements AuditSecretProvider
{
    public const SECRET_ENV = 'MXMED_AUDIT_IP_HMAC_SECRET';
    public const VERSION_ENV = 'MXMED_AUDIT_IP_HMAC_KEY_VERSION';
    private const VERSION_PATTERN = '/^audit-ip-local-v[0-9]{3,}$/D';

    public function currentAuditIpKey(): array
    {
        $secret = getenv(self::SECRET_ENV);
        if ($secret === false) {
            throw new \RuntimeException('audit_ip_secret_missing');
        }
        if (strlen($secret) < 32) {
            throw new \RuntimeException('audit_ip_secret_too_short');
        }

        $version = getenv(self::VERSION_ENV);
        if ($version === false || preg_match(self::VERSION_PATTERN, $version) !== 1) {
            throw new \RuntimeException('audit_ip_key_version_invalid');
        }

        return ['version' => $version, 'secret' => $secret];
    }
}
