<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/AgendaApiClient.php';
require_once __DIR__ . '/_layout/header.php';
require_once __DIR__ . '/_layout/flash.php';

use Agenda\UI\AgendaApiClient;

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$client = new AgendaApiClient();

$appointmentId = isset($_GET['id']) ? (string)$_GET['id'] : '';
$date = isset($_GET['date']) ? (string)$_GET['date'] : '';

if ($appointmentId === '') {
    echo '<div class="alert alert-danger">appointment_id requerido</div>';
    require_once __DIR__ . '/_layout/footer.php';
    exit;
}

$appointment = $client->get('/appointments/' . urlencode($appointmentId));
$events = $client->get('/appointments/' . urlencode($appointmentId) . '/events', ['limit' => 200]);

$startAt = '';
$endAt = '';
$patientId = '';
if ($appointment['ok'] && is_array($appointment['data'])) {
    $startAt = (string)($appointment['data']['start_at'] ?? '');
    $endAt = (string)($appointment['data']['end_at'] ?? '');
    $patientId = (string)($appointment['data']['patient_id'] ?? '');
}

$backUrl = '/api/agenda/ui/index.php';
if ($date !== '') {
    $backUrl = '/api/agenda/ui/day.php?date=' . urlencode($date);
}
?>
<div class="mb-3">
  <a class="btn btn-outline-secondary btn-sm" href="<?php echo h($backUrl); ?>">Volver al dia</a>
</div>

<h4>Detalle de cita</h4>
<?php if (!$appointment['ok']): ?>
  <div class="alert alert-warning">
    <?php echo h($client->friendlyMessage($appointment)); ?>
    <span class="text-muted">(<?php echo h((string)$appointment['error']); ?>)</span>
  </div>
<?php else: ?>
  <ul>
    <li><strong>ID:</strong> <?php echo h($appointmentId); ?></li>
    <li><strong>Inicio:</strong> <?php echo h($startAt); ?></li>
    <li><strong>Fin:</strong> <?php echo h($endAt); ?></li>
    <li><strong>Status:</strong> <?php echo h((string)($appointment['data']['status'] ?? '')); ?></li>
    <li><strong>Patient:</strong> <?php echo h($patientId); ?></li>
  </ul>
<?php endif; ?>

<h4>Eventos</h4>
<?php if (!$events['ok']): ?>
  <div class="alert alert-warning">
    <?php echo h($client->friendlyMessage($events)); ?>
    <span class="text-muted">(<?php echo h((string)$events['error']); ?>)</span>
  </div>
<?php else: ?>
  <table class="table table-sm table-bordered">
    <thead>
      <tr>
        <th>timestamp</th>
        <th>event_type</th>
        <th>actor</th>
        <th>motivo</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($events['data'])): ?>
        <tr><td colspan="4" class="text-muted">Sin eventos</td></tr>
      <?php else: ?>
        <?php foreach ($events['data'] as $ev): ?>
          <tr>
            <td><?php echo h((string)($ev['timestamp'] ?? $ev['created_at'] ?? '')); ?></td>
            <td><?php echo h((string)($ev['event_type'] ?? '')); ?></td>
            <td><?php echo h((string)($ev['actor_role'] ?? '')); ?> <?php echo h((string)($ev['actor_id'] ?? '')); ?></td>
            <td><?php echo h((string)($ev['motivo_code'] ?? '')); ?> <?php echo h((string)($ev['motivo_text'] ?? '')); ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
<?php endif; ?>

<h4>Acciones</h4>
<div class="row">
  <div class="col-md-4">
    <h6>Cancelar</h6>
    <form method="post" action="action.php">
      <input type="hidden" name="op" value="cancel">
      <input type="hidden" name="appointment_id" value="<?php echo h($appointmentId); ?>">
      <input type="hidden" name="date" value="<?php echo h($date); ?>">
      <div class="mb-2">
        <input type="text" name="reason_code" class="form-control form-control-sm" placeholder="reason_code">
      </div>
      <div class="mb-2">
        <input type="text" name="reason_text" class="form-control form-control-sm" placeholder="reason_text">
      </div>
      <button type="submit" class="btn btn-danger btn-sm">Cancelar</button>
    </form>
  </div>
  <div class="col-md-4">
    <h6>No-show</h6>
    <form method="post" action="action.php">
      <input type="hidden" name="op" value="no_show">
      <input type="hidden" name="appointment_id" value="<?php echo h($appointmentId); ?>">
      <input type="hidden" name="date" value="<?php echo h($date); ?>">
      <div class="mb-2">
        <input type="text" name="motivo_code" class="form-control form-control-sm" placeholder="motivo_code">
      </div>
      <div class="mb-2">
        <input type="text" name="motivo_text" class="form-control form-control-sm" placeholder="motivo_text">
      </div>
      <button type="submit" class="btn btn-warning btn-sm">No-show</button>
    </form>
  </div>
  <div class="col-md-4">
    <h6>Reprogramar</h6>
    <form method="post" action="action.php">
      <input type="hidden" name="op" value="reschedule">
      <input type="hidden" name="appointment_id" value="<?php echo h($appointmentId); ?>">
      <input type="hidden" name="date" value="<?php echo h($date); ?>">
      <input type="hidden" name="from_start_at" value="<?php echo h($startAt); ?>">
      <input type="hidden" name="from_end_at" value="<?php echo h($endAt); ?>">
      <div class="mb-2">
        <input type="text" name="to_start_at" class="form-control form-control-sm" placeholder="YYYY-MM-DD HH:MM:SS">
      </div>
      <div class="mb-2">
        <input type="text" name="to_end_at" class="form-control form-control-sm" placeholder="YYYY-MM-DD HH:MM:SS">
      </div>
      <div class="mb-2">
        <input type="text" name="motivo_code" class="form-control form-control-sm" placeholder="motivo_code">
      </div>
      <div class="mb-2">
        <input type="text" name="motivo_text" class="form-control form-control-sm" placeholder="motivo_text">
      </div>
      <button type="submit" class="btn btn-primary btn-sm">Reprogramar</button>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/_layout/footer.php'; ?>
