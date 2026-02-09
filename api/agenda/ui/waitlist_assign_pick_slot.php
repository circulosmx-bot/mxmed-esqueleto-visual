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
    $sep = str_contains($base, '?') ? '&' : '?';
    return $base . $sep . http_build_query($params);
}

$client = new AgendaApiClient();

$entryId = trim((string)($_GET['id'] ?? ''));
$doctorId = trim((string)($_GET['doctor_id'] ?? '1'));
$consultorioId = trim((string)($_GET['consultorio_id'] ?? '1'));
$slotMinutes = (int)($_GET['slot_minutes'] ?? 30);
if ($slotMinutes <= 0) {
    $slotMinutes = 30;
}
$date = trim((string)($_GET['date'] ?? ''));
$returnTo = trim((string)($_GET['return_to'] ?? ''));
$isValidDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1;
$availability = $isValidDate
    ? $client->get('/availability', [
        'doctor_id' => $doctorId,
        'consultorio_id' => $consultorioId,
        'date' => $date,
        'slot_minutes' => $slotMinutes,
    ])
    : null;

$backupReturn = '/api/agenda/ui/waitlist.php?' . http_build_query([
    'doctor_id' => $doctorId,
    'consultorio_id' => $consultorioId,
    'slot_minutes' => $slotMinutes,
    'date' => $date,
]);
if ($returnTo === '') {
    $returnTo = $backupReturn;
}

$backUrlParams = [
    'id' => $entryId,
    'doctor_id' => $doctorId,
    'consultorio_id' => $consultorioId,
    'slot_minutes' => $slotMinutes,
    'date' => $date,
    'return_to' => $returnTo,
];
$backUrl = '/api/agenda/ui/waitlist_assign_pick_day.php?' . http_build_query($backUrlParams);
?>
<div class="mb-3">
  <a class="btn btn-outline-secondary btn-sm" href="<?php echo h($backUrl); ?>">Volver a elegir día</a>
</div>

<h4>Elegir slot</h4>
<?php if (!$isValidDate): ?>
  <div class="alert alert-danger">Fecha inválida</div>
<?php endif; ?>
<?php if ($entryId === ''): ?>
  <div class="alert alert-danger">entry_id requerido</div>
<?php endif; ?>

<?php if ($isValidDate && $availability !== null && !$availability['ok']): ?>
  <div class="alert alert-warning">
    <?php echo h((string)($availability['message'] ?? $availability['error'])); ?>
  </div>
<?php endif; ?>

<?php if ($isValidDate && $availability !== null && $availability['ok']): ?>
  <?php
    $slots = is_array($availability['data']['slots'] ?? null)
        ? $availability['data']['slots']
        : [];
  ?>
  <?php if (empty($slots)): ?>
    <div class="alert alert-secondary">
      No hay slots disponibles para <?php echo h($date); ?>.
    </div>
  <?php else: ?>
    <div class="row g-2">
      <?php foreach ($slots as $slot): ?>
        <?php
          $startAt = (string)($slot['start_at'] ?? '');
          $endAt = (string)($slot['end_at'] ?? '');
          if ($startAt === '' || $endAt === '') {
              continue;
          }
          $confirmUrl = appendQueryParams($returnTo, [
              'id' => $entryId,
              'to_start_at' => $startAt,
              'to_end_at' => $endAt,
          ]);
        ?>
        <div class="col-12 col-md-6">
          <div class="card">
            <div class="card-body">
              <h6 class="card-title mb-2"><?php echo h($startAt); ?> → <?php echo h($endAt); ?></h6>
              <a class="btn btn-outline-primary btn-sm" href="<?php echo h($confirmUrl); ?>">Seleccionar</a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/_layout/footer.php'; ?>
