<?php
declare(strict_types=1);
namespace Media\Services;
use PDO;
use RuntimeException;
/** Canonical submitted membership and SOURCE metadata; never a client-selected key. */
final class BatchOriginals
{
    public static function load(PDO $pdo,string $id):array
    {
        if(!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D',$id))throw new RuntimeException('intervention_invalid_request');
        $s=$pdo->prepare("SELECT * FROM media_review_batches WHERE batch_id=? AND owner_type='PHYSICIAN' AND status='SUBMITTED'");$s->execute([$id]);$batch=$s->fetch(PDO::FETCH_ASSOC);
        if(!$batch)throw new RuntimeException('intervention_not_found');
        $s=$pdo->prepare('SELECT * FROM media_review_submissions WHERE batch_id=? ORDER BY purpose,created_at,submission_id');$s->execute([$id]);$items=$s->fetchAll(PDO::FETCH_ASSOC);
        if(!$items||count($items)>100)throw new RuntimeException('intervention_conflict');
        foreach($items as &$item){
            if($item['owner_type']!=='PHYSICIAN'||$item['owner_id']!==$batch['owner_id']||$item['technical_status']!=='READY'||!in_array($item['purpose'],['DOCTOR_PROFILE_PHOTO','PHYSICIAN_PERSONAL_LOGO','DOCTOR_GALLERY'],true)||!in_array($item['review_status'],['PENDING_REVIEW','APPROVED','NEEDS_WORK','WITHDRAWN'],true))throw new RuntimeException('intervention_conflict');
            $s=$pdo->prepare("SELECT * FROM media_review_files WHERE submission_id=? AND role='SOURCE'");$s->execute([$item['submission_id']]);$files=$s->fetchAll(PDO::FETCH_ASSOC);
            if(count($files)!==1)throw new RuntimeException('intervention_integrity_failed');$item['source']=$files[0];
        }
        return ['batch'=>$batch,'items'=>$items];
    }
    public static function entry(array $batch,array $item,int $order):array
    {
        $f=$item['source'];$ext=match($f['mime_type']){'image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp',default=>throw new RuntimeException('intervention_integrity_failed')};
        $prefix=match($item['purpose']){'DOCTOR_PROFILE_PHOTO'=>'01_PERFIL/profile-photo','PHYSICIAN_PERSONAL_LOGO'=>'02_LOGOTIPO/logo','DOCTOR_GALLERY'=>'03_GALERIA/gallery'};
        // Full canonical UUID plus order avoids collisions even with identical original names.
        $name=$prefix.'_'.sprintf('%03d',$order).'_'.$item['submission_id'].'.'.$ext;
        if(!preg_match('~^[A-Za-z0-9_/-]+\.(jpg|png|webp)$~D',$name)||str_contains($name,'..'))throw new RuntimeException('intervention_integrity_failed');
        return ['archive_schema_version'=>1,'owner_type'=>'PHYSICIAN','owner_id'=>$batch['owner_id'],'batch_id'=>$batch['batch_id'],'submission_id'=>$item['submission_id'],'purpose'=>$item['purpose'],'archive_filename'=>$name,
            // Existing canonical ingestion does not retain client filenames. Never invent one.
            'original_filename'=>null,'mime_type'=>$f['mime_type'],'byte_size'=>(int)$f['byte_size'],'sha256'=>$f['checksum_sha256'],'uploaded_at'=>$item['created_at']];
    }
}
