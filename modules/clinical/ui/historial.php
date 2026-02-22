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

function render_embed_css(bool $embed): void
{
    if (!$embed) {
        return;
    }

    echo '<link rel="stylesheet" href="/assets/css/style.css">' . "\n";
    echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">' . "\n";
}

function fetch_http_json(string $url, int $timeoutSeconds = 4, int $maxAttempts = 2): array
{
    $last = [
        'ok' => false,
        'raw' => false,
        'status' => 0,
        'headers' => [],
        'error' => '',
        'body_snippet' => '',
        'attempts' => 0,
    ];

    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => $timeoutSeconds,
                'ignore_errors' => true,
                'header' => "Accept: application/json\r\nUser-Agent: MXMed\r\n",
            ],
        ]);

        $raw = @file_get_contents($url, false, $context);
        $headers = function_exists('http_get_last_response_headers')
            ? http_get_last_response_headers()
            : [];
        if (!is_array($headers)) {
            $headers = [];
        }
        $status = 0;
        if (!empty($headers) && preg_match('/^HTTP\/\S+\s+(\d{3})\b/', (string)$headers[0], $m) === 1) {
            $status = (int)$m[1];
        }
        $lastError = error_get_last();
        $errMsg = is_array($lastError) ? trim((string)($lastError['message'] ?? '')) : '';
        $snippet = is_string($raw) ? substr(trim($raw), 0, 600) : '';
        $isOk = is_string($raw) && $status >= 200 && $status < 300;

        $last = [
            'ok' => $isOk,
            'raw' => $raw,
            'status' => $status,
            'headers' => $headers,
            'error' => $errMsg,
            'body_snippet' => $snippet,
            'attempts' => $attempt,
        ];

        if ($isOk) {
            break;
        }
    }

    return $last;
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
$errorTechnicalDetails = '';
$resolveErrorMsg = '';
$items = [];
$cursorNext = '';
$cursorPrev = '';
$activeCase = null;
$activeCaseError = '';

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

        $queryApi = http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        $url = get_api_base() . '/api/clinical/index.php/patients/' . rawurlencode($patientId) . '/timeline'
            . '?' . $queryApi;

        $fetch = fetch_http_json($url, 4, 2);
        $raw = $fetch['raw'];
        $status = (int)($fetch['status'] ?? 0);
        $headers = is_array($fetch['headers'] ?? null) ? $fetch['headers'] : [];
        $attempts = (int)($fetch['attempts'] ?? 1);

        if ($raw === false) {
            $errorMessage = 'No se pudo cargar el historial. Verifique que el servicio clínico (API) esté activo y reintente.';
            $errorTechnicalDetails = "status: {$status}\nurl: {$url}\nattempts: {$attempts}\nerror: " . (string)($fetch['error'] ?? '') . "\nheaders:\n" . implode("\n", $headers);
        } elseif ($status >= 400) {
            $errorMessage = 'No se pudo cargar el historial. Verifique que el servicio clínico (API) esté activo y reintente.';
            $errorTechnicalDetails = "status: {$status}\nurl: {$url}\nattempts: {$attempts}\nheaders:\n" . implode("\n", $headers) . "\n\nbody_snippet:\n" . (string)($fetch['body_snippet'] ?? '');
        } else {
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                $errorMessage = 'No se pudo cargar el historial. Verifique que el servicio clínico (API) esté activo y reintente.';
                $errorTechnicalDetails = "status: {$status}\nurl: {$url}\nattempts: {$attempts}\nheaders:\n" . implode("\n", $headers) . "\n\nbody_snippet:\n" . (string)($fetch['body_snippet'] ?? '');
            } elseif (($decoded['ok'] ?? false) !== true) {
                $errorMessage = 'No se pudo cargar el historial. Verifique que el servicio clínico (API) esté activo y reintente.';
                $errorTechnicalDetails = "status: {$status}\nurl: {$url}\nattempts: {$attempts}\nheaders:\n" . implode("\n", $headers) . "\n\napi_message: " . (string)($decoded['message'] ?? '');
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

if ($patientId !== '') {
    $caseUrl = get_api_base() . '/api/clinical/index.php/patients/' . rawurlencode($patientId) . '/cases/active';
    $caseContext = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 8,
            'ignore_errors' => true,
            'header' => "Accept: application/json\r\n",
        ],
    ]);
    $caseRaw = @file_get_contents($caseUrl, false, $caseContext);
    if ($caseRaw !== false) {
        $caseDecoded = json_decode($caseRaw, true);
        if (is_array($caseDecoded) && ($caseDecoded['ok'] ?? false) === true) {
            $caseData = $caseDecoded['data'] ?? null;
            $activeCase = is_array($caseData) ? $caseData : null;
        } elseif (is_array($caseDecoded)) {
            $activeCaseError = trim((string)($caseDecoded['message'] ?? ''));
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
$activeCaseId = (is_array($activeCase) && isset($activeCase['case_id'])) ? (string)$activeCase['case_id'] : '';
$activeCaseItemsCount = 0;
if ($activeCaseId !== '') {
    foreach ($appointmentItems as $it) {
        if ((string)($it['case_id'] ?? '') === $activeCaseId) {
            $activeCaseItemsCount++;
        }
    }
    foreach ($encounterOrder as $ek) {
        $enc = is_array($encounters[$ek]['raw'] ?? null) ? $encounters[$ek]['raw'] : [];
        if ((string)($enc['case_id'] ?? '') === $activeCaseId) {
            $activeCaseItemsCount++;
        }
    }
    foreach ($orphanDocs as $docIt) {
        if ((string)($docIt['case_id'] ?? '') === $activeCaseId) {
            $activeCaseItemsCount++;
        }
    }
}

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
    .clinical-historial .is-in-active-case{
      border-left: 4px solid var(--mm-borde-input, #00B0C5);
      background: linear-gradient(90deg, rgba(0,176,197,.06) 0%, rgba(0,176,197,0) 24%);
    }
    .clinical-historial .only-active-case-note{
      background: var(--mm-header-top, #EAF6FB);
      border: 1px solid rgba(0,176,197,.35);
      color: var(--mm-barra-vigencia, #003152);
      border-radius: .6rem;
      padding: .5rem .75rem;
      font-size: .875rem;
      margin-bottom: .75rem;
    }
    .clinical-historial .encounter-doc-preview{
      border: 1px solid rgba(0,0,0,.08);
      border-radius: .5rem;
      padding: .5rem .6rem;
      background: #fff;
    }
    .clinical-historial .encounter-doc-preview .doc-line{
      padding: .25rem 0;
      border-bottom: 1px dashed rgba(0,0,0,.08);
      font-size: .88rem;
    }
    .clinical-historial .encounter-doc-preview .doc-line:last-child{
      border-bottom: 0;
      padding-bottom: 0;
    }
  </style>
HTML;
if (!$embed) {
    require_once __DIR__ . '/../../_partials/mm_shell_top.php';
} else {
    render_embed_css($embed);
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

  <div class="btn-group mb-3" role="group" aria-label="Filtros del historial de atención" data-role="timeline-filters">
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

  <?php if ($patientId !== ''): ?>
    <div class="mm-card mb-3">
      <div class="body d-flex flex-wrap justify-content-between align-items-center gap-2">
        <?php if (is_array($activeCase) && $activeCase !== []): ?>
          <div>
            <span class="badge text-bg-success me-2">Caso activo</span>
            <strong><?php echo h((string)($activeCase['title'] ?? 'Caso clínico')); ?></strong>
          </div>
          <div class="d-flex gap-2">
            <span class="small text-secondary align-self-center" data-role="active-case-counter">Items en este caso: <?php echo h((string)$activeCaseItemsCount); ?></span>
            <button type="button" class="btn btn-sm btn-outline-success" data-action="toggle-only-active-case">Ver solo este caso</button>
            <button
              type="button"
              class="btn btn-sm btn-outline-primary"
              data-action="rename-active-case"
              data-case-id="<?php echo h((string)($activeCase['case_id'] ?? '')); ?>"
            >Renombrar</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-action="open-cases-modal">Ver casos</button>
          </div>
        <?php else: ?>
          <div>
            <span class="badge text-bg-secondary me-2">Sin caso clínico</span>
            <span class="text-secondary">Crea un caso para agrupar eventos del historial.</span>
          </div>
          <div>
            <button type="button" class="btn btn-sm btn-primary" data-action="create-clinical-case">Crear caso clínico</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-action="open-cases-modal">Ver casos</button>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <div class="alert alert-info d-none py-2 mb-3" data-role="recent-case-suggestion">
      <span data-role="recent-case-suggestion-text"></span>
      <div class="mt-2 d-flex gap-2">
        <button type="button" class="btn btn-sm btn-primary" data-action="assign-recent-to-active-case">Agregar recientes</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-action="snooze-recent-case-suggestion">No por ahora</button>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($resolveErrorMsg !== ''): ?>
    <div class="alert alert-danger"><?php echo h($resolveErrorMsg); ?></div>
  <?php endif; ?>
  <?php if ($activeCaseError !== ''): ?>
    <div class="alert alert-warning py-2"><?php echo h($activeCaseError); ?></div>
  <?php endif; ?>

  <?php if ($patientId === ''): ?>
    <?php if ($embed): ?>
      <div class="alert alert-info py-2 mb-2">Sin <code>patient_id</code>.</div>
    <?php else: ?>
      <div class="alert alert-info">Captura un <code>patient_id</code> para consultar el historial de atención.</div>
    <?php endif; ?>
  <?php elseif ($errorMessage !== ''): ?>
    <div class="alert alert-danger">
      <?php echo h($errorMessage); ?>
      <?php if ($errorTechnicalDetails !== ''): ?>
        <details class="mt-2">
          <summary>Detalles técnicos</summary>
          <pre class="mb-0 mt-2 small"><?php echo h($errorTechnicalDetails); ?></pre>
        </details>
      <?php endif; ?>
    </div>
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

    <div class="only-active-case-note d-none" data-role="only-active-case-note">Mostrando solo items del caso activo.</div>
    <div class="vstack gap-2">
      <?php foreach ($appointmentItems as $item): ?>
        <?php
        $agenda = is_array($item['agenda'] ?? null) ? $item['agenda'] : [];
        $links = is_array($item['links'] ?? null) ? $item['links'] : [];
        $appointmentRef = trim((string)($links['appointment_id'] ?? ''));
        $isInActiveCase = ($activeCaseId !== '' && (string)($item['case_id'] ?? '') === $activeCaseId);
        $itemCaseId = trim((string)($item['case_id'] ?? ''));
        ?>
        <?php $appointmentEncounterKey = trim((string)($item['encounter_key'] ?? '')); ?>
        <article class="mm-card <?php echo $isInActiveCase ? 'is-in-active-case' : ''; ?>" data-timeline-item="1" data-case-id="<?php echo h($itemCaseId); ?>" data-item-type="appointment" data-item-ref="<?php echo h($appointmentRef); ?>" data-encounter-key="<?php echo h($appointmentEncounterKey); ?>">
          <div class="body">
            <?php if (!empty($item['case_id'])): ?>
              <div class="mb-2"><span class="badge text-bg-info">Caso: <?php echo h((string)($item['case_title'] ?? '')); ?></span></div>
            <?php elseif (is_array($activeCase) && $appointmentRef !== ''): ?>
              <div class="mb-2">
                <button
                  type="button"
                  class="btn btn-sm btn-outline-success"
                  data-action="assign-case-item"
                  data-case-id="<?php echo h((string)($activeCase['case_id'] ?? '')); ?>"
                  data-item-type="appointment"
                  data-item-ref="<?php echo h($appointmentRef); ?>"
                >Agregar a caso activo</button>
              </div>
            <?php endif; ?>
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
            <?php if ($appointmentEncounterKey !== ''): ?>
              <div class="mt-2">
                <a class="btn btn-sm btn-outline-secondary" href="/modules/clinical/ui/encounter.php?<?php echo h(carry_embed_params(['encounter_key' => $appointmentEncounterKey])); ?>" data-embed-nav data-nav-mode="encounter" data-encounter-key="<?php echo h($appointmentEncounterKey); ?>">Ver episodio</a>
              </div>
            <?php endif; ?>
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
        $encounterDocCount = count($clinicalDocs);
        $encounterPreviewDocs = array_slice($clinicalDocs, 0, 3);
        $encCaseId = trim((string)($rawEncounter['case_id'] ?? ''));
        $encInActiveCase = ($activeCaseId !== '' && $encCaseId === $activeCaseId);
        ?>
        <article class="mm-card <?php echo $encInActiveCase ? 'is-in-active-case' : ''; ?>" data-timeline-item="1" data-case-id="<?php echo h($encCaseId); ?>" data-item-type="encounter" data-item-ref="<?php echo h($ek); ?>" data-encounter-key="<?php echo h($ek); ?>">
          <div class="body">
            <?php if (!empty($rawEncounter['case_id'])): ?>
              <div class="mb-2"><span class="badge text-bg-info">Caso: <?php echo h((string)($rawEncounter['case_title'] ?? '')); ?></span></div>
            <?php elseif (is_array($activeCase) && $ek !== ''): ?>
              <div class="mb-2">
                <button
                  type="button"
                  class="btn btn-sm btn-outline-success"
                  data-action="assign-case-item"
                  data-case-id="<?php echo h((string)($activeCase['case_id'] ?? '')); ?>"
                  data-item-type="encounter"
                  data-item-ref="<?php echo h($ek); ?>"
                >Agregar a caso activo</button>
              </div>
            <?php endif; ?>
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
            <?php if ($ek !== ''): ?>
              <div class="mt-2">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-action="open-encounter-detail" data-encounter-key="<?php echo h($ek); ?>">Ver detalle</button>
                <?php if ($isAppointmentEncounter): ?>
                  <a class="btn btn-sm btn-outline-primary" href="/modules/clinical/ui/encounter.php?<?php echo h(carry_embed_params(['encounter_key' => $ek])); ?>" data-embed-nav data-nav-mode="encounter" data-encounter-key="<?php echo h($ek); ?>">Ver atención</a>
                <?php endif; ?>
              </div>
            <?php endif; ?>

            <div class="mt-3">
              <div class="d-flex align-items-center justify-content-between mb-2">
                <div class="small fw-semibold">Documentos: <?php echo (int)$encounterDocCount; ?></div>
                <?php if ($encounterDocCount > 3): ?>
                  <button type="button" class="btn btn-sm btn-outline-secondary" data-action="open-encounter-detail" data-encounter-key="<?php echo h($ek); ?>">Ver todos (<?php echo (int)$encounterDocCount; ?>)</button>
                <?php endif; ?>
              </div>
              <div class="encounter-doc-preview">
                <?php if ($encounterPreviewDocs === []): ?>
                  <div class="small text-secondary">Sin documentos clínicos en esta atención</div>
                <?php else: ?>
                  <?php foreach ($encounterPreviewDocs as $pdoc): ?>
                    <?php
                    $pType = trim((string)($pdoc['document_type'] ?? '-'));
                    $pSummary = trim((string)($pdoc['summary'] ?? ''));
                    $pEvent = trim((string)($pdoc['event_datetime'] ?? ''));
                    $pDateShort = ($pEvent !== '') ? substr($pEvent, 0, 16) : '-';
                    ?>
                    <div class="doc-line">
                      <div><strong><?php echo h($pType); ?></strong> · <span class="text-secondary"><?php echo h($pDateShort); ?></span></div>
                      <div class="text-secondary"><?php echo h($pSummary !== '' ? $pSummary : '-'); ?></div>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </div>

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
                    <div class="border rounded p-2 small" data-item-type="document" data-document-uuid="<?php echo h($docUuid); ?>">
                      <?php if (!empty($docItem['case_id'])): ?>
                        <div class="mb-1"><span class="badge text-bg-info">Caso: <?php echo h((string)($docItem['case_title'] ?? '')); ?></span></div>
                      <?php elseif (is_array($activeCase) && $docUuid !== ''): ?>
                        <div class="mb-1">
                          <button
                            type="button"
                            class="btn btn-sm btn-outline-success"
                            data-action="assign-case-item"
                            data-case-id="<?php echo h((string)($activeCase['case_id'] ?? '')); ?>"
                            data-item-type="document"
                            data-item-ref="<?php echo h($docUuid); ?>"
                          >Agregar a caso activo</button>
                        </div>
                      <?php endif; ?>
                      <div><strong><?php echo h((string)($doc['document_type'] ?? '-')); ?></strong></div>
                      <div class="text-secondary"><?php echo h((string)($doc['summary'] ?? '-')); ?></div>
                      <?php if ($docUuid !== ''): ?>
                        <div class="mt-1">
                          <a class="btn btn-sm btn-outline-secondary" href="/modules/clinical/ui/document.php?<?php echo h(carry_embed_params(['uuid' => $docUuid])); ?>" data-embed-nav data-nav-mode="document" data-uuid="<?php echo h($docUuid); ?>">Ver documento</a>
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
          $docCaseId = trim((string)($docItem['case_id'] ?? ''));
          $docInActiveCase = ($activeCaseId !== '' && $docCaseId === $activeCaseId);
          ?>
          <article class="mm-card <?php echo $docInActiveCase ? 'is-in-active-case' : ''; ?>" data-timeline-item="1" data-case-id="<?php echo h($docCaseId); ?>" data-item-type="document" data-item-ref="<?php echo h($docUuid); ?>" data-document-uuid="<?php echo h($docUuid); ?>">
            <div class="body">
              <?php if (!empty($docItem['case_id'])): ?>
                <div class="mb-2"><span class="badge text-bg-info">Caso: <?php echo h((string)($docItem['case_title'] ?? '')); ?></span></div>
              <?php elseif (is_array($activeCase) && $docUuid !== ''): ?>
                <div class="mb-2">
                  <button
                    type="button"
                    class="btn btn-sm btn-outline-success"
                    data-action="assign-case-item"
                    data-case-id="<?php echo h((string)($activeCase['case_id'] ?? '')); ?>"
                    data-item-type="document"
                    data-item-ref="<?php echo h($docUuid); ?>"
                  >Agregar a caso activo</button>
                </div>
              <?php endif; ?>
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
                  <a class="btn btn-sm btn-outline-primary" href="/modules/clinical/ui/document.php?<?php echo h(carry_embed_params(['uuid' => $docUuid])); ?>" data-embed-nav data-nav-mode="document" data-uuid="<?php echo h($docUuid); ?>">Ver documento</a>
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
<div class="modal fade" id="encounterDetailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Detalle de atención</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <div id="encounterDetailLoading" class="text-secondary small d-none">Cargando detalle...</div>
        <div id="encounterDetailError" class="alert alert-danger d-none mb-2">No se pudo cargar el detalle del encounter.</div>
        <div id="encounterDetailMeta" class="small text-secondary mb-2 d-none"></div>
        <div id="encounterDetailList" class="vstack gap-2"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>
<div class="modal fade" id="clinicalCasesModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Casos clínicos</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <div id="casesModalLoading" class="text-secondary small d-none">Cargando casos...</div>
        <div id="casesModalEmpty" class="alert alert-secondary d-none mb-0">Sin casos clínicos.</div>
        <div id="casesModalList" class="vstack gap-2"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal" data-action="close-cases-modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>
<script>
  (function () {
    var patientId = <?php echo json_encode($patientId, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    var apiBase = <?php echo json_encode(get_api_base(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    var activeCaseId = <?php echo json_encode($activeCaseId, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    var onlyActiveCaseStorageKey = 'mxmed_historial_only_active_case:' + String(patientId || '');
    var casesModalEl = document.getElementById('clinicalCasesModal');
    var casesModalList = document.getElementById('casesModalList');
    var casesModalEmpty = document.getElementById('casesModalEmpty');
    var casesModalLoading = document.getElementById('casesModalLoading');
    var casesModalInstance = null;
    if (casesModalEl && window.bootstrap && window.bootstrap.Modal) {
      casesModalInstance = window.bootstrap.Modal.getOrCreateInstance(casesModalEl);
    }
    var onlyActiveCaseBtn = document.querySelector('[data-action="toggle-only-active-case"]');
    var onlyActiveCaseNotice = document.querySelector('[data-role="only-active-case-note"]');
    var recentSuggestion = document.querySelector('[data-role="recent-case-suggestion"]');
    var recentSuggestionText = document.querySelector('[data-role="recent-case-suggestion-text"]');
    var onlyActiveCaseEnabled = false;
    var encounterDetailModalEl = document.getElementById('encounterDetailModal');
    var encounterDetailLoading = document.getElementById('encounterDetailLoading');
    var encounterDetailError = document.getElementById('encounterDetailError');
    var encounterDetailMeta = document.getElementById('encounterDetailMeta');
    var encounterDetailList = document.getElementById('encounterDetailList');
    var encounterDetailModalInstance = null;
    if (encounterDetailModalEl && window.bootstrap && window.bootstrap.Modal) {
      encounterDetailModalInstance = window.bootstrap.Modal.getOrCreateInstance(encounterDetailModalEl);
    }
    var debugMode = false;
    try {
      debugMode = new URLSearchParams(window.location.search || '').get('debug') === '1';
    } catch (_) {
      debugMode = false;
    }
    var recentCandidates = [];
    var recentSuggestStorageKey = 'mxmed_historial_snooze_suggest:' + String(patientId || '');
    try {
      onlyActiveCaseEnabled = activeCaseId !== '' && localStorage.getItem(onlyActiveCaseStorageKey) === '1';
    } catch (_) {
      onlyActiveCaseEnabled = false;
    }

    function applyOnlyActiveCaseFilter() {
      var timelineItems = document.querySelectorAll('[data-timeline-item="1"]');
      timelineItems.forEach(function (item) {
        if (!onlyActiveCaseEnabled || activeCaseId === '') {
          item.classList.remove('d-none');
          return;
        }
        var itemCaseId = String(item.getAttribute('data-case-id') || '').trim();
        item.classList.toggle('d-none', itemCaseId !== activeCaseId);
      });
      if (onlyActiveCaseNotice) {
        onlyActiveCaseNotice.classList.toggle('d-none', !onlyActiveCaseEnabled || activeCaseId === '');
      }
      if (onlyActiveCaseBtn) {
        onlyActiveCaseBtn.textContent = (onlyActiveCaseEnabled && activeCaseId !== '') ? 'Ver todos' : 'Ver solo este caso';
      }
    }

    function setOnlyActiveCaseEnabled(nextValue) {
      onlyActiveCaseEnabled = !!nextValue && activeCaseId !== '';
      try {
        if (activeCaseId === '') {
          localStorage.removeItem(onlyActiveCaseStorageKey);
        } else {
          localStorage.setItem(onlyActiveCaseStorageKey, onlyActiveCaseEnabled ? '1' : '0');
        }
      } catch (_) {}
      applyOnlyActiveCaseFilter();
    }

    function recentSnoozed() {
      try {
        var ts = Number(localStorage.getItem(recentSuggestStorageKey) || '0');
        if (!Number.isFinite(ts) || ts <= 0) return false;
        return (Date.now() - ts) < (24 * 60 * 60 * 1000);
      } catch (_) {
        return false;
      }
    }

    function computeRecentCandidates() {
      if (!activeCaseId) return [];
      var nodes = Array.from(document.querySelectorAll('[data-timeline-item="1"]')).slice(0, 10);
      return nodes.map(function (node) {
        return {
          caseId: String(node.getAttribute('data-case-id') || '').trim(),
          itemType: String(node.getAttribute('data-item-type') || '').trim(),
          itemRef: String(node.getAttribute('data-item-ref') || '').trim()
        };
      }).filter(function (item) {
        return item.caseId === '' && item.itemType !== '' && item.itemRef !== '';
      });
    }

    function renderRecentSuggestion() {
      if (!recentSuggestion || !recentSuggestionText) return;
      recentCandidates = computeRecentCandidates();
      var show = activeCaseId !== '' && recentCandidates.length >= 2 && !recentSnoozed();
      recentSuggestion.classList.toggle('d-none', !show);
      if (show) {
        recentSuggestionText.textContent = 'Hay ' + recentCandidates.length + ' eventos recientes sin caso. ¿Agregar al caso activo?';
      }
    }

    async function apiJson(url, options) {
      var response = await fetch(url, Object.assign({
        headers: { 'Accept': 'application/json' },
        credentials: 'same-origin'
      }, options || {}));
      var payload = null;
      try {
        payload = await response.json();
      } catch (_) {
        payload = null;
      }
      if (!payload || payload.ok !== true) {
        var message = (payload && payload.message) ? String(payload.message) : ('HTTP ' + response.status);
        throw new Error(message);
      }
      return payload;
    }

    async function loadActiveCase(pid) {
      if (!pid) return null;
      var url = apiBase + '/api/clinical/index.php/patients/' + encodeURIComponent(pid) + '/cases/active';
      var payload = await apiJson(url, { method: 'GET' });
      return payload.data || null;
    }

    async function createCase(pid, title) {
      var url = apiBase + '/api/clinical/index.php/patients/' + encodeURIComponent(pid) + '/cases';
      var payload = await apiJson(url, {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({ title: title || 'Caso clínico' }),
        credentials: 'same-origin'
      });
      return payload.data || null;
    }

    async function renameCase(caseId, title) {
      var url = apiBase + '/api/clinical/index.php/cases/' + encodeURIComponent(String(caseId || ''));
      var payload = await apiJson(url, {
        method: 'PATCH',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({ title: title }),
        credentials: 'same-origin'
      });
      return payload.data || null;
    }

    async function listCases(pid) {
      if (!pid) return [];
      var url = apiBase + '/api/clinical/index.php/patients/' + encodeURIComponent(pid) + '/cases';
      var payload = await apiJson(url, { method: 'GET' });
      return Array.isArray(payload.data) ? payload.data : [];
    }

    function setCasesModalLoading(flag) {
      if (!casesModalLoading) return;
      casesModalLoading.classList.toggle('d-none', !flag);
    }

    function renderCases(cases) {
      if (!casesModalList || !casesModalEmpty) return;
      casesModalList.innerHTML = '';
      var list = Array.isArray(cases) ? cases : [];
      casesModalEmpty.classList.toggle('d-none', list.length > 0);
      list.forEach(function (item) {
        var row = document.createElement('div');
        row.className = 'border rounded p-2 d-flex flex-wrap justify-content-between align-items-center gap-2';
        var caseId = String(item.case_id || '').trim();
        var status = String(item.status || '').trim();
        var title = String(item.title || 'Caso clínico').trim();
        var active = status === 'active';
        row.innerHTML = ''
          + '<div>'
          + '  <div class="fw-semibold">' + title.replace(/</g, '&lt;') + '</div>'
          + '  <div class="small text-secondary">#' + caseId + ' · ' + (item.updated_at || '-') + '</div>'
          + '</div>'
          + '<div class="d-flex gap-2">'
          + '  <span class="badge ' + (active ? 'text-bg-success' : 'text-bg-secondary') + '">' + (active ? 'Activo' : (status || '')) + '</span>'
          + (active ? '' : '<button type="button" class="btn btn-sm btn-outline-primary" data-action="activate-case" data-case-id="' + caseId + '">Activar</button>')
          + '  <button type="button" class="btn btn-sm btn-outline-secondary" data-action="rename-case-from-modal" data-case-id="' + caseId + '" data-case-title="' + title.replace(/"/g, '&quot;') + '">Renombrar</button>'
          + '</div>';
        casesModalList.appendChild(row);
      });
    }

    async function openCasesModal() {
      if (!patientId) {
        window.alert('patient_id requerido para listar casos.');
        return;
      }
      if (casesModalInstance) {
        casesModalInstance.show();
      } else if (casesModalEl) {
        casesModalEl.style.display = 'block';
        casesModalEl.classList.add('show');
      }
      setCasesModalLoading(true);
      try {
        var cases = await listCases(patientId);
        renderCases(cases);
      } catch (err) {
        window.alert(err.message || 'No se pudieron listar casos clínicos');
      } finally {
        setCasesModalLoading(false);
      }
    }

    async function assignItem(caseId, itemType, itemRef) {
      var url = apiBase + '/api/clinical/index.php/cases/' + encodeURIComponent(String(caseId || '')) + '/items';
      var payload = await apiJson(url, {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({ item_type: itemType, item_ref: itemRef }),
        credentials: 'same-origin'
      });
      return payload.data || null;
    }

    function renderEncounterDetail(payload) {
      var data = payload && typeof payload === 'object'
        ? (payload.data && typeof payload.data === 'object' ? payload.data : payload)
        : {};
      if (encounterDetailMeta) {
        var metaParts = [];
        metaParts.push('Atención: ' + String(data.encounter_key || '-'));
        metaParts.push('Fecha: ' + String(data.event_datetime || '-'));
        encounterDetailMeta.textContent = metaParts.join(' | ');
        encounterDetailMeta.classList.remove('d-none');
      }
      if (!encounterDetailList) return;
      var docs = Array.isArray(data.documents) ? data.documents : [];
      if (docs.length === 0) {
        encounterDetailList.innerHTML = '<div class="alert alert-secondary mb-0">Sin documentos en esta atención.</div>';
        return;
      }
      var html = docs.map(function (doc) {
        var type = String(doc.document_type || '-');
        var title = String(doc.title || '');
        var summary = String(doc.summary || '-');
        var dt = String(doc.event_datetime || '-');
        var header = title ? (type + ' · ' + title) : type;
        return ''
          + '<div class="border rounded p-2">'
          + '  <div class="small"><strong>' + header.replace(/[&<>"]/g, function (m) { return ({'&':'&amp;','<':'&lt;','>':'&gt;','\"':'&quot;'}[m]); }) + '</strong></div>'
          + '  <div class="small text-secondary">' + dt.replace(/[&<>"]/g, function (m) { return ({'&':'&amp;','<':'&lt;','>':'&gt;','\"':'&quot;'}[m]); }) + '</div>'
          + '  <div class="small">' + summary.replace(/[&<>"]/g, function (m) { return ({'&':'&amp;','<':'&lt;','>':'&gt;','\"':'&quot;'}[m]); }) + '</div>'
          + '</div>';
      }).join('');
      encounterDetailList.innerHTML = html;
    }

    async function openEncounterDetail(encounterKey) {
      if (!encounterKey) return;
      if (encounterDetailModalInstance) {
        encounterDetailModalInstance.show();
      } else if (encounterDetailModalEl) {
        // Fallback when Bootstrap JS is not available in embed host.
        encounterDetailModalEl.style.display = 'block';
        encounterDetailModalEl.classList.add('show');
        encounterDetailModalEl.removeAttribute('aria-hidden');
      }
      if (encounterDetailLoading) encounterDetailLoading.classList.remove('d-none');
      if (encounterDetailError) encounterDetailError.classList.add('d-none');
      if (encounterDetailMeta) {
        encounterDetailMeta.textContent = '';
        encounterDetailMeta.classList.add('d-none');
      }
      if (encounterDetailList) {
        encounterDetailList.innerHTML = '';
      }
      try {
        var url = apiBase + '/api/clinical/index.php/encounters/' + encodeURIComponent(String(encounterKey));
        if (debugMode && window.console && typeof window.console.log === 'function') {
          window.console.log('[encounter detail] fetching', url);
        }
        var resp = await fetch(url, { method: 'GET', credentials: 'include' });
        if (debugMode && window.console && typeof window.console.log === 'function') {
          window.console.log('[encounter detail] status', resp.status);
        }
        if (!resp.ok) {
          throw new Error('HTTP ' + resp.status);
        }
        var payload = await resp.json();
        if (!payload || payload.ok !== true) {
          throw new Error((payload && payload.message) ? String(payload.message) : 'No se pudo cargar detalle');
        }
        renderEncounterDetail((payload && payload.data) ? payload.data : payload);
      } catch (_) {
        if (encounterDetailError) encounterDetailError.classList.remove('d-none');
      } finally {
        if (encounterDetailLoading) encounterDetailLoading.classList.add('d-none');
      }
    }

    document.addEventListener('click', function (event) {
      var createBtn = event.target && event.target.closest ? event.target.closest('[data-action="create-clinical-case"]') : null;
      if (createBtn) {
        event.preventDefault();
        createCase(patientId, 'Caso clínico')
          .then(function () { window.location.reload(); })
          .catch(function (err) { window.alert(err.message || 'No se pudo crear caso clínico'); });
        return;
      }

      var renameBtn = event.target && event.target.closest ? event.target.closest('[data-action="rename-active-case"]') : null;
      if (renameBtn) {
        event.preventDefault();
        var caseId = String(renameBtn.getAttribute('data-case-id') || '').trim();
        if (!caseId) return;
        var nextTitle = window.prompt('Nuevo nombre del caso clínico:', '');
        if (nextTitle === null) return;
        nextTitle = String(nextTitle || '').trim();
        if (!nextTitle) return;
        renameCase(caseId, nextTitle)
          .then(function () { window.location.reload(); })
          .catch(function (err) { window.alert(err.message || 'No se pudo renombrar caso clínico'); });
        return;
      }

      var openCasesBtn = event.target && event.target.closest ? event.target.closest('[data-action="open-cases-modal"]') : null;
      if (openCasesBtn) {
        event.preventDefault();
        openCasesModal();
        return;
      }

      var openEncounterDetailBtn = event.target && event.target.closest ? event.target.closest('[data-action="open-encounter-detail"]') : null;
      if (openEncounterDetailBtn) {
        event.preventDefault();
        var encounterKey = String(openEncounterDetailBtn.getAttribute('data-encounter-key') || '').trim();
        if (!encounterKey) return;
        openEncounterDetail(encounterKey);
        return;
      }

      var toggleOnlyCaseBtn = event.target && event.target.closest ? event.target.closest('[data-action="toggle-only-active-case"]') : null;
      if (toggleOnlyCaseBtn) {
        event.preventDefault();
        setOnlyActiveCaseEnabled(!onlyActiveCaseEnabled);
        return;
      }

      var activateCaseBtn = event.target && event.target.closest ? event.target.closest('[data-action="activate-case"]') : null;
      if (activateCaseBtn) {
        event.preventDefault();
        var activateCaseId = String(activateCaseBtn.getAttribute('data-case-id') || '').trim();
        if (!activateCaseId) return;
        apiJson(apiBase + '/api/clinical/index.php/cases/' + encodeURIComponent(activateCaseId) + '/activate', { method: 'POST' })
          .then(function () { window.location.reload(); })
          .catch(function (err) { window.alert(err.message || 'No se pudo activar caso'); });
        return;
      }

      var renameFromModalBtn = event.target && event.target.closest ? event.target.closest('[data-action="rename-case-from-modal"]') : null;
      if (renameFromModalBtn) {
        event.preventDefault();
        var modalCaseId = String(renameFromModalBtn.getAttribute('data-case-id') || '').trim();
        if (!modalCaseId) return;
        var currentTitle = String(renameFromModalBtn.getAttribute('data-case-title') || '').trim();
        var nextModalTitle = window.prompt('Nuevo nombre del caso clínico:', currentTitle);
        if (nextModalTitle === null) return;
        nextModalTitle = String(nextModalTitle || '').trim();
        if (!nextModalTitle) return;
        renameCase(modalCaseId, nextModalTitle)
          .then(function () { window.location.reload(); })
          .catch(function (err) { window.alert(err.message || 'No se pudo renombrar caso'); });
        return;
      }

      var closeCasesBtn = event.target && event.target.closest ? event.target.closest('[data-action="close-cases-modal"]') : null;
      if (closeCasesBtn && !casesModalInstance && casesModalEl) {
        event.preventDefault();
        casesModalEl.classList.remove('show');
        casesModalEl.style.display = 'none';
        return;
      }

      var assignBtn = event.target && event.target.closest ? event.target.closest('[data-action="assign-case-item"]') : null;
      if (assignBtn) {
        event.preventDefault();
        var cId = String(assignBtn.getAttribute('data-case-id') || '').trim();
        var itemType = String(assignBtn.getAttribute('data-item-type') || '').trim();
        var itemRef = String(assignBtn.getAttribute('data-item-ref') || '').trim();
        if (!cId || !itemType || !itemRef) return;
        assignItem(cId, itemType, itemRef)
          .then(function () { window.location.reload(); })
          .catch(function (err) { window.alert(err.message || 'No se pudo asignar item al caso'); });
        return;
      }

      var assignRecentBtn = event.target && event.target.closest ? event.target.closest('[data-action="assign-recent-to-active-case"]') : null;
      if (assignRecentBtn) {
        event.preventDefault();
        if (!activeCaseId || recentCandidates.length < 1) return;
        (async function () {
          var okCount = 0;
          for (var i = 0; i < recentCandidates.length; i += 1) {
            var rc = recentCandidates[i];
            try {
              await assignItem(activeCaseId, rc.itemType, rc.itemRef);
              okCount += 1;
            } catch (_) {}
          }
          window.alert('Listo: se agregaron ' + okCount);
          window.location.reload();
        })();
        return;
      }

      var snoozeRecentBtn = event.target && event.target.closest ? event.target.closest('[data-action="snooze-recent-case-suggestion"]') : null;
      if (snoozeRecentBtn) {
        event.preventDefault();
        try {
          localStorage.setItem(recentSuggestStorageKey, String(Date.now()));
        } catch (_) {}
        renderRecentSuggestion();
        return;
      }

      var trigger = event.target && event.target.closest ? event.target.closest('[data-embed-nav]') : null;
      if (!trigger) return;
      var mode = String(trigger.getAttribute('data-nav-mode') || '').trim();
      if (mode !== 'encounter' && mode !== 'document') return;

      if (!window.parent || window.parent === window || typeof window.parent.postMessage !== 'function') {
        return;
      }

      var payload = { type: 'mxmed:embed:navigate', mode: mode };
      if (mode === 'encounter') {
        var encounterKey = String(trigger.getAttribute('data-encounter-key') || '').trim();
        if (!encounterKey) return;
        payload.encounter_key = encounterKey;
      } else {
        var uuid = String(trigger.getAttribute('data-uuid') || '').trim();
        if (!uuid) return;
        payload.uuid = uuid;
      }

      event.preventDefault();
      window.parent.postMessage(payload, '*');
    }, true);

    if (patientId) {
      loadActiveCase(patientId).catch(function () {});
    }
    applyOnlyActiveCaseFilter();
    renderRecentSuggestion();
  })();
</script>
<?php if ($embed): ?>
<?php clinical_embed_end(); ?>
<?php else: ?>
<?php require_once __DIR__ . '/../../_partials/mm_shell_bottom.php'; ?>
<?php endif; ?>
