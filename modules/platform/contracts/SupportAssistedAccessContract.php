<?php
declare(strict_types=1);

namespace Platform\Contracts;

final class SupportAssistedAccessContract
{
    public static function featureFlag(): string { return FeatureFlags::SUPPORT_ASSISTED_SESSION_ENABLED; }
    public static function enabledByDefault(): bool { return false; }
    public static function minimumRisk(): string { return RiskLevel::R2; }
    public static function clinicalAccessAllowedByDefault(): bool { return false; }
    public static function requiresCase(): bool { return true; }
    public static function requiresScope(): bool { return true; }
    public static function requiresExpiration(): bool { return true; }
    public static function requiresAudit(): bool { return true; }
}
