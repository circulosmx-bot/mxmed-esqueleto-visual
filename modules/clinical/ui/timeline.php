<?php

declare(strict_types=1);

const TIMELINE_API_BASE = 'http://127.0.0.1:8091';
const TIMELINE_LIMIT = 50;

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$patientId = trim((string)($_GET['patient_id'] ?? ''));
$include = trim((string)($_GET['include'] ?? 'agenda,clinical'));
$allowedIncludes = ['agenda,clinical', 'agenda', 'clinical'];
if (!in_array($include, $allowedIncludes, true)) {
    $include = 'agenda,clinical';
}

$responseData = null;
$errorMessage = '';
$items = [];

if ($patientId !== '') {
    $query = http_build_query([
        'include' => $include,
        'limit' => TIMELINE_LIMIT,
    ]);
    $url = TIMELINE_API_BASE . '/api/clinical/index.php/patients/' . rawurlencode($patientId) . '/timeline?' . $query;

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
        $errorMessage = 'No se pudo consultar el timeline.';
    } else {
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            $errorMessage = 'Respuesta inválida del endpoint timeline.';
        } else {
            $responseData = $decoded;
            if (($decoded['ok'] ?? false) !== true) {
                $errorMessage = (string)($decoded['message'] ?? 'Error consultando timeline.');
            } else {
                $list = $decoded['data']['items'] ?? [];
                $items = is_array($list) ? $list : [];
            }
        }
    }
}

$filters = [
    'agenda,clinical' => 'Todo',
    'agenda' => 'Agenda',
    'clinical' => 'Clinical',
];
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Timeline del Paciente</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container py-4">
  <h1 class="h4 mb-1">Timeline del Paciente</h1>
  <p class="text-secondary mb-3">patient_id: <code><?php echo h($patientId !== '' ? $patientId : '-'); ?></code></p>

  <form class="row g-2 mb-3" method="get">
    <div class="col-12 col-md-8">
      <label for="patient_id" class="form-label">Patient ID</label>
      <input id="patient_id" name="patient_id" class="form-control" value="<?php echo h($patientId); ?>" required>
    </div>
    <div class="col-12 col-md-4 d-flex align-items-end">
      <button type="submit" class="btn btn-primary w-100">Cargar timeline</button>
    </div>
  </form>

  <div class="btn-group mb-3" role="group" aria-label="Filtros timeline">
    <?php foreach ($filters as $filterValue => $filterLabel): ?>
      <?php
      $isActive = ($include === $filterValue);
      $href = '?patient_id=' . urlencode($patientId) . '&include=' . urlencode($filterValue);
      ?>
      <a class="btn <?php echo $isActive ? 'btn-primary' : 'btn-outline-primary'; ?>" href="<?php echo h($href); ?>">
        <?php echo h($filterLabel); ?>
      </a>
    <?php endforeach; ?>
  </div>

  <?php if ($patientId === ''): ?>
    <div class="alert alert-info">Captura un <code>patient_id</code> para consultar el timeline.</div>
  <?php elseif ($errorMessage !== ''): ?>
    <div class="alert alert-danger"><?php echo h($errorMessage); ?></div>
  <?php elseif ($items === []): ?>
    <div class="alert alert-light border">Sin eventos en timeline</div>
  <?php else: ?>
    <div class="vstack gap-2">
      <?php foreach ($items as $item): ?>
        <?php
        $itemType = (string)($item['item_type'] ?? '-');
        $eventDatetime = (string)($item['event_datetime'] ?? '-');
        $encounterKey = (string)($item['encounter_key'] ?? '-');
        $sortKey = (string)($item['sort_key'] ?? '-');
        ?>
        <article class="card">
          <div class="card-body">
            <div class="d-flex flex-wrap gap-3 small mb-2">
              <span><strong>Tipo:</strong> <?php echo h($itemType); ?></span>
              <span><strong>Fecha:</strong> <?php echo h($eventDatetime); ?></span>
              <span><strong>Encounter:</strong> <?php echo h($encounterKey); ?></span>
              <span><strong>Sort:</strong> <?php echo h($sortKey); ?></span>
            </div>

            <?php if ($itemType === 'appointment'): ?>
              <?php $agenda = is_array($item['agenda'] ?? null) ? $item['agenda'] : []; ?>
              <div class="small text-secondary">
                status: <?php echo h((string)($agenda['status'] ?? '-')); ?> |
                start_at: <?php echo h((string)($agenda['start_at'] ?? '-')); ?> |
                end_at: <?php echo h((string)($agenda['end_at'] ?? '-')); ?> |
                modality: <?php echo h((string)($agenda['modality'] ?? '-')); ?> |
                channel_origin: <?php echo h((string)($agenda['channel_origin'] ?? '-')); ?>
              </div>
            <?php elseif ($itemType === 'document'): ?>
              <?php $doc = is_array($item['clinical_document'] ?? null) ? $item['clinical_document'] : []; ?>
              <div class="small text-secondary">
                document_type: <?php echo h((string)($doc['document_type'] ?? '-')); ?> |
                summary: <?php echo h((string)($doc['summary'] ?? '-')); ?>
              </div>
            <?php elseif ($itemType === 'encounter'): ?>
              <?php
              $clinical = is_array($item['clinical'] ?? null) ? $item['clinical'] : [];
              $docs = is_array($clinical['documents'] ?? null) ? $clinical['documents'] : [];
              $types = [];
              foreach ($docs as $d) {
                  if (is_array($d)) {
                      $t = trim((string)($d['document_type'] ?? ''));
                      if ($t !== '') {
                          $types[$t] = true;
                      }
                  }
              }
              ?>
              <div class="small text-secondary">
                documentos: <?php echo count($docs); ?> |
                tipos: <?php echo h(implode(', ', array_keys($types))); ?>
              </div>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
</body>
</html>
