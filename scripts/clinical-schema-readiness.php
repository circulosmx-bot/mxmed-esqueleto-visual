<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/api/_lib/db.php';
require_once dirname(__DIR__) . '/api/_lib/clinical_schema_readiness.php';

$mode = strtolower(trim((string)($argv[1] ?? '')));
$target = trim((string)getenv('MXMED_SCHEMA_READINESS_DB'));
if (!in_array($mode, ['prestate', 'poststate'], true) || $target === '') {
    fwrite(STDERR, "Usage: MXMED_SCHEMA_READINESS_DB=<explicit_database> php scripts/clinical-schema-readiness.php prestate|poststate\n");
    exit(64);
}
if (!preg_match('/^[A-Za-z0-9_]+$/', $target)) {
    fwrite(STDERR, "Invalid explicit database name.\n");
    exit(64);
}

$cfg = mxmed_load_db_config();
$mysql = (array)($cfg['mysql'] ?? []);
$host = (string)($mysql['host'] ?? '127.0.0.1');
$port = (int)($mysql['port'] ?? 3306);
$user = (string)($mysql['user'] ?? '');
$pass = (string)($mysql['pass'] ?? '');
if ($user === '') {
    fwrite(STDERR, "Explicit database credentials are required through existing MXMED_DB_* or config authority.\n");
    exit(64);
}

try {
    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$target};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $result = (new ClinicalSchemaReadinessComparator($pdo, $target))->compare($mode);
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    exit(($result['ready'] ?? false) === true ? 0 : 2);
} catch (Throwable $e) {
    fwrite(STDERR, json_encode(['ready'=>false,'error'=>'SCHEMA_READINESS_EXECUTION_FAILED','message'=>$e->getMessage()], JSON_UNESCAPED_SLASHES) . "\n");
    exit(3);
}
