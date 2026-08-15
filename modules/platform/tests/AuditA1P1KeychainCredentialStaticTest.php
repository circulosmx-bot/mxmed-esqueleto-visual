<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require_once $root . '/modules/platform/audit/db/AuditDatabaseCredentialRole.php';
require_once $root . '/modules/platform/audit/db/AuditDatabaseCredential.php';
require_once $root . '/modules/platform/audit/db/contracts/AuditDatabaseCredentialProvider.php';
require_once $root . '/modules/platform/audit/db/contracts/ProcessRunnerPort.php';
require_once $root . '/modules/platform/audit/db/MacOsKeychainAuditDatabaseCredentialAdapter.php';

use Platform\Audit\Db\AuditDatabaseCredential;
use Platform\Audit\Db\AuditDatabaseCredentialRole;
use Platform\Audit\Db\Contracts\AuditDatabaseCredentialProvider;
use Platform\Audit\Db\Contracts\ProcessRunnerPort;
use Platform\Audit\Db\MacOsKeychainAuditDatabaseCredentialAdapter;

$p1i1StaticTotal = 0;
$p1i1StaticPass = 0;
function p1i1Static(bool $condition, string $name): void
{
    global $p1i1StaticTotal, $p1i1StaticPass;
    $p1i1StaticTotal++;
    if (!$condition) {
        throw new RuntimeException('static:' . $name);
    }
    $p1i1StaticPass++;
}

$candidate = [
    'modules/platform/audit/db/AuditDatabaseCredentialRole.php',
    'modules/platform/audit/db/AuditDatabaseCredential.php',
    'modules/platform/audit/db/contracts/AuditDatabaseCredentialProvider.php',
    'modules/platform/audit/db/contracts/ProcessRunnerPort.php',
    'modules/platform/audit/db/MacOsKeychainAuditDatabaseCredentialAdapter.php',
    'modules/platform/tests/AuditA1P1KeychainCredentialSemanticTest.php',
    'modules/platform/tests/AuditA1P1KeychainCredentialStaticTest.php',
];
$production = array_slice($candidate, 0, 5);
p1i1Static(count($candidate) === 7, 'target_path_count');
p1i1Static(array_reduce($candidate, static fn(bool $ok, string $path): bool => $ok && is_file($root . '/' . $path), true), 'all_targets_installed');
p1i1Static(count(AuditDatabaseCredentialRole::cases()) === 3, 'role_count');

$expectedIdentities = [
    'MIGRATION' => ['mxmed.audit.db.migration.local', 'mxmed_audit_migration_local', 'MXMed Audit DB Migration Local', '127.0.0.1'],
    'WRITER' => ['mxmed.audit.db.writer.local', 'mxmed_audit_writer_local', 'MXMed Audit DB Writer Local', '127.0.0.1'],
    'READER' => ['mxmed.audit.db.reader.local', 'mxmed_audit_reader_local', 'MXMed Audit DB Reader Local', '127.0.0.1'],
];
$actualIdentities = [];
foreach (AuditDatabaseCredentialRole::cases() as $role) {
    $actualIdentities[$role->name] = [$role->service(), $role->account(), $role->label(), $role->host()];
}
p1i1Static($actualIdentities === $expectedIdentities, 'exact_role_identities');
p1i1Static(count(array_unique(array_column($actualIdentities, 0))) === 3, 'services_distinct');
p1i1Static(count(array_unique(array_column($actualIdentities, 1))) === 3, 'accounts_distinct');
p1i1Static(count(array_unique(array_map(static fn(array $row): string => $row[0] . "\0" . $row[1], $actualIdentities))) === 3, 'service_account_pairs_distinct');

$adapterReflection = new ReflectionClass(MacOsKeychainAuditDatabaseCredentialAdapter::class);
p1i1Static($adapterReflection->implementsInterface(AuditDatabaseCredentialProvider::class), 'adapter_provider_contract');
$constructorType = $adapterReflection->getConstructor()?->getParameters()[0]->getType();
p1i1Static($constructorType instanceof ReflectionNamedType && $constructorType->getName() === ProcessRunnerPort::class, 'runner_injected_without_default');
p1i1Static((new ReflectionClass(ProcessRunnerPort::class))->isInterface(), 'runner_is_port_only');

$credentialReflection = new ReflectionClass(AuditDatabaseCredential::class);
$secretProperty = $credentialReflection->getProperty('secretMaterial');
p1i1Static($secretProperty->isPrivate() && !$credentialReflection->implementsInterface(JsonSerializable::class), 'secret_private_and_not_json_serializable');
p1i1Static(!$credentialReflection->hasMethod('__toString') && $credentialReflection->hasMethod('__debugInfo') && $credentialReflection->hasMethod('__serialize'), 'secret_debug_surfaces_controlled');

$forbidden = [
    'file_put_contents', 'fopen(', 'fwrite(', 'shell_exec', 'exec(', 'system(', 'passthru', 'proc_open',
    'getenv(', 'putenv(', 'PDO', 'mysqli', 'MYSQL_PWD', 'MXMED_DB_PASS', 'mysql_config_editor',
    'CREATE USER', 'ALTER USER', 'DROP USER', 'GRANT', 'REVOKE',
];
$violations = [];
foreach ($production as $path) {
    $source = file_get_contents($root . '/' . $path);
    foreach ($forbidden as $needle) {
        if (str_contains($source, $needle)) {
            $violations[] = $path . ':' . $needle;
        }
    }
}
p1i1Static($violations === [], 'production_source_safety');

$adapterSource = file_get_contents($root . '/modules/platform/audit/db/MacOsKeychainAuditDatabaseCredentialAdapter.php');
$argvMatch = [];
p1i1Static(preg_match('/\\$argv\\s*=\\s*\\[(.*?)\\];/s', $adapterSource, $argvMatch) === 1, 'argv_array_physical');
$argvBlock = $argvMatch[1] ?? '';
p1i1Static(str_contains($argvBlock, 'SECURITY_EXECUTABLE')
    && str_contains($argvBlock, "'find-generic-password'")
    && str_contains($argvBlock, "'-s'")
    && str_contains($argvBlock, "'-a'")
    && str_contains($argvBlock, "'-w'"), 'keychain_read_argv_structure');
p1i1Static(!str_contains($argvBlock, '$secret') && substr_count($adapterSource, '$this->runner->run($argv)') === 1, 'secret_absent_and_one_runner_call');

$productiveRunnerImplementations = 0;
foreach ($production as $path) {
    if (str_contains(file_get_contents($root . '/' . $path), 'implements ProcessRunnerPort')) {
        $productiveRunnerImplementations++;
    }
}
p1i1Static($productiveRunnerImplementations === 0, 'no_productive_process_runner');
p1i1Static(array_filter($candidate, static fn(string $path): bool => str_starts_with($path, 'api/')
    || str_contains($path, '/controllers/')
    || str_contains($path, '/routes/')) === [], 'no_productive_entrypoint_wiring');

$protectedCritical = [
    'modules/platform/repositories/PdoCanonicalAuditTransactionAdapter.php' => 'b398e6b903251d8fe3c8c0361d2ed74e357571359a46e70aa882aee5a94e8a3d',
    'modules/platform/db/migrations/AuditMp01BMigrationManifest.php' => '099502f764c15d25b9a623801c8dd1b9e1608acd6c6b51be4a44bd9b7f2a70fd',
    'modules/platform/audit/read/contracts/AuditReadRepositoryPort.php' => '13b6029e14840812f3c5abc58603516a44ca68b3a9c10318bb08fd423e4ba11d',
    'api/_lib/db.php' => '3b32a6e245a4d8cc756031fde7a05ba47a1af516efca5914fc82d61be4526964',
    'modules/platform/services/EnvironmentAuditSecretProvider.php' => '8ab246e1704637ed087d3a07111b659743cce70ac31465f4bed1d58c8f5fa8f6',
    'modules/identity/audit/EnvironmentAuthIdentifierAuditSecretProvider.php' => '4aadb7bcc2ea46d030bd2edfcbc34f965096fdc19b3710181aecf1716a93fb8b',
    'modules/platform/audit/read/contracts/AuditReadCursorSecretProvider.php' => '5fae31ae85a8cab005856d5310612f442dc59b7e0f0eaab4a5ca734dbfb40bfd',
    'modules/platform/audit/read/EnvironmentAuditReadCursorSecretProvider.php' => 'b2f95666275085abd81d8cd060e760bb7647862b31200eb887b9488f8d280f91',
    'modules/platform/audit/read/AuditReadCursorCodec.php' => '10b4a1360b592f3dc5470a0de44525d05a9a0a0f1f4cc8c76b0e40e807b1c101',
    'modules/platform/tests/AuditA1R0LocalProviderSemanticTest.php' => 'ec905c6c7a2b90131d01f7d0445ff1df0887df0fb3bbe0c9992eae8d236d7afb',
    'modules/platform/tests/AuditA1R0LocalProviderStaticTest.php' => '7bbb1bb143bc82b27ea2394d4993dd5b75dd7acc2c5bb5f521ab0b4f50b3850a',
];
$protectedMatch = 0;
foreach ($protectedCritical as $path => $sha256) {
    if (is_file($root . '/' . $path) && hash_file('sha256', $root . '/' . $path) === $sha256) {
        $protectedMatch++;
    }
}
p1i1Static($protectedMatch === count($protectedCritical), 'protected_critical_byte_identity');

echo 'P1_I1_STATIC_PASS=' . $p1i1StaticPass . '/' . $p1i1StaticTotal . '_PASS' . PHP_EOL;
echo 'TARGET_PATH_COUNT=7' . PHP_EOL;
echo 'PROTECTED_CRITICAL_MATCH=' . $protectedMatch . '/' . count($protectedCritical) . '_PASS' . PHP_EOL;
echo 'PRODUCTIVE_PROCESS_RUNNER_IMPLEMENTED=false' . PHP_EOL;
echo 'PRODUCTIVE_DB_CREDENTIAL_WIRING=0' . PHP_EOL;
echo 'REAL_SECURITY_PROCESS_INVOCATIONS=0' . PHP_EOL;
echo 'MYSQL_CONNECTION_ATTEMPTS=0' . PHP_EOL;
