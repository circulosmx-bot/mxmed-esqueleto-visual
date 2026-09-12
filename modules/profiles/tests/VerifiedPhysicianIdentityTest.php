<?php
declare(strict_types=1);

use Profiles\Controllers\PrivateProfileController;
use Profiles\Repositories\PrivateProfileRepository;
use Profiles\Repositories\VerifiedPhysicianIdentityRepository;
use Profiles\Services\VerifiedPhysicianIdentityService;

require_once __DIR__ . '/../../../api/_lib/db.php';
require_once __DIR__ . '/../controllers/PrivateProfileController.php';

function vid01Assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function vid01Request(string $url, array $headers = [], string $method = 'GET', ?array $payload = null): array
{
    $headerLines = array_merge(['Accept: application/json'], $headers);
    $options = [
        'method' => $method,
        'ignore_errors' => true,
        'timeout' => 10,
        'header' => implode("\r\n", $headerLines),
    ];
    if ($payload !== null) {
        $options['content'] = json_encode($payload, JSON_THROW_ON_ERROR);
        $options['header'] .= "\r\nContent-Type: application/json";
    }
    $body = file_get_contents($url, false, stream_context_create(['http' => $options]));
    $statusLine = $http_response_header[0] ?? '';
    preg_match('/\s(\d{3})\s/', $statusLine, $match);
    return [
        'status' => (int)($match[1] ?? 0),
        'json' => is_string($body) ? json_decode($body, true) : null,
    ];
}

$host = getenv('MXMED_VID01_TEST_HOST') ?: '127.0.0.1';
$port = (int)(getenv('MXMED_VID01_TEST_PORT') ?: 3309);
$user = getenv('MXMED_VID01_TEST_USER') ?: 'root';
$pass = getenv('MXMED_VID01_TEST_PASS') ?: '';
$admin = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$database = 'mxmed_vid01_test_' . getmypid();
$quotedDatabase = '`' . str_replace('`', '``', $database) . '`';
$admin->exec("CREATE DATABASE {$quotedDatabase} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$server = null;
$pipes = [];
$httpRoot = sys_get_temp_dir() . '/mxmed_vid01_http_' . getmypid();

try {
    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $root = realpath(__DIR__ . '/../../..');
    vid01Assert(is_string($root), 'repository root resolves');
    $pdo->exec((string)file_get_contents($root . '/modules/profiles/db/profiles_doctors_schema.sql'));
    $pdo->exec((string)file_get_contents($root . '/modules/identity/db/migrations/2026_07_19_01_create_auth_accounts.sql'));
    $pdo->exec(
        "CREATE TABLE internal_staff (
            account_id VARCHAR(64) NOT NULL PRIMARY KEY,
            governance_class ENUM('DIRECTOR','MASTER_ADMIN','ADVISOR') NOT NULL,
            status ENUM('ACTIVE','SUSPENDED') NOT NULL,
            FOREIGN KEY (account_id) REFERENCES auth_accounts(account_id) ON DELETE RESTRICT ON UPDATE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $migration = (string)file_get_contents($root . '/modules/profiles/db/2026_09_11_create_profiles_verified_identities.sql');
    $pdo->exec($migration);
    $pdo->exec($migration);

    $create = $pdo->query('SHOW CREATE TABLE profiles_verified_identities')->fetch(PDO::FETCH_NUM);
    $createSql = (string)($create[1] ?? '');
    vid01Assert(str_contains($createSql, 'uniq_profiles_verified_identity_doctor'), 'one current identity unique key exists');
    vid01Assert((int)$pdo->query('SELECT COUNT(*) FROM profiles_verified_identities')->fetchColumn() === 0, 'migration contains no backfill or fixture');

    $insertProfile = $pdo->prepare(
        'INSERT INTO profiles_doctors (doctor_id, display_name, profile_status, is_public_candidate)
         VALUES (:doctor_id, :display_name, :profile_status, :is_public_candidate)'
    );
    foreach ([
        ['vid01-luis', 'Luis público previo', 'active', 1],
        ['vid01-no-second', 'Ana pública previa', 'active', 1],
        ['vid01-legacy', 'Dra. María del Carmen López Soto', 'active', 1],
        ['vid01-other', 'Otro médico', 'active', 1],
    ] as [$doctorId, $displayName, $status, $candidate]) {
        $insertProfile->execute([
            'doctor_id' => $doctorId,
            'display_name' => $displayName,
            'profile_status' => $status,
            'is_public_candidate' => $candidate,
        ]);
    }
    $pdo->exec(
        "INSERT INTO auth_accounts (account_id, email_address, email_normalized, status, email_verified_at)
         VALUES ('advisor-vid01', 'advisor@example.test', 'advisor@example.test', 'active', '2026-09-11 10:00:00')"
    );
    $pdo->exec("INSERT INTO internal_staff (account_id, governance_class, status) VALUES ('advisor-vid01', 'ADVISOR', 'ACTIVE')");

    $repository = new VerifiedPhysicianIdentityRepository($pdo);
    $service = new VerifiedPhysicianIdentityService($repository);
    $fixtureService = new VerifiedPhysicianIdentityService($repository, true);

    $created = $service->provisionFromTrustedAuthority([
        'doctor_id' => 'vid01-luis',
        'given_names' => 'Luis Armando',
        'first_surname' => 'Reynoso',
        'second_surname' => 'Femat',
    ], [
        'source_type' => 'admission_approved',
        'source_reference' => 'admission:test:vid01-luis',
        'verified_at' => '2026-09-11 10:15:00',
        'verified_by_account_id' => 'advisor-vid01',
    ]);
    vid01Assert($created['given_names'] === 'Luis Armando', 'structured given names preserved');
    vid01Assert($created['first_surname'] === 'Reynoso', 'structured first surname preserved');
    vid01Assert($created['second_surname'] === 'Femat', 'structured second surname preserved');
    vid01Assert($created['source_type'] === 'admission_approved', 'admission provenance preserved');
    vid01Assert($created['verified_by_account_id'] === 'advisor-vid01', 'canonical approver preserved internally');

    $fixtureService->provisionFromTrustedAuthority([
        'doctor_id' => 'vid01-no-second',
        'given_names' => 'Ana Sofía',
        'first_surname' => 'Torres',
        'second_surname' => null,
    ], [
        'source_type' => 'synthetic_test',
        'source_reference' => 'fixture:vid01:no-second',
        'verified_at' => '2026-09-11 10:20:00.123456',
    ]);
    vid01Assert($repository->findByDoctorId('vid01-no-second')['second_surname'] === null, 'null second surname supported');

    try {
        $service->provisionFromTrustedAuthority([
            'doctor_id' => 'vid01-other',
            'given_names' => 'Fixture',
            'first_surname' => 'Denied',
        ], [
            'source_type' => 'synthetic_test',
            'source_reference' => 'fixture:forbidden',
            'verified_at' => '2026-09-11 10:25:00',
        ]);
        throw new RuntimeException('productive service accepted synthetic fixture');
    } catch (RuntimeException $e) {
        vid01Assert($e->getMessage() === 'verified_identity_synthetic_source_forbidden', 'synthetic fixtures require explicit test mode');
    }

    try {
        $service->provisionFromTrustedAuthority([
            'doctor_id' => 'vid01-luis',
            'given_names' => 'Luis',
            'first_surname' => 'Reynoso',
        ], [
            'source_type' => 'admission_approved',
            'source_reference' => 'admission:test:duplicate',
            'verified_at' => '2026-09-11 10:30:00',
            'verified_by_account_id' => 'advisor-vid01',
        ]);
        throw new RuntimeException('duplicate identity was accepted');
    } catch (RuntimeException $e) {
        vid01Assert($e->getMessage() === 'verified_identity_already_exists', 'duplicate current identity rejected');
    }

    $private = new PrivateProfileController(new PrivateProfileRepository($pdo), $service);
    $own = $private->showByDoctorId('vid01-luis', 'strict');
    vid01Assert(($own['ok'] ?? false) === true, 'private profile read succeeds');
    vid01Assert(($own['data']['verified_identity']['full_name'] ?? null) === 'Luis Armando Reynoso Femat', 'derived full verified name is exact');
    vid01Assert(!array_key_exists('verified_by_account_id', $own['data']['verified_identity']), 'internal approver is not exposed to physician');
    vid01Assert(!array_key_exists('source_reference', $own['data']['verified_identity']), 'internal source reference is not exposed to physician');
    vid01Assert(($own['data']['public_name_policy']['verified_identity_available'] ?? false) === true, 'private read model exposes verified-name policy');
    vid01Assert(($own['data']['public_name_policy']['allowed_given_name_presentations'] ?? null) === ['Luis', 'Armando', 'Luis Armando'], 'private read model exposes allowed given-name presentations');
    vid01Assert(($own['data']['public_name_policy']['current_display_name_policy_status'] ?? null) === 'LEGACY_NONCONFORMING', 'existing nonconforming display name remains visible and is reported');

    $legacyBefore = (string)$pdo->query("SELECT display_name FROM profiles_doctors WHERE doctor_id = 'vid01-legacy'")->fetchColumn();
    $legacy = $private->showByDoctorId('vid01-legacy', 'strict');
    vid01Assert(
        array_key_exists('verified_identity', $legacy['data']) && $legacy['data']['verified_identity'] === null,
        'legacy doctor without verified identity is supported'
    );
    vid01Assert(($legacy['data']['public_name_policy']['verified_identity_available'] ?? true) === false, 'legacy private read model reports no verified identity');
    $legacyAfter = (string)$pdo->query("SELECT display_name FROM profiles_doctors WHERE doctor_id = 'vid01-legacy'")->fetchColumn();
    vid01Assert($legacyAfter === $legacyBefore, 'legacy display name was neither split nor modified');
    $legacyUpdate = $private->patchByDoctorId('vid01-legacy', [
        'display_name' => 'Nombre público legado libre',
        'prefix' => 'Dra.',
    ], 'strict');
    vid01Assert(($legacyUpdate['ok'] ?? false) === true, 'legacy physician retains existing display-name update behavior');
    vid01Assert(($legacyUpdate['data']['identity_public']['display_name'] ?? null) === 'Nombre público legado libre', 'legacy arbitrary display name remains supported');

    $blockedWrite = $private->patchByDoctorId('vid01-luis', [
        'verified_identity' => ['given_names' => 'Fernando'],
        'given_names' => 'Fernando',
        'first_surname' => 'Ramírez',
        'second_surname' => null,
    ], 'strict');
    vid01Assert(($blockedWrite['ok'] ?? false) === true, 'blocked verified fields preserve existing private PATCH compatibility');
    vid01Assert(($blockedWrite['meta']['no_editable_fields_applied'] ?? false) === true, 'verified fields are not editable');
    vid01Assert($service->readForPhysician('vid01-luis')['full_name'] === 'Luis Armando Reynoso Femat', 'physician PATCH cannot mutate verified identity');

    $displayBefore = (string)$pdo->query("SELECT display_name FROM profiles_doctors WHERE doctor_id = 'vid01-luis'")->fetchColumn();
    $patched = $private->patchByDoctorId('vid01-luis', ['bio_short' => 'Perfil de prueba'], 'strict');
    vid01Assert(($patched['ok'] ?? false) === true, 'current identity PATCH still succeeds');
    $displayAfter = (string)$pdo->query("SELECT display_name FROM profiles_doctors WHERE doctor_id = 'vid01-luis'")->fetchColumn();
    vid01Assert($displayAfter === $displayBefore, 'current public display name remains unchanged');

    mkdir($httpRoot . '/api/profiles', 0700, true);
    mkdir($httpRoot . '/api/_lib', 0700, true);
    vid01Assert(copy($root . '/api/profiles/index.php', $httpRoot . '/api/profiles/index.php'), 'isolated API entry point copied');
    vid01Assert(copy($root . '/api/_lib/db.php', $httpRoot . '/api/_lib/db.php'), 'isolated DB composition copied');
    vid01Assert(symlink($root . '/modules', $httpRoot . '/modules'), 'isolated runtime module link created');

    $httpPort = 19000 + (getmypid() % 1000);
    $descriptor = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $environment = array_merge($_ENV, [
        'MXMED_DB_HOST' => $host,
        'MXMED_DB_PORT' => (string)$port,
        'MXMED_DB_NAME' => $database,
        'MXMED_DB_USER' => $user,
        'MXMED_DB_PASS' => $pass,
        'MXMED_PROFILES_PRIVATE_AUTH_REQUIRED' => '1',
    ]);
    $command = [PHP_BINARY, '-S', '127.0.0.1:' . $httpPort, '-t', $httpRoot];
    $server = proc_open($command, $descriptor, $pipes, $httpRoot, $environment);
    vid01Assert(is_resource($server), 'private API test server starts');
    $ready = false;
    for ($attempt = 0; $attempt < 50; $attempt++) {
        $socket = @fsockopen('127.0.0.1', $httpPort, $errno, $error, 0.1);
        if (is_resource($socket)) {
            fclose($socket);
            $ready = true;
            break;
        }
        usleep(100000);
    }
    vid01Assert($ready, 'private API test server becomes ready');
    $base = 'http://127.0.0.1:' . $httpPort . '/api/profiles/index.php';
    $ownHttp = vid01Request($base . '/private/doctor/vid01-luis', [
        'X-User-Id: account-vid01',
        'X-Doctor-Id: vid01-luis',
    ]);
    vid01Assert($ownHttp['status'] === 200, 'physician can read own private verified identity');
    vid01Assert(($ownHttp['json']['data']['verified_identity']['full_name'] ?? null) === 'Luis Armando Reynoso Femat', 'private API exposes own verified identity');
    vid01Assert(($ownHttp['json']['data']['public_name_policy']['current_display_name_policy_status'] ?? null) === 'LEGACY_NONCONFORMING', 'private API reports nonconforming current legacy name without rewriting it');
    $writeHttp = vid01Request($base . '/private/doctor/vid01-luis', [
        'X-User-Id: account-vid01',
        'X-Doctor-Id: vid01-luis',
    ], 'PATCH', [
        'verified_identity' => ['given_names' => 'Nombre alterado'],
        'given_names' => 'Nombre alterado',
    ]);
    vid01Assert($writeHttp['status'] === 200, 'private profile PATCH safely ignores verified identity input');
    vid01Assert(($writeHttp['json']['meta']['no_editable_fields_applied'] ?? false) === true, 'verified identity has no physician write path');
    vid01Assert(($writeHttp['json']['data']['verified_identity']['full_name'] ?? null) === 'Luis Armando Reynoso Femat', 'private API PATCH cannot mutate verified identity');

    $beforeBypass = $pdo->query("SELECT display_name, bio_short, prefix FROM profiles_doctors WHERE doctor_id = 'vid01-luis'")->fetch(PDO::FETCH_ASSOC);
    $bypassHttp = vid01Request($base . '/private/doctor/vid01-luis', [
        'X-User-Id: account-vid01',
        'X-Doctor-Id: vid01-luis',
    ], 'PATCH', [
        'display_name' => 'Fernando Reynoso',
        'bio_short' => 'Este campo no debe guardarse',
        'prefix' => 'Dr.',
    ]);
    vid01Assert($bypassHttp['status'] === 422, 'direct HTTP bypass with invented given name is rejected');
    vid01Assert(($bypassHttp['json']['error'] ?? null) === 'invalid_public_display_name', 'stable invalid public display-name error returned');
    $afterBypass = $pdo->query("SELECT display_name, bio_short, prefix FROM profiles_doctors WHERE doctor_id = 'vid01-luis'")->fetch(PDO::FETCH_ASSOC);
    vid01Assert($afterBypass === $beforeBypass, 'invalid grouped PATCH persists no sibling public-identity fields');

    $validNameHttp = vid01Request($base . '/private/doctor/vid01-luis', [
        'X-User-Id: account-vid01',
        'X-Doctor-Id: vid01-luis',
    ], 'PATCH', [
        'display_name' => "  lUiS\u{00A0}rEyNoSo  ",
        'professional_designation' => 'Endocrinólogo',
        'prefix' => 'Dr.',
    ]);
    vid01Assert($validNameHttp['status'] === 200, 'allowed verified-name subset saves through grouped private PATCH');
    vid01Assert(($validNameHttp['json']['data']['identity_public']['display_name'] ?? null) === 'Luis Reynoso', 'HTTP PATCH saves canonical verified spelling');
    vid01Assert(($validNameHttp['json']['data']['identity_public']['professional_designation'] ?? null) === 'Endocrinólogo', 'valid grouped PATCH preserves sibling update behavior');
    vid01Assert(($validNameHttp['json']['data']['identity_public']['prefix'] ?? null) === 'Dr.', 'prefix remains a separate public-presentation field');
    vid01Assert(($validNameHttp['json']['data']['public_name_policy']['current_display_name_policy_status'] ?? null) === 'VALID', 'read model reports valid status after compliant change');
    $missingScopeHttp = vid01Request($base . '/private/doctor/vid01-luis', [
        'X-User-Id: account-vid01-other',
    ]);
    vid01Assert($missingScopeHttp['status'] === 403, 'authenticated request without doctor scope cannot read verified identity');
    $crossHttp = vid01Request($base . '/private/doctor/vid01-luis', [
        'X-User-Id: account-vid01-other',
        'X-Doctor-Id: vid01-other',
    ]);
    vid01Assert($crossHttp['status'] === 403, 'other physician cannot read verified identity');
    vid01Assert(($crossHttp['json']['data'] ?? null) === null, 'cross-doctor response exposes no identity');
    $publicHttp = vid01Request($base . '/public/doctor/vid01-luis');
    vid01Assert($publicHttp['status'] === 200, 'existing public profile endpoint still succeeds');
    vid01Assert(($publicHttp['json']['data']['identity']['display_name'] ?? null) === 'Luis Reynoso', 'public profile still uses the saved display_name');
    vid01Assert(!str_contains(json_encode($publicHttp['json'], JSON_THROW_ON_ERROR), 'verified_identity'), 'public API does not expose verified identity');
    vid01Assert(!str_contains(json_encode($publicHttp['json'], JSON_THROW_ON_ERROR), 'public_name_policy'), 'public API does not expose private name policy');

    foreach (['profiles/doctor.php', 'profiles/listing.php', 'modules/profiles/repositories/PublicProfileRepository.php', 'modules/profiles/repositories/PublicDiscoveryRepository.php'] as $publicFile) {
        $source = (string)file_get_contents($root . '/' . $publicFile);
        vid01Assert(!str_contains($source, 'profiles_verified_identities'), 'public source remains independent: ' . $publicFile);
    }

    echo "VerifiedPhysicianIdentityTest PASS (VID01 authority plus VID02 private policy, direct HTTP rejection, grouped atomicity, legacy compatibility, public isolation)\n";
} finally {
    if (is_resource($server)) {
        proc_terminate($server);
    }
    foreach ($pipes as $pipe) {
        if (is_resource($pipe)) {
            fclose($pipe);
        }
    }
    if (is_resource($server)) {
        proc_close($server);
    }
    if (is_link($httpRoot . '/modules')) {
        unlink($httpRoot . '/modules');
    }
    foreach (['/api/profiles/index.php', '/api/_lib/db.php'] as $file) {
        if (is_file($httpRoot . $file)) {
            unlink($httpRoot . $file);
        }
    }
    foreach (['/api/profiles', '/api/_lib', '/api', ''] as $directory) {
        if (is_dir($httpRoot . $directory)) {
            rmdir($httpRoot . $directory);
        }
    }
    $admin->exec("DROP DATABASE IF EXISTS {$quotedDatabase}");
}
