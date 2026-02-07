<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/lib/AgendaApiClient.php';

use Agenda\UI\AgendaApiClient;

$client = new AgendaApiClient();

function set_flash(string $type, string $message, string $detail = ''): void
{
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message,
        'detail' => $detail,
    ];
}

function normalize_datetime(string $value): string
{
    return str_replace('T', ' ', $value);
}

function is_valid_datetime(string $value): bool
{
    $value = normalize_datetime($value);
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $value);
    return $dt && $dt->format('Y-m-d H:i:s') === $value;
}

function safe_redirect(string $fallback, ?string $preferred): void
{
    $target = $fallback;
    if ($preferred && str_starts_with($preferred, '/api/agenda/ui/')) {
        $target = $preferred;
    }
    header('Location: ' . $target);
    exit;
}

$op = $_POST['op'] ?? '';
$referer = $_SERVER['HTTP_REFERER'] ?? '';

switch ($op) {
    case 'create':
        $doctorId = trim((string)($_POST['doctor_id'] ?? ''));
        $consultorioId = trim((string)($_POST['consultorio_id'] ?? ''));
        $startAt = normalize_datetime(trim((string)($_POST['start_at'] ?? '')));
        $endAt = normalize_datetime(trim((string)($_POST['end_at'] ?? '')));
        $slotMinutes = (int)($_POST['slot_minutes'] ?? 30);
        $date = trim((string)($_POST['date'] ?? ''));

        if ($doctorId === '' || $consultorioId === '' || !is_valid_datetime($startAt) || !is_valid_datetime($endAt)) {
            set_flash('error', 'Parametros invalidos', 'create');
            safe_redirect('/api/agenda/ui/day.php', $referer);
        }

        $payload = [
            'doctor_id' => $doctorId,
            'consultorio_id' => $consultorioId,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'slot_minutes' => $slotMinutes,
            'modality' => 'presencial',
            'channel_origin' => 'ui_internal',
            'created_by_role' => 'system',
            'created_by_id' => 'ui',
        ];

        $patientId = trim((string)($_POST['patient_id'] ?? ''));
        $patientName = trim((string)($_POST['patient_name'] ?? ''));
        $patientPhone = trim((string)($_POST['patient_phone'] ?? ''));
        if ($patientId !== '') {
            $payload['patient_id'] = $patientId;
        } elseif ($patientName !== '') {
            $patient = ['display_name' => $patientName];
            if ($patientPhone !== '') {
                $patient['contacts'] = [[
                    'type' => 'phone',
                    'value' => $patientPhone,
                    'is_primary' => true,
                ]];
            }
            $payload['patient'] = $patient;
        }

        $resp = $client->post('/appointments', $payload);
        if ($resp['ok']) {
            set_flash('success', 'Cita creada', (string)($resp['data']['appointment_id'] ?? ''));
        } else {
            set_flash('error', $client->friendlyMessage($resp), (string)$resp['error']);
        }
        $redirect = '/api/agenda/ui/day.php?date=' . urlencode($date);
        safe_redirect($redirect, $referer);
        break;

    case 'cancel':
        $appointmentId = trim((string)($_POST['appointment_id'] ?? ''));
        $date = trim((string)($_POST['date'] ?? ''));
        if ($appointmentId === '') {
            set_flash('error', 'appointment_id requerido', 'cancel');
            safe_redirect('/api/agenda/ui/index.php', $referer);
        }
        $payload = [
            'reason_code' => trim((string)($_POST['reason_code'] ?? '')),
            'reason_text' => trim((string)($_POST['reason_text'] ?? '')),
            'actor_role' => 'system',
            'actor_id' => 'ui',
            'channel_origin' => 'ui_internal',
            'notify_patient' => false,
            'contact_method' => 'none',
        ];
        $resp = $client->post('/appointments/' . urlencode($appointmentId) . '/cancel', $payload);
        if ($resp['ok']) {
            set_flash('success', 'Cita cancelada', (string)($resp['data']['status'] ?? ''));
        } else {
            set_flash('error', $client->friendlyMessage($resp), (string)$resp['error']);
        }
        $redirect = '/api/agenda/ui/appointment.php?id=' . urlencode($appointmentId);
        if ($date !== '') {
            $redirect .= '&date=' . urlencode($date);
        }
        safe_redirect($redirect, $referer);
        break;

    case 'no_show':
        $appointmentId = trim((string)($_POST['appointment_id'] ?? ''));
        $date = trim((string)($_POST['date'] ?? ''));
        if ($appointmentId === '') {
            set_flash('error', 'appointment_id requerido', 'no_show');
            safe_redirect('/api/agenda/ui/index.php', $referer);
        }
        $payload = [
            'motivo_code' => trim((string)($_POST['motivo_code'] ?? '')),
            'motivo_text' => trim((string)($_POST['motivo_text'] ?? '')),
            'actor_role' => 'system',
            'actor_id' => 'ui',
            'channel_origin' => 'ui_internal',
            'notify_patient' => false,
            'contact_method' => 'none',
        ];
        $resp = $client->post('/appointments/' . urlencode($appointmentId) . '/no-show', $payload);
        if ($resp['ok']) {
            $msg = (string)($resp['message'] ?? '');
            $detail = $msg !== '' ? $msg : (string)($resp['data']['status'] ?? '');
            set_flash('success', 'No-show registrado', $detail);
        } else {
            set_flash('error', $client->friendlyMessage($resp), (string)$resp['error']);
        }
        $redirect = '/api/agenda/ui/appointment.php?id=' . urlencode($appointmentId);
        if ($date !== '') {
            $redirect .= '&date=' . urlencode($date);
        }
        safe_redirect($redirect, $referer);
        break;

    case 'reschedule':
        $appointmentId = trim((string)($_POST['appointment_id'] ?? ''));
        $date = trim((string)($_POST['date'] ?? ''));
        $toStart = normalize_datetime(trim((string)($_POST['to_start_at'] ?? '')));
        $toEnd = normalize_datetime(trim((string)($_POST['to_end_at'] ?? '')));
        $fromStart = normalize_datetime(trim((string)($_POST['from_start_at'] ?? '')));
        $fromEnd = normalize_datetime(trim((string)($_POST['from_end_at'] ?? '')));
        if ($appointmentId === '' || !is_valid_datetime($toStart) || !is_valid_datetime($toEnd)) {
            set_flash('error', 'Parametros invalidos', 'reschedule');
            safe_redirect('/api/agenda/ui/index.php', $referer);
        }
        $payload = [
            'from_start_at' => $fromStart,
            'from_end_at' => $fromEnd,
            'to_start_at' => $toStart,
            'to_end_at' => $toEnd,
            'motivo_code' => trim((string)($_POST['motivo_code'] ?? '')),
            'motivo_text' => trim((string)($_POST['motivo_text'] ?? '')),
            'notify_patient' => false,
            'contact_method' => 'none',
        ];
        $resp = $client->patch('/appointments/' . urlencode($appointmentId) . '/reschedule', $payload);
        if ($resp['ok']) {
            set_flash('success', 'Cita reprogramada', (string)($resp['data']['status'] ?? ''));
        } else {
            set_flash('error', $client->friendlyMessage($resp), (string)$resp['error']);
        }
        $redirect = '/api/agenda/ui/appointment.php?id=' . urlencode($appointmentId);
        if ($date !== '') {
            $redirect .= '&date=' . urlencode($date);
        }
        safe_redirect($redirect, $referer);
        break;

    default:
        set_flash('error', 'Operacion no soportada', $op);
        safe_redirect('/api/agenda/ui/index.php', $referer);
}
