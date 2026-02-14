<?php
namespace Agenda\Controllers;

use Agenda\Repositories\ConsultoriosRepository;
use Agenda\Services\DevOtpSender;
use Agenda\Services\OtpSender;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOException;
use RuntimeException;

require_once __DIR__ . '/../repositories/ConsultoriosRepository.php';
require_once __DIR__ . '/../services/OtpSender.php';
require_once __DIR__ . '/AvailabilityController.php';
require_once __DIR__ . '/AppointmentWriteController.php';
require_once __DIR__ . '/../config/agenda.php';
require_once __DIR__ . '/../../../api/_lib/db.php';

class PublicAppointmentsController
{
    private const TIMEZONE = 'America/Mexico_City';
    private const OTP_TABLE = 'agenda_public_otp_requests';
    private const OTP_TTL_MINUTES = 10;
    private const OTP_MAX_ATTEMPTS = 5;

    private ?PDO $pdo = null;
    private ?string $dbError = null;
    private OtpSender $otpSender;

    public function __construct(?OtpSender $otpSender = null)
    {
        try {
            $this->pdo = mxmed_pdo();
        } catch (RuntimeException $e) {
            $this->dbError = 'database error';
        } catch (\Throwable $e) {
            $this->dbError = 'database error';
        }

        $this->otpSender = $otpSender ?: new DevOtpSender();
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

        $doctorId = (string)$validation['doctor_id'];
        $consultorioId = $this->resolveConsultorioId($doctorId, $payload['consultorio_id'] ?? null);

        if (!$this->isValidNumeric($consultorioId)) {
            return $this->error('invalid_params', 'consultorio_id must be numeric', [
                'doctor_id' => $doctorId,
                'consultorio_id_used' => null,
            ]);
        }

        try {
            $this->ensureOtpTable();
        } catch (\Throwable $e) {
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
                'doctor_id' => (int)$doctorId,
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
        if ($this->isQaDebugEnabled()) {
            $meta['otp_debug'] = $otp;
        }

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
            return $this->error('db_error', 'database error');
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
        $writer = new AppointmentWriteController();
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

    private function validateRequestPayload(array $payload): array
    {
        $errors = [];

        $doctorId = trim((string)($payload['doctor_id'] ?? ''));
        if (!$this->isValidNumeric($doctorId)) {
            $errors['doctor_id'] = 'required_numeric';
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

        $availability = new AvailabilityController();
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
        if (!$this->pdo) {
            throw new RuntimeException('database error');
        }

        $sql = 'CREATE TABLE IF NOT EXISTS ' . self::OTP_TABLE . ' (
            id VARCHAR(36) NOT NULL,
            doctor_id INT NOT NULL,
            consultorio_id INT NOT NULL,
            start_at DATETIME NOT NULL,
            end_at DATETIME NOT NULL,
            patient_name VARCHAR(191) NOT NULL,
            patient_phone VARCHAR(32) DEFAULT NULL,
            patient_email VARCHAR(191) DEFAULT NULL,
            otp_hash VARCHAR(255) NOT NULL,
            otp_last4 CHAR(4) DEFAULT NULL,
            status ENUM("pending_verification","verified","expired","failed") NOT NULL DEFAULT "pending_verification",
            attempts INT NOT NULL DEFAULT 0,
            expires_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL,
            verified_at DATETIME DEFAULT NULL,
            meta_json JSON DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_public_otp_slot (doctor_id, consultorio_id, start_at),
            KEY idx_public_otp_expires (expires_at),
            KEY idx_public_otp_email (patient_email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        $this->pdo->exec($sql);
    }

    private function resolveConsultorioId(string $doctorId, $requestedConsultorioId): ?string
    {
        if ($this->isValidNumeric($requestedConsultorioId)) {
            return (string)$requestedConsultorioId;
        }

        if (!$this->pdo) {
            return null;
        }

        $catalogConsultorio = $this->resolveConsultorioFromCatalog($doctorId);
        if ($catalogConsultorio !== null) {
            return $catalogConsultorio;
        }

        return $this->resolveConsultorioFromSchedule($doctorId);
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

    private function resolveConsultorioFromSchedule(string $doctorId): ?string
    {
        if (!$this->pdo) {
            return null;
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
                $sql = sprintf(
                    'SELECT consultorio_id FROM %s WHERE doctor_id = :doctor_id ORDER BY consultorio_id ASC LIMIT 1',
                    $table
                );
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute(['doctor_id' => $doctorId]);
                $value = $stmt->fetchColumn();
                if ($this->isValidNumeric($value)) {
                    return (string)$value;
                }
            } catch (\Throwable $e) {
                continue;
            }
        }

        return null;
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
