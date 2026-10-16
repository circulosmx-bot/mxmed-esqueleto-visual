<?php
declare(strict_types=1);
/** Opt-in: owned schema-only clone + synthetic rows. Never targets a persistent QA or mxmed. */
require_once __DIR__.'/../http/IdentityHttpComposition.php';
Identity\Http\IdentityHttpComposition::registerAutoloader();
use Identity\Http\IdentityHttpComposition;
use Identity\Operators\{OperatorRuntimeComposition,OperatorInvitationDispatcher,OperatorInvitationTransport,OperatorDeliveryFailure,OperatorDeploymentPackage,OperatorDeploymentSchema,OperatorInvitationOrigin};
use Identity\Contracts\{Clock,NotificationMessage,OneTimeTokenPurpose};
use Platform\Repositories\CanonicalAuditDatabaseTarget;
if(PHP_SAPI!=='cli'||getenv('MXMED_OPERATOR_DEPLOYMENT_REHEARSAL')!=='1')throw new RuntimeException('owned_rehearsal_required');
$db=(string)getenv('MXMED_DB_NAME');if(!preg_match('/^mxmed_operator_deploy_([a-f0-9]{10})$/D',$db,$match)||getenv('MXMED_DB_USER')!=='mxmed_act01_'.$match[1])throw new RuntimeException('owned_credentials_required');
if(($argv[1]??'')==='--race-worker'){
 try{
  $task=json_decode(stream_get_contents(STDIN),true,32,JSON_THROW_ON_ERROR);$identity=IdentityHttpComposition::fromProcessEnvironment();$runtime=OperatorRuntimeComposition::fromIdentity($identity);
  $creds=json_decode(file_get_contents(getenv('HOME').'/Library/Application Support/MXMED/op-act01-rehearsal/credentials.json'),true,32,JSON_THROW_ON_ERROR)['other-owner'];$decision=$identity->authentication()->authenticate($creds['email'],$creds['password'],['ip'=>'127.0.0.1']);if(!$decision->isAllowed())throw new RuntimeException('synthetic_authentication_failed');$session=$identity->sessions()->create($decision->candidate());if(!$session->allowed())throw new RuntimeException('synthetic_session_failed');
  echo "READY\n";flush();$runtime->http->operators->command($session->token()->value(),$identity->csrf()->issueAuthenticated($session->record()->tokenDigest()),$identity->allowedOrigin(),$task['profile'],'race_'.bin2hex(random_bytes(12)),'invite',['email'=>$task['email'],'capabilities'=>['agenda.appointments.read'],'reason'=>'OWNER_REQUEST']);echo "ACCEPTED\n";exit;
 }catch(Throwable $e){echo $e->getMessage()==='operator_quota_exceeded'?"QUOTA_DENIED\n":"DENIED\n";exit(2);}
}
function check(bool $ok,string $label):void{if(!$ok)throw new RuntimeException('FAIL '.$label);echo $label."=PASS\n";}
function denied(callable $f,string $label):void{try{$f();}catch(Throwable){check(true,$label);return;}throw new RuntimeException('FAIL '.$label);}
$identity=IdentityHttpComposition::fromProcessEnvironment();$p=$identity->pdo();$runtime=OperatorRuntimeComposition::fromIdentity($identity);$service=$runtime->http->operators;
check(str_starts_with((string)$p->query('SELECT CURRENT_USER()')->fetchColumn(),'mxmed_act01_'),'restricted_runtime_user');
foreach(['SELECT COUNT(*) FROM mxmed.auth_accounts','INSERT INTO mxmed.platform_audit_events SELECT * FROM mxmed.platform_audit_events WHERE 0','CALL mxmed.audit_mp01c_lock_stream_head_v1(NULL)'] as $n=>$sql){
 try{$p->exec($sql);throw new RuntimeException('isolation_privilege_unexpected');}catch(PDOException $e){check(in_array((int)$e->errorInfo[1],[1142,1370],true),'live_privilege_denied_'.$n);}
}
$target=CanonicalAuditDatabaseTarget::fromConnection($p);foreach(['audit_mp01c_lock_stream_head_v1','audit_mp01c_advance_stream_head_cas_v1'] as $name)check(str_starts_with($target->routine($p,$name),'`'.$db.'`.'),'native_owned_'.$name);
$support=getenv('HOME').'/Library/Application Support/MXMED/op-act01-rehearsal';$creds=json_decode(file_get_contents($support.'/credentials.json'),true,512,JSON_THROW_ON_ERROR);
$d=$identity->authentication()->authenticate($creds['other-owner']['email'],$creds['other-owner']['password'],['ip'=>'127.0.0.1']);check($d->isAllowed(),'canonical_worker_test_authentication');$session=$identity->sessions()->create($d->candidate());check($session->allowed(),'canonical_worker_test_session');$token=$session->token()->value();$csrf=$identity->csrf()->issueAuthenticated($session->record()->tokenDigest());
$cmd=function(string $profile,string $action,array $input=[])use($service,$identity,$token,$csrf):array{return $service->command($token,$csrf,$identity->allowedOrigin(),$profile,'act01_'.bin2hex(random_bytes(12)),$action,$input+['reason'=>'OWNER_REQUEST']);};
$fresh=function(string $kind)use($p):string{
 $id='act01_'.$kind.'_'.bin2hex(random_bytes(5));$p->prepare('INSERT INTO profiles_doctors(doctor_id,display_name) VALUES(?,?)')->execute([$id,'Synthetic deployment test']);
 $p->prepare("INSERT INTO auth_account_memberships(membership_id,account_id,profile_doctor_id,role_code,scope_code,status,assignment_source) VALUES(?,'op02-other-owner',?,'owner','profile','active','op02_synthetic_fixture')")->execute(['owner_'.$id,$id]);
 $p->prepare("INSERT INTO profile_subscriptions(subscription_id,entity_type,entity_id,doctor_id,plan_code,contracted_plan_code,effective_plan_code,plan_label,status,starts_at,expires_at) VALUES(UUID(),'doctor',?,?,'standard','standard','standard','Synthetic Standard','active',DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 DAY),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 30 DAY))")->execute([$id,$id]);return $id;
};
$invite=fn(string $profile)=>$cmd($profile,'invite',['email'=>'op02-operador-a@example.invalid','alias'=>'Prueba','capabilities'=>['agenda.appointments.read']]);
$admin=new PDO('mysql:host=127.0.0.1;dbname='.$db.';charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]); // Owned DDL fault injection only.
$counts=fn()=>json_encode(array_map(fn($table)=>$p->query('SELECT COUNT(*) FROM `'.$table.'`')->fetchColumn(),['auth_profile_operator_invitations','auth_profile_operator_commands','auth_profile_operator_presentations','auth_identity_notification_deliveries','platform_audit_events','platform_audit_stream_heads']));
foreach(['platform_audit_events'=>'audit','auth_identity_notification_deliveries'=>'enqueue'] as $table=>$name){
 $profile=$fresh($name);$before=$counts();$admin->exec("CREATE TRIGGER act01_fault BEFORE INSERT ON `$table` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic_fault'");
 try{denied(fn()=>$invite($profile),$name.'_failure_denied');check($counts()===$before,$name.'_failure_entire_transaction_rolled_back');}finally{$admin->exec('DROP TRIGGER act01_fault');}
}
// Signature and body drift are rejected by the SAME adapter before any CALL.
$name='audit_mp01c_lock_stream_head_v1';$saved=$admin->query('SHOW CREATE PROCEDURE `'.$name.'`')->fetch(PDO::FETCH_NUM)[2];
try{
 $admin->exec('DROP PROCEDURE `'.$name.'`');$admin->exec('CREATE PROCEDURE `'.$name.'`(IN p_stream_key VARCHAR(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci) READS SQL DATA SQL SECURITY DEFINER '.substr($saved,strpos($saved,'BEGIN')));
 denied(fn()=>$target->routine($p,$name),'audit_signature_drift_denied');
 $admin->exec('DROP PROCEDURE `'.$name.'`');$admin->exec('CREATE PROCEDURE `'.$name.'`(IN p_stream_key VARCHAR(191)) BEGIN SELECT 1; END');denied(fn()=>$target->routine($p,$name),'audit_body_drift_denied');
}finally{$admin->exec('DROP PROCEDURE IF EXISTS `'.$name.'`');$admin->exec($saved);}
check($target->routine($p,$name)==='`'.$db.'`.`'.$name.'`','audit_exact_restore');
final class DeploymentClock implements Clock{public function __construct(public DateTimeImmutable $time){}public function now():DateTimeImmutable{return $this->time;}}
final class DeploymentTransport implements OperatorInvitationTransport{
 public int $calls=0;public string $outcome='accepted';public function __construct(private PDO $p){}
 public function deliver(NotificationMessage $m):string{$this->calls++;if($this->p->inTransaction())throw new LogicException('transport_in_transaction');if($m->purpose()!==OneTimeTokenPurpose::OPERATOR_INVITATION)throw new LogicException('purpose');if($this->outcome!=='accepted')throw new OperatorDeliveryFailure($this->outcome);return 'synthetic-provider-reference';}
}
$clock=new DeploymentClock(new DateTimeImmutable('+1 minute',new DateTimeZone('UTC')));$transport=new DeploymentTransport($p);$worker=new OperatorInvitationDispatcher($p,$transport,$runtime->configuration->deliveryKey(),$runtime->configuration->deliveryKeyId(),$clock);
// Any prior browser-review offer stays valid; a mock dispatch changes delivery metadata only.
while($worker->dispatchOne()['state']!=='idle'){}
check($transport->calls<=2,'existing_synthetic_offer_mock_only');
$profile=$fresh('aftercommit');$i=$invite($profile);$p->beginTransaction();try{denied(fn()=>$worker->dispatchOne(),'dispatch_inside_transaction_denied');}finally{$p->rollBack();}
check($worker->dispatchOne()['state']==='provider_accepted','dispatch_after_commit_provider_receipt');$calls=$transport->calls;check($worker->dispatchOne()['state']==='idle'&&$transport->calls===$calls,'provider_accepted_not_resent');
$profile=$fresh('cancel');$i=$invite($profile);$cmd($profile,'cancel',['invitation_id'=>$i['invitation_id']]);check($worker->dispatchOne()['state']==='idle'&&$transport->calls===$calls,'cancel_erases_queue_and_prevents_send');
$profile=$fresh('retry');$i=$invite($profile);$transport->outcome='retryable_not_accepted';
for($n=1;$n<=5;$n++){check($worker->dispatchOne()['state']===($n===5?'failed':'retry'),'bounded_retry_'.$n);$clock->time=$clock->time->modify('+1 hour');}
$calls=$transport->calls;check($worker->dispatchOne()['state']==='idle'&&$transport->calls===$calls,'retry_limit_no_resend');
$transport->outcome='uncertain';$profile=$fresh('uncertain');$i=$invite($profile);check($worker->dispatchOne()['state']==='uncertain','ambiguous_outcome_no_success');$calls=$transport->calls;$clock->time=$clock->time->modify('+1 hour');check($worker->dispatchOne()['state']==='idle'&&$calls===$transport->calls,'ambiguous_outcome_no_automatic_retry');
$transport->outcome='accepted';$profile=$fresh('lease');$i=$invite($profile);$p->prepare("UPDATE auth_identity_notification_deliveries SET state='dispatching',attempt_count=1,lease_token='synthetic-lease',lease_until=UTC_TIMESTAMP()-INTERVAL 1 MINUTE WHERE invitation_id=?")->execute([$i['invitation_id']]);check($worker->dispatchOne()['state']==='uncertain'&&$calls===$transport->calls,'crashed_lease_never_resent');
$profile=$fresh('expired');$i=$invite($profile);$clock->time=$clock->time->modify('+8 days');check($worker->dispatchOne()['state']==='retired'&&$calls===$transport->calls,'expired_offer_not_delivered');
check((int)$p->query("SELECT COUNT(*) FROM auth_identity_notification_deliveries WHERE state IN ('retired','failed','uncertain','provider_accepted') AND ciphertext IS NOT NULL")->fetchColumn()===0,'terminal_ciphertext_erased');
foreach(['https://localhost:8443','https://127.0.0.1','http://mexicomedico.com','https://host.local','https://localhost.','https://localhost.localdomain','https://127.1','https://bad_host.example.com','https://host.example.invalid','https://user@example.com','https://example.com/a'] as $origin)denied(fn()=>OperatorInvitationOrigin::assertPublic($origin),'private_or_invalid_invitation_origin_denied');
$fake=new class {public array $requests=[];public mixed $result=['MessageId'=>'mock-ses-id'];public function sendEmail(array $r):mixed{$this->requests[]=$r;return $this->result;}};
$ses=new Identity\Adapters\SesIdentityNotificationAdapter($fake,'us-east-1','no-reply@mexicomedico.com','México Médico','https://review.mexicomedico.com');$message=new NotificationMessage(OneTimeTokenPurpose::OPERATOR_INVITATION,'operator@example.invalid','synthetic-id.synthetic-token','2099-01-01 00:00:00.000000');check($ses->sendWithReceipt($message)==='mock-ses-id','ses_provider_message_id_required');$text=$fake->requests[0]['Content']['Simple']['Body']['Text']['Data'];check(str_contains($text,'https://review.mexicomedico.com/public/identity/acceso.php#operator-invitation=')&&!str_contains($text,'?token=')&&str_contains($text,'siete días'),'ses_public_fragment_seven_day_link');$fake->result=[];
try{$ses->sendWithReceipt($message);throw new LogicException('missing_receipt_allowed');}catch(OperatorDeliveryFailure $e){check($e->outcome==='uncertain','ses_missing_receipt_uncertain');}
// Populated recovery must fail before a DROP or historical mutation.
$package=new OperatorDeploymentPackage($admin);$before=OperatorDeploymentSchema::fingerprint($admin);$data=$counts();denied(fn()=>$package->recoverEmpty($package->inspect()+['authorization_reference'=>'OP-ACT01-SYNTHETIC-RECOVERY']),'populated_recovery_denied');check($before===OperatorDeploymentSchema::fingerprint($admin)&&$counts()===$data,'populated_recovery_no_changes');
$profile=$fresh('race');$workers=[];$p->beginTransaction();$p->prepare('SELECT doctor_id FROM profiles_doctors WHERE doctor_id=? FOR UPDATE')->execute([$profile]);
try{
 foreach(['a','b','c'] as $suffix){$process=proc_open([PHP_BINARY,__FILE__,'--race-worker'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);if(!is_resource($process))throw new RuntimeException('worker_failed');fwrite($pipes[0],json_encode(['profile'=>$profile,'email'=>'op02-operador-'.$suffix.'@example.invalid'],JSON_THROW_ON_ERROR));fclose($pipes[0]);$workers[]=[$process,$pipes];}
 foreach($workers as [$process,$pipes]){stream_set_timeout($pipes[1],20);if(trim((string)fgets($pipes[1]))!=='READY')throw new RuntimeException('worker_not_ready');}
 $p->commit();$outcomes=[];foreach($workers as [$process,$pipes]){$outcomes[]=trim(stream_get_contents($pipes[1]));$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);proc_close($process);if($error!=='')throw new RuntimeException('worker_error');}
 check(count(array_filter($outcomes,fn($v)=>$v==='ACCEPTED'))===2&&count(array_filter($outcomes,fn($v)=>$v==='QUOTA_DENIED'))===1,'native_audit_three_process_two_seat_atomic_limit');
}finally{if($p->inTransaction())$p->rollBack();foreach($workers as [$process,$pipes])if(is_resource($process))proc_terminate($process);}
echo "OP_ACT01_DEPLOYMENT_DELIVERY_AUDIT_INTEGRATION=PASS\n";
