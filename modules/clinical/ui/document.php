<?php

declare(strict_types=1);

const TIMELINE_API_BASE = 'http://127.0.0.1:8091';

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$uuid = trim((string)($_GET['uuid'] ?? ''));
$errorMessage = '';
$document = null;

if ($uuid !== '') {
    $url = TIMELINE_API_BASE . '/api/clinical/index.php/documents/' . rawurlencode($uuid);
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 8,
            'ignore_errors' => true,
            'header' => "Accept: application/json\r\n",
        ],
    ]);

    $raw = @file_get_contents($url, false, $context);
    if ($raw === false) {
        $errorMessage = 'No se pudo consultar el documento.';
    } else {
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            $errorMessage = 'Respuesta inválida del endpoint de documentos.';
        } elseif (($decoded['ok'] ?? false) !== true) {
            $errorMessage = (string)($decoded['message'] ?? 'Error consultando documento.');
        } else {
            $doc = $decoded['data']['document'] ?? null;
            $document = is_array($doc) ? $doc : null;
            if ($document === null) {
                $errorMessage = 'Documento no disponible.';
            }
        }
    }
}

$payload = is_array($document['payload'] ?? null) ? $document['payload'] : null;
$payloadJson = $payload ? json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Documento clínico</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Documento clínico</h1>
    <a class="btn btn-outline-secondary btn-sm" href="javascript:history.back()">Volver</a>
  </div>

  <p class="text-secondary">uuid: <code><?php echo h($uuid !== '' ? $uuid : '-'); ?></code></p>

  <?php if ($uuid === ''): ?>
    <div class="alert alert-warning">uuid requerido.</div>
  <?php elseif ($errorMessage !== ''): ?>
    <div class="alert alert-danger"><?php echo h($errorMessage); ?></div>
  <?php else: ?>
    <div class="card mb-3">
      <div class="card-body small">
        <div><strong>Tipo:</strong> <?php echo h((string)($document['document_type'] ?? '-')); ?></div>
        <div><strong>Fecha:</strong> <?php echo h((string)($document['event_datetime'] ?? '-')); ?></div>
        <div><strong>Summary:</strong> <?php echo h((string)($document['summary'] ?? '-')); ?></div>
      </div>
    </div>

    <?php if ($payloadJson !== ''): ?>
      <div class="card">
        <div class="card-header">Payload / body</div>
        <div class="card-body">
          <pre class="mb-0 small"><?php echo h($payloadJson); ?></pre>
        </div>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>
</body>
</html>
