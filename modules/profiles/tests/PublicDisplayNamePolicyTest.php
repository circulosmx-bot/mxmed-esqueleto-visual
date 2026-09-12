<?php
declare(strict_types=1);

use Profiles\Services\PublicDisplayNamePolicy;

require_once __DIR__ . '/../services/PublicDisplayNamePolicy.php';

function vid02Assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$policy = new PublicDisplayNamePolicy();
$identity = [
    'given_names' => 'Luis Armando',
    'first_surname' => 'Reynoso',
    'second_surname' => 'Femat',
];

vid02Assert(
    $policy->allowedGivenNamePresentations($identity) === ['Luis', 'Armando', 'Luis Armando'],
    'ordered given-name subsets are derived deterministically'
);

foreach ([
    'Luis Reynoso',
    'Armando Reynoso',
    'Luis Armando Reynoso',
    'Luis Reynoso Femat',
    'Armando Reynoso Femat',
    'Luis Armando Reynoso Femat',
] as $allowed) {
    $decision = $policy->validate($identity, $allowed);
    vid02Assert($decision['valid'] === true, 'accepts: ' . $allowed);
    vid02Assert($decision['canonical_display_name'] === $allowed, 'preserves canonical verified spelling: ' . $allowed);
}

foreach ([
    'Fernando Reynoso',
    'Luis Ramírez',
    'Luis Femat',
    'Luis Femat Reynoso',
    'Reynoso Luis',
    'Armando Luis Reynoso',
    '',
    'Reynoso',
    'Luis',
] as $rejected) {
    vid02Assert($policy->validate($identity, $rejected)['valid'] === false, 'rejects: ' . ($rejected === '' ? '<empty>' : $rejected));
}
vid02Assert($policy->validate($identity, null)['valid'] === false, 'rejects null public display name');

$normalized = $policy->validate($identity, "\t lUiS   aRmAnDo\u{00A0}rEyNoSo   fEmAt \n");
vid02Assert($normalized['valid'] === true, 'Unicode whitespace and case normalize for comparison');
vid02Assert($normalized['canonical_display_name'] === 'Luis Armando Reynoso Femat', 'saved form uses verified spelling and spacing');

$noSecondSurname = [
    'given_names' => 'Ana Sofía',
    'first_surname' => 'Torres',
    'second_surname' => null,
];
vid02Assert($policy->validate($noSecondSurname, 'Ana Torres')['valid'] === true, 'identity without second surname accepts first surname form');
vid02Assert($policy->validate($noSecondSurname, 'Sofía Torres')['valid'] === true, 'identity without second surname accepts another ordered given-name subset');
vid02Assert($policy->validate($noSecondSurname, 'Ana Torres López')['valid'] === false, 'identity without second surname rejects invented surname');

$accented = [
    'given_names' => 'José María',
    'first_surname' => 'Núñez',
    'second_surname' => null,
];
$accentedDecision = $policy->validate($accented, 'josé núñez');
vid02Assert($accentedDecision['valid'] === true && $accentedDecision['canonical_display_name'] === 'José Núñez', 'accents are preserved with Unicode-aware case comparison');
vid02Assert($policy->validate($accented, 'Jose Núñez')['valid'] === false, 'accent removal is not treated as canonical spelling');

$punctuated = [
    'given_names' => 'Jean-Luc D’Arcy',
    'first_surname' => "O'Neill",
    'second_surname' => 'García-López',
];
$punctuatedDecision = $policy->validate($punctuated, "d’arcy o'neill garcía-lópez");
vid02Assert($punctuatedDecision['valid'] === true, 'hyphens and apostrophes are supported');
vid02Assert($punctuatedDecision['canonical_display_name'] === "D’Arcy O'Neill García-López", 'punctuated names save canonical verified spelling');

$unsafeToTokenize = [
    'given_names' => 'Ma. Elena',
    'first_surname' => 'Pérez',
    'second_surname' => null,
];
vid02Assert($policy->allowedGivenNamePresentations($unsafeToTokenize) === ['Ma. Elena'], 'unusual compound form falls back to complete given_names');
vid02Assert($policy->validate($unsafeToTokenize, 'Ma. Elena Pérez')['valid'] === true, 'safe fallback accepts complete given_names');
vid02Assert($policy->validate($unsafeToTokenize, 'Elena Pérez')['valid'] === false, 'safe fallback never permits arbitrary partial parsing');

$validDescription = $policy->describe($identity, 'Luis Reynoso');
vid02Assert($validDescription['current_display_name_policy_status'] === 'VALID', 'valid current name is reported');
vid02Assert($validDescription['first_surname_required'] === true, 'first surname requirement is explicit');
vid02Assert($validDescription['second_surname_optional'] === true, 'second surname optionality is explicit');
$nonconformingDescription = $policy->describe($identity, 'Nombre público anterior');
vid02Assert($nonconformingDescription['current_display_name_policy_status'] === 'LEGACY_NONCONFORMING', 'existing nonconforming public name is reported without rewrite');
$legacyDescription = $policy->describe(null, 'Nombre legado libre');
vid02Assert($legacyDescription['verified_identity_available'] === false, 'legacy profile remains supported');
vid02Assert($legacyDescription['current_display_name_policy_status'] === 'NOT_APPLICABLE', 'legacy profile has no verified-name policy');
vid02Assert($policy->validate(null, 'Nombre legado libre')['valid'] === true, 'legacy display-name behavior remains unrestricted by VID02');

echo "PublicDisplayNamePolicyTest PASS (ordered subsets, required first surname, optional second surname, Unicode/canonical spelling, fallback, legacy status)\n";
