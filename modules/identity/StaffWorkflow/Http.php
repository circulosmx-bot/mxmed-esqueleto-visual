<?php
declare(strict_types=1);
namespace Identity\StaffWorkflow;
use Identity\Http\{IdentityHttpComposition,CanonicalHttpSessionResolver,GovernanceTransport};
/** Normal endpoint is closed; isolated server router supplies explicitly owned composition. */
final class Http
{
    public static function handle(?array $services=null):void
    {
        \Identity\Team\Http::headers(true);
        try{
            if($services===null)throw new \RuntimeException('workflow_production_policy_unavailable');
            [$i,$flow,$cases,$invites,$media,$writer]=$services;
            if(($_SERVER['REQUEST_METHOD']??'')!=='POST'||($_SERVER['QUERY_STRING']??'')!=='')throw new \RuntimeException('workflow_invalid');
            $raw=file_get_contents('php://input',false,null,0,200001);if(!is_string($raw)||strlen($raw)>200000)throw new \RuntimeException('workflow_invalid');
            if(!GovernanceTransport::secure($_SERVER,$raw,(string)getenv('MXMED_GOVERNANCE_PROXY_KEY'))||($_SERVER['HTTP_ORIGIN']??'')!==$i->allowedOrigin()||($_SERVER['HTTP_SEC_FETCH_SITE']??'')!=='same-origin')throw new \RuntimeException('workflow_denied');
            $a=(new CanonicalHttpSessionResolver($i->sessions()))->resolve($_COOKIE,true);if(!$a)throw new \RuntimeException('workflow_denied');
            if(!preg_match('~^application/json(?:;.*)?$~',$_SERVER['CONTENT_TYPE']??''))throw new \RuntimeException('workflow_invalid');
            $b=json_decode($raw,true,12,JSON_THROW_ON_ERROR);if(!is_array($b)||!is_string($b['action']??null)||!is_array($b['input']??null)||array_keys($b)!==['action','input'])throw new \RuntimeException('workflow_invalid');
            // Reject duplicate object keys, including nested objects, before authority/writes.
            preg_match_all('/"(?:[^"\\\\]|\\\\.)*"\s*:/s',$raw,$matches);
            $count=function($v)use(&$count){if(!is_array($v))return 0;$n=array_is_list($v)?0:count($v);foreach($v as $child)$n+=$count($child);return $n;};if(count($matches[0])!==$count($b))throw new \RuntimeException('workflow_invalid');
            $action=$b['action'];$input=$b['input'];
            if($action==='bootstrap'){
                Service::keys($input,[]);
                $q=$i->pdo()->prepare('SELECT governance_class,status FROM internal_staff WHERE account_id=?');$q->execute([$a->accountId()]);$staff=$q->fetch(\PDO::FETCH_ASSOC);
                $r=['csrf_token'=>$i->csrf()->issueAuthenticated($a->session()->tokenDigest()),'director'=>$staff&&$staff['governance_class']==='DIRECTOR'&&$staff['status']==='ACTIVE','account_id'=>$a->accountId(),'synthetic'=>true];
            }else{
                if(!$i->csrf()->validAuthenticated($_SERVER['HTTP_X_CSRF_TOKEN']??'',$a->session()->tokenDigest()))throw new \RuntimeException('workflow_denied');
                // This composition is exclusively synthetic. Validate before the canonical write,
                // rather than discovering the reserved-domain restriction after commit/delivery.
                if(in_array($action,['issue','reissue'],true)){
                    $validated=\Identity\StaffAdmission\InvitationInput::validate($action,$input);
                    if(!str_ends_with($validated['recipient_email'],'@example.test')){
                        http_response_code(400);echo json_encode(['error'=>'synthetic_recipient_required','retry'=>false]);return;
                    }
                }
                $r=match($action){
                    'prepare','correct','confirm','update','revoke'=>$cases->command($_COOKIE,$action,$input),
                    'case_result'=>$cases->read($_COOKIE,'result',$input),
                    'invitation_status'=>$invites->read($_COOKIE,'case_status',$input),
                    'case'=>$cases->read($_COOKIE,'read',$input),
                    'issue','reissue','accept'=>$invites->command($_COOKIE,$action,$input),
                    'invitation_result'=>$invites->read($_COOKIE,'result',$input),
                    'upload'=>self::upload($media,$input),
                    'media_status'=>$flow->mediaStatus($_COOKIE,$input),
                    'media_result'=>$media->result($_COOKIE,$input),
                    'submit'=>$flow->submit($_COOKIE,$input),
                    'cases'=>$flow->cases($_COOKIE,$input),
                    'view'=>self::view($flow,$input),
                    'challenge'=>$flow->challenge($_COOKIE,$input),
                    'verify'=>$flow->verify($_COOKIE,$input),
                    'evidence'=>$flow->readEvidence($_COOKIE,$input),
                    'decide'=>$flow->decide($_COOKIE,$input),
                    'admit'=>self::admit($writer,$flow,$input),
                    default=>throw new \RuntimeException('workflow_invalid')
                };
            }
            echo json_encode($r,JSON_THROW_ON_ERROR|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
        }catch(\Throwable $e){
            $m=$e->getMessage();$status=str_contains($m,'invalid')?400:(str_contains($m,'conflict')||str_contains($m,'stale')?409:(str_contains($m,'denied')||str_contains($m,'required')?403:503));
            if($status===403&&isset($flow,$input)&&is_array($input)){try{$flow->denial($_COOKIE,$input);}catch(\Throwable){$status=503;}}
            http_response_code($status);echo json_encode(['error'=>$status===409?'conflict_refresh_required':($status===400?'invalid_request':($status===403?'access_denied':'unavailable_reconcile_required')),'retry'=>false]);
        }
    }
    private static function view(Service $s,array $b):array{Service::keys($b,['case_id']);return $s->view($_COOKIE,$b['case_id']);}
    private static function upload($media,array $b):array
    {Service::keys($b,['metadata','base64']);if(!is_string($b['base64'])||strlen($b['base64'])>90000)throw new \RuntimeException('workflow_invalid');$bytes=base64_decode($b['base64'],true);if(!is_string($bytes))throw new \RuntimeException('workflow_invalid');return $media->write($_COOKIE,$b['metadata'],$bytes);}
    private static function admit($writer,Service $flow,array $b):array
    {Service::keys($b,['case_id','intent_id','proof_id','proof']);$q=$flow->query('SELECT bound_account_id FROM auth_staff_admission_cases WHERE case_id=?',[$b['case_id']]);$target=$q->fetchColumn();if(!is_string($target))throw new \RuntimeException('workflow_denied');return $writer->completeAdmission($_COOKIE,$target,$flow,$b);}
}
