<?php
declare(strict_types=1);

require_once __DIR__ . '/clinical_idempotency.php';
require_once __DIR__ . '/clinical_encounter_sections.php';
require_once __DIR__ . '/clinical_observations.php';

function clinical_m5_qa_barrier_reach_if_enabled(array $allowedPoints, ?string $implementationFile = null): bool
{
    $value = getenv('MXMED_CLINICAL_M5_QA_MODE');
    $requested = $value !== false
        && in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on'], true);
    if (!$requested) {
        return false;
    }

    $implementationFile ??= __DIR__ . '/../../modules/clinical/qa/m5_concurrency_barrier.php';
    if (!is_file($implementationFile) || !is_readable($implementationFile)) {
        throw new RuntimeException('M5_QA_BARRIER_IMPLEMENTATION_NOT_AVAILABLE');
    }
    require_once $implementationFile;
    if (!function_exists('clinical_m5_qa_barrier_reach')) {
        throw new RuntimeException('M5_QA_BARRIER_IMPLEMENTATION_NOT_AVAILABLE');
    }
    return clinical_m5_qa_barrier_reach($allowedPoints);
}

function clinical_encounter_integrity_v1_enabled(): bool
{
    $value=strtolower(trim((string)(getenv('MXMED_CLINICAL_ENCOUNTER_INTEGRITY_V1') ?: '')));
    return in_array($value, ['1','true','yes','on'], true);
}

function clinical_encounter_transition_allowed(string $from, string $to): bool
{
    return $from === 'open' && in_array($to, ['closed','voided'], true);
}

function clinical_encounter_status_is_canonical(string $status): bool
{
    return in_array($status, ['open', 'closed', 'voided'], true);
}

function clinical_encounter_attribution_classification(?string $doctorId): string
{
    return $doctorId === null || trim($doctorId) === '' ? 'UNATTRIBUTED' : 'ATTRIBUTED';
}

function clinical_terminal_audit_change_code(array $old, array $new): ?string
{
    if (($old['status'] ?? null) === 'closed') {
        foreach (['closed_at', 'closed_by_user_id', 'auto_note_uuid_final'] as $field) {
            if (($old[$field] ?? null) !== ($new[$field] ?? null)) return 'FIRST_CLOSE_IMMUTABLE';
        }
    }
    if (($old['status'] ?? null) === 'voided') {
        foreach (['voided_at', 'voided_by_user_id', 'void_reason'] as $field) {
            if (($old[$field] ?? null) !== ($new[$field] ?? null)) return 'FIRST_VOID_IMMUTABLE';
        }
    }
    return null;
}

function clinical_canonical_document_class(string $documentType): string
{
    return match (strtolower(trim($documentType))) {
        'order', 'orders', 'lab_order', 'imaging_order', 'orden_estudio' => 'ORDER',
        'lab_result', 'lab_pdf' => 'LAB_RESULT',
        'imaging_result' => 'IMAGING_RESULT',
        'external_result' => 'EXTERNAL_RESULT',
        'external_report' => 'EXTERNAL_REPORT',
        'prescription', 'receta' => 'PRESCRIPTION',
        default => 'ENCOUNTER_DOCUMENT',
    };
}

function clinical_assert_document_class(array $payload): string
{
    $derived=clinical_canonical_document_class((string)($payload['document_type']??''));
    if(array_key_exists('document_class',$payload)){
        $asserted=strtoupper(trim((string)$payload['document_class']));
        if($asserted!==$derived)throw new InvalidArgumentException('DOCUMENT_CLASS_MISMATCH');
    }
    return $derived;
}

function clinical_document_type_is_order(string $documentType): bool
{
    return clinical_canonical_document_class($documentType)==='ORDER';
}

function clinical_v1_multipart_document_write_allowed(bool $isMultipart, bool $hasFile): bool
{
    return !$isMultipart && !$hasFile;
}

function clinical_document_content_rewrite_allowed(string $status, bool $isFinalEncounterNote = false): bool
{
    $status = strtolower(trim($status));
    return !$isFinalEncounterNote && in_array($status, ['draft', 'generated'], true);
}

function clinical_encounter_start_semantic_request(string $doctorId, string $patientId, array $command): array
{
    return [
        'appointment_id' => $command['appointment_id'] ?? null,
        'doctor_id' => $doctorId,
        'encounter_dt' => $command['encounter_dt'] ?? null,
        'encounter_type' => $command['encounter_type'] ?? 'outpatient',
        'patient_id' => $patientId,
    ];
}

function clinical_document_create_operation(string $documentClass): string
{
    return in_array(strtoupper(trim($documentClass)), ['LAB_RESULT','IMAGING_RESULT','EXTERNAL_RESULT','EXTERNAL_REPORT'], true)
        ? 'CREATE_POST_ENCOUNTER_RESULT'
        : 'CREATE_ENCOUNTER_DOCUMENT';
}

function clinical_document_policy_operation(string $documentClass): string
{
    return match (strtoupper(trim($documentClass))) {
        'LAB_RESULT' => 'CREATE_LAB_RESULT',
        'IMAGING_RESULT' => 'CREATE_IMAGING_RESULT',
        'EXTERNAL_RESULT', 'EXTERNAL_REPORT' => 'CREATE_EXTERNAL_RESULT',
        'PRESCRIPTION' => 'CREATE_PRESCRIPTION',
        'ORDER' => 'CREATE_ORDER',
        default => 'CREATE_ENCOUNTER_DOCUMENT',
    };
}

function clinical_document_semantic_request(array $payload, ?string $contentSha256): array
{
    return [
        'content_sha256' => $contentSha256,
        'document_class' => clinical_assert_document_class($payload),
        'document_type' => strtolower(trim((string)($payload['document_type'] ?? ''))),
        'event_datetime' => $payload['event_datetime'] ?? null,
        'logical_payload' => is_array($payload['payload'] ?? null) ? $payload['payload'] : [],
        'media_tag_key' => $payload['media_tag_key'] ?? null,
        'summary' => $payload['summary'] ?? null,
        'title' => $payload['title'] ?? null,
    ];
}

function clinical_document_amendment_reason_validate(string $reason): string
{
    $reason = trim($reason);
    if ($reason === '') {
        throw new InvalidArgumentException('AMENDMENT_REASON_REQUIRED');
    }
    $length = function_exists('mb_strlen') ? mb_strlen($reason, 'UTF-8') : strlen($reason);
    if ($length > 1000) {
        throw new InvalidArgumentException('AMENDMENT_REASON_TOO_LONG');
    }
    return $reason;
}

function clinical_document_lineage_context_matches(array $left, array $right): bool
{
    $leftPatient = trim((string)($left['patient_id'] ?? ''));
    $rightPatient = trim((string)($right['patient_id'] ?? ''));
    $leftEncounter = $left['encounter_ref_id'] ?? null;
    $rightEncounter = $right['encounter_ref_id'] ?? null;
    $leftEncounter = ($leftEncounter === null || $leftEncounter === '') ? null : (string)$leftEncounter;
    $rightEncounter = ($rightEncounter === null || $rightEncounter === '') ? null : (string)$rightEncounter;
    return $leftPatient !== '' && $leftPatient === $rightPatient && $leftEncounter === $rightEncounter;
}

function clinical_document_amendment_semantic_request(
    string $doctorId,
    array $original,
    array $replacement,
    string $reason
): array {
    $payload = is_array($replacement['payload'] ?? null) ? $replacement['payload'] : [];
    return [
        'canonicalization_version' => clinical_idempotency_canonicalization_version(),
        'operation' => 'CREATE_DOCUMENT_AMENDMENT_OR_REPLACEMENT',
        'doctor_id' => trim($doctorId),
        'original_document_id' => (int)($original['id'] ?? 0),
        'original_document_uuid' => (string)($original['document_uuid'] ?? ''),
        'patient_id' => (string)($original['patient_id'] ?? ''),
        'encounter_id' => $original['encounter_ref_id'] ?? null,
        'reason' => clinical_document_amendment_reason_validate($reason),
        'replacement' => clinical_document_semantic_request($replacement, null) + [
            'provenance' => $replacement['provenance'] ?? ($payload['provenance'] ?? null),
        ],
    ];
}

function clinical_finalize_result_normalize(array $encounter, ?array $finalDocument): array
{
    return [
        'encounter_id' => (int)($encounter['encounter_id'] ?? 0),
        'status' => (string)($encounter['status'] ?? ''),
        'closed_at' => $encounter['closed_at'] ?? null,
        'closed_by_user_id' => $encounter['closed_by_user_id'] ?? null,
        'auto_note_uuid_final' => $encounter['auto_note_uuid_final'] ?? ($finalDocument['document_uuid'] ?? null),
        'final_document_id' => isset($finalDocument['document_id']) ? (int)$finalDocument['document_id'] : null,
    ];
}

function clinical_document_operation_policy(string $operation, string $documentClass, string $encounterStatus, array $context=[]): array
{
    $operation=strtoupper(trim($operation)); $class=strtoupper(trim($documentClass)); $status=$encounterStatus;
    if(!clinical_encounter_status_is_canonical($status))return ['allowed'=>false,'code'=>'ENCOUNTER_STATE_INVALID'];
    if ($status === 'voided') return ['allowed'=>false,'code'=>'ENCOUNTER_VOIDED'];
    if (in_array($operation,['AMEND_DOCUMENT','REPLACE_DOCUMENT'],true)) {
        return ['allowed'=>in_array($status,['open','closed'],true),'code'=>in_array($status,['open','closed'],true)?'ALLOWED':'ENCOUNTER_STATE_INVALID'];
    }
    if ($operation === 'CREATE_FINAL_AUTO_NOTE') return ['allowed'=>$status==='open','code'=>$status==='open'?'ALLOWED':'FINAL_NOTE_REQUIRES_OPEN'];
    $postClasses=['LAB_RESULT','IMAGING_RESULT','EXTERNAL_RESULT','EXTERNAL_REPORT'];
    if (in_array($operation,['CREATE_LAB_RESULT','CREATE_IMAGING_RESULT','CREATE_EXTERNAL_RESULT'],true)
        && in_array($class,$postClasses,true)) {
        $validContext=($context['same_patient']??false)===true && ($context['valid_originating_order']??false)===true
          && trim((string)($context['effective_at']??''))!=='' && trim((string)($context['provenance']??''))!=='';
        return ['allowed'=>in_array($status,['open','closed'],true)&&$validContext,
          'code'=>$validContext?'ALLOWED':'DOCUMENT_CONTEXT_MISMATCH'];
    }
    if (in_array($operation,['CREATE_PRESCRIPTION','CREATE_ORDER','CREATE_ENCOUNTER_DOCUMENT'],true)) {
        return ['allowed'=>$status==='open','code'=>$status==='open'?'ALLOWED':'ENCOUNTER_TERMINAL'];
    }
    return ['allowed'=>false,'code'=>'DOCUMENT_OPERATION_UNSUPPORTED'];
}

function clinical_encounter_integrity_required_schema(): array
{
    return ['clinical_encounters','clinical_encounter_sections','clinical_observations','clinical_encounter_amendments',
      'clinical_encounter_start_requests','clinical_idempotency_requests','clinical_encounter_final_notes','clinical_document_revisions',
      'clinical_documents','clinical_document_participants'];
}

function clinical_encounter_integrity_assert_schema_ready(PDO $pdo): void
{
    $drift=[];
    $required=clinical_encounter_integrity_required_schema();
    $marks=implode(',',array_fill(0,count($required),'?'));
    $stmt=$pdo->prepare("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ($marks)");
    $stmt->execute($required);
    $found=$stmt->fetchAll(PDO::FETCH_COLUMN);
    $drift=array_values(array_diff($required,is_array($found)?$found:[]));

    $columns=[
        ['clinical_encounters','open_guard','tinyint'],['clinical_encounters','voided_at',null],
        ['clinical_encounters','voided_by_user_id',null],['clinical_encounters','void_reason',null],
        ['clinical_encounter_sections','payload_schema_version',null],['clinical_encounter_sections','row_version',null],
        ['clinical_observations','row_version',null],['clinical_documents','encounter_ref_id','bigint'],
    ];
    foreach($columns as [$table,$column,$type]){
        $q=$pdo->prepare('SELECT DATA_TYPE,GENERATION_EXPRESSION,IS_NULLABLE,EXTRA FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
        $q->execute([$table,$column]);$row=$q->fetch(PDO::FETCH_ASSOC);
        if(!is_array($row)||($type!==null&&strtolower((string)$row['DATA_TYPE'])!==$type)){$drift[]=$table.'.'.$column;continue;}
        if($column==='open_guard'){
            $expr=strtolower((string)($row['GENERATION_EXPRESSION']??''));
            if(!str_contains($expr,'status')||!str_contains($expr,'open')||str_contains($expr,'concat')
                ||(string)($row['IS_NULLABLE']??'')!=='YES'||!str_contains(strtolower((string)($row['EXTRA']??'')),'stored generated')){
                $drift[]='clinical_encounters.open_guard.expression';
            }
        }
    }

    $indexes=[
        ['clinical_encounters','uq_clinical_encounter_one_open','doctor_id,patient_id,open_guard',0],
        ['clinical_encounter_sections','uq_encounter_section_concept','encounter_id,section_type',0],
        ['clinical_observations','idx_observation_encounter_code_effective','encounter_id,code,effective_at',1],
        ['clinical_encounter_amendments','idx_encounter_amendment_history','encounter_id,amended_at,amendment_id',1],
        ['clinical_encounter_start_requests','uq_encounter_start_idempotency','doctor_id,idempotency_key',0],
        ['clinical_encounter_start_requests','idx_encounter_start_result','encounter_id',1],
        ['clinical_idempotency_requests','uq_clinical_command_idempotency','operation_type,doctor_id,context_type,context_id,idempotency_key',0],
        ['clinical_encounter_final_notes','PRIMARY','encounter_id',0],
        ['clinical_encounter_final_notes','uq_encounter_final_note_document','document_id',0],
        ['clinical_document_revisions','uq_document_revision_new_document','new_document_id',0],
        ['clinical_document_revisions','idx_document_revision_original','original_document_id,revision_id',1],
        ['clinical_documents','idx_clinical_documents_encounter_ref','encounter_ref_id,event_datetime',1],
    ];
    foreach($indexes as [$table,$name,$expected,$nonUnique]){
        $q=$pdo->prepare('SELECT NON_UNIQUE,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ",") AS columns_csv
            FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=? GROUP BY NON_UNIQUE');
        $q->execute([$table,$name]);$row=$q->fetch(PDO::FETCH_ASSOC);
        if(!is_array($row)||(int)$row['NON_UNIQUE']!==$nonUnique||(string)$row['columns_csv']!==$expected)$drift[]=$table.'.'.$name;
    }

    $triggers=[
        'trg_clinical_encounters_v1_before_insert'=>['INSERT','BEFORE','start_must_create_open','doctor_id_required'],
        'trg_clinical_encounters_v1_before_update'=>['UPDATE','BEFORE','encounter_ownership_immutable','encounter_transition_forbidden','first_close_immutable','first_void_immutable'],
    ];
    foreach($triggers as $trigger=>$expected){
        [$event,$timing]=$expected;$markers=array_slice($expected,2);
        $q=$pdo->prepare('SELECT EVENT_MANIPULATION,ACTION_TIMING,ACTION_STATEMENT FROM information_schema.TRIGGERS
            WHERE TRIGGER_SCHEMA=DATABASE() AND TRIGGER_NAME=? AND EVENT_OBJECT_TABLE="clinical_encounters"');
        $q->execute([$trigger]);$row=$q->fetch(PDO::FETCH_ASSOC);$body=strtolower((string)($row['ACTION_STATEMENT']??''));
        if(!is_array($row)||(string)$row['EVENT_MANIPULATION']!==$event||(string)$row['ACTION_TIMING']!==$timing)$drift[]='trigger.'.$trigger;
        foreach($markers as $marker){if(!str_contains($body,$marker))$drift[]='trigger.'.$trigger;}
    }
    $checks=[
        ['clinical_encounters','chk_clinical_encounter_lifecycle_v1',['doctor_id','open','closed','voided','closed_at','voided_at','void_reason']],
        ['clinical_encounter_sections','chk_encounter_section_type_v1',['reason_evolution','physical_exam','follow_up']],
        ['clinical_encounter_sections','chk_encounter_section_version_v1',['payload_schema_version','row_version']],
        ['clinical_observations','chk_observation_version_v1',['row_version']],
        ['clinical_observations','chk_observation_source_v1',['direct_measurement','patient_report','import']],
        ['clinical_observations','chk_observation_bp_v1',['blood_pressure','systolic_mm_hg','diastolic_mm_hg','mmhg']],
        ['clinical_encounter_amendments','chk_encounter_amendment_reason_v1',['reason','char_length']],
        ['clinical_encounter_start_requests','chk_encounter_start_commit_v1',['committed_at','encounter_id']],
        ['clinical_idempotency_requests','chk_idempotency_context_v1',['context_type','encounter','patient']],
        ['clinical_idempotency_requests','chk_idempotency_operation_v1',['create_observation','create_encounter_document','create_post_encounter_result','create_encounter_amendment','create_document_amendment_or_replacement']],
        ['clinical_idempotency_requests','chk_idempotency_committed_result_v1',['committed_at','observation_id','document_id','encounter_amendment_id','document_revision_id']],
        ['clinical_document_revisions','chk_document_revision_reason_v1',['reason','char_length']],
    ];
    foreach($checks as [$table,$name,$markers]){
        $q=$pdo->prepare('SELECT cc.CHECK_CLAUSE FROM information_schema.TABLE_CONSTRAINTS tc
            JOIN information_schema.CHECK_CONSTRAINTS cc ON cc.CONSTRAINT_SCHEMA=tc.CONSTRAINT_SCHEMA AND cc.CONSTRAINT_NAME=tc.CONSTRAINT_NAME
            WHERE tc.CONSTRAINT_SCHEMA=DATABASE() AND tc.TABLE_NAME=? AND tc.CONSTRAINT_NAME=? AND tc.CONSTRAINT_TYPE="CHECK"');
        $q->execute([$table,$name]);$clause=strtolower((string)$q->fetchColumn());
        if($clause===''){$drift[]=$table.'.'.$name;continue;}
        foreach($markers as $marker){if(!str_contains($clause,$marker))$drift[]=$table.'.'.$name;}
    }

    $foreignKeys=[
        ['clinical_encounter_sections','fk_encounter_sections_encounter','encounter_id','clinical_encounters','encounter_id'],
        ['clinical_observations','fk_observations_encounter','encounter_id','clinical_encounters','encounter_id'],
        ['clinical_encounter_amendments','fk_encounter_amendments_encounter','encounter_id','clinical_encounters','encounter_id'],
        ['clinical_encounter_start_requests','fk_encounter_start_result','encounter_id','clinical_encounters','encounter_id'],
        ['clinical_encounter_start_requests','fk_encounter_start_patient','patient_id','patients_patients','patient_id'],
        ['clinical_encounter_final_notes','fk_encounter_final_note_encounter','encounter_id','clinical_encounters','encounter_id'],
        ['clinical_encounter_final_notes','fk_encounter_final_note_document','document_id','clinical_documents','id'],
        ['clinical_document_revisions','fk_document_revision_original','original_document_id','clinical_documents','id'],
        ['clinical_document_revisions','fk_document_revision_supersedes','supersedes_document_id','clinical_documents','id'],
        ['clinical_document_revisions','fk_document_revision_new','new_document_id','clinical_documents','id'],
        ['clinical_documents','fk_clinical_documents_encounter_ref','encounter_ref_id','clinical_encounters','encounter_id'],
        ['clinical_idempotency_requests','fk_idempotency_observation','observation_id','clinical_observations','observation_id'],
        ['clinical_idempotency_requests','fk_idempotency_encounter_amendment','encounter_amendment_id','clinical_encounter_amendments','amendment_id'],
        ['clinical_idempotency_requests','fk_idempotency_document','document_id','clinical_documents','id'],
        ['clinical_idempotency_requests','fk_idempotency_document_revision','document_revision_id','clinical_document_revisions','revision_id'],
        ['clinical_document_participants','fk_cdp_doc','clinical_document_id','clinical_documents','id'],
    ];
    foreach($foreignKeys as [$table,$name,$column,$referenced,$referencedColumn]){
        $q=$pdo->prepare('SELECT rc.REFERENCED_TABLE_NAME,rc.DELETE_RULE,rc.UPDATE_RULE,kcu.COLUMN_NAME,kcu.REFERENCED_COLUMN_NAME
            FROM information_schema.REFERENTIAL_CONSTRAINTS rc JOIN information_schema.KEY_COLUMN_USAGE kcu
              ON kcu.CONSTRAINT_SCHEMA=rc.CONSTRAINT_SCHEMA AND kcu.TABLE_NAME=rc.TABLE_NAME AND kcu.CONSTRAINT_NAME=rc.CONSTRAINT_NAME
            WHERE rc.CONSTRAINT_SCHEMA=DATABASE() AND rc.TABLE_NAME=? AND rc.CONSTRAINT_NAME=?');
        $q->execute([$table,$name]);$row=$q->fetch(PDO::FETCH_ASSOC);
        $expectedDelete=$name==='fk_cdp_doc'?'CASCADE':'RESTRICT';
        if(!is_array($row)||(string)$row['REFERENCED_TABLE_NAME']!==$referenced||(string)$row['DELETE_RULE']!==$expectedDelete
            ||($name!=='fk_cdp_doc'&&(string)$row['UPDATE_RULE']!=='RESTRICT')||(string)$row['COLUMN_NAME']!==$column||(string)$row['REFERENCED_COLUMN_NAME']!==$referencedColumn){
            $drift[]=$table.'.'.$name;
        }
    }
    if($drift!==[])throw new RuntimeException('SCHEMA_NOT_READY: '.implode(',',array_unique($drift)));
}

final class ClinicalEncounterIntegrityRepository
{
    public function __construct(private PDO $pdo) {}

    public function findOpen(string $doctorId,string $patientId,bool $forUpdate=false): ?array
    {
        $sql="SELECT * FROM clinical_encounters WHERE doctor_id=:doctor AND patient_id=:patient AND BINARY status=BINARY 'open' ORDER BY encounter_id LIMIT 1".($forUpdate?' FOR UPDATE':'');
        $stmt=$this->pdo->prepare($sql);$stmt->execute([':doctor'=>$doctorId,':patient'=>$patientId]);$row=$stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row)?$row:null;
    }

    public function start(string $doctorId,string $actorId,string $patientId,array $command,string $idempotencyKey): array
    {
        if(array_key_exists('status',$command))throw new InvalidArgumentException('CLIENT_STATUS_FORBIDDEN');
        $key=clinical_idempotency_key_validate($idempotencyKey);
        $semantic = clinical_encounter_start_semantic_request($doctorId, $patientId, $command);
        $hash=clinical_idempotency_request_hash($semantic);
        $this->pdo->beginTransaction();
        try{
            $claim=$this->pdo->prepare('INSERT INTO clinical_encounter_start_requests
              (doctor_id,idempotency_key,canonicalization_version,request_hash,actor_user_id,patient_id,created_at)
              VALUES (:doctor,:key,1,:hash,:actor,:patient,UTC_TIMESTAMP())');
            $claim->execute([':doctor'=>$doctorId,':key'=>$key,':hash'=>$hash,':actor'=>$actorId,':patient'=>$patientId]);
            $requestId=(int)$this->pdo->lastInsertId();
            $created = false;
            clinical_m5_qa_barrier_reach_if_enabled(['T04_CONCURRENT_START_BEFORE_INSERT']);
            $open=$this->findOpen($doctorId,$patientId,true);
            if($open===null){
                $stmt=$this->pdo->prepare("INSERT INTO clinical_encounters
                  (patient_id,doctor_id,appointment_id,encounter_dt,encounter_type,opened_by_user_id,status,created_at,updated_at)
                  VALUES (:patient,:doctor,:appointment,:dt,:type,:actor,'open',UTC_TIMESTAMP(),UTC_TIMESTAMP())");
                $stmt->execute([':patient'=>$patientId,':doctor'=>$doctorId,':appointment'=>$command['appointment_id']??null,
                  ':dt'=>$command['encounter_dt']??gmdate('Y-m-d H:i:s'),':type'=>$command['encounter_type']??'outpatient',':actor'=>$actorId]);
                $open=$this->getForUpdate((int)$this->pdo->lastInsertId());
                $created = true;
            }
            $done=$this->pdo->prepare('UPDATE clinical_encounter_start_requests SET encounter_id=:encounter,committed_at=UTC_TIMESTAMP() WHERE request_id=:request');
            $done->execute([':encounter'=>$open['encounter_id'],':request'=>$requestId]);$this->pdo->commit();return $open + ['_integrity_start_created' => $created];
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }

    public function replayStart(string $doctorId,string $key,array $semantic): array
    {
        $stmt=$this->pdo->prepare('SELECT * FROM clinical_encounter_start_requests WHERE doctor_id=:doctor AND idempotency_key=:key LIMIT 1');
        $stmt->execute([':doctor'=>$doctorId,':key'=>clinical_idempotency_key_validate($key)]);$row=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!is_array($row)||empty($row['committed_at']))throw new ClinicalIdempotencyException('IDEMPOTENCY_RESULT_NOT_READY','Original START not committed');
        if(!hash_equals((string)$row['request_hash'],clinical_idempotency_request_hash($semantic)))throw new ClinicalIdempotencyException('IDEMPOTENCY_KEY_REUSED','Idempotency-Key reused',409);
        $enc=$this->get((int)$row['encounter_id']);if($enc===null)throw new RuntimeException('START_RESULT_MISSING');return $enc + ['_integrity_start_created' => false];
    }

    public function finalize(int $encounterId,string $actorId,int $finalDocumentId): array
    {
        $this->pdo->beginTransaction();try{$row=$this->getForUpdate($encounterId);$status=(string)$row['status'];
          if($status==='closed'){$this->pdo->commit();return $row;} if($status!=='open')throw new RuntimeException('ENCOUNTER_VOIDED');
          $rel=$this->pdo->prepare('INSERT INTO clinical_encounter_final_notes (encounter_id,document_id,created_at) VALUES (:e,:d,UTC_TIMESTAMP())');
          $rel->execute([':e'=>$encounterId,':d'=>$finalDocumentId]);
          $stmt=$this->pdo->prepare("UPDATE clinical_encounters SET status='closed',closed_at=UTC_TIMESTAMP(),closed_by_user_id=:actor,updated_at=UTC_TIMESTAMP() WHERE encounter_id=:id AND status='open'");
          $stmt->execute([':actor'=>$actorId,':id'=>$encounterId]);if($stmt->rowCount()!==1)throw new RuntimeException('ENCOUNTER_TERMINAL');
          $row=$this->getForUpdate($encounterId);$this->pdo->commit();return $row;
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}}

    public function finalizeWithNoteFactory(int $encounterId, string $actorId, callable $finalNoteFactory): array
    {
        $this->pdo->beginTransaction();
        try {
            clinical_m5_qa_barrier_reach_if_enabled([
                'T11_CONCURRENT_FINALIZE_BEFORE_TERMINAL_LOCK_OR_COMMIT',
                'T26_FINALIZE_VOID_RACE_BEFORE_TERMINAL_LOCK',
            ]);
            $row = $this->getForUpdate($encounterId);
            $status = (string)$row['status'];
            if ($status === 'closed') {
                $finalDocument=$this->finalDocument($encounterId);
                $this->pdo->commit();
                return clinical_finalize_result_normalize($row,$finalDocument);
            }
            if ($status !== 'open') {
                throw new RuntimeException('ENCOUNTER_VOIDED');
            }
            $finalDocument=$finalNoteFactory($this->pdo,$row);
            if(!is_array($finalDocument))throw new RuntimeException('FINAL_NOTE_CREATION_FAILED');
            $finalDocumentId=(int)($finalDocument['document_id']??0);
            $finalDocumentUuid=trim((string)($finalDocument['document_uuid']??''));
            if ($finalDocumentId <= 0||$finalDocumentUuid==='') {
                throw new RuntimeException('FINAL_NOTE_CREATION_FAILED');
            }
            $relation = $this->pdo->prepare('INSERT INTO clinical_encounter_final_notes
                (encounter_id, document_id, created_at) VALUES (:encounter_id, :document_id, UTC_TIMESTAMP())');
            $relation->execute([':encounter_id' => $encounterId, ':document_id' => $finalDocumentId]);
            $update = $this->pdo->prepare("UPDATE clinical_encounters
                SET status='closed', closed_at=UTC_TIMESTAMP(), closed_by_user_id=:actor,
                    auto_note_uuid_final=:document_uuid, updated_at=UTC_TIMESTAMP()
                WHERE encounter_id=:encounter_id AND status='open'");
            $update->execute([':actor' => $actorId,':document_uuid'=>$finalDocumentUuid, ':encounter_id' => $encounterId]);
            if ($update->rowCount() !== 1) {
                throw new RuntimeException('ENCOUNTER_TERMINAL');
            }
            $closed = $this->getForUpdate($encounterId);
            $this->pdo->commit();
            return clinical_finalize_result_normalize($closed,$finalDocument);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function void(int $encounterId,string $actorId,string $reason): array
    {
        $reason=trim($reason);if($reason==='')throw new InvalidArgumentException('VOID_REASON_REQUIRED');
        $this->pdo->beginTransaction();try{
          clinical_m5_qa_barrier_reach_if_enabled(['T26_FINALIZE_VOID_RACE_BEFORE_TERMINAL_LOCK']);
          $row=$this->getForUpdate($encounterId);$status=(string)$row['status'];
          if($status==='voided'){$this->pdo->commit();return $row;}if($status!=='open')throw new RuntimeException('ENCOUNTER_CLOSED');
          $stmt=$this->pdo->prepare("UPDATE clinical_encounters SET status='voided',voided_at=UTC_TIMESTAMP(),voided_by_user_id=:actor,void_reason=:reason,updated_at=UTC_TIMESTAMP() WHERE encounter_id=:id AND status='open'");
          $stmt->execute([':actor'=>$actorId,':reason'=>$reason,':id'=>$encounterId]);if($stmt->rowCount()!==1)throw new RuntimeException('ENCOUNTER_TERMINAL');
          $row=$this->getForUpdate($encounterId);$this->pdo->commit();return $row;
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}}

    public function appendEncounterAmendment(int $encounterId,array $target,string $reason,string $actor,array $correction): int
    {
        $reason=trim($reason);if($reason==='')throw new InvalidArgumentException('AMENDMENT_REASON_REQUIRED');
        $this->pdo->beginTransaction();try{$e=$this->getForUpdate($encounterId);if(($e['status']??'')!=='closed')throw new RuntimeException('AMENDMENT_REQUIRES_CLOSED');
          $stmt=$this->pdo->prepare('INSERT INTO clinical_encounter_amendments
           (encounter_id,target_type,target_id,target_field,reason,author_user_id,amended_at,correction_payload_json,previous_effective_reference)
           VALUES (:e,:type,:target,:field,:reason,:actor,UTC_TIMESTAMP(),:payload,:previous)');
          $stmt->execute([':e'=>$encounterId,':type'=>$target['type']??'',':target'=>$target['id']??null,':field'=>$target['field']??null,
            ':reason'=>$reason,':actor'=>$actor,':payload'=>json_encode($correction,JSON_THROW_ON_ERROR),':previous'=>$target['previous_reference']??null]);
          $id=(int)$this->pdo->lastInsertId();$this->pdo->commit();return $id;
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}}

    public function appendEncounterAmendmentInTransaction(int $encounterId,array $target,string $reason,string $actor,array $correction): int
    {
        $reason=trim($reason);if($reason==='')throw new InvalidArgumentException('AMENDMENT_REASON_REQUIRED');
        $e=$this->getForUpdate($encounterId);if(($e['status']??'')!=='closed')throw new RuntimeException('AMENDMENT_REQUIRES_CLOSED');
        $stmt=$this->pdo->prepare('INSERT INTO clinical_encounter_amendments
          (encounter_id,target_type,target_id,target_field,reason,author_user_id,amended_at,correction_payload_json,previous_effective_reference)
          VALUES (:e,:type,:target,:field,:reason,:actor,UTC_TIMESTAMP(),:payload,:previous)');
        $stmt->execute([':e'=>$encounterId,':type'=>$target['type']??'',':target'=>$target['id']??null,':field'=>$target['field']??null,
          ':reason'=>$reason,':actor'=>$actor,':payload'=>json_encode($correction,JSON_THROW_ON_ERROR),':previous'=>$target['previous_reference']??null]);
        return (int)$this->pdo->lastInsertId();
    }

    public function fetchAmendment(int $amendmentId): array
    {
        $stmt=$this->pdo->prepare('SELECT * FROM clinical_encounter_amendments WHERE amendment_id=:id');$stmt->execute([':id'=>$amendmentId]);
        $row=$stmt->fetch(PDO::FETCH_ASSOC);if(!is_array($row))throw new RuntimeException('AMENDMENT_NOT_FOUND');return $row;
    }

    public function createDocumentRevision(int $originalId,int $newId,string $reason,string $actor,?int $supersedesId=null): int
    {
        if(!$this->pdo->inTransaction())throw new LogicException('DOCUMENT_REVISION_TRANSACTION_REQUIRED');
        $reason=trim($reason);
        if($originalId<=0||$newId<=0||$originalId===$newId||$reason==='')throw new InvalidArgumentException('DOCUMENT_REVISION_INVALID');
        $stmt=$this->pdo->prepare('INSERT INTO clinical_document_revisions
          (original_document_id,supersedes_document_id,new_document_id,reason,author_user_id,created_at)
          VALUES (:original,:supersedes,:new,:reason,:actor,UTC_TIMESTAMP())');
        $stmt->execute([':original'=>$originalId,':supersedes'=>$supersedesId,':new'=>$newId,':reason'=>$reason,':actor'=>$actor]);
        return (int)$this->pdo->lastInsertId();
    }

    public function assertDocumentEncounterContext(int $documentId, int $encounterId, string $doctorId): array
    {
        $stmt=$this->pdo->prepare('SELECT d.id AS document_id,d.patient_id AS document_patient_id,d.status AS document_status,
            d.encounter_ref_id,e.patient_id AS encounter_patient_id,e.doctor_id,e.status AS encounter_status
            FROM clinical_documents d JOIN clinical_encounters e ON e.encounter_id=:encounter_id
            WHERE d.id=:document_id AND e.doctor_id=:doctor_id LIMIT 1');
        $stmt->execute([':document_id'=>$documentId,':encounter_id'=>$encounterId,':doctor_id'=>$doctorId]);
        $row=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!is_array($row))throw new RuntimeException('DOCUMENT_CONTEXT_NOT_FOUND');
        if((string)$row['document_patient_id']!==(string)$row['encounter_patient_id'])throw new RuntimeException('DOCUMENT_CONTEXT_MISMATCH');
        $ref=$row['encounter_ref_id']??null;
        if($ref!==null&&(int)$ref!==$encounterId)throw new RuntimeException('DOCUMENT_CONTEXT_MISMATCH');
        return $row;
    }

    private function getForUpdate(int $id): array{$stmt=$this->pdo->prepare('SELECT * FROM clinical_encounters WHERE encounter_id=:id FOR UPDATE');$stmt->execute([':id'=>$id]);$r=$stmt->fetch(PDO::FETCH_ASSOC);if(!is_array($r))throw new RuntimeException('ENCOUNTER_NOT_FOUND');return $r;}
    private function get(int $id): ?array{$stmt=$this->pdo->prepare('SELECT * FROM clinical_encounters WHERE encounter_id=:id');$stmt->execute([':id'=>$id]);$r=$stmt->fetch(PDO::FETCH_ASSOC);return is_array($r)?$r:null;}
    private function finalDocument(int $encounterId): ?array{$stmt=$this->pdo->prepare('SELECT d.id AS document_id,d.document_uuid FROM clinical_encounter_final_notes f JOIN clinical_documents d ON d.id=f.document_id WHERE f.encounter_id=:id');$stmt->execute([':id'=>$encounterId]);$r=$stmt->fetch(PDO::FETCH_ASSOC);return is_array($r)?$r:null;}
}

/**
 * Default-off service boundary for the V1 repository foundation.
 * Every entry point checks the feature gate and schema manifest before issuing
 * repository SQL. The constructor itself is side-effect free.
 */
final class ClinicalEncounterIntegrityService
{
    private ClinicalEncounterIntegrityRepository $encounters;
    private ClinicalEncounterSectionsRepository $sections;
    private ClinicalObservationsRepository $observations;

    public function __construct(private PDO $pdo)
    {
        $this->encounters = new ClinicalEncounterIntegrityRepository($pdo);
        $this->sections = new ClinicalEncounterSectionsRepository($pdo);
        $this->observations = new ClinicalObservationsRepository($pdo);
    }

    private function assertAvailable(): void
    {
        if (!clinical_encounter_integrity_v1_enabled()) {
            throw new LogicException('ENCOUNTER_INTEGRITY_V1_DISABLED');
        }
        clinical_encounter_integrity_assert_schema_ready($this->pdo);
    }

    public function active(string $doctorId, string $patientId): ?array
    {
        $this->assertAvailable();
        return $this->encounters->findOpen($doctorId, $patientId);
    }

    public function start(string $doctorId, string $actorId, string $patientId, array $command, string $idempotencyKey): array
    {
        $this->assertAvailable();
        if (array_key_exists('status', $command)) {
            throw new InvalidArgumentException('CLIENT_STATUS_FORBIDDEN');
        }
        $encounterDt = trim((string)($command['encounter_dt'] ?? ''));
        if ($encounterDt !== '' && preg_match('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}$/', $encounterDt) !== 1) {
            throw new InvalidArgumentException('ENCOUNTER_DATETIME_INVALID');
        }
        $semantic = clinical_encounter_start_semantic_request($doctorId, $patientId, $command);
        try {
            return $this->encounters->start($doctorId, $actorId, $patientId, $command, $idempotencyKey);
        } catch (PDOException $e) {
            if ((string)$e->getCode() !== '23000') {
                throw $e;
            }
            try {
                return $this->encounters->replayStart($doctorId, $idempotencyKey, $semantic);
            } catch (ClinicalIdempotencyException $replayError) {
                if ($replayError->errorCode !== 'IDEMPOTENCY_RESULT_NOT_READY') {
                    throw $replayError;
                }
                return $this->recoverCanonicalOpen($doctorId, $actorId, $patientId, $semantic, $idempotencyKey);
            }
        }
    }

    private function recoverCanonicalOpen(string $doctorId, string $actorId, string $patientId, array $semantic, string $key): array
    {
        $this->pdo->beginTransaction();
        try {
            $claim = $this->pdo->prepare('INSERT INTO clinical_encounter_start_requests
                (doctor_id,idempotency_key,canonicalization_version,request_hash,actor_user_id,patient_id,created_at)
                VALUES (:doctor,:key,1,:hash,:actor,:patient,UTC_TIMESTAMP())');
            $claim->execute([
                ':doctor' => $doctorId,
                ':key' => clinical_idempotency_key_validate($key),
                ':hash' => clinical_idempotency_request_hash($semantic),
                ':actor' => $actorId,
                ':patient' => $patientId,
            ]);
            $requestId = (int)$this->pdo->lastInsertId();
            $open = $this->encounters->findOpen($doctorId, $patientId, true);
            if ($open === null) {
                throw new RuntimeException('CANONICAL_OPEN_NOT_FOUND_AFTER_CONFLICT');
            }
            $done = $this->pdo->prepare('UPDATE clinical_encounter_start_requests
                SET encounter_id=:encounter_id, committed_at=UTC_TIMESTAMP() WHERE request_id=:request_id');
            $done->execute([':encounter_id' => $open['encounter_id'], ':request_id' => $requestId]);
            $this->pdo->commit();
            return $open + ['_integrity_start_created' => false];
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ((string)$e->getCode() === '23000') {
                return $this->encounters->replayStart($doctorId, $key, $semantic);
            }
            throw $e;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function finalize(int $encounterId, string $actorId, callable $finalNoteFactory): array
    {
        $this->assertAvailable();
        return $this->encounters->finalizeWithNoteFactory($encounterId, $actorId, $finalNoteFactory);
    }

    public function void(int $encounterId, string $actorId, string $reason): array
    {
        $this->assertAvailable();
        return $this->encounters->void($encounterId, $actorId, $reason);
    }

    public function saveSection(int $encounterId, string $type, int $schemaVersion, array $payload, string $narrative, string $actorId, ?int $expectedVersion): array
    {
        $this->assertAvailable();
        return $this->sections->saveOpen($encounterId, $type, $schemaVersion, $payload, $narrative, $actorId, $expectedVersion);
    }

    public function createObservation(int $encounterId, array $payload, string $actorId, string $doctorId, string $idempotencyKey): array
    {
        $this->assertAvailable();
        $validated=clinical_observation_validate($payload);
        return (new ClinicalIdempotentCreateExecutor($this->pdo))->execute(
            'CREATE_OBSERVATION',$doctorId,'ENCOUNTER',(string)$encounterId,$idempotencyKey,
            ['encounter_id'=>$encounterId,'observation'=>$validated],
            'observation_id',$actorId,
            fn():int=>$this->observations->createOpenInTransaction($encounterId,$validated,$actorId),
            fn(int $id):array=>$this->observations->fetch($id)
        );
    }

    public function updateObservation(int $encounterId, int $observationId, array $payload, int $expectedVersion, string $actorId): array
    {
        $this->assertAvailable();
        return $this->observations->updateOpen($encounterId, $observationId, $payload, $expectedVersion, $actorId);
    }

    public function appendAmendment(int $encounterId, array $target, string $reason, string $actorId, array $correction, string $doctorId, string $idempotencyKey): array
    {
        $this->assertAvailable();
        return (new ClinicalIdempotentCreateExecutor($this->pdo))->execute(
            'CREATE_ENCOUNTER_AMENDMENT',$doctorId,'ENCOUNTER',(string)$encounterId,$idempotencyKey,
            ['encounter_id'=>$encounterId,'target'=>$target,'reason'=>$reason,'correction'=>$correction],
            'encounter_amendment_id',$actorId,
            fn():int=>$this->encounters->appendEncounterAmendmentInTransaction($encounterId,$target,$reason,$actorId,$correction),
            fn(int $id):array=>$this->encounters->fetchAmendment($id)
        );
    }

    public function documentPolicy(string $operation, string $documentClass, string $encounterStatus, array $context = []): array
    {
        $this->assertAvailable();
        return clinical_document_operation_policy($operation, $documentClass, $encounterStatus, $context);
    }

    public function assertDocumentContext(int $documentId, int $encounterId, string $doctorId): array
    {
        $this->assertAvailable();
        return $this->encounters->assertDocumentEncounterContext($documentId, $encounterId, $doctorId);
    }

    public function idempotentCreate(
        string $operationType,string $doctorId,string $contextType,string $contextId,string $idempotencyKey,
        array $semanticRequest,string $resultColumn,string $actorId,callable $createResource,callable $fetchResource
    ): array {
        $this->assertAvailable();
        return (new ClinicalIdempotentCreateExecutor($this->pdo))->execute(
            $operationType,$doctorId,$contextType,$contextId,$idempotencyKey,$semanticRequest,
            $resultColumn,$actorId,$createResource,$fetchResource
        );
    }
}
