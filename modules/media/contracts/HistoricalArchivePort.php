<?php
declare(strict_types=1);
namespace Media\Contracts;
/** Future private S3 / DEEP_ARCHIVE adapter. No delete or public URL operation. */
interface HistoricalArchivePort
{
    /** Immutable, idempotent only for exactly identical bytes and identity metadata. */
    public function put(string $key,string $bytes,array $identity):void;
    /** Must prove stored identity AND actual bytes/checksum, never echo request metadata. */
    public function verify(string $key,array $identity):bool;
}
