<?php
declare(strict_types=1);

namespace Platform\Audit\Read;

use Platform\Audit\Read\Contracts\AuditReadCursorSecretProvider;

/** Local/dev current-key provider. Rotation deliberately invalidates prior local cursors. */
final class EnvironmentAuditReadCursorSecretProvider implements AuditReadCursorSecretProvider
{
    public const SECRET_ENV = 'MXMED_AUDIT_READ_CURSOR_HMAC_SECRET';
    public const VERSION_ENV = 'MXMED_AUDIT_READ_CURSOR_HMAC_KEY_VERSION';
    private const VERSION_PATTERN = '/^audit-read-cursor-local-v[0-9]{3,}$/D';

    public function currentAuditReadCursorKey(): array
    {
        $secret = getenv(self::SECRET_ENV);
        if ($secret === false) {
            throw new \RuntimeException('audit_read_cursor_secret_missing');
        }
        if (strlen($secret) < 32) {
            throw new \RuntimeException('audit_read_cursor_secret_too_short');
        }

        $version = getenv(self::VERSION_ENV);
        if ($version === false || preg_match(self::VERSION_PATTERN, $version) !== 1) {
            throw new \RuntimeException('audit_read_cursor_key_version_invalid');
        }

        return ['version' => $version, 'secret' => $secret];
    }
}
