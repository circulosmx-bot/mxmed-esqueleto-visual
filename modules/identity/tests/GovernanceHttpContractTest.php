<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
function checkGovHttp(bool $ok,string $label):void{if(!$ok)throw new RuntimeException('FAIL '.$label);echo "PASS $label\n";}
$root=dirname(__DIR__,3);$api=file_get_contents($root.'/api/identity/governance.php');$composition=file_get_contents(__DIR__.'/../http/GovernanceHttpComposition.php');$gate=file_get_contents(__DIR__.'/../http/GovernanceHttpSecurity.php');$mfa=file_get_contents(__DIR__.'/../services/StaffMfaService.php');$cli=file_get_contents($root.'/scripts/governance-ceremony.php');
checkGovHttp(str_contains($api,"REQUEST_METHOD']??''))!=='POST'")&&str_contains($api,'governanceHttps($raw)'),'POST and authenticated TLS transport');
checkGovHttp(str_contains($gate,'HTTP_ORIGIN')&&str_contains($gate,'HTTP_REFERER')&&str_contains($gate,'$origin!==$allowed'),'exact configured origin');
checkGovHttp(str_contains($gate,'validAuthenticated')&&str_contains($gate,'CanonicalHttpSessionResolver'),'canonical session-bound CSRF');
checkGovHttp(str_contains($composition,'GovernanceDeploymentAuthorization::require')&&str_contains($composition,'assertSchema'),'private pinned authorization and schema prerequisite');
checkGovHttp(str_contains($composition,'Ed25519ExternalTrustAnchorAdapter')&&str_contains($composition,'GovernanceStaffMfaCeremony'),'canonical trust and enrollment services');
checkGovHttp(!str_contains($api,'beginEnrollment'),'no HTTP enrollment writer');
checkGovHttp(str_contains($api,"'bootstrap/'")&&!str_contains($api,'->bootstrap->execute'),'bootstrap is CLI only');
checkGovHttp(str_contains($cli,'MXMED_GOVERNANCE_CEREMONY_ENABLED')&&str_contains($cli,'secret-output'),'disabled private operational ceremony');
checkGovHttp(str_contains($mfa,'reauthentication_context')&&str_contains($mfa,'assertChallengeContext'),'action-bound canonical reauthentication');
echo "IDENTITY_GOV03B_HTTP_CONTRACT=PASS\n";
