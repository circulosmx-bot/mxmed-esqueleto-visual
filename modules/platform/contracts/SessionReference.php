<?php
declare(strict_types=1);

namespace Platform\Contracts;

final readonly class SessionReference
{
    private SafeIdentifier $value;

    public function __construct(string $value)
    {
        $this->value = new SafeIdentifier($value);
    }
    public function value(): string { return $this->value->value(); }
}
