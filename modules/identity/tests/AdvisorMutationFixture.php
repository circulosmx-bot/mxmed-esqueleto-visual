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
if(!in_array($phase,['ase-int02a','ase-int02b','ase-int03a'],true))throw new RuntimeException('isolated_phase_required');
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
$service=new InternalGovernanceService($action==='legacy'?$fixture:$identity->pdo(),new CanonicalHttpSessionResolver($identity->sessions()));
if($action==='strict'){
    try{$service->execute(['__Host-mxmed_session'=>$tokens['director']],'grant','qa_advisor','media_review_improve',InternalGovernanceService::ACTIVE_ADVISOR_OPERATIONAL);echo '{"result":"PASS"}';}
    catch(\Throwable $e){fwrite(STDERR,get_class($e).': '.$e->getMessage());exit(1);}
    exit;
}
if($action==='operational'){
    $authority=new \Identity\Services\InternalOperatorAuthority(new CanonicalHttpSessionResolver($identity->sessions()),new \Identity\Repositories\InternalOperatorGrantRepository($identity->pdo()));
    $context=$authority->resolve(['__Host-mxmed_session'=>$tokens['target']],'read','media_review_submission',\Platform\Contracts\RiskLevel::R0);
    $mfa=new \Identity\Services\StaffMfaService($identity->pdo(),base64_encode(random_bytes(32)),'synthetic-key');
    $gate=false;try{$mfa->issueChallenge('qa_target','PHYSICIAN_ENROLLMENT_REVIEW','synthetic-case','qa_advisor','qa_advisor',null);}catch(\RuntimeException $e){$gate=$e->getMessage()==='staff_mfa_canonical_security_required';}
    echo json_encode(['allowed'=>$context!==null,'mfa_context_still_required'=>$gate],JSON_THROW_ON_ERROR);exit;
}
if($action==='legacy'){
    $result=$service->execute(['__Host-mxmed_session'=>$tokens[$argv[2]]],$argv[3],$argv[4],$argv[5]??null);
    echo json_encode($result,JSON_THROW_ON_ERROR);exit;
}
throw new RuntimeException('unknown_fixture_action');
