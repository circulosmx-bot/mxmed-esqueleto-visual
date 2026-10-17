<?php
declare(strict_types=1);
namespace Identity\ProfessionalSources;
use PDO;
use Identity\Accreditation\EvidenceCipher;

/** A reference to the original encrypted object, never a ciphertext copy or C4 approval. */
final class SourceEvidenceReference
{
    public static function assertCanonical(PDO $p,string $reference,string $profile):void
    {
        if(!str_starts_with($reference,'act03:'))return;
        if(!preg_match('/^act03:([a-f0-9-]{36}):(VID01|CRD02)$/D',$reference,$m))throw new \RuntimeException('source_reference_invalid');
        self::operation($p,$m[1],$profile);
    }
    public static function operation(PDO $p,string $id,string $profile):array
    {
        if(!$p->inTransaction())throw new \RuntimeException('source_reference_transaction_required');
        $q=$p->prepare('SELECT o.*,r.account_id,r.profile_doctor_id,r.state request_state FROM auth_professional_source_operations o JOIN auth_professional_source_requests r ON r.origin_id=o.origin_id WHERE o.operation_id=? FOR UPDATE');$q->execute([$id]);$o=$q->fetch(PDO::FETCH_ASSOC);
        if(!is_array($o)||$o['state']!=='COMPLETE'||$o['request_state']!=='COMPLETE'||$o['profile_doctor_id']!==$profile)throw new \RuntimeException('source_reference_not_verified');
        $q=$p->prepare('SELECT * FROM profiles_verified_identities WHERE doctor_id=?');$q->execute([$profile]);$i=$q->fetchAll(PDO::FETCH_ASSOC);
        $q=$p->prepare("SELECT * FROM profiles_doctor_credentials WHERE doctor_id=? AND credential_type='PROFESSIONAL'");$q->execute([$profile]);$c=$q->fetchAll(PDO::FETCH_ASSOC);
        if(count($i)!==1||count($c)!==1)throw new \RuntimeException('source_reference_revision_changed');
        $r=json_decode($o['result_json'],true,32,JSON_THROW_ON_ERROR);
        if((string)$o['result_identity_id']!==$r['identity_id']||(string)$o['result_credential_id']!==$r['credential_id']||(string)$i[0]['verified_identity_id']!==$r['identity_id']||(string)$c[0]['credential_id']!==$r['credential_id']
            ||hash('sha256',json_encode(['identity'=>$i[0],'credential'=>$c[0]],JSON_THROW_ON_ERROR))!==$r['sources_hash'])throw new \RuntimeException('source_reference_revision_changed');
        return $o;
    }
    public static function open(PDO $p,array $c4file,EvidenceCipher $cipher,\Media\Contracts\PrivateMediaStoragePort $storage):string
    {
        $q=$p->prepare('SELECT f.*,r.account_id,r.profile_doctor_id,o.operation_id FROM auth_professional_source_files f JOIN auth_professional_source_requests r ON r.origin_id=f.origin_id JOIN auth_professional_source_operations o ON o.origin_id=r.origin_id WHERE f.file_id=?');$q->execute([$c4file['professional_source_file_id']]);$f=$q->fetch(PDO::FETCH_ASSOC);
        if(!is_array($f))throw new \RuntimeException('source_reference_missing');self::operation($p,$f['operation_id'],$f['profile_doctor_id']);
        $q=$p->prepare('SELECT e.account_id,e.profile_doctor_id,h.origin_id FROM auth_physician_evidence_requests e JOIN auth_physician_enrollment_handoffs h ON h.evidence_request_id=e.request_id WHERE e.request_id=?');$q->execute([$c4file['request_id']]);$e=$q->fetch(PDO::FETCH_ASSOC);
        if(!is_array($e)||$e['account_id']!==$f['account_id']||$e['profile_doctor_id']!==$f['profile_doctor_id']||$e['origin_id']!==$f['origin_id'])throw new \RuntimeException('source_reference_scope_denied');
        foreach(['kind','storage_key','key_id','ciphertext_hash','mime_type','retention_policy_reference'] as $k)if($c4file[$k]!==$f[$k])throw new \RuntimeException('source_reference_integrity_denied');
        $opened=$storage->openReadStream($f['storage_key']);try{$bytes=stream_get_contents($opened['stream'],8*1024*1024+128);}finally{fclose($opened['stream']);}
        if(!is_string($bytes)||strlen($bytes)!==$opened['bytes']||!hash_equals($f['ciphertext_hash'],hash('sha256',$bytes)))throw new \RuntimeException('source_document_integrity_denied');
        return $cipher->open($bytes,'ACT03:file:'.$f['origin_id'].':'.$f['file_id'].':'.$f['kind'],$f['key_id']);
    }
}
