<?php
declare(strict_types=1);

error_reporting(E_ALL & ~E_DEPRECATED);

session_start();

require_once __DIR__ . '/lib/AgendaApiClient.php';

use Agenda\UI\AgendaApiClient;

$client = new AgendaApiClient();

function set_flash(string $type, string $message, string $detail = '', string $link = ''): void
{
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message,
        'detail' => $detail,
        'link' => $link,
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

function waitlist_url(string $doctorId, string $consultorioId, string $slotMinutes, string $date): string
{
    $params = [
        'doctor_id' => $doctorId,
        'consultorio_id' => $consultorioId,
        'slot_minutes' => $slotMinutes,
    ];
    if ($date !== '') {
        $params['date'] = $date;
    }
    return '/api/agenda/ui/waitlist.php?' . http_build_query($params);
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

    case 'waitlist_add':
        $doctorId = trim((string)($_POST['doctor_id'] ?? ''));
        $consultorioId = trim((string)($_POST['consultorio_id'] ?? ''));
        $slotMinutes = trim((string)($_POST['slot_minutes'] ?? '30'));
        $date = trim((string)($_POST['date'] ?? ''));
        $patientId = trim((string)($_POST['patient_id'] ?? ''));
        $patientName = trim((string)($_POST['patient_name'] ?? ''));
        $patientPhone = trim((string)($_POST['patient_phone'] ?? ''));
        $notes = trim((string)($_POST['notes'] ?? ''));
        if ($doctorId === '' || $consultorioId === '' || ($patientId === '' && ($patientName === '' || $patientPhone === ''))) {
            set_flash('error', 'Parametros invalidos', 'waitlist_add');
            safe_redirect(waitlist_url($doctorId, $consultorioId, $slotMinutes, $date), $referer);
        }
        $payload = [
            'doctor_id' => $doctorId,
            'consultorio_id' => $consultorioId,
            'actor_role' => 'system',
            'actor_id' => 'ui',
            'channel_origin' => 'waitlist_ui',
        ];
        if ($patientId !== '') {
            $payload['patient_id'] = $patientId;
        } else {
            $payload['patient_name'] = $patientName;
            $payload['patient_phone'] = $patientPhone;
        }
        if ($notes !== '') {
            $payload['notes'] = $notes;
        }
        $resp = $client->post('/waitlist', $payload);
        if ($resp['ok']) {
            set_flash('success', 'Entrada agregada', (string)($resp['data']['id'] ?? ''));
        } else {
            set_flash('error', $client->friendlyMessage($resp), (string)($resp['error'] ?? ''));
        }
        safe_redirect(waitlist_url($doctorId, $consultorioId, $slotMinutes, $date), $referer);
        break;

    case 'waitlist_status':
        $entryId = trim((string)($_POST['id'] ?? ''));
        $status = trim((string)($_POST['status'] ?? ''));
        $doctorId = trim((string)($_POST['doctor_id'] ?? ''));
        $consultorioId = trim((string)($_POST['consultorio_id'] ?? ''));
        $slotMinutes = trim((string)($_POST['slot_minutes'] ?? '30'));
        $date = trim((string)($_POST['date'] ?? ''));
        $allowed = ['contacted', 'accepted', 'declined', 'removed'];
        if ($entryId === '' || $status === '' || !in_array($status, $allowed, true)) {
            set_flash('error', 'Parametros invalidos', 'waitlist_status');
            safe_redirect(waitlist_url($doctorId, $consultorioId, $slotMinutes, $date), $referer);
        }
        $resp = $client->patch('/waitlist/' . urlencode($entryId), ['status' => $status]);
        if ($resp['ok']) {
            set_flash('success', 'Estado actualizado', $status);
        } else {
            set_flash('error', $client->friendlyMessage($resp), (string)($resp['error'] ?? ''));
        }
        safe_redirect(waitlist_url($doctorId, $consultorioId, $slotMinutes, $date), $referer);
        break;

    case 'waitlist_assign_confirm':
        $entryId = trim((string)($_POST['id'] ?? ''));
        $doctorId = trim((string)($_POST['doctor_id'] ?? ''));
        $consultorioId = trim((string)($_POST['consultorio_id'] ?? ''));
        $slotMinutesParam = trim((string)($_POST['slot_minutes'] ?? '30'));
        $date = trim((string)($_POST['date'] ?? ''));
        $slotMinutesValue = (int)$slotMinutesParam;
        if ($slotMinutesValue <= 0) {
            $slotMinutesValue = 30;
        }
        $startAt = normalize_datetime(trim((string)($_POST['start_at'] ?? '')));
        $endAt = normalize_datetime(trim((string)($_POST['end_at'] ?? '')));
        $override = isset($_POST['override']) && $_POST['override'] !== '0';
        $overrideReason = trim((string)($_POST['override_reason'] ?? ''));
        $linkedCancelled = trim((string)($_POST['linked_cancelled_appointment_id'] ?? ''));
        $actorRole = trim((string)($_POST['actor_role'] ?? 'system'));
        $actorId = trim((string)($_POST['actor_id'] ?? 'ui'));
        $channelOrigin = trim((string)($_POST['channel_origin'] ?? 'waitlist_ui'));
        if (
            $entryId === '' ||
            $doctorId === '' ||
            $consultorioId === '' ||
            !is_valid_datetime($startAt) ||
            !is_valid_datetime($endAt)
        ) {
            set_flash('error', 'Parametros invalidos', 'waitlist_assign');
            safe_redirect(waitlist_url($doctorId, $consultorioId, $slotMinutesParam, $date), $referer);
        }
        $payload = [
            'doctor_id' => $doctorId,
            'consultorio_id' => $consultorioId,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'slot_minutes' => $slotMinutesValue,
            'override' => $override,
            'override_reason' => $overrideReason,
            'actor_role' => $actorRole,
            'actor_id' => $actorId,
            'channel_origin' => $channelOrigin,
        ];
        if ($linkedCancelled !== '') {
            $payload['linked_cancelled_appointment_id'] = $linkedCancelled;
        }
        $resp = $client->post('/waitlist/' . urlencode($entryId) . '/assign', $payload);
        if ($resp['ok']) {
            $appointmentId = (string)($resp['data']['appointment_id'] ?? '');
            $detail = $appointmentId !== '' ? 'appointment_id=' . $appointmentId : 'entry=' . $entryId;
            $link = $appointmentId !== '' ? '/api/agenda/ui/appointment.php?id=' . urlencode($appointmentId) : '';
            set_flash('success', 'Asignación completada', $detail, $link);
        } else {
            set_flash('error', $client->friendlyMessage($resp), (string)($resp['error'] ?? ''));
        }
        safe_redirect(waitlist_url($doctorId, $consultorioId, $slotMinutesParam, $date), $referer);
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
        $fromStart = normalize_datetime(trim((string)($_POST['from_start_at'] ?? '')));
        $fromEnd = normalize_datetime(trim((string)($_POST['from_end_at'] ?? '')));
        $toStart = normalize_datetime(trim((string)($_POST['to_start_at'] ?? '')));
        $toEnd = normalize_datetime(trim((string)($_POST['to_end_at'] ?? '')));
        $redirectOnError = '/api/agenda/ui/index.php';
        if ($appointmentId !== '') {
            $redirectOnError = '/api/agenda/ui/appointment.php?id=' . urlencode($appointmentId);
            if ($date !== '') {
                $redirectOnError .= '&date=' . urlencode($date);
            }
        }
        if ($appointmentId === '' || $toStart === '' || $toEnd === '' || !is_valid_datetime($toStart) || !is_valid_datetime($toEnd)) {
            set_flash('error', 'Parametros invalidos', 'reschedule');
            safe_redirect($redirectOnError, $referer);
        }
        $notifyPatient = isset($_POST['notify_patient']) && $_POST['notify_patient'] !== '0';
        $payload = [
            'from_start_at' => $fromStart,
            'from_end_at' => $fromEnd,
            'to_start_at' => $toStart,
            'to_end_at' => $toEnd,
            'motivo_code' => trim((string)($_POST['motivo_code'] ?? '')),
            'motivo_text' => trim((string)($_POST['motivo_text'] ?? '')),
            'notify_patient' => $notifyPatient,
            'contact_method' => trim((string)($_POST['contact_method'] ?? 'none')),
            'actor_role' => trim((string)($_POST['actor_role'] ?? 'system')),
            'actor_id' => trim((string)($_POST['actor_id'] ?? 'ui')),
            'channel_origin' => trim((string)($_POST['channel_origin'] ?? 'ui')),
        ];
        $resp = $client->patch('/appointments/' . urlencode($appointmentId) . '/reschedule', $payload);
        if ($resp['ok']) {
            set_flash('success', 'Cita reprogramada', (string)($resp['data']['status'] ?? ''));
        } else {
            $errorCode = (string)($resp['error'] ?? '');
            $errorMessage = (string)($resp['message'] ?? '');
            $displayMessage = $errorMessage !== '' ? $errorMessage : $client->friendlyMessage($resp);
            $flashMessage = $errorCode !== '' ? ($errorCode . ': ' . $displayMessage) : $displayMessage;
            set_flash('error', $flashMessage, $displayMessage);
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
