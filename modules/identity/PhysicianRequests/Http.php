<?php
declare(strict_types=1);
namespace Identity\PhysicianRequests;
use Identity\Http\{CanonicalHttpSessionResolver,GovernanceTransport};
/** Explicit injected composition only. The normal endpoint remains unavailable. */
final class Http
{
    private static function keys(array $input,array $keys):void
    {if(array_diff(array_keys($input),$keys)!==[]||array_diff($keys,array_keys($input))!==[])throw new \InvalidArgumentException('physician_request_invalid');}
    public static function handle(?array $services):void
    {
        header('Content-Type: application/json; charset=utf-8');header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');header('Cross-Origin-Resource-Policy: same-origin');header('Referrer-Policy: no-referrer');
        try{
            if($services===null)throw new \RuntimeException('physician_request_runtime_unavailable');
            $i=$services['identity'];$s=$services['requests'];$actor=(new CanonicalHttpSessionResolver($i->sessions()))->resolve($_COOKIE,true);
            if($actor===null)throw new \RuntimeException('physician_request_authentication_required');
            $method=$_SERVER['REQUEST_METHOD']??'';$raw=$method==='POST'?file_get_contents('php://input',false,null,0,16001):'';
            if(!is_string($raw)||strlen($raw)>16000||!GovernanceTransport::secure($_SERVER,$raw,(string)getenv('MXMED_GOVERNANCE_PROXY_KEY')))throw new \RuntimeException('physician_request_transport_denied');
            if($method==='GET'){
                if(array_diff(array_keys($_GET),['mode','request_id','doctor_id','query','case_id','file_id'])||$_POST||$_FILES)throw new \InvalidArgumentException('physician_request_invalid');
                foreach($_GET as $value)if(!is_string($value))throw new \InvalidArgumentException('physician_request_invalid');
                $mode=$_GET['mode']??'read';
                if($mode==='evidence'){
                    self::keys($_GET,['mode','request_id','file_id']);$r=$s->readEvidence($actor,$_GET['request_id'],$_GET['file_id']);header('Content-Type: '.$r['mime']);header('Content-Security-Policy: sandbox');header('Content-Disposition: inline; filename="documento-solicitud"');echo $r['bytes'];return;
                }
                $result=match($mode){
                    'read'=>$s->read($actor,$_GET['request_id']??null,$_GET['doctor_id']??null,$_GET['query']??''),
                    'applicant'=>$s->applicantRead($actor,$_GET['request_id']??''),
                    'result'=>$s->result($actor,$_GET['case_id']??''),
                    'history'=>self::history($services,$actor,$_GET),
                    'ownership'=>self::owner($services,$_GET['request_id']??'')->status($actor),
                    default=>throw new \InvalidArgumentException('physician_request_invalid')};
                $result['csrf_token']=$i->csrf()->issueAuthenticated($actor->session()->tokenDigest());
            }elseif($method==='POST'){
                if($_GET||$_FILES||($_SERVER['HTTP_ORIGIN']??'')!==$i->allowedOrigin()||($_SERVER['HTTP_SEC_FETCH_SITE']??'')!=='same-origin'||!$i->csrf()->validAuthenticated($_SERVER['HTTP_X_CSRF_TOKEN']??'',$actor->session()->tokenDigest()))throw new \RuntimeException('physician_request_csrf_denied');
                if(!preg_match('~^application/json(?:;.*)?$~',$_SERVER['CONTENT_TYPE']??''))throw new \InvalidArgumentException('physician_request_invalid');
                $b=json_decode($raw,true,12,JSON_THROW_ON_ERROR);if(!is_array($b))throw new \InvalidArgumentException('physician_request_invalid');self::keys($b,['action','input']);
                if(!is_string($b['action'])||!is_array($b['input']))throw new \InvalidArgumentException('physician_request_invalid');
                preg_match_all('/"(?:[^"\\\\]|\\\\.)*"\s*:/s',$raw,$matches);$count=function($v)use(&$count){if(!is_array($v))return 0;$n=array_is_list($v)?0:count($v);foreach($v as $child)$n+=$count($child);return $n;};if(count($matches[0])!==$count($b))throw new \InvalidArgumentException('physician_request_invalid');
                $v=$b['input'];$action=$b['action'];
                $fields=match($action){
                    'begin'=>['request_id','intake_version','review_version','revision_token','decision','reason','email','password'],
                    'verify'=>['case_id','challenge_id','challenge','code'],
                    'decide'=>['case_id','proof_id','proof','confirmed'],
                    'link_evidence'=>['request_id','evidence_request_id'],
                    'respond'=>['request_id','resolution_id','text'],
                    'owner_begin','owner_prepare'=>['request_id'],
                    'owner_challenge','owner_stepup'=>['request_id','email','password'],
                    'owner_verify'=>['request_id','challenge_id','challenge','code'],
                    'owner_endorse'=>['request_id','proof_id','proof'],
                    'owner_finalize'=>['request_id','proof'],
                    default=>throw new \InvalidArgumentException('physician_request_invalid')};self::keys($v,$fields);
                foreach($v as $k=>$value){if(in_array($k,['intake_version','review_version'],true)){if(!is_int($value)||$value<0)throw new \InvalidArgumentException('physician_request_invalid');}elseif($k==='confirmed'){if(!is_bool($value))throw new \InvalidArgumentException('physician_request_invalid');}elseif(!is_string($value))throw new \InvalidArgumentException('physician_request_invalid');}
                $result=match($action){
                    'begin'=>$s->begin($actor,$v['request_id'],$v['intake_version'],$v['review_version'],$v['revision_token'],$v['decision'],$v['reason'],$v['email'],$v['password']),
                    'verify'=>$s->verify($actor,$v['case_id'],$v['challenge_id'],$v['challenge'],$v['code']),
                    'decide'=>$s->decide($actor,$v['case_id'],$v['proof_id'],$v['proof'],$v['confirmed']),
                    'link_evidence'=>$s->linkEvidence($actor,$v['request_id'],$v['evidence_request_id']),
                    'respond'=>$s->respond($actor,$v['request_id'],$v['resolution_id'],$v['text']),
                    'owner_begin'=>self::owner($services,$v['request_id'])->begin($actor),
                    'owner_prepare'=>self::owner($services,$v['request_id'])->prepareMembership($actor),
                    'owner_challenge'=>self::owner($services,$v['request_id'])->challenge($actor,$v['email'],$v['password']),
                    'owner_verify'=>self::ownerVerify($services,$actor,$v),
                    'owner_endorse'=>self::owner($services,$v['request_id'])->endorse($actor,$v['proof_id'],$v['proof']),
                    'owner_stepup'=>self::owner($services,$v['request_id'])->applicantStepUp($actor,$v['email'],$v['password']),
                    'owner_finalize'=>self::owner($services,$v['request_id'])->finalize($actor,$v['proof']),
                };
            }else{http_response_code(405);header('Allow: GET, POST');echo '{"ok":false,"error":"method_not_allowed"}';return;}
            echo json_encode(['ok'=>true,'data'=>$result],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
        }catch(\Throwable $e){
            $m=$e->getMessage();$status=$m==='physician_request_authentication_required'?401:($e instanceof \InvalidArgumentException||$e instanceof \JsonException?400:(str_contains($m,'not_found')?404:(str_contains($m,'conflict')||str_contains($m,'stale')?409:(str_contains($m,'denied')||str_contains($m,'required')?403:503))));
            if($status===403&&isset($s,$actor)&&$actor!==null){try{$s->denial($actor);}catch(\Throwable){$status=503;}}
            if($status===503)error_log('physician_request_unavailable: '.get_class($e).' '.$m);
            elseif(in_array($status,[403,409],true)&&preg_match('/^[a-z_]{1,100}$/D',$m))error_log('physician_request_rejected: '.$m);
            http_response_code($status);echo json_encode(['ok'=>false,'error'=>match($status){401=>'authentication_required',400=>'invalid_request',404=>'not_found',409=>'conflict_refresh_required',403=>'access_denied',default=>'unavailable_reconcile_required'},'retry'=>false]);
        }
    }
    private static function owner(array $s,string $id):RequestOwnershipService
    {return new RequestOwnershipService($s['identity'],$s['security'],$s['mfa'],$s['composition']->evidence,$id);}
    private static function ownerVerify(array $s,$actor,array $v):array
    {
        $status=self::owner($s,$v['request_id'])->status($actor);$case=$status['case_id'];
        $q=$s['identity']->pdo()->prepare('SELECT challenge_id FROM auth_staff_mfa_challenges WHERE challenge_id=? AND case_id=? AND actor_account_id=?');$q->execute([$v['challenge_id'],$case,$actor->accountId()]);if($q->fetchColumn()!==$v['challenge_id'])throw new \RuntimeException('physician_request_case_denied');
        return $s['mfa']->verifyChallenge($actor->accountId(),(string)$actor->session()->sessionId(),$actor->principal()->credentialVersion(),$v['challenge_id'],$v['challenge'],$v['code']);
    }
    private static function history(array $s,$actor,array $q):array
    {
        return (new HistoryReader($s['identity']->pdo(),$actor,$s['requests']->a->scope))->read($q['doctor_id']??null,$q['request_id']??null);
    }
}
