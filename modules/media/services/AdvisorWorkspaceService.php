<?php
declare(strict_types=1);
namespace Media\Services;

require_once __DIR__.'/MediaReviewAuthority.php';
require_once __DIR__.'/MediaSubmissionState.php';

use Identity\Http\{IdentityHttpComposition,CanonicalHttpSessionResolver};
use Identity\Repositories\InternalOperatorGrantRepository;
use Identity\Services\InternalOperatorAuthority;
use Platform\Contracts\{TrustedAuthorizationContext,RiskLevel};
use PDO;

/** Read composition for the existing Media staff domain, not an Advisor portfolio.
 * Profiles enter this workspace only through a submitted canonical Media item.
 * No owner authority, profile editing or additional business capability is created.
 */
final class AdvisorWorkspaceService
{
    private const PURPOSES="('DOCTOR_PROFILE_PHOTO','PHYSICIAN_PERSONAL_LOGO','DOCTOR_GALLERY')";
    private const LABELS=['DOCTOR_PROFILE_PHOTO'=>'Foto de perfil','PHYSICIAN_PERSONAL_LOGO'=>'Logotipo profesional','DOCTOR_GALLERY'=>'Foto de galería'];

    private function __construct(private PDO $pdo,private array $actor,private ?TrustedAuthorizationContext $context){}

    public static function fromCookies(array $cookies):self
    {
        $identity=IdentityHttpComposition::fromProcessEnvironment();
        $resolver=new CanonicalHttpSessionResolver($identity->sessions());
        $session=$resolver->resolve($cookies);
        if($session===null)throw new \RuntimeException('workspace_login_required');
        $pdo=$identity->pdo();
        $q=$pdo->prepare("SELECT s.governance_class,a.email_normalized FROM internal_staff s JOIN auth_accounts a ON a.account_id=s.account_id WHERE s.account_id=? AND s.status='ACTIVE' AND a.status='active'");
        $q->execute([$session->accountId()]);$staff=$q->fetch(PDO::FETCH_ASSOC);
        if(!is_array($staff))throw new \RuntimeException('workspace_staff_required');
        $q=$pdo->prepare("SELECT v.given_names,v.first_surname,v.second_surname FROM auth_staff_admission_cases c JOIN auth_staff_admission_versions v ON v.case_id=c.case_id AND v.version=c.current_profile_version WHERE c.bound_account_id=? AND c.state<>'REVOKED' ORDER BY c.updated_at DESC,c.case_id DESC LIMIT 1");
        $q->execute([$session->accountId()]);$personal=$q->fetch(PDO::FETCH_ASSOC);
        $name=is_array($personal)?trim(implode(' ',array_filter(array_map('trim',array_values($personal))))):'';
        $actor=['display_name'=>$name!==''?$name:$staff['email_normalized'],'email'=>$staff['email_normalized'],'access_level'=>$staff['governance_class']];
        $context=(new InternalOperatorAuthority($resolver,new InternalOperatorGrantRepository($pdo)))->resolve($cookies,'read','media_review_submission',RiskLevel::R0);
        return new self($pdo,$actor,$context);
    }

    /** Same submitted-state contract used by the original Media inbox. */
    public static function scope(string $alias='s'):string
    {
        return "$alias.owner_type='PHYSICIAN' AND $alias.purpose IN ".self::PURPOSES." AND $alias.technical_status='READY' AND $alias.review_status IN ('PENDING_REVIEW','APPROVED','NEEDS_WORK') AND ($alias.review_status<>'PENDING_REVIEW' OR EXISTS(SELECT 1 FROM media_review_files ready_file WHERE ready_file.submission_id=$alias.submission_id AND ready_file.role='REVIEW')) AND ".MediaSubmissionState::sql($alias);
    }

    private static function doctor(string $id):void
    {
        if(!preg_match('/^[a-zA-Z0-9_-]{1,64}$/D',$id))throw new \InvalidArgumentException('workspace_invalid_request');
    }

    public function requireMedia():void
    {
        if($this->context===null||$this->context->trustSource()!=='canonical_internal_operator')throw new \RuntimeException('review_access_denied');
        MediaReviewAuthority::requireRead($this->context);
    }

    private function table(string $name):bool
    {
        $q=$this->pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');
        $q->execute([$name]);return (int)$q->fetchColumn()===1;
    }

    /** Only coarse location; never expose contacts or addresses through Media grants. */
    private function location(string $id):array
    {
        if(!$this->table('consultorios'))return ['city'=>null,'state'=>null];
        $q=$this->pdo->prepare('SELECT municipio,estado FROM consultorios WHERE doctor_id=? ORDER BY consultorio_id LIMIT 1');
        $q->execute([$id]);$row=$q->fetch(PDO::FETCH_ASSOC);
        return ['city'=>is_array($row)?self::text($row['municipio']):null,'state'=>is_array($row)?self::text($row['estado']):null];
    }

    private static function text(mixed $value):?string
    {
        $value=trim((string)$value);return $value===''?null:$value;
    }

    private function physician(array $row):array
    {
        return ['doctor_id'=>$row['doctor_id'],'name'=>self::text($row['display_name'])??'Perfil médico','specialty'=>self::text($row['specialty_primary']),
            ...$this->location($row['doctor_id']),'status'=>$row['profile_status'],'pending_count'=>(int)$row['pending_count']];
    }

    private function physicianSql():string
    {
        return "SELECT p.doctor_id,p.display_name,p.specialty_primary,p.profile_status,(SELECT COUNT(*) FROM media_review_submissions s WHERE s.owner_id=p.doctor_id AND ".self::scope()." AND s.review_status='PENDING_REVIEW') pending_count FROM profiles_doctors p WHERE EXISTS(SELECT 1 FROM media_review_submissions s WHERE s.owner_id=p.doctor_id AND ".self::scope().")";
    }

    public function assertPhysician(string $doctorId):array
    {
        $this->requireMedia();self::doctor($doctorId);
        $q=$this->pdo->prepare($this->physicianSql().' AND p.doctor_id=?');$q->execute([$doctorId]);$row=$q->fetch(PDO::FETCH_ASSOC);
        if(!is_array($row))throw new \RuntimeException('workspace_not_found');
        return $this->physician($row);
    }

    /** Context binding precedes delegation; the canonical consumer still authorizes. */
    public function assertSubmission(string $doctorId,string $submissionId):array
    {
        $this->requireMedia();self::doctor($doctorId);
        if(!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D',$submissionId))throw new \InvalidArgumentException('workspace_invalid_request');
        $q=$this->pdo->prepare('SELECT s.submission_id,s.owner_id,s.purpose,s.review_status FROM media_review_submissions s JOIN profiles_doctors p ON p.doctor_id=s.owner_id WHERE s.submission_id=? AND s.owner_id=? AND '.self::scope());
        $q->execute([$submissionId,$doctorId]);$row=$q->fetch(PDO::FETCH_ASSOC);
        if(!is_array($row))throw new \RuntimeException('workspace_not_found');
        return $row;
    }

    public function snapshot(string $query='',?string $doctorId=null):array
    {
        if(strlen($query)>240||!preg_match('//u',$query))throw new \InvalidArgumentException('workspace_invalid_request');
        $result=['actor'=>$this->actor,'count'=>0,'physicians'=>[],'pending'=>[],'pending_has_more'=>false,'physician'=>null,'sections'=>[],'pending_items'=>[],'activity'=>[],'published_assets'=>[]];
        // An authenticated staff member can retain the shell after revocation, with
        // no work metadata. No cached grant or previously selected profile survives.
        if($this->context===null||!$this->context->context()->capabilities()->contains(MediaReviewAuthority::CAPABILITY))return $result;
        $this->requireMedia();
        $result['sections']=[['id'=>'summary','label'=>'Resumen'],['id'=>'images','label'=>'Imágenes']];
        $q=$this->pdo->query("SELECT COUNT(*) FROM media_review_submissions s JOIN profiles_doctors p ON p.doctor_id=s.owner_id WHERE ".self::scope()." AND s.review_status='PENDING_REVIEW'");
        $result['count']=(int)$q->fetchColumn();
        $q=$this->pdo->prepare($this->physicianSql()." AND (?='' OR LOCATE(?,COALESCE(p.display_name,''))>0 OR LOCATE(?,COALESCE(p.specialty_primary,''))>0) ORDER BY p.display_name,p.doctor_id LIMIT 50");
        $needle=trim($query);$q->execute([$needle,$needle,$needle]);
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row)$result['physicians'][]=$this->physician($row);
        $q=$this->pdo->query("SELECT s.submission_id,s.owner_id,s.purpose,p.display_name FROM media_review_submissions s JOIN profiles_doctors p ON p.doctor_id=s.owner_id WHERE ".self::scope()." AND s.review_status='PENDING_REVIEW' ORDER BY s.created_at,s.submission_id LIMIT 100");
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row)$result['pending'][]=['doctor_id'=>$row['owner_id'],'submission_id'=>$row['submission_id'],'label'=>self::LABELS[$row['purpose']],'purpose'=>$row['purpose'],'physician_name'=>self::text($row['display_name'])??'Perfil médico'];
        $result['pending_has_more']=$result['count']>count($result['pending']);
        if($doctorId===null)return $result;
        $result['physician']=$this->assertPhysician($doctorId);
        $q=$this->pdo->prepare("SELECT s.submission_id,s.owner_id,s.purpose,s.technical_status,s.review_status,s.created_at,s.updated_at,f.mime_type,f.width,f.height,f.byte_size FROM media_review_submissions s LEFT JOIN media_review_files f ON f.submission_id=s.submission_id AND f.role='REVIEW' WHERE s.owner_id=? AND ".self::scope()." ORDER BY s.created_at,s.submission_id LIMIT 100");
        $q->execute([$doctorId]);
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row){
            $item=['submission_id'=>$row['submission_id'],'doctor_id'=>$row['owner_id'],'purpose'=>$row['purpose'],'label'=>self::LABELS[$row['purpose']],'technical_status'=>$row['technical_status'],'review_status'=>$row['review_status'],'created_at'=>$row['created_at'],'updated_at'=>$row['updated_at'],
                'review'=>['mime_type'=>$row['mime_type'],'width'=>(int)$row['width'],'height'=>(int)$row['height'],'byte_size'=>(int)$row['byte_size']]];
            if($row['review_status']==='PENDING_REVIEW')$result['pending_items'][]=$item;
            else $result['activity'][]=['submission_id'=>$row['submission_id'],'purpose'=>$row['purpose'],'label'=>self::LABELS[$row['purpose']],'status'=>$row['review_status'],'recorded_at'=>$row['updated_at']];
        }
        if($this->table('media_assets')){
            $q=$this->pdo->prepare("SELECT media_id,purpose,public_url,alt_text,width,height FROM media_assets WHERE owner_type='PHYSICIAN' AND owner_id=? AND classification='PUBLIC' AND status='READY' AND purpose IN ".self::PURPOSES.' ORDER BY created_at,media_id LIMIT 100');
            $q->execute([$doctorId]);
            foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row){$row['width']=(int)$row['width'];$row['height']=(int)$row['height'];$result['published_assets'][]=$row;}
        }
        return $result;
    }
}
