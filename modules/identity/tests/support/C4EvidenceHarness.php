<?php
declare(strict_types=1);
// Shared synthetic fixtures. Including this file performs no provisioning or delivery.
use Identity\Accreditation\{EvidenceAuthority,EvidenceCipher,EvidenceAudit,PhoneVerificationService,PhysicianEvidenceService,ProfileRequestMatcher};
use Identity\Contracts\{Clock,MfaSecretGeneratorPort};
use Identity\Repositories\{IdentityAccountRepository,AccountCredentialRepository,AccountConsentRepository,OneTimeTokenRepository};
use Identity\Services\{SessionService,SessionTokenCodec,RateLimitService,CredentialAuthenticationService,GovernanceSecurityContext,StaffMfaService};

final class EvClock implements Clock {
    public function __construct(public DateTimeImmutable $time) {}
    public function now():DateTimeImmutable{return $this->time;}
    public function advance(string $value):void{$this->time=$this->time->modify($value);}
}
final class EvMfaSecret implements MfaSecretGeneratorPort { public function generate():string{return str_repeat('e',20);} }
final class EvSyntheticSms implements Agenda\Contracts\OtpProviderPort {
    public ?string $last=null;public int $sent=0;public bool $accept=true;
    public function providerId():string{return 'c4-disposable-synthetic';}
    public function configured():bool{return true;}
    public function deliver(string $channel,string $destination,string $secret,array $context=[]):Agenda\Contracts\OtpDeliveryResult {
        if ($channel!=='sms'||!str_starts_with($destination,'+1555')) throw new RuntimeException('synthetic_delivery_scope');
        $this->last=$secret;$this->sent++;return new Agenda\Contracts\OtpDeliveryResult($this->accept,'synthetic',null);
    }
}
const EV_PASSWORD='Only-Synthetic-C4-EV01!';
function evCheck(bool $value,string $label):void {if(!$value)throw new RuntimeException('FAIL '.$label);echo 'PASS '.$label.PHP_EOL;}
function evDeny(callable $fn,string $expected,string $label):void {
    try {$fn();} catch (Throwable $e) {
        if ($e->getMessage()!==$expected) throw new RuntimeException('FAIL '.$label.' unexpected '.get_class($e).':'.$e->getMessage(),0,$e);
        evCheck(true,$label);return;
    }
    throw new RuntimeException('FAIL '.$label.' unexpectedly allowed');
}
function evSqlDeny(callable $fn,string $state,string $label):void {
    try {$fn();}catch(PDOException $e){if((string)$e->getCode()!==$state)throw $e;evCheck(true,$label);return;}
    throw new RuntimeException('FAIL '.$label);
}
function evConnect():PDO {
    $c=json_decode(file_get_contents((string)getenv('GOV03B_QA_CONNECTION')),true,32,JSON_THROW_ON_ERROR);
    if (!preg_match('/^mxmed_gate4d_preview_(?:c4ev_|c4issuer_|c4ui_)[a-f0-9]{10}$/D',$c['db'])) throw new RuntimeException('disposable_only');
    return new PDO('mysql:host=127.0.0.1;port=3306;dbname='.$c['db'].';charset=utf8mb4',$c['user'],$c['password'],
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
}
function evStack(PDO $p,EvClock $clock):array {
    $accounts=new IdentityAccountRepository($p);$credentials=new AccountCredentialRepository($p);
    $sessions=new SessionService(new Identity\Adapters\ValkeySessionStoreAdapter(new Identity\Adapters\PreviewValkeyClient('127.0.0.1',(int)getenv('MXMED_SESSION_STORE_PORT')),
        'mxmed:gate4d:preview:session:',$clock),new SessionTokenCodec((string)getenv('MXMED_PREVIEW_PEPPER')),$clock,
        new Identity\Contracts\SessionPolicy(),new Identity\Adapters\PdoSessionAccountStateAdapter($accounts,$credentials));
    $limits=new RateLimitService($p,new Identity\Contracts\RateLimitKeyHasher(str_repeat('l',32)),$clock);
    $auth=new CredentialAuthenticationService($accounts,$credentials,$limits,$clock);$security=new GovernanceSecurityContext($sessions,$auth,$clock);
    $mfa=new StaffMfaService($p,base64_encode(str_repeat('m',32)),'c4-qa-mfa',new EvMfaSecret(),$clock,$security);
    $authority=new EvidenceAuthority($p,$security,$clock);$audit=new EvidenceAudit($p);
    $cipher=new EvidenceCipher('c4-qa-key',['c4-qa-key'=>base64_decode((string)getenv('C4_EV_QA_KEY'),true)],'synthetic-retention-fixture-no-real-policy');
    $storage=new Media\Storage\LocalPersistentPrivateMediaStorage((string)getenv('C4_EV_QA_STORAGE_ROOT'));
    $sms=new EvSyntheticSms();$phone=new PhoneVerificationService($authority,$cipher,$audit,$sms);
    $evidence=new PhysicianEvidenceService($authority,$cipher,$storage,$mfa);
    $composition=null;
    if (getenv('C4_ISSUER_QA')==='1') {
        $composition=new Identity\Accreditation\PhysicianAdmissionComposition($p,$security,$cipher,$storage,$mfa,$clock);
        $evidence=$composition->evidence;
    }
    return compact('composition','accounts','credentials','sessions','limits','auth','security','mfa','authority','audit','cipher','storage','sms','phone','evidence');
}
function evLogin(array $s,string $email):array {
    $a=$s['auth']->authenticate($email,EV_PASSWORD,['ip'=>null]);if(!$a->isAllowed())throw new RuntimeException('synthetic_login_denied');
    $created=$s['sessions']->create($a->candidate());if(!$created->allowed())throw new RuntimeException('synthetic_session_denied');
    $token=(string)$created->token();return ['context'=>$s['sessions']->validate($token)->context(),'token'=>$token,'email'=>$email];
}
function evPhone(array $s,array $actor,string $number):void {
    $c=$s['phone']->issue($actor['context'],$number,$actor['email'],EV_PASSWORD);
    if (!$c['delivery_accepted']||!$s['phone']->verify($actor['context'],$c['challenge_id'],$c['contact_version'],$s['sms']->last)) throw new RuntimeException('synthetic_phone_flow_failed');
}
function evProfessional(PDO $p,string $label,string $reviewer):array {
    $profile='c4ev_'.$label;$p->prepare('INSERT INTO profiles_doctors(doctor_id,display_name,specialty_primary) VALUES(?,?,?)')->execute([$profile,'Synthetic same public name','Synthetic specialty']);
    $ids=new Profiles\Services\VerifiedPhysicianIdentityService(new Profiles\Repositories\VerifiedPhysicianIdentityRepository($p));
    $identity=$ids->provisionFromTrustedAuthority(['doctor_id'=>$profile,'given_names'=>'Synthetic','first_surname'=>'Physician'],
        ['source_type'=>'internal_provisioning','source_reference'=>'c4ev-synthetic-identity-'.$label,'verified_at'=>gmdate('Y-m-d H:i:s'),'verified_by_account_id'=>$reviewer]);
    $context=Platform\Contracts\TrustedAuditContext::fromServer($reviewer,'account','MASTER_ADMIN','internal_governance','c4-request','c4-correlation','c4-synthetic-reviewer-session',null,null,'PROFILE','c4-disposable-fixture');
    $service=new Profiles\Services\VerifiedDoctorCredentialService(new Profiles\Repositories\DoctorCredentialRepository($p));
    $credential=$service->provisionFromTrustedAuthority(['doctor_id'=>$profile,'credential_type'=>'PROFESSIONAL','license_number'=>'C4EV-SYNTHETIC-'.$label,
        'professional_area_label'=>'Synthetic area','institution_name'=>'Synthetic institution','verification_status'=>'VERIFIED',
        'source_type'=>'internal_provisioning','source_reference'=>'c4ev-synthetic-credential-'.$label],$context);
    return [$profile,(int)$identity['verified_identity_id'],(int)$credential['credential_id']];
}
function evRequest(array $s,array $actor,array $professional,string $origin='CLAIM_EXISTING_PROFILE'):array {
    $r=$s['evidence']->create($actor['context'],$origin,...$professional);$files=[];
    foreach(['OFFICIAL_ID','PROFESSIONAL_CREDENTIAL'] as $kind){$d=$s['evidence']->addDocument($actor['context'],$r['request_id'],$r['version'],$kind,"%PDF-1.4\nC4-EV01-SYNTHETIC-DOCUMENT-".$kind."\n%%EOF");$r['version']=$d['version'];$files[]=$d['file_id'];}
    $r=$s['evidence']->submit($actor['context'],$r['request_id'],$r['version']);return $r+['files'=>$files];
}
function evProof(array $s,EvClock $clock,array $reviewer,array $r,string $action):array {
    $clock->advance('+31 seconds');
    $reason=match($action){'APPROVED'=>'DOCUMENT_RELATIONSHIP_VERIFIED','REJECTED'=>'ACCOUNT_PERSON_MISMATCH','SUSPENDED'=>'SECURITY_REVIEW','EXPIRED'=>'SOURCE_EXPIRED',default=>'SECURITY_REVOCATION'};
    $c=$s['evidence']->beginReview($reviewer['context'],$r['request_id'],$r['version'],$action,$reviewer['email'],EV_PASSWORD,$reason);
    return $s['mfa']->verifyChallenge($reviewer['context']->accountId(),(string)$reviewer['context']->session()->sessionId(),
        $reviewer['context']->principal()->credentialVersion(),$c['challenge_id'],$c['challenge'],StaffMfaService::totp(str_repeat('e',20),intdiv($clock->now()->getTimestamp(),30)));
}
function evConsumer(array $s, PDO $p,array $r,string $account,string $doctor):array {
    $p->beginTransaction();try{return $s['evidence']->requireConfirmed($p,$r['request_id'],$r['version'],$account,$doctor);}finally{$p->rollBack();}
}
