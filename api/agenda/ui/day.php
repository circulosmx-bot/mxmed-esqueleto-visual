<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/AgendaApiClient.php';
require_once __DIR__ . '/_layout/header.php';
require_once __DIR__ . '/_layout/flash.php';

use Agenda\UI\AgendaApiClient;

$tz = new DateTimeZone('America/Mexico_City');
$today = (new DateTime('now', $tz))->format('Y-m-d');
$date = $_GET['date'] ?? $today;
$doctorId = $_GET['doctor_id'] ?? '1';
$consultorioId = $_GET['consultorio_id'] ?? '1';
$slotMinutes = $_GET['slot_minutes'] ?? '30';

$client = new AgendaApiClient();

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function is_valid_date(string $value): bool
{
    $dt = DateTime::createFromFormat('Y-m-d', $value);
    return $dt && $dt->format('Y-m-d') === $value;
}

if (!is_valid_date($date)) {
    $date = $today;
}

$from = $date . ' 00:00:00';
$to = $date . ' 23:59:59';

$availability = $client->get('/availability', [
    'doctor_id' => $doctorId,
    'consultorio_id' => $consultorioId,
    'date' => $date,
    'slot_minutes' => $slotMinutes,
]);

$appointments = $client->get('/appointments', [
    'from' => $from,
    'to' => $to,
    'doctor_id' => $doctorId,
    'consultorio_id' => $consultorioId,
]);

$prevDate = (new DateTime($date, $tz))->modify('-1 day')->format('Y-m-d');
$nextDate = (new DateTime($date, $tz))->modify('+1 day')->format('Y-m-d');

$slots = [];
$windows = [];
if ($availability['ok'] && is_array($availability['data'])) {
    $windows = $availability['data']['windows'] ?? [];
    $slots = $availability['data']['slots'] ?? [];
}

$list = [];
if ($appointments['ok'] && is_array($appointments['data'])) {
    $list = $appointments['data'];
}

?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <a class="btn btn-outline-secondary btn-sm" href="day.php?date=<?php echo h($prevDate); ?>&doctor_id=<?php echo h((string)$doctorId); ?>&consultorio_id=<?php echo h((string)$consultorioId); ?>&slot_minutes=<?php echo h((string)$slotMinutes); ?>">Dia anterior</a>
    <a class="btn btn-outline-secondary btn-sm" href="day.php?date=<?php echo h($nextDate); ?>&doctor_id=<?php echo h((string)$doctorId); ?>&consultorio_id=<?php echo h((string)$consultorioId); ?>&slot_minutes=<?php echo h((string)$slotMinutes); ?>">Dia siguiente</a>
  </div>
  <form class="d-flex" method="get" action="day.php">
    <input type="date" class="form-control form-control-sm me-2" name="date" value="<?php echo h($date); ?>">
    <input type="hidden" name="doctor_id" value="<?php echo h((string)$doctorId); ?>">
    <input type="hidden" name="consultorio_id" value="<?php echo h((string)$consultorioId); ?>">
    <input type="hidden" name="slot_minutes" value="<?php echo h((string)$slotMinutes); ?>">
    <button type="submit" class="btn btn-primary btn-sm">Ir</button>
  </form>
</div>

<h4>Disponibilidad</h4>
<?php if (!$availability['ok']): ?>
  <div class="alert alert-warning">
    <?php echo h($client->friendlyMessage($availability)); ?>
    <span class="text-muted">(<?php echo h((string)$availability['error']); ?>)</span>
  </div>
<?php endif; ?>

<div class="mb-3">
  <strong>Windows:</strong>
  <?php if (empty($windows)): ?>
    <span class="text-muted">Sin ventanas</span>
  <?php else: ?>
    <ul>
      <?php foreach ($windows as $w): ?>
        <li><?php echo h((string)($w['start_at'] ?? '')); ?> - <?php echo h((string)($w['end_at'] ?? '')); ?> (<?php echo h((string)($w['source'] ?? 'A')); ?>)</li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>

<table class="table table-sm table-bordered">
  <thead>
    <tr>
      <th>Slot</th>
      <th>Estado</th>
      <th>Crear cita</th>
    </tr>
  </thead>
  <tbody>
    <?php if (empty($slots)): ?>
      <tr><td colspan="3" class="text-muted">Sin slots disponibles</td></tr>
    <?php else: ?>
      <?php foreach ($slots as $slot): ?>
        <tr>
          <td><?php echo h((string)($slot['start_at'] ?? '')); ?> - <?php echo h((string)($slot['end_at'] ?? '')); ?></td>
          <td>available</td>
          <td>
            <form method="post" action="action.php">
              <input type="hidden" name="op" value="create">
              <input type="hidden" name="doctor_id" value="<?php echo h((string)$doctorId); ?>">
              <input type="hidden" name="consultorio_id" value="<?php echo h((string)$consultorioId); ?>">
              <input type="hidden" name="slot_minutes" value="<?php echo h((string)$slotMinutes); ?>">
              <input type="hidden" name="date" value="<?php echo h($date); ?>">
              <input type="hidden" name="start_at" value="<?php echo h((string)($slot['start_at'] ?? '')); ?>">
              <input type="hidden" name="end_at" value="<?php echo h((string)($slot['end_at'] ?? '')); ?>">
              <div class="input-group input-group-sm">
                <input type="text" name="patient_id" class="form-control" placeholder="patient_id (opcional)">
                <input type="text" name="patient_name" class="form-control" placeholder="display_name (opcional)">
                <input type="text" name="patient_phone" class="form-control" placeholder="phone (opcional)">
                <button class="btn btn-success" type="submit">Crear</button>
              </div>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    <?php endif; ?>
  </tbody>
</table>

<h4>Citas del dia</h4>
<?php if (!$appointments['ok']): ?>
  <div class="alert alert-warning">
    <?php echo h($client->friendlyMessage($appointments)); ?>
    <span class="text-muted">(<?php echo h((string)$appointments['error']); ?>)</span>
  </div>
<?php endif; ?>

<table class="table table-sm table-striped">
  <thead>
    <tr>
      <th>Hora</th>
      <th>Appointment</th>
      <th>Status</th>
      <th>Patient</th>
      <th>Flags</th>
      <th>Link</th>
    </tr>
  </thead>
  <tbody>
    <?php if (empty($list)): ?>
      <tr><td colspan="6" class="text-muted">Sin citas</td></tr>
    <?php else: ?>
      <?php foreach ($list as $appt): ?>
        <tr>
          <td><?php echo h((string)($appt['start_at'] ?? '')); ?></td>
          <td><?php echo h((string)($appt['appointment_id'] ?? '')); ?></td>
          <td><?php echo h((string)($appt['status'] ?? '')); ?></td>
          <td><?php echo h((string)($appt['patient_id'] ?? '')); ?></td>
          <td>-</td>
          <td><a href="appointment.php?id=<?php echo h((string)($appt['appointment_id'] ?? '')); ?>&date=<?php echo h($date); ?>">Ver</a></td>
        </tr>
      <?php endforeach; ?>
    <?php endif; ?>
  </tbody>
</table>

<?php require_once __DIR__ . '/_layout/footer.php'; ?>
