<?php
declare(strict_types=1);
namespace Media\Services;
require_once __DIR__.'/MediaReviewAudit.php';
require_once __DIR__.'/MediaReplacementReasons.php';
require_once __DIR__.'/../contracts/PrivateMediaStoragePort.php';
use Media\Contracts\PrivateMediaStoragePort;
use Platform\Contracts\{TrustedAuthorizationContext,AuthorizationRequirement,AuthorizationPlane,RiskLevel,CapabilitySet};
use Platform\Services\AuthorizationBoundary;
use PDO;
use RuntimeException;
final class MediaReplacementService
{
    public const CAPABILITY='media_review_request_replacement';
    public const EVENT='MEDIA_REVIEW_REPLACEMENT_REQUESTED';
    public function __construct(private PDO $pdo,private PrivateMediaStoragePort $storage){}
    public static function requirement():AuthorizationRequirement
    {
        return new AuthorizationRequirement(authorizationPlane:AuthorizationPlane::INTERNAL_OPERATOR,riskLevel:RiskLevel::R1,
            action:'request_replacement',resourceType:'media_review_submission',actorAuthenticatedRequired:true,
            capabilitiesRequired:new CapabilitySet([self::CAPABILITY]),auditTrailRequired:true);
    }
    public function request(?TrustedAuthorizationContext $context,string $id,mixed $reason,mixed $feedback=''):array
    {
        if($context===null||$context->trustSource()!=='canonical_internal_operator')throw new RuntimeException('replacement_denied');
        if($this->pdo->inTransaction())throw new RuntimeException('replacement_outer_transaction');
        $old=[];$attempt=false;
        try {
            if(!$this->pdo->beginTransaction())throw new RuntimeException('replacement_begin_failed');
            $prepare=function()use($id,$reason,$feedback,&$old):array{
                if(!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D',$id))throw new RuntimeException('replacement_invalid_request');
                $s=$this->pdo->prepare('SELECT owner_id FROM media_review_submissions WHERE submission_id=?');$s->execute([$id]);$owner=$s->fetchColumn();
                if($owner===false)throw new RuntimeException('replacement_not_found');
                $s=$this->pdo->prepare('SELECT doctor_id FROM profiles_doctors WHERE doctor_id=? FOR UPDATE');$s->execute([$owner]);if($s->fetchColumn()===false)throw new RuntimeException('replacement_conflict');
                $s=$this->pdo->prepare('SELECT * FROM media_review_submissions WHERE submission_id=? FOR UPDATE');$s->execute([$id]);$candidate=$s->fetch(PDO::FETCH_ASSOC);
                if(!$candidate||$candidate['owner_id']!==$owner||$candidate['owner_type']!=='PHYSICIAN'||!in_array($candidate['purpose'],['DOCTOR_PROFILE_PHOTO','PHYSICIAN_PERSONAL_LOGO'],true)||$candidate['technical_status']!=='READY'||$candidate['review_status']!=='PENDING_REVIEW')throw new RuntimeException('replacement_conflict');
                [$code,$text]=MediaReplacementReasons::validate($reason,$feedback);
                $s=$this->pdo->prepare("SELECT * FROM media_review_files WHERE submission_id=? AND role='AUTO_PROPOSAL' FOR UPDATE");$s->execute([$id]);
                foreach($s->fetchAll(PDO::FETCH_ASSOC) as $file){
                    $expected='private/media-review/'.hash('sha256','PHYSICIAN:'.$owner).'/'.$id.'/auto_proposal/'.$file['file_id'].'.webp';
                    if(!preg_match('/^[0-9a-f-]{36}$/D',$file['file_id'])||$file['storage_key']!==$expected)throw new RuntimeException('replacement_integrity_failed');
                    $old[]=$expected;
                }
                $this->pdo->prepare("DELETE FROM media_review_files WHERE submission_id=? AND role='AUTO_PROPOSAL'")->execute([$id]);
                $this->pdo->prepare("UPDATE media_review_submissions SET review_status='NEEDS_WORK',review_reason_code=?,review_feedback=?,review_decided_at=CURRENT_TIMESTAMP(6),updated_at=CURRENT_TIMESTAMP WHERE submission_id=?")->execute([$code,$text,$id]);
                return ['submission_id'=>$id,'physician_id'=>(string)$owner,'purpose'=>$candidate['purpose'],'reason_code'=>$code];
            };
            $audit=new MediaReviewAudit($this->pdo,$context,$prepare,self::EVENT,self::CAPABILITY,'POST /api/internal/media-review/request-replacement.php');
            if(!(new AuthorizationBoundary())->authorize($context,self::requirement(),$audit)->allowed())throw new RuntimeException('replacement_denied');
            $attempt=true;if(!$this->pdo->commit())throw new RuntimeException('replacement_commit_failed');
        }catch(\Throwable $e){
            if($this->pdo->inTransaction())try{$this->pdo->rollBack();}catch(\Throwable){error_log('replacement_rollback_unconfirmed');}
            elseif($attempt)error_log('replacement_commit_requires_reconciliation');
            throw $e;
        }
        foreach($old as $key)try{$this->storage->delete($key);}catch(\Throwable){error_log('replacement_proposal_cleanup_failed');}
        return ['ok'=>true,'submission_id'=>$id,'technical_status'=>'READY','review_status'=>'NEEDS_WORK'];
    }
}
