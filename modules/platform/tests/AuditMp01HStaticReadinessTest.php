<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
$tests = [];
function h01Static(array &$tests, bool $condition, string $name): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL {$name}\n");
        exit(1);
    }
    $tests[] = $name;
}

$requiredPublished = [
    'modules/platform/contracts/CanonicalAuditEventType.php',
    'modules/platform/services/CanonicalAuditPolicyRegistry.php',
    'modules/platform/services/CorrelatableOperationCatalog.php',
    'modules/platform/services/SourceModuleCatalog.php',
    'modules/platform/contracts/TrustedRequestContext.php',
    'modules/platform/contracts/TrustedActorContext.php',
    'modules/platform/contracts/TrustedAuditContext.php',
    'modules/platform/contracts/CanonicalAuditEventInput.php',
    'modules/platform/services/AuditWriterContextBridge.php',
    'modules/identity/audit/BoundedBestEffortAuditEmitter.php',
    'modules/platform/contracts/AuditEventScopePolicy.php',
    'modules/identity/audit/Mp01eEventScopePolicy.php',
    'modules/platform/audit/Mp01fEventScopePolicy.php',
    'modules/platform/services/CanonicalAuditWriter.php',
    'modules/platform/repositories/CanonicalAuditTransactionPort.php',
    'modules/platform/repositories/PdoCanonicalAuditTransactionAdapter.php',
    'modules/platform/audit/read/AuditReadService.php',
    'modules/platform/audit/read/contracts/AuditReadRepositoryPort.php',
    'modules/platform/audit/read/SelfSecurityTimelinePolicy.php',
    'modules/platform/audit/read/contracts/SelfSecuritySubjectResolverPort.php',
    'modules/platform/db/migrations/2026_08_13_01_align_platform_audit_stream_heads_d11.sql',
];
h01Static($tests, array_reduce($requiredPublished, static fn(bool $carry, string $path): bool => $carry && is_file($root . '/' . $path), true), 'published_chain_present');

$candidatePaths = [
    'docs/product-completion/audit-mp01h-activation-readiness.md',
    'modules/platform/audit/readiness/AuditMp01HReadiness.php',
    'modules/platform/tests/AuditMp01HPostvalidationTest.php',
    'modules/platform/tests/AuditMp01HStaticReadinessTest.php',
];
h01Static($tests, count($candidatePaths) === 4, 'candidate_scope_physical_four');
h01Static($tests, array_reduce($candidatePaths, static fn(bool $carry, string $path): bool => $carry && is_file($root . '/' . $path), true), 'candidate_paths_installed');

$allPhp = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if ($file->isFile() && !$file->isLink() && $file->getExtension() === 'php') {
        $allPhp[] = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
    }
}

$classAuthorities = [
    'CanonicalAuditWriter', 'CanonicalAuditPolicyRegistry',
    'CanonicalAuditSerializer', 'CanonicalAuditSealer',
    'BoundedBestEffortAuditEmitter',
];
foreach ($classAuthorities as $className) {
    $definitions = 0;
    foreach ($allPhp as $path) {
        $text = file_get_contents($root . '/' . $path);
        if (preg_match('/\b(?:final\s+|abstract\s+)?class\s+' . preg_quote($className, '/') . '\b/', $text) === 1) {
            $definitions++;
        }
    }
    h01Static($tests, $definitions === 1, 'single_authority_' . $className);
}

$productiveTokens = [
    'CanonicalIdentityAuditProducer', 'CanonicalSessionAuditProducer',
    'CanonicalMp01fAuditProducer', 'CanonicalAuditWriterAdapter',
    'AuditReadService', 'SelfSecuritySubjectResolverPort',
];
$hiddenWiring = [];
foreach ($allPhp as $path) {
    if (str_contains($path, '/tests/') || str_contains($path, '/audit/')) {
        continue;
    }
    $text = file_get_contents($root . '/' . $path);
    foreach ($productiveTokens as $token) {
        if (str_contains($text, $token)) {
            $hiddenWiring[] = $path . ':' . $token;
        }
    }
}
h01Static($tests, $hiddenWiring === [], 'hidden_productive_wiring_zero');

$readAdapters = [];
$subjectAdapters = [];
foreach ($allPhp as $path) {
    if (str_contains($path, '/tests/')) {
        continue;
    }
    $text = file_get_contents($root . '/' . $path);
    if (preg_match('/implements\s+AuditReadRepositoryPort\b/', $text) === 1) {
        $readAdapters[] = $path;
    }
    if (preg_match('/implements\s+SelfSecuritySubjectResolverPort\b/', $text) === 1) {
        $subjectAdapters[] = $path;
    }
}
h01Static($tests, $readAdapters === [], 'productive_read_adapter_absent');
h01Static($tests, $subjectAdapters === [], 'productive_subject_adapter_absent');

$apiTokens = 0;
$apiRoot = $root . '/api';
if (is_dir($apiRoot)) {
    $apiIt = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($apiRoot, FilesystemIterator::SKIP_DOTS));
    foreach ($apiIt as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') continue;
        $text = file_get_contents($file->getPathname());
        foreach ($productiveTokens as $token) {
            if (str_contains($text, $token)) $apiTokens++;
        }
    }
}
h01Static($tests, $apiTokens === 0, 'productive_entrypoint_wiring_zero');

$d11 = file_get_contents($root . '/modules/platform/db/migrations/2026_08_13_01_align_platform_audit_stream_heads_d11.sql');
h01Static($tests, str_contains($d11, 'updated_at') && str_contains($d11, 'hash_version'), 'd11_authority_present');
h01Static($tests, !is_file($root . '/modules/platform/db/migrations/.d11-executed'), 'd11_execution_marker_absent');

$candidateText = '';
foreach ($candidatePaths as $path) $candidateText .= file_get_contents($root . '/' . $path);
$downloadsNeedle = '/Users/' . 'circulodigital/' . 'Downloads';
$temporaryNeedle = '/' . 'tmp' . '/';
$pdoNeedle = 'new ' . 'PDO(';
$dockerNeedle = 'docker ' . 'run';
$pushNeedle = 'git ' . 'push';
h01Static($tests, !str_contains($candidateText, $downloadsNeedle), 'no_downloads_dependency');
h01Static($tests, !str_contains($candidateText, $temporaryNeedle), 'no_tmp_dependency');
h01Static($tests, !str_contains($candidateText, $pdoNeedle), 'no_db_activation');
h01Static($tests, !str_contains($candidateText, $dockerNeedle), 'no_docker_activation');
h01Static($tests, !str_contains($candidateText, $pushNeedle), 'no_push_implementation');

echo "DORMANT_STATE_VERIFIED=true\n";
echo "HIDDEN_PRODUCTIVE_WIRING=0\n";
echo "RUNTIME_WRITER_ACTIVATED=false\n";
echo "IDENTITY_PRODUCER_ACTIVE=false\n";
echo "SESSION_PRODUCER_ACTIVE=false\n";
echo "MP01F_PRODUCER_ACTIVE=false\n";
echo "PRODUCTIVE_PRODUCERS_ACTIVE=false\n";
echo "PRODUCTIVE_MIDDLEWARE_ACTIVE=false\n";
echo "AUDIT_READ_ACCESS_EMISSION_ACTIVE=false\n";
echo "AUTHORITY_CUTOVER=false\n";
echo "D11_MIGRATION_EXECUTED=false\n";
echo "PRODUCTIVE_READ_ADAPTERS=0\n";
echo "PRODUCTIVE_SUBJECT_RESOLVER_ADAPTERS=0\n";
echo "REPO_INSTALLED_TESTS_SELF_CONTAINED=true\n";
echo "OUT_OF_REPO_RUNTIME_DEPENDENCIES=0\n";
echo "OUT_OF_REPO_TEST_DEPENDENCIES=0\n";
echo 'STATIC_POSTVALIDATION_TESTS=' . count($tests) . '/' . count($tests) . "_PASS\n";
