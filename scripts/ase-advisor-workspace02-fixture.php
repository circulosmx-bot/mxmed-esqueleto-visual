<?php
declare(strict_types=1);
// Synthetic setup only. Never allowlisted by the HTTP router.
if(PHP_SAPI!=='cli')exit(1);
require_once __DIR__.'/ase-adm02-workflow-runtime.php';
[$identity]=staffWorkflowOwnedServices();
$owned=realpath(getenv('ASE_INT02A_OWNED'));
$p=new PDO('mysql:unix_socket='.$owned.'/mysql.sock;dbname='.getenv('MXMED_DB_NAME').';charset=utf8mb4','ase_fixture',getenv('ASE_INT02A_FIXTURE_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
if(realpath($p->query('SELECT @@datadir')->fetchColumn())!==$owned.'/mysql')throw new RuntimeException('owned_datadir_required');
require_once __DIR__.'/../modules/media/storage/LocalPersistentPrivateMediaStorage.php';
require_once __DIR__.'/../modules/profiles/services/VerifiedPhysicianIdentityService.php';
require_once __DIR__.'/../modules/profiles/services/VerifiedDoctorCredentialService.php';
require_once __DIR__.'/../modules/agenda/contracts/OtpProviderPort.php';
$clock=new Identity\Contracts\SystemClock();
$security=new Identity\Services\GovernanceSecurityContext($identity->sessions(),$identity->authentication());
$a=new Identity\Accreditation\EvidenceAuthority($p,$security);
$cipher=new Identity\Accreditation\EvidenceCipher('workspace02-synthetic',['workspace02-synthetic'=>file_get_contents($owned.'/workspace02-evidence-key')],'synthetic-owned-disposal-only');
$storage=new Media\Storage\LocalPersistentPrivateMediaStorage($owned.'/workspace02-private');
$mfa=new Identity\Services\StaffMfaService($p,base64_encode(file_get_contents($owned.'/workflow-mfa-key')),'synthetic-workflow-mfa',security:$security);
$composition=new Identity\Accreditation\PhysicianAdmissionComposition($p,$security,$cipher,$storage,$mfa);
$enrollment=new Identity\Accreditation\PublicEnrollmentService($a,$cipher,file_get_contents($owned.'/workspace02-proof-key'));
$requests=new Identity\PhysicianRequests\RequestService($a,$enrollment,$cipher,$mfa,$storage);
$action=$argv[1]??'prepare';$input=json_decode(stream_get_contents(STDIN)?:'{}',true,32,JSON_THROW_ON_ERROR);
$recordFile=$owned.'/workspace02-fixture.json';$records=is_file($recordFile)?json_decode(file_get_contents($recordFile),true,64,JSON_THROW_ON_ERROR):[];
function persist():void{global $records,$recordFile;file_put_contents($recordFile,json_encode($records,JSON_THROW_ON_ERROR));}
function loginActor(string $email,?string $password=null):Identity\Contracts\AuthenticatedAccessContext{
 global $identity,$owned;
 $password??=trim(file_get_contents($owned.'/applicant-password'));
 $d=$identity->authentication()->authenticate($email,$password,['identity'=>$email]);
 if(!$d->isAllowed())throw new RuntimeException('synthetic_login_denied');
 $s=$identity->sessions()->create($d->candidate());if(!$s->allowed())throw new RuntimeException('synthetic_session_denied');
 return $identity->sessions()->validate($s->token()->value())->context();
}
function account(string $key):array{
 global $p,$owned,$clock;
 $email='workspace02_'.$key.'@example.test';
 $q=$p->prepare('SELECT account_id FROM auth_accounts WHERE email_normalized=?');$q->execute([$email]);$id=$q->fetchColumn();
 if(!$id){
  $accounts=new Identity\Repositories\IdentityAccountRepository($p);$credentials=new Identity\Repositories\AccountCredentialRepository($p);
  $consents=new Identity\Repositories\AccountConsentRepository($p);$tokens=new Identity\Repositories\OneTimeTokenRepository($p);
  $limits=new Identity\Services\RateLimitService($p,new Identity\Contracts\RateLimitKeyHasher(getenv('MXMED_PREVIEW_PEPPER')),$clock);
  $notifications=new Identity\Adapters\PreviewIdentityNotificationAdapter('/tmp/'.basename($owned).'/workspace02-inert-email.json');
  $r=(new Identity\Services\RegistrationService($p,$accounts,$credentials,$consents,$tokens,$limits,$notifications,$clock))->register([
   'email'=>$email,'password'=>trim(file_get_contents($owned.'/applicant-password')),'terms_accepted'=>true,'privacy_notice_accepted'=>true,
   'terms_version'=>'synthetic-terms-v1','privacy_notice_version'=>'synthetic-privacy-v1'],['identity'=>$email]);
  if(!$r->accepted()||!$r->accountId())throw new RuntimeException('synthetic_registration_failed');$id=$r->accountId();
  $messages=json_decode(file_get_contents($owned.'/workspace02-inert-email.json'),true,512,JSON_THROW_ON_ERROR);
  $matches=array_values(array_filter($messages,fn($m)=>$m['recipient']===$email&&$m['purpose']==='email_verification'));
  $message=end($matches);
  if(!(new Identity\Services\EmailVerificationService($p,$accounts,$consents,$tokens,$limits,$notifications,$clock))->verify($message['token'],['identity'=>$email])->verified())throw new RuntimeException('synthetic_email_verification_failed');
 }
 return ['email'=>$email,'account_id'=>$id];
}
function sources(string $doctor,string $key):array{
 global $p;
 $q=$p->prepare('SELECT verified_identity_id FROM profiles_verified_identities WHERE doctor_id=?');$q->execute([$doctor]);$i=$q->fetchColumn();
 $q=$p->prepare("SELECT credential_id FROM profiles_doctor_credentials WHERE doctor_id=? AND credential_type='PROFESSIONAL'");$q->execute([$doctor]);$c=$q->fetchColumn();
 if(!$i){$v=(new Profiles\Services\VerifiedPhysicianIdentityService(new Profiles\Repositories\VerifiedPhysicianIdentityRepository($p)))->provisionFromTrustedAuthority(
  ['doctor_id'=>$doctor,'given_names'=>'Persona Ficticia','first_surname'=>ucfirst($key)],
  ['source_type'=>'internal_provisioning','source_reference'=>'workspace02-synthetic-identity-'.$key,'verified_at'=>gmdate('Y-m-d H:i:s'),'verified_by_account_id'=>'qa_director']);$i=$v['verified_identity_id'];}
 if(!$c){$context=Platform\Contracts\TrustedAuditContext::fromServer('qa_director','account','DIRECTOR','internal_governance','workspace02-fixture','workspace02-fixture','synthetic-source-fixture',null,null,'PROFILE','workspace02-owned-fixture');
  $v=(new Profiles\Services\VerifiedDoctorCredentialService(new Profiles\Repositories\DoctorCredentialRepository($p)))->provisionFromTrustedAuthority([
   'doctor_id'=>$doctor,'credential_type'=>'PROFESSIONAL','license_number'=>'SYNTHETIC-WS02-'.strtoupper($key),'professional_area_label'=>'Área ficticia',
   'institution_name'=>'Institución ficticia','verification_status'=>'VERIFIED','source_type'=>'internal_provisioning','source_reference'=>'workspace02-synthetic-credential-'.$key],$context);$c=$v['credential_id'];}
 return [(int)$i,(int)$c];
}
function verifyPhone(Identity\Contracts\AuthenticatedAccessContext $actor,string $email):void{
 global $a,$cipher,$p,$owned;
 $q=$p->prepare("SELECT status FROM auth_account_phone_verifications WHERE account_id=?");$q->execute([$actor->accountId()]);if($q->fetchColumn()==='VERIFIED')return;
 $sms=new class implements Agenda\Contracts\OtpProviderPort{
  public string $code='';public function providerId():string{return 'workspace02-inert-synthetic';}public function configured():bool{return true;}
  public function deliver(string $channel,string $destination,string $secret,array $context=[]):Agenda\Contracts\OtpDeliveryResult{
   if($channel!=='sms'||$destination!=='+15550002002')throw new RuntimeException('synthetic_only');$this->code=$secret;return new Agenda\Contracts\OtpDeliveryResult(true,'synthetic',null);
  }
 };
 $phone=new Identity\Accreditation\PhoneVerificationService($a,$cipher,new Identity\Accreditation\EvidenceAudit($p),$sms);
 $c=$phone->issue($actor,'+15550002002',$email,trim(file_get_contents($owned.'/applicant-password')));
 if(!$c['delivery_accepted']||!$phone->verify($actor,$c['challenge_id'],$c['contact_version'],$sms->code))throw new RuntimeException('synthetic_contact_failed');
}
function nextCode(string $account,string $file):string{
 global $p,$owned;
 $q=$p->prepare("SELECT last_totp_time_step FROM auth_staff_mfa_factors WHERE account_id=? AND status='ACTIVE'");$q->execute([$account]);$last=$q->fetchColumn();
 while($last!==false&&$last!==null&&intdiv(time(),30)<=(int)$last)usleep(300000);
 return Identity\Services\StaffMfaService::totp(file_get_contents($owned.'/'.$file),intdiv(time(),30));
}
if($action==='library')return;
try{
 if($action==='prepare'){
  $cases=[
   'claim_ready'=>['workspace_aurora','Aurora Ejemplo del Valle'],
   'new_ready'=>[null,'Lucía Ficticia Nuevo'],
   'claim_approve'=>['workspace02_claim_approve','Clara Ficticia Reclamo'],
   'new_approve'=>[null,'Nicolás Ficticio Nuevo'],
   'claim_reject'=>['workspace02_claim_reject','Renata Ficticia Conservada'],
   'information'=>[null,'Inés Ficticia Información'],
   'race_a'=>['workspace02_race','Rafael Ficticio Carrera'],
   'race_b'=>['workspace02_race','Rafael Ficticio Carrera'],
   'owned_conflict'=>['workspace02_owned','Olivia Ficticia Ocupado'],
   'evidence_stale'=>[null,'Estela Ficticia Versiones'],
   'revoked'=>[null,'Raúl Ficticio Revocación'],
   'new_reject'=>[null,'Rosa Ficticia Rechazo'],
  ];
  foreach($cases as $key=>[$doctor,$name]){
   if(isset($records[$key]['evidence_request_id']))continue;
   $rec=$records[$key]??account($key);$rec+=['doctor_id'=>$doctor,'name'=>$name,'key'=>$key];$records[$key]=$rec;persist();
   $sourceKey=$key==='race_b'?'race_a':$key;
   if($doctor!==null){
    $q=$p->prepare('SELECT doctor_id FROM profiles_doctors WHERE doctor_id=?');$q->execute([$doctor]);
    if(!$q->fetchColumn())$p->prepare('INSERT INTO profiles_doctors(doctor_id,display_name,specialty_primary) VALUES(?,?,?)')->execute([$doctor,$name,'Medicina general']);
    $p->prepare("UPDATE profiles_doctors SET profile_status='active',is_public_candidate=1,plan_code='free',preloaded_free_plan='free',preloaded_free_reference=?,preloaded_free_recorded_at=CURRENT_TIMESTAMP(6),professional_license=? WHERE doctor_id=?")
     ->execute(['workspace02-synthetic-origin-'.$sourceKey,'SYNTHETIC-WS02-'.strtoupper($sourceKey),$doctor]);
    $q=$p->prepare('SELECT consultorio_id FROM consultorios WHERE doctor_id=? LIMIT 1');$q->execute([$doctor]);
    if(!$q->fetchColumn())$p->prepare('INSERT INTO consultorios(doctor_id,consultorio_id,titulo,municipio,estado) VALUES(?,?,?,?,?)')->execute([$doctor,$doctor.'_office','Consultorio ficticio','Ciudad Sintética','Estado Ficticio']);
    $refs=sources($doctor,$sourceKey);
   }
   $actor=loginActor($rec['email']);
   if(!isset($rec['application_id'])){
    $browser=Identity\Accreditation\PublicEnrollmentService::opaque();$type=$doctor===null?'REQUEST_NEW_PROFILE':'CLAIM_EXISTING_PROFILE';
    $ticket=$enrollment->ticket($type,$doctor,'/profiles/listing.php',$browser);$origin=$enrollment->start($ticket,$browser);
    $enrollment->bind($actor,$origin['application_id'],$origin['handle'],$browser);
    $enrollment->save($actor,$origin['application_id'],0,$a->uuid(),[
     'given_names'=>'Persona Ficticia','first_surname'=>ucfirst($sourceKey),'second_surname'=>'',
     'professional_license'=>'SYNTHETIC-WS02-'.strtoupper($sourceKey),'specialty'=>'Medicina general','state'=>'Estado Ficticio','city'=>'Ciudad Sintética'],true);
    $rec['application_id']=$origin['application_id'];$records[$key]=$rec;persist();
   }
   verifyPhone($actor,$rec['email']);
   $r=$doctor===null?$composition->evidence->create($actor,'REQUEST_NEW_PROFILE'):$composition->evidence->create($actor,'CLAIM_EXISTING_PROFILE',$doctor,...$refs);
   foreach(['OFFICIAL_ID','PROFESSIONAL_CREDENTIAL'] as $kind){$file=$composition->evidence->addDocument($actor,$r['request_id'],$r['version'],$kind,file_get_contents($owned.'/synthetic.png'));$r['version']=$file['version'];}
   $r=$composition->evidence->submit($actor,$r['request_id'],$r['version']);
   $rec['evidence_request_id']=$r['request_id'];$rec['evidence_version']=$r['version'];$records[$key]=$rec;persist();
  }
  foreach($records as $rec){$requests->linkEvidence(loginActor($rec['email']),$rec['application_id'],$rec['evidence_request_id']);}
  $result=['prepared'=>count($records),'cases'=>$records];
 }elseif($action==='secondary_advisor'){
  $writer=new Identity\Services\InternalGovernanceService($p,new Identity\Http\CanonicalHttpSessionResolver($identity->sessions()));
  $registered=$p->query("SELECT account_id FROM internal_staff WHERE account_id='qa_advisor'")->fetchColumn();
  $granted=$p->query("SELECT grant_id FROM internal_operator_grants WHERE account_id='qa_advisor' AND capability='physician_request_read' AND status='ACTIVE'")->fetchColumn();
  if(!$registered||!$granted){
   $login=$identity->authentication()->authenticate('qa_director@example.test',trim(file_get_contents($owned.'/fixture-password')),['identity'=>'qa_director@example.test']);
   if(!$login->isAllowed())throw new RuntimeException('synthetic_director_login_denied');
   $session=$identity->sessions()->create($login->candidate());if(!$session->allowed())throw new RuntimeException('synthetic_director_session_denied');
   $cookies=['__Host-mxmed_session'=>$session->token()->value()];
   if(!$registered)$writer->execute($cookies,'register','qa_advisor');
   if(!$granted)$writer->execute($cookies,'grant','qa_advisor','physician_request_read');
  }
  $result=['account'=>'qa_advisor','email'=>'qa_advisor@example.test','decision_authority'=>false];
 }elseif($action==='owned_conflict'){
  $rec=$records['owned_conflict'];
  $q=$p->prepare("SELECT membership_id FROM auth_account_memberships WHERE profile_doctor_id=? AND role_code='owner' AND status='active'");$q->execute([$rec['doctor_id']]);
  if(!$q->fetchColumn()){
   // Existing-owner synthetic fixture, using the canonical repository. This is
   // deliberately not an approval outcome and is never used as a positive test.
   $p->beginTransaction();
   (new Identity\Repositories\AccountMembershipRepository($p))->create(new Identity\Contracts\AccountMembership('ws02_existing_owner','qa_outsider',Identity\Contracts\CanonicalProfileReference::profile($rec['doctor_id']),'owner','profile','active','manual_review'));
   $p->commit();
  }
  $result=['existing_owner_conflict'=>true];
 }elseif($action==='enroll_advisor'){
  $record=json_decode(file_get_contents($owned.'/oper09-advisor.json'),true);$actor=loginActor($record['email']);
  $registrar=loginActor('qa_master@example.test',trim(file_get_contents($owned.'/fixture-password')));
  $secret=file_get_contents($owned.'/workspace02-totp-secret');
  $generator=new class($secret) implements Identity\Contracts\MfaSecretGeneratorPort{public function __construct(private string $s){}public function generate():string{return $this->s;}};
  $factorMfa=new Identity\Services\StaffMfaService($p,base64_encode(file_get_contents($owned.'/workflow-mfa-key')),'synthetic-workflow-mfa',secrets:$generator,security:$security);
  $ceremony=new Identity\Services\GovernanceStaffMfaCeremony($p,$factorMfa,$security,new Identity\Adapters\CanonicalGovernanceAuditAdapter($p),$clock,['qa_master']);
  $factor=$ceremony->begin($actor,$registrar,$record['email'],trim(file_get_contents($owned.'/applicant-password')),'qa_master@example.test',trim(file_get_contents($owned.'/fixture-password')));
  $ceremony->activate($actor,$factor['factor_id'],Identity\Services\StaffMfaService::totp($secret,intdiv(time(),30)));$result=['enrolled'=>true];
 }elseif($action==='accredit'){
  $key=$input['key'];$rec=$records[$key];$applicant=loginActor($rec['email']);
  $q=$p->prepare("SELECT profile_doctor_id FROM auth_physician_profile_request_decisions WHERE origin_id=? AND state='APPROVED' ORDER BY resolution_version DESC LIMIT 1");$q->execute([$rec['application_id']]);$doctor=$q->fetchColumn();
  if(!is_string($doctor))throw new RuntimeException('approved_application_required');
  $q=$p->query("SELECT grant_id FROM internal_operator_grants WHERE account_id='qa_master' AND capability='physician_accreditation_review' AND status='ACTIVE' AND revoked_at IS NULL");
  if(!$q->fetchColumn()){
   $d=$identity->authentication()->authenticate('qa_director@example.test',trim(file_get_contents($owned.'/fixture-password')),['identity'=>'qa_director@example.test']);
   if(!$d->isAllowed())throw new RuntimeException('synthetic_director_login_denied');
   $directorSession=$identity->sessions()->create($d->candidate());
   if(!$directorSession->allowed())throw new RuntimeException('synthetic_director_session_denied');
   $writer=new Identity\Services\InternalGovernanceService($p,new Identity\Http\CanonicalHttpSessionResolver($identity->sessions()));
   $writer->execute(['__Host-mxmed_session'=>$directorSession->token()->value()],'grant','qa_master','physician_accreditation_review');
  }
  $reviewer=loginActor('qa_master@example.test',trim(file_get_contents($owned.'/fixture-password')));
  $q=$p->query("SELECT factor_id FROM auth_staff_mfa_factors WHERE account_id='qa_master' AND status='ACTIVE'");
  if(!$q->fetchColumn()){
   if(!is_file($owned.'/workspace02-c4-totp-secret'))file_put_contents($owned.'/workspace02-c4-totp-secret',random_bytes(20));
   $secret=file_get_contents($owned.'/workspace02-c4-totp-secret');
   $generator=new class($secret) implements Identity\Contracts\MfaSecretGeneratorPort{public function __construct(private string $s){}public function generate():string{return $this->s;}};
   $factorMfa=new Identity\Services\StaffMfaService($p,base64_encode(file_get_contents($owned.'/workflow-mfa-key')),'synthetic-workflow-mfa',secrets:$generator,security:$security);
   $registrar=loginActor('qa_director@example.test',trim(file_get_contents($owned.'/fixture-password')));
   $ceremony=new Identity\Services\GovernanceStaffMfaCeremony($p,$factorMfa,$security,new Identity\Adapters\CanonicalGovernanceAuditAdapter($p),$clock,['qa_director']);
   $factor=$ceremony->begin($reviewer,$registrar,'qa_master@example.test',trim(file_get_contents($owned.'/fixture-password')),'qa_director@example.test',trim(file_get_contents($owned.'/fixture-password')));
   $ceremony->activate($reviewer,$factor['factor_id'],Identity\Services\StaffMfaService::totp($secret,intdiv(time(),30)));
  }
  if($rec['doctor_id']===null){
   sources($doctor,$key);
   $review=new Identity\Accreditation\ReviewedEnrollmentService($enrollment,$mfa,$composition->projection);
   $q=$p->prepare('SELECT state,resolution_version FROM auth_physician_enrollment_resolutions WHERE origin_id=? ORDER BY resolution_version DESC LIMIT 1');$q->execute([$rec['application_id']]);$head=$q->fetch();
   if(!$head||$head['state']!=='APPROVED'){
    $proof=$review->begin($reviewer,$rec['application_id'],1,(int)($head['resolution_version']??0),'APPROVED','qa_master@example.test',trim(file_get_contents($owned.'/fixture-password')));
    $verified=$review->verify($reviewer,$proof['case_id'],$proof['challenge_id'],$proof['challenge'],nextCode('qa_master','workspace02-c4-totp-secret'));
    $review->decide($reviewer,$proof['case_id'],$verified['proof_id'],$verified['proof'],true);
   }
   $r=$composition->evidence->createFromEnrollment($applicant,$rec['application_id']);
   if($r['status']==='DRAFT'){
    foreach(['OFFICIAL_ID','PROFESSIONAL_CREDENTIAL'] as $kind){$f=$composition->evidence->addDocument($applicant,$r['request_id'],$r['version'],$kind,file_get_contents($owned.'/synthetic.png'));$r['version']=$f['version'];}
    $r=$composition->evidence->submit($applicant,$r['request_id'],$r['version']);
   }
  }else{
   $q=$p->prepare('SELECT request_id,version,status FROM auth_physician_evidence_requests WHERE request_id=?');$q->execute([$rec['evidence_request_id']]);$r=$q->fetch();$r['version']=(int)$r['version'];
  }
  if($r['status']==='SUBMITTED'){
   $proof=$composition->evidence->beginReview($reviewer,$r['request_id'],$r['version'],'APPROVED','qa_master@example.test',trim(file_get_contents($owned.'/fixture-password')));
   $q=$p->prepare('SELECT file_id FROM auth_physician_evidence_files WHERE request_id=?');$q->execute([$r['request_id']]);$files=$q->fetchAll(PDO::FETCH_COLUMN);
   foreach($files as $file)$composition->evidence->readDocument($reviewer,$r['request_id'],$r['version'],$file);
   $verified=$mfa->verifyChallenge($reviewer->accountId(),(string)$reviewer->session()->sessionId(),$reviewer->principal()->credentialVersion(),$proof['challenge_id'],$proof['challenge'],nextCode('qa_master','workspace02-c4-totp-secret'));
   $r=$composition->evidence->decide($reviewer,$r['request_id'],$r['version'],$verified['proof_id'],$verified['proof'],true,array_fill_keys($files,null));
  }
  if($r['status']==='APPROVED'){
   $confirm=$composition->evidence->issueConfirmation($applicant,$r['request_id'],$r['version'],$rec['email'],trim(file_get_contents($owned.'/applicant-password')));
   $r=$composition->evidence->confirm($applicant,$r['request_id'],$r['version'],$confirm['confirmation'],true);
  }
  $result=$composition->issuer->issue($applicant,$r['issuance_command_id']??$r['request_id']);
  $records[$key]['accredited_profile']=$doctor;persist();
 }else throw new RuntimeException('unknown_fixture_action');
 echo json_encode(['ok'=>true,'data'=>$result],JSON_THROW_ON_ERROR);
}catch(Throwable $e){file_put_contents($owned.'/workspace02-fixture-error.log',(string)$e);echo json_encode(['ok'=>false,'error'=>$e->getMessage()],JSON_THROW_ON_ERROR);exit(1);}
