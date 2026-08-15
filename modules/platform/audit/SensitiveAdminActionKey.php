<?php
declare(strict_types=1);

namespace Platform\Audit;

/** Constructible only through the finite backend catalog. */
final readonly class SensitiveAdminActionKey
{
    private function __construct(
        public string $value,
        public string $catalogVersion,
        public string $targetType,
    ) {}

    public static function fromBackendCatalog(string $key, SensitiveAdminActionCatalog $catalog): self
    {
        $definition = $catalog->definition($key);
        return new self($key, SensitiveAdminActionCatalog::VERSION, $definition['target_type']);
    }
}
