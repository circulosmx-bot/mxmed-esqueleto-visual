<?php
// Stub de verificación de contraseña (modo pruebas)
// En producción: validar contra hash Argon2id en BD + intentos y bloqueo.
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$pass = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $pass = isset($_POST['password']) ? (string)$_POST['password'] : '';
  if ($pass === '' && isset($_SERVER['CONTENT_TYPE']) && stripos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $j = json_decode($raw, true);
    if (is_array($j) && isset($j['password'])) $pass = (string)$j['password'];
  }
} else {
  $pass = isset($_GET['password']) ? (string)$_GET['password'] : '';
}

// Modo pruebas: cualquier valor no vacío es válido
$ok = ($pass !== '');
echo json_encode(['ok' => $ok], JSON_UNESCAPED_UNICODE);

