<?php
declare(strict_types=1);

foreach (glob(__DIR__ . '/../contracts/*.php') as $file) require_once $file;
foreach (glob(__DIR__ . '/../services/*.php') as $file) require_once $file;

use Platform\Contracts\FeatureFlags;
use Platform\Contracts\LegacyContainmentStatus;
use Platform\Contracts\PlatformFoundationReadinessDecision;
use Platform\Services\LegacyContainmentEvaluator;
use Platform\Services\LegacyContainmentRegistry;
use Platform\Services\PlatformFoundationReadinessEvaluator;
use Platform\Services\PrivilegedAccessActivationGate;

function gate6fAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function gate6fRead(string $path): string
{
    $source = file_get_contents($path);
    gate6fAssert(is_string($source), 'readable fixture required: ' . $path);
    return $source;
}

function gate6fThrows(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (InvalidArgumentException|RuntimeException) {
        return;
    }
    throw new RuntimeException($message);
}

$root = dirname(__DIR__, 3);
$manifestPath = __DIR__ . '/../config/legacy-containment-pg08-v1.json';
$reactivationPath = __DIR__ . '/../config/consultorio-secondary-delete-reactivation-v1.json';
$manifestSource = gate6fRead($manifestPath);
$manifest = json_decode($manifestSource, true, 512, JSON_THROW_ON_ERROR);
gate6fAssert(is_array($manifest), 'manifest JSON object required');
$registry = LegacyContainmentRegistry::fromJson($manifestSource);
gate6fAssert($registry->schemaVersion() === '1', 'manifest schema version stable');
gate6fAssert($registry->authoritativeGate() === '6F', 'manifest authoritative Gate 6F');
gate6fAssert(count($registry->records()) === 7, 'manifest surfaces exact and deterministic');
$invalidStatusManifest = $manifest;
$invalidStatusManifest['surfaces'][0]['status'] = 'unknown_status';
gate6fThrows(fn() => new LegacyContainmentRegistry($invalidStatusManifest), 'unknown status rejected');
$duplicateSurfaceManifest = $manifest;
$duplicateSurfaceManifest['surfaces'][] = $duplicateSurfaceManifest['surfaces'][0];
gate6fThrows(fn() => new LegacyContainmentRegistry($duplicateSurfaceManifest), 'duplicate surface rejected');
$emptySurfaceManifest = $manifest;
$emptySurfaceManifest['surfaces'][0]['surface'] = '';
gate6fThrows(fn() => new LegacyContainmentRegistry($emptySurfaceManifest), 'empty surface rejected');

$surfaceNames = [];
foreach ($manifest['surfaces'] as $surface) {
    gate6fAssert(is_array($surface), 'surface record object required');
    $surfaceName = (string)($surface['surface'] ?? '');
    gate6fAssert($surfaceName !== '' && !in_array($surfaceName, $surfaceNames, true), 'surfaces unique and non-empty');
    $surfaceNames[] = $surfaceName;
    gate6fAssert(in_array((string)($surface['status'] ?? ''), LegacyContainmentStatus::all(), true), 'surface status valid');
    gate6fAssert(in_array((string)($surface['risk'] ?? ''), ['R0', 'R1', 'R2', 'R3'], true), 'surface risk valid');
}
gate6fAssert($registry->find('api/verify-password.php')?->status() === LegacyContainmentStatus::RETIRED_FAIL_CLOSED, 'verify-password retired fail closed');
gate6fAssert($registry->find('api/verify-sms.php')?->status() === LegacyContainmentStatus::RETIRED_FAIL_CLOSED, 'verify-sms retired fail closed');
gate6fAssert($registry->find('api/catalog/index.php')?->status() === LegacyContainmentStatus::REMEDIATED_READ_PURITY, 'catalog read purity registered');
gate6fAssert($registry->find('profiles_transitional_open_family')?->status() === LegacyContainmentStatus::CONTAINED_DEPLOYMENT_BLOCKER, 'Profiles blocker registered');
gate6fAssert($registry->find('agenda_client_authoritative_role_family')?->status() === LegacyContainmentStatus::CONTAINED_DEPLOYMENT_BLOCKER, 'Agenda blocker registered');
gate6fAssert($registry->find('runtime_schema_php_family')?->status() === LegacyContainmentStatus::CONTAINED_DEPLOYMENT_BLOCKER, 'schema blocker registered');
gate6fAssert($registry->find('consultorio_secondary_delete_legacy_verification')?->status() === LegacyContainmentStatus::CONTAINED_DEPLOYMENT_BLOCKER, 'consultorio blocker registered');

$evaluator = new LegacyContainmentEvaluator();
gate6fAssert($evaluator->evaluate(null, 'api/catalog/index.php')->contained() === false, 'missing manifest blocks');
gate6fAssert($evaluator->evaluate($registry, 'unknown/surface')->status() === LegacyContainmentStatus::UNRESOLVED_STOP_REQUIRED, 'missing record blocks');
gate6fAssert($evaluator->evaluate($registry, 'api/catalog/index.php')->resolved(), 'catalog is resolved');
gate6fAssert($evaluator->evaluate($registry, 'profiles_transitional_open_family')->contained() && !$evaluator->evaluate($registry, 'profiles_transitional_open_family')->resolved(), 'deferred blocker is contained but unresolved');
gate6fThrows(fn() => LegacyContainmentRegistry::fromJson('{invalid'), 'invalid JSON blocks');
$readiness = (new PlatformFoundationReadinessEvaluator())->evaluate($registry, [
    '6A' => true, '6B' => true, '6C' => true, '6D' => true, '6E' => true,
    '6F' => 'PASS_GATE_6F_LEGACY_CONTAINMENT_FOUNDATION_INTEGRATION_READY',
]);
gate6fAssert($readiness->ready() === false, 'readiness remains false');
gate6fAssert($readiness->deploymentDecision() === PlatformFoundationReadinessDecision::NO_GO_LEGACY_BLOCKERS_PRESENT, 'readiness is NO_GO_LEGACY_BLOCKERS_PRESENT');
gate6fAssert(in_array('profiles_transitional_open_family:contained_deployment_blocker', $readiness->blockers(), true), 'Profiles remains readiness blocker');
gate6fAssert(in_array('agenda_client_authoritative_role_family:contained_deployment_blocker', $readiness->blockers(), true), 'Agenda remains readiness blocker');
gate6fAssert(in_array('runtime_schema_php_family:contained_deployment_blocker', $readiness->blockers(), true), 'schema remains readiness blocker');

$endpointFiles = [$root . '/api/verify-password.php', $root . '/api/verify-sms.php'];
foreach ($endpointFiles as $endpoint) {
    $source = gate6fRead($endpoint);
    gate6fAssert(str_contains($source, 'http_response_code(410)'), 'legacy endpoint returns HTTP 410');
    gate6fAssert(str_contains($source, 'legacy_endpoint_retired'), 'legacy endpoint retirement code stable');
    gate6fAssert(str_contains($source, 'LEGACY-IDENTITY-RETIREMENT-1'), 'legacy endpoint contract version stable');
    gate6fAssert(!preg_match('/\$_(?:GET|POST)|php:\/\/input|password_verify|session_start|session_id|\$_SESSION|setcookie|\$_COOKIE|random_bytes|openssl|error_log|Access-Control-Allow-Origin|Origin/i', $source), 'retired endpoint has no input, credential, session, cookie, token, log or wildcard CORS primitive');
}

$catalogSource = gate6fRead($root . '/api/catalog/index.php');
foreach ([
    '/\bCREATE\s+TABLE\b/i', '/\bALTER\s+TABLE\b/i', '/\bDROP\s+TABLE\b/i',
    '/\bINSERT\s+INTO\b/i', '/\bUPDATE\s+/i', '/\bDELETE\s+FROM\b/i',
    '/\bREPLACE\s+/i', '/\bseed\b/i', '/\bbootstrap\b/i',
    '/ensureTable|ensureSchema/i', '/\bTRUNCATE\b/i',
] as $pattern) {
    gate6fAssert(preg_match($pattern, $catalogSource) !== 1, 'catalog GET has no write/schema/bootstrap primitive: ' . $pattern);
}
gate6fAssert(str_contains($catalogSource, 'catalog_table_is_missing'), 'catalog missing table detection is scoped');
gate6fAssert(str_contains($catalogSource, 'catalog_not_initialized'), 'catalog missing table contract stable');
gate6fAssert(str_contains($catalogSource, '], 503)'), 'catalog missing table returns 503');
gate6fAssert(preg_match('/SELECT\s+cp,\s+colonia,\s+municipio,\s+estado/i', $catalogSource) === 1, 'catalog GET remains a SELECT');

$ddl = gate6fRead($root . '/modules/catalog/db/2026_07_21_01_create_catalog_cp_colonias.sql');
$seed = gate6fRead($root . '/modules/catalog/db/2026_07_21_02_seed_catalog_cp_colonias.sql');
gate6fAssert(str_contains($ddl, 'CREATE TABLE IF NOT EXISTS'), 'catalog DDL prepared');
gate6fAssert(str_contains($ddl, 'uq_catalog_cp_colonia'), 'catalog unique index preserved');
gate6fAssert(str_contains($seed, 'ON DUPLICATE KEY UPDATE'), 'catalog seed idempotent');
gate6fAssert(str_contains($seed, "'20230', 'Colonia 1'") && str_contains($seed, "'20230', 'Colonia 2'") && str_contains($seed, "'20230', 'Colonia 3'"), 'catalog seed rows preserved');
gate6fAssert(!preg_match('/DROP\s+TABLE|TRUNCATE\s+TABLE|mxmed_pdo|PDO|->exec\s*\(/i', $ddl . "\n" . $seed), 'prepared SQL has no destructive or runtime execution hook');

$reactivation = json_decode(gate6fRead($reactivationPath), true, 512, JSON_THROW_ON_ERROR);
gate6fAssert(is_array($reactivation), 'reactivation contract JSON valid');
gate6fAssert($reactivation['capability_id'] === 'consultorio_secondary_delete', 'capability_id exact');
gate6fAssert($reactivation['current_state'] === 'temporarily_disabled_pending_secure_reauthentication', 'temporary current state exact');
gate6fAssert($reactivation['permanent_removal'] === false, 'permanent removal false');
gate6fAssert($reactivation['reactivation_required'] === true, 'reactivation required');
gate6fAssert($reactivation['ui_action_preserved'] === true, 'UI action preserved');
gate6fAssert($reactivation['implementation_present'] === false, 'future implementation absent');
gate6fAssert($reactivation['required_controls'] !== [] && $reactivation['forbidden_shortcuts'] !== [], 'reactivation controls and shortcuts are explicit');
gate6fAssert(in_array('frontend_only_authorization', $reactivation['forbidden_shortcuts'], true), 'frontend-only authorization forbidden');

$multisede = gate6fRead($root . '/assets/js/perfil/consultorio/multisede.js');
$deleteStart = strpos($multisede, 'modalConsulDelYes');
$deleteEnd = strpos($multisede, 'function nextConsultorioIndex', $deleteStart === false ? 0 : $deleteStart);
gate6fAssert($deleteStart !== false && $deleteEnd !== false, 'consultorio delete flow trace boundaries present');
$deleteFlow = substr($multisede, $deleteStart, $deleteEnd - $deleteStart);
gate6fAssert(!preg_match('/fetch\s*\(|verify-password|verify-sms|password\.trim|code\.trim|\.remove\s*\(|queueSave|initAutosave|localStorage|sessionStorage/i', $deleteFlow), 'blocked delete flow has no endpoint, credential fallback, DOM removal or persistence');
gate6fAssert(str_contains($multisede, 'CONSULTORIO_SECONDARY_DELETE_CAPABILITY') && str_contains($multisede, 'CONSULTORIO_SECONDARY_DELETE_STATE'), 'UI containment capability and state constants preserved');
gate6fAssert(str_contains($multisede, 'data-target-n'), 'delete target identifier preserved');

foreach ([$root . '/index.html', $root . '/docs/index.html'] as $htmlPath) {
    $html = gate6fRead($htmlPath);
    gate6fAssert(str_contains($html, 'id="modalConsulDel"'), 'modalConsulDel preserved');
    gate6fAssert(str_contains($html, 'id="modalConsulDelYes"'), 'modalConsulDelYes preserved');
    gate6fAssert(str_contains($html, 'Eliminar consultorio'), 'delete action semantic text preserved');
    gate6fAssert(str_contains($html, 'Por seguridad, la eliminación de consultorios está temporalmente deshabilitada. Tus demás sedes y configuraciones permanecen disponibles.'), 'approved temporary message present');
    gate6fAssert(str_contains($html, '>Entendido</button>'), 'temporary action acknowledgement preserved');
    gate6fAssert(!preg_match('/del-auth|del-code|del-pass|Código SMS|Ingresa tu Contraseña/i', $html), 'fake password and SMS selection removed from UI');
}

$runtimeFiles = [];
foreach ([$root . '/api', $root . '/public'] as $directory) {
    if (!is_dir($directory)) continue;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'php') $runtimeFiles[] = $file->getPathname();
    }
}
$runtimeSource = '';
foreach ($runtimeFiles as $runtimeFile) $runtimeSource .= "\n" . gate6fRead($runtimeFile);
gate6fAssert(!preg_match('/LegacyContainmentRegistry|LegacyContainmentEvaluator|PlatformFoundationReadinessEvaluator|PrivilegedAccessActivationGate/i', $runtimeSource), 'zero Platform runtime wiring');
gate6fAssert(!preg_match('/PdoAuditTrailAdapter|AuditTrailPort|AuditEventRepository/i', $runtimeSource), 'zero audit runtime wiring');
gate6fAssert(!preg_match('/2026_07_21_0[12]_create_catalog|2026_07_21_0[12]_seed_catalog|catalog_cp_colonias\.sql/i', $catalogSource), 'catalog SQL is not runtime-executed');

$schemaFiles = [];
$schemaIterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/api', FilesystemIterator::SKIP_DOTS));
foreach ($schemaIterator as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') continue;
    $source = gate6fRead($file->getPathname());
    if (preg_match('/CREATE\s+TABLE|ALTER\s+TABLE|DROP\s+TABLE|ensureTable|ensureSchema/i', $source) === 1) $schemaFiles[] = str_replace($root . '/', '', $file->getPathname());
}
foreach (['agenda', 'clinical', 'identity', 'patients', 'subscriptions'] as $module) {
    $moduleRoot = $root . '/modules/' . $module;
    if (!is_dir($moduleRoot)) continue;
    $schemaIterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($moduleRoot, FilesystemIterator::SKIP_DOTS));
    foreach ($schemaIterator as $file) {
        if (!$file->isFile() || strtolower($file->getExtension()) !== 'php' || str_contains($file->getPathname(), DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR)) continue;
        $source = gate6fRead($file->getPathname());
        if (preg_match('/CREATE\s+TABLE|ALTER\s+TABLE|DROP\s+TABLE|ensureTable|ensureSchema/i', $source) === 1) $schemaFiles[] = str_replace($root . '/', '', $file->getPathname());
    }
}
sort($schemaFiles, SORT_STRING);
gate6fAssert(count($schemaFiles) === 21, 'runtime schema debt baseline remains exactly 21 files');

gate6fAssert(FeatureFlags::defaults()[FeatureFlags::SUPPORT_ASSISTED_SESSION_ENABLED] === false, 'support-assisted remains disabled');
gate6fAssert(FeatureFlags::defaults()[FeatureFlags::BREAK_GLASS_ENABLED] === false, 'break-glass remains disabled');
$activationGate = new PrivilegedAccessActivationGate();
gate6fAssert($activationGate->mayActivate('support_assisted', [FeatureFlags::SUPPORT_ASSISTED_SESSION_ENABLED => true]) === false, 'support activation hard-stop preserved');
gate6fAssert($activationGate->mayActivate('break_glass', [FeatureFlags::BREAK_GLASS_ENABLED => true]) === false, 'break-glass activation hard-stop preserved');

echo "Gate6FLegacyContainmentIntegrationTest PASS\n";
