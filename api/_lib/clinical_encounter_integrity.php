<?php
declare(strict_types=1);

require_once __DIR__ . '/clinical_idempotency.php';
require_once __DIR__ . '/clinical_encounter_sections.php';
require_once __DIR__ . '/clinical_observations.php';

function clinical_encounter_integrity_v1_enabled(): bool
{
    $value=strtolower(trim((string)(getenv('MXMED_CLINICAL_ENCOUNTER_INTEGRITY_V1') ?: '')));
    return in_array($value, ['1','true','yes','on'], true);
}

function clinical_encounter_transition_allowed(string $from, string $to): bool
{
    return strtolower($from) === 'open' && in_array(strtolower($to), ['closed','voided'], true);
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

function clinical_document_operation_policy(string $operation, string $documentClass, string $encounterStatus, array $context=[]): array
{
    $operation=strtoupper(trim($operation)); $class=strtoupper(trim($documentClass)); $status=strtolower(trim($encounterStatus));
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
      'clinical_encounter_start_requests','clinical_idempotency_requests','clinical_encounter_final_notes','clinical_document_revisions','clinical_documents'];
}

function clinical_encounter_integrity_assert_schema_ready(PDO $pdo): void
{
    $required=clinical_encounter_integrity_required_schema();
    $marks=implode(',',array_fill(0,count($required),'?'));
    $stmt=$pdo->prepare("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ($marks)");
    $stmt->execute($required); $found=$stmt->fetchAll(PDO::FETCH_COLUMN); $missing=array_values(array_diff($required,is_array($found)?$found:[]));
    $columns=[
        ['clinical_encounters','open_guard'],
        ['clinical_encounters','voided_at'],
        ['clinical_encounters','voided_by_user_id'],
        ['clinical_encounters','void_reason'],
        ['clinical_encounter_sections','payload_schema_version'],
        ['clinical_encounter_sections','row_version'],
        ['clinical_observations','row_version'],
        ['clinical_documents','encounter_ref_id'],
    ];
    foreach($columns as [$table,$column]){
        $q=$pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
        $q->execute([$table,$column]); if((int)$q->fetchColumn()!==1)$missing[]=$table.'.'.$column;
    }
    $index=$pdo->prepare("SELECT COUNT(*) FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clinical_encounters'
          AND INDEX_NAME='uq_clinical_encounter_one_open' AND NON_UNIQUE=0");
    $index->execute();
    if ((int)$index->fetchColumn() < 1) $missing[]='clinical_encounters.uq_clinical_encounter_one_open';
    if($missing!==[]) throw new RuntimeException('SCHEMA_NOT_READY: '.implode(',',$missing));
}

final class ClinicalEncounterIntegrityRepository
{
    public function __construct(private PDO $pdo) {}

    public function findOpen(string $doctorId,string $patientId,bool $forUpdate=false): ?array
    {
        $sql="SELECT * FROM clinical_encounters WHERE doctor_id=:doctor AND patient_id=:patient AND status='open' ORDER BY encounter_id LIMIT 1".($forUpdate?' FOR UPDATE':'');
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
            $row = $this->getForUpdate($encounterId);
            $status = strtolower((string)$row['status']);
            if ($status === 'closed') {
                $this->pdo->commit();
                return $row;
            }
            if ($status !== 'open') {
                throw new RuntimeException('ENCOUNTER_VOIDED');
            }
            $finalDocumentId = (int)$finalNoteFactory($this->pdo, $row);
            if ($finalDocumentId <= 0) {
                throw new RuntimeException('FINAL_NOTE_CREATION_FAILED');
            }
            $relation = $this->pdo->prepare('INSERT INTO clinical_encounter_final_notes
                (encounter_id, document_id, created_at) VALUES (:encounter_id, :document_id, UTC_TIMESTAMP())');
            $relation->execute([':encounter_id' => $encounterId, ':document_id' => $finalDocumentId]);
            $update = $this->pdo->prepare("UPDATE clinical_encounters
                SET status='closed', closed_at=UTC_TIMESTAMP(), closed_by_user_id=:actor, updated_at=UTC_TIMESTAMP()
                WHERE encounter_id=:encounter_id AND status='open'");
            $update->execute([':actor' => $actorId, ':encounter_id' => $encounterId]);
            if ($update->rowCount() !== 1) {
                throw new RuntimeException('ENCOUNTER_TERMINAL');
            }
            $closed = $this->getForUpdate($encounterId);
            $this->pdo->commit();
            return $closed;
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
        $this->pdo->beginTransaction();try{$row=$this->getForUpdate($encounterId);$status=(string)$row['status'];
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

    public function createDocumentRevision(int $originalId,int $newId,string $reason,string $actor,?int $supersedesId=null): int
    {
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

    public function createObservation(int $encounterId, array $payload, string $actorId): array
    {
        $this->assertAvailable();
        return $this->observations->createOpen($encounterId, $payload, $actorId);
    }

    public function updateObservation(int $encounterId, int $observationId, array $payload, int $expectedVersion, string $actorId): array
    {
        $this->assertAvailable();
        return $this->observations->updateOpen($encounterId, $observationId, $payload, $expectedVersion, $actorId);
    }

    public function appendAmendment(int $encounterId, array $target, string $reason, string $actorId, array $correction): int
    {
        $this->assertAvailable();
        return $this->encounters->appendEncounterAmendment($encounterId, $target, $reason, $actorId, $correction);
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
}
