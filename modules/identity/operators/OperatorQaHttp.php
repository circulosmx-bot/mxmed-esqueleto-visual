<?php
declare(strict_types=1);
namespace Identity\Operators;
use Identity\Http\IdentityHttpComposition;
use Platform\Services\{CanonicalAuditWriter,CanonicalAuditPolicyRegistry,CanonicalAuditMetadataSanitizer,TrustedAuditContextValidator,RandomAuditUuidProvider,SystemAuditUtcClock,HmacSha256AuditIpHasher,EnvironmentAuditSecretProvider,CoarseAuditUserAgentSummarizer,CanonicalAuditSealer,CanonicalAuditSerializer,AuditV1PhysicalMapper};
use PDO;

/** Only routed by the isolated front controller. Every other private path is denied there. */
final class OperatorQaHttp
{
    public IdentityHttpComposition $identity;
    public ProfileOperatorService $operators;
    public IsolatedInvitationOutbox $outbox;
    public PDO $pdo;
    public function __construct()
    {
        if(!defined('MXMED_OP02_ROUTED')||MXMED_OP02_ROUTED!==true)throw new \RuntimeException('op02_http_boundary_required');
        OperatorQaConfiguration::assertEnabled();$this->identity=IdentityHttpComposition::fromProcessEnvironment();$this->pdo=$this->identity->pdo();
        $this->outbox=new IsolatedInvitationOutbox($this->pdo);
        $writer=new CanonicalAuditWriter(CanonicalAuditPolicyRegistry::canonical(),new CanonicalAuditMetadataSanitizer(),new TrustedAuditContextValidator(),new RandomAuditUuidProvider(),new SystemAuditUtcClock(),new HmacSha256AuditIpHasher(new EnvironmentAuditSecretProvider()),new CoarseAuditUserAgentSummarizer(),new IsolatedOperatorAuditTransaction($this->pdo),new CanonicalAuditSealer(new CanonicalAuditSerializer()),new AuditV1PhysicalMapper());
        $this->operators=ProfileOperatorService::isolated($this->identity,$writer,$this->outbox);
    }
    public static function json(array $data,int $status=200): never
    {http_response_code($status);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_THROW_ON_ERROR);exit;}
    public static function deny(int $status=403): never
    {self::json(['ok'=>false,'error'=>$status===401?'authentication_required':'forbidden','message'=>'No tienes autorización para realizar esta acción. Solicita al médico titular que revise tus permisos.'],$status);}
    public function session(): string
    {
        $token=(string)($_COOKIE['__Host-mxmed_session']??'');$d=$this->identity->sessions()->validate($token);
        if(!$d->allowed())self::deny(401);
        $origin=(string)($_SERVER['HTTP_ORIGIN']??'');if($origin!==''&&$origin!==OperatorQaConfiguration::ORIGIN)self::deny();
        return $token;
    }
    public function csrf(string $session): string
    {
        $csrf=(string)($_SERVER['HTTP_X_CSRF_TOKEN']??'');$d=$this->identity->sessions()->validate($session);
        if(!$d->allowed())self::deny(401);
        if(!$this->identity->csrf()->validAuthenticated($csrf,$d->record()->tokenDigest()))self::deny();
        if(!in_array($_SERVER['REQUEST_METHOD'],['GET','HEAD'],true)&&($_SERVER['HTTP_ORIGIN']??'')!==OperatorQaConfiguration::ORIGIN)self::deny();
        return $csrf;
    }
    public function body(): array
    { $v=json_decode(file_get_contents('php://input'),true);if(!is_array($v)||array_is_list($v))self::json(['ok'=>false,'message'=>'Revisa los datos enviados.'],400);return $v; }
    private function profile(array $state,array $input=[]): string
    {
        $id=$state['profile']['id']??null;if(!is_string($id))self::deny();
        foreach(['doctor_id','profile_doctor_id','owner','account_id','actor_id','actor_role','created_by_id','created_by_role','operator_id'] as $key)
            if(array_key_exists($key,$input))self::deny();
        if(isset($_GET['doctor_id'])&&$_GET['doctor_id']!==$id)self::deny();
        return $id;
    }
    public function route(string $path): never
    {
        $session=$this->session();$state=$this->operators->snapshot($session);$method=$_SERVER['REQUEST_METHOD'];
        if($path==='/api/identity/index.php/current-session'&&$method==='GET'){
            $d=$this->identity->sessions()->validate($session);
            self::json(['authenticated'=>true,'state'=>'AUTHENTICATED','account_status'=>'active','application_access'=>['allowed'=>true,'reason'=>'isolated_bounded_operator_workspace','context'=>['workspace'=>'agenda_operators']],
                'csrf_token'=>$this->identity->csrf()->issueAuthenticated($d->record()->tokenDigest()),'idle_expires_at'=>$d->record()->expiresAt()->format(DATE_ATOM),'absolute_expires_at'=>$d->record()->absoluteExpiresAt()->format(DATE_ATOM)]);
        }
        $csrf=$this->csrf($session);
        if($path==='/api/profile-operators/index.php/bootstrap'&&$method==='GET'){
            $state['offices']=[];$state['booking_subjects']=[];
            if(in_array(true,$state['capabilities'],true)){
                $s=$this->pdo->prepare('SELECT consultorio_id,titulo FROM consultorios WHERE doctor_id=? ORDER BY consultorio_id');$s->execute([$state['profile']['id']]);$state['offices']=$s->fetchAll();
            }
            if(($state['capabilities']['agenda.appointments.create']??false)===true){
                $s=$this->pdo->prepare("SELECT p.patient_id,p.display_name FROM patients_patients p JOIN patients_doctor_links l ON l.patient_id=p.patient_id WHERE l.doctor_id=? AND l.status='active' AND p.status='active' ORDER BY p.display_name LIMIT 50");$s->execute([$state['profile']['id']]);$state['booking_subjects']=$s->fetchAll();
            }
            self::json(['ok'=>true,'data'=>$state]);
        }
        if($path==='/api/profile-operators/index.php/overview'&&$method==='GET')self::json(['ok'=>true,'data'=>$this->operators->overview($session,$this->profile($state))]);
        if($path==='/api/profile-operators/index.php/audit'&&$method==='GET'){
            $profile=$this->profile($state);$this->operators->overview($session,$profile);
            $s=$this->pdo->prepare('SELECT action,outcome,real_actor_reference,occurred_at_utc,metadata_json FROM platform_audit_events WHERE resource_reference=? ORDER BY occurred_at_utc DESC LIMIT 30');$s->execute([$profile]);self::json(['ok'=>true,'data'=>$s->fetchAll()]);
        }
        if($path==='/api/profile-operators/index.php/command'&&$method==='POST'){
            $body=$this->body();$action=(string)($body['action']??'');$input=$body['input']??[];if(!is_array($input))self::deny();
            if(in_array($action,['accept','reject'],true)){
                // Invitation id is a locator, not profile or account authority.
                $d=$this->identity->sessions()->validate($session);
                $s=$this->pdo->prepare("SELECT i.profile_doctor_id FROM auth_profile_operator_invitations i JOIN auth_accounts a ON a.email_normalized=i.email_normalized WHERE i.invitation_id=? AND a.account_id=? AND a.status='active' AND a.email_verified_at IS NOT NULL");$s->execute([$input['invitation_id']??'',$d->context()->accountId()]);$profile=$s->fetchColumn();if(!is_string($profile))self::deny();
            }else{$profile=$this->profile($state,$input);if(!$state['owner'])self::deny();}
            $input['reason']=in_array($action,['accept','reject'],true)?'INVITEE_DECISION':'OWNER_REQUEST';
            self::json(['ok'=>true,'data'=>$this->operators->command($session,$csrf,OperatorQaConfiguration::ORIGIN,$profile,(string)($body['command_id']??''),$action,$input)]);
        }
        if($path==='/api/profile-operators/index.php/open-invitation'&&$method==='POST'){
            $body=$this->body();$d=$this->identity->sessions()->validate($session);$id=(string)($body['invitation_id']??'');
            $this->pdo->beginTransaction();
            try{
                $s=$this->pdo->prepare("SELECT email_normalized FROM auth_accounts WHERE account_id=? AND status='active' AND email_verified_at IS NOT NULL FOR UPDATE");$s->execute([$d->context()->accountId()]);$email=$s->fetchColumn();if(!is_string($email))throw new \RuntimeException('operator_denied');
                $token=$this->outbox->open($id,$email);$s=$this->pdo->prepare('SELECT profile_doctor_id FROM auth_profile_operator_invitations WHERE invitation_id=?');$s->execute([$id]);$profile=$s->fetchColumn();$this->pdo->commit();
                $view=$this->operators->inspectInvitation($session,$profile,$id,$token);self::json(['ok'=>true,'data'=>$view+['token'=>$token]]);
            }catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
        }
        if(str_starts_with($path,'/api/agenda/index.php/'))$this->agenda($path,$session,$csrf,$state);
        self::deny();
    }
    private function agenda(string $path,string $session,string $csrf,array $state): never
    {
        $method=$_SERVER['REQUEST_METHOD'];$input=$method==='GET'?[]:$this->body();$profile=$this->profile($state,$input);
        $suffix=substr($path,strlen('/api/agenda/index.php/'));$id=null;
        if($suffix==='appointments'&&$method==='GET')$cap='agenda.appointments.read';
        elseif($suffix==='appointments'&&$method==='POST')$cap='agenda.appointments.create';
        elseif(preg_match('~^appointments/([A-Za-z0-9_-]+)/reschedule$~D',$suffix,$m)&&$method==='PATCH'){$cap='agenda.appointments.reschedule';$id=$m[1];}
        elseif(preg_match('~^appointments/([A-Za-z0-9_-]+)/cancel$~D',$suffix,$m)&&$method==='POST'){$cap='agenda.appointments.cancel';$id=$m[1];}
        elseif($suffix==='availability'&&$method==='GET')$cap='agenda.availability.read';
        else self::deny(); // Includes ALL legacy operators/migration/lifecycle/settings aliases.
        $result=$this->operators->executeAgenda($session,$csrf,OperatorQaConfiguration::ORIGIN,$profile,$cap,(int)($_SERVER['HTTP_X_GRANT_REVISION']??-1),function(array $actor)use($profile,$input,$method,$cap,$id):array{
            require_once dirname(__DIR__,2).'/agenda/controllers/AppointmentWriteController.php';
            require_once dirname(__DIR__,2).'/agenda/helpers/db_helpers.php';
            require_once dirname(__DIR__,2).'/agenda/controllers/AvailabilityController.php';
            require_once dirname(__DIR__,2).'/agenda/repositories/AppointmentsRepository.php';
            $context=['doctor_id'=>$profile,'user_id'=>$actor['account_id'],'actor_role'=>$actor['role']==='owner'?'doctor':'operator','strict'=>true,'mode'=>'canonical_operator','canonical_profile_scope'=>true];
            if($cap==='agenda.appointments.read'){
                $from=(string)($_GET['from']??'');$to=(string)($_GET['to']??'');
                if(!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D',$from)||!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D',$to)||$to<=$from||strtotime($to)-strtotime($from)>32*86400)throw new \DomainException('invalid_range');
                $rows=(new \Agenda\Repositories\AppointmentsRepository($this->pdo))->listByRange($from,$to,$profile,null,200);
                // No contact, clinical reason, flags, diagnosis or record hydration.
                $rows=array_map(fn($r)=>array_intersect_key($r,array_flip(['appointment_id','consultorio_id','start_at','end_at','modality','status'])),$rows);
                return ['ok'=>true,'data'=>$rows];
            }
            if($cap==='agenda.availability.read'){
                $office=(string)($_GET['consultorio_id']??'');$this->office($profile,$office);
                $c=new \Agenda\Controllers\AvailabilityController($this->pdo);$c->setActorContext($context);$r=$c->index(['doctor_id'=>$profile,'consultorio_id'=>$office,'date'=>$_GET['date']??'','slot_minutes'=>30]);
                if(($r['ok']??false)!==true)return $r;
                return ['ok'=>true,'data'=>array_intersect_key($r['data']??[],array_flip(['slots','windows','date','consultorio_id','slot_minutes','available_slots','availability']))];
            }
            $writer=new \Agenda\Controllers\AppointmentWriteController($this->pdo);$writer->setActorContext($context);
            $common=['actor_role'=>$context['actor_role'],'actor_id'=>$actor['account_id'],'created_by_role'=>$context['actor_role'],'created_by_id'=>$actor['account_id'],'channel_origin'=>'operator_workspace','doctor_id'=>$profile,'notify_patient'=>false,'contact_method'=>'none'];
            if($cap==='agenda.appointments.create'){
                foreach(array_keys($input) as $key)if(!in_array($key,['consultorio_id','patient_id','start_at','end_at','modality'],true))self::deny();
                $this->office($profile,(string)($input['consultorio_id']??''));
                $s=$this->pdo->prepare("SELECT patient_id FROM patients_doctor_links WHERE doctor_id=? AND patient_id=? AND status='active'");$s->execute([$profile,$input['patient_id']??'']);if($s->fetchColumn()===false)throw new \RuntimeException('operator_scope_denied');
                if(!preg_match('/^[A-Za-z0-9._:-]{8,128}$/D',$_SERVER['HTTP_IDEMPOTENCY_KEY']??''))throw new \DomainException('idempotency_required');
                return $writer->createFromPayload($input+$common);
            }
            $s=$this->pdo->prepare('SELECT appointment_id,doctor_id,consultorio_id,start_at,end_at FROM agenda_appointments WHERE appointment_id=? AND doctor_id=? FOR UPDATE');$s->execute([$id,$profile]);$row=$s->fetch();if(!is_array($row))throw new \RuntimeException('operator_scope_denied');
            if($cap==='agenda.appointments.reschedule'){
                foreach(array_keys($input) as $key)if(!in_array($key,['from_start_at','from_end_at','to_start_at','to_end_at','to_consultorio_id'],true))throw new \RuntimeException('operator_request_denied');
                if(($input['from_start_at']??null)!==$row['start_at']||($input['from_end_at']??null)!==$row['end_at'])throw new \DomainException('state_conflict');
                $this->office($profile,(string)($input['to_consultorio_id']??$row['consultorio_id']));
                return $writer->rescheduleFromPayload($id,$input+$common+['motivo_code'=>'administrative_reschedule']);
            }
            if(array_keys($input)!==['expected_start_at']||$input['expected_start_at']!==$row['start_at'])throw new \DomainException('state_conflict');
            return $writer->cancelFromPayload($id,$common+['motivo_code'=>'administrative_cancel','apply_late_cancel_flag'=>false,'cancel_flag_type'=>'none']);
        });
        self::json($result);
    }
    private function office(string $profile,string $id): void
    { $s=$this->pdo->prepare('SELECT consultorio_id FROM consultorios WHERE doctor_id=? AND consultorio_id=?');$s->execute([$profile,$id]);if($s->fetchColumn()===false)throw new \RuntimeException('operator_scope_denied'); }
}
