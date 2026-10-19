<?php
declare(strict_types=1);
// CLI-only consumer-boundary probe; the owned runtime validates root/datadir/source pins.
if(PHP_SAPI!=='cli')exit(1);
require_once __DIR__.'/ase-adm02-workflow-runtime.php';
[$identity]=staffWorkflowOwnedServices();$owned=realpath((string)getenv('ASE_INT02A_OWNED'));$p=$identity->pdo();
$tokens=json_decode(file_get_contents($owned.'/sessions.json'),true,512,JSON_THROW_ON_ERROR);
$actor=(new \Identity\Http\CanonicalHttpSessionResolver($identity->sessions()))->resolve(['__Host-mxmed_session'=>$tokens['bruno']],true);
if(!$actor)throw new RuntimeException('synthetic_actor_session_required');
$security=new \Identity\Services\GovernanceSecurityContext($identity->sessions(),$identity->authentication());
$mfa=new \Identity\Services\StaffMfaService($p,base64_encode(file_get_contents($owned.'/workflow-mfa-key')),'synthetic-workflow-mfa',security:$security);
$cipher=new \Profiles\Corrections\ReportCipher('synthetic-probe',['synthetic-probe'=>random_bytes(32)],'synthetic-owned-disposal');
$reports=new \Profiles\Corrections\ReportService($p,$cipher,$identity->csrf(),new \Profiles\Corrections\ReportRatePolicy($p,$cipher));
$out=[];
foreach(['review','apply'] as $key){
 try{
  if($key==='review')(new \Profiles\Corrections\ReportReviewService($reports,$security,$mfa))->queue($actor);
  else (new \Profiles\Corrections\ApplicationAuthority($reports,$security))->transaction($actor,static fn()=>throw new RuntimeException('unexpected_authorized_callback'));
  throw new RuntimeException('unexpected_authorization');
 }catch(\Profiles\Corrections\ReportDenied $e){if($e->status!==403)throw $e;$out[$key]='DENIED_403';}
}
$out['routine_review']=\Identity\Services\StaffRoutineOperationPolicy::allowAdvisor($p,$actor->accountId(),'PUBLIC_PROFILE_CORRECTION_REVIEW');
$out['routine_apply']=\Identity\Services\StaffRoutineOperationPolicy::allowAdvisor($p,$actor->accountId(),'PUBLIC_PROFILE_CORRECTION_APPLY');
echo json_encode($out,JSON_THROW_ON_ERROR);
