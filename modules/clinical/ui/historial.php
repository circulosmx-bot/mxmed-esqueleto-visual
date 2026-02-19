<?php
declare(strict_types=1);

function get_api_base(): string
{
    $env = trim((string)getenv('MXMED_API_BASE'));
    if ($env !== '') {
        return rtrim($env, '/');
    }

    // UI corre en 8091
    // API Gateway corre en 8092
    return 'http://127.0.0.1:8092';
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function build_demo_timeline_items(): array
{
    // Fixtures demo para UX (sin dependencia de DB/API)
    return [
        [
            'item_type' => 'appointment',
            'encounter_key' => 'appt:9001',
            'event_datetime' => '2026-02-19 09:00:00',
            'agenda' => [
                'status' => 'confirmed',
                'start_at' => '2026-02-19 09:00:00',
                'end_at' => '2026-02-19 09:30:00',
                'modality' => 'presencial',
                'channel_origin' => 'public_web',
            ],
            'links' => ['appointment_id' => '9001'],
        ],
        [
            'item_type' => 'encounter',
            'encounter_key' => 'appt:9001',
            'event_datetime' => '2026-02-19 09:35:00',
            'clinical' => [
                'has_vitals' => true,
                'has_note' => true,
                'has_prescription' => true,
                'has_orders' => true,
                'has_results' => false,
                'documents' => [
                    ['document_type' => 'vitals'],
                    ['document_type' => 'note'],
                    ['document_type' => 'prescription'],
                    ['document_type' => 'orders'],
                ],
            ],
        ],
        [
            'item_type' => 'document',
            'event_datetime' => '2026-02-19 09:12:00',
            'clinical_document' => ['document_type' => 'vitals', 'summary' => 'TA 120/80, FC 72, Temp 36.6'],
            'links' => ['appointment_id' => '9001', 'document_uuid' => 'demo-doc-9001-vitals'],
        ],
        [
            'item_type' => 'document',
            'event_datetime' => '2026-02-19 09:18:00',
            'clinical_document' => ['document_type' => 'note', 'summary' => 'Dolor faríngeo de 3 días, sin fiebre.'],
            'links' => ['appointment_id' => '9001', 'document_uuid' => 'demo-doc-9001-note'],
        ],
        [
            'item_type' => 'document',
            'event_datetime' => '2026-02-19 09:24:00',
            'clinical_document' => ['document_type' => 'prescription', 'summary' => 'Paracetamol 500mg cada 8h por 3 días.'],
            'links' => ['appointment_id' => '9001', 'document_uuid' => 'demo-doc-9001-rx'],
        ],
        [
            'item_type' => 'appointment',
            'encounter_key' => 'appt:9002',
            'event_datetime' => '2026-02-17 16:00:00',
            'agenda' => [
                'status' => 'completed',
                'start_at' => '2026-02-17 16:00:00',
                'end_at' => '2026-02-17 16:40:00',
                'modality' => 'teleconsulta',
                'channel_origin' => 'call_center',
            ],
            'links' => ['appointment_id' => '9002'],
        ],
        [
            'item_type' => 'encounter',
            'encounter_key' => 'appt:9002',
            'event_datetime' => '2026-02-17 16:45:00',
            'clinical' => [
                'has_vitals' => false,
                'has_note' => true,
                'has_prescription' => false,
                'has_orders' => true,
                'has_results' => true,
                'documents' => [
                    ['document_type' => 'note'],
                    ['document_type' => 'orders'],
                    ['document_type' => 'results'],
                ],
            ],
        ],
        [
            'item_type' => 'document',
            'event_datetime' => '2026-02-17 16:20:00',
            'clinical_document' => ['document_type' => 'note', 'summary' => 'Control metabólico con mejoría parcial.'],
            'links' => ['appointment_id' => '9002', 'document_uuid' => 'demo-doc-9002-note'],
        ],
        [
            'item_type' => 'document',
            'event_datetime' => '2026-02-17 16:33:00',
            'clinical_document' => ['document_type' => 'results', 'summary' => 'HbA1c 6.9%, glucosa en ayuno 122 mg/dL.'],
            'links' => ['appointment_id' => '9002', 'document_uuid' => 'demo-doc-9002-results'],
        ],
        [
            'item_type' => 'appointment',
            'encounter_key' => 'appt:9003',
            'event_datetime' => '2026-02-12 11:30:00',
            'agenda' => [
                'status' => 'cancelled',
                'start_at' => '2026-02-12 11:30:00',
                'end_at' => '2026-02-12 12:00:00',
                'modality' => 'presencial',
                'channel_origin' => 'doctor_assistant',
            ],
            'links' => ['appointment_id' => '9003'],
        ],
        [
            'item_type' => 'encounter',
            'encounter_key' => 'appt:9004',
            'event_datetime' => '2026-02-09 14:20:00',
            'clinical' => [
                'has_vitals' => true,
                'has_note' => false,
                'has_prescription' => true,
                'has_orders' => false,
                'has_results' => false,
                'documents' => [
                    ['document_type' => 'vitals'],
                    ['document_type' => 'prescription'],
                ],
            ],
        ],
        [
            'item_type' => 'document',
            'event_datetime' => '2026-02-09 14:10:00',
            'clinical_document' => ['document_type' => 'vitals', 'summary' => 'Peso 82 kg, IMC 28.2.'],
            'links' => ['appointment_id' => '9004', 'document_uuid' => 'demo-doc-9004-vitals'],
        ],
        [
            'item_type' => 'document',
            'event_datetime' => '2026-02-09 14:15:00',
            'clinical_document' => ['document_type' => 'prescription', 'summary' => 'Omeprazol 20mg cada 24h por 14 días.'],
            'links' => ['appointment_id' => '9004', 'document_uuid' => 'demo-doc-9004-rx'],
        ],
        [
            'item_type' => 'document',
            'event_datetime' => '2026-01-28 08:40:00',
            'clinical_document' => ['document_type' => 'note', 'summary' => 'Nota externa sin cita ligada en sistema.'],
            'links' => ['appointment_id' => null, 'document_uuid' => 'demo-doc-orphan-01'],
        ],
    ];
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
require_once __DIR__ . '/../../_partials/clinical_embed.php';
$embed = is_embed_request();

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
            if ($embed) {
                $redirectParams['embed'] = '1';
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
            if ($embed) {
                $redirectParams['embed'] = '1';
            }
            header('Location: /modules/clinical/ui/historial.php?' . http_build_query($redirectParams));
            exit;
        }
    }
    $resolveErrorMsg = 'No se pudo resolver patient_id desde el encounter.';
}

if ($patientId !== '') {
    if ($patientId === 'demo') {
        $demoItems = build_demo_timeline_items();
        $items = array_values(array_filter($demoItems, static function (array $item) use ($include): bool {
            $type = (string)($item['item_type'] ?? '');
            if ($include === 'agenda') {
                return $type === 'appointment';
            }
            if ($include === 'clinical') {
                return $type === 'encounter' || $type === 'document';
            }
            return true;
        }));
        usort($items, static function (array $a, array $b): int {
            return strcmp((string)($b['event_datetime'] ?? ''), (string)($a['event_datetime'] ?? ''));
        });
        if ($limit > 0) {
            $items = array_slice($items, 0, $limit);
        }
    } else {
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
    return '?' . carry_embed_params($params);
};
// Shell MXMed
$pageTitle = 'Historial de atención';
$extraHead = <<<'HTML'
  <style>
    .clinical-historial .mm-chip{
      display:inline-flex;
      align-items:center;
      gap:6px;
      padding:.25rem .55rem;
      border-radius:999px;
      font-weight:600;
      font-size:.78rem;
      border:1px solid rgba(0,0,0,.08);
    }
    .clinical-historial .mm-chip.is-on{
      background-color:var(--mm-header-top, #EAF6FB) !important;
      border-color:var(--mm-borde-input, #00B0C5) !important;
      color:var(--mm-barra-vigencia, #003152) !important;
    }
    .clinical-historial .mm-chip.is-off{
      background-color:#fff !important;
      color:#6c757d !important;
      opacity:.55;
      border-color:rgba(0,0,0,.08) !important;
    }
    .clinical-historial .mm-chip .dot{
      width:8px;
      height:8px;
      border-radius:50%;
      background-color:var(--mm-acc-activo, #00738F) !important;
      flex:0 0 auto;
    }
    .clinical-historial .mm-chip.is-off .dot{
      background-color:#adb5bd !important;
    }
  </style>
HTML;
if (!$embed) {
    require_once __DIR__ . '/../../_partials/mm_shell_top.php';
} else {
    echo $extraHead;
    clinical_embed_start();
}
?>
<div class="clinical-historial">
<div class="<?php echo $embed ? 'py-1' : 'container py-4'; ?>">
  <?php if (!$embed): ?>
    <h1 class="h4 mb-1">Historial de atención</h1>
    <p class="text-secondary mb-3">patient_id: <code><?php echo h($patientId !== '' ? $patientId : '-'); ?></code></p>

    <form class="row g-2 mb-3" method="get">
      <div class="col-12 col-md-8">
        <label for="patient_id" class="form-label">Patient ID</label>
        <input id="patient_id" name="patient_id" class="form-control" value="<?php echo h($patientId); ?>" required>
        <?php echo carry_embed_hidden_input(); ?>
      </div>
      <div class="col-12 col-md-4 d-flex align-items-end">
        <button type="submit" class="btn btn-primary w-100">Cargar historial de atención</button>
      </div>
    </form>
  <?php else: ?>
    <?php // Modo embed: ocultar encabezado y formulario (UX integrado) ?>
  <?php endif; ?>

  <div class="btn-group mb-3" role="group" aria-label="Filtros del historial de atención">
    <?php foreach ($filters as $filterValue => $filterLabel): ?>
      <?php
      $isActive = ($include === $filterValue);
      $href = '?' . carry_embed_params([
          'patient_id' => $patientId,
          'include' => $filterValue,
          'limit' => $limit,
      ]);
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
    <?php if ($embed): ?>
      <div class="alert alert-info py-2 mb-2">Sin <code>patient_id</code>.</div>
    <?php else: ?>
      <div class="alert alert-info">Captura un <code>patient_id</code> para consultar el historial de atención.</div>
    <?php endif; ?>
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
        <article class="mm-card">
          <div class="body">
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
        $hasVitals = (bool)($clinical['has_vitals'] ?? false);
        $hasNote = (bool)($clinical['has_note'] ?? false);
        $hasPrescription = (bool)($clinical['has_prescription'] ?? false);
        $hasOrders = (bool)($clinical['has_orders'] ?? false);
        $hasResults = (bool)($clinical['has_results'] ?? false);
        $isAppointmentEncounter = strpos($ek, 'appt:') === 0;
        $docsInEncounter = is_array($encounter['documents'] ?? null) ? $encounter['documents'] : [];
        ?>
        <article class="mm-card">
          <div class="body">
            <div class="d-flex flex-wrap gap-3 small mb-2">
              <span><strong>Tipo:</strong> encounter</span>
              <span><strong>Fecha:</strong> <?php echo h((string)($encounter['event_datetime'] ?: '-')); ?></span>
              <span><strong>Atención:</strong> <?php echo h($ek); ?></span>
            </div>
            <div class="d-flex flex-wrap gap-2 mb-2">
              <span class="mm-chip <?php echo $hasVitals ? 'is-on' : 'is-off'; ?>" title="<?php echo $hasVitals ? 'Tiene signos vitales' : 'Sin signos vitales'; ?>"><span class="dot"></span>Signos</span>
              <span class="mm-chip <?php echo $hasNote ? 'is-on' : 'is-off'; ?>" title="<?php echo $hasNote ? 'Tiene nota clínica' : 'Sin nota clínica'; ?>"><span class="dot"></span>Nota</span>
              <span class="mm-chip <?php echo $hasPrescription ? 'is-on' : 'is-off'; ?>" title="<?php echo $hasPrescription ? 'Tiene receta' : 'Sin receta'; ?>"><span class="dot"></span>Rx</span>
              <span class="mm-chip <?php echo $hasOrders ? 'is-on' : 'is-off'; ?>" title="<?php echo $hasOrders ? 'Tiene órdenes' : 'Sin órdenes'; ?>"><span class="dot"></span>Órdenes</span>
              <span class="mm-chip <?php echo $hasResults ? 'is-on' : 'is-off'; ?>" title="<?php echo $hasResults ? 'Tiene resultados' : 'Sin resultados'; ?>"><span class="dot"></span>Resultados</span>
            </div>
            <div class="small text-secondary">
              documentos: <?php echo count($clinicalDocs); ?> |
              tipos: <?php echo h(implode(', ', array_keys($types))); ?>
            </div>
            <?php if ($isAppointmentEncounter): ?>
              <div class="mt-2">
                <a class="btn btn-sm btn-outline-primary" href="/modules/clinical/ui/encounter.php?<?php echo h(carry_embed_params(['encounter_key' => $ek])); ?>">Ver atención</a>
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
                          <a class="btn btn-sm btn-outline-secondary" href="/modules/clinical/ui/document.php?<?php echo h(carry_embed_params(['uuid' => $docUuid])); ?>">Ver documento</a>
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
          <article class="mm-card">
            <div class="body">
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
                  <a class="btn btn-sm btn-outline-primary" href="/modules/clinical/ui/document.php?<?php echo h(carry_embed_params(['uuid' => $docUuid])); ?>">Ver documento</a>
                </div>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>
</div>
<?php if ($embed): ?>
<?php clinical_embed_end(); ?>
<?php else: ?>
<?php require_once __DIR__ . '/../../_partials/mm_shell_bottom.php'; ?>
<?php endif; ?>
