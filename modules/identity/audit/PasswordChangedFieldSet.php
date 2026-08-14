<?php
declare(strict_types=1);

namespace Identity\Audit;

final readonly class PasswordChangedFieldSet
{
    /** @var list<string> */
    public array $names;

    private function __construct(array $names)
    {
        if ($names !== ['password']) throw new \InvalidArgumentException('invalid_password_changed_field_set');
        $this->names = $names;
    }

    public static function passwordOnly(): self { return new self(['password']); }
}
