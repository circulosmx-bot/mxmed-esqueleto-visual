<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$cp = isset($_GET['cp']) ? preg_replace('/\D/', '', $_GET['cp']) : '';
if (strlen($cp) !== 5) {
  http_response_code(400);
  echo json_encode(['error' => 'invalid_cp']);
  exit;
}

// Carga configuración
$cfgFile = __DIR__ . '/sepomex-db.config.php';
if (!is_file($cfgFile)) {
  http_response_code(503);
  echo json_encode(['error' => 'not_configured', 'hint' => 'copy sepomex-db.config.sample.php to sepomex-db.config.php']);
  exit;
}
$cfg = include $cfgFile;
$driver = $cfg['driver'] ?? 'mysql';

try {
  if ($driver === 'sqlite') {
    $path = $cfg['sqlite']['path'];
    $pdo = new PDO('sqlite:' . $path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $sql = 'SELECT d_asenta, d_mnpio, d_estado FROM sepomex WHERE d_codigo = :cp ORDER BY d_asenta';
  } else {
    $h = $cfg['mysql']['host']; $p = (int)$cfg['mysql']['port']; $db = $cfg['mysql']['dbname'];
    $u = $cfg['mysql']['user']; $pw = $cfg['mysql']['pass']; $ch = $cfg['mysql']['charset'] ?? 'utf8mb4';
    $dsn = "mysql:host={$h};port={$p};dbname={$db};charset={$ch}";
    $pdo = new PDO($dsn, $u, $pw, [ PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION ]);
    $sql = 'SELECT d_asenta, d_mnpio, d_estado FROM sepomex WHERE d_codigo = :cp ORDER BY d_asenta';
  }

  $stmt = $pdo->prepare($sql);
  $stmt->execute([':cp' => $cp]);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  if (!$rows) { echo json_encode(['response' => ['settlement' => []]]); exit; }

  $colonias = [];
  $mun = '';
  $edo = '';
  foreach ($rows as $r) {
    $name = trim($r['d_asenta'] ?? '');
    if ($name !== '' && !in_array($name, $colonias, true)) { $colonias[] = $name; }
  }
  $first = $rows[0];
  $mun = $first['d_mnpio'] ?? '';
  $edo = $first['d_estado'] ?? '';

  echo json_encode([
    'response' => [
      'settlement' => $colonias,
      'municipio' => $mun,
      'estado'    => $edo,
    ]
  ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error' => 'db_error', 'detail' => $e->getMessage()]);
}

