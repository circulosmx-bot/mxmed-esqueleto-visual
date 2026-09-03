<?php
namespace Agenda\Controllers;

use Agenda\Composition\PublicAgendaOtpComposition;
use Agenda\Contracts\OtpProviderPort;
use Agenda\Helpers\DoctorIdentity as DoctorIdentity;
use Agenda\Repositories\PublicOtpRepository;
use DateTimeImmutable;
use DateTimeZone;
use PDOException;
use RuntimeException;

require_once __DIR__ . '/../composition/PublicAgendaOtpComposition.php';
require_once __DIR__ . '/../contracts/OtpProviderPort.php';
require_once __DIR__ . '/../repositories/PublicOtpRepository.php';
require_once __DIR__ . '/../helpers/doctor_identity.php';
require_once __DIR__ . '/../../../api/_lib/db.php';

class PublicOtpController
{
    private const TIMEZONE = 'America/Mexico_City';
    private const EXPIRES_IN_SECONDS = 600;
    private const MAX_ATTEMPTS = 5;
    private const REQUEST_RATE_LIMIT_SECONDS = 60;

    private ?PublicOtpRepository $repository = null;
    private ?string $dbError = null;
    private ?\PDO $pdo = null;
    private ?OtpProviderPort $deliveryProvider = null;
    private $clock;

    public function __construct(?\PDO $pdo = null, ?OtpProviderPort $deliveryProvider = null, ?callable $clock = null)
    {
        $this->clock = $clock;
        try {
            $pdo ??= mxmed_pdo();
            $this->pdo = $pdo;
            $this->repository = new PublicOtpRepository($pdo);
        } catch (RuntimeException $e) {
            $this->dbError = 'database error';
        } catch (\Throwable $e) {
            $this->dbError = 'database error';
        }
        if ($deliveryProvider !== null) {
            $this->deliveryProvider = $deliveryProvider;
        } else {
            try {
                $this->deliveryProvider = PublicAgendaOtpComposition::productive();
            } catch (\Throwable) {
                $this->deliveryProvider = null;
            }
        }
    }

    public function request(array $body): array
    {
        if ($this->dbError || !$this->repository) {
            return $this->error('db_error', 'database error', ['route' => 'public_otp_request']);
        }

        $appointmentId = trim((string)($body['appointment_id'] ?? ''));

        $errors = [];
        if ($appointmentId === '' || strlen($appointmentId) > 64) $errors['appointment_id'] = 'required';

        if (!empty($errors)) {
            return $this->error('invalid_params', 'invalid payload', [
                'route' => 'public_otp_request',
                'fields' => $errors,
            ]);
        }
        if ($this->deliveryProvider === null || !$this->deliveryProvider->configured()) {
            return $this->error('otp_delivery_unavailable', 'No fue posible enviar el código.', [
                'route' => 'public_otp_request',
            ]);
        }

        try {
            $booking = $this->repository->findBookingState($appointmentId);
        } catch (\Throwable) {
            return $this->error('otp_delivery_unavailable', 'No fue posible enviar el código.', [
                'route' => 'public_otp_request',
            ]);
        }
        if (!is_array($booking) || (string)($booking['status'] ?? '') !== 'pending_otp') {
            return $this->error('booking_not_eligible', 'La reserva no está disponible para verificación.', [
                'route' => 'public_otp_request',
            ]);
        }
        $bookingExpiry = $this->parseDateTime((string)($booking['expires_at'] ?? ''));
        if ($bookingExpiry === null || $bookingExpiry < $this->now()) {
            return $this->error('booking_not_eligible', 'La reserva no está disponible para verificación.', [
                'route' => 'public_otp_request',
            ]);
        }
        $recipient = $this->resolveBookingEmail($booking);
        if ($recipient === null) {
            return $this->error('booking_email_unavailable', 'No existe un correo válido para esta reserva.', [
                'route' => 'public_otp_request',
            ]);
        }
        $lastRequestAt = $this->parseDateTime((string)($booking['otp_created_at'] ?? ''));
        if ($lastRequestAt !== null && $lastRequestAt > $this->now()->modify('-' . self::REQUEST_RATE_LIMIT_SECONDS . ' seconds')) {
            return $this->error('rate_limited', 'Espera antes de solicitar otro código.', [
                'route' => 'public_otp_request',
                'retry_after' => self::REQUEST_RATE_LIMIT_SECONDS,
            ]);
        }

        $doctorIdRaw = trim((string)($booking['doctor_id'] ?? ''));
        $doctorIdCanonical = $doctorIdRaw;
        if ($this->pdo) {
            try {
                $doctorIdCanonical = DoctorIdentity\resolveCanonicalDoctorId($this->pdo, $doctorIdRaw);
            } catch (\Throwable) {
                $doctorIdCanonical = $doctorIdRaw;
            }
        }
        if ($doctorIdCanonical === '') {
            return $this->error('booking_not_eligible', 'La reserva no está disponible para verificación.', [
                'route' => 'public_otp_request',
            ]);
        }

        $code = $this->generateCode();
        $codeHash = password_hash($code, PASSWORD_DEFAULT);
        if (!is_string($codeHash) || $codeHash === '') {
            return $this->error('server_error', 'could not generate otp hash', ['route' => 'public_otp_request']);
        }

        $now = $this->now();
        $expiresAt = $now->modify('+10 minutes');
        $previousOtpId = isset($booking['otp_id']) ? (int)$booking['otp_id'] : null;

        try {
            $otpId = $this->repository->createOtp(
                $doctorIdCanonical,
                'email',
                $recipient,
                $codeHash,
                $expiresAt->format('Y-m-d H:i:s'),
                $now->format('Y-m-d H:i:s')
            );
            if (!$this->repository->bindOtpToPendingBooking(
                $appointmentId,
                (int)$otpId,
                $previousOtpId,
                $now->format('Y-m-d H:i:s')
            )) {
                $this->repository->discardFailedDelivery($appointmentId, (int)$otpId);
                return $this->error('booking_not_eligible', 'La reserva no está disponible para verificación.', [
                    'route' => 'public_otp_request',
                ]);
            }
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'doctor_id_legacy_alias_required') {
                return $this->error('booking_not_eligible', 'La reserva no está disponible para verificación.', [
                    'route' => 'public_otp_request',
                ]);
            }
            return $this->error('db_error', 'database error', ['route' => 'public_otp_request']);
        } catch (PDOException $e) {
            return $this->error('db_error', 'database error', ['route' => 'public_otp_request']);
        } catch (\Throwable $e) {
            return $this->error('db_error', 'database error', ['route' => 'public_otp_request']);
        }

        try {
            $delivery = $this->deliveryProvider->deliver('email', $recipient, $code, [
                'purpose' => 'public_appointment_confirmation',
            ]);
        } catch (\Throwable) {
            $delivery = null;
        }
        unset($code);
        if ($delivery === null || !$delivery->accepted()) {
            try {
                $this->repository->discardFailedDelivery($appointmentId, (int)$otpId, $previousOtpId);
            } catch (\Throwable) {
                // The challenge remains unverified and the booking remains pending.
            }
            return $this->error('otp_delivery_unavailable', 'No fue posible enviar el código.', [
                'route' => 'public_otp_request',
            ]);
        }

        $meta = [
            'route' => 'public_otp_request',
        ];

        return $this->success([
            'otp_id' => $otpId,
            'expires_in' => self::EXPIRES_IN_SECONDS,
            'delivery_channel' => 'email',
            'destination_hint' => $this->maskEmail($recipient),
        ], $meta);
    }

    public function verify(array $body): array
    {
        if ($this->dbError || !$this->repository) {
            return $this->error('db_error', 'database error', ['route' => 'public_otp_verify']);
        }

        $otpIdRaw = trim((string)($body['otp_id'] ?? ''));
        $code = trim((string)($body['code'] ?? ''));

        $errors = [];
        if ($otpIdRaw === '' || !$this->isNumericString($otpIdRaw)) {
            $errors['otp_id'] = 'required_numeric';
        }
        if (!preg_match('/^\d{6}$/', $code)) {
            $errors['code'] = 'must_be_6_digits';
        }

        if (!empty($errors)) {
            return $this->error('invalid_params', 'invalid payload', [
                'route' => 'public_otp_verify',
                'fields' => $errors,
            ]);
        }

        $otpId = (int)$otpIdRaw;

        try {
            $row = $this->repository->findOtpById($otpId);
        } catch (PDOException $e) {
            return $this->error('db_error', 'database error', ['route' => 'public_otp_verify']);
        } catch (\Throwable $e) {
            return $this->error('db_error', 'database error', ['route' => 'public_otp_verify']);
        }

        if (!$row) {
            return $this->error('not_found', 'otp not found', [
                'route' => 'public_otp_verify',
                'otp_id' => $otpId,
            ]);
        }

        if ((int)($row['verified'] ?? 0) === 1) {
            return $this->success([
                'verified' => true,
            ], [
                'route' => 'public_otp_verify',
                'otp_id' => $otpId,
                'idempotent' => true,
            ], 'already verified');
        }

        $expiresAt = $this->parseDateTime((string)($row['expires_at'] ?? ''));
        if (!$expiresAt || $expiresAt < $this->now()) {
            return $this->error('expired', 'otp expired', [
                'route' => 'public_otp_verify',
                'otp_id' => $otpId,
            ]);
        }

        $attempts = (int)($row['attempts'] ?? 0);
        if ($attempts >= self::MAX_ATTEMPTS) {
            return $this->error('too_many_attempts', 'too many attempts', [
                'route' => 'public_otp_verify',
                'otp_id' => $otpId,
                'attempts' => $attempts,
            ]);
        }

        $hash = (string)($row['code_hash'] ?? '');
        if ($hash === '' || !password_verify($code, $hash)) {
            try {
                $this->repository->incrementAttempts($otpId);
                $updated = $this->repository->findOtpById($otpId);
                $newAttempts = (int)($updated['attempts'] ?? ($attempts + 1));
            } catch (\Throwable $e) {
                $newAttempts = $attempts + 1;
            }

            if ($newAttempts >= self::MAX_ATTEMPTS) {
                return $this->error('too_many_attempts', 'too many attempts', [
                    'route' => 'public_otp_verify',
                    'otp_id' => $otpId,
                    'attempts' => $newAttempts,
                ]);
            }

            return $this->error('invalid_code', 'invalid code', [
                'route' => 'public_otp_verify',
                'otp_id' => $otpId,
                'attempts' => $newAttempts,
            ]);
        }

        try {
            $this->repository->markVerified($otpId);
        } catch (PDOException $e) {
            return $this->error('db_error', 'database error', [
                'route' => 'public_otp_verify',
                'otp_id' => $otpId,
            ]);
        } catch (\Throwable $e) {
            return $this->error('db_error', 'database error', [
                'route' => 'public_otp_verify',
                'otp_id' => $otpId,
            ]);
        }

        return $this->success([
            'verified' => true,
        ], [
            'route' => 'public_otp_verify',
            'otp_id' => $otpId,
        ]);
    }

    private function isNumericString(string $value): bool
    {
        return $value !== '' && ctype_digit($value);
    }

    private function resolveBookingEmail(array $booking): ?string
    {
        $payload = json_decode((string)($booking['payload_json'] ?? ''), true);
        if (!is_array($payload)) return null;
        $bookerIsPatient = ($payload['booker_is_patient'] ?? null) === true;
        $email = $bookerIsPatient
            ? (string)($payload['patient']['email'] ?? '')
            : (string)($payload['booker']['email'] ?? '');
        $email = strtolower(trim($email));
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : null;
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $first = function_exists('mb_substr') ? mb_substr($local, 0, 1, 'UTF-8') : substr($local, 0, 1);
        return $first . '***@' . $domain;
    }

    private function generateCode(): string
    {
        return (string)random_int(100000, 999999);
    }

    private function now(): DateTimeImmutable
    {
        if (is_callable($this->clock)) {
            $value = ($this->clock)();
            if ($value instanceof DateTimeImmutable) return $value;
        }
        return new DateTimeImmutable('now', new DateTimeZone(self::TIMEZONE));
    }

    private function parseDateTime(string $value): ?DateTimeImmutable
    {
        if ($value === '') {
            return null;
        }

        $timezone = new DateTimeZone(self::TIMEZONE);
        $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value, $timezone);
        return $dt ?: null;
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
