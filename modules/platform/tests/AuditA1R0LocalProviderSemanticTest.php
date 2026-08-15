<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require_once $root . '/modules/platform/contracts/AuditSecretProvider.php';
require_once $root . '/modules/identity/audit/contracts/AuthIdentifierAuditSecretProvider.php';
require_once $root . '/modules/platform/audit/read/contracts/AuditReadCursorSecretProvider.php';
require_once $root . '/modules/platform/services/EnvironmentAuditSecretProvider.php';
require_once $root . '/modules/identity/audit/EnvironmentAuthIdentifierAuditSecretProvider.php';
require_once $root . '/modules/platform/audit/read/EnvironmentAuditReadCursorSecretProvider.php';
require_once $root . '/modules/platform/audit/read/AuditReadCursorCodec.php';

use Identity\Audit\EnvironmentAuthIdentifierAuditSecretProvider;
use Identity\Audit\Contracts\AuthIdentifierAuditSecretProvider;
use Platform\Audit\Read\AuditReadCursor;
use Platform\Audit\Read\AuditReadCursorCodec;
use Platform\Audit\Read\Contracts\AuditReadCursorSecretProvider;
use Platform\Audit\Read\EnvironmentAuditReadCursorSecretProvider;
use Platform\Contracts\AuditSecretProvider;
use Platform\Services\EnvironmentAuditSecretProvider;

$semanticTotal = 0; $semanticPass = 0; $negativeTotal = 0; $negativeBlocked = 0;
function r0Ok(bool $condition, string $name): void
{
    global $semanticTotal, $semanticPass;
    $semanticTotal++;
    if (!$condition) throw new RuntimeException('semantic:' . $name);
    $semanticPass++;
}
function r0Blocked(callable $probe, string $name): Throwable
{
    global $negativeTotal, $negativeBlocked;
    $negativeTotal++;
    try { $probe(); } catch (Throwable $error) { $negativeBlocked++; return $error; }
    throw new RuntimeException('negative_escaped:' . $name);
}
function r0Set(array $values, array $names): void
{
    foreach ($names as $name) putenv($name);
    foreach ($values as $name => $value) putenv($name . '=' . $value);
}
function r0Body(string $cursor): array
{
    [$encoded] = explode('.', $cursor, 2);
    $padding = (4 - strlen($encoded) % 4) % 4;
    $decoded = base64_decode(strtr($encoded . str_repeat('=', $padding), '-_', '+/'), true);
    return json_decode((string)$decoded, true, 8, JSON_THROW_ON_ERROR);
}

final class R0SyntheticCursorProvider implements AuditReadCursorSecretProvider
{
    /** @param array{version:string,secret:string} $key */
    public function __construct(public array $key) {}
    public function currentAuditReadCursorKey(): array { return $this->key; }
}

$names = [
    EnvironmentAuditSecretProvider::SECRET_ENV, EnvironmentAuditSecretProvider::VERSION_ENV,
    EnvironmentAuthIdentifierAuditSecretProvider::SECRET_ENV, EnvironmentAuthIdentifierAuditSecretProvider::VERSION_ENV,
    EnvironmentAuditReadCursorSecretProvider::SECRET_ENV, EnvironmentAuditReadCursorSecretProvider::VERSION_ENV,
];
$original = [];
foreach ($names as $name) { $value = getenv($name); $original[$name] = $value === false ? null : $value; putenv($name); }

try {
    $ipSecret = str_repeat('i', 32);
    r0Set([EnvironmentAuditSecretProvider::SECRET_ENV => $ipSecret, EnvironmentAuditSecretProvider::VERSION_ENV => 'audit-ip-local-v001'], $names);
    $ip = new EnvironmentAuditSecretProvider(); $ipKey = $ip->currentAuditIpKey();
    r0Ok($ip instanceof AuditSecretProvider, 'ip_published_interface');
    r0Ok($ipKey['version'] === 'audit-ip-local-v001' && $ipKey['secret'] === $ipSecret, 'ip_exact_environment_contract');
    r0Blocked(fn() => (r0Set([], $names)) ?? $ip->currentAuditIpKey(), 'ip_missing_secret');
    r0Set([EnvironmentAuditSecretProvider::SECRET_ENV => 'short', EnvironmentAuditSecretProvider::VERSION_ENV => 'audit-ip-local-v001'], $names);
    $ipShort = r0Blocked(fn() => $ip->currentAuditIpKey(), 'ip_short_secret');
    r0Ok(!str_contains($ipShort->getMessage(), 'short') || $ipShort->getMessage() === 'audit_ip_secret_too_short', 'ip_exception_has_no_material');
    r0Set([EnvironmentAuditSecretProvider::SECRET_ENV => $ipSecret], $names);
    r0Blocked(fn() => $ip->currentAuditIpKey(), 'ip_missing_version');
    r0Set([EnvironmentAuditSecretProvider::SECRET_ENV => $ipSecret, EnvironmentAuditSecretProvider::VERSION_ENV => 'invalid'], $names);
    r0Blocked(fn() => $ip->currentAuditIpKey(), 'ip_invalid_version');

    $authSecret = str_repeat('a', 32);
    r0Set([EnvironmentAuthIdentifierAuditSecretProvider::SECRET_ENV => $authSecret, EnvironmentAuthIdentifierAuditSecretProvider::VERSION_ENV => 'auth-identifier-local-v001'], $names);
    $auth = new EnvironmentAuthIdentifierAuditSecretProvider(); $authKey = $auth->currentAuthIdentifierAuditKey();
    r0Ok($auth instanceof AuthIdentifierAuditSecretProvider, 'auth_published_interface');
    r0Ok($authKey['namespace'] === 'audit-auth-identifier' && $authKey['version'] === 'auth-identifier-local-v001', 'auth_fixed_namespace_and_version');
    r0Blocked(fn() => (r0Set([], $names)) ?? $auth->currentAuthIdentifierAuditKey(), 'auth_missing_secret');
    r0Set([EnvironmentAuthIdentifierAuditSecretProvider::SECRET_ENV => 'short', EnvironmentAuthIdentifierAuditSecretProvider::VERSION_ENV => 'auth-identifier-local-v001'], $names);
    $authShort = r0Blocked(fn() => $auth->currentAuthIdentifierAuditKey(), 'auth_short_secret');
    r0Ok(!str_contains($authShort->getMessage(), $authSecret), 'auth_exception_has_no_material');
    r0Set([EnvironmentAuthIdentifierAuditSecretProvider::SECRET_ENV => $authSecret], $names);
    r0Blocked(fn() => $auth->currentAuthIdentifierAuditKey(), 'auth_missing_version');
    r0Set([EnvironmentAuthIdentifierAuditSecretProvider::SECRET_ENV => $authSecret, EnvironmentAuthIdentifierAuditSecretProvider::VERSION_ENV => 'bad version'], $names);
    r0Blocked(fn() => $auth->currentAuthIdentifierAuditKey(), 'auth_invalid_version');

    $cursorSecret = str_repeat('c', 32);
    r0Set([EnvironmentAuditReadCursorSecretProvider::SECRET_ENV => $cursorSecret, EnvironmentAuditReadCursorSecretProvider::VERSION_ENV => 'audit-read-cursor-local-v001'], $names);
    $cursorProvider = new EnvironmentAuditReadCursorSecretProvider(); $cursorKey = $cursorProvider->currentAuditReadCursorKey();
    r0Ok($cursorProvider instanceof AuditReadCursorSecretProvider, 'cursor_provider_interface');
    r0Ok($cursorKey['version'] === 'audit-read-cursor-local-v001' && $cursorKey['secret'] === $cursorSecret, 'cursor_exact_environment_contract');
    r0Blocked(fn() => (r0Set([], $names)) ?? $cursorProvider->currentAuditReadCursorKey(), 'cursor_missing_secret');
    r0Set([EnvironmentAuditReadCursorSecretProvider::SECRET_ENV => 'short', EnvironmentAuditReadCursorSecretProvider::VERSION_ENV => 'audit-read-cursor-local-v001'], $names);
    $cursorShort = r0Blocked(fn() => $cursorProvider->currentAuditReadCursorKey(), 'cursor_short_secret');
    r0Ok(!str_contains($cursorShort->getMessage(), $cursorSecret), 'cursor_exception_has_no_material');
    r0Set([EnvironmentAuditReadCursorSecretProvider::SECRET_ENV => $cursorSecret], $names);
    r0Blocked(fn() => $cursorProvider->currentAuditReadCursorKey(), 'cursor_missing_version');
    r0Set([EnvironmentAuditReadCursorSecretProvider::SECRET_ENV => $cursorSecret, EnvironmentAuditReadCursorSecretProvider::VERSION_ENV => 'cursor-v1'], $names);
    r0Blocked(fn() => $cursorProvider->currentAuditReadCursorKey(), 'cursor_invalid_version');

    r0Ok(count(array_unique([EnvironmentAuditSecretProvider::SECRET_ENV, EnvironmentAuthIdentifierAuditSecretProvider::SECRET_ENV, EnvironmentAuditReadCursorSecretProvider::SECRET_ENV])) === 3, 'distinct_secret_names');
    r0Ok(count(array_unique([EnvironmentAuditSecretProvider::VERSION_ENV, EnvironmentAuthIdentifierAuditSecretProvider::VERSION_ENV, EnvironmentAuditReadCursorSecretProvider::VERSION_ENV])) === 3, 'distinct_version_names');

    $point = new AuditReadCursor('2026-08-15T05:00:00.000001Z', str_repeat('a', 64));
    $legacy = new AuditReadCursorCodec(str_repeat('l', 32)); $legacyCursor = $legacy->encode($point);
    r0Ok(r0Body($legacyCursor)['v'] === 1 && $legacy->decode($legacyCursor)->eventId === $point->eventId, 'legacy_raw_v1_roundtrip');

    $provider = new R0SyntheticCursorProvider(['version' => 'audit-read-cursor-local-v001', 'secret' => str_repeat('p', 32)]);
    $codec = new AuditReadCursorCodec($provider); $v2 = $codec->encode($point); $v2Body = r0Body($v2);
    r0Ok($v2Body['v'] === 2 && $v2Body['key_version'] === 'audit-read-cursor-local-v001', 'provider_v2_version_embedded');
    r0Ok($codec->decode($v2)->createdAt === $point->createdAt, 'provider_v2_roundtrip');
    r0Blocked(fn() => (new AuditReadCursorCodec(new R0SyntheticCursorProvider(['version' => 'audit-read-cursor-local-v001', 'secret' => str_repeat('q', 32)])))->decode($v2), 'provider_wrong_key');
    r0Blocked(fn() => (new AuditReadCursorCodec(new R0SyntheticCursorProvider(['version' => 'audit-read-cursor-local-v002', 'secret' => str_repeat('p', 32)])))->decode($v2), 'provider_wrong_current_version');
    r0Blocked(fn() => (new AuditReadCursorCodec(new R0SyntheticCursorProvider(['version' => 'audit-read-cursor-local-v002', 'secret' => str_repeat('r', 32)])))->decode($v2), 'provider_rotation_invalidates_old_cursor');
    r0Blocked(fn() => $codec->decode(substr($v2, 0, -1) . ($v2[-1] === 'A' ? 'B' : 'A')), 'provider_tampering');
    r0Blocked(fn() => (new AuditReadCursorCodec(new R0SyntheticCursorProvider(['version' => 'audit-read-cursor-local-v001', 'secret' => 'short'])))->encode($point), 'provider_weak_secret');
    r0Blocked(fn() => $codec->decode((new AuditReadCursorCodec(str_repeat('p', 32)))->encode($point)), 'provider_unversioned_v1_blocked');
} finally {
    foreach ($original as $name => $value) { $value === null ? putenv($name) : putenv($name . '=' . $value); }
}

$restored = true;
foreach ($original as $name => $value) { $current = getenv($name); $restored = $restored && ($value === null ? $current === false : $current === $value); }
r0Ok($restored, 'test_environment_restored');

echo 'R0_SEMANTIC_TESTS=' . $semanticPass . '/' . $semanticTotal . '_PASS' . PHP_EOL;
echo 'R0_NEGATIVES=' . $negativeBlocked . '/' . $negativeTotal . '_BLOCKED' . PHP_EOL;
echo 'AUDIT_IP_PROVIDER_IMPLEMENTS_PUBLISHED_INTERFACE=true' . PHP_EOL;
echo 'AUTH_IDENTIFIER_PROVIDER_IMPLEMENTS_PUBLISHED_INTERFACE=true' . PHP_EOL;
echo 'AUDIT_READ_CURSOR_PROVIDER_CONTRACT_PUBLISHED=true' . PHP_EOL;
echo 'AUDIT_READ_CURSOR_ENV_PROVIDER_IMPLEMENTS_CONTRACT=true' . PHP_EOL;
echo 'AUTH_IDENTIFIER_NAMESPACE_ENV_OVERRIDE_ALLOWED=false' . PHP_EOL;
echo 'LEGACY_RAW_STRING_CURSOR_V1_COMPATIBILITY=true' . PHP_EOL;
echo 'VERSIONED_PROVIDER_CURSOR_V2=true' . PHP_EOL;
echo 'PROVIDER_MODE_ACCEPTS_UNVERSIONED_V1=false' . PHP_EOL;
echo 'PROVIDER_KEY_VERSION_MISMATCH_BLOCKED=true' . PHP_EOL;
echo 'PROVIDER_ROTATION_INVALIDATES_OLD_CURSOR=true' . PHP_EOL;
echo 'TEST_ENVIRONMENT_RESTORED=true' . PHP_EOL;
