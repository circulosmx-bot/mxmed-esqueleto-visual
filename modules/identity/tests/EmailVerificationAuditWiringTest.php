<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
$entrypoint = (string)file_get_contents($root . '/api/identity/index.php');
$producer = (string)file_get_contents($root . '/modules/identity/audit/CanonicalIdentityAuditProducer.php');

function eotp02AuditAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

eotp02AuditAssert(str_contains($entrypoint, 'function identityHttpAuditVerificationSent('), 'sent audit helper present');
eotp02AuditAssert(str_contains($entrypoint, '$producer->emailVerificationSent($request,TrustedIdentityId::fromAuthoritativeOutcome($accountId),true,\'USER_REQUEST\')'), 'sent event wired from authoritative account ID');
eotp02AuditAssert(str_contains($entrypoint, 'identityHttpAuditVerificationSent($composition,$decision->accountId())'), 'registration success invokes sent event');
eotp02AuditAssert(str_contains($entrypoint, 'function identityHttpAuditEmailVerified('), 'verified audit helper present');
eotp02AuditAssert(str_contains($entrypoint, '$producer->emailVerified($request,VerifiedAccountId::fromValidatedTokenResolution($accountId),\'USER_REQUEST\')'), 'verified event wired from resolved account ID');
eotp02AuditAssert(str_contains($entrypoint, 'identityHttpAuditEmailVerified($composition,(string)$tokenRow[\'account_id\'])'), 'verification success invokes verified event');
eotp02AuditAssert(str_contains($producer, "'AUTH_EMAIL_VERIFICATION_SENT','SUCCESS'"), 'canonical sent event retained');
eotp02AuditAssert(str_contains($producer, "return \$this->event('AUTH_EMAIL_VERIFIED','SUCCESS',\$reasonCode,\$target,[],\$request,\$actor)"), 'verified event metadata empty');

$sentHelperStart = strpos($entrypoint, 'function identityHttpAuditVerificationSent(');
$sentHelperEnd = strpos($entrypoint, 'function identityHttpAuditEmailVerified(', $sentHelperStart === false ? 0 : $sentHelperStart);
$sentHelper = $sentHelperStart === false || $sentHelperEnd === false ? '' : substr($entrypoint, $sentHelperStart, $sentHelperEnd - $sentHelperStart);
$verifiedHelperStart = strpos($entrypoint, 'function identityHttpAuditEmailVerified(');
$verifiedHelperEnd = strpos($entrypoint, "\ntry {", $verifiedHelperStart === false ? 0 : $verifiedHelperStart);
$verifiedHelper = $verifiedHelperStart === false || $verifiedHelperEnd === false ? '' : substr($entrypoint, $verifiedHelperStart, $verifiedHelperEnd - $verifiedHelperStart);
foreach ([$sentHelper, $verifiedHelper] as $helper) {
    eotp02AuditAssert(!str_contains($helper, '$email') && !str_contains($helper, '$rawToken') && !str_contains($helper, '$tokenRow'), 'audit helper receives no email or token');
}
eotp02AuditAssert(str_contains($entrypoint, "identityHttpJson(['ok'=>false,'error'=>'VERIFICATION_UNAVAILABLE'], 400)"), 'failure response remains generic');

echo "EmailVerificationAuditWiringTest PASS\n";
