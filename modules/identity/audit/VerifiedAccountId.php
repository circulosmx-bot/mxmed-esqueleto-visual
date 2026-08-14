<?php
declare(strict_types=1);

namespace Identity\Audit;

final readonly class VerifiedAccountId
{
    private function __construct(public string $value)
    {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,127}$/D', $value) !== 1) {
            throw new \InvalidArgumentException('invalid_verified_account_id');
        }
    }

    public static function fromValidatedTokenResolution(string $verifiedAccountId): self
    {
        return new self($verifiedAccountId);
    }
}
