<?php
declare(strict_types=1);
// CLI only; private credentials/proofs are retained exclusively in the owned root.
if(PHP_SAPI!=='cli')exit(1);
$securityAction=$argv[1]??'';$argv[1]='library';
require __DIR__.'/ase-advisor-workspace02-fixture.php';
// Request operations use the exact HTTP composition: Identity/session checks and
// mutation share ase_writer PDO. The fixture PDO remains setup/inspection only.
require_once __DIR__.'/ase-advisor-workspace02-runtime.php';
$requests=workspace02OwnedServices()['requests'];
$securityFile=$owned.'/workspace02-security-actor.json';
function secActorFile(string $account):string{
 global $owned;if(!in_array($account,['qa_master2','qa_master'],true))throw new RuntimeException('security_reviewer_invalid');
 return $owned.'/workspace02-security-'.($account==='qa_master2'?'actor':'secondary').'.json';
}
function secActor(string $account='qa_master2'):Identity\Contracts\AuthenticatedAccessContext{
 global $identity,$owned;$path=secActorFile($account);
 if(is_file($path)){
  $v=json_decode(file_get_contents($path),true,32,JSON_THROW_ON_ERROR);$d=$identity->sessions()->validate($v['token']);
  if($d->allowed()&&$d->context()->accountId()===$account)return $d->context();
 }
 $d=$identity->authentication()->authenticate($account.'@example.test',trim(file_get_contents($owned.'/fixture-password')),['identity'=>$account.'@example.test']);
 if(!$d->isAllowed())throw new RuntimeException('security_actor_login_failed');$session=$identity->sessions()->create($d->candidate());
 if(!$session->allowed())throw new RuntimeException('security_actor_session_failed');
 file_put_contents($path,json_encode(['token'=>$session->token()->value()],JSON_THROW_ON_ERROR));chmod($path,0600);
 return $identity->sessions()->validate($session->token()->value())->context();
}
function secDirectorCookies():array{
 global $identity,$owned;
 $path=$owned.'/workspace02-security-director.json';
 if(is_file($path)){
  $saved=json_decode(file_get_contents($path),true,32,JSON_THROW_ON_ERROR);$valid=$identity->sessions()->validate($saved['token']);
  if($valid->allowed()&&$valid->context()->accountId()==='qa_director')return ['__Host-mxmed_session'=>$saved['token']];
 }
 $d=$identity->authentication()->authenticate('qa_director@example.test',trim(file_get_contents($owned.'/fixture-password')),['identity'=>'qa_director@example.test']);
 if(!$d->isAllowed())throw new RuntimeException('security_director_login_failed');$s=$identity->sessions()->create($d->candidate());
 if(!$s->allowed())throw new RuntimeException('security_director_session_failed');
 file_put_contents($path,json_encode(['token'=>$s->token()->value()],JSON_THROW_ON_ERROR));chmod($path,0600);
 return ['__Host-mxmed_session'=>$s->token()->value()];
}
function secGrant(string $action,string $capability,string $account='qa_master2'):void{
 global $p,$identity;
 $q=$p->prepare("SELECT grant_id FROM internal_operator_grants WHERE account_id=? AND capability=? AND status='ACTIVE' AND revoked_at IS NULL");$q->execute([$account,$capability]);$active=$q->fetchColumn()!==false;
 if(($action==='grant'&&$active)||($action==='revoke'&&!$active))return;
 (new Identity\Services\InternalGovernanceService($p,new Identity\Http\CanonicalHttpSessionResolver($identity->sessions())))->execute(secDirectorCookies(),$action,$account,$capability);
}
function secFixture(string $key,bool $claim=false,?string $declaredLicense=null):array{
 global $records,$p,$a,$enrollment,$composition,$owned,$requests;
 if(isset($records[$key]['evidence_request_id']))return $records[$key];
 $rec=$records[$key]??account($key);$doctor=$claim?'workspace02_'.$key:null;$rec+=['doctor_id'=>$doctor,'name'=>'Persona sintética '.$key,'key'=>$key];$records[$key]=$rec;persist();
 if($claim){
  $q=$p->prepare('SELECT doctor_id FROM profiles_doctors WHERE doctor_id=?');$q->execute([$doctor]);
  if(!$q->fetchColumn()){
   require_once __DIR__.'/../modules/profiles/repositories/PrivateProfileRepository.php';
   (new Profiles\Repositories\PrivateProfileRepository($p))->upsertIdentity($doctor,['display_name'=>$rec['name']]);
   $p->prepare("UPDATE profiles_doctors SET profile_status='active',is_public_candidate=1,plan_code='free',preloaded_free_plan='free',preloaded_free_reference=?,preloaded_free_recorded_at=CURRENT_TIMESTAMP(6),professional_license=?,specialty_primary='Medicina general' WHERE doctor_id=?")->execute(['workspace02-synthetic-origin-'.$key,'SYNTHETIC-WS02-'.strtoupper($key),$doctor]);
   $p->prepare('INSERT INTO consultorios(doctor_id,consultorio_id,titulo,municipio,estado) VALUES(?,?,?,?,?)')->execute([$doctor,$doctor.'_office','Consultorio ficticio','Ciudad Sintética','Estado Ficticio']);
  }
  $refs=sources($doctor,$key);
 }
 $applicant=loginActor($rec['email']);
 if(!isset($rec['application_id'])){
  $browser=Identity\Accreditation\PublicEnrollmentService::opaque();$type=$claim?'CLAIM_EXISTING_PROFILE':'REQUEST_NEW_PROFILE';
  $ticket=$enrollment->ticket($type,$doctor,'/profiles/listing.php',$browser);$origin=$enrollment->start($ticket,$browser);
  $enrollment->bind($applicant,$origin['application_id'],$origin['handle'],$browser);
  $enrollment->save($applicant,$origin['application_id'],0,$a->uuid(),['given_names'=>'Persona Ficticia','first_surname'=>ucfirst($key),'second_surname'=>'','professional_license'=>$declaredLicense??'SYNTHETIC-WS02-'.strtoupper($key),'specialty'=>'Medicina general','state'=>'Estado Ficticio','city'=>'Ciudad Sintética'],true);
  $rec['application_id']=$origin['application_id'];$records[$key]=$rec;persist();
 }
 verifyPhone($applicant,$rec['email']);
 $e=$claim?$composition->evidence->create($applicant,'CLAIM_EXISTING_PROFILE',$doctor,...$refs):$composition->evidence->create($applicant,'REQUEST_NEW_PROFILE');
 foreach(['OFFICIAL_ID','PROFESSIONAL_CREDENTIAL'] as $kind){$f=$composition->evidence->addDocument($applicant,$e['request_id'],$e['version'],$kind,file_get_contents($owned.'/synthetic.png'));$e['version']=$f['version'];}
 $e=$composition->evidence->submit($applicant,$e['request_id'],$e['version']);$rec['evidence_request_id']=$e['request_id'];$rec['evidence_version']=$e['version'];$records[$key]=$rec;persist();
 $requests->linkEvidence($applicant,$rec['application_id'],$e['request_id']);return $rec;
}
function jobPath(string $key):string{
 global $owned;if(!preg_match('/^[a-z0-9_]{3,40}$/D',$key))throw new RuntimeException('security_job_invalid');return $owned.'/workspace02-security-job-'.$key.'.json';
}
function summary(string $origin):array{
 global $p;
 $q=$p->prepare('SELECT state,profile_doctor_id FROM auth_physician_profile_request_decisions WHERE origin_id=? ORDER BY resolution_version');$q->execute([$origin]);$decisions=$q->fetchAll(PDO::FETCH_ASSOC);
 $q=$p->prepare('SELECT COUNT(*) FROM auth_physician_profile_request_profiles WHERE origin_id=?');$q->execute([$origin]);$links=(int)$q->fetchColumn();
 $q=$p->prepare("SELECT action,JSON_UNQUOTE(JSON_EXTRACT(metadata_json,'$.producer_metadata.operation')) operation FROM platform_audit_events WHERE resource_reference=? AND outcome='SUCCESS' AND action='PHYSICIAN_PROFILE_REQUEST_CHANGED'");$q->execute([$origin]);$events=$q->fetchAll(PDO::FETCH_ASSOC);
 return ['decisions'=>$decisions,'profile_links'=>$links,'operations'=>array_column($events,'operation')];
}
try{
 if($securityAction==='setup'){
  foreach(Identity\PhysicianRequests\RequestAuthority::CAPABILITIES as $cap)secGrant('grant',$cap);
  $actor=secActor();$q=$p->query("SELECT factor_id FROM auth_staff_mfa_factors WHERE account_id='qa_master2' AND status='ACTIVE'");
  if(!$q->fetchColumn()){
   $secretFile=$owned.'/workspace02-security-totp-secret';if(!is_file($secretFile)){file_put_contents($secretFile,random_bytes(20));chmod($secretFile,0600);}$secret=file_get_contents($secretFile);
   $generator=new class($secret) implements Identity\Contracts\MfaSecretGeneratorPort{public function __construct(private string $s){}public function generate():string{return $this->s;}};
   $factorMfa=new Identity\Services\StaffMfaService($p,base64_encode(file_get_contents($owned.'/workflow-mfa-key')),'synthetic-workflow-mfa',secrets:$generator,security:$security);
   $registrar=loginActor('qa_director@example.test',trim(file_get_contents($owned.'/fixture-password')));
   $ceremony=new Identity\Services\GovernanceStaffMfaCeremony($p,$factorMfa,$security,new Identity\Adapters\CanonicalGovernanceAuditAdapter($p),$clock,['qa_director']);
   $f=$ceremony->begin($actor,$registrar,'qa_master2@example.test',trim(file_get_contents($owned.'/fixture-password')),'qa_director@example.test',trim(file_get_contents($owned.'/fixture-password')));
   $ceremony->activate($actor,$f['factor_id'],Identity\Services\StaffMfaService::totp($secret,intdiv(time(),30)));
  }
  foreach(['security_double','security_audit','security_tworeview','security_revoked'] as $key)secFixture($key);
  secFixture('security_stale',true);
  $attempt=(int)($input['new_race_attempt']??1);if($attempt<1||$attempt>9)throw new RuntimeException('security_attempt_invalid');
  foreach(['a','b'] as $suffix)secFixture('security_new_race'.$attempt.'_'.$suffix,false,'SYNTHETIC-WS02-NEW-RACE-'.$attempt);
  $result=['ready'=>true,'actor'=>'qa_master2'];
 }elseif($securityAction==='prepare'){
  $key=$input['key'];$rec=$records[$key];$account=($input['secondary']??false)?'qa_master':'qa_master2';
  if($account==='qa_master')foreach(Identity\PhysicianRequests\RequestAuthority::CAPABILITIES as $cap)secGrant('grant',$cap,'qa_master');
  $actor=secActor($account);$path=jobPath($key);
  if(is_file($path)){$job=json_decode(file_get_contents($path),true,32,JSON_THROW_ON_ERROR);$r=$requests->result($actor,$job['case_id']);if($r['committed']){echo json_encode(['ok'=>true,'data'=>['already_committed'=>true]]);exit;}if($job['expires']>time()+15){echo json_encode(['ok'=>true,'data'=>['prepared'=>true,'reused'=>true]]);exit;}}
  $read=$requests->read($actor,$rec['application_id'])['request'];
  foreach($read['evidence'] as $file)$requests->readEvidence($actor,$rec['application_id'],$file['id']);
  $case=$requests->begin($actor,$rec['application_id'],$read['intake_version'],$read['review_version'],$read['revision_token'],'APPROVED','Revisión sintética de seguridad',$account.'@example.test',trim(file_get_contents($owned.'/fixture-password')));
  $verified=$requests->verify($actor,$case['case_id'],$case['challenge_id'],$case['challenge'],nextCode($account,$account==='qa_master'?'workspace02-c4-totp-secret':'workspace02-security-totp-secret'));
  $token=json_decode(file_get_contents(secActorFile($account)),true)['token'];
  $job=['origin_id'=>$rec['application_id'],'actor_id'=>$account,'case_id'=>$case['case_id'],'proof_id'=>$verified['proof_id'],'proof'=>$verified['proof'],'actor_token'=>$token,'expires'=>time()+240];file_put_contents($path,json_encode($job,JSON_THROW_ON_ERROR));chmod($path,0600);$result=['prepared'=>true];
 }elseif($securityAction==='decide'){
  $job=json_decode(file_get_contents(jobPath($input['key'])),true,32,JSON_THROW_ON_ERROR);$d=$identity->sessions()->validate($job['actor_token']);if(!$d->allowed())throw new RuntimeException('security_session_expired');
  if(isset($input['release_file'])){if($input['release_file']!==$owned.'/workspace02-security-release')throw new RuntimeException('security_release_invalid');$until=microtime(true)+20;while(!is_file($input['release_file'])&&microtime(true)<$until)usleep(10000);}
  $r=$requests->decide($d->context(),$job['case_id'],$job['proof_id'],$job['proof'],true);$result=['state'=>$r['state'],'replayed'=>$r['replayed'],'version'=>$r['version']];
 }elseif($securityAction==='revoke_grant'){
  secGrant('revoke',Identity\PhysicianRequests\RequestAuthority::NEW_PROFILE);$result=['revoked'=>true];
 }elseif($securityAction==='restore_grant'){
  secGrant('grant',Identity\PhysicianRequests\RequestAuthority::NEW_PROFILE);$result=['restored'=>true];
 }elseif($securityAction==='revoke_source'){
  $rec=$records['security_stale'];$q=$p->prepare("SELECT credential_id,lifecycle_status FROM profiles_doctor_credentials WHERE doctor_id=? AND credential_type='PROFESSIONAL'");$q->execute([$rec['doctor_id']]);$row=$q->fetch();
  if($row['lifecycle_status']!=='REVOKED'){
   $ctx=Platform\Contracts\TrustedAuditContext::fromServer('qa_director','account','DIRECTOR','internal_governance','workspace02-security','workspace02-security','synthetic-source-fixture',null,null,'PROFILE','workspace02-owned-security');
   (new Profiles\Services\VerifiedDoctorCredentialService(new Profiles\Repositories\DoctorCredentialRepository($p)))->revoke((string)$row['credential_id'],$ctx);
  }$result=['source_revoked'=>true];
 }elseif($securityAction==='reject_evidence'){
  $rec=$records['evidence_stale'];$reviewer=loginActor('qa_master@example.test',trim(file_get_contents($owned.'/fixture-password')));
  $q=$p->prepare('SELECT request_id,version,status FROM auth_physician_evidence_requests WHERE request_id=?');$q->execute([$rec['evidence_request_id']]);$r=$q->fetch();
  if($r['status']!=='REJECTED'){
   $challenge=$composition->evidence->beginReview($reviewer,$r['request_id'],(int)$r['version'],'REJECTED','qa_master@example.test',trim(file_get_contents($owned.'/fixture-password')),'DOCUMENT_UNREADABLE');
   $proof=$mfa->verifyChallenge($reviewer->accountId(),(string)$reviewer->session()->sessionId(),$reviewer->principal()->credentialVersion(),$challenge['challenge_id'],$challenge['challenge'],nextCode('qa_master','workspace02-c4-totp-secret'));
   $r=$composition->evidence->decide($reviewer,$r['request_id'],(int)$r['version'],$proof['proof_id'],$proof['proof'],false);
  }$result=['evidence_status'=>$r['status'],'version'=>(int)$r['version']];
 }elseif($securityAction==='inspect'){$result=summary($records[$input['key']]['application_id']);
 }elseif($securityAction==='result'){
  $job=json_decode(file_get_contents(jobPath($input['key'])),true,32,JSON_THROW_ON_ERROR);$value=$requests->result(secActor($job['actor_id']??'qa_master2'),$job['case_id']);$result=['committed'=>$value['committed'],'status'=>$value['status']];
 }elseif($securityAction==='general_owner_gate'){
  $actor=secActor();$rec=$records['security_stale'];$denied=false;$p->beginTransaction();
  try{(new Identity\Adapters\PdoProfileOwnershipApproverAuthorityAdapter($p))->assertAuthorized($p,$actor->accountId(),$rec['account_id'],$rec['doctor_id'],$actor->principal()->credentialVersion());}catch(RuntimeException $e){$denied=$e->getMessage()==='profile_ownership_approver_authority_denied';}finally{$p->rollBack();}
  $result=['denied'=>$denied];
 }elseif($securityAction==='unauthorized'){
  $actor=loginActor('qa_advisor@example.test',trim(file_get_contents($owned.'/fixture-password')));$rec=$records['security_tworeview'];$denied=false;
  try{$v=$requests->read($actor,$rec['application_id'])['request'];$requests->begin($actor,$rec['application_id'],$v['intake_version'],$v['review_version'],$v['revision_token'],'REJECTED','Rechazo sintético no autorizado','qa_advisor@example.test',trim(file_get_contents($owned.'/fixture-password')));}catch(RuntimeException $e){$denied=str_contains($e->getMessage(),'capability_denied');}$result=['denied'=>$denied];
 }elseif($securityAction==='owned_conflict'){
  $actor=secActor();$rec=$records['owned_conflict'];$v=$requests->read($actor,$rec['application_id'])['request'];$denied=false;
  try{$requests->begin($actor,$rec['application_id'],$v['intake_version'],$v['review_version'],$v['revision_token'],'APPROVED','','qa_master2@example.test',trim(file_get_contents($owned.'/fixture-password')));}catch(RuntimeException $e){$denied=str_contains($e->getMessage(),'conflict');}$result=['denied'=>$denied,'approval_available'=>in_array('APPROVED',$v['allowed_actions'],true)];
 }elseif($securityAction==='secondary_begin'){
  foreach(Identity\PhysicianRequests\RequestAuthority::CAPABILITIES as $cap)secGrant('grant',$cap,'qa_master');
  $actor=secActor('qa_master');$rec=$records['security_tworeview'];$v=$requests->read($actor,$rec['application_id'])['request'];$denied=false;
  foreach($v['evidence'] as $file)$requests->readEvidence($actor,$rec['application_id'],$file['id']);
  try{$requests->begin($actor,$rec['application_id'],$v['intake_version'],$v['review_version'],$v['revision_token'],'APPROVED','','qa_master@example.test',trim(file_get_contents($owned.'/fixture-password')));}catch(RuntimeException $e){$denied=str_contains($e->getMessage(),'conflict');}$result=['denied'=>$denied];
 }else throw new RuntimeException('security_action_unknown');
 echo json_encode(['ok'=>true,'data'=>$result],JSON_THROW_ON_ERROR);
}catch(Throwable $e){file_put_contents($owned.'/workspace02-security-error.log',(string)$e);echo json_encode(['ok'=>false,'error'=>$e->getMessage(),'class'=>get_class($e)],JSON_THROW_ON_ERROR);exit(1);}
