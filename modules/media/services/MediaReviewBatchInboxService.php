<?php
declare(strict_types=1);
namespace Media\Services;
require_once __DIR__.'/MediaReviewAuthority.php';
use PDO;
use Platform\Contracts\{AuthorizationContext,TrustedAuthorizationContext};
final class MediaReviewBatchInboxService
{
    public function __construct(private PDO $pdo){}
    private const SUMMARY="SELECT b.batch_id,b.owner_type,b.owner_id,p.display_name owner_display_name,b.status,b.opened_at,b.last_activity_at,b.submitted_at,COUNT(s.submission_id) item_count,SUM(s.review_status='PENDING_REVIEW') pending_count,SUM(s.review_status='APPROVED') approved_count,SUM(s.review_status='NEEDS_WORK') needs_work_count,SUM(s.purpose='DOCTOR_PROFILE_PHOTO') photo_count,SUM(s.purpose='PHYSICIAN_PERSONAL_LOGO') logo_count,SUM(s.purpose='DOCTOR_GALLERY') gallery_count FROM media_review_batches b JOIN media_review_submissions s ON s.batch_id=b.batch_id LEFT JOIN profiles_doctors p ON p.doctor_id=b.owner_id ";
    private function summary(array $r):array
    {
        foreach(['item_count','pending_count','approved_count','needs_work_count','photo_count','logo_count','gallery_count'] as $key)$r[$key]=(int)$r[$key];
        $r['owner_display_name']=trim((string)$r['owner_display_name'])?:'Perfil médico';return $r;
    }
    private function pagination(int $limit,int $offset):void{if($limit<1||$limit>50||$offset<0||$offset>1000000)throw new \InvalidArgumentException('invalid_pagination');}
    public function listing(AuthorizationContext|TrustedAuthorizationContext|null $context,int $limit=25,int $offset=0):array
    {
        MediaReviewAuthority::requireRead($context);$this->pagination($limit,$offset);
        $s=$this->pdo->prepare(self::SUMMARY."WHERE b.status='SUBMITTED' GROUP BY b.batch_id,p.display_name HAVING pending_count>0 ORDER BY b.submitted_at,b.batch_id LIMIT ? OFFSET ?");
        $s->bindValue(1,$limit+1,PDO::PARAM_INT);$s->bindValue(2,$offset,PDO::PARAM_INT);$s->execute();$rows=$s->fetchAll(PDO::FETCH_ASSOC);$more=count($rows)>$limit;
        return ['items'=>array_map($this->summary(...),array_slice($rows,0,$limit)),'pagination'=>['limit'=>$limit,'offset'=>$offset,'has_more'=>$more,'next_offset'=>$more?$offset+$limit:null]];
    }
    public function detail(AuthorizationContext|TrustedAuthorizationContext|null $context,string $id,int $limit=50,int $offset=0):array
    {
        MediaReviewAuthority::requireRead($context);$this->pagination($limit,$offset);
        if(!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D',$id))throw new \InvalidArgumentException('invalid_batch');
        $s=$this->pdo->prepare(self::SUMMARY."WHERE b.batch_id=? AND b.status='SUBMITTED' GROUP BY b.batch_id,p.display_name");$s->execute([$id]);$batch=$s->fetch(PDO::FETCH_ASSOC);
        if(!$batch)throw new \RuntimeException('batch_not_found');
        $s=$this->pdo->prepare("SELECT s.submission_id,s.owner_type,s.owner_id,s.purpose,s.technical_status,s.review_status,s.created_at,s.updated_at,f.mime_type,f.width,f.height,f.byte_size FROM media_review_submissions s LEFT JOIN media_review_files f ON f.submission_id=s.submission_id AND f.role='REVIEW' WHERE s.batch_id=? ORDER BY s.created_at,s.submission_id LIMIT ? OFFSET ?");
        $s->bindValue(1,$id);$s->bindValue(2,$limit+1,PDO::PARAM_INT);$s->bindValue(3,$offset,PDO::PARAM_INT);$s->execute();$rows=$s->fetchAll(PDO::FETCH_ASSOC);$more=count($rows)>$limit;$items=[];
        foreach(array_slice($rows,0,$limit) as $r){$r['owner_display_name']=trim((string)$batch['owner_display_name'])?:'Perfil médico';$r['review']=['mime_type'=>$r['mime_type'],'width'=>(int)$r['width'],'height'=>(int)$r['height'],'byte_size'=>(int)$r['byte_size']];unset($r['mime_type'],$r['width'],$r['height'],$r['byte_size']);$items[]=$r;}
        return ['batch'=>$this->summary($batch),'items'=>$items,'pagination'=>['limit'=>$limit,'offset'=>$offset,'has_more'=>$more,'next_offset'=>$more?$offset+$limit:null]];
    }
}
