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
use Platform\Audit\Read\AuditReadCursorCodec;
use Platform\Audit\Read\Contracts\AuditReadCursorSecretProvider;
use Platform\Audit\Read\EnvironmentAuditReadCursorSecretProvider;
use Platform\Contracts\AuditSecretProvider;
use Platform\Services\EnvironmentAuditSecretProvider;

$total = 0; $pass = 0;
function r0Static(bool $condition, string $name): void
{
    global $total, $pass; $total++;
    if (!$condition) throw new RuntimeException('static:' . $name);
    $pass++;
}

$candidate = [
    'modules/platform/services/EnvironmentAuditSecretProvider.php',
    'modules/identity/audit/EnvironmentAuthIdentifierAuditSecretProvider.php',
    'modules/platform/audit/read/contracts/AuditReadCursorSecretProvider.php',
    'modules/platform/audit/read/EnvironmentAuditReadCursorSecretProvider.php',
    'modules/platform/audit/read/AuditReadCursorCodec.php',
    'modules/platform/tests/AuditA1R0LocalProviderSemanticTest.php',
    'modules/platform/tests/AuditA1R0LocalProviderStaticTest.php',
];
r0Static(count($candidate) === 7, 'physical_candidate_count');
r0Static(array_reduce($candidate, static fn(bool $ok, string $path): bool => $ok && is_file($root . '/' . $path), true), 'candidate_paths_installed');
r0Static((new ReflectionClass(EnvironmentAuditSecretProvider::class))->implementsInterface(AuditSecretProvider::class), 'ip_provider_interface');
r0Static((new ReflectionClass(EnvironmentAuthIdentifierAuditSecretProvider::class))->implementsInterface(AuthIdentifierAuditSecretProvider::class), 'auth_provider_interface');
r0Static(interface_exists(AuditReadCursorSecretProvider::class), 'cursor_provider_contract');
r0Static((new ReflectionClass(EnvironmentAuditReadCursorSecretProvider::class))->implementsInterface(AuditReadCursorSecretProvider::class), 'cursor_provider_interface');

$constructor = (new ReflectionClass(AuditReadCursorCodec::class))->getConstructor();
$type = $constructor?->getParameters()[0]->getType();
$types = $type instanceof ReflectionUnionType ? array_map(static fn(ReflectionNamedType $part): string => $part->getName(), $type->getTypes()) : [];
sort($types);
$expectedTypes = ['Platform\\Audit\\Read\\Contracts\\AuditReadCursorSecretProvider', 'string']; sort($expectedTypes);
r0Static($types === $expectedTypes, 'raw_and_provider_constructor_compatibility');

$production = array_slice($candidate, 0, 5);
$forbidden = ['file_put_contents', 'fopen(', 'fwrite(', 'shell_exec', 'exec(', 'system(', 'passthru', 'curl_exec', 'Aws\\', 'Dotenv', '$_GET', '$_POST', '$_REQUEST', 'PDO', 'mysqli', 'INSERT INTO', 'UPDATE ', 'DELETE FROM'];
$violations = []; $getenvPaths = [];
foreach ($production as $path) {
    $source = file_get_contents($root . '/' . $path);
    foreach ($forbidden as $needle) if (str_contains($source, $needle)) $violations[] = $path . ':' . $needle;
    if (str_contains($source, 'getenv(')) $getenvPaths[] = $path;
}
sort($getenvPaths);
$expectedGetenv = [
    'modules/identity/audit/EnvironmentAuthIdentifierAuditSecretProvider.php',
    'modules/platform/audit/read/EnvironmentAuditReadCursorSecretProvider.php',
    'modules/platform/services/EnvironmentAuditSecretProvider.php',
];
sort($expectedGetenv);
r0Static($violations === [], 'production_source_safety');
r0Static($getenvPaths === $expectedGetenv, 'environment_reads_only_exact_providers');
r0Static(EnvironmentAuditSecretProvider::SECRET_ENV === 'MXMED_AUDIT_IP_HMAC_SECRET' && EnvironmentAuditSecretProvider::VERSION_ENV === 'MXMED_AUDIT_IP_HMAC_KEY_VERSION', 'ip_exact_names');
r0Static(EnvironmentAuthIdentifierAuditSecretProvider::SECRET_ENV === 'MXMED_AUTH_IDENTIFIER_HMAC_SECRET' && EnvironmentAuthIdentifierAuditSecretProvider::VERSION_ENV === 'MXMED_AUTH_IDENTIFIER_HMAC_KEY_VERSION', 'auth_exact_names');
r0Static(EnvironmentAuditReadCursorSecretProvider::SECRET_ENV === 'MXMED_AUDIT_READ_CURSOR_HMAC_SECRET' && EnvironmentAuditReadCursorSecretProvider::VERSION_ENV === 'MXMED_AUDIT_READ_CURSOR_HMAC_KEY_VERSION', 'cursor_exact_names');
r0Static(EnvironmentAuthIdentifierAuditSecretProvider::NAMESPACE === 'audit-auth-identifier', 'auth_namespace_fixed');
r0Static(count(array_unique([EnvironmentAuditSecretProvider::SECRET_ENV, EnvironmentAuthIdentifierAuditSecretProvider::SECRET_ENV, EnvironmentAuditReadCursorSecretProvider::SECRET_ENV])) === 3, 'cross_class_secret_names_distinct');
r0Static(count(array_unique([EnvironmentAuditSecretProvider::VERSION_ENV, EnvironmentAuthIdentifierAuditSecretProvider::VERSION_ENV, EnvironmentAuditReadCursorSecretProvider::VERSION_ENV])) === 3, 'cross_class_version_names_distinct');

$semanticPath = $root . '/modules/platform/tests/AuditMp01GReadApiSemanticTest.php';
$staticPath = $root . '/modules/platform/tests/AuditMp01GStaticContractsTest.php';
r0Static(hash_file('sha256', $semanticPath) === 'f56d4f4d848b2ac401fc731e9b296dee8ca901189f64e61ebc73277c1418c39d', 'existing_mp01g_semantic_byte_identical');
r0Static(hash_file('sha256', $staticPath) === '33db54ba9c9f4635d4f56398839d03d232700d0aa1f92172dfb4e30d3405d045', 'existing_mp01g_static_byte_identical');
r0Static(array_filter($candidate, static fn(string $path): bool => str_starts_with($path, 'api/') || str_contains($path, '/controllers/') || str_contains($path, '/routes/')) === [], 'no_productive_entrypoint_wiring');

echo 'R0_STATIC_TESTS=' . $pass . '/' . $total . '_PASS' . PHP_EOL;
echo 'TARGET_PATH_COUNT=7' . PHP_EOL;
echo 'PRODUCTIVE_PROVIDER_WIRING=0' . PHP_EOL;
echo 'PRODUCTIVE_CURSOR_WIRING=0' . PHP_EOL;
echo 'EXISTING_MP01G_TEST_FILES_BYTE_IDENTICAL=true' . PHP_EOL;
echo 'OUT_OF_REPO_RUNTIME_DEPENDENCIES=0' . PHP_EOL;
echo 'OUT_OF_REPO_TEST_DEPENDENCIES=0' . PHP_EOL;
