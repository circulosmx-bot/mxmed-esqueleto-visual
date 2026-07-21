<?php
declare(strict_types=1);

namespace Platform\Contracts;

final class BreakGlassContract
{
    public static function featureFlag(): string { return FeatureFlags::BREAK_GLASS_ENABLED; }
    public static function enabledByDefault(): bool { return false; }
    public static function risk(): string { return RiskLevel::R3; }
    public static function requiresEmergencyCase(): bool { return true; }
    public static function requiresScope(): bool { return true; }
    public static function requiresExpiration(): bool { return true; }
    public static function requiresMfa(): bool { return true; }
    public static function requiresPostReview(): bool { return true; }
    public static function clinicalAccessAllowedByDefault(): bool { return false; }
}
