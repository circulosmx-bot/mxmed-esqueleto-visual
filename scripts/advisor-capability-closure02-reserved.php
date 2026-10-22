<?php
declare(strict_types=1);
$argv[1]='library';require __DIR__.'/ase-advisor-workspace02-fixture.php';
$record=json_decode(file_get_contents($owned.'/full-closure.json'),true);$actor=loginActor($record['email']);$results=[];
require_once __DIR__.'/../modules/profiles/Corrections/bootstrap.php';
$rc=new Profiles\Corrections\ReportCipher('closure-denial',['closure-denial'=>random_bytes(32)],'synthetic-denial-only');
$reports=new Profiles\Corrections\ReportService($p,$rc,$identity->csrf(),new Profiles\Corrections\ReportRatePolicy($p,$rc));
foreach(['public_profile_correction_review'=>fn()=>(new Profiles\Corrections\ReportReviewService($reports,$security,$mfa))->queue($actor),
 'public_profile_correction_apply'=>fn()=>(new Profiles\Corrections\ApplicationAuthority($reports,$security))->transaction($actor,fn()=>throw new RuntimeException('unauthorized_callback_executed'))] as $cap=>$fn){
 try{$fn();throw new RuntimeException('reserved_unexpected_allow');}catch(Profiles\Corrections\ReportDenied $e){if($e->status!==403)throw $e;$results[$cap]='DENIED_BY_CANONICAL_ROLE_AUTHORITY';}
}
$p->beginTransaction();try{(new Identity\Adapters\PdoProfileOwnershipApproverAuthorityAdapter($p))->assertAuthorized($p,$actor->accountId(),'qa_target','workspace_aurora',$actor->principal()->credentialVersion());throw new RuntimeException('reserved_unexpected_allow');}catch(RuntimeException $e){if($e->getMessage()!=='profile_ownership_approver_authority_denied')throw $e;$results['profile_initial_ownership_approve']='DENIED_BY_CANONICAL_APPROVER_AUTHORITY';}finally{$p->rollBack();}
file_put_contents($owned.'/closure-evidence/reserved.json',json_encode($results,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));echo "RESERVED_CANONICAL_DENIALS=PASS\n";
