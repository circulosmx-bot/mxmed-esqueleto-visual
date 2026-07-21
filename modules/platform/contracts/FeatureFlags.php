<?php
declare(strict_types=1);

namespace Platform\Contracts;

final class FeatureFlags
{
    public const SUPPORT_ASSISTED_SESSION_ENABLED = 'support_assisted_session_enabled';
    public const BREAK_GLASS_ENABLED = 'break_glass_enabled';
    public static function defaults(): array
    {
        return [self::SUPPORT_ASSISTED_SESSION_ENABLED => false, self::BREAK_GLASS_ENABLED => false];
    }
    public static function isEnabledByDefault(string $flag): bool
    {
        if (!array_key_exists($flag, self::defaults())) throw new \InvalidArgumentException('unknown_feature_flag');
        return false;
    }
}
