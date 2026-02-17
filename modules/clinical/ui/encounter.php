<?php

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

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$encounterKey = trim((string)($_GET['encounter_key'] ?? ''));
$errorMessage = '';
$encounter = null;

if ($encounterKey !== '') {
    // ✅ CORRECTO: encounters/{encounter_key}
    $url = get_api_base() . '/api/clinical/index.php/encounters/' . rawurlencode($encounterKey);

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
        $errorMessage = 'No se pudo consultar la atención.';
    } else {
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            $errorMessage = 'Respuesta inválida del endpoint de encounters.';
        } elseif (($decoded['ok'] ?? false) !== true) {
            $errorMessage = (string)($decoded['message'] ?? 'Error consultando atención.');
        } else {
            // La API devuelve: { data: { encounter_key, appointment_id, ... } }
            $data = $decoded['data'] ?? null;
            $encounter = is_array($data) ? $data : null;
            if ($encounter === null) {
                $errorMessage = 'Atención no disponible.';
            }
        }
    }
}

$documents = is_array($encounter['documents'] ?? null) ? $encounter['documents'] : [];
$prescriptions = is_array($encounter['prescriptions'] ?? null) ? $encounter['prescriptions'] : [];
$orders = is_array($encounter['orders'] ?? null) ? $encounter['orders'] : [];
$results = is_array($encounter['results'] ?? null) ? $encounter['results'] : [];

?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Atención clínica</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Atención clínica</h1>
    <a class="btn btn-outline-secondary btn-sm" href="javascript:history.back()">Volver</a>
  </div>

  <p class="text-secondary">encounter_key: <code><?php echo h($encounterKey !== '' ? $encounterKey : '-'); ?></code></p>

  <?php if ($encounterKey === ''): ?>
    <div class="alert alert-warning">encounter_key requerido.</div>
  <?php elseif ($errorMessage !== ''): ?>
    <div class="alert alert-danger"><?php echo h($errorMessage); ?></div>
  <?php else: ?>
    <div class="card mb-3">
      <div class="card-body small">
        <div><strong>Fecha:</strong> <?php echo h((string)($encounter['event_datetime'] ?? '-')); ?></div>
        <div><strong>patient_id:</strong> <?php echo h((string)($encounter['patient_id'] ?? '-')); ?></div>
        <div><strong>appointment_id:</strong> <?php echo h((string)($encounter['appointment_id'] ?? '-')); ?></div>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header">Documentos</div>
      <ul class="list-group list-group-flush">
        <?php if ($documents === []): ?>
          <li class="list-group-item text-secondary">Sin documentos</li>
        <?php else: ?>
          <?php foreach ($documents as $doc): ?>
            <?php
              // OJO: en la API de documentos el campo que vimos es "document_id" (UUID).
              // Pero dejamos fallback a "document_uuid" por compatibilidad.
              $uuid = trim((string)($doc['document_id'] ?? ($doc['document_uuid'] ?? '')));
              $docType = (string)($doc['document_type'] ?? '-');
              $docDt = (string)($doc['event_datetime'] ?? '-');
            ?>
            <li class="list-group-item small">
              <strong><?php echo h($docType); ?></strong>
              · <?php echo h($docDt); ?>
              <?php if ($uuid !== ''): ?>
                · <a href="/modules/clinical/ui/document.php?uuid=<?php echo urlencode($uuid); ?>">Ver documento</a>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        <?php endif; ?>
      </ul>
    </div>

    <div class="row g-3">
      <div class="col-md-4">
        <div class="card h-100">
          <div class="card-header">Recetas</div>
          <div class="card-body small text-secondary"><?php echo $prescriptions === [] ? 'Sin recetas' : h((string)count($prescriptions)); ?></div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card h-100">
          <div class="card-header">Órdenes</div>
          <div class="card-body small text-secondary"><?php echo $orders === [] ? 'Sin órdenes' : h((string)count($orders)); ?></div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card h-100">
          <div class="card-header">Resultados</div>
          <div class="card-body small text-secondary"><?php echo $results === [] ? 'Sin resultados' : h((string)count($results)); ?></div>
        </div>
      </div>
    </div>
  <?php endif; ?>
</div>
</body>
</html>
