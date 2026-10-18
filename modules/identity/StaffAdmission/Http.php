<?php
declare(strict_types=1);
namespace Identity\StaffAdmission;
use Identity\Http\{IdentityHttpComposition,CanonicalHttpSessionResolver,GovernanceTransport};
use Identity\Team\ReadFailure;
final class Http
{
    public static function handle(string $action):void
    {
        \Identity\Team\Http::headers(true);
        try{
            $get=in_array($action,['csrf','read','result'],true);$method=$get?'GET':'POST';
            if(($_SERVER['REQUEST_METHOD']??'')!==$method){header('Allow: '.$method);throw new ReadFailure(405,'method_not_allowed');}
            if(getenv('MXMED_STAFF_ADMISSION_FOUNDATION_ENABLED')!=='1'||!IdentityHttpComposition::canonicalRuntimeRequested())throw new ReadFailure(503,'service_unavailable');
            if(($_SERVER['PATH_INFO']??'')!=='')throw new ReadFailure(400,'invalid_request');
            $query=$_SERVER['QUERY_STRING']??'';$b=[];
            if(in_array($action,['read','result'],true)){
                $key=$action==='read'?'case_id':'operation_id';
                if(!preg_match('/^'.$key.'=([a-z0-9_-]+)$/D',$query,$m))throw new ReadFailure(400,'invalid_request');$b=Input::validate($action,[$key=>$m[1]]);
            }elseif($query!=='')throw new ReadFailure(400,'invalid_request');
            $length=$_SERVER['CONTENT_LENGTH']??'0';if(!ctype_digit((string)$length)||(int)$length>2048)throw new ReadFailure(413,'payload_too_large');
            $raw=file_get_contents('php://input',false,null,0,2049);if($raw===false||strlen($raw)>2048)throw new ReadFailure(413,'payload_too_large');
            if($get&&$raw!=='')throw new ReadFailure(400,'invalid_request');
            if(!GovernanceTransport::secure($_SERVER,$raw,(string)getenv('MXMED_GOVERNANCE_PROXY_KEY')))throw new ReadFailure(403,'access_denied');
            $i=IdentityHttpComposition::fromProcessEnvironment();$origin=$_SERVER['HTTP_ORIGIN']??'';
            if(($origin!==$i->allowedOrigin()&&!($get&&$origin===''&&str_starts_with($_SERVER['HTTP_REFERER']??'',$i->allowedOrigin().'/')))||($_SERVER['HTTP_SEC_FETCH_SITE']??'')!=='same-origin')throw new ReadFailure(403,'access_denied');
            $i->pdo()->setAttribute(\PDO::ATTR_EMULATE_PREPARES,false);
            $actor=(new CanonicalHttpSessionResolver($i->sessions()))->resolve($_COOKIE,true);if(!$actor)throw new ReadFailure(401,'authentication_required');
            $service=Composition::fromIdentity($i);
            if($get){$result=$service->read($_COOKIE,$action,$b);if($action!=='result')$result['csrf_token']=$i->csrf()->issueAuthenticated($actor->session()->tokenDigest());}
            else{
                if(!preg_match('~^application/json(?:\s*;\s*charset=utf-8)?$~i',trim($_SERVER['CONTENT_TYPE']??'')))throw new ReadFailure(415,'json_required');
                $csrf=$_SERVER['HTTP_X_CSRF_TOKEN']??'';if(strlen($csrf)>256||!$i->csrf()->validAuthenticated($csrf,$actor->session()->tokenDigest()))throw new ReadFailure(403,'access_denied');
                try{$o=json_decode($raw,false,3,JSON_THROW_ON_ERROR);}catch(\JsonException){throw new ReadFailure(400,'invalid_request');}
                if(!$o instanceof \stdClass)throw new ReadFailure(400,'invalid_request');$b=(array)$o;
                preg_match_all('/"(?:[^"\\\\]|\\\\.)*"\s*:/s',$raw,$matches);
                if(count($matches[0])!==count($b))throw new ReadFailure(400,'invalid_request');
                $result=$service->command($_COOKIE,$action,$b);
            }
            self::respond($result);
        }catch(\Throwable $e){
            $status=$e instanceof ReadFailure?$e->status:match($e->getMessage()){'admission_invalid'=>400,'admission_conflict'=>409,'admission_authentication_required'=>401,'admission_denied'=>403,default=>503};
            http_response_code($status);if(($_SERVER['REQUEST_METHOD']??'')!=='HEAD')self::respond(['error'=>match($status){400=>'invalid_request',401=>'authentication_required',403=>'access_denied',405=>'method_not_allowed',409=>'conflict_refresh_required',413=>'payload_too_large',415=>'json_required',default=>'service_unavailable_reconcile_required'},'retry'=>false]);
        }
    }
    private static function respond(array $r):void{echo json_encode($r,JSON_THROW_ON_ERROR|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);}
}
