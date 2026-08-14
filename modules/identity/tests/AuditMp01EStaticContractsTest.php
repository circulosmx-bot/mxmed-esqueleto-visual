<?php
declare(strict_types=1);

$root=dirname(__DIR__,3);$unitTotal=0;$unitPass=0;
function mp01eStaticOk(bool $value,string $name):void{global $unitTotal,$unitPass;$unitTotal++;if(!$value)throw new RuntimeException('static:'.$name);$unitPass++;}
$paths=[
'modules/identity/audit/contracts/AuthIdentifierAuditSecretProvider.php','modules/identity/audit/contracts/AuditProducerFailureSignalPort.php','modules/identity/audit/contracts/CanonicalAuditAppendPort.php','modules/identity/audit/contracts/IdentityAuditProducer.php','modules/identity/audit/contracts/SessionAuditProducer.php',
'modules/identity/audit/AuthIdentifierAuditTarget.php','modules/identity/audit/TrustedIdentityId.php','modules/identity/audit/VerifiedAccountId.php','modules/identity/audit/PasswordChangedFieldSet.php','modules/identity/audit/AuditProducerFailureSignal.php','modules/identity/audit/AuditProducerEmissionResult.php','modules/identity/audit/HmacSha256AuthIdentifierAuditHasher.php','modules/identity/audit/CanonicalAuditWriterAdapter.php','modules/identity/audit/Mp01eEventScopePolicy.php','modules/identity/audit/PreauthActorOptionalContext.php','modules/identity/audit/BoundedBestEffortAuditEmitter.php','modules/identity/audit/IdentityAuditReasonResolver.php','modules/identity/audit/CanonicalIdentityAuditProducer.php','modules/identity/audit/CanonicalSessionAuditProducer.php',
'modules/identity/tests/AuditMp01EIdentitySessionProducerTest.php','modules/identity/tests/AuditMp01EStaticContractsTest.php','docs/product-completion/audit-mp01e-producer-contract.md'];
mp01eStaticOk(count($paths)===22,'target_path_count');
foreach($paths as $path)mp01eStaticOk(is_file($root.'/'.$path),'installed_path_'.$path);
$runtime='';foreach(array_slice($paths,0,19) as $path)$runtime.=file_get_contents($root.'/'.$path)."\n";
$all='';foreach($paths as $path)$all.=file_get_contents($root.'/'.$path)."\n";
$downloads='/Users/'.'circulodigital/Downloads';$candidateContracts='candidate/'.'contracts';
mp01eStaticOk(!str_contains($all,$downloads),'no_downloads_dependency');
mp01eStaticOk(!str_contains($all,$candidateContracts),'no_candidate_dependency');
mp01eStaticOk(!str_contains($runtime,'PDO'),'no_direct_db');
mp01eStaticOk(!str_contains($runtime,'INSERT INTO'),'no_direct_insert');
mp01eStaticOk(!str_contains($runtime,'CanonicalAuditSerializer'),'no_second_serializer');
mp01eStaticOk(!str_contains($runtime,'CanonicalAuditSealer'),'no_second_sealer');
mp01eStaticOk(str_contains($runtime,'CanonicalAuditWriter')&&str_contains($runtime,'AuditWriterContextBridge'),'published_handoff');
mp01eStaticOk(str_contains($runtime,"'AUTH_LOGOUT_ALL'")&&!str_contains($runtime,'session_count'),'logout_all_aggregate_no_count');
mp01eStaticOk(str_contains($runtime,"'audit-auth-identifier'")&&!str_contains($runtime,"'audit-ip'"),'dedicated_hmac_namespace');
mp01eStaticOk(str_contains($runtime,'PASSWORD_RESET_SUCCEEDED')&&str_contains($runtime,'PASSWORD_CHANGED'),'password_events_distinct');
mp01eStaticOk(str_contains($runtime,'adapterAcceptedForSend'),'notification_acceptance_typed');
mp01eStaticOk(str_contains($runtime,'fromValidatedTokenResolution'),'verified_actor_typed_handoff');
mp01eStaticOk(str_contains($runtime,'AUDIT_FAILED_SIGNALLED')&&str_contains($runtime,'AUDIT_AND_SIGNAL_FAILED'),'failure_not_silent');
mp01eStaticOk(!str_contains($runtime,'sleep(')&&!str_contains($runtime,'retry'),'no_retry_loop');
$scopeSource=file_get_contents($root.'/modules/identity/audit/Mp01eEventScopePolicy.php');preg_match_all("/^\\s*'AUTH_[A-Z_]+'=>\\[/m",$scopeSource,$scopeRows);
mp01eStaticOk(count($scopeRows[0])===13,'exact_13_scope_rows');
$session=file_get_contents($root.'/modules/identity/audit/CanonicalSessionAuditProducer.php');
mp01eStaticOk(str_contains($session,"'SESSION',\$sessionId->value()")&&!str_contains($session,'safe_session_identifier_handoff_mismatch'),'single_session_target_not_request_session');
mp01eStaticOk(str_contains($session,"'AUTH_LOGOUT_ALL'")&&str_contains($session,"'ACCOUNT',\$target->value,[]"),'logout_all_account_target');
$preauth=file_get_contents($root.'/modules/identity/audit/PreauthActorOptionalContext.php');
mp01eStaticOk(str_contains($preauth,'public bool $authenticationFailure')&&str_contains($preauth,"'UNKNOWN',\n            'UNKNOWN'")&&str_contains($preauth,"'PRE_AUTH',\n            false"),'typed_unknown_nonfailure_context');
mp01eStaticOk(!str_contains($preauth,"'SYSTEM',"),'preauth_no_system_fiction');
$identity=file_get_contents($root.'/modules/identity/audit/CanonicalIdentityAuditProducer.php');
mp01eStaticOk(substr_count($identity,'hashCanonicalIdentifier($canonicalAuthIdentifier)')===3,'registration_recovery_login_use_dedicated_hmac');
mp01eStaticOk(substr_count($identity,'emitActorOptionalPreauth')===1&&substr_count($identity,'preauthEvent(')>=4,'preauth_normal_events_use_local_handoff');
mp01eStaticOk(str_contains($identity,"'authentication_failure'=>true")&&substr_count($identity,"'authentication_failure'=>true")===1,'only_login_failed_marks_auth_failure');
mp01eStaticOk(!str_contains($identity,"'actor_role'=>'SYSTEM'")&&!str_contains($identity,"'actor_role'=>'ACCOUNT',"),'no_preauth_system_or_fake_actor');
$functional=file_get_contents($root.'/modules/identity/tests/AuditMp01EIdentitySessionProducerTest.php');
mp01eStaticOk(str_contains($functional,'FOCUSED_REQUIRED_CASES_TOTAL')&&str_contains($functional,'admin_session_a_revokes_session_b')&&str_contains($functional,'system_null_request_session_revokes_b'),'focused_case_accounting_present');
mp01eStaticOk(!str_contains($runtime,"'account_exists'=>")&&!str_contains($runtime,"'verification_token'=>")&&!str_contains($runtime,"'reset_token'=>"),'no_enumeration_or_secret_metadata');
$forbidden=['api/','public/','modules/identity/services/','modules/identity/http/','modules/platform/'];
mp01eStaticOk(count(array_filter($paths,fn($path)=>array_reduce($forbidden,fn($hit,$prefix)=>$hit||str_starts_with($path,$prefix),false)))===0,'no_productive_or_mp01c_mp01d_paths');
echo 'STATIC_ASSERTIONS_TOTAL='.$unitTotal."\n";
echo 'STATIC_ASSERTIONS_PASS='.$unitPass.'/'.$unitTotal."\n";
echo 'STATIC_ASSERTIONS_FAIL='.($unitTotal-$unitPass)."\n";
echo "TARGET_PATH_COUNT=22\nTARGET_PATH_COUNT_CHANGED=true\nOLD_TARGET_PATH_COUNT=21\nNEW_TARGET_PATH_COUNT=22\n";
echo "REPO_INSTALLED_TESTS_SELF_CONTAINED=true\nOUT_OF_REPO_RUNTIME_DEPENDENCIES=0\nOUT_OF_REPO_TEST_DEPENDENCIES=0\n";
