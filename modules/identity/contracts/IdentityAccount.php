<?php
declare(strict_types=1);

namespace Identity\Contracts;

final class IdentityAccount
{
    private string $accountId;
    private string $emailAddress;
    private string $emailNormalized;
    private string $status;
    private ?string $emailVerifiedAt;

    public function __construct(
        string $accountId,
        string $emailAddress,
        string $status = AccountStatus::PENDING_VERIFICATION,
        ?string $emailVerifiedAt = null
    ) {
        $this->accountId = self::assertAccountId($accountId);
        $this->emailAddress = self::assertEmail($emailAddress);
        $this->emailNormalized = self::normalizeEmail($emailAddress);
        $this->status = AccountStatus::assertValid($status);
        if ($emailVerifiedAt !== null && !self::isTimestamp($emailVerifiedAt)) {
            throw new \InvalidArgumentException('invalid_email_verified_at');
        }
        if ($status === AccountStatus::PENDING_VERIFICATION && $emailVerifiedAt !== null) {
            throw new \InvalidArgumentException('pending_account_cannot_be_verified');
        }
        $this->emailVerifiedAt = $emailVerifiedAt;
    }

    public function accountId(): string { return $this->accountId; }
    public function emailAddress(): string { return $this->emailAddress; }
    public function emailNormalized(): string { return $this->emailNormalized; }
    public function status(): string { return $this->status; }
    public function emailVerifiedAt(): ?string { return $this->emailVerifiedAt; }

    public static function normalizeEmail(string $email): string
    {
        $email = trim($email);
        $normalized = function_exists('mb_strtolower') ? mb_strtolower($email, 'UTF-8') : strtolower($email);
        if ($normalized === '' || filter_var($normalized, FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException('invalid_email');
        }
        if (preg_match('/[\x00-\x1F\x7F\s]/', $normalized) === 1) {
            throw new \InvalidArgumentException('invalid_email_whitespace');
        }
        return $normalized;
    }

    private static function assertEmail(string $email): string
    {
        $email = trim($email);
        self::normalizeEmail($email);
        if (strlen($email) > 190) {
            throw new \InvalidArgumentException('email_too_long');
        }
        return $email;
    }

    private static function assertAccountId(string $accountId): string
    {
        $accountId = trim($accountId);
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{1,63}$/', $accountId) !== 1) {
            throw new \InvalidArgumentException('invalid_account_id');
        }
        return $accountId;
    }

    private static function isTimestamp(string $value): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value) === 1;
    }
}
