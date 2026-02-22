<?php
namespace Agenda\Controllers;

use Agenda\Repositories\PublicOtpRepository;
use DateTimeImmutable;
use DateTimeZone;
use PDOException;
use RuntimeException;

require_once __DIR__ . '/../repositories/PublicOtpRepository.php';
require_once __DIR__ . '/../../../api/_lib/db.php';

class PublicOtpController
{
    private const TIMEZONE = 'America/Mexico_City';
    private const EXPIRES_IN_SECONDS = 600;
    private const MAX_ATTEMPTS = 5;

    private ?PublicOtpRepository $repository = null;
    private ?string $dbError = null;

    public function __construct()
    {
        try {
            $pdo = mxmed_pdo();
            $this->repository = new PublicOtpRepository($pdo);
        } catch (RuntimeException $e) {
            $this->dbError = 'database error';
        } catch (\Throwable $e) {
            $this->dbError = 'database error';
        }
    }

    public function request(array $body): array
    {
        if ($this->dbError || !$this->repository) {
            return $this->error('db_error', 'database error', ['route' => 'public_otp_request']);
        }

        $doctorIdRaw = trim((string)($body['doctor_id'] ?? ''));
        $contactType = strtolower(trim((string)($body['contact_type'] ?? '')));
        $contactValue = trim((string)($body['contact_value'] ?? ''));

        $errors = [];
        if ($doctorIdRaw === '' || !$this->isNumericString($doctorIdRaw)) {
            $errors['doctor_id'] = 'required_numeric';
        }

        if (!in_array($contactType, ['sms', 'email'], true)) {
            $errors['contact_type'] = 'must_be_sms_or_email';
        }

        if ($contactValue === '') {
            $errors['contact_value'] = 'required';
        } elseif ($contactType === 'email' && !$this->isValidEmail($contactValue)) {
            $errors['contact_value'] = 'invalid_email';
        } elseif ($contactType === 'sms' && !$this->isValidSms($contactValue)) {
            $errors['contact_value'] = 'invalid_sms';
        }

        if (!empty($errors)) {
            return $this->error('invalid_params', 'invalid payload', [
                'route' => 'public_otp_request',
                'fields' => $errors,
            ]);
        }

        $code = $this->generateCode();
        $codeHash = password_hash($code, PASSWORD_DEFAULT);
        if (!is_string($codeHash) || $codeHash === '') {
            return $this->error('server_error', 'could not generate otp hash', ['route' => 'public_otp_request']);
        }

        $now = $this->now();
        $expiresAt = $now->modify('+10 minutes');

        try {
            $otpId = $this->repository->createOtp(
                (int)$doctorIdRaw,
                $contactType,
                $contactValue,
                $codeHash,
                $expiresAt->format('Y-m-d H:i:s')
            );
        } catch (PDOException $e) {
            return $this->error('db_error', 'database error', ['route' => 'public_otp_request']);
        } catch (\Throwable $e) {
            return $this->error('db_error', 'database error', ['route' => 'public_otp_request']);
        }

        $meta = [
            'route' => 'public_otp_request',
        ];

        if ($this->isQaModeEnabled()) {
            $meta['debug_code'] = $code;
        }

        return $this->success([
            'otp_id' => $otpId,
            'expires_in' => self::EXPIRES_IN_SECONDS,
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

    private function isValidEmail(string $value): bool
    {
        if (strpos($value, '@') === false) {
            return false;
        }
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    private function isValidSms(string $value): bool
    {
        if (!preg_match('/^[0-9+\-\s]+$/', $value)) {
            return false;
        }

        $digits = preg_replace('/\D+/', '', $value);
        return is_string($digits) && strlen($digits) >= 8 && strlen($digits) <= 20;
    }

    private function generateCode(): string
    {
        return (string)random_int(100000, 999999);
    }

    private function now(): DateTimeImmutable
    {
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

    private function isQaModeEnabled(): bool
    {
        $env = getenv('MXMED_QA_MODE');
        if (is_string($env) && trim($env) === '1') {
            return true;
        }

        $header = trim((string)($_SERVER['HTTP_X_MXMED_QA_MODE'] ?? ''));
        return $header === '1';
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
