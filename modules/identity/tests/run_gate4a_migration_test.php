<?php
declare(strict_types=1);

require_once __DIR__ . '/IdentityModelContractTest.php';
require_once __DIR__ . '/IdentityPersistenceTest.php';

$host = getenv('MXMED_GATE4A_TEST_HOST') ?: '127.0.0.1';
$port = (int)(getenv('MXMED_GATE4A_TEST_PORT') ?: 3306);
$user = getenv('MXMED_GATE4A_TEST_USER') ?: 'root';
$pass = getenv('MXMED_GATE4A_TEST_PASS') ?: '';
$admin = new PDO("mysql:host={$host};port={$port}", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$database = 'mxmed_gate4a_test_' . getmypid();
$quotedDatabase = '`' . str_replace('`', '``', $database) . '`';
$admin->exec("CREATE DATABASE {$quotedDatabase} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

try {
    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec("CREATE TABLE profiles_doctors (doctor_id VARCHAR(64) NOT NULL PRIMARY KEY) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE medical_groups (group_id VARCHAR(64) NOT NULL PRIMARY KEY) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("INSERT INTO profiles_doctors (doctor_id) VALUES ('doctor_gate4a_01')");
    $pdo->exec("INSERT INTO medical_groups (group_id) VALUES ('group_gate4a_01')");

    $migrationDir = realpath(__DIR__ . '/../db/migrations');
    $forward = [
        $migrationDir . '/2026_07_19_01_create_auth_accounts.sql',
        $migrationDir . '/2026_07_19_02_create_auth_account_consents.sql',
        $migrationDir . '/2026_07_19_03_create_auth_account_memberships.sql',
    ];
    $rollback = [
        $migrationDir . '/2026_07_19_03_rollback_auth_account_memberships.sql',
        $migrationDir . '/2026_07_19_02_rollback_auth_account_consents.sql',
        $migrationDir . '/2026_07_19_01_rollback_auth_accounts.sql',
    ];
    foreach ($forward as $file) $pdo->exec(file_get_contents($file));
    $tables = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name LIKE 'auth_%' ORDER BY table_name")->fetchAll(PDO::FETCH_COLUMN);
    if ($tables !== ['auth_account_consents', 'auth_account_memberships', 'auth_accounts']) throw new RuntimeException('forward did not create expected tables');
    $foreignKeys = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.referential_constraints WHERE constraint_schema = DATABASE() AND table_name IN ('auth_account_consents','auth_account_memberships')")->fetchColumn();
    if ($foreignKeys < 3) throw new RuntimeException('expected canonical foreign keys are missing');

    runIdentityPersistenceTest($pdo);
    foreach ($rollback as $file) $pdo->exec(file_get_contents($file));
    $remaining = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name LIKE 'auth_%'")->fetchColumn();
    if ($remaining !== 0) throw new RuntimeException('rollback did not remove Gate 4A tables');
    if ((int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ('profiles_doctors','medical_groups')")->fetchColumn() !== 2) throw new RuntimeException('rollback altered parent authorities');

    foreach ($forward as $file) $pdo->exec(file_get_contents($file));
    $secondForward = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name LIKE 'auth_%'")->fetchColumn();
    if ($secondForward !== 3) throw new RuntimeException('second forward did not restore Gate 4A tables');
    echo "Gate4A migration forward/rollback/second-forward PASS\n";
} finally {
    $admin->exec("DROP DATABASE IF EXISTS {$quotedDatabase}");
}
