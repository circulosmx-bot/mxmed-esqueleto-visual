<?php
declare(strict_types=1);
namespace Profiles\Corrections;
use Identity\Http\{IdentityHttpComposition,GovernanceTransport,GovernanceHttpSecurity,CanonicalHttpSessionResolver};
use Identity\Contracts\AuthenticatedAccessContext;

final class ReportHttp
{
    public const COOKIE='__Host-mxmed_correction_browser';
    public const ACK='Recibimos tu informe. Nuestro equipo revisará la información antes de realizar cualquier cambio.';
    public function __construct(public readonly IdentityHttpComposition $identity,public readonly ReportService $reports,
        public readonly ReportReviewService $review,private string $transportKey)
    {if($reports->pdo!==$identity->pdo()||strlen($transportKey)<32)throw new \RuntimeException('correction_http_composition_required');}
    private function transport(array $server,string $raw,bool $post):void
    {
        if(!GovernanceTransport::secure($server,$raw,$this->transportKey))throw new ReportDenied(503);
        $allowed=$this->identity->allowedOrigin();
        if(($server['HTTP_HOST']??'')!==parse_url($allowed,PHP_URL_HOST).(parse_url($allowed,PHP_URL_PORT)?':'.parse_url($allowed,PHP_URL_PORT):''))throw new ReportDenied(403);
        if(($post&&($server['HTTP_ORIGIN']??'')!==$allowed)
            ||(!$post&&isset($server['HTTP_ORIGIN'])&&$server['HTTP_ORIGIN']!==$allowed)
            ||(isset($server['HTTP_SEC_FETCH_SITE'])&&!in_array($server['HTTP_SEC_FETCH_SITE'],['same-origin','none'],true)))throw new ReportDenied(403);
    }
    public function form(string $profile,string $route,array $server,array $cookies):array
    {
        if(($server['REQUEST_METHOD']??'')!=='GET')throw new ReportDenied(405);
        $this->transport($server,'',false);
        $browser=$cookies[self::COOKIE]??'';
        if(!is_string($browser)||!preg_match('/^[a-f0-9]{64}$/D',$browser)){
            $browser=bin2hex(random_bytes(32));
            setcookie(self::COOKIE,$browser,['expires'=>0,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Strict']);
        }
        $intent=$this->reports->intent($profile,$route,$browser);
        header('Cache-Control: private, no-store');header('Referrer-Policy: same-origin');
        header("Content-Security-Policy: frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
        return $intent;
    }
    public function staffPage(array $server,array $cookies):AuthenticatedAccessContext
    {
        $this->transport($server,'',false);
        $actor=(new CanonicalHttpSessionResolver($this->identity->sessions()))->resolve($cookies);
        if($actor===null)throw new ReportDenied(401);
        $this->review->queue($actor);return $actor;
    }
    public function handle(bool $staff,array $server,array $cookies,string $raw):array
    {
        try{
            if(($server['REQUEST_METHOD']??'')!=='POST')throw new ReportDenied(405);
            if(strlen($raw)>16384||(int)($server['CONTENT_LENGTH']??strlen($raw))>16384)throw new ReportDenied(413);
            if(strtolower(trim(explode(';',(string)($server['CONTENT_TYPE']??''))[0]))!=='application/json')throw new ReportDenied(415);
            $this->transport($server,$raw,true);
            $body=json_decode($raw,true,16,JSON_THROW_ON_ERROR);
            if(!is_array($body)||array_is_list($body))throw new ReportDenied(400);
            if($staff){
                if(!is_string($body['csrf_token']??null))throw new ReportDenied(403);
                $actor=GovernanceHttpSecurity::access($this->identity->sessions(),$this->identity->csrf(),$this->identity->allowedOrigin(),$server,$cookies,$body,$raw,$this->transportKey);
                $result=$this->staff($actor,$body);
            }else{
                ReportCatalog::fields($body,['intent','csrf_token','category','explanation','relation','contact','privacy_ack']);
                if(!is_string($body['intent']??null)||!is_string($body['csrf_token']??null))throw new ReportDenied(403);
                $referer=(string)($server['HTTP_REFERER']??'');$allowed=$this->identity->allowedOrigin();
                if(!str_starts_with($referer,$allowed.'/'))throw new ReportDenied(403);
                $route=substr($referer,strlen($allowed));
                $data=$body;unset($data['intent'],$data['csrf_token']);
                $result=$this->reports->submit($body['intent'],$body['csrf_token'],is_string($cookies[self::COOKIE]??null)?$cookies[self::COOKIE]:'',
                    (string)($server['REMOTE_ADDR']??''),$route,$data)+['message'=>self::ACK];
            }
            return [200,['ok'=>true,'data'=>$result]];
        }catch(ReportDenied $e){return [$e->status,['ok'=>false]];}
        catch(\Identity\Http\GovernanceHttpDenied $e){return [$e->status,['ok'=>false]];}
        catch(\JsonException|\TypeError){return [400,['ok'=>false]];}
        catch(\Throwable){return [503,['ok'=>false]];}
    }
    private function staff(AuthenticatedAccessContext $actor,array $body):array
    {
        $action=$body['action']??null;
        $fields=match($action){
            'queue'=>['page'], 'open'=>['report_id'],
            'begin'=>['report_id','version','decision','email','password'],
            'decide'=>['report_id','version','case_id','challenge_id','challenge','code'],
            default=>throw new ReportDenied(400)};
        ReportCatalog::fields($body,['action','csrf_token',...$fields]);
        foreach($fields as $field)if($field!=='page'&&!array_key_exists($field,$body))throw new ReportDenied(400);
        if(array_key_exists('page',$body)&&!is_int($body['page']))throw new ReportDenied(400);
        if(isset($body['report_id'])&&(!is_string($body['report_id'])||!preg_match('/^[a-f0-9-]{36}$/D',$body['report_id'])))throw new ReportDenied(400);
        if(isset($body['version'])&&(!is_int($body['version'])||$body['version']<1))throw new ReportDenied(400);
        if($action==='begin'&&(!is_array($body['decision'])||!is_string($body['email'])||!is_string($body['password'])||strlen($body['email'])>254||strlen($body['password'])>1024))throw new ReportDenied(400);
        if($action==='decide')foreach(['case_id','challenge_id','challenge','code'] as $field)if(!is_string($body[$field])||strlen($body[$field])>128)throw new ReportDenied(400);
        return match($action){
            'queue'=>$this->review->queue($actor,$body['page']??0),
            'open'=>$this->review->open($actor,$body['report_id']),
            'begin'=>$this->review->begin($actor,$body['report_id'],$body['version'],$body['decision'],$body['email'],$body['password']),
            'decide'=>$this->review->decide($actor,$body['report_id'],$body['version'],$body['case_id'],$body['challenge_id'],$body['challenge'],$body['code'])};
    }
    public static function serve(bool $staff):never
    {
        header('Content-Type: application/json; charset=UTF-8');header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');header('Cross-Origin-Resource-Policy: same-origin');
        $runtime=ReportRuntime::current();
        if($runtime===null){http_response_code(503);echo '{"ok":false}';exit;}
        $raw=file_get_contents('php://input',false,null,0,16385);
        [$status,$body]=$runtime->handle($staff,$_SERVER,$_COOKIE,is_string($raw)?$raw:'');
        http_response_code($status);if($status===405)header('Allow: POST');if($status===429)header('Retry-After: 3600');
        echo json_encode($body,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);exit;
    }
}
