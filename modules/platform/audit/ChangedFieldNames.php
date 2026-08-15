<?php
declare(strict_types=1);

namespace Platform\Audit;

/** Minimized backend-authoritative projection for changed_field_names. */
final readonly class ChangedFieldNames
{
    /** @var list<string> */
    public array $values;

    /** @param list<string> $values */
    private function __construct(array $values)
    {
        if ($values === [] || count($values) > 32) {
            throw new \InvalidArgumentException('invalid_changed_field_count');
        }
        $normalized = [];
        foreach ($values as $value) {
            if (preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $value) !== 1) {
                throw new \InvalidArgumentException('invalid_changed_field_name');
            }
            if (preg_match('/(?:password|credential|secret|token|otp)/', $value) === 1) {
                throw new \InvalidArgumentException('sensitive_changed_field_forbidden');
            }
            $normalized[] = $value;
        }
        $deduplicated = array_values(array_unique($normalized));
        sort($deduplicated, SORT_STRING);
        if ($deduplicated !== $normalized) {
            throw new \InvalidArgumentException('changed_fields_must_be_unique_and_sorted');
        }
        $this->values = $deduplicated;
    }

    /** @param list<string> $values */
    public static function fromBackendAllowlist(array $values): self
    {
        return new self($values);
    }
}
