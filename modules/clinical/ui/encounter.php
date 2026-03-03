<?php
declare(strict_types=1);

function get_api_base(): string
{
    $env = trim((string)getenv('CLINICAL_API_BASE'));
    if ($env !== '') {
        return rtrim($env, '/');
    }

    $env = trim((string)getenv('MXMED_API_BASE'));
    if ($env !== '') {
        return rtrim($env, '/');
    }

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

    $hostOnly = preg_replace('/:\d+$/', '', $host) ?: '127.0.0.1';
    return $proto . '://' . $hostOnly . ':8091';
}

function normalize_clinical_api_base(string $base): string
{
    $normalized = rtrim(trim($base), '/');
    if ($normalized === '') {
        return '';
    }
    $suffix = '/api/clinical/index.php';
    if (substr($normalized, -strlen($suffix)) === $suffix) {
        $normalized = rtrim(substr($normalized, 0, -strlen($suffix)), '/');
    }
    return $normalized;
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
    echo '<link rel="stylesheet" href="/modules/_partials/mxmed-ui.css?v=1">' . "\n";
}

function http_get_json(string $url, int $timeoutSeconds = 8): ?array
{
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => $timeoutSeconds,
            'ignore_errors' => true,
            'header' => "Accept: application/json\r\n",
        ],
    ]);
    $raw = @file_get_contents($url, false, $context);
    if (!is_string($raw) || $raw === '') {
        return null;
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
}

function http_status_from_headers(?array $headers): int
{
    if (!is_array($headers)) {
        return 0;
    }
    foreach ($headers as $line) {
        if (!is_string($line)) {
            continue;
        }
        if (preg_match('/^HTTP\/\d+(?:\.\d+)?\s+(\d{3})/i', trim($line), $m)) {
            return (int)$m[1];
        }
    }
    return 0;
}

function agenda_actor_role_label(string $role): string
{
    $role = strtolower(trim($role));
    switch ($role) {
        case 'patient':
            return 'Paciente';
        case 'doctor':
            return 'Medico';
        case 'operator':
        case 'operadora':
            return 'Operadora';
        case 'system':
            return 'Sistema';
        default:
            return $role !== '' ? ucfirst($role) : '-';
    }
}

function agenda_modality_label(string $modality): string
{
    $modality = strtolower(trim($modality));
    switch ($modality) {
        case 'presencial':
            return 'Presencial';
        case 'video':
        case 'online':
            return 'Online';
        default:
            return $modality !== '' ? ucfirst($modality) : '-';
    }
}

function clinical_encounter_document_buckets_ui(array $documents): array
{
    $buckets = [
        'vitals' => [],
        'notes' => [],
        'prescriptions' => [],
        'orders' => [],
        'results' => [],
        'procedures' => [],
    ];

    foreach ($documents as $doc) {
        if (!is_array($doc)) {
            continue;
        }
        $type = strtolower(trim((string)($doc['document_type'] ?? '')));
        if (in_array($type, ['vitals', 'vital_signs', 'signs'], true)) {
            $buckets['vitals'][] = $doc;
        } elseif (in_array($type, ['note', 'medical_note', 'evolution_note'], true)) {
            $buckets['notes'][] = $doc;
        } elseif (in_array($type, ['prescription', 'rx'], true)) {
            $buckets['prescriptions'][] = $doc;
        } elseif (in_array($type, ['orders', 'order', 'lab_order', 'imaging_order'], true)) {
            $buckets['orders'][] = $doc;
        } elseif (in_array($type, ['results', 'result', 'lab_result', 'imaging_result'], true)) {
            $buckets['results'][] = $doc;
        } elseif (in_array($type, ['procedure', 'immunization', 'medication_administration', 'wound_care'], true)) {
            $buckets['procedures'][] = $doc;
        }
    }

    return $buckets;
}

$encounterKey = trim((string)($_GET['encounter_key'] ?? ''));
$errorMessage = '';
$encounter = null;
$activeCase = null;
$activeCaseError = '';
$activeCaseSuccess = '';
$isInActiveCase = false;
$isInActiveCaseByAppt = false;
$patientId = '';
$appointmentId = '';
$currentUserId = trim((string)($_SESSION['user_id'] ?? ($_SERVER['PHP_AUTH_USER'] ?? 'qa')));
if ($currentUserId === '') {
    $currentUserId = 'qa';
}
$appointmentData = null;
$appointmentEvents = [];
$appointmentError = '';
$apiBase = normalize_clinical_api_base((string)getenv('CLINICAL_API_BASE'));
if ($apiBase === '') {
    $apiBase = normalize_clinical_api_base(get_api_base());
}
$apiIndexBase = ($apiBase !== '') ? ($apiBase . '/api/clinical/index.php') : '';

if (trim((string)($_GET['flash'] ?? '')) === 'added_case_item') {
    $activeCaseSuccess = 'Agregado al caso activo.';
}

if ($encounterKey !== '') {
    // IMPORTANT (dev mode): use API base for server-side calls to avoid UI->UI recursion.
    if ($apiIndexBase === '') {
        $errorMessage = 'CLINICAL_API_BASE no configurado y get_api_base() vacío';
    } else {
        $encodedEncounterKey = rawurlencode($encounterKey);
        $url = $apiIndexBase . '/encounters/' . $encodedEncounterKey;

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 8,
                'ignore_errors' => true,
                'header' => "Accept: application/json\r\n",
            ],
        ]);

        $raw = @file_get_contents($url, false, $context);
        $status = http_status_from_headers($http_response_header ?? null);
        if ($raw === false) {
            $last = error_get_last();
            $details = trim((string)($last['message'] ?? ''));
            $errorMessage = 'No se pudo consultar la atención.';
            if ($status > 0) {
                $errorMessage .= ' status=' . $status . '.';
            }
            if ($details !== '') {
                $errorMessage .= ' ' . $details;
            }
        } else {
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                $errorMessage = 'Respuesta inválida del endpoint de encounters.';
                if ($status > 0) {
                    $errorMessage .= ' status=' . $status . '.';
                }
            } elseif (($decoded['ok'] ?? false) !== true) {
                $backendMessage = trim((string)($decoded['message'] ?? ''));
                $backendError = trim((string)($decoded['error'] ?? ''));
                $errorMessage = ($backendMessage !== '') ? $backendMessage : 'Error consultando atención.';
                if ($backendError !== '') {
                    $errorMessage .= ' (' . $backendError . ')';
                }
                if ($status > 0) {
                    $errorMessage .= ' status=' . $status . '.';
                }
            } else {
                $data = $decoded['data'] ?? null;
                $encounter = is_array($data) ? $data : null;
                if ($encounter === null) {
                    $errorMessage = 'Atención no disponible.';
                }
            }
        }
    }
}

if ($encounter !== null) {
    $patientId = trim((string)($encounter['patient_id'] ?? (($encounter['links']['patient_id'] ?? ''))));
    $appointmentId = trim((string)($encounter['appointment_id'] ?? ($encounter['links']['appointment_id'] ?? '')));
    if ($appointmentId === '' && $encounterKey !== '' && strpos($encounterKey, 'appt:') === 0) {
        $appointmentId = preg_replace('/^appt:([^#]+)(#enc:.*)?$/', '$1', $encounterKey) ?? '';
        $appointmentId = trim((string)$appointmentId);
    }
    if ($patientId !== '') {
        $activeCaseUrl = $apiIndexBase . '/patients/' . rawurlencode($patientId) . '/cases/active';
        $activeCaseResp = http_get_json($activeCaseUrl);
        if (is_array($activeCaseResp) && ($activeCaseResp['ok'] ?? false) === true) {
            $caseData = $activeCaseResp['data'] ?? null;
            $activeCase = is_array($caseData) ? $caseData : null;
            $caseId = (int)($activeCase['case_id'] ?? 0);
            if ($caseId > 0) {
                $caseItemsUrl = $apiIndexBase . '/cases/' . rawurlencode((string)$caseId) . '/items?limit=200';
                $caseItemsResp = http_get_json($caseItemsUrl);
                if (is_array($caseItemsResp) && ($caseItemsResp['ok'] ?? false) === true) {
                    $caseItems = is_array($caseItemsResp['data'] ?? null) ? $caseItemsResp['data'] : [];
                    $caseMap = [];
                    foreach ($caseItems as $row) {
                        if (!is_array($row)) {
                            continue;
                        }
                        $itemType = trim((string)($row['item_type'] ?? ''));
                        $itemRef = trim((string)($row['item_ref'] ?? ''));
                        if ($itemType === '' || $itemRef === '') {
                            continue;
                        }
                        $caseMap[$itemType . '|' . $itemRef] = true;
                    }
                    $isInActiveCaseByAppt = ($appointmentId !== '') && isset($caseMap['appointment|appt:' . $appointmentId]);
                    // Keep membership rule aligned with timeline: derive by appointment_id.
                    $isInActiveCase = $isInActiveCaseByAppt;
                } else {
                    $activeCaseError = 'No se pudo consultar los items del caso activo.';
                }
            }
        } elseif (is_array($activeCaseResp)) {
            $activeCaseError = trim((string)($activeCaseResp['message'] ?? 'No se pudo consultar el caso activo.'));
        } else {
            $activeCaseError = 'No se pudo consultar el caso activo.';
        }
    }
}

if ($encounter !== null && $appointmentId !== '') {
    require_once __DIR__ . '/../../agenda/controllers/AppointmentsController.php';
    require_once __DIR__ . '/../../agenda/controllers/AppointmentEventsController.php';

    try {
        $appointmentsController = new \Agenda\Controllers\AppointmentsController();
        $appointmentResp = $appointmentsController->show($appointmentId);
        if (is_array($appointmentResp) && ($appointmentResp['ok'] ?? false) === true && is_array($appointmentResp['data'] ?? null)) {
            $appointmentData = $appointmentResp['data'];
        } else {
            $appointmentError = trim((string)($appointmentResp['message'] ?? ''));
        }
    } catch (Throwable $e) {
        $appointmentError = trim((string)$e->getMessage());
    }

    try {
        $eventsController = new \Agenda\Controllers\AppointmentEventsController();
        $eventsResp = $eventsController->index($appointmentId, ['limit' => 50]);
        if (is_array($eventsResp) && ($eventsResp['ok'] ?? false) === true && is_array($eventsResp['data'] ?? null)) {
            $appointmentEvents = $eventsResp['data'];
        } elseif ($appointmentError === '') {
            $appointmentError = trim((string)($eventsResp['message'] ?? ''));
        }
    } catch (Throwable $e) {
        if ($appointmentError === '') {
            $appointmentError = trim((string)$e->getMessage());
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $encounter !== null) {
    $action = trim((string)($_POST['action'] ?? ''));
    if ($action === 'add_active_case_appointment') {
        $caseId = (int)($activeCase['case_id'] ?? 0);
        if ($caseId <= 0 || $appointmentId === '') {
            $activeCaseError = 'No se pudo agregar al caso activo.';
        } elseif (!$isInActiveCase) {
            $itemRef = 'appt:' . $appointmentId;
            $postUrl = $apiIndexBase . '/cases/' . rawurlencode((string)$caseId) . '/items';
            $postPayload = json_encode([
                'item_type' => 'appointment',
                'item_ref' => $itemRef,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (!is_string($postPayload)) {
                $postPayload = '{"item_type":"appointment","item_ref":""}';
            }
            $postContext = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'timeout' => 8,
                    'ignore_errors' => true,
                    'header' => "Accept: application/json\r\nContent-Type: application/json\r\n",
                    'content' => $postPayload,
                ],
            ]);
            $postRaw = @file_get_contents($postUrl, false, $postContext);
            if (!is_string($postRaw) || $postRaw === '') {
                $activeCaseError = 'No se pudo agregar al caso activo.';
            } else {
                $postDecoded = json_decode($postRaw, true);
                if (!is_array($postDecoded) || ($postDecoded['ok'] ?? false) !== true) {
                    $activeCaseError = trim((string)($postDecoded['message'] ?? 'No se pudo agregar al caso activo.'));
                    if ($activeCaseError === '') {
                        $activeCaseError = 'No se pudo agregar al caso activo.';
                    }
                } else {
                    $redirectParams = ['encounter_key' => $encounterKey, 'flash' => 'added_case_item'];
                    if (trim((string)($_GET['embed'] ?? '')) === '1') {
                        $redirectParams['embed'] = '1';
                    }
                    header('Location: /modules/clinical/ui/encounter.php?' . http_build_query($redirectParams));
                    exit;
                }
            }
        }
    }
}

$documents = is_array($encounter['documents'] ?? null) ? $encounter['documents'] : [];
$documentBuckets = clinical_encounter_document_buckets_ui($documents);
$vitals = is_array($encounter['vitals'] ?? null) ? $encounter['vitals'] : $documentBuckets['vitals'];
$notes = is_array($encounter['notes'] ?? null) ? $encounter['notes'] : $documentBuckets['notes'];
$prescriptions = is_array($encounter['prescriptions'] ?? null) ? $encounter['prescriptions'] : $documentBuckets['prescriptions'];
$orders = is_array($encounter['orders'] ?? null) ? $encounter['orders'] : $documentBuckets['orders'];
$results = is_array($encounter['results'] ?? null) ? $encounter['results'] : $documentBuckets['results'];
$procedures = is_array($encounter['procedures'] ?? null) ? $encounter['procedures'] : $documentBuckets['procedures'];
$encounterStatus = strtolower(trim((string)($encounter['status'] ?? 'open')));
$encounterClosedAt = trim((string)($encounter['closed_at'] ?? ''));
$encounterAutoNoteUuidFinal = trim((string)($encounter['auto_note_uuid_final'] ?? ''));
$autoNoteFinalHref = $encounterAutoNoteUuidFinal !== ''
    ? ('/modules/clinical/ui/viewer.php?uuid=' . rawurlencode($encounterAutoNoteUuidFinal) . ($embed ? '&embed=1' : ''))
    : '';
$documentsJson = json_encode($documents, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if (!is_string($documentsJson)) {
    $documentsJson = '[]';
}
require_once __DIR__ . '/../../_partials/clinical_embed.php';
$embed = is_embed_request();

// Shell MXMed
if (!$embed) {
    $pageTitle = 'Atención clínica';
    require_once __DIR__ . '/../../_partials/mm_shell_top.php';
} else {
    render_embed_css($embed);
    clinical_embed_start();
}
?>
<style>
  [data-role="doc-overlay"]{
    position: fixed;
    inset: 0;
    z-index: 1060;
  }
  [data-role="doc-overlay"][hidden]{
    display: none !important;
  }
  [data-role="doc-overlay-backdrop"]{
    position: absolute;
    inset: 0;
    background: rgba(0,0,0,.55);
  }
  [data-role="doc-overlay-panel"]{
    position: relative;
    width: min(1200px, calc(100vw - 2rem));
    height: 90vh;
    margin: 5vh auto;
    background: #fff;
    border-radius: .75rem;
    border: 1px solid rgba(0,0,0,.08);
    box-shadow: 0 20px 40px rgba(0,0,0,.25);
    overflow: hidden;
    display: flex;
    flex-direction: column;
  }
  [data-role="doc-overlay-head"]{
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .75rem;
    padding: .65rem .85rem;
    border-bottom: 1px solid rgba(0,0,0,.1);
  }
  [data-role="doc-overlay-iframe"]{
    width: 100%;
    height: 100%;
    border: 0;
    flex: 1 1 auto;
    background: #fff;
  }
</style>
<div class="<?php echo $embed ? 'py-1' : 'container py-4'; ?>">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Atención clínica</h1>
    <div class="d-flex gap-2">
      <?php if ($encounterKey !== ''): ?>
        <a class="btn btn-outline-primary btn-sm" href="/modules/clinical/ui/historial.php?<?php echo h(carry_embed_params(['encounter_key' => $encounterKey, 'include' => 'agenda,clinical', 'limit' => 20])); ?>">Ver historial del paciente</a>
      <?php endif; ?>
      <a class="btn btn-outline-secondary btn-sm" href="javascript:history.back()">Volver</a>
    </div>
  </div>

  <p class="text-secondary">encounter_key: <code><?php echo h($encounterKey !== '' ? $encounterKey : '-'); ?></code></p>

  <?php if ($encounterKey === ''): ?>
    <div class="alert alert-warning">encounter_key requerido.</div>
  <?php elseif ($errorMessage !== ''): ?>
    <div class="alert alert-danger"><?php echo h($errorMessage); ?></div>
  <?php else: ?>
    <?php
      $agendaStartAt = trim((string)($appointmentData['start_at'] ?? $appointmentId));
      $agendaEndAt = trim((string)($appointmentData['end_at'] ?? ''));
      $agendaStatus = trim((string)($appointmentData['status'] ?? ''));
      $agendaModality = trim((string)($appointmentData['modality'] ?? ''));
      $agendaChannelOrigin = trim((string)($appointmentData['channel_origin'] ?? ''));
      $agendaCreatedByRole = trim((string)($appointmentData['created_by_role'] ?? ''));
      $agendaCreatedById = trim((string)($appointmentData['created_by_id'] ?? ''));
      $agendaCancelledAt = trim((string)($appointmentData['cancelled_at'] ?? ($appointmentData['canceled_at'] ?? '')));
      $agendaReasonCode = trim((string)($appointmentData['reason_code'] ?? ''));
      $agendaReasonText = trim((string)($appointmentData['reason_text'] ?? ''));
      if (($agendaReasonCode === '' || $agendaReasonText === '') && $appointmentEvents !== []) {
          foreach (array_reverse($appointmentEvents) as $ev) {
              if (!is_array($ev)) {
                  continue;
              }
              if ($agendaReasonCode === '') {
                  $agendaReasonCode = trim((string)($ev['reason_code'] ?? ($ev['motivo_code'] ?? '')));
              }
              if ($agendaReasonText === '') {
                  $agendaReasonText = trim((string)($ev['reason_text'] ?? ($ev['motivo_text'] ?? '')));
              }
              if ($agendaReasonCode !== '' || $agendaReasonText !== '') {
                  break;
              }
          }
      }
    ?>
    <?php if ($activeCaseSuccess !== ''): ?>
      <div class="alert alert-success mb-3"><?php echo h($activeCaseSuccess); ?></div>
    <?php endif; ?>
    <?php if ($activeCase === null): ?>
      <div class="alert alert-secondary d-flex justify-content-between align-items-center mb-3">
        <div>Sin caso clínico activo</div>
        <span class="badge text-bg-secondary">Sin caso</span>
      </div>
    <?php else: ?>
      <div class="alert <?php echo $isInActiveCase ? 'alert-success' : 'alert-warning'; ?> d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
          <strong>Caso activo:</strong> <?php echo h((string)($activeCase['title'] ?? 'Caso clínico')); ?>
          <span class="text-secondary">(ID <?php echo h((string)($activeCase['case_id'] ?? '')); ?>)</span>
        </div>
        <div class="d-flex align-items-center gap-2">
          <span class="badge <?php echo $isInActiveCase ? 'text-bg-success' : 'text-bg-warning'; ?>">
            <?php echo $isInActiveCase ? 'Incluido en caso activo' : 'No pertenece al caso activo'; ?>
          </span>
          <?php if (!$isInActiveCase): ?>
            <?php if ($appointmentId !== ''): ?>
              <form method="post" class="d-inline" onsubmit="return confirm('¿Agregar esta cita al caso activo?');">
                <input type="hidden" name="action" value="add_active_case_appointment">
                <button type="submit" class="btn btn-sm btn-outline-secondary">Agregar a caso activo</button>
              </form>
            <?php else: ?>
              <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="Sin appointment_id">Agregar a caso activo</button>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
    <?php if ($activeCaseError !== ''): ?>
      <div class="alert alert-warning mb-3"><?php echo h($activeCaseError); ?></div>
    <?php endif; ?>

    <div class="mm-card mb-3">
      <div class="head"><h5>Agenda</h5></div>
      <div class="body small">
        <div><strong>Agendada para:</strong> <?php echo h($agendaStartAt !== '' ? $agendaStartAt : '-'); ?></div>
        <div><strong>Fin estimado:</strong> <?php echo h($agendaEndAt !== '' ? $agendaEndAt : '-'); ?></div>
        <div><strong>Estado:</strong> <?php echo h($agendaStatus !== '' ? $agendaStatus : '-'); ?></div>
        <div><strong>Modalidad:</strong> <?php echo h(agenda_modality_label($agendaModality)); ?></div>
        <div><strong>Origen:</strong> <?php echo h($agendaChannelOrigin !== '' ? $agendaChannelOrigin : '-'); ?></div>
        <div><strong>Creada por:</strong> <?php echo h(agenda_actor_role_label($agendaCreatedByRole)); ?><?php echo $agendaCreatedById !== '' ? ' · ' . h($agendaCreatedById) : ''; ?></div>
        <div><strong>Motivo:</strong> <?php echo h($agendaReasonText !== '' ? $agendaReasonText : ($agendaReasonCode !== '' ? $agendaReasonCode : '-')); ?></div>
        <?php if ($agendaCancelledAt !== ''): ?>
          <div><strong>Cancelada:</strong> <?php echo h($agendaCancelledAt); ?></div>
        <?php endif; ?>
        <?php if ($appointmentError !== ''): ?>
          <div class="text-secondary mt-2">Agenda: <?php echo h($appointmentError); ?></div>
        <?php endif; ?>
      </div>
    </div>

    <div class="mm-card mb-3">
      <div class="head"><h5>Resumen</h5></div>
      <div class="body small">
        <div><strong>Fecha:</strong> <?php echo h((string)($encounter['event_datetime'] ?? '-')); ?></div>
        <div><strong>encounter_id:</strong> <?php echo h((string)($encounter['encounter_id'] ?? '-')); ?></div>
        <div><strong>patient_id:</strong> <?php echo h((string)($encounter['patient_id'] ?? '-')); ?></div>
        <div><strong>appointment_id:</strong> <?php echo h((string)($encounter['appointment_id'] ?? '-')); ?></div>
      </div>
    </div>

    <?php if ($appointmentEvents !== []): ?>
      <div class="mm-card mb-3">
        <div class="head"><h5>Eventos de agenda</h5></div>
        <div class="body">
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr>
                  <th>Fecha</th>
                  <th>Evento</th>
                  <th>Actor</th>
                  <th>Motivo</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($appointmentEvents as $ev): ?>
                  <?php if (!is_array($ev)) continue; ?>
                  <?php
                    $evTs = trim((string)($ev['timestamp'] ?? ($ev['created_at'] ?? '')));
                    $evType = trim((string)($ev['event_type'] ?? ''));
                    $evActorRole = trim((string)($ev['actor_role'] ?? ''));
                    $evActorId = trim((string)($ev['actor_id'] ?? ''));
                    $evReasonCode = trim((string)($ev['reason_code'] ?? ($ev['motivo_code'] ?? '')));
                    $evReasonText = trim((string)($ev['reason_text'] ?? ($ev['motivo_text'] ?? '')));
                  ?>
                  <tr>
                    <td><?php echo h($evTs !== '' ? $evTs : '-'); ?></td>
                    <td><?php echo h($evType !== '' ? $evType : '-'); ?></td>
                    <td><?php echo h(agenda_actor_role_label($evActorRole)); ?><?php echo $evActorId !== '' ? ' · ' . h($evActorId) : ''; ?></td>
                    <td><?php echo h($evReasonText !== '' ? $evReasonText : ($evReasonCode !== '' ? $evReasonCode : '-')); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <div class="mm-card mb-3">
      <div class="head"><h5>Documentos</h5></div>
      <div class="body">
        <div id="encounterDocumentsControls" class="d-none mb-2"></div>
        <div id="encounterDocumentsList" class="vstack gap-2"></div>
      </div>
    </div>

    <div class="row g-3">
      <div class="col-md-2">
        <div class="mm-card h-100">
          <div class="head"><h5>Signos</h5></div>
          <div class="body small text-secondary"><?php echo $vitals === [] ? 'Sin signos' : h((string)count($vitals)); ?></div>
        </div>
      </div>
      <div class="col-md-2">
        <div class="mm-card h-100">
          <div class="head"><h5>Notas</h5></div>
          <div class="body small text-secondary"><?php echo $notes === [] ? 'Sin notas' : h((string)count($notes)); ?></div>
        </div>
      </div>
      <div class="col-md-2">
        <div class="mm-card h-100">
          <div class="head"><h5>Recetas</h5></div>
          <div class="body small text-secondary"><?php echo $prescriptions === [] ? 'Sin recetas' : h((string)count($prescriptions)); ?></div>
        </div>
      </div>
      <div class="col-md-2">
        <div class="mm-card h-100">
          <div class="head"><h5>Órdenes</h5></div>
          <div class="body small text-secondary"><?php echo $orders === [] ? 'Sin órdenes' : h((string)count($orders)); ?></div>
        </div>
      </div>
      <div class="col-md-2">
        <div class="mm-card h-100">
          <div class="head"><h5>Resultados</h5></div>
          <div class="body small text-secondary"><?php echo $results === [] ? 'Sin resultados' : h((string)count($results)); ?></div>
        </div>
      </div>
      <div class="col-md-2">
        <div class="mm-card h-100">
          <div class="head"><h5>Procedimientos</h5></div>
          <div class="body small text-secondary"><?php echo $procedures === [] ? 'Sin procedimientos' : h((string)count($procedures)); ?></div>
        </div>
      </div>
    </div>

    <div class="mm-card mt-3">
      <div class="head"><h5>Cierre</h5></div>
      <div class="body small">
        <?php if ($encounterStatus !== 'closed'): ?>
          <div class="text-secondary mb-3">La consulta sigue abierta. Al cerrar se generará la Nota clínica AUTO final de esta consulta.</div>
          <div class="d-flex flex-wrap align-items-center gap-2">
            <button type="button" class="btn btn-sm btn-primary" data-action="finalize-encounter">Cerrar consulta</button>
            <span class="text-secondary d-none" data-role="finalize-encounter-loading">Generando Nota clínica AUTO final…</span>
          </div>
          <div class="alert alert-danger small d-none mt-3 mb-0" data-role="finalize-encounter-error"></div>
        <?php else: ?>
          <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
            <span class="badge text-bg-success">Consulta cerrada</span>
            <span>Cerrada el <?php echo h($encounterClosedAt !== '' ? $encounterClosedAt : '-'); ?></span>
          </div>
          <div class="d-flex flex-wrap gap-2">
            <?php if ($autoNoteFinalHref !== ''): ?>
              <a class="btn btn-sm btn-outline-primary" href="<?php echo h($autoNoteFinalHref); ?>" data-role="open-final-auto-note" data-href="<?php echo h($autoNoteFinalHref); ?>">Ver Nota clínica AUTO (Cierre)</a>
            <?php endif; ?>
            <?php if (count($prescriptions) === 1): ?>
              <?php $rxUuid = trim((string)($prescriptions[0]['document_uuid'] ?? '')); ?>
              <?php if ($rxUuid !== ''): ?>
                <a class="btn btn-sm btn-outline-secondary" href="<?php echo h('/modules/clinical/ui/viewer.php?uuid=' . rawurlencode($rxUuid) . ($embed ? '&embed=1' : '')); ?>">Abrir receta de esta consulta</a>
              <?php endif; ?>
            <?php elseif (count($prescriptions) > 1): ?>
              <div class="w-100 text-secondary">Recetas de esta consulta:</div>
              <?php foreach ($prescriptions as $rx): ?>
                <?php $rxUuid = trim((string)($rx['document_uuid'] ?? '')); ?>
                <?php $rxTitle = trim((string)($rx['title'] ?? 'Receta')); ?>
                <?php if ($rxUuid === '') continue; ?>
                <a class="btn btn-sm btn-outline-secondary" href="<?php echo h('/modules/clinical/ui/viewer.php?uuid=' . rawurlencode($rxUuid) . ($embed ? '&embed=1' : '')); ?>"><?php echo h($rxTitle !== '' ? $rxTitle : 'Receta'); ?></a>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</div>
<div data-role="doc-overlay" hidden aria-hidden="true">
  <div data-role="doc-overlay-backdrop"></div>
  <div data-role="doc-overlay-panel" role="dialog" aria-modal="true" aria-label="Documento">
    <div data-role="doc-overlay-head">
      <strong data-role="doc-overlay-title">Documento</strong>
      <div class="d-flex gap-2">
        <a class="btn btn-sm btn-outline-primary" data-role="doc-overlay-open-new" href="#" target="_blank" rel="noopener">Abrir en pestaña</a>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-role="doc-overlay-close">Cerrar</button>
      </div>
    </div>
    <div data-role="doc-overlay-loader" class="small text-secondary px-3 py-2 d-none">Cargando…</div>
    <iframe data-role="doc-overlay-iframe" src="about:blank" loading="lazy"></iframe>
  </div>
</div>
<script src="/modules/clinical/ui/_shared/clinical_doc_render.js"></script>
<script src="/modules/clinical/ui/_shared/clinical_doc_overlay.js"></script>
<script src="/modules/clinical/ui/_shared/clinical_embed_kit.js"></script>
<script>
  (function () {
    var isEmbed = <?php echo $embed ? 'true' : 'false'; ?>;
    var PREVIEW_LIMIT = 10;
    var encounterKey = <?php echo json_encode($encounterKey, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    var apiIndexBase = <?php echo json_encode($apiIndexBase, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    var currentUserId = <?php echo json_encode($currentUserId, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    var el = document.getElementById('encounterDocumentsList');
    var controlsEl = document.getElementById('encounterDocumentsControls');
    if (!el) return;

    var docs = <?php echo $documentsJson; ?>;
    var renderer = window.MXMed && typeof window.MXMed.renderClinicalDocuments === 'function'
      ? window.MXMed.renderClinicalDocuments
      : null;
    if (!renderer) {
      el.innerHTML = '<div class="small text-secondary">Sin documentos</div>';
      return;
    }
    el.innerHTML = renderer(docs, {
      embedLink: <?php echo $embed ? 'true' : 'false'; ?>,
      returnTo: window.location.href,
      openInOverlay: isEmbed,
      emptyHtml: '<div class="small text-secondary">Sin documentos</div>'
    });

    function getDocumentItems() {
      return Array.prototype.slice.call(el.children).filter(function (child) {
        return child && child.classList
          && child.classList.contains('border')
          && child.classList.contains('rounded')
          && child.classList.contains('p-2');
      });
    }

    function renderDocControls(items, expanded) {
      if (!controlsEl) return;
      var total = items.length;
      if (total <= PREVIEW_LIMIT) {
        controlsEl.classList.add('d-none');
        controlsEl.innerHTML = '';
        return;
      }
      controlsEl.classList.remove('d-none');
      var shown = expanded ? total : PREVIEW_LIMIT;
      controlsEl.innerHTML = ''
        + '<div class="d-flex flex-wrap justify-content-between align-items-center gap-2">'
        + '  <span class="small text-secondary">Mostrando ' + shown + ' de ' + total + '</span>'
        + '  <div class="d-flex gap-2">'
        + (expanded
          ? '<button type="button" class="btn btn-sm btn-outline-secondary" data-action="docs-show-less">Ver menos</button>'
          : '<button type="button" class="btn btn-sm btn-outline-primary" data-action="docs-show-all">Ver todos</button>')
        + '  </div>'
        + '</div>';
    }

    function applyDocumentsPreview(expanded) {
      var items = getDocumentItems();
      if (items.length === 0) {
        if (controlsEl) {
          controlsEl.classList.add('d-none');
          controlsEl.innerHTML = '';
        }
        return;
      }
      items.forEach(function (item, idx) {
        item.hidden = !expanded && idx >= PREVIEW_LIMIT;
      });
      renderDocControls(items, expanded);
    }

    var docsExpanded = false;
    applyDocumentsPreview(docsExpanded);

    function resolveClinicalActorUserId() {
      if (currentUserId) {
        return String(currentUserId).trim();
      }
      if (window.MXMED_USER_ID) {
        return String(window.MXMED_USER_ID).trim();
      }
      if (window.__MXMED && window.__MXMED.user_id) {
        return String(window.__MXMED.user_id).trim();
      }
      var rootUserId = document.body ? String(document.body.getAttribute('data-user-id') || '').trim() : '';
      if (rootUserId) {
        return rootUserId;
      }
      return 'qa';
    }

    function showFinalizeLoading(flag) {
      var el = document.querySelector('[data-role="finalize-encounter-loading"]');
      if (el) el.classList.toggle('d-none', !flag);
      var btn = document.querySelector('[data-action="finalize-encounter"]');
      if (btn) btn.disabled = !!flag;
    }

    function setFinalizeError(message) {
      var el = document.querySelector('[data-role="finalize-encounter-error"]');
      if (!el) return;
      var text = String(message || '').trim();
      el.textContent = text;
      el.classList.toggle('d-none', text === '');
    }

    function openHrefWithCurrentPattern(href) {
      var nextHref = String(href || '').trim();
      if (!nextHref) return;
      if (isEmbed && window.parent && window.parent !== window && typeof window.parent.postMessage === 'function') {
        window.parent.postMessage({ type: 'mxmed:embed:navigate', mode: 'document', href: nextHref }, '*');
        return;
      }
      window.location.href = nextHref;
    }

    if (controlsEl) {
      controlsEl.addEventListener('click', function (event) {
        var btn = event.target.closest('button[data-action]');
        if (!btn) return;
        var action = btn.getAttribute('data-action') || '';
        if (action === 'docs-show-all') {
          docsExpanded = true;
          applyDocumentsPreview(docsExpanded);
        } else if (action === 'docs-show-less') {
          docsExpanded = false;
          applyDocumentsPreview(docsExpanded);
        }
      });
    }

    document.addEventListener('click', function (event) {
      var finalizeBtn = event.target && event.target.closest ? event.target.closest('[data-action="finalize-encounter"]') : null;
      if (finalizeBtn) {
        event.preventDefault();
        if (!encounterKey) return;
        if (!window.confirm('¿Cerrar consulta? Se generará la Nota clínica AUTO final de esta consulta.')) {
          return;
        }
        showFinalizeLoading(true);
        setFinalizeError('');
        fetch(apiIndexBase + '/encounters/' + encodeURIComponent(encounterKey) + '/finalize', {
          method: 'POST',
          headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json'
          },
          credentials: 'same-origin',
          body: JSON.stringify({
            actor: {
              user_id: resolveClinicalActorUserId()
            }
          })
        })
          .then(function (response) {
            return response.json().catch(function () { return null; });
          })
          .then(function (resp) {
            if (resp && resp.ok === true) {
              window.location.reload();
              return;
            }
            showFinalizeLoading(false);
            setFinalizeError((resp && (resp.message || resp.error)) || 'No se pudo cerrar la consulta.');
          })
          .catch(function (err) {
            showFinalizeLoading(false);
            setFinalizeError((err && err.message) || 'No se pudo cerrar la consulta.');
          });
        return;
      }

      var openFinalNoteLink = event.target && event.target.closest ? event.target.closest('[data-role="open-final-auto-note"]') : null;
      if (openFinalNoteLink) {
        event.preventDefault();
        openHrefWithCurrentPattern(openFinalNoteLink.getAttribute('data-href') || openFinalNoteLink.getAttribute('href') || '');
      }
    });

    if (window.MXMed && typeof window.MXMed.initClinicalEmbedKit === 'function') {
      window.MXMed.initClinicalEmbedKit({ embedOnly: true });
    }
  })();
</script>
<?php if ($embed): ?>
<?php clinical_embed_end(); ?>
<?php else: ?>
<?php require_once __DIR__ . '/../../_partials/mm_shell_bottom.php'; ?>
<?php endif; ?>
