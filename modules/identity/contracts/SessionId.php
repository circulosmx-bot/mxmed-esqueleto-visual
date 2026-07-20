<?php
declare(strict_types=1);

namespace Identity\Contracts;

final class SessionId
{
    public function __construct(private string $value)
    {
        $this->value = trim($this->value);
        if (preg_match('/^[A-Za-z0-9_-]{32,128}$/', $this->value) !== 1) throw new \InvalidArgumentException('invalid_session_id');
    }

    public static function generate(): self
    {
        return new self(rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '='));
    }

    public function value(): string { return $this->value; }
    public function __toString(): string { return $this->value; }
}
