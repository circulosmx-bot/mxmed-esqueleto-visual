<?php
declare(strict_types=1);

namespace Media\Contracts;

interface PublicMediaStoragePort
{
    public function storeImmutable(string $storageKey, string $sourcePath): void;

    /** @return array{stream:resource,bytes:int} */
    public function openReadStream(string $storageKey): array;

    public function exists(string $storageKey): bool;

    public function delete(string $storageKey): void;
}
