<?php
declare(strict_types=1);

namespace Platform\Contracts;

final readonly class ScopeSet
{
    /** @var list<string> */
    private array $values;

    /** @param list<string> $values */
    public function __construct(array $values = [])
    {
        $normalized = [];
        foreach ($values as $value) $normalized[] = (new SafeIdentifier($value))->value();
        $normalized = array_values(array_unique($normalized, SORT_STRING));
        sort($normalized, SORT_STRING);
        $this->values = $normalized;
    }
    /** @return list<string> */
    public function values(): array { return $this->values; }
    public function contains(string $value): bool { return in_array($value, $this->values, true); }
    public function isEmpty(): bool { return $this->values === []; }
}
