<?php
declare(strict_types=1);

if (!defined('MXMED_PORTABLE_ORDER_TEMPLATE_ALLOWED')) {
    http_response_code(404);
    exit;
}

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

function portable_css_string(string $value): string
{
    return '"' . str_replace(
        ["\\", '"', '<', '>', "\n", "\r", "\f"],
        ["\\\\", '\\"', '\\3C ', '\\3E ', '\\A ', '', ''],
        $value
    ) . '"';
}

function portable_show_designation(array $physician): bool
{
    $designation = trim((string)($physician['designation'] ?? ''));
    if ($designation === '') return false;
    $generic = in_array(mb_strtolower($designation), ['médico', 'médica', 'medico', 'medica'], true);
    $medicalPrefix = in_array(mb_strtolower(trim((string)($physician['prefix'] ?? ''))), ['dr.', 'dra.', 'dr', 'dra'], true);
    return !$generic || !$medicalPrefix;
}

$patient = is_array($order['patient'] ?? null) ? $order['patient'] : [];
$physician = is_array($order['physician'] ?? null) ? $order['physician'] : [];
$place = is_array($order['consultorio'] ?? null) ? $order['consultorio'] : [];
$reference = (string)($order['display_reference'] ?? '');
$continuationHeader = $order
    ? 'Orden de estudios · ' . (string)($patient['name'] ?? '') . ' · Ref. ' . $reference
    : '';
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
      @top-left { content: <?php echo portable_css_string($continuationHeader); ?>; font: 9px Arial, sans-serif; }
      @bottom-right { content: "Página " counter(page); font: 9px Arial, sans-serif; }
    }
    @page :first {
      @top-left { content: ""; }
    }
    *, *::before, *::after { box-sizing: border-box; }
    body { margin: 0; color: #182c3b; background: #eef2f4; font: 14px/1.48 Arial, Helvetica, sans-serif; }
    .toolbar { max-width: 216mm; margin: 14px auto; display: flex; justify-content: flex-end; }
    button { padding: 9px 17px; border: 1px solid #183c55; border-radius: 6px; background: #183c55; color: white; font: inherit; cursor: pointer; }
    .paper { width: 216mm; min-height: 279mm; margin: 0 auto 24px; padding: 16mm 17mm; background: #fff; box-shadow: 0 2px 14px #0002; }
    .brand { font-size: 18px; font-weight: 700; letter-spacing: .02em; }
    .document-title { margin: 12px 0 14px; padding-bottom: 7px; border-bottom: 2px solid currentColor; font-size: 22px; letter-spacing: .04em; }
    .identity { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 8px 20px; margin-bottom: 16px; }
    .reference { margin-top: 3px; font-size: 11px; }
    .label { display: block; margin-bottom: 3px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
    h2 { margin: 16px 0 8px; font-size: 13px; letter-spacing: .04em; text-transform: uppercase; }
    .studies { margin: 0; padding-left: 25px; }
    .study { padding: 5px 0 9px; break-inside: avoid; page-break-inside: avoid; overflow-wrap: anywhere; }
    .study-note { margin: 3px 0 0; color: #444; white-space: pre-wrap; }
    .study-context { margin: 3px 0 0; color: #244c5d; font-size: 12px; overflow-wrap: anywhere; }
    .text { margin: 0; white-space: pre-wrap; overflow-wrap: anywhere; }
    .priority { margin: 14px 0 0; }
    .priority--elevated { font-weight: 700; }
    .doctor { margin-top: 18px; break-inside: avoid; page-break-inside: avoid; }
    .doctor p { margin: 2px 0; }
    .signature { margin-top: 22px; width: 210px; border-top: 1px solid currentColor; padding-top: 5px; }
    .legacy { margin: -4px 0 12px; padding-left: 8px; border-left: 2px solid #777; color: #444; font-size: 11px; }
    .error { max-width: 620px; margin: 12vh auto; padding: 28px; background: white; border: 1px solid #becbd2; border-radius: 8px; }
    @media print {
      body { background: white; color: black; }
      .toolbar { display: none !important; }
      .paper { width: auto; min-height: 0; margin: 0; padding: 0; box-shadow: none; }
      h2, .identity, .doctor { break-inside: avoid; page-break-inside: avoid; }
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
    <header><div class="brand">México Médico</div><h1 class="document-title">ORDEN DE ESTUDIOS</h1></header>
    <section class="identity" aria-label="Identificación">
      <div><span class="label">Paciente</span><strong><?php echo portable_escape($patient['name'] ?? ''); ?></strong>
        <?php if (!empty($patient['birthdate'])): ?><div>Fecha de nacimiento: <?php echo portable_escape(portable_date((string)$patient['birthdate'])); ?></div><?php endif; ?>
      </div>
      <div><span class="label">Fecha de emisión</span><?php echo portable_escape(portable_date((string)$order['issued_at'])); ?>
        <div class="reference">Referencia MXMED: <?php echo portable_escape($reference); ?></div>
      </div>
    </section>
    <?php if (($order['identity_snapshot_source'] ?? '') === 'LEGACY_RECONSTRUCTED'): ?><p class="legacy">Copia histórica reconstruida con los datos de identificación disponibles.</p><?php endif; ?>
    <section><h2>Estudios solicitados</h2><ol class="studies">
      <?php foreach ($order['studies'] as $study): ?><li class="study"><strong><?php echo portable_escape($study['name']); ?></strong>
        <?php if (!empty($study['dental_context'])): ?><p class="study-context"><?php echo portable_escape($study['dental_context']); ?></p><?php endif; ?>
        <?php if (!empty($study['specimen_context'])): ?><p class="study-context"><?php echo portable_escape($study['specimen_context']); ?></p><?php endif; ?>
        <?php if (!empty($study['note'])): ?><p class="study-note"><?php echo portable_escape($study['note']); ?></p><?php endif; ?>
      </li><?php endforeach; ?>
    </ol></section>
    <?php if (!empty($order['indication'])): ?><section><h2>Indicación clínica</h2><p class="text"><?php echo portable_escape($order['indication']); ?></p></section><?php endif; ?>
    <?php if (!empty($order['priority'])): ?><p class="priority<?php echo preg_match('/^(urgente|stat)$/iu', trim((string)$order['priority'])) === 1 ? ' priority--elevated' : ''; ?>"><strong>Prioridad:</strong> <?php echo portable_escape($order['priority']); ?></p><?php endif; ?>
    <section class="doctor"><h2>Médico solicitante</h2>
      <p><strong><?php echo portable_escape(trim((string)($physician['prefix'] ?? '') . ' ' . (string)($physician['name'] ?? ''))); ?></strong></p>
      <?php if (portable_show_designation($physician)): ?><p><?php echo portable_escape($physician['designation']); ?></p><?php endif; ?>
      <?php if (!empty($physician['specialty'])): ?><p><?php echo portable_escape($physician['specialty']); ?></p><?php endif; ?>
      <?php if (!empty($physician['professional_license'])): ?><p>Cédula profesional: <?php echo portable_escape($physician['professional_license']); ?></p><?php endif; ?>
      <?php if (!empty($physician['specialty_license'])): ?><p>Cédula de especialidad: <?php echo portable_escape($physician['specialty_license']); ?></p><?php endif; ?>
      <?php if (!empty($place['name'])): ?><p><?php echo portable_escape($place['name']); ?></p><?php endif; ?>
      <?php if (!empty($place['address'])): ?><p><?php echo portable_escape($place['address']); ?></p><?php endif; ?>
      <div class="signature">Firma del médico</div>
    </section>
  </main>
<?php endif; ?>
</body>
</html>
