<?php
declare(strict_types=1);

namespace Platform\Contracts;

final class AuditIntegrityReason
{
    public const VALID = 'valid';
    public const SEQUENCE_DUPLICATE = 'sequence_duplicate';
    public const SEQUENCE_GAP = 'sequence_gap';
    public const SEQUENCE_OUT_OF_ORDER = 'sequence_out_of_order';
    public const STREAM_MISMATCH = 'stream_mismatch';
    public const UNSUPPORTED_SCHEMA_VERSION = 'unsupported_schema_version';
    public const PREVIOUS_HASH_MISMATCH = 'previous_hash_mismatch';
    public const EVENT_HASH_MISMATCH = 'event_hash_mismatch';
    public const EVENT_ID_DUPLICATE = 'event_id_duplicate';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::VALID, self::SEQUENCE_DUPLICATE, self::SEQUENCE_GAP, self::SEQUENCE_OUT_OF_ORDER, self::STREAM_MISMATCH, self::UNSUPPORTED_SCHEMA_VERSION, self::PREVIOUS_HASH_MISMATCH, self::EVENT_HASH_MISMATCH, self::EVENT_ID_DUPLICATE];
    }
}
