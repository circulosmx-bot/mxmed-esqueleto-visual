<?php
declare(strict_types=1);
namespace Identity\Contracts;

/** Browser persistence only. Never changes server idle/absolute deadlines. */
final class DevelopmentSessionCookiePolicy
{
    private function __construct() {}
    public static function fromEnvironment(string $environment, string $origin): ?self
    {
        if (getenv('MXMED_DEV_PERSISTENT_COOKIE') !== '1') return null;
        $url = parse_url($origin);
        if (!in_array($environment, ['local', 'development'], true)
            || in_array(getenv('APP_ENV'), ['production', 'staging'], true)
            || getenv('MXMED_PREVIEW_EXPLICIT') !== '1'
            || !in_array(getenv('MXMED_NORMAL_LOCAL_IDENTITY_EXPLICIT'), [false, '', '0'], true)
            || getenv('MXMED_DB_HOST') !== '127.0.0.1'
            || !str_starts_with((string)getenv('MXMED_DB_NAME'), 'mxmed_gate4d_preview_')
            || (int)getenv('MXMED_DB_PORT') <= 1024 || (int)getenv('MXMED_DB_PORT') === 3306
            || ($url['scheme'] ?? '') !== 'https' || ($url['host'] ?? '') !== '127.0.0.1'
            || !isset($url['port']) || isset($url['user']) || isset($url['pass'])) {
            throw new \RuntimeException('isolated_development_cookie_required');
        }
        return new self();
    }
    public function remainingSeconds(SessionRecord $record, Clock $clock): int
    {
        return max(0, min(SessionPolicy::ABSOLUTE_TTL_SECONDS,
            $record->absoluteExpiresAt()->getTimestamp() - $clock->now()->getTimestamp()));
    }
}
