<?php
declare(strict_types=1);

namespace Identity\Contracts;

final class ConsentDocumentType
{
    public const TERMS = 'terms';
    public const PRIVACY_NOTICE = 'privacy_notice';

    public static function assertValid(string $value): string
    {
        if (!in_array($value, self::all(), true)) {
            throw new \InvalidArgumentException('unknown_consent_document_type');
        }
        return $value;
    }

    /** @return list<string> */
    public static function all(): array
    {
        return [self::TERMS, self::PRIVACY_NOTICE];
    }
}
