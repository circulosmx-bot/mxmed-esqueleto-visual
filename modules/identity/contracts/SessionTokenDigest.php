<?php
declare(strict_types=1);

namespace Identity\Contracts;

final class SessionTokenDigest
{
    public function __construct(private string $value)
    {
        if (preg_match('/^[a-f0-9]{64}$/', $this->value) !== 1) throw new \InvalidArgumentException('invalid_session_token_digest');
    }

    public function value(): string { return $this->value; }
    public function __toString(): string { return $this->value; }
}
