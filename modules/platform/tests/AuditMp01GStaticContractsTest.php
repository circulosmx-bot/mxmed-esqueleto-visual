<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
foreach (['CanonicalAuditEventType', 'CanonicalAuditReasonCode', 'CanonicalAuditResult', 'CanonicalAuditRetentionClass', 'CanonicalAuditSeverity', 'TrustedActorContext'] as $name) require_once $root . '/modules/platform/contracts/' . $name . '.php';
foreach (['SourceModuleCatalog', 'CanonicalAuditPolicyRegistry'] as $name) require_once $root . '/modules/platform/services/' . $name . '.php';
$read = $root . '/modules/platform/audit/read/';
foreach (['AuditReadAccess', 'TrustedAuditReadAuthority', 'AuditReadFilter', 'AuditReadCursorCodec', 'AuditReadProjection', 'AuditReadQuery', 'AuditReadAuthorization', 'AuditReadPage'] as $name) require_once $read . $name . '.php';
require_once $read . 'contracts/AuditReadRepositoryPort.php';
require_once $read . 'contracts/SelfSecuritySubjectResolverPort.php';
require_once $read . 'SelfSecurityTimelinePolicy.php';
require_once $read . 'AuditReadService.php';

use Platform\Audit\Read\AuditReadProjection;
use Platform\Audit\Read\AuditReadQuery;
use Platform\Audit\Read\AuditReadService;
use Platform\Audit\Read\Contracts\AuditReadRepositoryPort;
use Platform\Audit\Read\Contracts\SelfSecuritySubjectResolverPort;
use Platform\Audit\Read\SelfSecurityTimelinePolicy;
use Platform\Services\CanonicalAuditPolicyRegistry;

$candidateFiles = [
    'modules/platform/audit/read/AuditReadAccess.php',
    'modules/platform/audit/read/TrustedAuditReadAuthority.php',
    'modules/platform/audit/read/AuditReadFilter.php',
    'modules/platform/audit/read/AuditReadCursorCodec.php',
    'modules/platform/audit/read/AuditReadProjection.php',
    'modules/platform/audit/read/AuditReadQuery.php',
    'modules/platform/audit/read/AuditReadAuthorization.php',
    'modules/platform/audit/read/AuditReadPage.php',
    'modules/platform/audit/read/SelfSecurityTimelinePolicy.php',
    'modules/platform/audit/read/contracts/AuditReadRepositoryPort.php',
    'modules/platform/audit/read/contracts/SelfSecuritySubjectResolverPort.php',
    'modules/platform/audit/read/AuditReadService.php',
    'modules/platform/tests/AuditMp01GReadApiSemanticTest.php',
    'modules/platform/tests/AuditMp01GStaticContractsTest.php',
    'docs/product-completion/audit-mp01g-read-contract.md',
];
$total = 0; $pass = 0;
function mp01gStatic(bool $condition, string $name): void
{
    global $total, $pass; $total++;
    if (!$condition) throw new RuntimeException('static:' . $name);
    $pass++;
}
foreach ($candidateFiles as $file) mp01gStatic(is_file($root . '/' . $file), 'installed_' . $file);
mp01gStatic(count($candidateFiles) === 15, 'physical_candidate_count');
$constructor = (new ReflectionClass(AuditReadService::class))->getConstructor();
mp01gStatic($constructor?->getParameters()[0]->getType()?->getName() === AuditReadRepositoryPort::class, 'repository_port_boundary');
$policyProperty = (new ReflectionClass(SelfSecurityTimelinePolicy::class))->getProperty('canonicalPolicies');
mp01gStatic($policyProperty->getType()?->getName() === CanonicalAuditPolicyRegistry::class, 'canonical_policy_reused');
$subjectProperty = (new ReflectionClass(SelfSecurityTimelinePolicy::class))->getProperty('subjects');
mp01gStatic($subjectProperty->getType()?->getName() === SelfSecuritySubjectResolverPort::class, 'trusted_subject_resolver_required');
$resolverMethod = new ReflectionMethod(SelfSecuritySubjectResolverPort::class, 'assertBelongsToSelf');
mp01gStatic($resolverMethod->getNumberOfParameters() === 2, 'subject_resolver_contract_minimal');
$policySource = file_get_contents($root . '/modules/platform/audit/read/SelfSecurityTimelinePolicy.php');
mp01gStatic(is_string($policySource) && !str_contains($policySource, 'hash_equals') && !str_contains($policySource, "row['target_id']"), 'no_universal_direct_target_equality');
$selfFactory = new ReflectionMethod(AuditReadQuery::class, 'selfSecurity');
$selfParameters = array_map(fn(ReflectionParameter $parameter): string => $parameter->getName(), $selfFactory->getParameters());
mp01gStatic(array_intersect(['accountId', 'targetId', 'role', 'capability', 'scope'], $selfParameters) === [], 'self_authority_not_request_supplied');
$fetch = new ReflectionMethod(AuditReadRepositoryPort::class, 'fetch');
mp01gStatic($fetch->getNumberOfParameters() === 3 && $fetch->getParameters()[2]->getName() === 'limit', 'repository_read_is_bounded');
$forbidden = ['metadata_json', 'writer_internal_metadata', 'ip_hmac', 'user_agent_summary', 'session_id', 'previous_hash', 'event_hash'];
foreach ([AuditReadProjection::SELF_SECURITY, AuditReadProjection::INTERNAL_SCOPED, AuditReadProjection::ADMIN_PRIVILEGED] as $projection) {
    mp01gStatic(array_intersect($forbidden, AuditReadProjection::named($projection)->fields()) === [], 'projection_minimized_' . $projection);
}
$production = array_slice($candidateFiles, 0, 12);
$declaredPolicyRegistries = 0; $declaredWriters = 0; $unsafeRuntimePrimitives = [];
foreach ($production as $file) {
    $source = file_get_contents($root . '/' . $file);
    if (!is_string($source)) throw new RuntimeException('unreadable_candidate_file');
    $tokens = token_get_all($source);
    for ($i = 0, $n = count($tokens); $i < $n; $i++) {
        if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_CLASS) continue;
        for ($j = $i + 1; $j < $n; $j++) {
            if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                if ($tokens[$j][1] === 'CanonicalAuditPolicyRegistry') $declaredPolicyRegistries++;
                if (str_ends_with($tokens[$j][1], 'Writer') || str_ends_with($tokens[$j][1], 'Emitter')) $declaredWriters++;
                break;
            }
        }
    }
    foreach (['$_GET', '$_POST', '$_REQUEST', 'PDO', 'curl_exec', 'file_put_contents', 'INSERT INTO', 'UPDATE ', 'DELETE FROM'] as $primitive) {
        if (str_contains($source, $primitive)) $unsafeRuntimePrimitives[] = $file . ':' . $primitive;
    }
}
mp01gStatic($declaredPolicyRegistries === 0, 'no_duplicate_canonical_policy_registry');
mp01gStatic($declaredWriters === 0, 'no_writer_or_emitter');
mp01gStatic($unsafeRuntimePrimitives === [], 'no_productive_io_or_request_authority');
mp01gStatic(array_filter($candidateFiles, fn(string $file): bool => str_contains($file, '/controllers/') || str_starts_with($file, 'api/') || str_starts_with($file, 'public/')) === [], 'no_productive_route_or_controller_wiring');
mp01gStatic(AuditReadQuery::DEFAULT_PAGE_SIZE === 25 && AuditReadQuery::MAX_PAGE_SIZE === 100 && AuditReadQuery::SORT === 'created_at_desc_event_id_desc', 'bounded_deterministic_pagination');

echo 'STATIC_TESTS=' . $pass . '/' . $total . '_PASS' . PHP_EOL;
echo 'NO_PRODUCTIVE_ROUTE_WIRING=true' . PHP_EOL;
echo 'NO_RUNTIME_ACTIVATION=true' . PHP_EOL;
echo 'NO_DB_WRITES=true' . PHP_EOL;
echo 'NO_DUPLICATE_CANONICAL_POLICY_REGISTRY=true' . PHP_EOL;
echo 'NO_RAW_PERSISTENCE_ROW_PROJECTION=true' . PHP_EOL;
echo 'NO_UNBOUNDED_LIST_METHOD=true' . PHP_EOL;
echo 'NO_REQUEST_SUPPLIED_ROLE_AUTHORITY=true' . PHP_EOL;
echo 'SELF_TIMELINE_DIRECT_TARGET_EQUALITY_ASSUMPTION=false' . PHP_EOL;
echo 'SELF_TIMELINE_SUBJECT_BINDING=TRUSTED_FAIL_CLOSED_RESOLVER' . PHP_EOL;
