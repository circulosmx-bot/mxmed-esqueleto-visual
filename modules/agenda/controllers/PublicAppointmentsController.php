<?php
namespace Agenda\Controllers;

use Agenda\Adapters\CanonicalPublicAgendaAdapter;
use Agenda\Contracts\OtpProviderPort;
use Agenda\Helpers\DoctorIdentity as DoctorIdentity;
use Agenda\Repositories\ConsultoriosRepository;
use Agenda\Repositories\PublicOtpRepository;
use Agenda\Services\DevOtpSender;
use Agenda\Services\OtpSender;
use Patients\Services\PublicBookingPatientIdentityResolver;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOException;
use RuntimeException;

require_once __DIR__ . '/../adapters/CanonicalPublicAgendaAdapter.php';
require_once __DIR__ . '/../repositories/ConsultoriosRepository.php';
require_once __DIR__ . '/../repositories/PublicOtpRepository.php';
require_once __DIR__ . '/../services/OtpSender.php';
require_once __DIR__ . '/../helpers/doctor_identity.php';
require_once __DIR__ . '/AvailabilityController.php';
require_once __DIR__ . '/AppointmentWriteController.php';
require_once __DIR__ . '/../config/agenda.php';
require_once __DIR__ . '/../../../api/_lib/db.php';
require_once __DIR__ . '/../../patients/services/PublicBookingPatientIdentityResolver.php';

class PublicAppointmentsController
{
    private const TIMEZONE = 'America/Mexico_City';
    private const OTP_TABLE = 'agenda_public_otp_requests';
    private const FLOW_TABLE = 'agenda_public_appointment_flows';
    private const OTP_TTL_MINUTES = 10;
    private const OTP_MAX_ATTEMPTS = 5;

    private ?PDO $pdo = null;
    private ?string $dbError = null;
    private OtpSender $otpSender;
    private ?OtpProviderPort $publicOtpProvider = null;
    private $clock;

    public function __construct(?OtpSender $otpSender = null, ?PDO $pdo = null, ?OtpProviderPort $publicOtpProvider = null, ?callable $clock = null)
    {
        $config = require __DIR__ . '/../config/agenda.php';
        $canonicalPublicAgendaAdapterClass = CanonicalPublicAgendaAdapter::canonicalPublicAgendaEnabled($config)
            ? CanonicalPublicAgendaAdapter::class
            : null;
        try {
            $this->pdo = $pdo ?? mxmed_pdo();
        } catch (RuntimeException $e) {
            $this->dbError = 'database error';
        } catch (\Throwable $e) {
            $this->dbError = 'database error';
        }

        $this->otpSender = $otpSender ?: new DevOtpSender();
        $this->publicOtpProvider = $publicOtpProvider;
        $this->clock = $clock;
    }

    public function request(array $payload = []): array
    {
        if ($this->dbError || !$this->pdo) {
            return $this->error('db_error', 'database error');
        }

        $validation = $this->validateRequestPayload($payload);
        if (!empty($validation['errors'])) {
            return $this->error('invalid_params', 'invalid payload', ['fields' => $validation['errors']]);
        }

        $doctorId = $this->resolveCanonicalDoctorId((string)$validation['doctor_id']);
        if ($doctorId === '') {
            return $this->error('invalid_params', 'invalid payload', [
                'fields' => ['doctor_id' => 'required'],
            ]);
        }
        $consultorioId = $this->resolveConsultorioId(
            $doctorId,
            $payload['consultorio_id'] ?? null,
            (string)$validation['start_at'],
            (string)$validation['end_at']
        );

        if (!$this->isValidNumeric($consultorioId)) {
            return $this->error('invalid_params', 'consultorio_id must be numeric', [
                'doctor_id' => $doctorId,
                'consultorio_id_used' => null,
            ]);
        }

        try {
            $this->ensureOtpTable();
        } catch (\Throwable $e) {
            return $this->error('schema_not_ready', 'service temporarily unavailable');
        }

        try {
            $doctorIdForOtpStorage = $this->resolveDoctorIdForOtpStorage($doctorId);
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'doctor_id_legacy_alias_required') {
                return $this->error('invalid_params', 'doctor_id has no legacy alias mapping for otp storage', [
                    'fields' => ['doctor_id' => 'legacy_alias_required'],
                    'doctor_id' => $doctorId,
                ]);
            }
            return $this->error('db_error', 'database error');
        }

        $consultorioId = (string)$consultorioId;
        $slotCheck = $this->checkSlotAvailability(
            $doctorId,
            $consultorioId,
            (string)$validation['start_at'],
            (string)$validation['end_at']
        );

        if (($slotCheck['ok'] ?? false) !== true) {
            $meta = (array)($slotCheck['meta'] ?? []);
            $meta['consultorio_id_used'] = $consultorioId;
            return $this->error(
                (string)($slotCheck['error'] ?? 'slot_unavailable'),
                (string)($slotCheck['message'] ?? 'slot unavailable'),
                $meta
            );
        }

        $otp = $this->generateOtp();
        $otpHash = password_hash($otp, PASSWORD_DEFAULT);

        if (!is_string($otpHash) || $otpHash === '') {
            return $this->error('server_error', 'could not generate otp hash');
        }

        $requestId = $this->generateUuidV4();
        $createdAt = $this->now();
        $expiresAt = $createdAt->add(new DateInterval('PT' . self::OTP_TTL_MINUTES . 'M'));

        $phone = (string)$validation['patient_phone'];
        $email = (string)$validation['patient_email'];
        $channel = $phone !== '' ? 'sms' : 'email';
        $recipient = $phone !== '' ? $phone : $email;

        $metaJson = json_encode([
            'source' => 'public_agenda',
            'channel' => $channel,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        try {
            $sql = 'INSERT INTO ' . self::OTP_TABLE . '
                (id, doctor_id, consultorio_id, start_at, end_at, patient_name, patient_phone, patient_email, otp_hash, otp_last4, status, attempts, expires_at, created_at, verified_at, meta_json)
                VALUES
                (:id, :doctor_id, :consultorio_id, :start_at, :end_at, :patient_name, :patient_phone, :patient_email, :otp_hash, :otp_last4, :status, :attempts, :expires_at, :created_at, NULL, :meta_json)';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'id' => $requestId,
                'doctor_id' => $doctorIdForOtpStorage,
                'consultorio_id' => (int)$consultorioId,
                'start_at' => (string)$validation['start_at'],
                'end_at' => (string)$validation['end_at'],
                'patient_name' => (string)$validation['patient_name'],
                'patient_phone' => $phone === '' ? null : $phone,
                'patient_email' => $email === '' ? null : $email,
                'otp_hash' => $otpHash,
                'otp_last4' => substr($otp, -4),
                'status' => 'pending_verification',
                'attempts' => 0,
                'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
                'created_at' => $createdAt->format('Y-m-d H:i:s'),
                'meta_json' => $metaJson,
            ]);
        } catch (PDOException $e) {
            return $this->error('db_error', 'database error');
        } catch (\Throwable $e) {
            return $this->error('db_error', 'database error');
        }

        $sent = $this->otpSender->send($channel, $recipient, $otp, [
            'request_id' => $requestId,
            'doctor_id' => $doctorId,
            'consultorio_id' => $consultorioId,
        ]);

        if (!$sent) {
            $this->updateOtpStatus($requestId, 'failed');
            return $this->error('otp_send_failed', 'could not send otp', [
                'request_id' => $requestId,
                'consultorio_id_used' => $consultorioId,
            ]);
        }

        $meta = [
            'verification_required' => true,
            'consultorio_id_used' => $consultorioId,
        ];
        return $this->success([
            'request_id' => $requestId,
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
        ], $meta, 'verification required');
    }

    public function verify(array $payload = []): array
    {
        if ($this->dbError || !$this->pdo) {
            return $this->error('db_error', 'database error');
        }

        $requestId = trim((string)($payload['request_id'] ?? ''));
        $otp = trim((string)($payload['otp'] ?? ''));

        if ($requestId === '' || !preg_match('/^\d{6}$/', $otp)) {
            return $this->error('invalid_params', 'request_id and otp are required', [
                'fields' => [
                    'request_id' => $requestId === '' ? 'required' : 'ok',
                    'otp' => preg_match('/^\d{6}$/', $otp) ? 'ok' : 'invalid',
                ],
            ]);
        }

        try {
            $this->ensureOtpTable();
        } catch (\Throwable $e) {
            return $this->error('schema_not_ready', 'service temporarily unavailable');
        }

        $row = $this->findOtpRequest($requestId);
        if (!$row) {
            return $this->error('not_found', 'request not found');
        }

        $metaBase = [
            'request_id' => $requestId,
            'consultorio_id_used' => (string)$row['consultorio_id'],
        ];

        $status = (string)($row['status'] ?? '');

        if ($status === 'verified') {
            return $this->error('conflict', 'request already verified', $metaBase);
        }

        $now = $this->now();
        $expiresAt = $this->parseDateTime((string)$row['expires_at']);
        if (!$expiresAt || $expiresAt < $now || $status === 'expired') {
            $this->updateOtpStatus($requestId, 'expired');
            return $this->error('otp_expired', 'otp expired', $metaBase);
        }

        if (!password_verify($otp, (string)($row['otp_hash'] ?? ''))) {
            $attempts = ((int)($row['attempts'] ?? 0)) + 1;
            $nextStatus = $attempts >= self::OTP_MAX_ATTEMPTS ? 'failed' : 'pending_verification';
            $this->updateOtpAttempt($requestId, $attempts, $nextStatus);

            return $this->error('otp_invalid', 'otp invalid', array_merge($metaBase, [
                'attempts' => $attempts,
                'attempts_remaining' => max(0, self::OTP_MAX_ATTEMPTS - $attempts),
            ]));
        }

        if ($status === 'failed') {
            return $this->error('otp_invalid', 'otp invalid', array_merge($metaBase, [
                'attempts' => (int)($row['attempts'] ?? 0),
                'attempts_remaining' => 0,
            ]));
        }

        $createPayload = $this->buildAppointmentPayload($row);
        $writer = new AppointmentWriteController($this->pdo);
        $created = $writer->createFromPayload($createPayload);

        if (($created['ok'] ?? false) !== true) {
            return $this->error(
                (string)($created['error'] ?? 'db_error'),
                (string)($created['message'] ?? 'database error'),
                array_merge($metaBase, [
                    'source' => 'appointment_create',
                ])
            );
        }

        $appointmentId = (string)($created['data']['appointment_id'] ?? '');
        if ($appointmentId === '') {
            return $this->error('server_error', 'appointment id missing', $metaBase);
        }

        $this->markRequestVerified($requestId);

        return $this->success([
            'appointment_id' => $appointmentId,
            'status' => 'confirmed',
        ], array_merge($metaBase, [
            'confirmed' => true,
        ]), 'appointment confirmed');
    }

    public function reserve(array $payload = []): array
    {
        if ($this->dbError || !$this->pdo) {
            return $this->error('db_error', 'database error', ['route' => 'public_reserve']);
        }

        try {
            $this->ensureFlowTable();
            $this->expirePendingReservations();
        } catch (\Throwable $e) {
            return $this->error('schema_not_ready', 'service temporarily unavailable', ['route' => 'public_reserve']);
        }

        $validated = $this->validateReservePayload($payload);
        if (!empty($validated['errors'])) {
            return $this->error('invalid_params', 'invalid payload', [
                'route' => 'public_reserve',
                'fields' => $validated['errors'],
            ]);
        }

        $doctorId = $this->resolveCanonicalDoctorId((string)$validated['doctor_id']);
        if ($doctorId === '') {
            return $this->error('invalid_params', 'invalid payload', [
                'route' => 'public_reserve',
                'fields' => ['doctor_id' => 'required'],
            ]);
        }
        $validated['doctor_id'] = $doctorId;
        $consultorioId = $this->resolveConsultorioId(
            $doctorId,
            $payload['consultorio_id'] ?? null,
            (string)$validated['start_at'],
            (string)$validated['end_at']
        );
        if (!$this->isValidNumeric($consultorioId)) {
            return $this->error('invalid_params', 'consultorio_id must be numeric', [
                'route' => 'public_reserve',
                'doctor_id' => $doctorId,
                'consultorio_id_used' => null,
            ]);
        }

        $consultorioId = (string)$consultorioId;
        $slotCheck = $this->checkSlotAvailability(
            $doctorId,
            $consultorioId,
            (string)$validated['start_at'],
            (string)$validated['end_at']
        );

        if (($slotCheck['ok'] ?? false) !== true) {
            return $this->mapSlotErrorForReserve($slotCheck, $doctorId, $consultorioId, (string)$validated['start_at'], (string)$validated['end_at']);
        }

        $identityResolution = (new PublicBookingPatientIdentityResolver($this->pdo))->resolve(
            (string)$validated['doctor_id'],
            (array)$validated['patient']
        );
        $validated['patient_identity_resolution'] = [
            'status' => (string)$identityResolution['status'],
            'match_tier' => (string)$identityResolution['match_tier'],
        ];
        if (($identityResolution['status'] ?? '') === 'matched' && is_string($identityResolution['patient_id'] ?? null)) {
            $validated['patient_identity_resolution']['patient_id'] = $identityResolution['patient_id'];
        }

        $writer = new AppointmentWriteController($this->pdo);
        $createPayload = $this->buildReserveCreatePayload($validated, $consultorioId);
        $created = $writer->createFromPayload($createPayload);
        if (($created['ok'] ?? false) !== true) {
            return $this->mapWriterCreateErrorForReserve($created, $doctorId, $consultorioId, (string)$validated['start_at'], (string)$validated['end_at']);
        }

        $appointmentId = (string)($created['data']['appointment_id'] ?? '');
        if ($appointmentId === '') {
            return $this->error('db_error', 'database error', ['route' => 'public_reserve']);
        }

        $expiresAt = $this->now()->add(new DateInterval('PT' . self::OTP_TTL_MINUTES . 'M'));
        $cancelToken = bin2hex(random_bytes(16));
        $flowInsert = $this->insertReserveFlow($appointmentId, $doctorId, $consultorioId, $validated, $expiresAt, $cancelToken);
        if (($flowInsert['ok'] ?? false) !== true) {
            $this->tryCancelPendingAppointment($appointmentId);
            return $this->error('db_error', 'database error', ['route' => 'public_reserve']);
        }

        return $this->success([
            'appointment_id' => $appointmentId,
            'status' => 'pending_otp',
            'expires_in' => self::OTP_TTL_MINUTES * 60,
            'cancel_token' => $cancelToken,
        ], [
            'route' => 'public_reserve',
            'doctor_id' => $doctorId,
            'consultorio_id_used' => $consultorioId,
            'start_at' => (string)$validated['start_at'],
            'end_at' => (string)$validated['end_at'],
            'cancel_token_ready' => true,
        ]);
    }

    public function confirm(array $payload = []): array
    {
        if ($this->dbError || !$this->pdo) {
            return $this->error('db_error', 'database error', ['route' => 'public_confirm']);
        }

        $appointmentId = trim((string)($payload['appointment_id'] ?? ''));
        $otpId = trim((string)($payload['otp_id'] ?? ''));
        $code = trim((string)($payload['code'] ?? ''));
        $errors = [];
        if ($appointmentId === '') {
            $errors['appointment_id'] = 'required';
        }
        if (!$this->isValidNumeric($otpId)) {
            $errors['otp_id'] = 'required_numeric';
        }
        if (!preg_match('/^\d{6}$/', $code)) {
            $errors['code'] = 'must_be_6_digits';
        }
        if (!empty($errors)) {
            return $this->error('invalid_params', 'invalid payload', [
                'route' => 'public_confirm',
                'fields' => $errors,
            ]);
        }

        try {
            $this->ensureFlowTable();
            $this->expirePendingReservations();
        } catch (\Throwable $e) {
            return $this->error('schema_not_ready', 'service temporarily unavailable', ['route' => 'public_confirm']);
        }

        $flow = $this->findFlowByAppointmentId($appointmentId);
        if (!$flow) {
            return $this->error('not_found', 'appointment not found', [
                'route' => 'public_confirm',
                'appointment_id' => $appointmentId,
            ]);
        }

        if ((string)($flow['status'] ?? '') === 'confirmed') {
            return $this->success([
                'appointment_id' => $appointmentId,
                'status' => 'confirmed',
            ], [
                'route' => 'public_confirm',
                'idempotent' => true,
            ]);
        }

        $flowStatus = (string)($flow['status'] ?? '');
        if ($flowStatus === 'canceled' || $flowStatus === 'expired') {
            return $this->error('conflict', 'appointment not confirmable', [
                'route' => 'public_confirm',
                'appointment_id' => $appointmentId,
                'flow_status' => $flowStatus,
            ]);
        }

        $expiresAt = $this->parseDateTime((string)($flow['expires_at'] ?? ''));
        if (!$expiresAt || $expiresAt < $this->now()) {
            $this->markFlowExpired($appointmentId);
            $this->tryCancelPendingAppointment($appointmentId);
            return $this->error('otp_expired', 'otp expired', [
                'route' => 'public_confirm',
                'appointment_id' => $appointmentId,
            ]);
        }

        if ((int)($flow['otp_id'] ?? 0) !== (int)$otpId) {
            return $this->error('otp_mismatch', 'otp does not match appointment', [
                'route' => 'public_confirm',
                'appointment_id' => $appointmentId,
            ]);
        }

        $otpController = new PublicOtpController($this->pdo, $this->publicOtpProvider);
        $otpVerify = $otpController->verify([
            'otp_id' => (string)$otpId,
            'code' => $code,
        ]);
        if (($otpVerify['ok'] ?? false) !== true) {
            $meta = (array)($otpVerify['meta'] ?? []);
            $meta['route'] = 'public_confirm';
            $meta['appointment_id'] = $appointmentId;
            return $this->error(
                (string)($otpVerify['error'] ?? 'invalid_code'),
                (string)($otpVerify['message'] ?? 'invalid code'),
                $meta
            );
        }

        $otpRepository = new PublicOtpRepository($this->pdo);
        $otpRow = $otpRepository->findOtpById((int)$otpId);
        if (!$otpRow) {
            return $this->error('not_found', 'otp not found', [
                'route' => 'public_confirm',
                'appointment_id' => $appointmentId,
                'otp_id' => (int)$otpId,
            ]);
        }

        $otpDoctorCanonical = $this->resolveCanonicalDoctorId((string)($otpRow['doctor_id'] ?? ''));
        $flowDoctorCanonical = $this->resolveCanonicalDoctorId((string)($flow['doctor_id'] ?? ''));
        if ($otpDoctorCanonical === '' || $flowDoctorCanonical === '' || $otpDoctorCanonical !== $flowDoctorCanonical) {
            return $this->error('otp_mismatch', 'otp does not match appointment', [
                'route' => 'public_confirm',
                'appointment_id' => $appointmentId,
                'otp_id' => (int)$otpId,
            ]);
        }

        $statusUpdate = $this->updateAppointmentStatus($appointmentId, 'confirmed');
        if (($statusUpdate['ok'] ?? false) !== true) {
            return $this->error(
                (string)($statusUpdate['error'] ?? 'db_error'),
                (string)($statusUpdate['message'] ?? 'database error'),
                array_merge(['route' => 'public_confirm'], (array)($statusUpdate['meta'] ?? []))
            );
        }

$this->markFlowConfirmed(
    $appointmentId,
    (int)$otpId,
    (string)($otpRow['contact_type'] ?? ''),
    '' // otp_external_id no existe en agenda_public_otps (se guarda NULL)
);

return $this->success([
    'appointment_id' => $appointmentId,
    'status' => 'confirmed',
], [
    'route' => 'public_confirm',
]);
}

public function cancel(array $payload = []): array
{
    if ($this->dbError || !$this->pdo) {
        return $this->error('db_error', 'database error', ['route' => 'public_cancel']);
    }

    $cancelToken = trim((string)($payload['cancel_token'] ?? ''));
    $reason = trim((string)($payload['reason'] ?? ''));

    $fields = [];
    if ($cancelToken === '') {
        $fields['cancel_token'] = 'required';
    }
    if ($reason !== '' && strlen($reason) > 280) {
        $fields['reason'] = 'max_280';
    }
    if (!empty($fields)) {
        return $this->error('validation_error', 'invalid payload', [
            'route' => 'public_cancel',
            'fields' => $fields,
        ]);
    }

        try {
            $this->ensureFlowTable();
        } catch (\Throwable $e) {
            return $this->error('schema_not_ready', 'service temporarily unavailable', ['route' => 'public_cancel']);
        }

        [$appointmentsTable, $appointmentPk] = $this->getAppointmentsTableAndPk();

        try {
            $this->pdo->beginTransaction();

            $flow = $this->findFlowByCancelTokenForUpdate($cancelToken);
            if (!$flow) {
                $this->pdo->rollBack();
                return $this->error('invalid_token', 'invalid token', ['route' => 'public_cancel']);
            }

            $appointmentId = (string)($flow['appointment_id'] ?? '');
            if ($appointmentId === '') {
                $this->pdo->rollBack();
                return $this->error('invalid_token', 'invalid token', ['route' => 'public_cancel']);
            }

            $appointmentStmt = $this->pdo->prepare(
                "SELECT * FROM {$appointmentsTable} WHERE {$appointmentPk} = :appointment_id LIMIT 1 FOR UPDATE"
            );
            $appointmentStmt->execute(['appointment_id' => $appointmentId]);
            $appointment = $appointmentStmt->fetch(PDO::FETCH_ASSOC);

            if (!is_array($appointment)) {
                $this->pdo->rollBack();
                return $this->error('invalid_token', 'invalid token', ['route' => 'public_cancel']);
            }

            $currentStatus = strtolower(trim((string)($appointment['status'] ?? '')));
            if (in_array($currentStatus, ['canceled', 'cancelled'], true)) {
                $this->updateFlowCancellationAudit($flow, $reason, 'already_canceled');
                $this->pdo->commit();
                return $this->success([
                    'appointment_id' => $appointmentId,
                    'status' => 'canceled',
                ], [
                    'route' => 'public_cancel',
                    'released_slot' => true,
                    'idempotent' => true,
                ], 'already_canceled');
            }

            if (!in_array($currentStatus, ['pending_otp', 'confirmed'], true)) {
                $this->pdo->rollBack();
                return $this->error('not_cancelable', 'not cancelable', [
                    'route' => 'public_cancel',
                    'appointment_id' => $appointmentId,
                    'status' => $currentStatus,
                ]);
            }

            $cancelStmt = $this->pdo->prepare(
                "UPDATE {$appointmentsTable} SET status = 'canceled' WHERE {$appointmentPk} = :appointment_id"
            );
            $cancelStmt->execute(['appointment_id' => $appointmentId]);

            $this->updateFlowCancellationAudit($flow, $reason, 'canceled');

            $this->pdo->commit();
            return $this->success([
                'appointment_id' => $appointmentId,
                'status' => 'canceled',
            ], [
                'route' => 'public_cancel',
                'released_slot' => true,
            ], 'canceled');
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return $this->error('db_error', 'database error', ['route' => 'public_cancel']);
        }
    }

    public function expireReservations(array $payload = []): array
    {
        if ($this->dbError || !$this->pdo) {
            return $this->error('db_error', 'database error', ['route' => 'public_expire']);
        }

        $limitRaw = $payload['limit'] ?? 50;
        $limit = is_numeric($limitRaw) ? (int)$limitRaw : 50;
        if ($limit <= 0) {
            $limit = 50;
        }
        if ($limit > 200) {
            $limit = 200;
        }

        $dryRun = $this->normalizeBooleanInput($payload['dry_run'] ?? false) === true;
        $force = $this->normalizeBooleanInput($payload['force'] ?? false) === true;
        $forceAppointmentId = trim((string)($payload['appointment_id'] ?? ''));

        try {
            $this->ensureFlowTable();
        } catch (\Throwable $e) {
            return $this->error('schema_not_ready', 'service temporarily unavailable', ['route' => 'public_expire']);
        }

        [$appointmentsTable, $appointmentPk] = $this->getAppointmentsTableAndPk();
        $now = $this->now()->format('Y-m-d H:i:s');
        $flowsExpired = 0;
        $appointmentsCanceled = 0;

        try {
            $this->pdo->beginTransaction();

            if ($force && $this->isQaDebugEnabled() && $forceAppointmentId !== '') {
                $forceStmt = $this->pdo->prepare(
                    'UPDATE ' . self::FLOW_TABLE . '
                     SET expires_at = :forced_expiry
                     WHERE appointment_id = :appointment_id'
                );
                $forceStmt->execute([
                    'forced_expiry' => $this->now()->sub(new DateInterval('PT1M'))->format('Y-m-d H:i:s'),
                    'appointment_id' => $forceAppointmentId,
                ]);
            }

            $flowsStmt = $this->pdo->prepare(
                'SELECT * FROM ' . self::FLOW_TABLE . '
                 WHERE expires_at IS NOT NULL
                   AND expires_at <= :now
                   AND status = "pending_otp"
                 ORDER BY flow_id ASC
                 LIMIT ' . $limit . '
                 FOR UPDATE'
            );
            $flowsStmt->execute(['now' => $now]);
            $flows = $flowsStmt->fetchAll(PDO::FETCH_ASSOC);

            if (is_array($flows)) {
                foreach ($flows as $flow) {
                    if (!is_array($flow)) {
                        continue;
                    }

                    $appointmentId = trim((string)($flow['appointment_id'] ?? ''));
                    if ($appointmentId === '') {
                        if (!$dryRun) {
                            $this->updateFlowExpirationAudit((int)($flow['flow_id'] ?? 0), $flow, $now);
                        }
                        $flowsExpired += 1;
                        continue;
                    }

                    $apptStmt = $this->pdo->prepare(
                        "SELECT * FROM {$appointmentsTable} WHERE {$appointmentPk} = :appointment_id LIMIT 1 FOR UPDATE"
                    );
                    $apptStmt->execute(['appointment_id' => $appointmentId]);
                    $appointment = $apptStmt->fetch(PDO::FETCH_ASSOC);

                    if (is_array($appointment)) {
                        $status = strtolower(trim((string)($appointment['status'] ?? '')));
                        if ($status === 'pending_otp') {
                            if (!$dryRun) {
                                $cancelStmt = $this->pdo->prepare(
                                    "UPDATE {$appointmentsTable}
                                     SET status = 'canceled'
                                     WHERE {$appointmentPk} = :appointment_id
                                       AND status = 'pending_otp'"
                                );
                                $cancelStmt->execute(['appointment_id' => $appointmentId]);
                            }
                            $appointmentsCanceled += 1;
                        }
                    }

                    if (!$dryRun) {
                        $this->updateFlowExpirationAudit((int)($flow['flow_id'] ?? 0), $flow, $now);
                    }
                    $flowsExpired += 1;
                }
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return $this->error('db_error', 'database error', ['route' => 'public_expire']);
        }

        return $this->success([
            'flows_expired' => $flowsExpired,
            'appointments_canceled' => $appointmentsCanceled,
        ], [
            'route' => 'public_expire',
            'limit_used' => $limit,
            'dry_run' => $dryRun,
        ], 'expire completed');
    }

    private function validateReservePayload(array $payload): array
    {
        $errors = [];

        $doctorId = trim((string)($payload['doctor_id'] ?? ''));
        if ($doctorId === '') {
            $errors['doctor_id'] = 'required';
        }

        $startAt = $this->normalizeDateTime((string)($payload['start_at'] ?? ''));
        $endAt = $this->normalizeDateTime((string)($payload['end_at'] ?? ''));
        if ($startAt === null) {
            $errors['start_at'] = 'invalid_datetime';
        }
        if ($endAt === null) {
            $errors['end_at'] = 'invalid_datetime';
        }
        if ($startAt !== null && $endAt !== null) {
            $start = $this->parseDateTime($startAt);
            $end = $this->parseDateTime($endAt);
            if (!$start || !$end || $start >= $end) {
                $errors['time_range'] = 'invalid';
            } elseif ($start->format('Y-m-d') !== $end->format('Y-m-d')) {
                $errors['time_range'] = 'same_day_required';
            }
        }

        $visitKind = trim((string)($payload['visit_kind'] ?? ''));
        if (!in_array($visitKind, ['presencial', 'video'], true)) {
            $errors['visit_kind'] = 'must_be_presencial_or_video';
        }

        $patientType = trim((string)($payload['patient_type'] ?? ''));
        if (!in_array($patientType, ['first_time', 'follow_up'], true)) {
            $errors['patient_type'] = 'must_be_first_time_or_follow_up';
        }

        if (!array_key_exists('booker_is_patient', $payload)) {
            $errors['booker_is_patient'] = 'required_boolean';
        }
        $bookerIsPatient = $this->normalizeBooleanInput($payload['booker_is_patient'] ?? null);
        if ($bookerIsPatient === null) {
            $errors['booker_is_patient'] = 'required_boolean';
        }

        $paymentMode = trim((string)($payload['payment_mode'] ?? 'none'));
        if (!in_array($paymentMode, ['none', 'future_platform_charge', 'future_doctor_charge'], true)) {
            $errors['payment_mode'] = 'invalid';
        }

        $patient = is_array($payload['patient'] ?? null) ? $payload['patient'] : [];
        $patientName = trim((string)($patient['name'] ?? ''));
        $patientPhone = trim((string)($patient['phone'] ?? ''));
        $patientEmail = trim((string)($patient['email'] ?? ''));
        $patientDob = trim((string)($patient['dob'] ?? ''));
        $patientGender = trim((string)($patient['gender'] ?? ''));
        $patientReason = trim((string)($patient['reason'] ?? ''));

        if ($patientName === '') {
            $errors['patient.name'] = 'required';
        }
        if ($patientPhone === '') {
            $errors['patient.phone'] = 'required';
        }
        if ($patientEmail === '') {
            $errors['patient.email'] = 'required';
        } elseif (filter_var($patientEmail, FILTER_VALIDATE_EMAIL) === false) {
            $errors['patient.email'] = 'invalid_email';
        }
        if ($patientDob === '' || !$this->isValidDateYmd($patientDob)) {
            $errors['patient.dob'] = 'invalid_ymd';
        }
        if (!in_array($patientGender, ['M', 'F', 'No especifica'], true)) {
            $errors['patient.gender'] = 'must_be_M_F_or_No_especifica';
        }
        if ($patientReason !== '' && strlen($patientReason) > 1000) {
            $errors['patient.reason'] = 'too_long';
        }

        $booker = is_array($payload['booker'] ?? null) ? $payload['booker'] : [];
        if ($bookerIsPatient === false) {
            if (trim((string)($booker['name'] ?? '')) === '') {
                $errors['booker.name'] = 'required';
            }
            if (trim((string)($booker['phone'] ?? '')) === '') {
                $errors['booker.phone'] = 'required';
            }
            $bookerEmail = trim((string)($booker['email'] ?? ''));
            if ($bookerEmail === '') {
                $errors['booker.email'] = 'required';
            } elseif (filter_var($bookerEmail, FILTER_VALIDATE_EMAIL) === false) {
                $errors['booker.email'] = 'invalid_email';
            }
            if (trim((string)($booker['relationship'] ?? '')) === '') {
                $errors['booker.relationship'] = 'required';
            }
        }

        $otp = is_array($payload['otp'] ?? null) ? $payload['otp'] : [];
        if (isset($otp['otp_id']) && !$this->isValidNumeric((string)$otp['otp_id'])) {
            $errors['otp.otp_id'] = 'must_be_numeric';
        }
        if (isset($otp['channel']) && !in_array((string)$otp['channel'], ['sms', 'email'], true)) {
            $errors['otp.channel'] = 'must_be_sms_or_email';
        }

        return [
            'errors' => $errors,
            'doctor_id' => $doctorId,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'visit_kind' => $visitKind,
            'patient_type' => $patientType,
            'booker_is_patient' => $bookerIsPatient,
            'booker' => $booker,
            'patient' => [
                'name' => $patientName,
                'phone' => $patientPhone,
                'email' => $patientEmail,
                'dob' => $patientDob,
                'gender' => $patientGender,
                'reason' => $patientReason,
            ],
            'extras' => is_array($payload['extras'] ?? null) ? $payload['extras'] : [],
            'otp' => $otp,
            'payment_mode' => $paymentMode,
        ];
    }

    private function buildReserveCreatePayload(array $validated, string $consultorioId): array
    {
        $patient = is_array($validated['patient'] ?? null) ? $validated['patient'] : [];
        $contacts = [];
        if (trim((string)($patient['phone'] ?? '')) !== '') {
            $contacts[] = [
                'type' => 'phone',
                'value' => trim((string)$patient['phone']),
            ];
        }
        if (trim((string)($patient['email'] ?? '')) !== '') {
            $contacts[] = [
                'type' => 'email',
                'value' => trim((string)$patient['email']),
            ];
        }

        $payload = [
            'doctor_id' => (string)$validated['doctor_id'],
            'consultorio_id' => $consultorioId,
            'start_at' => (string)$validated['start_at'],
            'end_at' => (string)$validated['end_at'],
            'modality' => (string)$validated['visit_kind'],
            'status' => 'pending_otp',
            'channel_origin' => 'public_agenda',
            'created_by_role' => 'patient',
            'created_by_id' => 'public_reserve',
            'patient' => [
                'display_name' => trim((string)($patient['name'] ?? '')),
                'doctor_id' => (string)$validated['doctor_id'],
                'birthdate' => (string)($patient['dob'] ?? ''),
                'sex' => $this->normalizeGender((string)($patient['gender'] ?? '')),
                'contacts' => $contacts,
            ],
        ];

        $identityResolution = is_array($validated['patient_identity_resolution'] ?? null)
            ? $validated['patient_identity_resolution']
            : [];
        if (($identityResolution['status'] ?? '') === 'matched' && is_string($identityResolution['patient_id'] ?? null) && $identityResolution['patient_id'] !== '') {
            unset($payload['patient']);
            $payload['patient_id'] = $identityResolution['patient_id'];
        }

        return $payload;
    }

    private function mapSlotErrorForReserve(array $slotCheck, string $doctorId, string $consultorioId, string $startAt, string $endAt): array
    {
        $error = (string)($slotCheck['error'] ?? 'slot_unavailable');
        if (in_array($error, ['slot_unavailable', 'collision'], true)) {
            return $this->error('slot_taken', 'El horario ya fue reservado, elige otro', [
                'route' => 'public_reserve',
                'start_at' => $startAt,
                'end_at' => $endAt,
                'doctor_id' => $doctorId,
                'consultorio_id_used' => $consultorioId,
            ]);
        }

        return $this->error(
            $error,
            (string)($slotCheck['message'] ?? 'slot unavailable'),
            array_merge([
                'route' => 'public_reserve',
                'doctor_id' => $doctorId,
                'consultorio_id_used' => $consultorioId,
            ], (array)($slotCheck['meta'] ?? []))
        );
    }

    private function mapWriterCreateErrorForReserve(array $created, string $doctorId, string $consultorioId, string $startAt, string $endAt): array
    {
        $error = (string)($created['error'] ?? 'db_error');
        $message = strtolower((string)($created['message'] ?? ''));
        if ($error === 'collision' || $error === 'slot_unavailable' || str_contains($message, 'collision') || str_contains($message, 'duplicate')) {
            return $this->error('slot_taken', 'El horario ya fue reservado, elige otro', [
                'route' => 'public_reserve',
                'start_at' => $startAt,
                'end_at' => $endAt,
                'doctor_id' => $doctorId,
                'consultorio_id_used' => $consultorioId,
            ]);
        }

        return $this->error(
            $error,
            (string)($created['message'] ?? 'database error'),
            array_merge([
                'route' => 'public_reserve',
                'doctor_id' => $doctorId,
                'consultorio_id_used' => $consultorioId,
            ], (array)($created['meta'] ?? []))
        );
    }

    private function ensureFlowTable(): void
    {
        if (!$this->ensureSchemaTableReady(self::FLOW_TABLE)) {
            throw new RuntimeException('schema_not_ready');
        }
    }

    private function expirePendingReservations(): void
    {
        if (!$this->pdo) {
            return;
        }

        $now = $this->now()->format('Y-m-d H:i:s');
        $cutoff = $this->now()->sub(new DateInterval('PT' . self::OTP_TTL_MINUTES . 'M'))->format('Y-m-d H:i:s');

        try {
            $stmt = $this->pdo->prepare(
                'UPDATE ' . self::FLOW_TABLE . '
                 SET status = "expired"
                 WHERE status = "pending_otp" AND expires_at < :now'
            );
            $stmt->execute(['now' => $now]);
        } catch (\Throwable $e) {
            return;
        }

        [$appointmentsTable] = $this->getAppointmentsTableAndPk();
        try {
            $stmt = $this->pdo->prepare(
                "UPDATE {$appointmentsTable}
                 SET status = \"canceled\"
                 WHERE status = \"pending_otp\" AND channel_origin = \"public_agenda\" AND created_at < :cutoff"
            );
            $stmt->execute(['cutoff' => $cutoff]);
        } catch (\Throwable $e) {
            // noop
        }
    }

    private function insertReserveFlow(
        string $appointmentId,
        string $doctorId,
        string $consultorioId,
        array $validated,
        DateTimeImmutable $expiresAt,
        string $cancelToken
    ): array {
        if (!$this->pdo) {
            return ['ok' => false];
        }

        $payloadJson = json_encode($validated, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($payloadJson === false) {
            $payloadJson = '{}';
        }

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO ' . self::FLOW_TABLE . '
                (appointment_id, doctor_id, consultorio_id, start_at, end_at, status, otp_id, otp_channel, otp_external_id, otp_verified_at, expires_at, cancel_token, payload_json, created_at, updated_at)
                VALUES
                (:appointment_id, :doctor_id, :consultorio_id, :start_at, :end_at, "pending_otp", :otp_id, :otp_channel, NULL, NULL, :expires_at, :cancel_token, :payload_json, :created_at, :updated_at)'
            );
            $stmt->execute([
                'appointment_id' => $appointmentId,
                'doctor_id' => $doctorId,
                'consultorio_id' => $consultorioId,
                'start_at' => (string)$validated['start_at'],
                'end_at' => (string)$validated['end_at'],
                'otp_id' => isset($validated['otp']['otp_id']) ? (int)$validated['otp']['otp_id'] : null,
                'otp_channel' => isset($validated['otp']['channel']) ? (string)$validated['otp']['channel'] : null,
                'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
                'cancel_token' => $cancelToken,
                'payload_json' => $payloadJson,
                'created_at' => $this->now()->format('Y-m-d H:i:s'),
                'updated_at' => $this->now()->format('Y-m-d H:i:s'),
            ]);
            return ['ok' => true];
        } catch (\Throwable $e) {
            return ['ok' => false];
        }
    }

    private function findFlowByAppointmentId(string $appointmentId): ?array
    {
        if (!$this->pdo) {
            return null;
        }
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM ' . self::FLOW_TABLE . ' WHERE appointment_id = :appointment_id LIMIT 1');
            $stmt->execute(['appointment_id' => $appointmentId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? $row : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function findFlowByCancelTokenForUpdate(string $cancelToken): ?array
    {
        if (!$this->pdo) {
            return null;
        }
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM ' . self::FLOW_TABLE . ' WHERE cancel_token = :cancel_token LIMIT 1 FOR UPDATE');
            $stmt->execute(['cancel_token' => $cancelToken]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? $row : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function markFlowExpired(string $appointmentId): void
    {
        if (!$this->pdo) {
            return;
        }
        try {
            $stmt = $this->pdo->prepare('UPDATE ' . self::FLOW_TABLE . ' SET status = "expired" WHERE appointment_id = :appointment_id');
            $stmt->execute(['appointment_id' => $appointmentId]);
        } catch (\Throwable $e) {
            // noop
        }
    }

    private function markFlowConfirmed(string $appointmentId, int $otpId, string $otpChannel, string $otpExternalId): void
    {
        if (!$this->pdo) {
            return;
        }
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE ' . self::FLOW_TABLE . '
                 SET status = "confirmed", otp_id = :otp_id, otp_channel = :otp_channel, otp_external_id = :otp_external_id, otp_verified_at = :otp_verified_at
                 WHERE appointment_id = :appointment_id'
            );
            $stmt->execute([
                'otp_id' => $otpId,
                'otp_channel' => $otpChannel !== '' ? $otpChannel : null,
                'otp_external_id' => $otpExternalId !== '' ? $otpExternalId : null,
                'otp_verified_at' => $this->now()->format('Y-m-d H:i:s'),
                'appointment_id' => $appointmentId,
            ]);
        } catch (\Throwable $e) {
            // noop
        }
    }

private function updateFlowCancellationAudit(array $flow, string $reason, string $flowStatus): void
{
    if (!$this->pdo) {
        return;
    }

    $currentPayload = [];
    $rawPayload = $flow['payload_json'] ?? null;
    if (is_string($rawPayload) && trim($rawPayload) !== '') {
        $decoded = json_decode($rawPayload, true);
        if (is_array($decoded)) {
            $currentPayload = $decoded;
        }
    }

    $currentPayload['cancellation'] = [
        'reason' => $reason,
        'status' => $flowStatus,
        'canceled_at' => $this->now()->format('Y-m-d H:i:s'),
    ];

    $payloadJson = json_encode($currentPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($payloadJson === false) {
        $payloadJson = '{}';
    }

    try {
        $stmt = $this->pdo->prepare(
            'UPDATE ' . self::FLOW_TABLE . '
             SET status = :status, payload_json = :payload_json, updated_at = :updated_at
             WHERE flow_id = :flow_id'
        );
        $stmt->execute([
            'status' => $flowStatus,
            'payload_json' => $payloadJson,
            'updated_at' => $this->now()->format('Y-m-d H:i:s'),
            'flow_id' => (int)($flow['flow_id'] ?? 0),
        ]);
    } catch (\Throwable $e) {
        // noop
    }
}

private function updateFlowConfirmationAudit(array $flow, array $otpMeta = []): void
{
    if (!$this->pdo) {
        return;
    }

    $currentPayload = [];
    $rawPayload = $flow['payload_json'] ?? null;
    if (is_string($rawPayload) && trim($rawPayload) !== '') {
        $decoded = json_decode($rawPayload, true);
        if (is_array($decoded)) {
            $currentPayload = $decoded;
        }
    }

    $currentPayload['confirmation'] = [
        'confirmed_at' => $this->now()->format('Y-m-d H:i:s'),
        'otp_meta' => $otpMeta,
    ];

    $payloadJson = json_encode($currentPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($payloadJson === false) {
        $payloadJson = '{}';
    }

    try {
        $stmt = $this->pdo->prepare(
            'UPDATE ' . self::FLOW_TABLE . '
             SET status = :status, payload_json = :payload_json, updated_at = :updated_at
             WHERE flow_id = :flow_id'
        );
        $stmt->execute([
            'status' => 'confirmed',
            'payload_json' => $payloadJson,
            'updated_at' => $this->now()->format('Y-m-d H:i:s'),
            'flow_id' => (int)($flow['flow_id'] ?? 0),
        ]);
    } catch (\Throwable $e) {
        // noop
    }
}

    private function updateFlowExpirationAudit(int $flowId, array $flow, string $expiredAt): void
    {
        if (!$this->pdo || $flowId <= 0) {
            return;
        }

        $currentPayload = [];
        $rawPayload = $flow['payload_json'] ?? null;
        if (is_string($rawPayload) && trim($rawPayload) !== '') {
            $decoded = json_decode($rawPayload, true);
            if (is_array($decoded)) {
                $currentPayload = $decoded;
            }
        }

        $currentPayload['expiration'] = [
            'expired_at' => $expiredAt,
            'reason' => 'ttl_reached',
        ];

        $payloadJson = json_encode($currentPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($payloadJson === false) {
            $payloadJson = '{}';
        }

        try {
            $stmt = $this->pdo->prepare(
                'UPDATE ' . self::FLOW_TABLE . '
                 SET status = "expired", payload_json = :payload_json, updated_at = :updated_at
                 WHERE flow_id = :flow_id'
            );
            $stmt->execute([
                'payload_json' => $payloadJson,
                'updated_at' => $expiredAt,
                'flow_id' => $flowId,
            ]);
        } catch (\Throwable $e) {
            // noop
        }
    }

    private function updateAppointmentStatus(string $appointmentId, string $toStatus): array
    {
        if (!$this->pdo) {
            return ['ok' => false, 'error' => 'db_error', 'message' => 'database error', 'meta' => []];
        }

        [$table, $pk] = $this->getAppointmentsTableAndPk();

        try {
            $stmt = $this->pdo->prepare(
                "UPDATE {$table} SET status = :to_status WHERE {$pk} = :appointment_id AND status IN ('pending_otp', 'confirmed')"
            );
            $stmt->execute([
                'to_status' => $toStatus,
                'appointment_id' => $appointmentId,
            ]);

            if ($stmt->rowCount() > 0) {
                return ['ok' => true];
            }

            $find = $this->pdo->prepare("SELECT status FROM {$table} WHERE {$pk} = :appointment_id LIMIT 1");
            $find->execute(['appointment_id' => $appointmentId]);
            $currentRaw = $find->fetchColumn();
            $current = is_string($currentRaw) ? $currentRaw : '';
            if ($current === 'confirmed' && $toStatus === 'confirmed') {
                return ['ok' => true];
            }
            if ($current === '') {
                return ['ok' => false, 'error' => 'not_found', 'message' => 'appointment not found', 'meta' => ['appointment_id' => $appointmentId]];
            }
            return ['ok' => false, 'error' => 'conflict', 'message' => 'appointment status conflict', 'meta' => ['status' => $current]];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'db_error', 'message' => 'database error', 'meta' => []];
        }
    }

    private function tryCancelPendingAppointment(string $appointmentId): void
    {
        if (!$this->pdo) {
            return;
        }
        [$table, $pk] = $this->getAppointmentsTableAndPk();
        try {
            $stmt = $this->pdo->prepare(
                "UPDATE {$table}
                 SET status = \"canceled\"
                 WHERE {$pk} = :appointment_id AND status = \"pending_otp\""
            );
            $stmt->execute(['appointment_id' => $appointmentId]);
        } catch (\Throwable $e) {
            // noop
        }
    }

    private function getAppointmentsTableAndPk(): array
    {
        $table = 'agenda_appointments';
        $pk = 'appointment_id';
        try {
            $config = require __DIR__ . '/../config/agenda.php';
            if (is_array($config) && trim((string)($config['appointments_table'] ?? '')) !== '') {
                $table = trim((string)$config['appointments_table']);
            }
            if (is_array($config) && trim((string)($config['appointment_pk'] ?? '')) !== '') {
                $pk = trim((string)$config['appointment_pk']);
            }
        } catch (\Throwable $e) {
            // defaults
        }
        return [$table, $pk];
    }

    private function normalizeBooleanInput($value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            if ($value === 1) {
                return true;
            }
            if ($value === 0) {
                return false;
            }
        }
        if (is_string($value)) {
            $v = strtolower(trim($value));
            if (in_array($v, ['1', 'true', 'yes', 'si'], true)) {
                return true;
            }
            if (in_array($v, ['0', 'false', 'no'], true)) {
                return false;
            }
        }
        return null;
    }

    private function isValidDateYmd(string $value): bool
    {
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $value, new DateTimeZone(self::TIMEZONE));
        return $dt instanceof DateTimeImmutable && $dt->format('Y-m-d') === $value;
    }

    private function normalizeGender(string $value): ?string
    {
        if ($value === 'M') {
            return 'M';
        }
        if ($value === 'F') {
            return 'F';
        }
        return null;
    }

    private function validateRequestPayload(array $payload): array
    {
        $errors = [];

        $doctorId = trim((string)($payload['doctor_id'] ?? ''));
        if ($doctorId === '') {
            $errors['doctor_id'] = 'required';
        }

        $patientName = trim((string)($payload['patient_name'] ?? ''));
        if ($patientName === '') {
            $errors['patient_name'] = 'required';
        } elseif (strlen($patientName) > 160) {
            $errors['patient_name'] = 'too_long';
        }

        $phone = trim((string)($payload['patient_phone'] ?? ''));
        $email = trim((string)($payload['patient_email'] ?? ''));

        if ($phone === '' && $email === '') {
            $errors['patient_contact'] = 'phone_or_email_required';
        }

        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['patient_email'] = 'invalid';
        }

        $startAt = $this->normalizeDateTime((string)($payload['start_at'] ?? ''));
        $endAt = $this->normalizeDateTime((string)($payload['end_at'] ?? ''));

        if ($startAt === null) {
            $errors['start_at'] = 'invalid_datetime';
        }
        if ($endAt === null) {
            $errors['end_at'] = 'invalid_datetime';
        }

        if ($startAt !== null && $endAt !== null) {
            $start = $this->parseDateTime($startAt);
            $end = $this->parseDateTime($endAt);
            if (!$start || !$end || $start >= $end) {
                $errors['time_range'] = 'invalid';
            } elseif ($start->format('Y-m-d') !== $end->format('Y-m-d')) {
                $errors['time_range'] = 'same_day_required';
            }
        }

        return [
            'errors' => $errors,
            'doctor_id' => $doctorId,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'patient_name' => $patientName,
            'patient_phone' => $phone,
            'patient_email' => $email,
        ];
    }

    private function checkSlotAvailability(string $doctorId, string $consultorioId, string $startAt, string $endAt): array
    {
        $start = $this->parseDateTime($startAt);
        $end = $this->parseDateTime($endAt);

        if (!$start || !$end || $start >= $end) {
            return [
                'ok' => false,
                'error' => 'invalid_params',
                'message' => 'invalid time range',
                'meta' => [
                    'doctor_id' => $doctorId,
                    'consultorio_id' => $consultorioId,
                ],
            ];
        }

        $durationSeconds = $end->getTimestamp() - $start->getTimestamp();
        if ($durationSeconds <= 0 || ($durationSeconds % 60) !== 0) {
            return [
                'ok' => false,
                'error' => 'invalid_params',
                'message' => 'invalid slot duration',
                'meta' => [
                    'doctor_id' => $doctorId,
                    'consultorio_id' => $consultorioId,
                ],
            ];
        }

        $slotMinutes = (int)($durationSeconds / 60);
        if ($slotMinutes < 5 || $slotMinutes > 720) {
            return [
                'ok' => false,
                'error' => 'invalid_params',
                'message' => 'slot_minutes must be between 5 and 720',
                'meta' => [
                    'doctor_id' => $doctorId,
                    'consultorio_id' => $consultorioId,
                ],
            ];
        }

        $availability = new AvailabilityController($this->pdo);
        $dayResponse = $availability->index([
            'doctor_id' => $doctorId,
            'consultorio_id' => $consultorioId,
            'date' => $start->format('Y-m-d'),
            'slot_minutes' => $slotMinutes,
        ]);

        if (($dayResponse['ok'] ?? false) !== true) {
            return [
                'ok' => false,
                'error' => (string)($dayResponse['error'] ?? 'slot_unavailable'),
                'message' => (string)($dayResponse['message'] ?? 'slot unavailable'),
                'meta' => (array)($dayResponse['meta'] ?? []),
            ];
        }

        $slots = $dayResponse['data']['slots'] ?? [];
        if (is_array($slots)) {
            foreach ($slots as $slot) {
                if (!is_array($slot)) {
                    continue;
                }
                if ((string)($slot['start_at'] ?? '') === $startAt && (string)($slot['end_at'] ?? '') === $endAt) {
                    return [
                        'ok' => true,
                        'meta' => [
                            'doctor_id' => $doctorId,
                            'consultorio_id' => $consultorioId,
                        ],
                    ];
                }
            }
        }

        return [
            'ok' => false,
            'error' => 'slot_unavailable',
            'message' => 'slot unavailable',
            'meta' => [
                'doctor_id' => $doctorId,
                'consultorio_id' => $consultorioId,
                'start_at' => $startAt,
                'end_at' => $endAt,
            ],
        ];
    }

    private function buildAppointmentPayload(array $row): array
    {
        $payload = [
            'doctor_id' => (string)$row['doctor_id'],
            'consultorio_id' => (string)$row['consultorio_id'],
            'start_at' => (string)$row['start_at'],
            'end_at' => (string)$row['end_at'],
            'modality' => 'presencial',
            'status' => 'confirmed',
            'channel_origin' => 'public_agenda',
            'created_by_role' => 'patient',
            'created_by_id' => (string)$row['id'],
        ];

        $patientName = trim((string)($row['patient_name'] ?? ''));
        if ($patientName !== '') {
            $contacts = [];
            $phone = trim((string)($row['patient_phone'] ?? ''));
            $email = trim((string)($row['patient_email'] ?? ''));
            if ($phone !== '') {
                $contacts[] = ['type' => 'phone', 'value' => $phone];
            }
            if ($email !== '') {
                $contacts[] = ['type' => 'email', 'value' => $email];
            }

            $payload['patient'] = [
                'display_name' => $patientName,
                'doctor_id' => (string)$row['doctor_id'],
                'contacts' => $contacts,
            ];
        }

        return $payload;
    }

    private function findOtpRequest(string $requestId): ?array
    {
        if (!$this->pdo) {
            return null;
        }

        try {
            $stmt = $this->pdo->prepare('SELECT * FROM ' . self::OTP_TABLE . ' WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $requestId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? $row : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function markRequestVerified(string $requestId): void
    {
        if (!$this->pdo) {
            return;
        }
        try {
            $stmt = $this->pdo->prepare('UPDATE ' . self::OTP_TABLE . ' SET status = :status, verified_at = :verified_at WHERE id = :id');
            $stmt->execute([
                'status' => 'verified',
                'verified_at' => $this->now()->format('Y-m-d H:i:s'),
                'id' => $requestId,
            ]);
        } catch (\Throwable $e) {
            // noop
        }
    }

    private function updateOtpStatus(string $requestId, string $status): void
    {
        if (!$this->pdo) {
            return;
        }
        try {
            $stmt = $this->pdo->prepare('UPDATE ' . self::OTP_TABLE . ' SET status = :status WHERE id = :id');
            $stmt->execute([
                'status' => $status,
                'id' => $requestId,
            ]);
        } catch (\Throwable $e) {
            // noop
        }
    }

    private function updateOtpAttempt(string $requestId, int $attempts, string $status): void
    {
        if (!$this->pdo) {
            return;
        }
        try {
            $stmt = $this->pdo->prepare('UPDATE ' . self::OTP_TABLE . ' SET attempts = :attempts, status = :status WHERE id = :id');
            $stmt->execute([
                'attempts' => $attempts,
                'status' => $status,
                'id' => $requestId,
            ]);
        } catch (\Throwable $e) {
            // noop
        }
    }

    private function ensureOtpTable(): void
    {
        if (!$this->ensureSchemaTableReady(self::OTP_TABLE)) {
            throw new RuntimeException('schema_not_ready');
        }
    }

    private function ensureSchemaTableReady(string $table): bool
    {
        if (!$this->pdo) {
            return false;
        }
        if ((string)$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = :table");
            $stmt->execute(['table' => $table]);
            return (int)$stmt->fetchColumn() > 0;
        }
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table'
        );
        $stmt->execute(['table' => $table]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function resolveCanonicalDoctorId(string $doctorId): string
    {
        $raw = trim($doctorId);
        if ($raw === '') {
            return '';
        }
        if (!$this->pdo) {
            return $raw;
        }
        try {
            return DoctorIdentity\resolveCanonicalDoctorId($this->pdo, $raw);
        } catch (\Throwable $e) {
            return $raw;
        }
    }

    private function resolveDoctorIdForOtpStorage(string $canonicalDoctorId): string
    {
        $canonical = $this->resolveCanonicalDoctorId($canonicalDoctorId);
        if ($canonical === '') {
            throw new RuntimeException('doctor_id required');
        }
        if (!$this->pdo) {
            return $canonical;
        }

        $columnType = $this->getOtpDoctorIdColumnType();
        $isNumericStorage = str_contains(strtolower($columnType), 'int');
        if (!$isNumericStorage) {
            return $canonical;
        }

        $legacyNumeric = DoctorIdentity\resolveLegacyDoctorIdForCanonical(
            $this->pdo,
            $canonical,
            static fn(string $value): bool => ctype_digit($value)
        );
        if ($legacyNumeric === '') {
            throw new RuntimeException('doctor_id_legacy_alias_required');
        }
        return $legacyNumeric;
    }

    private function getOtpDoctorIdColumnType(): string
    {
        if (!$this->pdo) {
            return '';
        }
        try {
            $stmt = $this->pdo->prepare(
                'SELECT COLUMN_TYPE
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = :table
                   AND COLUMN_NAME = :column
                 LIMIT 1'
            );
            $stmt->execute([
                'table' => self::OTP_TABLE,
                'column' => 'doctor_id',
            ]);
            return (string)($stmt->fetchColumn() ?: '');
        } catch (\Throwable $e) {
            return '';
        }
    }

    private function resolveConsultorioId(
        string $doctorId,
        $requestedConsultorioId,
        string $startAt = '',
        string $endAt = ''
    ): ?string
    {
        if ($this->isValidNumeric($requestedConsultorioId)) {
            return (string)$requestedConsultorioId;
        }

        if (!$this->pdo) {
            return null;
        }

        $scheduledConsultorios = $this->resolveConsultoriosFromSchedule($doctorId);
        if ($startAt !== '' && $endAt !== '') {
            foreach ($scheduledConsultorios as $candidate) {
                $slotCheck = $this->checkSlotAvailability($doctorId, $candidate, $startAt, $endAt);
                if (($slotCheck['ok'] ?? false) === true) {
                    return $candidate;
                }
            }
        }

        if (!empty($scheduledConsultorios)) {
            return $scheduledConsultorios[0];
        }

        return $this->resolveConsultorioFromCatalog($doctorId);
    }

    private function resolveConsultorioFromCatalog(string $doctorId): ?string
    {
        if (!$this->pdo) {
            return null;
        }

        try {
            $repository = new ConsultoriosRepository($this->pdo);
            $rows = $repository->listByDoctor($doctorId);
        } catch (\Throwable $e) {
            return null;
        }

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $candidate = $row['consultorio_id'] ?? $row['id'] ?? null;
            if ($this->isValidNumeric($candidate)) {
                return (string)$candidate;
            }
        }

        return null;
    }

    private function resolveConsultoriosFromSchedule(string $doctorId): array
    {
        if (!$this->pdo) {
            return [];
        }

        $candidates = [
            'consultorio_schedule',
            'consultorio_schedules',
            'consultorio_horarios',
            'consultorio_horarios_base',
            'agenda_consultorio_schedule',
        ];

        foreach ($candidates as $table) {
            if (!$this->tableExists($table)) {
                continue;
            }
            try {
                $activeFilter = $this->tableColumnExists($table, 'is_active')
                    ? ' AND is_active = 1'
                    : '';
                $sql = sprintf(
                    'SELECT consultorio_id
                       FROM %s
                      WHERE doctor_id = :doctor_id
                        %s
                      GROUP BY consultorio_id
                      ORDER BY consultorio_id ASC',
                    $table,
                    $activeFilter
                );
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute(['doctor_id' => $doctorId]);
                $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
                if (!is_array($rows)) {
                    continue;
                }
                $out = [];
                foreach ($rows as $value) {
                    if ($this->isValidNumeric($value)) {
                        $out[] = (string)$value;
                    }
                }
                if (!empty($out)) {
                    return array_values(array_unique($out));
                }
            } catch (\Throwable $e) {
                continue;
            }
        }

        return [];
    }

    private function tableExists(string $table): bool
    {
        if (!$this->pdo) {
            return false;
        }

        try {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table');
            $stmt->execute(['table' => $table]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function tableColumnExists(string $table, string $column): bool
    {
        if (!$this->pdo) {
            return false;
        }

        try {
            $stmt = $this->pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.columns
                  WHERE table_schema = DATABASE()
                    AND table_name = :table
                    AND column_name = :column'
            );
            $stmt->execute([
                'table' => $table,
                'column' => $column,
            ]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function normalizeDateTime(string $value): ?string
    {
        $raw = trim($value);
        if ($raw === '') {
            return null;
        }

        $timezone = new DateTimeZone(self::TIMEZONE);
        $formats = ['Y-m-d H:i:s', 'Y-m-d\\TH:i:s', 'Y-m-d\\TH:i'];
        foreach ($formats as $format) {
            $dt = DateTimeImmutable::createFromFormat($format, $raw, $timezone);
            if ($dt && $dt->format($format) === $raw) {
                return $dt->format('Y-m-d H:i:s');
            }
        }

        return null;
    }

    private function parseDateTime(string $value): ?DateTimeImmutable
    {
        $normalized = $this->normalizeDateTime($value);
        if ($normalized === null) {
            return null;
        }

        $timezone = new DateTimeZone(self::TIMEZONE);
        $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $normalized, $timezone);
        return $dt ?: null;
    }

    private function isValidNumeric($value): bool
    {
        if ($value === null) {
            return false;
        }
        return ctype_digit((string)$value);
    }

    private function now(): DateTimeImmutable
    {
        if (is_callable($this->clock)) {
            $value = ($this->clock)();
            if ($value instanceof DateTimeImmutable) return $value;
        }
        return new DateTimeImmutable('now', new DateTimeZone(self::TIMEZONE));
    }

    private function generateOtp(): string
    {
        return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function generateUuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    private function isQaDebugEnabled(): bool
    {
        $headerQa = trim((string)($_SERVER['HTTP_X_QA_MODE'] ?? ''));
        if ($headerQa === '1') {
            return true;
        }

        $envQa = getenv('QA_MODE');
        return is_string($envQa) && trim($envQa) === '1';
    }

    private function success(array $data, array $meta = [], string $message = ''): array
    {
        return [
            'ok' => true,
            'error' => null,
            'message' => $message,
            'data' => $data,
            'meta' => empty($meta) ? (object)[] : (object)$meta,
        ];
    }

    private function error(string $code, string $message, array $meta = []): array
    {
        return [
            'ok' => false,
            'error' => $code,
            'message' => $message,
            'data' => null,
            'meta' => empty($meta) ? (object)[] : (object)$meta,
        ];
    }
}
