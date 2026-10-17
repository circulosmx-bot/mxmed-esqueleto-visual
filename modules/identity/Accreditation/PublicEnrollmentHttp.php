<?php
declare(strict_types=1);
namespace Identity\Accreditation;

use Identity\Http\{IdentityHttpComposition,GovernanceTransport,GovernanceHttpSecurity,GovernanceHttpDenied,CanonicalHttpSessionResolver};
require_once __DIR__.'/../http/GovernanceHttpSecurity.php';

/** Same Identity, transport, CSRF and account/session services; no login/registration writer. */
final class PublicEnrollmentHttp
{
    private const BROWSER='__Host-mxmed_enrollment_browser';
    private const RETURN_COOKIE='__Host-mxmed_enrollment_return';
    public function __construct(private IdentityHttpComposition $identity,private PublicEnrollmentService $service,private string $transportKey)
    {if($identity->pdo()!==$service->a->pdo||strlen($transportKey)<32)throw new \RuntimeException('enrollment_composition_denied');}
    private static function proofCookie(string $id):string{return '__Host-mxmed_enrollment_'.str_replace('-','',$id);}
    private static function value(array $values,string $key):string{return is_string($values[$key]??null)?$values[$key]:'';}
    private function cookie(string $name,string $value):void
    {setcookie($name,$value,['expires'=>time()+PublicEnrollmentService::TTL,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Strict']);}
    private function secure(array $server,string $raw=''):void
    {if(!GovernanceTransport::secure($server,$raw,$this->transportKey))throw new GovernanceHttpDenied(503);}
    public function returnPath(array $query,array $cookies):?string
    {
        $candidate=$query['next']??null;$id=$cookies[self::RETURN_COOKIE]??'';
        if(array_key_exists('next',$query)){
            if(!is_string($candidate)||!preg_match('~^/public/identity/perfil-medico\.php\?application=([a-f0-9-]{36})$~D',$candidate,$m))return null;
            $id=$m[1];
        }
        if(!is_string($id)||!preg_match('/^[a-f0-9-]{36}$/D',$id))return null;
        try{$this->service->preview($id,self::value($cookies,self::proofCookie($id)),self::value($cookies,self::BROWSER));}
        catch(\Throwable){return null;}
        return '/public/identity/perfil-medico.php?application='.$id;
    }
    public function page(array $query,array $cookies,array $server):array
    {
        $this->secure($server);
        if(isset($query['application'])&&array_diff(array_keys($query),['application'])!==[])throw new \InvalidArgumentException('enrollment_input_invalid');
        $base=['enabled'=>false,'staff'=>false,'email_verified'=>false,'phone_verified'=>false,'requests'=>[],'request'=>null,'csrf_token'=>'','expires_at'=>0,
            'message'=>'Tu solicitud inicial queda pendiente de los siguientes pasos de verificación y revisión. No concede acceso médico.'];
        $e=['available'=>true,'authenticated'=>false,'bound'=>false,'application'=>null,'ticket'=>null,'csrf_token'=>$this->identity->csrf()->issuePreAuth()];
        $actor=(new CanonicalHttpSessionResolver($this->identity->sessions()))->resolve($cookies);
        if($actor!==null){$e['authenticated']=true;$e['csrf_token']=$this->identity->csrf()->issueAuthenticated($actor->session()->tokenDigest());$base['expires_at']=$actor->session()->expiresAt()->getTimestamp();}
        $id=$query['application']??null;
        if(($query['enrollment']??'')==='resume'){
            if($actor===null)throw new GovernanceHttpDenied(401);
            $id=$this->service->latest($actor);if($id===null)throw new \RuntimeException('enrollment_not_found');
        }
        if($id!==null){
            if(!is_string($id))throw new \RuntimeException('enrollment_not_found');
            // Account-scoped resume is independent of an expired public proof.
            if($actor!==null){
                try{$app=$this->service->snapshot($actor,$id);}
                catch(\RuntimeException $error){if($error->getMessage()!=='enrollment_account_denied')throw $error;
                    $app=$this->service->preview($id,self::value($cookies,self::proofCookie($id)),self::value($cookies,self::BROWSER));
                    if($app['bound'])throw new \RuntimeException('enrollment_account_denied');
                }
            }else{$app=$this->service->preview($id,self::value($cookies,self::proofCookie($id)),self::value($cookies,self::BROWSER));}
            $e['application']=$app;$e['bound']=$app['bound'];
            $e['return_path']='/public/identity/perfil-medico.php?application='.$id;
            $e['profile_name']=$app['original_profile_id']!==null?$this->profileName($app['original_profile_id']):null;
        }else{
            require_once dirname(__DIR__,2).'/profiles/services/PublicEnrollmentPresentation.php';
            require_once dirname(__DIR__,2).'/profiles/controllers/PublicProfileController.php';
            $controller=new \Profiles\Controllers\PublicProfileController(new \Profiles\Repositories\PublicProfileRepository($this->identity->pdo()));
            $entry=\Profiles\Services\PublicEnrollmentPresentation::resolve($query,$controller->showByDoctorId(...));
            if(!in_array($entry['mode']??'', ['claim','join'],true))throw new \RuntimeException('enrollment_claim_unavailable');
            $browser=$cookies[self::BROWSER]??null;
            if(!is_string($browser)||!preg_match('/^[a-f0-9]{64}$/D',$browser)){$browser=PublicEnrollmentService::opaque();$this->cookie(self::BROWSER,$browser);}
            $type=$entry['mode']==='claim'?'CLAIM_EXISTING_PROFILE':'REQUEST_NEW_PROFILE';
            $profile=$entry['mode']==='claim'?(string)$query['profile']:null;
            $route='/public/identity/perfil-medico.php?entry=join';
            $referer=(string)($server['HTTP_REFERER']??'');$prefix=$this->identity->allowedOrigin();
            if(str_starts_with($referer,$prefix.'/')){$candidate=substr($referer,strlen($prefix));if(PublicEnrollmentService::publicRoute($candidate))$route=$candidate;}
            $e['ticket']=$this->service->ticket($type,$profile,$route,$browser);$e['profile_name']=$entry['display_name'];$e['mode']=$entry['mode'];
        }
        return $base+['enrollment'=>$e];
    }
    private function profileName(string $id):string
    {return (string)($this->service->a->query('SELECT display_name FROM profiles_doctors WHERE doctor_id=?',[$id])->fetchColumn()?:'Perfil profesional');}
    public function handle(array $server,array $cookies,string $raw):array
    {
        try{
            if(($server['REQUEST_METHOD']??'')!=='POST')throw new GovernanceHttpDenied(405);
            if(strlen($raw)>12000)throw new GovernanceHttpDenied(413);
            if(!str_starts_with(strtolower($server['CONTENT_TYPE']??''),'application/json'))throw new GovernanceHttpDenied(415);
            $this->secure($server,$raw);
            if(($server['HTTP_ORIGIN']??'')!==$this->identity->allowedOrigin()||isset($server['HTTP_SEC_FETCH_SITE'])&&!in_array($server['HTTP_SEC_FETCH_SITE'],['same-origin','none'],true))throw new GovernanceHttpDenied(403);
            $b=json_decode($raw,true,16,JSON_THROW_ON_ERROR);if(!is_array($b)||array_is_list($b))throw new \InvalidArgumentException('enrollment_input_invalid');
            if(!is_string($b['action']??null)||!is_string($b['csrf_token']??null))throw new \InvalidArgumentException('enrollment_input_invalid');
            $action=$b['action']??'';
            $keys=match($action){'start'=>['action','ticket','csrf_token'],'bind','snapshot'=>['action','application_id','csrf_token'],
                'save','submit'=>['action','application_id','csrf_token','expected_version','command_id','intake'],default=>[]};
            if($keys===[]||array_diff(array_keys($b),$keys)!==[])throw new \InvalidArgumentException('enrollment_input_invalid');
            if($action==='start'){
                $token=$b['csrf_token']??'';
                if(!is_string($token))throw new GovernanceHttpDenied(403);
                $actor=(new CanonicalHttpSessionResolver($this->identity->sessions()))->resolve($cookies);
                $valid=$actor!==null?$this->identity->csrf()->validAuthenticated($token,$actor->session()->tokenDigest()):$this->identity->csrf()->validPreAuth($token);
                if(!$valid)throw new GovernanceHttpDenied(403);
                if(!is_string($b['ticket']??null))throw new \InvalidArgumentException('enrollment_input_invalid');
                $result=$this->service->start($b['ticket'],self::value($cookies,self::BROWSER));
                $id=$result['application_id'];$this->cookie(self::proofCookie($id),$result['handle']);$this->cookie(self::RETURN_COOKIE,$id);
                return ['status'=>200,'body'=>['ok'=>true,'data'=>['return_path'=>'/public/identity/perfil-medico.php?application='.$id]]];
            }
            $actor=GovernanceHttpSecurity::access($this->identity->sessions(),$this->identity->csrf(),$this->identity->allowedOrigin(),$server,$cookies,$b,$raw,$this->transportKey);
            $id=$b['application_id']??'';if(!is_string($id))throw new \InvalidArgumentException('enrollment_input_invalid');
            $result=match($action){
                'bind'=>$this->service->bind($actor,$id,self::value($cookies,self::proofCookie($id)),self::value($cookies,self::BROWSER)),
                'snapshot'=>$this->service->snapshot($actor,$id),
                default=>$this->save($actor,$id,$b,$action==='submit')};
            return ['status'=>200,'body'=>['ok'=>true,'data'=>$result]];
        }catch(GovernanceHttpDenied $e){return $this->error($e->status);}
        catch(\InvalidArgumentException|\JsonException|\TypeError){return $this->error(422);}
        catch(\PDOException $e){return $this->error($e->getCode()==='23000'?409:503);}
        catch(\RuntimeException $e){return $this->error(match($e->getMessage()){
            'enrollment_expired'=>410,'enrollment_version_conflict','enrollment_command_conflict','enrollment_submitted_immutable','enrollment_origin_already_used'=>409,
            'enrollment_try_later'=>429,'enrollment_not_found'=>404,'enrollment_proof_denied','enrollment_account_denied','enrollment_claim_unavailable','c4_nonmedical_operator_denied','c4_account_denied'=>403,
            'governance_session_invalidated'=>401,default=>503});}
        catch(\Throwable){return $this->error(503);}
    }
    private function save($actor,string $id,array $b,bool $submit):array
    {
        if(!is_int($b['expected_version']??null)||!is_array($b['intake']??null)||!is_string($b['command_id']??null))throw new \InvalidArgumentException('enrollment_input_invalid');
        return $this->service->save($actor,$id,$b['expected_version'],$b['command_id'],$b['intake'],$submit);
    }
    private function error(int $status):array
    {return ['status'=>$status,'body'=>['ok'=>false,'message'=>match($status){
        401=>'Inicia sesión con tu cuenta personal para continuar.',403=>'No puedes continuar esta solicitud. Vuelve al perfil o al directorio para comenzar de forma segura.',
        404=>'No encontramos una solicitud disponible para tu cuenta.',409=>'La solicitud cambió o ya existe. Actualiza el estado antes de intentar de nuevo.',
        410=>'El inicio de esta solicitud venció. Vuelve al perfil o al directorio y comienza nuevamente.',422=>'Revisa los datos profesionales y completa los campos necesarios.',
        429=>'Espera unos minutos antes de iniciar otra solicitud.',default=>'No pudimos confirmar la operación. Actualiza el estado antes de volver a intentar.'}]];}
    public static function serve(?self $http):never
    {
        header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store, private');header('Referrer-Policy: no-referrer');header('X-Content-Type-Options: nosniff');
        $r=$http?->handle($_SERVER,$_COOKIE,(string)file_get_contents('php://input'))??['status'=>503,'body'=>['ok'=>false,'message'=>'La recepción de solicitudes está pendiente de habilitación.']];
        http_response_code($r['status']);echo json_encode($r['body'],JSON_THROW_ON_ERROR);exit;
    }
}
