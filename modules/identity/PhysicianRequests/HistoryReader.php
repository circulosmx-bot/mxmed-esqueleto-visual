<?php
declare(strict_types=1);
namespace Identity\PhysicianRequests;
require_once __DIR__.'/../../media/services/MediaSubmissionState.php';

use Identity\Contracts\AuthenticatedAccessContext;
use Identity\Repositories\InternalOperatorGrantRepository;
use Identity\Operational\WorkflowScope;
use PDO;

/** Bounded read of the canonical audit; no events are authored by this view.
 * Each domain retains its current read authority and authoritative ID relation. */
final class HistoryReader
{
    public function __construct(private PDO $pdo,private AuthenticatedAccessContext $actor,private ?WorkflowScope $scope=null){}
    public function read(?string $doctorId=null,?string $requestId=null):array
    {
        (new RequestAuthority($this->pdo))->assertActiveStaff($this->actor);
        if($doctorId===null&&$requestId===null)throw new \InvalidArgumentException('physician_request_history_context_required');
        foreach([$doctorId,$requestId] as $id)if($id!==null&&!preg_match('/^[A-Za-z0-9_-]{1,64}$/D',$id))throw new \InvalidArgumentException('physician_request_invalid_context');
        $caps=(new RequestAuthority($this->pdo))->capabilities($this->actor);$origins=[];
        if(in_array(RequestAuthority::READ,$caps,true)){
            $where="o.status='BOUND' AND o.intake_state='SUBMITTED' AND o.account_id<>?";$args=[$this->actor->accountId()];
            if($requestId!==null){$where.=' AND o.origin_id=?';$args[]=$requestId;}
            if($doctorId!==null){$where.=' AND (o.original_profile_doctor_id=? OR p.profile_doctor_id=?)';array_push($args,$doctorId,$doctorId);}
            $q=$this->pdo->prepare('SELECT o.origin_id,o.account_id,o.original_profile_doctor_id,p.profile_doctor_id FROM auth_physician_enrollment_origins o LEFT JOIN auth_physician_profile_request_profiles p ON p.origin_id=o.origin_id WHERE '.$where.' ORDER BY o.origin_id LIMIT 100');$q->execute($args);$origins=$q->fetchAll(PDO::FETCH_ASSOC);
            // Match the canonical intake reader's deployment cohort: original
            // profile/null plus bound applicant. New profiles retain exact links.
            if($this->scope!==null)$origins=array_values(array_filter($origins,fn(array $o):bool=>$this->scope->accepts($o['original_profile_doctor_id'],$o['account_id'])));
        }
        if($requestId!==null&&!$origins)throw new \RuntimeException('physician_request_history_denied');
        if($doctorId===null&&$origins)$doctorId=$origins[0]['profile_doctor_id']??$origins[0]['original_profile_doctor_id'];
        $grants=(new InternalOperatorGrantRepository($this->pdo))->activeCapabilities($this->actor->accountId());
        $media=false;
        if($doctorId!==null&&$grants->contains('media_review_read')){
            $q=$this->pdo->prepare("SELECT 1 FROM media_review_submissions s JOIN profiles_doctors p ON p.doctor_id=s.owner_id WHERE s.owner_type='PHYSICIAN' AND s.owner_id=? AND s.technical_status='READY' AND s.review_status IN ('PENDING_REVIEW','APPROVED','NEEDS_WORK') AND (s.review_status<>'PENDING_REVIEW' OR EXISTS(SELECT 1 FROM media_review_files f WHERE f.submission_id=s.submission_id AND f.role='REVIEW')) AND s.purpose IN ('DOCTOR_PROFILE_PHOTO','PHYSICIAN_PERSONAL_LOGO','DOCTOR_GALLERY') AND ".\Media\Services\MediaSubmissionState::sql().' LIMIT 1');
            $q->execute([$doctorId]);$media=$q->fetchColumn()!==false;
        }
        if(!$origins&&!$media)throw new \RuntimeException('physician_request_history_denied');
        $conditions=[];$args=[];$coverage=[];
        $app="JSON_UNQUOTE(JSON_EXTRACT(metadata_json,'$.producer_metadata.application_id'))";
        $profile="JSON_UNQUOTE(JSON_EXTRACT(metadata_json,'$.producer_metadata.profile_doctor_id'))";
        $op="JSON_UNQUOTE(JSON_EXTRACT(metadata_json,'$.producer_metadata.operation'))";
        if($origins){
            $ids=array_column($origins,'origin_id');$marks=implode(',',array_fill(0,count($ids),'?'));
            $operations=implode(',',array_fill(0,count(HistoryLabels::REQUEST_OPERATIONS),'?'));
            $conditions[]="(action='PHYSICIAN_PROFILE_REQUEST_CHANGED' AND $app IN ($marks) AND $op IN ($operations))";
            array_push($args,...$ids,...array_keys(HistoryLabels::REQUEST_OPERATIONS));$coverage[]='AUTHORIZED_PROFILE_REQUESTS';
            $conditions[]="(action='PROFILE_OWNERSHIP_ASSIGNED' AND JSON_UNQUOTE(JSON_EXTRACT(metadata_json,'$.producer_metadata.case_reference')) IN (SELECT case_id FROM auth_physician_profile_request_ownership WHERE origin_id IN ($marks)))";
            array_push($args,...$ids);$coverage[]='LINKED_CANONICAL_OWNERSHIP';
        }
        if($media){
            $types=array_values(array_filter(array_keys(HistoryLabels::ACTIONS),static fn($k)=>str_starts_with($k,'MEDIA_')));
            $conditions[]="(action IN (".implode(',',array_fill(0,count($types),'?')).") AND JSON_UNQUOTE(JSON_EXTRACT(metadata_json,'$.producer_metadata.physician_id'))=?)";
            array_push($args,...$types);$args[]=$doctorId;$coverage[]='AUTHORIZED_MEDIA_CHANGES';
        }
        // Reserved correction events require their existing capability and staff
        // class; a request grant never turns into access to those private cases.
        $q=$this->pdo->prepare("SELECT governance_class FROM internal_staff WHERE account_id=? AND status='ACTIVE'");$q->execute([$this->actor->accountId()]);$staffClass=$q->fetchColumn();
        if($doctorId!==null&&in_array($staffClass,['DIRECTOR','MASTER_ADMIN'],true)&&$grants->contains('public_profile_correction_review')){
            $conditions[]="(action IN ('PUBLIC_PROFILE_CORRECTION_REVIEWED','PUBLIC_PROFILE_CORRECTION_APPLICATION') AND $profile=?)";$args[]=$doctorId;$coverage[]='AUTHORIZED_RECORDED_PROFILE_CORRECTIONS';
        }
        $q=$this->pdo->prepare("SELECT event_id,occurred_at_utc,action,outcome,real_actor_reference,resource_type,resource_reference,metadata_json FROM platform_audit_events WHERE outcome='SUCCESS' AND (".implode(' OR ',$conditions).') ORDER BY occurred_at_utc DESC,event_id DESC LIMIT 101');$q->execute($args);$rows=$q->fetchAll(PDO::FETCH_ASSOC);$items=[];
        foreach(array_slice($rows,0,100) as $row){
            $meta=json_decode($row['metadata_json'],true,32,JSON_THROW_ON_ERROR);$producer=$meta['producer_metadata']??[];$audit=$meta['_audit_v1']??[];
            $details=[];foreach(['previous_status','new_status','prior_role','new_role','request_type','version','purpose'] as $key)if(isset($producer[$key])&&is_scalar($producer[$key]))$details[$key]=$producer[$key];
            $items[]=['id'=>$row['event_id'],'at'=>str_replace(' ','T',$row['occurred_at_utc']).'Z','action'=>$row['action'],
                'description'=>HistoryLabels::label($row['action'],$producer['operation']??null,$producer['request_type']??null),'outcome'=>$row['outcome'],
                'actor'=>['reference'=>$row['real_actor_reference'],'label'=>$row['real_actor_reference'],'role'=>$audit['actor_role']??null,'scope'=>$audit['actor_scope']??null],
                'target'=>['doctor_id'=>$producer['profile_doctor_id']??$producer['physician_id']??$doctorId,'request_id'=>$producer['application_id']??$requestId,'resource_type'=>$row['resource_type'],'reference'=>$row['resource_reference']],
                'details'=>$details,'reason'=>($producer['review_reason']??null)==='REASON_IN_PROTECTED_REQUEST'?'Motivo disponible en el detalle autorizado de la solicitud':null];
        }
        return ['state'=>'AVAILABLE','items'=>$items,'has_more'=>count($rows)>100,'coverage'=>$coverage];
    }
}
