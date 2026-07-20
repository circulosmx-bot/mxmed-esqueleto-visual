<?php
declare(strict_types=1);

namespace Identity\Services;

final class IdentityIdGenerator
{
    public static function accountId(): string { return 'acct_' . bin2hex(random_bytes(12)); }
    public static function consentId(): string { return 'cons_' . bin2hex(random_bytes(12)); }
    public static function tokenId(): string { return 'tok_' . bin2hex(random_bytes(12)); }
}
