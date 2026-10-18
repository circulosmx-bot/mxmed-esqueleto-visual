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
if(in_array($action,['sessions','refresh_sessions'],true)){
    $authentication=new CredentialAuthenticationService(new IdentityAccountRepository($fixture),new AccountCredentialRepository($fixture),new RateLimitService($fixture,new RateLimitKeyHasher((string)getenv('MXMED_PREVIEW_PEPPER')),$clock),$clock);
    $tokens=[];
    foreach($action==='refresh_sessions'?['director','master']:['director','master','advisor','target','expired','revoked','suspended','disabled','changed','outsider'] as $actor){
        $decision=$authentication->authenticate('qa_'.$actor.'@example.test',trim(file_get_contents($owned.'/fixture-password')),[]);
        if(!$decision->isAllowed())throw new RuntimeException('synthetic_authentication_failed');
        $sessions=$identity->sessions();
        if($actor==='expired'){
            $past=new class implements Clock{public function now():DateTimeImmutable{return new DateTimeImmutable('-3700 seconds');}};
            $store=SessionStoreFactory::create('local',['driver'=>'valkey','prefix'=>'mxmed:gate4d:preview:session:','explicit_preview_flag'=>true],new PreviewValkeyClient('127.0.0.1',(int)getenv('MXMED_SESSION_STORE_PORT')),$past);
            $sessions=new SessionService($store,new SessionTokenCodec((string)getenv('MXMED_PREVIEW_PEPPER')),$past,new SessionPolicy(),new PdoSessionAccountStateAdapter($identity->accounts(),$identity->credentials()));
        }
        $created=$sessions->create($decision->candidate());if(!$created->allowed())throw new RuntimeException('synthetic_session_failed');
        $tokens[$actor]=$created->token()->value();if($actor==='revoked')$sessions->logout($tokens[$actor]);
    }
    echo json_encode($tokens,JSON_THROW_ON_ERROR);exit;
}
$tokens=json_decode(file_get_contents($owned.'/sessions.json'),true,512,JSON_THROW_ON_ERROR);
$p=$identity->pdo();
if($action==='deployment'){
 $r=['contract_version'=>2,'state'=>'approved_for_governance','authorization_reference'=>'synthetic-staff-foundation-only','allowed_actions'=>['staff_admission_foundation'],'target_account_ids'=>[],
 'expires_at'=>(new DateTimeImmutable('+2 hours'))->format(DATE_ATOM),...\Identity\Operators\OperatorDeploymentSchema::identity($p),
 'schema_fingerprint'=>\Identity\Operators\OperatorDeploymentSchema::fingerprint($p),'source_hash'=>\Identity\Http\GovernanceDeploymentAuthorization::sourceHash(),
 'staff_admission_source_sha256'=>\Identity\StaffAdmission\Composition::sourceHash(),
 'staff_privacy_reference'=>'synthetic-no-personal-data','staff_retention_reference'=>'synthetic-dispose-owned-instance','staff_custody_reference'=>'synthetic-private-owned-files',
 'organization_id'=>getenv('MXMED_GOVERNANCE_ORGANIZATION_ID'),'environment'=>$identity->environment(),
 'trust_key_sha256'=>hash('sha256',(string)getenv('MXMED_GOVERNANCE_TRUST_ANCHOR_PUBLIC_KEY')),'mfa_key_id'=>getenv('MXMED_STAFF_MFA_KEY_ID'),'mfa_key_sha256'=>hash('sha256',(string)getenv('MXMED_STAFF_MFA_ENCRYPTION_KEY'))];
 $bytes=json_encode($r,JSON_THROW_ON_ERROR);file_put_contents($owned.'/admission-deployment.json',$bytes);chmod($owned.'/admission-deployment.json',0600);echo hash('sha256',$bytes);exit;
}
$b=json_decode(stream_get_contents(STDIN),true,16,JSON_THROW_ON_ERROR);
try{
 if($action==='bind'){
  // Fixture only: no product method, endpoint or environment selector can perform this handoff.
  $q=$fixture->prepare("UPDATE auth_staff_admission_cases SET bound_account_id=?,binding_credential_version=1,binding_reference='synthetic-cli-binding' WHERE case_id=? AND bound_account_id IS NULL AND state='PREPARING'");
  $q->execute([$b['account'],$b['case_id']]);if($q->rowCount()!==1)throw new RuntimeException('fixture_binding_conflict');$r=['bound'=>true];
 }elseif($action==='csrf'){
  $actor=(new CanonicalHttpSessionResolver($identity->sessions()))->resolve(['__Host-mxmed_session'=>$tokens[$b['actor']]]);
  $r=['csrf_token'=>$identity->csrf()->issueAuthenticated($actor->session()->tokenDigest())];
 }elseif($action==='master_denied'){
  $security=new \Identity\Services\GovernanceSecurityContext($identity->sessions(),$identity->authentication());
  $mfa=new \Identity\Services\StaffMfaService($p,base64_encode(random_bytes(32)),'synthetic-only',security:$security);
  $cases=new \Identity\MasterGovernance\Cases($p,$security,$mfa,new \Identity\Adapters\UnavailableStaffIdentityReview());
  $actor=(new CanonicalHttpSessionResolver($identity->sessions()))->resolve(['__Host-mxmed_session'=>$tokens['director']]);
  $cases->open($actor,'INTERNAL_MASTER_APPOINT','qa_target',null,'Synthetic forbidden appointment');throw new RuntimeException('unexpected_review');
 }else{
  $service=new \Identity\StaffAdmission\Service($p,new CanonicalHttpSessionResolver($identity->sessions()));
  $r=$service->command(['__Host-mxmed_session'=>$tokens[$b['actor']]],$b['command'],$b['input']);
 }
 echo json_encode(['ok'=>true,'data'=>$r],JSON_THROW_ON_ERROR);
}catch(Throwable $e){echo json_encode(['ok'=>false,'error'=>$e->getMessage()],JSON_THROW_ON_ERROR);}
