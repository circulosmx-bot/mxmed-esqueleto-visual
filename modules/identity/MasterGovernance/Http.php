<?php
declare(strict_types=1);
namespace Identity\MasterGovernance;
use Identity\Http\{IdentityHttpComposition,CanonicalHttpSessionResolver,GovernanceTransport};
use Identity\Team\ReadFailure;
/** Closed actions only; all effective authority is server-derived. */
final class Http
{
    public static function handle(string $action):void
    {
        \Identity\Team\Http::headers(true);
        try{
            $get=in_array($action,['read','csrf'],true);$method=$get?'GET':'POST';
            if(($_SERVER['REQUEST_METHOD']??'')!==$method){header('Allow: '.$method);throw new ReadFailure(405,'method_not_allowed');}
            if(getenv('MXMED_MASTER_GOVERNANCE_ENABLED')!=='1'||!IdentityHttpComposition::canonicalRuntimeRequested())throw new ReadFailure(503,'service_unavailable');
            if(($_SERVER['PATH_INFO']??'')!=='')throw new ReadFailure(400,'invalid_request');
            $query=$_SERVER['QUERY_STRING']??'';
            if($action==='read'){if(!preg_match('/^case_id=(master_[a-f0-9]{32})$/D',$query,$match))throw new ReadFailure(400,'invalid_request');}
            elseif($query!=='')throw new ReadFailure(400,'invalid_request');
            $length=$_SERVER['CONTENT_LENGTH']??'0';if(!ctype_digit((string)$length)||(int)$length>2048)throw new ReadFailure(413,'payload_too_large');
            $raw=file_get_contents('php://input',false,null,0,2049);if($raw===false||strlen($raw)>2048)throw new ReadFailure(413,'payload_too_large');
            if($get&&$raw!=='')throw new ReadFailure(400,'invalid_request');
            if(!GovernanceTransport::secure($_SERVER,$raw,(string)getenv('MXMED_GOVERNANCE_PROXY_KEY')))throw new ReadFailure(403,'access_denied');
            $identity=IdentityHttpComposition::fromProcessEnvironment();
            // Same-origin browser GET omits Origin; accept only its exact-origin Referer. POST still requires Origin.
            $origin=$_SERVER['HTTP_ORIGIN']??'';
            $sameOrigin=$origin===$identity->allowedOrigin()||($get&&$origin===''&&str_starts_with($_SERVER['HTTP_REFERER']??'',$identity->allowedOrigin().'/'));
            if(!$sameOrigin||($_SERVER['HTTP_SEC_FETCH_SITE']??'')!=='same-origin')throw new ReadFailure(403,'access_denied');
            $identity->pdo()->setAttribute(\PDO::ATTR_EMULATE_PREPARES,false);
            $actor=(new CanonicalHttpSessionResolver($identity->sessions()))->resolve($_COOKIE,$get);
            if(!$actor)throw new ReadFailure(401,'authentication_required');
            Policy::director($identity->pdo(),$actor->accountId(),$actor->principal()->credentialVersion());
            $runtime=Composition::fromIdentity($identity);$runtime->cases->authority($actor);
            if($action==='csrf'){
                $q=$identity->pdo()->prepare("SELECT enrollment_context FROM auth_staff_mfa_factors WHERE account_id=? AND status='ACTIVE'");$q->execute([$actor->accountId()]);
                $rawFactor=$q->fetchColumn();$factor=$rawFactor===false?null:json_decode($rawFactor,true,32,JSON_THROW_ON_ERROR);
                $ready=is_array($factor)&&($factor['subject']['account_id']??null)===$actor->accountId()&&(($factor['action_blocked_until']??null)===null||new \DateTimeImmutable($factor['action_blocked_until'])<=new \DateTimeImmutable());
                self::respond(['csrf_token'=>$identity->csrf()->issueAuthenticated($actor->session()->tokenDigest()),'state'=>$ready?'READY_FOR_EXACT_ACTION':'MISSING_MFA_FACTOR','appointment'=>'MISSING_IDENTITY_REVIEW']);return;
            }
            if($action==='read'){self::respond($runtime->cases->read($actor,$match[1]));return;}
            if(!preg_match('~^application/json(?:\s*;\s*charset=utf-8)?$~i',trim($_SERVER['CONTENT_TYPE']??'')))throw new ReadFailure(415,'json_required');
            $csrf=$_SERVER['HTTP_X_CSRF_TOKEN']??'';
            if(strlen($csrf)>256||!$identity->csrf()->validAuthenticated($csrf,$actor->session()->tokenDigest()))throw new ReadFailure(403,'access_denied');
            $b=self::body($action,$raw);
            $result=match($action){
                'open'=>$runtime->cases->open($actor,$b['operation'],$b['target'],$b['capability'],$b['reason']),
                'confirm'=>$runtime->cases->confirm($actor,$b['case_id'],$b['intent_digest'],$b['email'],$b['password'],['ip'=>$_SERVER['REMOTE_ADDR']??null]),
                'verify'=>$runtime->cases->verify($actor,$b['case_id'],$b['challenge_id'],$b['challenge'],$b['code']),
                'execute'=>$runtime->writer->executePrivileged($_COOKIE,$b['case_id'],$b['proof_id'],$b['proof']),
                'cancel'=>$runtime->cases->close($actor,$b['case_id']),
                'expire'=>$runtime->cases->close($actor,$b['case_id'],true),
                default=>throw new ReadFailure(400,'invalid_request'),
            };self::respond($result);
        }catch(\Throwable $e){
            $message=$e->getMessage();$status=$e instanceof ReadFailure?$e->status:match($message){
                'master_invalid_request'=>400,'master_conflict','governance_conflict','governance_case_expired'=>409,
                'staff_identity_review_source_unavailable'=>503,
                default=>(str_starts_with($message,'master_')&&in_array($message,['master_denied','master_context_denied','master_target_ineligible','master_review_mismatch'],true))||str_starts_with($message,'staff_mfa_')||str_starts_with($message,'governance_reauthentication')||$message==='governance_session_invalidated'?403:503,
            };
            http_response_code($status);
            if(($_SERVER['REQUEST_METHOD']??'')!=='HEAD')self::respond(['error'=>match($status){400=>'invalid_request',401=>'authentication_required',403=>'access_denied',405=>'method_not_allowed',409=>'conflict_refresh_required',413=>'payload_too_large',415=>'json_required',default=>'service_unavailable_reconcile_required'},'retry'=>false]);
        }
    }
    private static function body(string $action,string $raw):array
    {
        try{$o=json_decode($raw,false,4,JSON_THROW_ON_ERROR);}catch(\JsonException){throw new ReadFailure(400,'invalid_request');}
        if(!$o instanceof \stdClass)throw new ReadFailure(400,'invalid_request');$b=(array)$o;
        $keys=match($action){'open'=>['operation','target','capability','reason'],'confirm'=>['case_id','intent_digest','email','password'],'verify'=>['case_id','challenge_id','challenge','code'],'execute'=>['case_id','proof_id','proof'],'cancel','expire'=>['case_id'],default=>[]};
        sort($keys);$actual=array_keys($b);sort($actual);if($keys!==$actual)throw new ReadFailure(400,'invalid_request');
        preg_match_all('/"(?:[^"\\\\]|\\\\.)*"\s*:/s',$raw,$m);if(count($m[0])!==count($keys))throw new ReadFailure(400,'invalid_request');
        foreach($b as $key=>$value)if(!is_string($value)&&!($key==='capability'&&$value===null))throw new ReadFailure(400,'invalid_request');
        if(isset($b['case_id'])&&!preg_match('/^master_[a-f0-9]{32}$/D',$b['case_id']))throw new ReadFailure(400,'invalid_request');
        return $b;
    }
    private static function respond(array $data):void{echo json_encode($data,JSON_THROW_ON_ERROR|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);}
}
