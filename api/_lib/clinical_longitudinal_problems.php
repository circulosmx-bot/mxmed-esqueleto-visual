<?php
declare(strict_types=1);

require_once __DIR__.'/clinical_longitudinal_antecedents.php';

/** LON04A patient-level authority. No encounter writer invokes this service. */
final class ClinicalLongitudinalProblems {
    public function __construct(private PDO $pdo) {}
    private static function fail(string $code, int $status=400): never { throw new ClinicalLongitudinalException($code,$status); }
    private static function value(array $body,string $key,int $limit,bool $required=true): ?string {
        $raw=$body[$key]??null;
        if ($raw===null && !$required) return null;
        if (!is_string($raw)) self::fail('INVALID_'.strtoupper($key));
        $value=trim($raw);
        if (($required && $value==='') || strlen($value)>$limit) self::fail('INVALID_'.strtoupper($key));
        return $value===''?null:$value;
    }
    private static function only(array $body,array $keys): void {
        foreach ($body as $key=>$_) if (!in_array($key,$keys,true)) self::fail('UNKNOWN_FIELD');
    }
    private static function expected(array $body): int {
        $v=$body['expected_version']??null;
        if (!is_int($v) || $v<1) self::fail('EXPECTED_VERSION_REQUIRED');
        return $v;
    }
    private static function json(array $data): string {return json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}
    private static function canonical(mixed $value): mixed {
        if (!is_array($value)) return $value;
        if (!array_is_list($value)) ksort($value,SORT_STRING);
        foreach ($value as $key=>$part) $value[$key]=self::canonical($part);
        return $value;
    }
    private function scope(string $doctor,string $patient,bool $lock=false): void {
        if ($doctor==='' || $patient==='') self::fail('NOT_FOUND',404);
        $s=$this->pdo->prepare("SELECT link_id FROM patients_doctor_links WHERE doctor_id=? AND patient_id=? AND status='active'".($lock?' FOR UPDATE':''));
        $s->execute([$doctor,$patient]);
        if (!$s->fetchColumn()) self::fail('NOT_FOUND',404);
    }
    private function row(string $doctor,string $patient,int $id,bool $lock=false): array {
        $s=$this->pdo->prepare('SELECT * FROM clinical_patient_problems WHERE problem_id=? AND doctor_id=? AND patient_id=?'.($lock?' FOR UPDATE':''));
        $s->execute([$id,$doctor,$patient]);$row=$s->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) self::fail('NOT_FOUND',404);
        return $row;
    }
    private function source(array $body,string $doctor,string $patient,bool $required): array {
        $encounter=$body['source_encounter_id']??null;
        $section=$body['source_section_id']??null;
        if ($encounter===null && $section===null && !$required) return [null,null];
        if (!is_int($encounter) || $encounter<1 || !is_int($section) || $section<1) self::fail('SOURCE_REQUIRED');
        $s=$this->pdo->prepare("SELECT 1 FROM clinical_encounter_sections s JOIN clinical_encounters e ON e.encounter_id=s.encounter_id WHERE s.section_id=? AND s.encounter_id=? AND s.section_type='assessment' AND s.narrative_text IS NOT NULL AND TRIM(s.narrative_text)<>'' AND e.doctor_id=? AND e.patient_id=?");
        $s->execute([$section,$encounter,$doctor,$patient]);
        if (!$s->fetchColumn()) self::fail('FOREIGN_SOURCE',409);
        return [$encounter,$section];
    }
    private static function identity(array $body): array {
        $codeSystem=self::value($body,'code_system',64,false);
        $codeValue=self::value($body,'code_value',128,false);
        if (($codeSystem===null)!==($codeValue===null)) self::fail('INVALID_CODE');
        $onset=self::value($body,'onset_date',10,false);
        if ($onset!==null && (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$onset) || !checkdate((int)substr($onset,5,2),(int)substr($onset,8,2),(int)substr($onset,0,4)))) self::fail('INVALID_ONSET_DATE');
        return ['label'=>self::value($body,'label',500),'code_system'=>$codeSystem,'code_value'=>$codeValue,'onset_date'=>$onset];
    }
    public function read(string $doctor,string $patient,?int $id=null,bool $history=false): array {
        $this->scope($doctor,$patient);
        if ($history) {
            $sql='SELECT * FROM clinical_patient_problem_audit_events WHERE doctor_id=? AND patient_id=?';$args=[$doctor,$patient];
            if ($id!==null) {$this->row($doctor,$patient,$id);$sql.=' AND problem_id=?';$args[]=$id;}
            $s=$this->pdo->prepare($sql.' ORDER BY event_id ASC');$s->execute($args);
            return ['events'=>$s->fetchAll(PDO::FETCH_ASSOC)];
        }
        if ($id!==null) return ['item'=>$this->row($doctor,$patient,$id)];
        $s=$this->pdo->prepare('SELECT * FROM clinical_patient_problems WHERE doctor_id=? AND patient_id=? ORDER BY problem_id ASC');
        $s->execute([$doctor,$patient]);
        return ['items'=>$s->fetchAll(PDO::FETCH_ASSOC),'knowledge_state'=>'UNREVIEWED'];
    }
    public function mutate(string $doctor,string $patient,string $actor,string $operation,array $body,string $key,?int $id=null): array {
        if (getenv('MXMED_LON04A_WRITE_ENABLED')!=='1') self::fail('LON04A_WRITE_DISABLED',503);
        if (clinical_m6_write_window_blocks_writes()) self::fail('M6_WRITE_WINDOW_BLOCKED',503);
        if ($actor==='' || $key==='' || strlen($key)>128 || strlen($doctor)>64 || strlen($patient)>64) self::fail('INVALID_COMMAND');
        $create=in_array($operation,['CREATE','EXPLICIT_PROMOTION_FROM_ENCOUNTER'],true);
        if (!in_array($operation,['CREATE','EXPLICIT_PROMOTION_FROM_ENCOUNTER','UPDATE','RESOLVE','MARK_INACTIVE','ACTIVATE','REACTIVATE'],true) || $create!==($id===null)) self::fail('INVALID_COMMAND');
        $identityKeys=['label','code_system','code_value','onset_date'];
        $sourceKeys=['source_encounter_id','source_section_id'];
        $allowed=match($operation) {
            'CREATE'=>[...$identityKeys,'provenance'],
            'EXPLICIT_PROMOTION_FROM_ENCOUNTER'=>[...$identityKeys,...$sourceKeys],
            'UPDATE'=>[...$identityKeys,'expected_version','reason'],
            default=>['expected_version','reason',...$sourceKeys]
        };
        self::only($body,$allowed);
        $expected=$create?null:self::expected($body);
        $reason=$create?null:self::value($body,'reason',255);
        $hash=hash('sha256',self::json(self::canonical(['v'=>1,'operation'=>$operation,'id'=>$id,'body'=>$body])));
        $command='problems_'.$operation;
        $this->pdo->beginTransaction();
        try {
            $this->scope($doctor,$patient,true);
            $s=$this->pdo->prepare('SELECT request_hash,response_json FROM clinical_longitudinal_idempotency WHERE doctor_id=? AND patient_id=? AND operation=? AND idempotency_key=?');
            $s->execute([$doctor,$patient,$command,$key]);$prior=$s->fetch(PDO::FETCH_ASSOC);
            if (is_array($prior)) {
                if (!hash_equals($prior['request_hash'],$hash)) self::fail('IDEMPOTENCY_PAYLOAD_CONFLICT',409);
                $result=json_decode($prior['response_json'],true,512,JSON_THROW_ON_ERROR);$this->pdo->commit();return $result;
            }
            $now=gmdate('Y-m-d H:i:s');$before=null;$evidence=[null,null];
            if ($create) {
                $identity=self::identity($body);
                if ($operation==='CREATE') {
                    $provenance=self::value($body,'provenance',48);
                    if (!in_array($provenance,['EXPLICIT_LONGITUDINAL_ENTRY','PATIENT_REPORTED'],true)) self::fail('INVALID_PROVENANCE');
                } else {$provenance='ENCOUNTER_DERIVED_EXPLICIT_PROMOTION';$evidence=$this->source($body,$doctor,$patient,true);}
                $s=$this->pdo->prepare('INSERT INTO clinical_patient_problems (doctor_id,patient_id,label,code_system,code_value,onset_date,status,provenance,source_encounter_id,source_section_id,created_by,updated_by,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
                $s->execute([$doctor,$patient,...array_values($identity),'ACTIVE',$provenance,...$evidence,$actor,$actor,$now,$now]);
                $id=(int)$this->pdo->lastInsertId();
            } else {
                $before=$this->row($doctor,$patient,$id,true);
                if ((int)$before['row_version']!==$expected) self::fail('STALE_VERSION',409);
                $from=$before['status'];$to=$from;
                if ($operation==='UPDATE') {
                    $identity=self::identity($body);
                    $s=$this->pdo->prepare('UPDATE clinical_patient_problems SET label=?,code_system=?,code_value=?,onset_date=?,updated_by=?,updated_at=?,row_version=row_version+1 WHERE problem_id=? AND doctor_id=? AND patient_id=? AND row_version=?');
                    $s->execute([...array_values($identity),$actor,$now,$id,$doctor,$patient,$expected]);
                } else {
                    $to=match($operation) {'RESOLVE'=>'RESOLVED','MARK_INACTIVE'=>'INACTIVE',default=>'ACTIVE'};
                    $allowedTransition=match($operation) {
                        'RESOLVE'=>in_array($from,['ACTIVE','INACTIVE'],true),
                        'MARK_INACTIVE'=>$from==='ACTIVE',
                        'ACTIVATE'=>$from==='INACTIVE',
                        'REACTIVATE'=>$from==='RESOLVED',
                        default=>false
                    };
                    if (!$allowedTransition) self::fail('INVALID_TRANSITION',409);
                    $evidence=$this->source($body,$doctor,$patient,false);
                    $s=$this->pdo->prepare('UPDATE clinical_patient_problems SET status=?,resolution_at=?,resolution_by=?,updated_by=?,updated_at=?,row_version=row_version+1 WHERE problem_id=? AND doctor_id=? AND patient_id=? AND row_version=?');
                    $s->execute([$to,$to==='RESOLVED'?$now:null,$to==='RESOLVED'?$actor:null,$actor,$now,$id,$doctor,$patient,$expected]);
                }
                if ($s->rowCount()!==1) self::fail('STALE_VERSION',409);
            }
            $after=$this->row($doctor,$patient,$id);
            $s=$this->pdo->prepare('INSERT INTO clinical_patient_problem_audit_events (doctor_id,patient_id,problem_id,entity_version,operation,actor_user_id,source_encounter_id,source_section_id,reason,before_json,after_json,occurred_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP())');
            $s->execute([$doctor,$patient,$id,(int)$after['row_version'],$operation,$actor,...$evidence,$reason,$before===null?null:self::json($before),self::json($after)]);
            $result=['item'=>$after];
            $s=$this->pdo->prepare('INSERT INTO clinical_longitudinal_idempotency (doctor_id,patient_id,operation,idempotency_key,request_hash,response_json,created_at) VALUES (?,?,?,?,?,?,UTC_TIMESTAMP())');
            $s->execute([$doctor,$patient,$command,$key,$hash,self::json($result)]);
            $this->pdo->commit();return $result;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }
}
