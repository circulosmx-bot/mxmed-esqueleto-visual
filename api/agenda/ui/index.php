<?php
declare(strict_types=1);

$tz = new DateTimeZone('America/Mexico_City');
$today = (new DateTime('now', $tz))->format('Y-m-d');
$doctorId = '1';
$consultorioId = '1';
$slotMinutes = '30';
$target = sprintf(
    '/api/agenda/ui/day.php?date=%s&doctor_id=%s&consultorio_id=%s&slot_minutes=%s',
    urlencode($today),
    urlencode($doctorId),
    urlencode($consultorioId),
    urlencode($slotMinutes)
);
header('Location: ' . $target);
exit;
