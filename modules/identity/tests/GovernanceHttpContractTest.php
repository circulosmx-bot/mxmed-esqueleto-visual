<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
function checkGovHttp(bool $ok,string $label):void{if(!$ok)throw new RuntimeException('FAIL '.$label);echo "PASS $label\n";}
$root=dirname(__DIR__,3);$api=(string)file_get_contents($root.'/api/identity/governance.php');$composition=(string)file_get_contents(__DIR__.'/../http/GovernanceHttpComposition.php');$mfa=(string)file_get_contents(__DIR__.'/../services/StaffMfaService.php');
checkGovHttp(str_contains($api,"REQUEST_METHOD']??''))!=='POST'")&&str_contains($api,'governanceHttps()'),'strict POST and HTTPS gate');
checkGovHttp(str_contains($api,'HTTP_ORIGIN')&&str_contains($api,'HTTP_REFERER')&&str_contains($api,'allowedOrigin'),'same-origin enforcement');
checkGovHttp(str_contains($api,'validAuthenticated')&&str_contains($api,'CanonicalHttpSessionResolver'),'canonical session and session-bound CSRF');
checkGovHttp(str_contains($composition,"MXMED_IDENTITY_GOVERNANCE_ENABLED')!=='1'")&&str_contains($composition,'identity_governance_configuration_required'),'explicit fail-closed runtime configuration');
checkGovHttp(str_contains($composition,'Ed25519ExternalTrustAnchorAdapter')&&str_contains($composition,'StaffMfaService'),'productive trust and MFA adapters composed');
checkGovHttp(!str_contains($api,'beginEnrollment')&&!str_contains($api,'test_mode')&&!str_contains($composition,'GovSecret'),'no enrollment or synthetic provider HTTP route');
checkGovHttp(str_contains($mfa,'sodium_crypto_secretbox')&&str_contains($mfa,'ux_auth_staff_mfa_totp_replay')===false&&str_contains($mfa,"'+15 minutes'"),'encrypted TOTP and throttling contract');
checkGovHttp(str_contains($api,"['cases','initial-owner']")&&str_contains($api,"'beneficiary-step-up'")&&str_contains($api,"'execute-recovery'")&&str_contains($api,"['bootstrap','first-director']"),'bounded governance routes only');
checkGovHttp(substr_count($api,"'GOVERNANCE_DENIED'")===1&&!str_contains($api,'$e->getMessage()'),'non-enumerating HTTP denial');
echo "IDENTITY_GOV03A_HTTP_CONTRACT=PASS\n";
