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

$tz = new DateTimeZone('America/Mexico_City');
$today = (new DateTime('now', $tz))->format('Y-m-d');
$date = trim($_GET['date'] ?? $today);
$doctorId = trim((string)($_GET['doctor_id'] ?? '1'));
$consultorioId = trim((string)($_GET['consultorio_id'] ?? '1'));
$slotMinutes = trim((string)($_GET['slot_minutes'] ?? '30'));
if ((int)$slotMinutes <= 0) {
    $slotMinutes = '30';
}

$client = new AgendaApiClient();
$entriesResp = $client->get('/waitlist', [
    'doctor_id' => $doctorId,
    'consultorio_id' => $consultorioId,
    'status' => 'active',
]);
$entries = [];
if ($entriesResp['ok'] && is_array($entriesResp['data'])) {
    $entries = $entriesResp['data'];
}

$baseParams = [
    'doctor_id' => $doctorId,
    'consultorio_id' => $consultorioId,
    'slot_minutes' => $slotMinutes,
    'date' => $date,
];
$baseUrl = '/api/agenda/ui/waitlist.php?' . http_build_query($baseParams);
$dayUrl = '/api/agenda/ui/day.php?date=' . urlencode($date) . '&doctor_id=' . urlencode($doctorId) . '&consultorio_id=' . urlencode($consultorioId) . '&slot_minutes=' . urlencode($slotMinutes);

$selectedEntryId = trim((string)($_GET['id'] ?? ''));
$selectedStart = trim((string)($_GET['to_start_at'] ?? ''));
$selectedEnd = trim((string)($_GET['to_end_at'] ?? ''));
$selectedEntry = null;
foreach ($entries as $entry) {
    if (isset($entry['id']) && $entry['id'] === $selectedEntryId) {
        $selectedEntry = $entry;
        break;
    }
}

function displayPatient(array $entry): string
{
    if (!empty($entry['patient_name'])) {
        return $entry['patient_name'];
    }
    if (!empty($entry['patient_id'])) {
        return 'Paciente ' . $entry['patient_id'];
    }
    if (!empty($entry['patient_phone'])) {
        return 'Contacto ' . $entry['patient_phone'];
    }
    return 'Paciente desconocido';
}

?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h4 class="m-0">Lista de espera</h4>
    <small class="text-muted">Doctor <?php echo h($doctorId); ?> · Consultorio <?php echo h($consultorioId); ?></small>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary btn-sm" href="<?php echo h($dayUrl); ?>">Volver al día</a>
    <a class="btn btn-outline-primary btn-sm" href="<?php echo h($baseUrl); ?>">Recargar</a>
  </div>
</div>

<?php if (!$entriesResp['ok']): ?>
  <div class="alert alert-warning">
    <?php echo h($client->friendlyMessage($entriesResp)); ?>
    <span class="text-muted">(<?php echo h((string)($entriesResp['error'] ?? 'error desconocido')); ?>)</span>
  </div>
<?php endif; ?>

<?php if ($selectedEntry && $selectedStart !== '' && $selectedEnd !== ''): ?>
  <div class="card mb-4">
    <div class="card-body">
      <h5 class="card-title">Confirmar asignación</h5>
      <p class="card-text mb-1">
        <strong>Paciente:</strong> <?php echo h(displayPatient($selectedEntry)); ?>
      </p>
      <p class="card-text mb-1">
        <strong>Horario seleccionado:</strong>
        <?php echo h($selectedStart); ?> – <?php echo h($selectedEnd); ?>
      </p>
      <form method="post" action="action.php">
        <input type="hidden" name="op" value="waitlist_assign_confirm">
        <input type="hidden" name="id" value="<?php echo h($selectedEntryId); ?>">
        <input type="hidden" name="doctor_id" value="<?php echo h($doctorId); ?>">
        <input type="hidden" name="consultorio_id" value="<?php echo h($consultorioId); ?>">
        <input type="hidden" name="slot_minutes" value="<?php echo h($slotMinutes); ?>">
        <input type="hidden" name="start_at" value="<?php echo h($selectedStart); ?>">
        <input type="hidden" name="end_at" value="<?php echo h($selectedEnd); ?>">
        <input type="hidden" name="date" value="<?php echo h($date); ?>">
        <input type="hidden" name="actor_role" value="system">
        <input type="hidden" name="actor_id" value="ui">
        <input type="hidden" name="channel_origin" value="waitlist_ui">
        <div class="row g-3 mb-3">
          <div class="col-6 form-check">
            <input class="form-check-input" type="checkbox" name="override" value="1" id="overrideCheck">
            <label class="form-check-label" for="overrideCheck">Override manual</label>
          </div>
          <div class="col-6">
            <input class="form-control form-control-sm" type="text" name="override_reason" placeholder="Motivo override">
          </div>
        </div>
        <div class="mb-3">
          <input class="form-control form-control-sm" type="text" name="linked_cancelled_appointment_id" placeholder="Cita cancelada (opcional)">
        </div>
        <div class="d-flex gap-2">
          <button class="btn btn-success btn-sm" type="submit">Confirmar asignación</button>
          <?php
            $changeParams = [
                'id' => $selectedEntryId,
                'doctor_id' => $doctorId,
                'consultorio_id' => $consultorioId,
                'slot_minutes' => $slotMinutes,
                'date' => $date,
                'return_to' => $baseUrl,
            ];
          ?>
          <a class="btn btn-outline-secondary btn-sm" href="/api/agenda/ui/waitlist_assign_pick_day.php?<?php echo h(http_build_query($changeParams)); ?>">Cambiar selección</a>
        </div>
      </form>
    </div>
  </div>
<?php endif; ?>

<div class="row gy-3">
  <div class="col-md-5">
    <div class="card">
      <div class="card-body">
        <h5 class="card-title">Agregar a lista de espera</h5>
        <form method="post" action="action.php">
          <input type="hidden" name="op" value="waitlist_add">
          <input type="hidden" name="doctor_id" value="<?php echo h($doctorId); ?>">
          <input type="hidden" name="consultorio_id" value="<?php echo h($consultorioId); ?>">
          <input type="hidden" name="slot_minutes" value="<?php echo h($slotMinutes); ?>">
          <input type="hidden" name="date" value="<?php echo h($date); ?>">
          <div class="mb-3">
            <label class="form-label">Patient ID (opcional)</label>
            <input class="form-control form-control-sm" type="text" name="patient_id">
          </div>
          <div class="mb-3">
            <label class="form-label">Nombre</label>
            <input class="form-control form-control-sm" type="text" name="patient_name">
          </div>
          <div class="mb-3">
            <label class="form-label">Teléfono</label>
            <input class="form-control form-control-sm" type="text" name="patient_phone">
          </div>
          <div class="mb-3">
            <label class="form-label">Notas</label>
            <textarea class="form-control form-control-sm" name="notes" rows="2"></textarea>
          </div>
          <small class="text-muted d-block mb-2">Si no hay patient_id, indica nombre y teléfono.</small>
          <div class="d-grid">
            <button class="btn btn-primary btn-sm" type="submit">Agregar</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  <div class="col-md-7">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <h5 class="mb-0">Entradas activas (<?php echo count($entries); ?>)</h5>
      <span class="text-muted"><?php echo h($slotMinutes); ?> min slots</span>
    </div>
    <div class="alert alert-info">
      Esta lista se usa cuando la agenda está saturada y sólo se asigna cuando aparece un hueco real (cancelación). No es una cita confirmada; la entrada tiene vigencia de 7 días y permanece en cola hasta que se crea una cita o se expira.
    </div>
    <?php if (empty($entries)): ?>
      <div class="alert alert-secondary">Sin entradas en espera.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
          <thead>
            <tr>
              <th>Paciente</th>
              <th>Contacto</th>
              <th>Notas</th>
              <th>Creada</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($entries as $entry): ?>
              <?php
                $entryId = (string)($entry['id'] ?? '');
                $patientName = $entry['patient_name'] ?? '';
                $patientPhone = $entry['patient_phone'] ?? '';
                $patientId = $entry['patient_id'] ?? '';
                $notes = $entry['notes'] ?? '';
                $createdAt = $entry['created_at'] ?? '';
                $assignParams = [
                    'id' => $entryId,
                    'doctor_id' => $doctorId,
                    'consultorio_id' => $consultorioId,
                    'slot_minutes' => $slotMinutes,
                    'date' => $date,
                    'return_to' => $baseUrl,
                ];
              ?>
              <tr>
                <td>
                  <?php if ($patientName !== ''): ?>
                    <?php echo h($patientName); ?>
                  <?php elseif ($patientId !== ''): ?>
                    ID <?php echo h($patientId); ?>
                  <?php else: ?>
                    <?php echo h($patientPhone !== '' ? $patientPhone : 'Paciente sin nombre'); ?>
                  <?php endif; ?>
                </td>
                <td><?php echo h($patientPhone ?? '—'); ?></td>
                <td><?php echo h($notes !== '' ? $notes : '—'); ?></td>
                <td><?php echo h($createdAt !== '' ? $createdAt : '—'); ?></td>
                <td>
                  <div class="d-flex flex-wrap gap-2">
                    <?php foreach (['contacted' => 'Contactado', 'declined' => 'Rechazado', 'removed' => 'Eliminado'] as $status => $label): ?>
                      <form method="post" action="action.php" class="m-0">
                        <input type="hidden" name="op" value="waitlist_status">
                        <input type="hidden" name="id" value="<?php echo h($entryId); ?>">
                        <input type="hidden" name="status" value="<?php echo h($status); ?>">
                        <input type="hidden" name="doctor_id" value="<?php echo h($doctorId); ?>">
                        <input type="hidden" name="consultorio_id" value="<?php echo h($consultorioId); ?>">
                        <input type="hidden" name="slot_minutes" value="<?php echo h($slotMinutes); ?>">
                        <input type="hidden" name="date" value="<?php echo h($date); ?>">
                        <button type="submit" class="btn btn-outline-secondary btn-sm"><?php echo h($label); ?></button>
                      </form>
                    <?php endforeach; ?>
                    <div class="d-flex flex-column">
                      <a class="btn btn-link btn-sm text-secondary ps-0" href="/api/agenda/ui/waitlist_assign_pick_day.php?<?php echo h(http_build_query($assignParams)); ?>">Asignar (solo cuando hay hueco)</a>
                      <small class="text-muted">Usa este enlace solo si se detecta una cancelación o hueco.</small>
                    </div>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/_layout/footer.php'; ?>
