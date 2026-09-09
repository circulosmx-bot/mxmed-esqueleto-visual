<?php
declare(strict_types=1);
namespace Media\Services;
require_once __DIR__.'/MediaReviewAuthority.php';
use Platform\Contracts\{AuthorizationContext,TrustedAuthorizationContext};
use PDO;

final class MediaReviewInboxService
{
    public const DEFAULT_LIMIT = 25;
    public const MAX_LIMIT = 50;
    public function __construct(private PDO $pdo) {}

    public function pending(AuthorizationContext|TrustedAuthorizationContext|null $context, int $limit = self::DEFAULT_LIMIT, int $offset = 0): array
    {
        MediaReviewAuthority::requireRead($context);
        $limit = max(1,min(self::MAX_LIMIT,$limit));
        if ($offset < 0 || $offset > 1000000) throw new \InvalidArgumentException('invalid_pagination');
        // One metadata-only query: no N+1 owner lookup and no private file reads.
        $s = $this->pdo->prepare("SELECT s.submission_id,s.owner_type,s.owner_id,p.display_name AS owner_display_name,s.purpose,s.technical_status,s.review_status,s.created_at,s.updated_at,f.mime_type,f.width,f.height,f.byte_size FROM media_review_submissions s JOIN media_review_files f ON f.submission_id=s.submission_id AND f.role='REVIEW' LEFT JOIN profiles_doctors p ON p.doctor_id=s.owner_id WHERE s.batch_id IS NULL AND s.owner_type='PHYSICIAN' AND s.purpose IN ('DOCTOR_PROFILE_PHOTO','PHYSICIAN_PERSONAL_LOGO','DOCTOR_GALLERY') AND s.technical_status='READY' AND s.review_status='PENDING_REVIEW' ORDER BY s.created_at ASC,s.submission_id ASC LIMIT ? OFFSET ?");
        $s->bindValue(1,$limit+1,PDO::PARAM_INT);$s->bindValue(2,$offset,PDO::PARAM_INT);$s->execute();
        $rows = $s->fetchAll(PDO::FETCH_ASSOC);
        $more = count($rows)>$limit;
        $items = [];
        foreach (array_slice($rows,0,$limit) as $row) {
            $items[] = [
                'submission_id'=>$row['submission_id'],'owner_type'=>$row['owner_type'],'owner_id'=>$row['owner_id'],
                'owner_display_name'=>trim((string)$row['owner_display_name']) ?: 'Perfil médico',
                'purpose'=>$row['purpose'],'technical_status'=>$row['technical_status'],'review_status'=>$row['review_status'],
                'created_at'=>$row['created_at'],'updated_at'=>$row['updated_at'],
                'review'=>['mime_type'=>$row['mime_type'],'width'=>(int)$row['width'],'height'=>(int)$row['height'],'byte_size'=>(int)$row['byte_size']]
            ];
        }
        return ['items'=>$items,'pagination'=>['limit'=>$limit,'offset'=>$offset,'has_more'=>$more,'next_offset'=>$more?$offset+$limit:null]];
    }
}
