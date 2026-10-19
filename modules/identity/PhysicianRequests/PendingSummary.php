<?php
declare(strict_types=1);
namespace Identity\PhysicianRequests;

use Identity\Accreditation\{EvidenceAuthority,PublicEnrollmentService};
use Identity\Contracts\AuthenticatedAccessContext;
use Identity\Repositories\InternalOperatorGrantRepository;
use Media\Services\AdvisorWorkspaceService;
use PDO;

/** Read-only work index. Summary permission extends counts, never the physician
 * follow-up scope: that scope must already exist through a permitted domain.
 * No applicant identifiers, document metadata or reasons leave this reader. */
final class PendingSummary
{
    public const CAPABILITY='physician_pending_summary_read';
    public function __construct(private EvidenceAuthority $a,private PublicEnrollmentService $enrollment){}

    public function read(AuthenticatedAccessContext $actor,?string $doctor=null,?string $application=null):array
    {
        foreach([$doctor,$application] as $id)if($id!==null&&!preg_match('/^[a-zA-Z0-9_-]{1,64}$/D',$id))throw new \InvalidArgumentException('workspace_invalid_request');
        return $this->a->transaction($actor,function()use($actor,$doctor,$application){
            (new RequestAuthority($this->a->pdo,$this->a->clock))->assertActiveStaff($actor,true);
            $caps=(new InternalOperatorGrantRepository($this->a->pdo))->activeCapabilities($actor->accountId(),true);
            $mediaRead=$caps->contains('media_review_read');$requestRead=$caps->contains(RequestAuthority::READ);
            $summary=$caps->contains(self::CAPABILITY);
            if($summary&&$this->a->scope!==null)$summary=$this->a->scope->allowsStaff($actor->accountId(),self::CAPABILITY);
            require_once dirname(__DIR__,2).'/media/services/AdvisorWorkspaceService.php';
            $media=[];$mediaActions=[];$scope=[];$requests=[];
            $allMediaActions=$mediaRead&&($caps->contains('media_review_approve')||$caps->contains('media_review_request_replacement')||$caps->contains('media_review_corrected_upload'));
            $actionPredicate=$allMediaActions?'1=1':($mediaRead&&$caps->contains('media_review_improve')?"s.purpose='PHYSICIAN_PERSONAL_LOGO'":'1=0');
            if($mediaRead||($summary&&$requestRead)){
                $rows=$this->a->query("SELECT s.owner_id,SUM(s.review_status='PENDING_REVIEW') pending_count,SUM(s.review_status='PENDING_REVIEW' AND ($actionPredicate)) actionable_count FROM media_review_submissions s JOIN profiles_doctors p ON p.doctor_id=s.owner_id WHERE ".AdvisorWorkspaceService::scope()." GROUP BY s.owner_id")->fetchAll(PDO::FETCH_ASSOC);
                foreach($rows as $row){$id=$row['owner_id'];if($this->a->scope!==null&&!$this->a->scope->accepts($id))continue;$media[$id]=(int)$row['pending_count'];$mediaActions[$id]=(int)$row['actionable_count'];if($mediaRead)$scope[$id]=true;}
            }
            if($requestRead||($summary&&$mediaRead)){
                $requests=$this->a->query("SELECT o.origin_id,o.account_id,o.initial_origin,o.intake_version,COALESCE(o.original_profile_doctor_id,l.profile_doctor_id) doctor_id,COALESCE(d.state,'PENDING_REVIEW') state,
                    (d.state='NEEDS_INFORMATION' AND NOT EXISTS(SELECT 1 FROM auth_physician_profile_request_responses r WHERE r.resolution_id=d.resolution_id)) waiting
                    FROM auth_physician_enrollment_origins o
                    LEFT JOIN auth_physician_profile_request_profiles l ON l.origin_id=o.origin_id
                    LEFT JOIN auth_physician_profile_request_decisions d ON d.origin_id=o.origin_id AND NOT EXISTS(SELECT 1 FROM auth_physician_profile_request_decisions newer WHERE newer.origin_id=d.origin_id AND newer.resolution_version>d.resolution_version)
                    WHERE o.status='BOUND' AND o.intake_state='SUBMITTED' AND o.account_id<>? ORDER BY o.origin_id",[$actor->accountId()])->fetchAll(PDO::FETCH_ASSOC);
                $requests=array_values(array_filter($requests,fn($v)=>$this->a->scope===null||$this->a->scope->accepts($v['doctor_id'],$v['account_id'])));
                if($requestRead)foreach($requests as $row)if($row['doctor_id']!==null)$scope[$row['doctor_id']]=true;
            }
            $groups=[];$applicationKeys=[];
            foreach(array_keys($scope) as $id){
                $p=$this->a->query('SELECT doctor_id,display_name,specialty_primary,profile_status FROM profiles_doctors WHERE doctor_id=?',[$id])->fetch(PDO::FETCH_ASSOC);
                if(!is_array($p))continue;
                $key='physician:'.$id;$groups[$key]=$this->group($key,$id,null,$p['display_name']?:'Perfil médico');
                $loc=$this->a->query('SELECT municipio,estado FROM consultorios WHERE doctor_id=? ORDER BY consultorio_id LIMIT 1',[$id])->fetch(PDO::FETCH_ASSOC);
                $groups[$key]['city']=$loc['municipio']??null;$groups[$key]['state']=$loc['estado']??null;
                $groups[$key]['specialty']=$p['specialty_primary'];$groups[$key]['status']=$p['profile_status'];
                $groups[$key]['permissions']=['requests'=>$requestRead,'images'=>$mediaRead&&array_key_exists($id,$media),'history'=>true];
                if($mediaRead||$summary)$groups[$key]['sections']['images']=$this->counts($media[$id]??0,$mediaActions[$id]??0,!$mediaRead);
                if($requestRead||$summary)$groups[$key]['sections']['requests']=$this->counts(0,0,!$requestRead);
            }
            foreach($requests as $row){
                $id=$row['doctor_id'];$key=$id!==null?'physician:'.$id:'application:'.$row['origin_id'];
                if(!$requestRead&&(!$summary||$id===null||!isset($scope[$id])))continue;
                if($id!==null&&!isset($groups[$key]))continue;
                if($id===null){
                    $d=$this->enrollment->requestIntakeInTransaction($actor,$row['origin_id'],(int)$row['intake_version'])['intake'];
                    $groups[$key]=$this->group($key,null,$row['origin_id'],trim($d['given_names'].' '.$d['first_surname'].' '.$d['second_surname']));
                    $groups[$key]['state']=$row['state'];$groups[$key]['permissions']=['requests'=>true,'images'=>false,'history'=>true];
                }
                if($requestRead)$applicationKeys[$row['origin_id']]=$key;
                if(!isset($groups[$key]['sections']['requests']))$groups[$key]['sections']['requests']=$this->counts(0,0,!$requestRead);
                $unresolved=!in_array($row['state'],['APPROVED','REJECTED'],true);
                $act=$requestRead&&$caps->contains($row['initial_origin']==='CLAIM_EXISTING_PROFILE'?RequestAuthority::CLAIM:RequestAuthority::NEW_PROFILE)&&!(bool)$row['waiting'];
                if($unresolved){$groups[$key]['sections']['requests']['visible_count']++;if($act)$groups[$key]['sections']['requests']['actionable_count']++;}
            }
            $actionable=0;$visible=0;$pending=[];$awareness=[];
            foreach($groups as $key=>&$g){
                $g['visible_count']=array_sum(array_column($g['sections'],'visible_count'));
                $g['actionable_count']=array_sum(array_column($g['sections'],'actionable_count'));
                $actionable+=$g['actionable_count'];$visible+=$g['visible_count'];
                if($g['actionable_count']>0)$pending[]=$g;elseif($g['visible_count']>0)$awareness[]=$g;
            }unset($g);
            $context=null;
            if($application!==null){$key=$applicationKeys[$application]??null;if($key===null)throw new \RuntimeException('workspace_scope_denied');$context=$groups[$key];if($doctor!==null&&$context['doctor_id']!==$doctor)throw new \RuntimeException('workspace_context_conflict');}
            elseif($doctor!==null){$context=$groups['physician:'.$doctor]??null;if($context===null)throw new \RuntimeException('workspace_scope_denied');}
            // Pagination changes displayed groups, never the exact canonical totals.
            return ['count_meaning'=>'ACTIONABLE_WORK_ITEMS','actionable_count'=>$actionable,'group_count'=>count($pending),'visible_unresolved_count'=>$visible,
                'groups'=>array_slice($pending,0,100),'has_more'=>count($pending)>100,'awareness_groups'=>array_slice($awareness,0,100),'awareness_group_count'=>count($awareness),'context'=>$context,'summary_authorized'=>$summary];
        });
    }
    private function counts(int $visible,int $actionable,bool $restricted):array
    {return ['visible_count'=>$visible,'actionable_count'=>$actionable,'summary_only'=>$restricted];}
    private function group(string $key,?string $doctor,?string $application,string $name):array
    {return ['key'=>$key,'doctor_id'=>$doctor,'application_id'=>$application,'name'=>$name,'sections'=>[]];}
}
