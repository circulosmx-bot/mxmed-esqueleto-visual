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

$p1i1SemanticTotal = 0;
$p1i1SemanticPass = 0;
$p1i1NegativeTotal = 0;
$p1i1NegativeBlocked = 0;

function p1i1Ok(bool $condition, string $name): void
{
    global $p1i1SemanticTotal, $p1i1SemanticPass;
    $p1i1SemanticTotal++;
    if (!$condition) {
        throw new RuntimeException('semantic:' . $name);
    }
    $p1i1SemanticPass++;
}

function p1i1Blocked(callable $probe, string $name): Throwable
{
    global $p1i1NegativeTotal, $p1i1NegativeBlocked;
    $p1i1NegativeTotal++;
    try {
        $probe();
    } catch (Throwable $error) {
        $p1i1NegativeBlocked++;
        return $error;
    }
    throw new RuntimeException('negative_escaped:' . $name);
}

final class P1I1SyntheticProcessRunner implements ProcessRunnerPort
{
    /** @var list<list<string>> */
    public array $calls = [];

    /** @param array<string,array{exitCode:int,stdout:string,stderr:string}> $resultsByService */
    public function __construct(private readonly array $resultsByService)
    {
    }

    public function run(array $argv): array
    {
        $this->calls[] = $argv;
        $service = $argv[3] ?? '';
        return $this->resultsByService[$service] ?? [
            'exitCode' => 44,
            'stdout' => '',
            'stderr' => 'synthetic_missing_item',
        ];
    }
}

$fixtures = [
    AuditDatabaseCredentialRole::MIGRATION->service() => 'fixture-migration-secret',
    AuditDatabaseCredentialRole::WRITER->service() => 'fixture-writer-secret',
    AuditDatabaseCredentialRole::READER->service() => 'fixture-reader-secret',
];
$results = [];
foreach ($fixtures as $service => $secret) {
    $results[$service] = ['exitCode' => 0, 'stdout' => $secret . "\n", 'stderr' => ''];
}

$runner = new P1I1SyntheticProcessRunner($results);
$provider = new MacOsKeychainAuditDatabaseCredentialAdapter($runner);
$credentials = [];
foreach (AuditDatabaseCredentialRole::cases() as $role) {
    $credentials[$role->name] = $provider->credentialFor($role);
}

p1i1Ok($provider instanceof AuditDatabaseCredentialProvider, 'provider_contract');
p1i1Ok(AuditDatabaseCredentialRole::MIGRATION->service() === 'mxmed.audit.db.migration.local'
    && AuditDatabaseCredentialRole::MIGRATION->account() === 'mxmed_audit_migration_local'
    && AuditDatabaseCredentialRole::MIGRATION->label() === 'MXMed Audit DB Migration Local'
    && AuditDatabaseCredentialRole::MIGRATION->host() === '127.0.0.1', 'migration_identity');
p1i1Ok(AuditDatabaseCredentialRole::WRITER->service() === 'mxmed.audit.db.writer.local'
    && AuditDatabaseCredentialRole::WRITER->account() === 'mxmed_audit_writer_local'
    && AuditDatabaseCredentialRole::WRITER->label() === 'MXMed Audit DB Writer Local'
    && AuditDatabaseCredentialRole::WRITER->host() === '127.0.0.1', 'writer_identity');
p1i1Ok(AuditDatabaseCredentialRole::READER->service() === 'mxmed.audit.db.reader.local'
    && AuditDatabaseCredentialRole::READER->account() === 'mxmed_audit_reader_local'
    && AuditDatabaseCredentialRole::READER->label() === 'MXMed Audit DB Reader Local'
    && AuditDatabaseCredentialRole::READER->host() === '127.0.0.1', 'reader_identity');
p1i1Ok(count(array_unique(array_map(static fn(AuditDatabaseCredentialRole $role): string => $role->service(), AuditDatabaseCredentialRole::cases()))) === 3, 'services_distinct');
p1i1Ok(count(array_unique(array_map(static fn(AuditDatabaseCredentialRole $role): string => $role->account(), AuditDatabaseCredentialRole::cases()))) === 3, 'accounts_distinct');
p1i1Ok(count(array_unique(array_values($fixtures))) === 3, 'synthetic_fixtures_distinct');

foreach (AuditDatabaseCredentialRole::cases() as $index => $role) {
    $expectedArgv = ['/usr/bin/security', 'find-generic-password', '-s', $role->service(), '-a', $role->account(), '-w'];
    p1i1Ok($runner->calls[$index] === $expectedArgv, strtolower($role->name) . '_exact_argv');
    p1i1Ok(!in_array($fixtures[$role->service()], $runner->calls[$index], true), strtolower($role->name) . '_secret_absent_from_argv');
    $credential = $credentials[$role->name];
    p1i1Ok($credential->role === $role && $credential->username === $role->account() && $credential->host === '127.0.0.1', strtolower($role->name) . '_credential_identity');
    p1i1Ok($credential->secret() === $fixtures[$role->service()], strtolower($role->name) . '_fixture_in_process_memory');
}
p1i1Ok(count($runner->calls) === 3, 'one_runner_call_per_request');

$crlfRunner = new P1I1SyntheticProcessRunner([
    AuditDatabaseCredentialRole::WRITER->service() => ['exitCode' => 0, 'stdout' => 'fixture-writer-secret' . "\r\n", 'stderr' => ''],
]);
$crlfCredential = (new MacOsKeychainAuditDatabaseCredentialAdapter($crlfRunner))->credentialFor(AuditDatabaseCredentialRole::WRITER);
p1i1Ok($crlfCredential->secret() === 'fixture-writer-secret', 'terminal_crlf_removed_only');

ob_start();
var_dump($credentials['MIGRATION']);
$debugOutput = (string)ob_get_clean();
p1i1Ok(str_contains($debugOutput, '[redacted]') && !str_contains($debugOutput, 'fixture-migration-secret'), 'debug_output_redacted');
p1i1Ok(!in_array('fixture-migration-secret', get_object_vars($credentials['MIGRATION']), true), 'secret_not_public_property');
p1i1Ok(!$credentials['MIGRATION'] instanceof JsonSerializable && !method_exists($credentials['MIGRATION'], '__toString'), 'no_json_or_string_secret_surface');

p1i1Blocked(fn() => (new MacOsKeychainAuditDatabaseCredentialAdapter(new P1I1SyntheticProcessRunner([])))->credentialFor(AuditDatabaseCredentialRole::MIGRATION), 'missing_item_nonzero');
p1i1Blocked(fn() => (new MacOsKeychainAuditDatabaseCredentialAdapter(new P1I1SyntheticProcessRunner([
    AuditDatabaseCredentialRole::MIGRATION->service() => ['exitCode' => 0, 'stdout' => '', 'stderr' => ''],
])))->credentialFor(AuditDatabaseCredentialRole::MIGRATION), 'empty_stdout');
p1i1Blocked(fn() => (new MacOsKeychainAuditDatabaseCredentialAdapter(new P1I1SyntheticProcessRunner([
    AuditDatabaseCredentialRole::MIGRATION->service() => ['exitCode' => 0, 'stdout' => "\r\n", 'stderr' => ''],
])))->credentialFor(AuditDatabaseCredentialRole::MIGRATION), 'crlf_only_stdout');
p1i1Blocked(fn() => new AuditDatabaseCredential(AuditDatabaseCredentialRole::MIGRATION, '', '127.0.0.1', 'fixture-migration-secret'), 'empty_username');
p1i1Blocked(fn() => new AuditDatabaseCredential(AuditDatabaseCredentialRole::MIGRATION, 'mxmed_audit_writer_local', '127.0.0.1', 'fixture-migration-secret'), 'username_mismatch');
p1i1Blocked(fn() => new AuditDatabaseCredential(AuditDatabaseCredentialRole::MIGRATION, 'mxmed_audit_migration_local', 'localhost', 'fixture-migration-secret'), 'host_mismatch');
p1i1Blocked(fn() => new AuditDatabaseCredential(AuditDatabaseCredentialRole::MIGRATION, 'root', '127.0.0.1', 'fixture-migration-secret'), 'migration_root_blocked');
p1i1Blocked(fn() => new AuditDatabaseCredential(AuditDatabaseCredentialRole::WRITER, 'root', '127.0.0.1', 'fixture-writer-secret'), 'writer_root_blocked');
p1i1Blocked(fn() => new AuditDatabaseCredential(AuditDatabaseCredentialRole::READER, 'root', '127.0.0.1', 'fixture-reader-secret'), 'reader_root_blocked');
p1i1Blocked(fn() => new AuditDatabaseCredential(AuditDatabaseCredentialRole::WRITER, 'mxmed', '127.0.0.1', 'fixture-writer-secret'), 'generic_mxmed_blocked');
p1i1Blocked(fn() => new AuditDatabaseCredential(AuditDatabaseCredentialRole::MIGRATION, 'mxmed_audit_writer_local', '127.0.0.1', 'fixture-writer-secret'), 'writer_cannot_become_migration');
p1i1Blocked(fn() => new AuditDatabaseCredential(AuditDatabaseCredentialRole::WRITER, 'mxmed_audit_reader_local', '127.0.0.1', 'fixture-reader-secret'), 'reader_cannot_become_writer');
p1i1Blocked(fn() => new AuditDatabaseCredential(AuditDatabaseCredentialRole::READER, 'mxmed_audit_migration_local', '127.0.0.1', 'fixture-migration-secret'), 'migration_cannot_become_reader');
p1i1Blocked(fn() => new AuditDatabaseCredential(AuditDatabaseCredentialRole::READER, 'mxmed_audit_reader_local', '127.0.0.1', ''), 'empty_secret');
p1i1Blocked(fn() => serialize($credentials['MIGRATION']), 'serialization_blocked');

$sensitiveFailure = p1i1Blocked(fn() => (new MacOsKeychainAuditDatabaseCredentialAdapter(new P1I1SyntheticProcessRunner([
    AuditDatabaseCredentialRole::READER->service() => [
        'exitCode' => 1,
        'stdout' => 'fixture-reader-secret',
        'stderr' => 'fixture-reader-secret: synthetic diagnostic',
    ],
])))->credentialFor(AuditDatabaseCredentialRole::READER), 'failure_message_sanitized');
p1i1Ok(!str_contains($sensitiveFailure->getMessage(), 'fixture-reader-secret'), 'exception_has_no_synthetic_secret');
p1i1Ok(!str_contains($sensitiveFailure->getMessage(), 'synthetic diagnostic'), 'stderr_absent_from_exception');

echo 'P1_I1_SEMANTIC_PASS=' . $p1i1SemanticPass . '/' . $p1i1SemanticTotal . '_PASS' . PHP_EOL;
echo 'P1_I1_NEGATIVES_BLOCKED=' . $p1i1NegativeBlocked . '/' . $p1i1NegativeTotal . '_BLOCKED' . PHP_EOL;
echo 'SYNTHETIC_NON_SECRET_FIXTURES=true' . PHP_EOL;
echo 'PRODUCTIVE_PROCESS_RUNNER_IMPLEMENTED=false' . PHP_EOL;
echo 'REAL_KEYCHAIN_COMMANDS_EXECUTED=0' . PHP_EOL;
echo 'MYSQL_CONNECTION_ATTEMPTS=0' . PHP_EOL;
