<?php
declare(strict_types=1);

namespace Platform\Contracts;

final readonly class SafeIdentifier
{
    private string $value;

    public function __construct(string $value)
    {
        $value = trim($value);
        $lower = strtolower($value);
        if ($value === '' || strlen($value) > 128 || preg_match('/^[A-Za-z0-9_.:-]+$/', $value) !== 1) {
            throw new \InvalidArgumentException('invalid_contract_identifier');
        }
        foreach (['password', 'cookie', 'secret', 'client_secret', 'payload', 'authorization'] as $forbidden) {
            if (str_contains($lower, $forbidden)) {
                throw new \InvalidArgumentException('sensitive_contract_value_rejected');
            }
        }
        $this->value = $value;
    }

    public function value(): string { return $this->value; }
    public function __toString(): string { return $this->value; }
}
