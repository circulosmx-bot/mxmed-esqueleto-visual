<?php
declare(strict_types=1);

header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header('Content-Type: text/html; charset=utf-8');

function portable_escape(mixed $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function portable_date(?string $raw, bool $time = false): string
{
    if (!$raw) return '';
    // A birthdate is a calendar date, not a UTC instant.
    if (!$time && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $raw, $matches) === 1) {
        return $matches[3] . '/' . $matches[2] . '/' . $matches[1];
    }
    try {
        $date = new DateTimeImmutable($raw, new DateTimeZone('UTC'));
        return $date->setTimezone(new DateTimeZone('America/Mexico_City'))->format($time ? 'd/m/Y H:i' : 'd/m/Y');
    } catch (Throwable) {
        return '';
    }
}

require_once __DIR__ . '/../../../api/_lib/db.php';
require_once __DIR__ . '/../../../api/_lib/clinical_portable_order.php';

$uuid = is_string($_GET['uuid'] ?? null) ? trim($_GET['uuid']) : '';
$doctorId = is_string($_GET['doctor_id'] ?? null) ? trim($_GET['doctor_id']) : '';
$error = 'No se pudo abrir esta orden para impresión.';
$order = null;
$scope = clinical_portable_doctor_context();
header('Cache-Control: private, no-store, max-age=0');
if ($scope === null) {
    $error = 'Inicia sesión para consultar esta orden.';
} elseif ($doctorId !== '' && preg_match('/^[A-Za-z0-9._:-]{1,64}$/D', $doctorId) === 1
    && hash_equals($scope['doctor_id'], $doctorId)) {
    try {
        $order = clinical_portable_order_read(mxmed_pdo(), $uuid, $doctorId, $scope['user_id']);
    } catch (ClinicalPortableOrderException $exception) {
        $error = $exception->reason;
    } catch (Throwable) {
        $error = 'No se pudo preparar la orden para impresión.';
    }
}
if ($order === null) http_response_code(403);
$patient = is_array($order['patient'] ?? null) ? $order['patient'] : [];
$physician = is_array($order['physician'] ?? null) ? $order['physician'] : [];
$place = is_array($order['consultorio'] ?? null) ? $order['consultorio'] : [];
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo $order ? 'Orden de estudios · México Médico' : 'Orden no disponible'; ?></title>
  <style>
    @page {
      size: letter portrait; margin: 16mm 17mm;
      @top-left { content: "MXMED · Orden de estudios"; font: 9px Arial, sans-serif; }
      @bottom-left { content: "Referencia <?php echo portable_escape($order['display_reference'] ?? ''); ?>"; font: 9px Arial, sans-serif; }
      @bottom-right { content: "Página " counter(page); font: 9px Arial, sans-serif; }
    }
    *, *::before, *::after { box-sizing: border-box; }
    body { margin: 0; color: #182c3b; background: #eef2f4; font: 14px/1.48 Arial, Helvetica, sans-serif; }
    .toolbar { max-width: 216mm; margin: 14px auto; display: flex; justify-content: flex-end; }
    button { padding: 9px 17px; border: 1px solid #183c55; border-radius: 6px; background: #183c55; color: white; font: inherit; cursor: pointer; }
    .paper { width: 216mm; min-height: 279mm; margin: 0 auto 24px; padding: 16mm 17mm; background: #fff; box-shadow: 0 2px 14px #0002; }
    .brand { font-size: 18px; font-weight: 700; letter-spacing: .02em; }
    .document-title { margin: 12px 0 14px; padding-bottom: 7px; border-bottom: 2px solid currentColor; font-size: 22px; letter-spacing: .04em; }
    .identity { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 8px 20px; margin-bottom: 16px; }
    .label { display: block; margin-bottom: 3px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
    h2 { margin: 16px 0 8px; font-size: 13px; letter-spacing: .04em; text-transform: uppercase; }
    .studies { margin: 0; padding-left: 25px; }
    .study { padding: 5px 0 9px; break-inside: avoid; page-break-inside: avoid; overflow-wrap: anywhere; }
    .study-note { margin: 3px 0 0; color: #444; white-space: pre-wrap; }
    .text { margin: 0; white-space: pre-wrap; overflow-wrap: anywhere; }
    .doctor { margin-top: 18px; break-inside: avoid; page-break-inside: avoid; }
    .doctor p { margin: 2px 0; }
    .signature { margin-top: 22px; width: 210px; border-top: 1px solid currentColor; padding-top: 5px; }
    .footer { margin-top: 17px; padding-top: 8px; border-top: 1px solid #777; font-size: 10px; overflow-wrap: anywhere; }
    .legacy { margin-top: 8px; }
    .error { max-width: 620px; margin: 12vh auto; padding: 28px; background: white; border: 1px solid #becbd2; border-radius: 8px; }
    @media print {
      body { background: white; color: black; }
      .toolbar { display: none !important; }
      .paper { width: auto; min-height: 0; margin: 0; padding: 0; box-shadow: none; }
      h2, .identity, .doctor, .footer { break-inside: avoid; page-break-inside: avoid; }
    }
    @media screen and (max-width: 850px) {
      .paper { width: auto; min-height: 0; padding: 22px; margin: 8px; }
      .toolbar { margin: 8px; }
      .identity { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>
<?php if ($order === null): ?>
  <main class="error" role="alert"><h1>Orden no disponible para impresión</h1><p><?php echo portable_escape($error); ?></p></main>
<?php else: ?>
  <div class="toolbar"><button type="button" onclick="window.print()">Imprimir orden</button></div>
  <main class="paper">
    <header><div class="brand">México Médico · MXMED</div><h1 class="document-title">ORDEN DE ESTUDIOS</h1></header>
    <section class="identity" aria-label="Identificación">
      <div><span class="label">Paciente</span><strong><?php echo portable_escape($patient['name'] ?? ''); ?></strong>
        <?php if (!empty($patient['birthdate'])): ?><div>Fecha de nacimiento: <?php echo portable_escape(portable_date((string)$patient['birthdate'])); ?></div><?php endif; ?>
      </div>
      <div><span class="label">Fecha de emisión</span><?php echo portable_escape(portable_date((string)$order['issued_at'])); ?></div>
    </section>
    <section><h2>Estudios solicitados</h2><ol class="studies">
      <?php foreach ($order['studies'] as $study): ?><li class="study"><strong><?php echo portable_escape($study['name']); ?></strong>
        <?php if (!empty($study['note'])): ?><p class="study-note"><?php echo portable_escape($study['note']); ?></p><?php endif; ?>
      </li><?php endforeach; ?>
    </ol></section>
    <?php if (!empty($order['indication'])): ?><section><h2>Indicación clínica</h2><p class="text"><?php echo portable_escape($order['indication']); ?></p></section><?php endif; ?>
    <?php if (!empty($order['priority'])): ?><section><h2>Prioridad</h2><p class="text"><?php echo portable_escape($order['priority']); ?></p></section><?php endif; ?>
    <section class="doctor"><h2>Médico solicitante</h2>
      <p><strong><?php echo portable_escape(trim((string)($physician['prefix'] ?? '') . ' ' . (string)($physician['name'] ?? ''))); ?></strong></p>
      <?php foreach (['designation','specialty'] as $field): if (!empty($physician[$field])): ?><p><?php echo portable_escape($physician[$field]); ?></p><?php endif; endforeach; ?>
      <?php if (!empty($physician['professional_license'])): ?><p>Cédula profesional: <?php echo portable_escape($physician['professional_license']); ?></p><?php endif; ?>
      <?php if (!empty($physician['specialty_license'])): ?><p>Cédula de especialidad: <?php echo portable_escape($physician['specialty_license']); ?></p><?php endif; ?>
      <?php if (!empty($place['name'])): ?><p><?php echo portable_escape($place['name']); ?></p><?php endif; ?>
      <?php if (!empty($place['address'])): ?><p><?php echo portable_escape($place['address']); ?></p><?php endif; ?>
      <div class="signature">Firma del médico</div>
    </section>
    <footer class="footer">Referencia de la orden: <?php echo portable_escape($order['display_reference']); ?> · Documento: <?php echo portable_escape($order['document_uuid']); ?> · Versión documental <?php echo portable_escape($order['document_version']); ?><?php if ((int)($order['lineage_revision'] ?? 1) > 1): ?> · Revisión de orden <?php echo portable_escape($order['lineage_revision']); ?><?php endif; ?>
      <?php if (($order['identity_snapshot_source'] ?? '') === 'LEGACY_RECONSTRUCTED'): ?><div class="legacy">Copia histórica reconstruida con los datos de identificación disponibles.</div><?php endif; ?>
    </footer>
  </main>
<?php endif; ?>
</body>
</html>
