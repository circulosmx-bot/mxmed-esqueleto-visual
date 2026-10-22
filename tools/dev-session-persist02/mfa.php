<?php
require __DIR__.'/bootstrap.php';
use Identity\Services\{GovernanceSecurityContext,StaffMfaService};
use Identity\Http\CanonicalHttpSessionResolver;
$resolver=new CanonicalHttpSessionResolver($identity->sessions());
$d=$resolver->resolve(['__Host-mxmed_session'=>$input['director']]);$a=$resolver->resolve(['__Host-mxmed_session'=>$input['advisor']]);
if(!$d||!$a||$d->accountId()!=='qa_director'||$a->accountId()!=='qa_target')throw new RuntimeException('exact_synthetic_actors_required');
$security=new GovernanceSecurityContext($identity->sessions(),$identity->authentication());
$mfa=new StaffMfaService($p,base64_encode(file_get_contents($owned.'/workflow-mfa-key')),'synthetic-workflow-mfa',security:$security);
$checks=[];
function denied(callable $fn,string $label):void {global $checks;try{$fn();}catch(RuntimeException){$checks[$label]=true;return;}throw new LogicException('Expected denial '.$label);}
$password=trim(file_get_contents($owned.'/fixture-password'));
denied(fn()=>$security->reauthenticate($d,'qa_director@example.test','invalid',[]),'wrong password denied');
$binding=$security->reauthenticate($d,'qa_director@example.test',$password,[]);$checks['canonical password reauthentication']=true;
$id='persist02_'.bin2hex(random_bytes(12));$hex=bin2hex(random_bytes(16));$uuid=substr($hex,0,8).'-'.substr($hex,8,4).'-4'.substr($hex,13,3).'-8'.substr($hex,17,3).'-'.substr($hex,20,12);
// A synthetic case fixture only; no membership, grant or privileged writer is executed.
$q=$p->prepare("INSERT INTO auth_governance_cases(case_id,operation_id,operation,requester_account_id,beneficiary_account_id,reason,status,expires_at) VALUES(?,?,'STAFF_ADMISSION_REVIEW','qa_director','qa_target','Synthetic session persistence MFA verification','OPEN',DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 10 MINUTE))");$q->execute([$id,$uuid]);
denied(fn()=>$mfa->issueChallenge('qa_target','STAFF_ADMISSION_REVIEW',$id,'qa_target','qa_target',null,$security->binding($a)),'Advisor privileged challenge denied');
denied(fn()=>$mfa->issueChallenge('qa_director','STAFF_ADMISSION_REVIEW',$id,'qa_target','qa_target',null),'missing reauthentication denied');
$ch=$mfa->issueChallenge('qa_director','STAFF_ADMISSION_REVIEW',$id,'qa_target','qa_target',null,$binding);
$verify=fn($code)=>$mfa->verifyChallenge('qa_director',(string)$d->session()->sessionId(),$d->principal()->credentialVersion(),$ch['challenge_id'],$ch['challenge'],$code);
denied(fn()=>$verify('invalid'),'invalid MFA denied');
// Enrollment consumed the current timestep. Wait for the next legitimate code.
$last=(int)$p->query("SELECT last_totp_time_step FROM auth_staff_mfa_factors WHERE account_id='qa_director' AND status='ACTIVE'")->fetchColumn();
while(intdiv(time(),30)<=$last)usleep(200000);
$proof=$verify(StaffMfaService::totp(file_get_contents($owned.'/workflow-totp-secret'),intdiv(time(),30)));$checks['current MFA verified with actual browser session']=true;
$consume=function(string $raw)use($p,$mfa,$proof,$d,$id,$uuid){$p->beginTransaction();try{$mfa->consumeProof($p,$proof['proof_id'],$raw,'qa_director',(string)$d->session()->sessionId(),$d->principal()->credentialVersion(),'STAFF_ADMISSION_REVIEW',$id,'qa_target','qa_target',null,$uuid);$p->commit();}catch(Throwable $e){$p->rollBack();throw $e;}};
denied(fn()=>$consume('invalid'),'missing valid proof denied');$consume($proof['proof']);$checks['exact bound proof consumed']=true;denied(fn()=>$consume($proof['proof']),'proof replay denied');
echo json_encode($checks);
