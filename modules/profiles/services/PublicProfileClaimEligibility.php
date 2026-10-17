<?php
declare(strict_types=1);
namespace Profiles\Services;

/** Public navigation eligibility only. Never accreditation, ownership or a request writer. */
final class PublicProfileClaimEligibility
{
    public static function build(string $doctorId, array $planSource, bool $isPublic, array $ownership): array
    {
        $storedPlan = strtolower(trim((string)($planSource['plan_code'] ?? '')));
        $free = in_array($planSource['source'] ?? null, ['profiles_doctors','profile_subscriptions'], true)
            && in_array($storedPlan, ['free', 'gratuito'], true);
        $eligible = $isPublic && $free && ($ownership['source_ready'] ?? false) === true
            && ($ownership['has_active_owner'] ?? true) === false
            && preg_match('/\A[A-Za-z0-9._:-]{1,64}\z/D', $doctorId) === 1;
        return ['eligible' => $eligible, 'url' => $eligible
            ? '/public/identity/perfil-medico.php?entry=claim&profile=' . rawurlencode($doctorId) : null,
            'presentation_only' => true, 'submission_available' => false];
    }
}
