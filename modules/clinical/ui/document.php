<?php
// modules/clinical/ui/document.php

function get_api_base(): string
{
    $env = trim((string)getenv('MXMED_TIMELINE_API_BASE'));
    if ($env !== '') {
        return rtrim($env, '/');
    }

    $host = (string)($_SERVER['HTTP_HOST'] ?? '127.0.0.1');
    $proto = 'http';
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        $proto = 'https';
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $proto = (string)$_SERVER['HTTP_X_FORWARDED_PROTO'];
    }

    return $proto . '://' . $host;
}

function h(string $value): string {
  return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function http_get_json(string $url, int $timeoutSeconds = 8): array {
  $context = stream_context_create([
    'http' => [
      'method' => 'GET',
      'timeout' => $timeoutSeconds,
      'ignore_errors' => true,
      'header' => "Accept: application/json\r\n",
    ],
  ]);

  $raw = @file_get_contents($url, false, $context);
  if ($raw === false) {
    return ['ok' => false, 'error' => 'fetch_failed', 'message' => 'No se pudo consultar el documento.'];
  }

  $decoded = json_decode($raw, true);
  if (!is_array($decoded)) {
    return ['ok' => false, 'error' => 'invalid_json', 'message' => 'Respuesta inválida del endpoint de documentos.'];
  }

  return $decoded;
}

$uuid = trim((string)($_GET['uuid'] ?? ''));
$errorMessage = '';
$document = null;

if ($uuid !== '') {
$url = get_api_base() . '/api/clinical/index.php/documents/' . rawurlencode($uuid);
  $decoded = http_get_json($url, 8);

  if (($decoded['ok'] ?? false) !== true) {
    $errorMessage = (string)($decoded['message'] ?? 'Error consultando documento.');
  } else {
    $doc = $decoded['data']['document'] ?? null;
    $document = is_array($doc) ? $doc : null;
    if (!$document) {
      $errorMessage = 'Documento no disponible.';
    }
  }
}

// Campos según el JSON real:
$docType = $document ? (string)($document['document_type'] ?? '-') : '-';
$title   = $document ? (string)($document['title'] ?? '-') : '-';
$date    = $document ? (string)($document['ui']['event_datetime'] ?? ($document['timestamps']['created_at'] ?? '-')) : '-';
$summary = $document ? (string)($document['content']['summary'] ?? '-') : '-';
$payload = $document && is_array($document['content']['payload'] ?? null) ? $document['content']['payload'] : null;
$payloadJson = $payload ? json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
$renderedText = $document ? (string)($document['content']['rendered_text'] ?? '') : '';
$embed = isset($_GET['embed']) && $_GET['embed'] === '1';

// Shell MXMed
if (!$embed) {
    $pageTitle = 'Documento clínico';
    require_once __DIR__ . '/../../_partials/mm_shell_top.php';
} else {
    echo '<div class="clinical-embed p-2">';
}
?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h1 class="h4 mb-0">Documento clínico</h1>
      <?php if ($title !== '-' && $uuid !== ''): ?>
        <div class="text-secondary small"><?php echo h($title); ?></div>
      <?php endif; ?>
    </div>
    <a class="btn btn-outline-secondary btn-sm" href="javascript:history.back()">Volver</a>
  </div>

  <p class="text-secondary mb-3">uuid: <code><?php echo h($uuid !== '' ? $uuid : '-'); ?></code></p>

  <?php if ($uuid === ''): ?>
    <div class="alert alert-warning">uuid requerido.</div>
  <?php elseif ($errorMessage !== ''): ?>
    <div class="alert alert-danger"><?php echo h($errorMessage); ?></div>
  <?php else: ?>
    <div class="mm-card mb-3">
      <div class="body small">
        <div><strong>Tipo:</strong> <?php echo h($docType); ?></div>
        <div><strong>Fecha:</strong> <?php echo h($date); ?></div>
        <div><strong>Summary:</strong> <?php echo h($summary); ?></div>
      </div>
    </div>

    <?php if ($renderedText !== ''): ?>
      <div class="mm-card mb-3">
        <div class="head"><h5>Texto renderizado</h5></div>
        <div class="body">
          <pre class="mb-0 small"><?php echo h($renderedText); ?></pre>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($payloadJson !== ''): ?>
      <div class="mm-card">
        <div class="head"><h5>Payload (JSON)</h5></div>
        <div class="body">
          <pre class="mb-0 small"><?php echo h($payloadJson); ?></pre>
        </div>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php if ($embed): ?>
</div>
<?php else: ?>
<?php require_once __DIR__ . '/../../_partials/mm_shell_bottom.php'; ?>
<?php endif; ?>
