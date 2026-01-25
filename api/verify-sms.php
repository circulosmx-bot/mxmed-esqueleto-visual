<?php
// Stub de verificación de código SMS (modo pruebas)
// En producción: validar contra proveedor SMS/2FA y aplicar rate‑limit.
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$code = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $code = isset($_POST['code']) ? trim($_POST['code']) : '';
  if ($code === '' && isset($_SERVER['CONTENT_TYPE']) && stripos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $j = json_decode($raw, true);
    if (is_array($j) && isset($j['code'])) $code = trim((string)$j['code']);
  }
} else {
  $code = isset($_GET['code']) ? trim($_GET['code']) : '';
}

// Modo pruebas: cualquier valor no vacío es válido
$ok = ($code !== '');
echo json_encode(['ok' => $ok], JSON_UNESCAPED_UNICODE);

