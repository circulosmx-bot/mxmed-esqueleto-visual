<?php
declare(strict_types=1);
// CLI only. Configuration comes from the owning fresh-instance runner, never HTTP.
if(PHP_SAPI!=='cli')exit(1);
require_once __DIR__.'/../http/IdentityHttpComposition.php';
\Identity\Http\IdentityHttpComposition::registerAutoloader();
use Identity\Http\{IdentityHttpComposition,CanonicalHttpSessionResolver};
use Identity\Services\{CredentialAuthenticationService,RateLimitService,SessionService,SessionTokenCodec,SessionStoreFactory,InternalGovernanceService};
use Identity\Repositories\{IdentityAccountRepository,AccountCredentialRepository};
use Identity\Contracts\{RateLimitKeyHasher,SystemClock,Clock,SessionPolicy};
use Identity\Adapters\{PreviewValkeyClient,PdoSessionAccountStateAdapter};
$phase=getenv('ASE_TEAM_QA_PHASE')?:'ase-int02a';
if(!in_array($phase,['ase-adm02imp01'],true))throw new RuntimeException('isolated_phase_required');
$owned=(string)getenv('ASE_INT02A_OWNED');
if(!preg_match('~^/private/tmp/mxmed-'.preg_quote($phase,'~').'-[a-zA-Z0-9_]+$~D',realpath($owned)?:'')||!preg_match('/^mxmed_gate4d_preview_'.str_replace('-','',$phase).'_[a-f0-9]{10}$/D',(string)getenv('MXMED_DB_NAME'))||in_array((int)getenv('MXMED_DB_PORT'),[3306,3309],true))throw new RuntimeException('isolated_target_required');
$fixture=new PDO('mysql:unix_socket='.$owned.'/mysql.sock;dbname='.getenv('MXMED_DB_NAME').';charset=utf8mb4','ase_fixture',(string)getenv('ASE_INT02A_FIXTURE_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
if(realpath((string)$fixture->query('SELECT @@datadir')->fetchColumn())!==realpath($owned.'/mysql'))throw new RuntimeException('isolated_datadir_required');
$identity=IdentityHttpComposition::fromProcessEnvironment();$clock=new SystemClock();$action=$argv[1]??'';

// Test entry only, unreachable from the allowlisted TLS router; canonical services below are real.
$tokens=json_decode(file_get_contents($owned.'/sessions.json'),true,512,JSON_THROW_ON_ERROR);
$b=json_decode(stream_get_contents(STDIN),true,16,JSON_THROW_ON_ERROR);
$fixture->setAttribute(PDO::ATTR_EMULATE_PREPARES,false);
try {
 if($action==='logout'){ $identity->sessions()->logout($tokens[$b['actor']]);echo json_encode(['ok'=>true]);exit;}
 if(in_array($action,['register','verify','login','login_denied'],true)) {
  $accounts=new IdentityAccountRepository($fixture);$credentials=new AccountCredentialRepository($fixture);
  $consents=new \Identity\Repositories\AccountConsentRepository($fixture);$oneTimes=new \Identity\Repositories\OneTimeTokenRepository($fixture);
  $limits=new RateLimitService($fixture,new RateLimitKeyHasher((string)getenv('MXMED_PREVIEW_PEPPER')),$clock);
  $notifications=new \Identity\Adapters\PreviewIdentityNotificationAdapter($owned.'/canonical-registration-sink.json');
  $email=$b['email'];if(!preg_match('/^[a-z0-9_]+@example\.test$/D',$email))throw new RuntimeException('synthetic_email_required');
  if($action==='register') {
   $register=new \Identity\Services\RegistrationService($fixture,$accounts,$credentials,$consents,$oneTimes,$limits,$notifications,$clock);
   $r=$register->register(['email'=>$email,'password'=>trim(file_get_contents($owned.'/applicant-password')),'terms_accepted'=>true,'privacy_notice_accepted'=>true,'terms_version'=>'synthetic-terms-v1','privacy_notice_version'=>'synthetic-privacy-v1'],['identity'=>$email]);
   if(!$r->accepted()||$r->accountId()===null)throw new RuntimeException('canonical_registration_failed');
   echo json_encode(['ok'=>true,'data'=>['account_id'=>$r->accountId()]]);exit;
  }
  if($action==='verify') {
   $messages=json_decode(file_get_contents($owned.'/canonical-registration-sink.json'),true,512,JSON_THROW_ON_ERROR);
   $matches=array_values(array_filter($messages,fn($m)=>$m['recipient']===$email&&$m['purpose']==='email_verification'));
   $m=end($matches);if(!$m)throw new RuntimeException('verification_delivery_missing');
   $verify=new \Identity\Services\EmailVerificationService($fixture,$accounts,$consents,$oneTimes,$limits,$notifications,$clock);
   if(!$verify->verify($m['token'],['identity'=>$email])->verified())throw new RuntimeException('canonical_verification_failed');
   echo json_encode(['ok'=>true]);exit;
  }
  $auth=new CredentialAuthenticationService($accounts,$credentials,$limits,$clock);
  $password=trim(file_get_contents($owned.'/'.($b['existing']??false?'fixture-password':'applicant-password')));
  $decision=$auth->authenticate($email,$password,['identity'=>$email]);
  if($action==='login_denied'){echo json_encode(['ok'=>!$decision->isAllowed()]);exit;}
  if(!$decision->isAllowed())throw new RuntimeException('canonical_login_failed');
  $session=$identity->sessions()->create($decision->candidate());if(!$session->allowed())throw new RuntimeException('canonical_session_failed');
  $tokens[$b['actor']]=$session->token()->value();file_put_contents($owned.'/sessions.json',json_encode($tokens));chmod($owned.'/sessions.json',0600);
  echo json_encode(['ok'=>true,'data'=>['account_id'=>$decision->candidate()->accountId()]]);exit;
 }
 if($action==='production_gate') {
  putenv('MXMED_STAFF_ADMISSION_INVITATIONS_ENABLED=1');
  \Identity\StaffAdmission\InvitationComposition::fromIdentity($identity);
  throw new RuntimeException('unexpected_production_composition');
 }
 $p=new PDO('mysql:host=127.0.0.1;port='.getenv('MXMED_DB_PORT').';dbname='.getenv('MXMED_DB_NAME').';charset=utf8mb4','ase_invitation',trim(file_get_contents($owned.'/invitation-db-password')),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
 if(realpath((string)$p->query('SELECT @@datadir')->fetchColumn())!==realpath($owned.'/mysql'))throw new RuntimeException('isolated_datadir_required');
 $testClock=new class($b['clock_offset']??0) implements Clock {
  public function __construct(private int $offset){}
  public function now():DateTimeImmutable{return (new DateTimeImmutable('now',new DateTimeZone('UTC')))->modify(sprintf('%+d seconds',$this->offset));}
 };
 $policy=new \Identity\StaffAdmission\InvitationPolicy(120,'synthetic-only-validity-120s','synthetic-recipient-key-v1',file_get_contents($owned.'/recipient-key'));
 $delivery=new class($owned,$b['delivery_failure']??false) implements \Identity\StaffAdmission\InvitationDeliveryPort {
  public function __construct(private string $owned,private bool $fail){}
  public function deliver(string $id,string $case,string $email,string $token,string $expiry):void {
   if($this->fail)throw new RuntimeException('inert_delivery_failed');
   if(!str_ends_with($email,'@example.test'))throw new RuntimeException('synthetic_email_required');
   $path=$this->owned.'/invitation-'.$id.'.json';$f=fopen($path,'x');if(!$f)throw new RuntimeException('inert_delivery_duplicate');
   chmod($path,0600);fwrite($f,json_encode(['invitation_id'=>$id,'case_id'=>$case,'recipient'=>$email,'token'=>$token,'expires_at'=>$expiry],JSON_THROW_ON_ERROR));fclose($f);
  }
 };
 $resolver=new CanonicalHttpSessionResolver($identity->sessions());
 $service=new \Identity\StaffAdmission\InvitationService($p,$resolver,$policy,$testClock,new RateLimitService($p,new RateLimitKeyHasher((string)getenv('MXMED_PREVIEW_PEPPER')),$clock),$delivery);
 $cookies=isset($tokens[$b['actor']??''])?['__Host-mxmed_session'=>$tokens[$b['actor']]]:[];
 if(in_array($action,['result','csrf'],true))$r=$service->read($cookies,$action,$b['input']);
 else $r=$service->command($cookies,$action,$b['input']);
 echo json_encode(['ok'=>true,'data'=>$r],JSON_THROW_ON_ERROR);
} catch(Throwable $e) {
 // Diagnostics may contain SQL internals; never return SQL or tokens in test evidence.
 $allowed=['admission_denied','admission_invalid','admission_conflict','admission_authentication_required','staff_invitation_production_policy_unavailable'];
 $error=in_array($e->getMessage(),$allowed,true)?$e->getMessage():'internal_failure';
 if($error==='internal_failure')file_put_contents($owned.'/invitation-private-error.log',get_class($e).': '.$e->getMessage());
 echo json_encode(['ok'=>false,'error'=>$error],JSON_THROW_ON_ERROR);
}
