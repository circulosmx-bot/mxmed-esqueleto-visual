<?php
declare(strict_types=1);

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

$patientId = trim((string)($_GET['patient_id'] ?? ''));
$appointmentId = trim((string)($_GET['appointment_id'] ?? ''));
$encounterKey = trim((string)($_GET['encounter_key'] ?? ''));
$include = trim((string)($_GET['include'] ?? 'agenda,clinical'));
$limit = (int)($_GET['limit'] ?? 20);
$cursor = trim((string)($_GET['cursor'] ?? ''));
$direction = trim((string)($_GET['direction'] ?? ''));
$include = $include !== '' ? $include : 'agenda,clinical';
$limit = ($limit > 0 && $limit <= 200) ? $limit : 20;

$errorMessage = '';
$resolveErrorMsg = '';
$items = [];
$cursorNext = '';
$cursorPrev = '';

if ($encounterKey === '' && $appointmentId !== '') {
    $encounterKey = 'appt:' . $appointmentId;
}

if ($patientId === '' && $encounterKey !== '') {
    $resolveUrl = get_api_base() . '/api/clinical/index.php/encounters/' . rawurlencode($encounterKey);
    $resolveContext = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 8,
            'ignore_errors' => true,
            'header' => "Accept: application/json\r\n",
        ],
    ]);
    $resolveRaw = @file_get_contents($resolveUrl, false, $resolveContext);
    if ($resolveRaw !== false) {
        $resolveDecoded = json_decode($resolveRaw, true);
        $resolveData = is_array($resolveDecoded['data'] ?? null) ? $resolveDecoded['data'] : [];
        $resolveLinks = is_array($resolveData['links'] ?? null) ? $resolveData['links'] : [];
        $resolvedPatientId = trim((string)($resolveLinks['patient_id'] ?? ($resolveData['patient_id'] ?? '')));
        if (is_array($resolveDecoded) && ($resolveDecoded['ok'] ?? false) === true && $resolvedPatientId !== '') {
            $redirectParams = [
                'patient_id' => $resolvedPatientId,
                'include' => $include,
                'limit' => $limit,
            ];
            if ($cursor !== '') {
                $redirectParams['cursor'] = $cursor;
            }
            if ($direction !== '') {
                $redirectParams['direction'] = $direction;
            }
            header('Location: /modules/clinical/ui/historial.php?' . http_build_query($redirectParams));
            exit;
        }
    }
    $resolveErrorMsg = 'No se pudo resolver patient_id desde el encounter.';
}

if ($encounterKey === '' && $appointmentId !== '') {
    $encounterKey = 'appt:' . $appointmentId;
}

if ($patientId === '' && $encounterKey !== '') {
    $resolveUrl = get_api_base() . '/api/clinical/index.php/encounters/' . rawurlencode($encounterKey);
    $resolveContext = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 8,
            'ignore_errors' => true,
            'header' => "Accept: application/json\r\n",
        ],
    ]);
    $resolveRaw = @file_get_contents($resolveUrl, false, $resolveContext);
    if ($resolveRaw !== false) {
        $resolveDecoded = json_decode($resolveRaw, true);
        $resolveData = is_array($resolveDecoded['data'] ?? null) ? $resolveDecoded['data'] : [];
        $resolveLinks = is_array($resolveData['links'] ?? null) ? $resolveData['links'] : [];
        $resolvedPatientId = trim((string)($resolveLinks['patient_id'] ?? ($resolveData['patient_id'] ?? '')));
        if (is_array($resolveDecoded) && ($resolveDecoded['ok'] ?? false) === true && $resolvedPatientId !== '') {
            $redirectParams = [
                'patient_id' => $resolvedPatientId,
                'include' => $include,
                'limit' => $limit,
            ];
            if ($cursor !== '') {
                $redirectParams['cursor'] = $cursor;
            }
            if ($direction !== '') {
                $redirectParams['direction'] = $direction;
            }
            header('Location: /modules/clinical/ui/historial.php?' . http_build_query($redirectParams));
            exit;
        }
    }
    $resolveErrorMsg = 'No se pudo resolver patient_id desde el encounter.';
}

if ($patientId !== '') {
    $query = [
        'include' => $include,
        'limit' => $limit,
    ];
    if ($cursor !== '') {
        $query['cursor'] = $cursor;
    }
    if ($direction !== '') {
        $query['direction'] = $direction;
    }

    $url = get_api_base() . '/api/clinical/index.php/patients/' . rawurlencode($patientId) . '/timeline'
         . '?' . http_build_query($query);

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
        $lastError = error_get_last();
        $detail = is_array($lastError) ? trim((string)($lastError['message'] ?? '')) : '';
        $errorMessage = $detail !== '' ? $detail : 'No se pudo consultar el historial de atención.';
    } else {
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            $errorMessage = 'Respuesta inválida del endpoint de timeline.';
        } elseif (($decoded['ok'] ?? false) !== true) {
            $errorMessage = (string)($decoded['message'] ?? 'Error consultando historial de atención.');
        } else {
            $data = is_array($decoded['data'] ?? null) ? $decoded['data'] : [];
            $list = $data['items'] ?? [];
            $items = is_array($list) ? $list : [];
            $range = is_array($data['range'] ?? null) ? $data['range'] : [];
            $cursorNext = trim((string)($range['cursor_next'] ?? ''));
            $cursorPrev = trim((string)($range['cursor_prev'] ?? ''));
        }
    }
}

$filters = [
    'agenda,clinical' => 'Todo',
    'agenda' => 'Agenda',
    'clinical' => 'Clínico',
];

$encounters = [];
$encounterOrder = [];
$orphanDocs = [];
$appointmentItems = [];

foreach ($items as $item) {
    if (!is_array($item)) {
        continue;
    }
    $itemType = (string)($item['item_type'] ?? '');
    if ($itemType === 'encounter') {
        $ek = trim((string)($item['encounter_key'] ?? ''));
        if ($ek === '') {
            continue;
        }
        $appointmentInEncounter = null;
        if (strpos($ek, 'appt:') === 0) {
            $appointmentInEncounter = substr($ek, 5);
            $appointmentInEncounter = $appointmentInEncounter !== '' ? $appointmentInEncounter : null;
        }
        $encounters[$ek] = [
            'encounter_key' => $ek,
            'event_datetime' => (string)($item['event_datetime'] ?? ''),
            'appointment_id' => $appointmentInEncounter,
            'documents' => [],
            'raw' => $item,
        ];
        $encounterOrder[] = $ek;
    } elseif ($itemType === 'appointment') {
        $appointmentItems[] = $item;
    }
}

foreach ($items as $item) {
    if (!is_array($item) || (string)($item['item_type'] ?? '') !== 'document') {
        continue;
    }
    $links = is_array($item['links'] ?? null) ? $item['links'] : [];
    $appt = trim((string)($links['appointment_id'] ?? ''));
    if ($appt !== '') {
        $key = 'appt:' . $appt;
        if (isset($encounters[$key])) {
            $encounters[$key]['documents'][] = $item;
            continue;
        }
    }
    $orphanDocs[] = $item;
}

$hasRenderableItems = ($appointmentItems !== []) || ($encounterOrder !== []) || ($orphanDocs !== []);

$buildCursorHref = static function (string $nextCursor) use ($patientId, $include, $limit, $direction): string {
    $params = [
        'patient_id' => $patientId,
        'include' => $include,
        'limit' => $limit,
        'cursor' => $nextCursor,
    ];
    if ($direction !== '') {
        $params['direction'] = $direction;
    }
    return '?' . http_build_query($params);
};
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Historial de atención</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container py-4">
  <h1 class="h4 mb-1">Historial de atención</h1>
  <p class="text-secondary mb-3">patient_id: <code><?php echo h($patientId !== '' ? $patientId : '-'); ?></code></p>

  <form class="row g-2 mb-3" method="get">
    <div class="col-12 col-md-8">
      <label for="patient_id" class="form-label">Patient ID</label>
      <input id="patient_id" name="patient_id" class="form-control" value="<?php echo h($patientId); ?>" required>
    </div>
    <div class="col-12 col-md-4 d-flex align-items-end">
      <button type="submit" class="btn btn-primary w-100">Cargar historial de atención</button>
    </div>
  </form>

  <div class="btn-group mb-3" role="group" aria-label="Filtros del historial de atención">
    <?php foreach ($filters as $filterValue => $filterLabel): ?>
      <?php
      $isActive = ($include === $filterValue);
      $href = '?patient_id=' . urlencode($patientId) . '&include=' . urlencode($filterValue) . '&limit=' . $limit;
      ?>
      <a class="btn <?php echo $isActive ? 'btn-primary' : 'btn-outline-primary'; ?>" href="<?php echo h($href); ?>">
        <?php echo h($filterLabel); ?>
      </a>
    <?php endforeach; ?>
  </div>

  <?php if ($resolveErrorMsg !== ''): ?>
    <div class="alert alert-danger"><?php echo h($resolveErrorMsg); ?></div>
  <?php endif; ?>

  <?php if ($patientId === ''): ?>
    <div class="alert alert-info">Captura un <code>patient_id</code> para consultar el historial de atención.</div>
  <?php elseif ($errorMessage !== ''): ?>
    <div class="alert alert-danger"><?php echo h($errorMessage); ?></div>
  <?php elseif (!$hasRenderableItems): ?>
    <div class="alert alert-secondary">Sin eventos (no hay encuentros ni documentos)</div>
  <?php else: ?>
    <?php if ($cursorNext !== '' || $cursorPrev !== ''): ?>
      <div class="d-flex flex-wrap gap-2 mb-3">
        <?php if ($cursorNext !== ''): ?>
          <a class="btn btn-outline-primary btn-sm" href="<?php echo h($buildCursorHref($cursorNext)); ?>">Más reciente</a>
        <?php endif; ?>
        <?php if ($cursorPrev !== ''): ?>
          <a class="btn btn-outline-primary btn-sm" href="<?php echo h($buildCursorHref($cursorPrev)); ?>">Más antiguo</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <div class="vstack gap-2">
      <?php foreach ($appointmentItems as $item): ?>
        <?php
        $agenda = is_array($item['agenda'] ?? null) ? $item['agenda'] : [];
        $links = is_array($item['links'] ?? null) ? $item['links'] : [];
        ?>
        <article class="card">
          <div class="card-body">
            <div class="d-flex flex-wrap gap-3 small mb-2">
              <span><strong>Tipo:</strong> appointment</span>
              <span><strong>Fecha:</strong> <?php echo h((string)($item['event_datetime'] ?? '-')); ?></span>
              <span><strong>Atención:</strong> <?php echo h((string)($item['encounter_key'] ?? '-')); ?></span>
            </div>
            <div class="small text-secondary">
              status: <?php echo h((string)($agenda['status'] ?? '-')); ?> |
              start_at: <?php echo h((string)($agenda['start_at'] ?? '-')); ?> |
              end_at: <?php echo h((string)($agenda['end_at'] ?? '-')); ?> |
              modality: <?php echo h((string)($agenda['modality'] ?? '-')); ?> |
              channel_origin: <?php echo h((string)($agenda['channel_origin'] ?? '-')); ?>
            </div>
            <?php if (trim((string)($links['appointment_id'] ?? '')) !== ''): ?>
              <div class="mt-2">
                <a class="btn btn-sm btn-outline-primary" href="/index.html#p-agenda">Ver cita</a>
              </div>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>

      <?php foreach ($encounterOrder as $ek): ?>
        <?php
        if (!isset($encounters[$ek])) {
            continue;
        }
        $encounter = $encounters[$ek];
        $rawEncounter = is_array($encounter['raw'] ?? null) ? $encounter['raw'] : [];
        $clinical = is_array($rawEncounter['clinical'] ?? null) ? $rawEncounter['clinical'] : [];
        $clinicalDocs = is_array($clinical['documents'] ?? null) ? $clinical['documents'] : [];
        $types = [];
        foreach ($clinicalDocs as $d) {
            if (is_array($d)) {
                $t = trim((string)($d['document_type'] ?? ''));
                if ($t !== '') {
                    $types[$t] = true;
                }
            }
        }
        $isAppointmentEncounter = strpos($ek, 'appt:') === 0;
        $docsInEncounter = is_array($encounter['documents'] ?? null) ? $encounter['documents'] : [];
        ?>
        <article class="card">
          <div class="card-body">
            <div class="d-flex flex-wrap gap-3 small mb-2">
              <span><strong>Tipo:</strong> encounter</span>
              <span><strong>Fecha:</strong> <?php echo h((string)($encounter['event_datetime'] ?: '-')); ?></span>
              <span><strong>Atención:</strong> <?php echo h($ek); ?></span>
            </div>
            <div class="small text-secondary">
              documentos: <?php echo count($clinicalDocs); ?> |
              tipos: <?php echo h(implode(', ', array_keys($types))); ?>
            </div>
            <?php if ($isAppointmentEncounter): ?>
              <div class="mt-2">
                <a class="btn btn-sm btn-outline-primary" href="/modules/clinical/ui/encounter.php?encounter_key=<?php echo urlencode($ek); ?>">Ver atención</a>
              </div>
            <?php endif; ?>

            <div class="mt-3">
              <div class="small fw-semibold mb-2">Documentos asociados</div>
              <?php if ($docsInEncounter === []): ?>
                <div class="small text-secondary">Sin documentos asociados</div>
              <?php else: ?>
                <div class="vstack gap-2">
                  <?php foreach ($docsInEncounter as $docItem): ?>
                    <?php
                    $doc = is_array($docItem['clinical_document'] ?? null) ? $docItem['clinical_document'] : [];
                    $links = is_array($docItem['links'] ?? null) ? $docItem['links'] : [];
                    $docUuid = trim((string)($links['document_uuid'] ?? ''));
                    ?>
                    <div class="border rounded p-2 small">
                      <div><strong><?php echo h((string)($doc['document_type'] ?? '-')); ?></strong></div>
                      <div class="text-secondary"><?php echo h((string)($doc['summary'] ?? '-')); ?></div>
                      <?php if ($docUuid !== ''): ?>
                        <div class="mt-1">
                          <a class="btn btn-sm btn-outline-secondary" href="/modules/clinical/ui/document.php?uuid=<?php echo urlencode($docUuid); ?>">Ver documento</a>
                        </div>
                      <?php endif; ?>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </article>
      <?php endforeach; ?>

      <?php if ($orphanDocs !== []): ?>
        <div class="pt-2">
          <h2 class="h6 mb-2">Documentos sin atención</h2>
        </div>
        <?php foreach ($orphanDocs as $docItem): ?>
          <?php
          $doc = is_array($docItem['clinical_document'] ?? null) ? $docItem['clinical_document'] : [];
          $links = is_array($docItem['links'] ?? null) ? $docItem['links'] : [];
          $docUuid = trim((string)($links['document_uuid'] ?? ''));
          ?>
          <article class="card">
            <div class="card-body">
              <div class="d-flex flex-wrap gap-3 small mb-2">
                <span><strong>Tipo:</strong> document</span>
                <span><strong>Fecha:</strong> <?php echo h((string)($docItem['event_datetime'] ?? '-')); ?></span>
              </div>
              <div class="small text-secondary">
                document_type: <?php echo h((string)($doc['document_type'] ?? '-')); ?> |
                summary: <?php echo h((string)($doc['summary'] ?? '-')); ?>
              </div>
              <?php if ($docUuid !== ''): ?>
                <div class="mt-2">
                  <a class="btn btn-sm btn-outline-primary" href="/modules/clinical/ui/document.php?uuid=<?php echo urlencode($docUuid); ?>">Ver documento</a>
                </div>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>
</body>
</html>
