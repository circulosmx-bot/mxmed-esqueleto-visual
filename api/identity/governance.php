<?php
declare(strict_types=1);

use Identity\Http\{CanonicalHttpSessionResolver,GovernanceHttpComposition,IdentityHttpComposition};

require_once dirname(__DIR__,2).'/modules/identity/http/IdentityHttpComposition.php';
IdentityHttpComposition::registerAutoloader();

function governanceJson(array $body,int $status=200):never{http_response_code($status);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');echo json_encode($body,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);exit;}
function governanceBody():array{$raw=file_get_contents('php://input');try{$v=json_decode($raw?:'{}',true,32,JSON_THROW_ON_ERROR);return is_array($v)?$v:[];}catch(Throwable){governanceJson(['ok'=>false,'error'=>'INVALID_REQUEST'],400);}}
function governanceHttps():bool{$direct=strtolower((string)($_SERVER['HTTPS']??''))==='on';$loop=in_array((string)($_SERVER['REMOTE_ADDR']??''),['127.0.0.1','::1'],true);return $direct||($loop&&strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO']??''))==='https');}

try{
    if(strtoupper((string)($_SERVER['REQUEST_METHOD']??''))!=='POST')governanceJson(['ok'=>false,'error'=>'INVALID_REQUEST'],405);
    if(!governanceHttps())governanceJson(['ok'=>false,'error'=>'GOVERNANCE_UNAVAILABLE'],503);
    $identity=IdentityHttpComposition::fromProcessEnvironment();$allowed=rtrim($identity->allowedOrigin(),'/');$origin=rtrim((string)($_SERVER['HTTP_ORIGIN']??''),'/');$referer=(string)($_SERVER['HTTP_REFERER']??'');
    if(($origin!==''&&$origin!==$allowed)||($origin===''&&!str_starts_with($referer,$allowed.'/')))governanceJson(['ok'=>false,'error'=>'INVALID_REQUEST'],403);
    $body=governanceBody();$session=(new CanonicalHttpSessionResolver($identity->sessions()))->resolve($_COOKIE);
    if($session===null)governanceJson(['ok'=>false,'error'=>'AUTHENTICATION_REQUIRED'],401);
    $csrf=trim((string)($body['csrf_token']??($_SERVER['HTTP_X_CSRF_TOKEN']??'')));
    if(!$identity->csrf()->validAuthenticated($csrf,$session->session()->tokenDigest()))governanceJson(['ok'=>false,'error'=>'INVALID_REQUEST'],403);
    $governance=GovernanceHttpComposition::fromIdentity($identity);$runtime=$governance->runtime;
    $path=array_values(array_filter(explode('/',trim((string)($_SERVER['PATH_INFO']??''),'/')),'strlen'));
    $result=null;
    if($path===['cases','initial-owner'])$result=$runtime->cases->openInitialOwnerCase($session,trim((string)($body['membership_id']??'')),trim((string)($body['reason']??'')));
    elseif(count($path)===3&&$path[0]==='cases'&&$path[2]==='mfa-challenge')$result=$runtime->cases->issueEndorsementChallenge($session,$path[1]);
    elseif($path===['mfa','verify'])$result=$runtime->mfa->verifyChallenge($session->accountId(),(string)$session->session()->sessionId(),$session->principal()->credentialVersion(),trim((string)($body['challenge_id']??'')),trim((string)($body['challenge']??'')),trim((string)($body['code']??'')));
    elseif(count($path)===3&&$path[0]==='cases'&&$path[2]==='endorse')$result=$runtime->cases->endorse($session,$path[1],trim((string)($body['proof_id']??'')),trim((string)($body['proof']??'')));
    elseif(count($path)===3&&$path[0]==='cases'&&$path[2]==='beneficiary-step-up')$result=$runtime->issueBeneficiaryStepUp($session,$path[1],trim((string)($body['email']??'')),(string)($body['password']??''),['ip'=>(string)($_SERVER['REMOTE_ADDR']??''),'device'=>hash('sha256',(string)($_SERVER['HTTP_USER_AGENT']??''))]);
    elseif(count($path)===3&&$path[0]==='cases'&&$path[2]==='finalize')$result=$runtime->finalizeInitialOwner($session,$path[1],trim((string)($body['step_up_proof']??'')));
    elseif($path===['cases','owner-recovery'])$result=$runtime->cases->openOwnerRecoveryCase($session,trim((string)($body['operation']??'')),trim((string)($body['owner_membership_id']??'')),isset($body['replacement_membership_id'])?trim((string)$body['replacement_membership_id']):null,trim((string)($body['reason']??'')));
    elseif($path===['cases','mfa-recovery'])$result=$runtime->cases->openMfaRecoveryCase($session,trim((string)($body['staff_account_id']??'')),trim((string)($body['reason']??'')));
    elseif(count($path)===3&&$path[0]==='cases'&&$path[2]==='execute-mfa-recovery')$result=$runtime->cases->executeMfaRecovery($session,$path[1],trim((string)($body['factor_id']??'')));
    elseif(count($path)===3&&$path[0]==='cases'&&$path[2]==='execute-recovery')$result=$runtime->cases->executeOwnerRecovery($session,$path[1]);
    elseif($path===['bootstrap','first-director']){if(trim((string)($body['target_account_id']??''))!==$session->accountId())throw new RuntimeException('governance_bootstrap_denied');$governance->trustAnchor->stage(trim((string)($body['signed_evidence']??'')));$result=$governance->bootstrap->execute(trim((string)($body['operation_id']??'')),$session->accountId());}
    else governanceJson(['ok'=>false,'error'=>'NOT_FOUND'],404);
    array_walk_recursive($result,function(&$v):void{if($v instanceof DateTimeInterface)$v=$v->format(DATE_ATOM);});governanceJson(['ok'=>true,'data'=>$result]);
}catch(Throwable $e){error_log('MXMED_GOVERNANCE_DENIED '.get_class($e));governanceJson(['ok'=>false,'error'=>'GOVERNANCE_DENIED'],403);}
