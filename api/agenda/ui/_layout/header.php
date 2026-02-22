<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
error_reporting(E_ALL & ~E_DEPRECATED);
$navDefaultDate = (new DateTime('now', new DateTimeZone('America/Mexico_City')))->format('Y-m-d');
?><!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Agenda v1</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .appointment-muted {
      opacity: 0.55;
      color: #6c757d;
    }
    .appointment-muted a {
      color: inherit;
      text-decoration: underline;
    }
  </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light bg-light">
  <div class="container-fluid">
    <a class="navbar-brand" href="/api/agenda/ui/index.php">Agenda v1</a>
    <?php
      $navDate = (string)($_GET['date'] ?? $navDefaultDate);
      $navSlotMinutes = (string)($_GET['slot_minutes'] ?? '30');
      if ((int)$navSlotMinutes <= 0) {
        $navSlotMinutes = '30';
      }
      $navDoctorId = isset($_GET['doctor_id']) ? (string)$_GET['doctor_id'] : '';
      $navConsultorioId = isset($_GET['consultorio_id']) ? (string)$_GET['consultorio_id'] : '';
      $navParams = [
        'date' => $navDate,
        'slot_minutes' => $navSlotMinutes,
      ];
      if ($navDoctorId !== '') {
        $navParams['doctor_id'] = $navDoctorId;
      }
      if ($navConsultorioId !== '') {
        $navParams['consultorio_id'] = $navConsultorioId;
      }
      $dayHref = '/api/agenda/ui/day.php?' . http_build_query($navParams);
      $waitlistHref = '/api/agenda/ui/waitlist.php?' . http_build_query($navParams);
    ?>
    <div class="d-flex gap-2">
      <a class="btn btn-outline-primary btn-sm" href="<?php echo htmlspecialchars($dayHref, ENT_QUOTES, 'UTF-8'); ?>">Agenda</a>
      <a class="btn btn-outline-success btn-sm" href="<?php echo htmlspecialchars($waitlistHref, ENT_QUOTES, 'UTF-8'); ?>">Lista de espera</a>
    </div>
  </div>
</nav>
<div class="container my-4">
