<?php
declare(strict_types=1);

error_reporting(E_ALL & ~E_DEPRECATED);

require_once __DIR__ . '/lib/AgendaApiClient.php';
require_once __DIR__ . '/_layout/header.php';
require_once __DIR__ . '/_layout/flash.php';

use Agenda\UI\AgendaApiClient;

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function appendQueryParams(string $base, array $params): string
{
    $separator = str_contains($base, '?') ? '&' : '?';
    return $base . $separator . http_build_query($params);
}

$client = new AgendaApiClient();

$entryId = trim((string)($_GET['id'] ?? ''));
$doctorId = trim((string)($_GET['doctor_id'] ?? '1'));
$consultorioId = trim((string)($_GET['consultorio_id'] ?? '1'));
$slotMinutes = (int)($_GET['slot_minutes'] ?? 30);
if ($slotMinutes <= 0) {
    $slotMinutes = 30;
}
$dateParam = trim((string)($_GET['date'] ?? ''));
$shortcut = trim((string)($_GET['shortcut'] ?? ''));
$returnTo = trim((string)($_GET['return_to'] ?? ''));

$tz = new DateTimeZone('America/Mexico_City');
$baselineDate = $dateParam !== ''
    ? DateTime::createFromFormat('Y-m-d', $dateParam, $tz)
    : new DateTime('now', $tz);
if (!$baselineDate) {
    $baselineDate = new DateTime('now', $tz);
}

$fallbackDate = $dateParam !== '' ? $dateParam : $baselineDate->format('Y-m-d');
if ($returnTo === '') {
    $returnTo = '/api/agenda/ui/waitlist.php?' . http_build_query([
        'doctor_id' => $doctorId,
        'consultorio_id' => $consultorioId,
        'slot_minutes' => (string)$slotMinutes,
        'date' => $fallbackDate,
    ]);
}

$availableDays = [];
$nextSlot = null;
$next3Slots = [];
$firstError = '';
$maxDays = 30;
for ($i = 0; $i < $maxDays; $i++) {
    $candidate = (clone $baselineDate)->modify("+{$i} days");
    $dateString = $candidate->format('Y-m-d');
    $availability = $client->get('/availability', [
        'doctor_id' => $doctorId,
        'consultorio_id' => $consultorioId,
        'date' => $dateString,
        'slot_minutes' => $slotMinutes,
    ]);
    if (!$availability['ok']) {
        if ($firstError === '') {
            $firstError = (string)($availability['message'] ?? $availability['error']);
        }
        continue;
    }
    $slots = is_array($availability['data']['slots'] ?? null)
        ? $availability['data']['slots']
        : [];
    if (empty($slots)) {
        continue;
    }
    if (count($availableDays) < 10) {
        $availableDays[] = [
            'date' => $dateString,
            'slots_count' => count($slots),
        ];
    }
    if ($shortcut === 'next' && $nextSlot === null) {
        foreach ($slots as $slotCandidate) {
            $start = (string)($slotCandidate['start_at'] ?? '');
            $end = (string)($slotCandidate['end_at'] ?? '');
            if ($start !== '' && $end !== '') {
                $nextSlot = ['start_at' => $start, 'end_at' => $end];
                break;
            }
        }
        if ($nextSlot !== null) {
            break;
        }
    }
    if ($shortcut === 'next3' && count($next3Slots) < 3) {
        foreach ($slots as $slotCandidate) {
            $start = (string)($slotCandidate['start_at'] ?? '');
            $end = (string)($slotCandidate['end_at'] ?? '');
            if ($start === '' || $end === '') {
                continue;
            }
            $next3Slots[] = [
                'date' => $dateString,
                'start_at' => $start,
                'end_at' => $end,
            ];
            if (count($next3Slots) >= 3) {
                break 2;
            }
        }
    }
}

if ($shortcut === 'next' && $nextSlot !== null) {
    $redirectUrl = appendQueryParams($returnTo, [
        'id' => $entryId,
        'to_start_at' => $nextSlot['start_at'],
        'to_end_at' => $nextSlot['end_at'],
    ]);
    header('Location: ' . $redirectUrl);
    exit;
}

$shortcutParams = [
    'id' => $entryId,
    'doctor_id' => $doctorId,
    'consultorio_id' => $consultorioId,
    'slot_minutes' => $slotMinutes,
    'date' => $fallbackDate,
];
if ($returnTo !== '') {
    $shortcutParams['return_to'] = $returnTo;
}

$backUrl = $returnTo;
?>
<div class="mb-3 d-flex justify-content-between align-items-center">
  <h4 class="m-0">Asignar desde Lista de espera</h4>
  <a class="btn btn-outline-secondary btn-sm" href="<?php echo h($backUrl); ?>">Volver a la lista</a>
</div>

<div class="mb-3">
  <div class="btn-group" role="group" aria-label="Atajos de asignación">
    <a class="btn btn-outline-primary btn-sm" href="/api/agenda/ui/waitlist_assign_pick_day.php?<?php echo h(http_build_query(array_merge($shortcutParams, ['shortcut' => 'next']))); ?>">Mostrar la siguiente cita disponible</a>
    <a class="btn btn-outline-primary btn-sm" href="/api/agenda/ui/waitlist_assign_pick_day.php?<?php echo h(http_build_query(array_merge($shortcutParams, ['shortcut' => 'next3']))); ?>">Mostrar las siguientes 3 citas disponibles</a>
  </div>
</div>

<?php if ($firstError !== '' && empty($availableDays)): ?>
  <div class="alert alert-warning">
    <?php echo h($firstError); ?>
  </div>
<?php endif; ?>

<?php if ($shortcut === 'next3'): ?>
  <div class="mb-3">
    <p class="text-muted mb-1">Selecciona una de las siguientes 3 opciones disponibles.</p>
    <?php if (empty($next3Slots)): ?>
      <div class="alert alert-secondary">No hay al menos 3 slots en los próximos 30 días.</div>
    <?php else: ?>
      <div class="list-group mb-2">
        <?php foreach ($next3Slots as $slot): ?>
          <?php
            $confirmUrl = appendQueryParams($returnTo, [
                'id' => $entryId,
                'to_start_at' => $slot['start_at'],
                'to_end_at' => $slot['end_at'],
            ]);
          ?>
          <a class="list-group-item list-group-item-action" href="<?php echo h($confirmUrl); ?>">
            <?php echo h(sprintf('%s · %s – %s', $slot['date'], $slot['start_at'], $slot['end_at'])); ?>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>

<p class="text-muted mb-2">Próximos días con disponibilidad (máximo 10 resultados).</p>

<?php if (empty($availableDays)): ?>
  <div class="alert alert-secondary">
    No se encontraron días disponibles en los próximos 30 días.
  </div>
<?php else: ?>
  <div class="list-group">
    <?php foreach ($availableDays as $day): ?>
      <?php
        $slotDayParams = [
            'id' => $entryId,
            'doctor_id' => $doctorId,
            'consultorio_id' => $consultorioId,
            'slot_minutes' => $slotMinutes,
            'date' => $day['date'],
            'return_to' => $returnTo,
        ];
        $slotDayUrl = '/api/agenda/ui/waitlist_assign_pick_slot.php?' . http_build_query($slotDayParams);
      ?>
      <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
         href="<?php echo h($slotDayUrl); ?>">
        <span><?php echo h($day['date']); ?></span>
        <small class="text-muted"><?php echo h((string)$day['slots_count']); ?> slots</small>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/_layout/footer.php'; ?>
