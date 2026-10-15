<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli'
    || preg_match('/^clin_auth02a_qa_[a-f0-9]{12}$/D', (string)getenv('MXMED_DB_NAME')) !== 1) {
    exit(2);
}

$method = strtoupper((string)($argv[1] ?? 'GET'));
$path = (string)($argv[2] ?? '/');
$authenticated = ($argv[3] ?? '') === 'authenticated';
$origin = (string)($argv[4] ?? '');
$root = dirname(__DIR__, 3);

session_id('clin-auth02a-' . bin2hex(random_bytes(8)));
session_start();
if ($authenticated) {
    $_SESSION['doctor_id'] = 'd_a';
    $_SESSION['user_id'] = 'u_a';
}
$_SERVER['REQUEST_METHOD'] = $method;
$_SERVER['REQUEST_URI'] = '/api/clinical/index.php' . $path;
$_SERVER['SCRIPT_NAME'] = '/api/clinical/index.php';
$_SERVER['HTTP_HOST'] = 'localhost:8443';
$_SERVER['HTTP_ACCEPT'] = 'application/json';
if ($origin !== '') $_SERVER['HTTP_ORIGIN'] = $origin;

ob_start();
require $root . '/api/clinical/index.php';
$raw = (string)ob_get_clean();
$decoded = json_decode($raw, true);
echo json_encode([
    'status' => http_response_code() ?: 200,
    'body' => is_array($decoded) ? $decoded : ['raw' => $raw],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
