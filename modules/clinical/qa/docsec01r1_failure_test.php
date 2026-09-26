<?php
declare(strict_types=1);
// Faults exist only in this explicit CLI harness on the R1 disposable database.
$db = $argv[1] ?? '';
if (PHP_SAPI !== 'cli' || !preg_match('/^docsec01r1_qa_[0-9]+$/D', $db)) exit(2);
require_once __DIR__ . '/../../../api/_lib/clinical_note_capture_atomic.php';
function clinical_note_capture_extract_preview_url(array $document): string { return ''; }
class R1CommitFault extends PDO {
    public bool $fault = false;
    public int $faultCommitNumber = 0;
    private int $commits = 0;
    public function commit(): bool {
        $result = parent::commit();
        $this->commits++;
        if ($this->fault || $this->commits === $this->faultCommitNumber) throw new RuntimeException('simulated lost COMMIT acknowledgement');
        return $result;
    }
}
$pdo = new R1CommitFault('mysql:host=localhost;dbname=' . $db, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$token = bin2hex(random_bytes(16));
$pdo->prepare("INSERT INTO clinical_note_capture_tokens (token,patient_id,status,expires_at,created_at,updated_at) VALUES (?,'p_plan02_review','pending',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 15 MINUTE),UTC_TIMESTAMP(),UTC_TIMESTAMP())")->execute([$token]);
$row=['id'=>(int)$pdo->lastInsertId(),'token'=>$token];
$lock=clinical_note_capture_lock($pdo,$token);
$before=(int)$pdo->query('SELECT COUNT(*) FROM clinical_documents')->fetchColumn();
$writer = function() use($pdo): array {
    $uuid=bin2hex(random_bytes(16));
    $pdo->prepare("INSERT INTO clinical_documents (document_uuid,document_type,patient_id,title,payload_json,created_by_user_id,event_datetime,created_at,updated_at) VALUES (?,'pdf','p_plan02_review','DOCSEC01-R1 synthetic fault','{}','docsec01r1',UTC_TIMESTAMP(),UTC_TIMESTAMP(),UTC_TIMESTAMP())")->execute([$uuid]);
    return ['document_db_id'=>(int)$pdo->lastInsertId(),'document_id'=>$uuid];
};
try {
    try {
        clinical_note_capture_legacy_transaction($pdo,$row,function() use($writer) { $writer(); throw new RuntimeException('definite failure'); });
        throw new LogicException('expected failure');
    } catch (RuntimeException $e) { if ($e->getMessage() !== 'definite failure') throw $e; }
    if ((int)$pdo->query('SELECT COUNT(*) FROM clinical_documents')->fetchColumn() !== $before) throw new RuntimeException('rollback document leaked');
    $pdo->fault=true;
    try { clinical_note_capture_legacy_transaction($pdo,$row,$writer); throw new LogicException('expected lost ack'); }
    catch (RuntimeException $e) { if ($e->getMessage() !== 'simulated lost COMMIT acknowledgement') throw $e; }
    $other=new PDO('mysql:host=localhost;dbname='.$db,'root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $q=$other->prepare('SELECT status,document_id FROM clinical_note_capture_tokens WHERE id=?');$q->execute([$row['id']]);$result=$q->fetch(PDO::FETCH_ASSOC);
    if ($result['status'] !== 'uploaded' || !$result['document_id'] || (int)$other->query('SELECT COUNT(*) FROM clinical_documents')->fetchColumn() !== $before+1) throw new RuntimeException('ambiguous outcome split');
    $report=['definite_failure_rollback'=>'PASS','lost_commit_ack_authoritative_read'=>'PASS','documents'=>1];
} finally { clinical_note_capture_unlock($pdo,$lock); }

require_once __DIR__ . '/../../../api/_lib/clinical_multipart_document_service.php';
$root=$argv[2] ?? '';
if ($root === '' || !str_contains($root, '/docsec01r1-')) throw new RuntimeException('isolated storage root required');
if (!is_dir($root)) mkdir($root,0700,true);
$source=$root.'/synthetic.pdf';file_put_contents($source,"%PDF-1.4\n% synthetic R1\n%%EOF\n");
$storage=new ClinicalPrivateBinaryStorage($root);
foreach (['precommit','lost_ack'] as $fault) {
    $pdo=new R1CommitFault('mysql:host=localhost;dbname='.$db,'root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $token=bin2hex(random_bytes(16));
    $pdo->prepare("INSERT INTO clinical_note_capture_tokens (token,patient_id,encounter_key,status,expires_at,created_at,updated_at) VALUES (?,'p_plan02_review','enc:1015','pending',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 15 MINUTE),UTC_TIMESTAMP(),UTC_TIMESTAMP())")->execute([$token]);
    $row=['id'=>(int)$pdo->lastInsertId(),'token'=>$token];
    $lock=clinical_note_capture_lock($pdo,$token);
    $before=(int)$pdo->query('SELECT COUNT(*) FROM clinical_documents')->fetchColumn();
    $context=['operation'=>'CREATE_ENCOUNTER_DOCUMENT','doctor_id'=>'1','patient_id'=>'p_plan02_review','context_type'=>'ENCOUNTER','context_id'=>'1015','document_type'=>'pdf','metadata'=>[]];
    $called=false;
    $create=function(PDO $tr,string $uuid) use($row,$fault,&$called): array {
        $called=true;
        $tr->prepare("INSERT INTO clinical_documents (document_uuid,document_type,patient_id,title,payload_json,created_by_user_id,event_datetime,created_at) VALUES (?,'pdf','p_plan02_review','R1 canonical fault','{}','docsec01r1',UTC_TIMESTAMP(),UTC_TIMESTAMP())")->execute([$uuid]);
        $id=(int)$tr->lastInsertId();
        clinical_note_capture_complete_document($tr,$row,$id,$uuid);
        if ($fault==='precommit') throw new RuntimeException('simulated canonical precommit failure');
        return ['document_id'=>$id,'document_uuid'=>$uuid,'result_column'=>'document_id','result_id'=>$id];
    };
    $fetch=static function(PDO $tr,string $column,int $id): array {
        $q=$tr->prepare('SELECT id AS document_id,document_uuid FROM clinical_documents WHERE id=?');$q->execute([$id]);return $q->fetch(PDO::FETCH_ASSOC);
    };
    if ($fault==='lost_ack') $pdo->faultCommitNumber=2; // STAGED commit first; clinical commit second.
    try {
        try {
            (new ClinicalMultipartDocumentService($pdo,$storage))->execute($context,'note-capture.'.hash('sha256',$token),'docsec01r1',$source,'synthetic.pdf',new DateTimeImmutable('+15 minutes'),$create,$fetch);
            throw new LogicException('fault not injected');
        } catch (RuntimeException $e) {
            $expected = $fault === 'precommit' ? 'simulated canonical precommit failure' : 'simulated lost COMMIT acknowledgement';
            if ($e->getMessage() !== $expected) throw $e;
        }
        if (!$called) throw new RuntimeException('canonical callback not exercised');
        $q=$other->prepare('SELECT status,document_id FROM clinical_note_capture_tokens WHERE id=?');$q->execute([$row['id']]);$result=$q->fetch(PDO::FETCH_ASSOC);
        $delta=(int)$other->query('SELECT COUNT(*) FROM clinical_documents')->fetchColumn()-$before;
        if ($fault==='precommit' && ($result['status']!=='pending' || $delta!==0)) throw new RuntimeException('canonical rollback split');
        if ($fault==='lost_ack' && ($result['status']!=='uploaded' || !$result['document_id'] || $delta!==1)) throw new RuntimeException('canonical ambiguous commit split');
        $report['canonical_'.$fault]=['result'=>'PASS','state'=>$result['status'],'documents'=>$delta];
    } finally {clinical_note_capture_unlock($pdo,$lock);}
}
echo json_encode($report);
