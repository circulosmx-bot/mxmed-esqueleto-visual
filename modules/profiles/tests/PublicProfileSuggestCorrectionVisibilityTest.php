<?php
declare(strict_types=1);

use Profiles\Services\PublicProfilePlanCapabilities;

require_once __DIR__ . '/../services/PublicProfilePlanCapabilities.php';

function pdb04eCorrectionAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function pdb04eCorrectionVisible(string $plan, bool $claimed): bool
{
    $contract = PublicProfilePlanCapabilities::build($plan, [
        'has_public_profile' => true,
        'ownership_source_ready' => true,
        'claim_source_ready' => false,
        'profile_is_administered' => $claimed,
    ]);
    return (bool)($contract['public_visibility']['show_suggest_correction'] ?? false);
}

$freeUnmanaged = PublicProfilePlanCapabilities::build('free', [
    'has_public_profile' => true,
    'ownership_source_ready' => true,
    'claim_source_ready' => false,
    'profile_is_administered' => false,
]);
pdb04eCorrectionAssert((bool)($freeUnmanaged['public_visibility']['show_suggest_correction'] ?? false), 'Gratuito unmanaged shows correction action');
pdb04eCorrectionAssert(!(bool)($freeUnmanaged['public_visibility']['show_about_action'] ?? true), 'Gratuito unmanaged hides Sobre mí');
pdb04eCorrectionAssert(!(bool)($freeUnmanaged['public_visibility']['show_claim_button'] ?? false), 'correction visibility does not activate profile claim');
pdb04eCorrectionAssert(!pdb04eCorrectionVisible('free', true), 'Gratuito administered hides correction action');
foreach (['basic', 'standard', 'optimum', 'professional'] as $paidPlan) {
    $paidContract = PublicProfilePlanCapabilities::build($paidPlan, [
        'has_public_profile' => true,
        'ownership_source_ready' => true,
        'profile_is_administered' => false,
    ]);
    pdb04eCorrectionAssert(!(bool)($paidContract['public_visibility']['show_suggest_correction'] ?? false), $paidPlan . ' hides correction action');
    pdb04eCorrectionAssert((bool)($paidContract['public_visibility']['show_about_action'] ?? false), $paidPlan . ' shows Sobre mí');
}
$unavailable = PublicProfilePlanCapabilities::build('free', [
    'has_public_profile' => true,
    'ownership_source_ready' => false,
    'claim_source_ready' => false,
    'profile_is_administered' => false,
]);
pdb04eCorrectionAssert(!(bool)($unavailable['public_visibility']['show_suggest_correction'] ?? false), 'undefined ownership authority fails closed');

$repositorySource = (string)file_get_contents(__DIR__ . '/../repositories/PublicProfileRepository.php');
$controllerSource = (string)file_get_contents(__DIR__ . '/../controllers/PublicProfileController.php');
$pageSource = (string)file_get_contents(__DIR__ . '/../../../profiles/doctor.php');

pdb04eCorrectionAssert(str_contains($repositorySource, "FROM `auth_account_memberships`") && str_contains($repositorySource, "`status` = 'active'"), 'canonical active membership authority is used');
pdb04eCorrectionAssert(str_contains($repositorySource, "`scope_code` IN ('profile', 'profile_doctor')"), 'ownership lookup is profile-scoped');
pdb04eCorrectionAssert(str_contains($controllerSource, '$membershipAuthorityReady') && str_contains($controllerSource, ': false;'), 'current unclaimed public-profile contract remains the no-membership fallback');
$profileDtoStart = strpos($controllerSource, "'profile' => [");
$profileDtoEnd = $profileDtoStart === false ? false : strpos($controllerSource, "'plan' =>", $profileDtoStart);
$profileDto = ($profileDtoStart !== false && $profileDtoEnd !== false)
    ? substr($controllerSource, $profileDtoStart, $profileDtoEnd - $profileDtoStart)
    : '';
pdb04eCorrectionAssert(!str_contains($profileDto, "'ownership_status'") && !str_contains($profileDto, "'is_claimed'"), 'ownership fields are not projected into the public profile DTO');
pdb04eCorrectionAssert(str_contains($pageSource, 'class="mxpp-action-link mxpp-action-link--summary"'), 'visible action uses the former service-summary area');
pdb04eCorrectionAssert(str_contains($pageSource, '<?php if ($showSuggestCorrection): ?>'), 'correction action has its derived visibility guard');
pdb04eCorrectionAssert(str_contains($pageSource, '<?php elseif ($bioShort !== null): ?>'), 'professional summary is replaced only for the correction state');
pdb04eCorrectionAssert(str_contains($pageSource, '<?php if ($showAboutAction): ?>'), 'Sobre mí has an explicit paid-plan visibility guard');
pdb04eCorrectionAssert(str_contains($pageSource, '<?php if ($physicianLogoUrl !== null || $showAboutAction || $showConsultaAction): ?>'), 'empty hero action row collapses when no feature is visible');
pdb04eCorrectionAssert(substr_count($pageSource, '>Sugerir corrección<') === 1, 'correction action is not duplicated');
$oldActionsStart = strpos($pageSource, '<div class="mxpp-actions-row">');
$oldActionsEnd = $oldActionsStart === false ? false : strpos($pageSource, '</div>', $oldActionsStart);
$oldActions = ($oldActionsStart !== false && $oldActionsEnd !== false)
    ? substr($pageSource, $oldActionsStart, $oldActionsEnd - $oldActionsStart)
    : '';
pdb04eCorrectionAssert(!str_contains($oldActions, 'Sugerir corrección'), 'old standalone correction placement is absent');
pdb04eCorrectionAssert(!str_contains($pageSource, '$ownershipSource[') || !str_contains($pageSource, 'account_id'), 'private membership fields are not rendered by the page');

echo "PublicProfileSuggestCorrectionVisibilityTest PASS\n";
