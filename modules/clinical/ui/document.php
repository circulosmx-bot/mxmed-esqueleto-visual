<?php
// modules/clinical/ui/document.php

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

    return $proto . '://' . $host;
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

function h(string $value): string {
  return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function resolve_media_href(string $value): string {
  $value = trim($value);
  if ($value === '') {
    return '';
  }
  if (preg_match('/^https?:\/\//i', $value)) {
    return $value;
  }
  if ($value[0] !== '/') {
    return '/' . ltrim($value, '/');
  }
  return $value;
}

function validate_return_to(string $value): ?string {
  $value = trim($value);
  if ($value === '') {
    return null;
  }

  if ($value[0] === '/') {
    return (strpos($value, '//') === 0) ? null : $value;
  }

  if (!preg_match('/^https?:\/\//i', $value)) {
    return null;
  }

  $parts = parse_url($value);
  if (!is_array($parts)) {
    return null;
  }

  $urlPath = (string)($parts['path'] ?? '');
  if ($urlPath !== '' && strpos($urlPath, '/modules/clinical/ui/') === 0) {
    return $value;
  }

  $host = strtolower((string)($parts['host'] ?? ''));
  $scheme = strtolower((string)($parts['scheme'] ?? ''));
  $port = isset($parts['port']) ? (int)$parts['port'] : null;

  $currentHostRaw = (string)($_SERVER['HTTP_HOST'] ?? '');
  $currentHostParts = parse_url('http://' . $currentHostRaw);
  $currentHost = strtolower((string)($currentHostParts['host'] ?? ''));
  $currentPort = isset($currentHostParts['port']) ? (int)$currentHostParts['port'] : null;
  $currentScheme = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';

  if ($host === '' || $currentHost === '') {
    return null;
  }

  if ($host !== $currentHost || $scheme !== $currentScheme) {
    return null;
  }

  if (($port ?? ($scheme === 'https' ? 443 : 80)) !== ($currentPort ?? ($currentScheme === 'https' ? 443 : 80))) {
    return null;
  }

  return $value;
}

function render_embed_css(bool $embed): void {
  if (!$embed) {
    return;
  }

  echo '<link rel="stylesheet" href="/assets/css/style.css">' . "\n";
  echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">' . "\n";
}

function clinical_doc_clean_text($value): string {
  $text = trim((string)$value);
  if ($text === '' || $text === '--') {
    return '';
  }
  return $text;
}

function clinical_doc_format_date(string $value, bool $includeTime = false): string {
  $safe = trim($value);
  if ($safe === '') {
    return '';
  }
  $ts = strtotime($safe);
  if ($ts === false) {
    return $safe;
  }
  return $includeTime ? date('Y-m-d H:i:s', $ts) : date('Y-m-d', $ts);
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
  $status = 0;
  foreach (($http_response_header ?? []) as $line) {
    if (is_string($line) && preg_match('/^HTTP\/\d+(?:\.\d+)?\s+(\d{3})/i', trim($line), $m)) {
      $status = (int)$m[1];
      break;
    }
  }
  if ($raw === false) {
    return ['ok' => false, 'error' => 'fetch_failed', 'message' => 'No se pudo consultar el documento. status=' . $status];
  }

  $decoded = json_decode($raw, true);
  if (!is_array($decoded)) {
    return ['ok' => false, 'error' => 'invalid_json', 'message' => 'Respuesta inválida del endpoint de documentos. status=' . $status];
  }

  if ($status !== 0 && $status !== 200 && (($decoded['ok'] ?? false) !== true)) {
    $msg = trim((string)($decoded['message'] ?? ''));
    return [
      'ok' => false,
      'error' => (string)($decoded['error'] ?? 'http_error'),
      'message' => ($msg !== '' ? $msg : 'Error consultando documento.') . ' status=' . $status,
    ];
  }

  return $decoded;
}

$uuid = trim((string)($_GET['uuid'] ?? ''));
$returnTo = validate_return_to((string)($_GET['return_to'] ?? ''));
$backHref = $returnTo ?? 'javascript:history.back()';
$errorMessage = '';
$document = null;
$apiBase = normalize_clinical_api_base((string)getenv('CLINICAL_API_BASE'));
if ($apiBase === '') {
  $apiBase = normalize_clinical_api_base(get_api_base());
}
$apiIndexBase = ($apiBase !== '') ? ($apiBase . '/api/clinical/index.php') : '';

if ($uuid !== '') {
  if ($apiIndexBase === '') {
    $errorMessage = 'CLINICAL_API_BASE no configurado y get_api_base() vacío.';
  } else {
    $url = $apiIndexBase . '/documents/' . rawurlencode($uuid);
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
}

// Campos según el JSON real:
$docType = $document ? (string)($document['document_type'] ?? '-') : '-';
$title   = $document ? (string)($document['title'] ?? '-') : '-';
$date    = $document ? (string)($document['ui']['event_datetime'] ?? ($document['timestamps']['created_at'] ?? '-')) : '-';
$summary = $document ? (string)($document['content']['summary'] ?? '-') : '-';
$payload = $document && is_array($document['content']['payload'] ?? null) ? $document['content']['payload'] : null;
$payloadJson = $payload ? json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
$renderedText = $document ? (string)($document['content']['rendered_text'] ?? '') : '';
$docTypeNorm = strtolower(trim($docType));
$fileMeta = is_array($payload['file'] ?? null) ? $payload['file'] : [];
$optimizedMeta = is_array($fileMeta['optimized'] ?? null) ? $fileMeta['optimized'] : [];
$originalMeta = is_array($fileMeta['original'] ?? null) ? $fileMeta['original'] : [];
$renderMode = strtolower(trim((string)($fileMeta['render_mode'] ?? '')));
if ($renderMode === '') {
  if ($docTypeNorm === 'image') {
    $renderMode = 'image';
  } elseif ($docTypeNorm === 'pdf') {
    $renderMode = 'pdf';
  } else {
    $renderMode = 'structured';
  }
}
$downloadCandidate = trim((string)($optimizedMeta['url'] ?? $optimizedMeta['path'] ?? ''));
if ($downloadCandidate === '' && is_array($payload)) {
  $downloadCandidate = trim((string)($payload['url'] ?? $payload['src'] ?? $payload['file_url'] ?? $payload['pdf_url'] ?? $payload['image_url'] ?? ''));
}
$downloadHref = resolve_media_href($downloadCandidate);
$originalDownloadHref = resolve_media_href(trim((string)($originalMeta['url'] ?? $originalMeta['path'] ?? '')));
$embedRequested = trim((string)($_GET['embed'] ?? '')) === '1';
$documentOpenHref = '/modules/clinical/ui/document.php?uuid=' . rawurlencode($uuid) . ($embedRequested ? '&embed=1' : '');
$viewerOpenHref = '/modules/clinical/ui/viewer.php?uuid=' . rawurlencode($uuid) . ($embedRequested ? '&embed=1' : '');
$viewerFullscreenHref = $viewerOpenHref . '&mode=fullscreen';
$isImageDoc = ($renderMode === 'image' || $docTypeNorm === 'image');
$isPdfDoc = ($renderMode === 'pdf' || $docTypeNorm === 'pdf');
$isNoteDoc = in_array($docTypeNorm, ['note', 'nota_evolucion'], true);
$isOrderDoc = in_array($docTypeNorm, ['lab_order', 'imaging_order', 'orders'], true);
$isPrescriptionDoc = in_array($docTypeNorm, ['prescription', 'rx'], true);
$showCommonDocActions = $isNoteDoc || $isOrderDoc || (!$isImageDoc && !$isPdfDoc);

$rxSnapshot = ($isPrescriptionDoc && is_array($payload['snapshot'] ?? null)) ? $payload['snapshot'] : [];
$rxPrescription = ($isPrescriptionDoc && is_array($payload['prescription'] ?? null)) ? $payload['prescription'] : [];
$rxPaciente = is_array($rxSnapshot['paciente'] ?? null) ? $rxSnapshot['paciente'] : [];
$rxMedico = is_array($rxSnapshot['medico'] ?? null) ? $rxSnapshot['medico'] : [];
$rxBranding = is_array($rxSnapshot['branding'] ?? null) ? $rxSnapshot['branding'] : [];
$rxItems = is_array($rxPrescription['items'] ?? null) ? $rxPrescription['items'] : [];
$rxObservaciones = clinical_doc_clean_text($rxPrescription['observaciones'] ?? '');
$rxDoctorName = clinical_doc_clean_text($rxMedico['nombre'] ?? $rxMedico['nombre_completo'] ?? '');
$rxDoctorSpecialty = clinical_doc_clean_text($rxMedico['especialidad'] ?? '');
$rxDoctorCedula = clinical_doc_clean_text($rxMedico['cedula'] ?? $rxMedico['cedula_profesional'] ?? '');
$rxPatientName = clinical_doc_clean_text($rxPaciente['nombre'] ?? $rxPaciente['nombre_completo'] ?? '');
$rxPatientAge = clinical_doc_clean_text($rxPaciente['edad'] ?? '');
$rxPatientSex = clinical_doc_clean_text($rxPaciente['sexo'] ?? '');
$rxPatientMeta = array_values(array_filter([
    $rxPatientAge !== '' ? ('Edad: ' . $rxPatientAge) : '',
    $rxPatientSex !== '' ? ('Sexo: ' . $rxPatientSex) : '',
]));
$rxDoctorLogo = resolve_media_href((string)($rxBranding['doctor_logo_url'] ?? ''));
$rxGroupLogo = resolve_media_href((string)($rxBranding['group_logo_url'] ?? ''));
$rxGeneratedAtRaw = (string)($rxSnapshot['generated_at'] ?? $date);
$rxEmissionDate = clinical_doc_format_date($rxGeneratedAtRaw, false);

$rxConsultorios = [];
if (is_array($rxSnapshot['consultorios'] ?? null)) {
  foreach ($rxSnapshot['consultorios'] as $entry) {
    if (!is_array($entry)) {
      continue;
    }
    $rxConsultorios[] = [
      'nombre' => clinical_doc_clean_text($entry['nombre'] ?? $entry['name'] ?? ''),
      'domicilio' => clinical_doc_clean_text($entry['domicilio'] ?? $entry['address'] ?? ''),
      'telefono' => clinical_doc_clean_text($entry['telefono'] ?? $entry['phone'] ?? ''),
    ];
  }
}
if ($rxConsultorios === [] && is_array($rxSnapshot['consultorio'] ?? null)) {
  $single = $rxSnapshot['consultorio'];
  $rxConsultorios[] = [
    'nombre' => clinical_doc_clean_text($single['nombre'] ?? $single['name'] ?? ''),
    'domicilio' => clinical_doc_clean_text($single['domicilio'] ?? $single['address'] ?? ''),
    'telefono' => clinical_doc_clean_text($single['telefono'] ?? $single['phone'] ?? ''),
  ];
}
$rxConsultorios = array_values(array_filter($rxConsultorios, static function (array $entry): bool {
  return (($entry['nombre'] ?? '') !== '') || (($entry['domicilio'] ?? '') !== '') || (($entry['telefono'] ?? '') !== '');
}));
if (count($rxConsultorios) > 3) {
  $rxConsultorios = array_slice($rxConsultorios, 0, 3);
}

require_once __DIR__ . '/../../_partials/clinical_embed.php';
$embed = is_embed_request();
$replicateUrl = ($apiIndexBase !== '' && $uuid !== '') ? ($apiIndexBase . '/documents/' . rawurlencode($uuid) . '/replicate') : '';
$replicateRedirectTemplate = '/modules/clinical/ui/document.php?' . carry_embed_params(['uuid' => '__UUID__']);
$replicateTitleOverride = ($title !== '' && $title !== '-') ? ($title . ' (replicado)') : '';

// Shell MXMed
if (!$embed) {
    $pageTitle = 'Documento clínico';
    require_once __DIR__ . '/../../_partials/mm_shell_top.php';
} else {
    render_embed_css($embed);
    clinical_embed_start();
}
?>
<?php if ($isPrescriptionDoc): ?>
<style>
  .rx-doc-sheet{
    background:#fff;
    border:1px solid #d7eaf2;
    border-radius:14px;
    padding:18px;
    display:grid;
    gap:14px;
  }
  .rx-doc-header{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:12px;
    border-bottom:1px solid #edf4f8;
    padding-bottom:10px;
    break-inside:avoid;
  }
  .rx-doc-logos{display:flex;gap:10px;flex-wrap:wrap;align-items:center;}
  .rx-doc-logo{max-height:44px;max-width:132px;object-fit:contain;}
  .rx-doc-doctor{margin-left:auto;text-align:right;min-width:220px;}
  .rx-doc-doctor-name{font-size:1rem;font-weight:800;color:#0a405f;line-height:1.25;}
  .rx-doc-doctor-meta{font-size:0.86rem;color:#5d6b74;}
  .rx-doc-patient{display:grid;gap:4px;break-inside:avoid;}
  .rx-doc-patient-name{font-size:0.98rem;font-weight:700;color:#12344d;}
  .rx-doc-patient-meta{font-size:0.84rem;color:#64737d;}
  .rx-doc-rp{font-size:1.18rem;font-weight:800;color:#0a405f;}
  .rx-doc-list{margin:0;padding-left:22px;display:grid;gap:8px;}
  .rx-doc-item{break-inside:avoid;}
  .rx-doc-item-title{font-size:0.94rem;font-weight:700;color:#12344d;}
  .rx-doc-item-meta{font-size:0.84rem;color:#546670;}
  .rx-doc-item-note{font-size:0.84rem;color:#273b47;}
  .rx-doc-observaciones{
    border:1px solid #e8f0f5;
    border-radius:10px;
    padding:10px 12px;
    background:#fbfdff;
    break-inside:avoid;
  }
  .rx-doc-section-title{
    font-size:0.82rem;
    font-weight:800;
    color:#0a405f;
    text-transform:uppercase;
    letter-spacing:.02em;
    margin-bottom:4px;
  }
  .rx-doc-observaciones-text{font-size:0.88rem;color:#273b47;white-space:pre-wrap;}
  .rx-doc-signature{display:grid;justify-items:end;gap:4px;break-inside:avoid;}
  .rx-doc-sign-line{width:230px;border-top:1px solid #9fb6c4;}
  .rx-doc-sign-name{font-size:0.9rem;font-weight:700;color:#12344d;}
  .rx-doc-sign-meta{font-size:0.8rem;color:#5d6b74;}
  .rx-doc-footer{
    border-top:1px solid #edf4f8;
    padding-top:10px;
    display:grid;
    gap:6px;
    break-inside:avoid;
  }
  .rx-doc-footer-item{font-size:0.8rem;color:#5d6b74;line-height:1.35;}
  @media (max-width: 991.98px){
    .rx-doc-header{flex-direction:column;align-items:flex-start;}
    .rx-doc-doctor{text-align:left;margin-left:0;min-width:0;}
    .rx-doc-signature{justify-items:start;}
  }
  @media print{
    @page{size:letter portrait;margin:12mm 14mm;}
    .no-print{display:none !important;}
    html, body{background:#fff !important;}
    .container.py-4{padding:0 !important;max-width:none !important;}
    .rx-doc-sheet{
      border:0 !important;
      border-radius:0 !important;
      box-shadow:none !important;
      padding:0 !important;
      gap:12px;
    }
    .rx-doc-list{gap:6px;}
  }
</style>
<?php endif; ?>
<div class="<?php echo $embed ? 'py-1' : 'container py-4'; ?>">
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3 no-print">
    <div>
      <h1 class="h4 mb-0">Documento clínico</h1>
      <?php if ($title !== '-' && $uuid !== ''): ?>
        <div class="text-secondary small"><?php echo h($title); ?></div>
      <?php endif; ?>
    </div>
    <div class="d-flex flex-wrap justify-content-end gap-2">
      <a class="btn btn-outline-secondary btn-sm" href="<?php echo h($backHref); ?>">Volver</a>
      <div class="dropdown">
        <button class="btn btn-outline-primary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Acciones</button>
        <ul class="dropdown-menu dropdown-menu-end">
          <?php if ($isImageDoc): ?>
            <li><a class="dropdown-item" href="<?php echo h($viewerOpenHref); ?>" target="_blank" rel="noopener">Abrir en nueva pestaña</a></li>
            <li><a class="dropdown-item" href="<?php echo h($viewerFullscreenHref); ?>" target="_blank" rel="noopener">Pantalla completa</a></li>
            <?php if ($downloadHref !== ''): ?>
              <li><a class="dropdown-item" href="<?php echo h($downloadHref); ?>" target="_blank" rel="noopener" download>Descargar optimizada</a></li>
            <?php else: ?>
              <li><button type="button" class="dropdown-item" disabled title="No disponible">Descargar optimizada</button></li>
            <?php endif; ?>
            <?php if ($originalDownloadHref !== ''): ?>
              <li><a class="dropdown-item" href="<?php echo h($originalDownloadHref); ?>" target="_blank" rel="noopener" download>Descargar original</a></li>
            <?php endif; ?>
            <li><button type="button" class="dropdown-item" data-action="print-document">Imprimir</button></li>
          <?php elseif ($isPdfDoc): ?>
            <li><a class="dropdown-item" href="<?php echo h($viewerOpenHref); ?>" target="_blank" rel="noopener">Abrir en nueva pestaña</a></li>
            <?php if ($downloadHref !== ''): ?>
              <li><a class="dropdown-item" href="<?php echo h($downloadHref); ?>" target="_blank" rel="noopener" download>Descargar PDF</a></li>
            <?php else: ?>
              <li><button type="button" class="dropdown-item" disabled title="No disponible">Descargar PDF</button></li>
            <?php endif; ?>
            <li><button type="button" class="dropdown-item" data-action="print-document">Imprimir</button></li>
          <?php elseif ($showCommonDocActions): ?>
            <li><a class="dropdown-item" href="<?php echo h($documentOpenHref); ?>" target="_blank" rel="noopener">Abrir en nueva pestaña</a></li>
            <li><button type="button" class="dropdown-item" data-action="print-document">Imprimir</button></li>
            <li><button type="button" class="dropdown-item" data-action="copy-document-link">Copiar enlace</button></li>
          <?php endif; ?>
          <?php if ($uuid !== '' && $errorMessage === '' && $replicateUrl !== ''): ?>
            <li><hr class="dropdown-divider"></li>
            <li>
              <button
                type="button"
                class="dropdown-item"
                data-action="replicate-document"
                data-replicate-url="<?php echo h($replicateUrl); ?>"
                data-redirect-template="<?php echo h($replicateRedirectTemplate); ?>"
                data-title-override="<?php echo h($replicateTitleOverride); ?>"
              >Replicar (crear copia)</button>
            </li>
          <?php endif; ?>
        </ul>
      </div>
    </div>
  </div>

  <p class="text-secondary mb-3 no-print">uuid: <code><?php echo h($uuid !== '' ? $uuid : '-'); ?></code></p>

  <?php if ($uuid === ''): ?>
    <div class="alert alert-warning">uuid requerido.</div>
  <?php elseif ($errorMessage !== ''): ?>
    <div class="alert alert-danger"><?php echo h($errorMessage); ?></div>
  <?php else: ?>
    <?php if ($isPrescriptionDoc): ?>
      <article class="rx-doc-sheet">
        <header class="rx-doc-header">
          <?php if ($rxDoctorLogo !== '' || $rxGroupLogo !== ''): ?>
            <div class="rx-doc-logos">
              <?php if ($rxDoctorLogo !== ''): ?>
                <img class="rx-doc-logo" src="<?php echo h($rxDoctorLogo); ?>" alt="Logo médico">
              <?php endif; ?>
              <?php if ($rxGroupLogo !== ''): ?>
                <img class="rx-doc-logo" src="<?php echo h($rxGroupLogo); ?>" alt="Logo grupo médico">
              <?php endif; ?>
            </div>
          <?php endif; ?>
          <div class="rx-doc-doctor">
            <?php if ($rxDoctorName !== ''): ?><div class="rx-doc-doctor-name"><?php echo h($rxDoctorName); ?></div><?php endif; ?>
            <?php if ($rxDoctorSpecialty !== ''): ?><div class="rx-doc-doctor-meta"><?php echo h($rxDoctorSpecialty); ?></div><?php endif; ?>
            <?php if ($rxDoctorCedula !== ''): ?><div class="rx-doc-doctor-meta">Cédula: <?php echo h($rxDoctorCedula); ?></div><?php endif; ?>
          </div>
        </header>

        <section class="rx-doc-patient">
          <div class="rx-doc-patient-name"><?php echo h($rxPatientName !== '' ? $rxPatientName : 'Paciente'); ?></div>
          <div class="rx-doc-patient-meta">
            <?php
            $patientMetaText = implode(' · ', $rxPatientMeta);
            echo h($patientMetaText);
            if ($rxEmissionDate !== '') {
                echo h(($patientMetaText !== '' ? ' · ' : '') . 'Fecha: ' . $rxEmissionDate);
            }
            ?>
          </div>
        </section>

        <section>
          <div class="rx-doc-rp">Rp.</div>
          <ol class="rx-doc-list">
            <?php if ($rxItems === []): ?>
              <li class="rx-doc-item"><div class="rx-doc-item-note">Sin medicamentos registrados</div></li>
            <?php else: ?>
              <?php foreach ($rxItems as $idx => $item): ?>
                <?php
                $medicamento = clinical_doc_clean_text($item['medicamento'] ?? '');
                $dosis = clinical_doc_clean_text($item['dosis'] ?? '');
                $via = clinical_doc_clean_text($item['via'] ?? '');
                $frecuencia = clinical_doc_clean_text($item['frecuencia'] ?? $item['periodicidad'] ?? '');
                $duracion = clinical_doc_clean_text($item['duracion'] ?? '');
                $indicaciones = clinical_doc_clean_text($item['indicaciones'] ?? '');
                $itemMeta = array_values(array_filter([
                  $dosis !== '' ? ('Dosis: ' . $dosis) : '',
                  $via !== '' ? ('Vía: ' . $via) : '',
                  $frecuencia !== '' ? ('Frecuencia: ' . $frecuencia) : '',
                  $duracion !== '' ? ('Duración: ' . $duracion) : '',
                ]));
                ?>
                <li class="rx-doc-item">
                  <div class="rx-doc-item-title"><?php echo h($medicamento !== '' ? $medicamento : ('Medicamento ' . ((int)$idx + 1))); ?></div>
                  <?php if ($itemMeta !== []): ?><div class="rx-doc-item-meta"><?php echo h(implode(' · ', $itemMeta)); ?></div><?php endif; ?>
                  <?php if ($indicaciones !== ''): ?><div class="rx-doc-item-note"><?php echo h($indicaciones); ?></div><?php endif; ?>
                </li>
              <?php endforeach; ?>
            <?php endif; ?>
          </ol>
        </section>

        <?php if ($rxObservaciones !== ''): ?>
          <section class="rx-doc-observaciones">
            <div class="rx-doc-section-title">Observaciones</div>
            <div class="rx-doc-observaciones-text"><?php echo h($rxObservaciones); ?></div>
          </section>
        <?php endif; ?>

        <section class="rx-doc-signature">
          <div class="rx-doc-sign-line"></div>
          <?php if ($rxDoctorName !== ''): ?><div class="rx-doc-sign-name"><?php echo h($rxDoctorName); ?></div><?php endif; ?>
          <?php if ($rxDoctorCedula !== ''): ?><div class="rx-doc-sign-meta">Cédula: <?php echo h($rxDoctorCedula); ?></div><?php endif; ?>
        </section>

        <footer class="rx-doc-footer">
          <?php if ($rxConsultorios === []): ?>
            <div class="rx-doc-footer-item">Información de consultorio no disponible.</div>
          <?php else: ?>
            <?php foreach ($rxConsultorios as $consultorio): ?>
              <?php
              $lines = array_values(array_filter([
                $consultorio['nombre'] ?? '',
                $consultorio['domicilio'] ?? '',
                $consultorio['telefono'] ?? '',
              ]));
              ?>
              <div class="rx-doc-footer-item"><?php echo h(implode(' · ', $lines)); ?></div>
            <?php endforeach; ?>
          <?php endif; ?>
        </footer>
      </article>
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
  <?php endif; ?>
</div>
<script>
  (function () {
    document.addEventListener('click', function (event) {
      var printBtn = event.target && event.target.closest ? event.target.closest('[data-action="print-document"]') : null;
      if (printBtn) {
        event.preventDefault();
        window.print();
        return;
      }
      var copyBtn = event.target && event.target.closest ? event.target.closest('[data-action="copy-document-link"]') : null;
      if (copyBtn) {
        event.preventDefault();
        if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
          navigator.clipboard.writeText(window.location.href).catch(function () {});
        }
        return;
      }
      var replicateBtn = event.target && event.target.closest ? event.target.closest('[data-action="replicate-document"]') : null;
      if (replicateBtn) {
        event.preventDefault();
        if (replicateBtn.disabled) {
          return;
        }
        if (!window.confirm('¿Crear una copia de este documento?')) {
          return;
        }
        var endpoint = String(replicateBtn.getAttribute('data-replicate-url') || '').trim();
        if (!endpoint) {
          window.alert('No se pudo iniciar la replicación.');
          return;
        }
        var payload = {};
        var titleOverride = String(replicateBtn.getAttribute('data-title-override') || '').trim();
        if (titleOverride) {
          payload.title_override = titleOverride;
        }
        var originalText = replicateBtn.textContent;
        replicateBtn.disabled = true;
        replicateBtn.textContent = 'Replicando...';
        fetch(endpoint, {
          method: 'POST',
          credentials: 'include',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          },
          body: JSON.stringify(payload)
        })
          .then(function (resp) {
            return resp.json().catch(function () { return {}; }).then(function (json) {
              if (!resp.ok || !json || json.ok !== true) {
                var msg = (json && (json.message || json.error)) ? String(json.message || json.error) : ('Error HTTP ' + resp.status);
                throw new Error(msg);
              }
              return json;
            });
          })
          .then(function (json) {
            var newUuid = String((json && json.data && json.data.document_uuid) ? json.data.document_uuid : '').trim();
            if (!newUuid) {
              throw new Error('Respuesta inválida al replicar.');
            }
            var redirectTemplate = String(replicateBtn.getAttribute('data-redirect-template') || '').trim();
            if (!redirectTemplate) {
              redirectTemplate = '/modules/clinical/ui/document.php?uuid=__UUID__';
            }
            window.location.href = redirectTemplate.replace('__UUID__', encodeURIComponent(newUuid));
          })
          .catch(function (err) {
            window.alert((err && err.message) ? err.message : 'No se pudo replicar el documento.');
          })
          .finally(function () {
            replicateBtn.disabled = false;
            replicateBtn.textContent = originalText;
          });
      }
    }, true);
  })();
</script>
<?php if ($embed): ?>
<?php clinical_embed_end(); ?>
<?php else: ?>
<?php require_once __DIR__ . '/../../_partials/mm_shell_bottom.php'; ?>
<?php endif; ?>
