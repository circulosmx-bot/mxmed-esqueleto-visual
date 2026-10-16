<?php
declare(strict_types=1);

use Identity\Http\{CanonicalHttpSessionResolver,GovernanceHttpComposition,IdentityHttpComposition};

require_once dirname(__DIR__,2).'/modules/identity/http/IdentityHttpComposition.php';
IdentityHttpComposition::registerAutoloader();

function governanceJson(array $body,int $status=200):never{http_response_code($status);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');echo json_encode($body,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);exit;}
function governanceBody(string $raw):array{try{$v=json_decode($raw?:'{}',true,32,JSON_THROW_ON_ERROR);return is_array($v)?$v:[];}catch(Throwable){governanceJson(['ok'=>false,'error'=>'INVALID_REQUEST'],400);}}
function governanceHttps(string $raw):bool{return \Identity\Http\GovernanceTransport::secure($_SERVER,$raw,(string)getenv('MXMED_GOVERNANCE_PROXY_KEY'));}

try{
    if(strtoupper((string)($_SERVER['REQUEST_METHOD']??''))!=='POST')governanceJson(['ok'=>false,'error'=>'INVALID_REQUEST'],405);
    if(str_starts_with(trim((string)($_SERVER['PATH_INFO']??''),'/'),'bootstrap/'))governanceJson(['ok'=>false,'error'=>'NOT_FOUND'],404);
    if(getenv('MXMED_IDENTITY_GOVERNANCE_ENABLED')!=='1')governanceJson(['ok'=>false,'error'=>'GOVERNANCE_UNAVAILABLE'],503);
    $raw=(string)file_get_contents('php://input');
    if(!governanceHttps($raw))governanceJson(['ok'=>false,'error'=>'GOVERNANCE_UNAVAILABLE'],503);
    $identity=IdentityHttpComposition::fromProcessEnvironment();$body=governanceBody($raw);
    $session=\Identity\Http\GovernanceHttpSecurity::access($identity->sessions(),$identity->csrf(),$identity->allowedOrigin(),$_SERVER,$_COOKIE,$body,$raw,(string)getenv('MXMED_GOVERNANCE_PROXY_KEY'));
    $governance=GovernanceHttpComposition::fromIdentity($identity);$runtime=$governance->runtime;
    $path=array_values(array_filter(explode('/',trim((string)($_SERVER['PATH_INFO']??''),'/')),'strlen'));
    $result=null;
    if($path===['cases','initial-owner'])$result=$runtime->cases->openInitialOwnerCase($session,trim((string)($body['membership_id']??'')),trim((string)($body['reason']??'')));
    elseif(count($path)===3&&$path[0]==='cases'&&$path[2]==='mfa-challenge')$result=$runtime->cases->issueEndorsementChallenge($session,$path[1],trim((string)($body['email']??'')),(string)($body['password']??''),['ip'=>(string)($_SERVER['REMOTE_ADDR']??'')]);
    elseif(count($path)===3&&$path[0]==='cases'&&$path[2]==='external-evidence')$result=$runtime->cases->bindExternalEvidence($session,$path[1],(string)($body['signed_evidence']??''));
    elseif(count($path)===3&&$path[0]==='cases'&&in_array($path[2],['expire','revoke'],true))$result=$runtime->cases->expireOrRevoke($session,$path[1],$path[2]==='revoke');
    elseif($path===['mfa','verify'])$result=$runtime->mfa->verifyChallenge($session->accountId(),(string)$session->session()->sessionId(),$session->principal()->credentialVersion(),trim((string)($body['challenge_id']??'')),trim((string)($body['challenge']??'')),trim((string)($body['code']??'')));
    elseif(count($path)===3&&$path[0]==='cases'&&$path[2]==='endorse')$result=$runtime->cases->endorse($session,$path[1],trim((string)($body['proof_id']??'')),trim((string)($body['proof']??'')));
    elseif(count($path)===3&&$path[0]==='cases'&&$path[2]==='beneficiary-step-up')$result=$runtime->issueBeneficiaryStepUp($session,$path[1],trim((string)($body['email']??'')),(string)($body['password']??''),['ip'=>(string)($_SERVER['REMOTE_ADDR']??''),'device'=>hash('sha256',(string)($_SERVER['HTTP_USER_AGENT']??''))]);
    elseif(count($path)===3&&$path[0]==='cases'&&$path[2]==='finalize')$result=$runtime->finalizeInitialOwner($session,$path[1],trim((string)($body['step_up_proof']??'')));
    elseif($path===['cases','owner-recovery'])$result=$runtime->cases->openOwnerRecoveryCase($session,trim((string)($body['operation']??'')),trim((string)($body['owner_membership_id']??'')),isset($body['replacement_membership_id'])?trim((string)$body['replacement_membership_id']):null,trim((string)($body['reason']??'')));
    elseif($path===['cases','mfa-recovery'])$result=$runtime->cases->openMfaRecoveryCase($session,trim((string)($body['staff_account_id']??'')),trim((string)($body['reason']??'')));
    elseif(count($path)===3&&$path[0]==='cases'&&$path[2]==='execute-mfa-recovery')$result=$runtime->cases->executeMfaRecovery($session,$path[1],trim((string)($body['factor_id']??'')));
    elseif(count($path)===3&&$path[0]==='cases'&&$path[2]==='execute-recovery')$result=$runtime->cases->executeOwnerRecovery($session,$path[1]);
    else governanceJson(['ok'=>false,'error'=>'NOT_FOUND'],404);
    array_walk_recursive($result,function(&$v):void{if($v instanceof DateTimeInterface)$v=$v->format(DATE_ATOM);});governanceJson(['ok'=>true,'data'=>$result]);
}catch(\Identity\Http\GovernanceHttpDenied $e){governanceJson(['ok'=>false,'error'=>'GOVERNANCE_DENIED'],$e->status);}
catch(Throwable $e){error_log('MXMED_GOVERNANCE_DENIED '.get_class($e));governanceJson(['ok'=>false,'error'=>'GOVERNANCE_DENIED'],403);}
