<?php
declare(strict_types=1);
namespace Identity\Team;

use Identity\Http\{CanonicalHttpSessionResolver,GovernanceTransport,IdentityHttpComposition};
use Identity\Services\{InternalCapabilityCatalog,InternalGovernanceService};

/** Shared canonical boundary for individually scoped team commands. */
final class MutationHttp
{
    public static function handle(bool $bootstrap,bool $lifecycle=false): void
    {
        Http::headers(true);
        try {
            $method=$bootstrap?'GET':'POST';
            if(($_SERVER['REQUEST_METHOD']??'')!==$method){header('Allow: '.$method);throw new ReadFailure(405,'method_not_allowed');}
            // Explicit deployment gate: source presence never activates a runtime.
            if(getenv('MXMED_TEAM_ADVISOR_MUTATIONS_ENABLED')!=='1'||!IdentityHttpComposition::canonicalRuntimeRequested())throw new ReadFailure(503,'service_unavailable');
            if(($_SERVER['QUERY_STRING']??'')!==''||($_SERVER['PATH_INFO']??'')!=='')throw new ReadFailure(400,'invalid_request');
            $length=$_SERVER['CONTENT_LENGTH']??'0';
            if(!ctype_digit((string)$length))throw new ReadFailure(400,'invalid_request');
            if((int)$length>1024)throw new ReadFailure(413,'payload_too_large');
            $raw=file_get_contents('php://input',false,null,0,1025);
            if($raw===false||strlen($raw)>1024)throw new ReadFailure(413,'payload_too_large');
            if(!GovernanceTransport::secure($_SERVER,$raw,(string)getenv('MXMED_GOVERNANCE_PROXY_KEY')))throw new ReadFailure(403,'access_denied');
            $identity=IdentityHttpComposition::fromProcessEnvironment();
            $origin=$_SERVER['HTTP_ORIGIN']??'';$site=$_SERVER['HTTP_SEC_FETCH_SITE']??'';
            if(($site!==''&&$site!=='same-origin')||($origin!==''&&$origin!==$identity->allowedOrigin())||($origin===''&&(!$bootstrap||$site!=='same-origin'||!str_starts_with($_SERVER['HTTP_REFERER']??'',$identity->allowedOrigin().'/'))))throw new ReadFailure(403,'access_denied');
            $pdo=$identity->pdo();$pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES,false);
            $resolver=new CanonicalHttpSessionResolver($identity->sessions());
            $session=$resolver->resolve($_COOKIE);
            if(!$session)throw new ReadFailure(401,'authentication_required');
            // No privileged token for an ordinary/disabled/suspended account.
            (new ReadService($pdo,$resolver))->authority($_COOKIE);
            if($bootstrap){
                if($raw!=='')throw new ReadFailure(400,'invalid_request');
                self::respond(['csrf_token'=>$identity->csrf()->issueAuthenticated($session->session()->tokenDigest())]);return;
            }
            if(!preg_match('~^application/json(?:\s*;\s*charset=utf-8)?$~i',trim($_SERVER['CONTENT_TYPE']??'')))throw new ReadFailure(415,'json_required');
            $csrf=$_SERVER['HTTP_X_CSRF_TOKEN']??'';
            if(!is_string($csrf)||strlen($csrf)>256||!$identity->csrf()->validAuthenticated($csrf,$session->session()->tokenDigest()))throw new ReadFailure(403,'access_denied');
            $body=self::body($raw,$lifecycle);
            if($lifecycle){
                $result=(new InternalGovernanceService($pdo,$resolver))->execute($_COOKIE,$body['operation'],$body['target'],null,$body['operation']==='remove_advisor'?InternalGovernanceService::ADVISOR_REMOVAL:InternalGovernanceService::ADVISOR_LIFECYCLE);
                self::respond(['result'=>'changed','operation'=>$body['operation'],'target'=>$body['target'],'staff_status'=>$result['status'],'refresh_required'=>true,'consumer_authority'=>'independently_required']);return;
            }
            (new InternalGovernanceService($pdo,$resolver))->execute($_COOKIE,$body['operation'],$body['target'],$body['capability'],InternalGovernanceService::ACTIVE_ADVISOR_OPERATIONAL);
            self::respond(['result'=>'changed','operation'=>$body['operation'],'target'=>$body['target'],'capability'=>$body['capability'],'stored_grant'=>$body['operation']==='grant'?'ACTIVE':'REVOKED','refresh_required'=>true,'consumer_authority'=>'independently_required']);
        } catch(\Throwable $e) {
            $status=$e instanceof ReadFailure?$e->status:match($e->getMessage()){
                'governance_conflict'=>409,
                'governance_denied','governance_target_ineligible','governance_account_not_found'=>403,
                'governance_invalid_request','governance_unknown_capability','governance_invalid_operation'=>400,
                default=>503,
            };
            // No SQL detail, cookie, CSRF or session material in response/logs; no retry.
            http_response_code($status);
            if(($_SERVER['REQUEST_METHOD']??'')!=='HEAD')self::respond(['error'=>match($status){401=>'authentication_required',403=>'access_denied',409=>'conflict_refresh_required',405=>'method_not_allowed',413=>'payload_too_large',415=>'json_required',400=>'invalid_request',default=>'service_unavailable_refresh_required'},'refresh_required'=>in_array($status,[409,503],true)]);
        }
    }

    private static function body(string $raw,bool $lifecycle): array
    {
        try{$object=json_decode($raw,false,4,JSON_THROW_ON_ERROR);}catch(\JsonException){throw new ReadFailure(400,'invalid_request');}
        if(!$object instanceof \stdClass)throw new ReadFailure(400,'invalid_request');
        $body=(array)$object;$keys=array_keys($body);sort($keys);
        if($keys!==($lifecycle?['operation','target']:['capability','operation','target']))throw new ReadFailure(400,'invalid_request');
        foreach($body as $value)if(!is_string($value))throw new ReadFailure(400,'invalid_request');
        // Flat JSON object: count key tokens including duplicates before PHP collapses them.
        preg_match_all('/"(?:[^"\\\\]|\\\\.)*"\s*:/s',$raw,$matches);
        if(count($matches[0])!==($lifecycle?2:3))throw new ReadFailure(400,'invalid_request');
        if(!in_array($body['operation'],$lifecycle?['suspend','reactivate','remove_advisor']:['grant','revoke'],true)||!preg_match('/^[A-Za-z0-9_.:-]{1,64}$/D',$body['target']))throw new ReadFailure(400,'invalid_request');
        if($lifecycle)return $body;
        $definition=InternalCapabilityCatalog::all()[$body['capability']]??null;
        if(!$definition||$definition['kind']!=='operational'||$definition['delegable']!==true)throw new ReadFailure(400,'invalid_request');
        return $body;
    }

    private static function respond(array $data): void
    {
        echo json_encode($data,JSON_THROW_ON_ERROR|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
    }
}
