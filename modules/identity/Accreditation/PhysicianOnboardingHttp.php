<?php
declare(strict_types=1);
namespace Identity\Accreditation;

use Identity\Contracts\AuthenticatedAccessContext;
use Identity\Http\{IdentityHttpComposition,GovernanceHttpDenied};
use Identity\Services\GovernanceSecurityContext;
use PDO;
require_once __DIR__.'/../http/GovernanceHttpSecurity.php';

/** Original UI adapter. The normal entry supplies NO operational composition.
 * An explicitly constructed composition is exercised only by the owning QA router.
 * No environment switch, synthetic verifier or real activation is introduced.
 */
final class PhysicianOnboardingHttp
{
    private EvidenceAuthority $a;
    public function __construct(private IdentityHttpComposition $identity,
        private ?PhysicianAdmissionComposition $services=null,private ?PhoneVerificationService $phone=null)
    {
        $this->a=new EvidenceAuthority($identity->pdo(),new GovernanceSecurityContext($identity->sessions(),$identity->authentication()));
        if ($services!==null&&!$services->projection->usesConnection($identity->pdo())) throw new \RuntimeException('onboarding_connection_mismatch');
        if (($services===null)!==($phone===null)) throw new \RuntimeException('onboarding_incomplete_composition');
        if ($phone!==null&&!$phone->usesConnection($identity->pdo())) throw new \RuntimeException('onboarding_phone_connection_mismatch');
    }
    public function page(array $cookies,bool $staff):array
    {
        $decision=$this->identity->sessions()->validate($cookies['__Host-mxmed_session']??null);
        if (in_array($decision->reasonCode(),[\Identity\Contracts\ReasonCode::SESSION_STORE_UNAVAILABLE,\Identity\Contracts\ReasonCode::STORAGE_UNAVAILABLE],true))throw new \RuntimeException('onboarding_session_unavailable');
        if (!$decision->allowed()||$decision->context()===null||$decision->record()===null) throw new GovernanceHttpDenied(401);
        $actor=$decision->context();if ($staff)$this->staff($actor);
        return $this->snapshot($actor,$staff,null)+['csrf_token'=>$this->identity->csrf()->issueAuthenticated($actor->session()->tokenDigest()),
            'expires_at'=>$decision->record()->expiresAt()->getTimestamp()];
    }
    public function handle(array $server,array $cookies,string $raw):array
    {
        try {
            if (($server['REQUEST_METHOD']??'')!=='POST')return $this->error(405,'Utiliza el formulario protegido para esta acción.');
            if (strlen($raw)>12*1024*1024) return $this->error(413,'El archivo supera el tamaño permitido.');
            if (!str_starts_with(strtolower($server['CONTENT_TYPE']??''),'application/json')) return $this->error(415,'Envía los datos desde el formulario.');
            $b=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
            if (!is_array($b)||array_is_list($b)) return $this->error(400,'Revisa los datos de la solicitud.');
            $actor=EvidenceHttpBoundary::access($this->identity,$server,$cookies,$b,$raw,(string)getenv('MXMED_GOVERNANCE_PROXY_KEY'));
            foreach (['account_id','doctor_id','profile_doctor_id','verified_identity_id','professional_credential_id','origin','reviewer_account_id'] as $forbidden)
                if (array_key_exists($forbidden,$b)) return $this->error(400,'La solicitud incluye datos que no se pueden modificar.');
            $action=$b['action']??'';$staff=($b['view']??'physician')==='staff';
            if ($staff)$this->staff($actor);
            if ($action==='snapshot')return ['status'=>200,'body'=>['ok'=>true,'data'=>$this->snapshot($actor,$staff,$b['request_id']??null)]];
            if ($this->services===null) return $this->error(503,'La acreditación profesional está pendiente de habilitación. Por ahora puedes consultar los pasos.');
            $data=null;
            if ($action==='phone_issue')$data=$this->phone->issue($actor,(string)($b['phone']??''),(string)($b['email']??''),(string)($b['password']??''));
            elseif ($action==='phone_verify'){
                if (!$this->phone->verify($actor,(string)($b['challenge_id']??''),(int)($b['contact_version']??0),(string)($b['code']??'')))return $this->error(422,'El código no es válido, venció o ya se utilizó. Solicita uno nuevo si corresponde.');
                $data=['verified'=>true];
            } elseif ($action==='profile_lookup'||$action==='request_create') {
                $profile=$this->profile($actor,(string)($b['profile_reference']??''));
                if ($action==='profile_lookup')$data=['profile'=>array_intersect_key($profile,array_flip(['doctor_id','display_name','professional_area']))];
                else {
                    if (($b['claim_accepted']??false)!==true)return $this->error(422,'Confirma el perfil exacto antes de continuar.');
                    $data=$this->services->evidence->create($actor,'CLAIM_EXISTING_PROFILE',$profile['doctor_id'],$profile['identity_id'],$profile['credential_id']);
                }
            } elseif (in_array($action,['review_begin','review_verify','review_decide','document'],true)) {
                $this->staff($actor);$r=$this->scoped($actor,$b,true);
                if ($action==='review_begin') {
                    if (!in_array($b['decision']??null,['APPROVED','REJECTED'],true))return $this->error(422,'Selecciona una decisión de revisión disponible.');
                    $data=$this->services->evidence->beginReview($actor,$r['request_id'],(int)$r['version'],$b['decision'],(string)($b['email']??''),(string)($b['password']??''),$b['reason']??null);
                } elseif ($action==='review_verify') {
                    // Verify only the current case's exact MFA challenge; not an arbitrary operation.
                    $q=$this->a->query("SELECT c.challenge_id FROM auth_staff_mfa_challenges c WHERE c.challenge_id=? AND c.case_id=? AND c.operation='PHYSICIAN_EVIDENCE_REVIEW' AND c.actor_account_id=?",
                        [$b['challenge_id']??'',$r['case_id'],$actor->accountId()]);
                    if ($q->fetchColumn()===false)throw new GovernanceHttpDenied(403);
                    $data=$this->mfa()->verifyChallenge($actor->accountId(),(string)$actor->session()->sessionId(),$actor->principal()->credentialVersion(),
                        (string)$b['challenge_id'],(string)($b['challenge']??''),(string)($b['code']??''));
                } elseif ($action==='review_decide')$data=$this->services->evidence->decide($actor,$r['request_id'],(int)$r['version'],(string)($b['proof_id']??''),
                    (string)($b['proof']??''),($b['relationship_confirmed']??false)===true,is_array($b['document_facts']??null)?$b['document_facts']:[]);
                else {
                    $d=$this->services->evidence->readDocument($actor,$r['request_id'],(int)$r['version'],(string)($b['file_id']??''));
                    return ['status'=>200,'bytes'=>$d['bytes'],'headers'=>$d['headers']+['Content-Type'=>$d['mime_type'],'Cross-Origin-Resource-Policy'=>'same-origin']];
                }
            } else {
                $r=$this->scoped($actor,$b,false);
                if ($action==='document_add') {
                    $encoded=$b['content_base64']??null;$bytes=is_string($encoded)?base64_decode($encoded,true):false;
                    if (!is_string($bytes))return $this->error(422,'Selecciona un archivo válido.');
                    $data=$this->services->evidence->addDocument($actor,$r['request_id'],(int)$r['version'],(string)($b['kind']??''),$bytes);
                } elseif ($action==='request_submit')$data=$this->services->evidence->submit($actor,$r['request_id'],(int)$r['version']);
                elseif ($action==='confirmation_issue')$data=$this->services->evidence->issueConfirmation($actor,$r['request_id'],(int)$r['version'],(string)($b['email']??''),(string)($b['password']??''));
                elseif ($action==='confirmation_accept')$data=$this->services->evidence->confirm($actor,$r['request_id'],(int)$r['version'],(string)($b['confirmation']??''),($b['accepted']??false)===true);
                elseif ($action==='complete') {
                    $command=$this->a->query('SELECT command_id FROM auth_physician_issuance_workflows WHERE evidence_request_id=? AND evidence_version=? AND account_id=?',
                        [$r['request_id'],$r['version'],$actor->accountId()])->fetchColumn();
                    if (!is_string($command))throw new GovernanceHttpDenied(403);
                    $data=$this->services->issuer->issue($actor,$command);
                } else return $this->error(400,'La acción solicitada no está disponible.');
            }
            // Server confirmation is mandatory; never infer delivery or completed admission.
            if ($action==='phone_issue'&&($data['delivery_accepted']??false)!==true)return $this->error(503,'No pudimos confirmar el envío del código. Intenta de nuevo más tarde.');
            return ['status'=>200,'body'=>['ok'=>true,'data'=>$data]];
        } catch (GovernanceHttpDenied $e) {
            if ($e->status===401) {
                $decision=$this->identity->sessions()->validate($cookies['__Host-mxmed_session']??null);
                if (in_array($decision->reasonCode(),[\Identity\Contracts\ReasonCode::SESSION_STORE_UNAVAILABLE,\Identity\Contracts\ReasonCode::STORAGE_UNAVAILABLE],true))return $this->error(503,'No pudimos validar la sesión en este momento. Intenta de nuevo más tarde.');
            }
            return $this->error($e->status,match($e->status){401=>'Por seguridad, inicia sesión nuevamente para continuar.',404=>'No encontramos un perfil disponible con esa referencia. Verifícala o solicita atención al equipo.',503=>'El servicio no está disponible en este momento. Intenta de nuevo más tarde.',default=>'No tienes autorización para realizar esta acción.'});
        }
        catch (\JsonException|\InvalidArgumentException|\TypeError) {return $this->error(422,'Revisa los datos, el formato y el tamaño de los archivos.');}
        catch (\PDOException) {return $this->error(503,'No pudimos confirmar la operación. Actualiza el estado antes de volver a intentarlo.');}
        catch (\RuntimeException $e) {
            $message=$e->getMessage();
            if (str_contains($message,'stale')||str_contains($message,'version_conflict')||str_contains($message,'revision_changed'))return $this->error(409,'La solicitud cambió. Actualiza su estado antes de continuar.');
            if (str_contains($message,'session_invalidated'))return $this->error(401,'Por seguridad, inicia sesión nuevamente para continuar.');
            if (str_contains($message,'expired')||str_contains($message,'confirmation_denied'))return $this->error(409,'La autorización venció o dejó de estar disponible. Actualiza el estado y solicita una nueva confirmación si corresponde.');
            if (str_contains($message,'resend_throttled')||str_contains($message,'send_limit'))return $this->error(429,'Espera antes de solicitar otro código.');
            return $this->error(403,'No fue posible autorizar la acción. Revisa los datos y las indicaciones de tu solicitud.');
        } catch (\Throwable) {return $this->error(503,'El servicio no está disponible. Intenta de nuevo más tarde.');}
    }
    public static function serve(self $http):never
    {
        $raw=(string)file_get_contents('php://input',false,null,0,12*1024*1024+1);$r=$http->handle($_SERVER,$_COOKIE,$raw);
        if ($r['status']===200) {
            $decision=$http->identity->sessions()->validate($_COOKIE['__Host-mxmed_session']??null);
            if ($decision->allowed()&&$decision->record()!==null)header('X-MXMED-Session-Expires: '.$decision->record()->expiresAt()->getTimestamp());
            else {
                $unavailable=in_array($decision->reasonCode(),[\Identity\Contracts\ReasonCode::SESSION_STORE_UNAVAILABLE,\Identity\Contracts\ReasonCode::STORAGE_UNAVAILABLE],true);
                $r=$http->error($unavailable?503:401,$unavailable?'No pudimos validar la sesión. Intenta de nuevo más tarde.':'Por seguridad, inicia sesión nuevamente para continuar.');
            }
        }
        http_response_code($r['status']);header('Cache-Control: no-store, private, max-age=0');header('Pragma: no-cache');header('X-Content-Type-Options: nosniff');header('Referrer-Policy: no-referrer');
        foreach($r['headers']??[] as $key=>$value)header($key.': '.$value);
        if (isset($r['bytes'])){header('Content-Length: '.strlen($r['bytes']));echo $r['bytes'];}
        else {header('Content-Type: application/json; charset=utf-8');echo json_encode($r['body'],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);}
        exit;
    }
    private function staff(AuthenticatedAccessContext $actor):void
    {
        $q=$this->a->query("SELECT s.account_id FROM internal_staff s JOIN internal_operator_grants g ON g.account_id=s.account_id
            JOIN auth_accounts a ON a.account_id=s.account_id JOIN auth_account_credentials c ON c.account_id=a.account_id
            WHERE s.account_id=? AND s.status='ACTIVE' AND a.status='active' AND a.email_verified_at IS NOT NULL AND c.credential_version=?
            AND g.capability='physician_accreditation_review' AND g.status='ACTIVE' AND g.revoked_at IS NULL",[$actor->accountId(),$actor->principal()->credentialVersion()]);
        if ($q->fetchColumn()!==$actor->accountId())throw new GovernanceHttpDenied(403);
    }
    private function scoped(AuthenticatedAccessContext $actor,array $b,bool $staff):array
    {
        if (!is_string($b['request_id']??null)||!is_int($b['version']??null)||$b['version']<1)throw new \InvalidArgumentException('request_version_required');
        return $this->a->transaction($actor,function()use($actor,$b,$staff):array {
            $r=$this->a->request($b['request_id'],$b['version']);
            if ($staff)$this->a->reviewer($actor->accountId(),$r['account_id'],$actor->principal()->credentialVersion());
            elseif ($r['account_id']!==$actor->accountId())throw new GovernanceHttpDenied(403);
            return $r;
        });
    }
    private function profile(AuthenticatedAccessContext $actor,string $id):array
    {
        return $this->a->transaction($actor,function()use($actor,$id):array {
            $this->a->applicant($actor->accountId());$this->a->phone($actor->accountId());
            $p=$this->a->query("SELECT d.doctor_id,d.display_name,i.verified_identity_id identity_id,c.credential_id,c.professional_area_label professional_area
                FROM profiles_doctors d JOIN profiles_verified_identities i ON i.doctor_id=d.doctor_id JOIN profiles_doctor_credentials c ON c.doctor_id=d.doctor_id
                WHERE d.doctor_id=? AND c.credential_type='PROFESSIONAL' AND c.verification_status='VERIFIED' AND c.lifecycle_status='ACTIVE' FOR UPDATE",[$id])->fetchAll(PDO::FETCH_ASSOC);
            if (count($p)!==1||$p[0]['doctor_id']!==$id)throw new GovernanceHttpDenied(404);
            if ($this->a->query("SELECT membership_id FROM auth_account_memberships WHERE profile_doctor_id=? AND role_code='owner' AND status='active' LIMIT 1 FOR UPDATE",[$id])->fetchColumn()!==false)
                throw new \RuntimeException('c4_existing_owner_conflict');
            $this->a->sources($id,(int)$p[0]['identity_id'],(int)$p[0]['credential_id']);
            return array_replace($p[0],['identity_id'=>(int)$p[0]['identity_id'],'credential_id'=>(int)$p[0]['credential_id']]);
        });
    }
    private function snapshot(AuthenticatedAccessContext $actor,bool $staff,?string $selected):array
    {
        $base=['enabled'=>$this->services!==null,'staff'=>$staff,'email_verified'=>true,'phone_verified'=>false,'requests'=>[],'request'=>null,
            'message'=>$staff?'La revisión de acreditaciones está pendiente de habilitación.':'La acreditación profesional está pendiente de habilitación. Por ahora puedes consultar los pasos.'];
        if ($this->services===null)return $base;
        return $this->a->transaction($actor,function()use($actor,$staff,$selected,$base):array {
            if ($staff)$this->staff($actor);else $this->a->applicant($actor->accountId());
            $base['message']=$staff?'Revisa la cuenta, el perfil y los documentos exactos antes de registrar una decisión. La designación como titular es independiente.'
                :'Completa los pasos de tu solicitud. La acreditación y la designación como titular son procesos separados.';
            $phone=$this->a->query('SELECT status FROM auth_account_phone_verifications WHERE account_id=? FOR UPDATE',[$actor->accountId()])->fetchColumn();$base['phone_verified']=$phone==='VERIFIED';
            $where=$staff?'r.account_id<>?':'r.account_id=?';
            $rows=$this->a->query("SELECT r.request_id,r.version,r.status,r.origin,r.profile_doctor_id,d.display_name FROM auth_physician_evidence_requests r
                LEFT JOIN profiles_doctors d ON d.doctor_id=r.profile_doctor_id WHERE $where ORDER BY r.created_at DESC,r.request_id DESC LIMIT 30",[$actor->accountId()])->fetchAll(PDO::FETCH_ASSOC);
            $base['requests']=array_map(static fn($r)=>['request_id'=>$r['request_id'],'version'=>(int)$r['version'],'status'=>$r['status'],'display_name'=>$r['display_name']??'Perfil por resolver','profile_reference'=>$r['profile_doctor_id']],$rows);
            $id=$selected??($rows[0]['request_id']??null);if ($id===null)return $base;
            $r=$this->a->request($id);
            if ($staff)$this->a->reviewer($actor->accountId(),$r['account_id'],$actor->principal()->credentialVersion());
            elseif ($r['account_id']!==$actor->accountId())throw new GovernanceHttpDenied(403);
            $files=$this->a->query('SELECT file_id,kind,review_expires_at FROM auth_physician_evidence_files WHERE request_id=? ORDER BY created_at,file_id FOR UPDATE',[$id])->fetchAll(PDO::FETCH_ASSOC);
            $profile=$this->a->query('SELECT d.display_name,i.given_names,i.first_surname,i.second_surname,c.professional_area_label FROM profiles_doctors d
                LEFT JOIN profiles_verified_identities i ON i.verified_identity_id=? LEFT JOIN profiles_doctor_credentials c ON c.credential_id=? WHERE d.doctor_id=?',
                [$r['verified_identity_id'],$r['professional_credential_id'],$r['profile_doctor_id']])->fetch(PDO::FETCH_ASSOC);
            $complete=false;
            $binding=$this->a->query("SELECT binding_id FROM auth_account_professional_bindings WHERE evidence_request_id=? AND account_id=? AND status='ACTIVE' FOR UPDATE",[$id,$r['account_id']])->fetchColumn();
            if ($binding!==false) {try{$this->services->projection->requireCurrent($this->a->pdo,$r['account_id'],$r['profile_doctor_id']);$complete=true;}catch(\RuntimeException){$r['status']='ACTION_REQUIRED';}}
            $base['request']=['request_id'=>$id,'version'=>(int)$r['version'],'status'=>$r['status'],'origin'=>$r['origin'],
                'profile_reference'=>$r['profile_doctor_id'],'display_name'=>$profile['display_name']??'Perfil por resolver',
                'professional_name'=>trim(implode(' ',array_filter([$profile['given_names']??null,$profile['first_surname']??null,$profile['second_surname']??null]))),
                'professional_area'=>$profile['professional_area_label']??'','documents'=>$files,'review_reason'=>$r['review_reason'],
                'completed'=>$complete,'supported'=>$r['origin']==='CLAIM_EXISTING_PROFILE'];
            if ($staff)$base['request']['account_reference']=$r['account_id'];
            return $base;
        });
    }
    private function mfa():\Identity\Services\StaffMfaService
    {return $this->services->mfa();}
    private function error(int $status,string $message):array {return ['status'=>$status,'body'=>['ok'=>false,'message'=>$message]];}
}
