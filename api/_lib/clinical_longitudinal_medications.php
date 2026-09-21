<?php
declare(strict_types=1);

require_once __DIR__.'/clinical_longitudinal_antecedents.php';

/** LON05A: patient-scoped medication episodes. No prescription writer invokes this service. */
final class ClinicalLongitudinalMedications {
    private const CURRENT = ['ACTIVE_CONFIRMED','REPORTED_BY_PATIENT','PRESCRIBED_NOT_CONFIRMED_ACTIVE'];
    private const REGIMEN = ['medication_name','code_system','code_value','dose','dose_unit','route','frequency','started_at'];
    public function __construct(private PDO $pdo) {}
    private static function fail(string $code,int $status=400): never {throw new ClinicalLongitudinalException($code,$status);}
    private static function json(array $value): string {return json_encode($value,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}
    private static function canonical(mixed $value): mixed {
        if (!is_array($value)) return $value;
        if (!array_is_list($value)) ksort($value,SORT_STRING);
        foreach ($value as $key=>$part) $value[$key]=self::canonical($part);
        return $value;
    }
    private static function only(array $body,array $allowed): void {foreach($body as $key=>$_) if(!in_array($key,$allowed,true)) self::fail('UNKNOWN_FIELD');}
    private static function value(array $body,string $key,int $limit,bool $required=false): ?string {
        $raw=$body[$key]??null;
        if ($raw===null && !$required) return null;
        if (!is_string($raw)) self::fail('INVALID_'.strtoupper($key));
        $v=trim($raw);
        if (($required && $v==='') || strlen($v)>$limit) self::fail('INVALID_'.strtoupper($key));
        return $v===''?null:$v;
    }
    private static function version(array $body,string $field): int {
        $v=$body[$field]??null;
        if (!is_int($v) || $v<1) self::fail('EXPECTED_VERSION_REQUIRED');
        return $v;
    }
    private static function date(?string $value,string $field): ?string {
        if ($value===null) return null;
        $parsed=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$value,new DateTimeZone('UTC'));
        if (!$parsed || $parsed->format('Y-m-d H:i:s')!==$value) self::fail('INVALID_'.strtoupper($field));
        return $value;
    }
    private static function regimen(array $body): array {
        $v=[
            'medication_name'=>self::value($body,'medication_name',500,true),
            'code_system'=>self::value($body,'code_system',64),
            'code_value'=>self::value($body,'code_value',128),
            'dose'=>self::value($body,'dose',128),
            'dose_unit'=>self::value($body,'dose_unit',64),
            'route'=>self::value($body,'route',128),
            'frequency'=>self::value($body,'frequency',255),
            'started_at'=>self::date(self::value($body,'started_at',19),'started_at'),
        ];
        if (($v['code_system']===null)!==($v['code_value']===null)) self::fail('INVALID_CODE');
        return $v;
    }
    private function scope(string $doctor,string $patient,bool $lock=false): void {
        if ($doctor==='' || $patient==='') self::fail('NOT_FOUND',404);
        $s=$this->pdo->prepare("SELECT link_id FROM patients_doctor_links WHERE doctor_id=? AND patient_id=? AND status='active'".($lock?' FOR UPDATE':''));
        $s->execute([$doctor,$patient]);if(!$s->fetchColumn()) self::fail('NOT_FOUND',404);
    }
    private function listVersion(string $doctor,string $patient,bool $lock): int {
        if ($lock) {
            $s=$this->pdo->prepare('INSERT IGNORE INTO clinical_patient_medication_lists (doctor_id,patient_id,list_version,updated_at) VALUES (?,?,1,UTC_TIMESTAMP())');
            $s->execute([$doctor,$patient]);
        }
        $s=$this->pdo->prepare('SELECT list_version FROM clinical_patient_medication_lists WHERE doctor_id=? AND patient_id=?'.($lock?' FOR UPDATE':''));
        $s->execute([$doctor,$patient]);return (int)($s->fetchColumn()?:1);
    }
    private function advanceList(string $doctor,string $patient,int $expected): void {
        $s=$this->pdo->prepare('UPDATE clinical_patient_medication_lists SET list_version=list_version+1,updated_at=UTC_TIMESTAMP() WHERE doctor_id=? AND patient_id=? AND list_version=?');
        $s->execute([$doctor,$patient,$expected]);if($s->rowCount()!==1) self::fail('STALE_LIST_VERSION',409);
    }
    private function row(string $doctor,string $patient,int $id,bool $lock=false): array {
        $s=$this->pdo->prepare('SELECT * FROM clinical_patient_medications WHERE medication_id=? AND doctor_id=? AND patient_id=?'.($lock?' FOR UPDATE':''));
        $s->execute([$id,$doctor,$patient]);$row=$s->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) self::fail('NOT_FOUND',404);
        return $row;
    }
    private function source(string $doctor,string $patient,array $body,string $operation): array {
        $encounter=$body['source_encounter_id']??null;$document=$body['source_document_id']??null;
        if ($operation==='PRESCRIPTION_DERIVED_CREATE' && (!is_int($document)||$document<1)) self::fail('SOURCE_REQUIRED');
        if ($operation!=='PRESCRIPTION_DERIVED_CREATE' && $document!==null) self::fail('INVALID_SOURCE');
        if ($encounter!==null && (!is_int($encounter)||$encounter<1)) self::fail('INVALID_SOURCE');
        if ($document!==null) {
            $s=$this->pdo->prepare("SELECT d.encounter_ref_id FROM clinical_documents d JOIN clinical_encounters e ON e.encounter_id=d.encounter_ref_id WHERE d.id=? AND d.document_type IN ('prescription','receta') AND d.status IN ('generated','signed') AND d.patient_id=? AND e.doctor_id=? AND e.patient_id=?");
            $s->execute([$document,$patient,$doctor,$patient]);$source=$s->fetchColumn();
            if (!$source || ($encounter!==null && (int)$source!==$encounter)) self::fail('FOREIGN_SOURCE',409);
            $encounter=(int)$source;
        } elseif ($encounter!==null) {
            $s=$this->pdo->prepare('SELECT 1 FROM clinical_encounters WHERE encounter_id=? AND doctor_id=? AND patient_id=?');
            $s->execute([$encounter,$doctor,$patient]);if(!$s->fetchColumn()) self::fail('FOREIGN_SOURCE',409);
        }
        return [$encounter,$document];
    }
    private function insert(string $doctor,string $patient,string $actor,string $operation,array $body,string $now): array {
        $regimen=self::regimen($body);
        [$encounter,$document]=$this->source($doctor,$patient,$body,$operation);
        $state=match($operation) {
            'PATIENT_REPORTED_CREATE'=>'REPORTED_BY_PATIENT',
            'PRESCRIPTION_DERIVED_CREATE'=>self::value($body,'initial_state',40,true),
            default=>'ACTIVE_CONFIRMED'
        };
        if ($operation==='PRESCRIPTION_DERIVED_CREATE' && !in_array($state,['PRESCRIBED_NOT_CONFIRMED_ACTIVE','ACTIVE_CONFIRMED'],true)) self::fail('INVALID_INITIAL_STATE');
        $provenance=match($operation) {'PATIENT_REPORTED_CREATE'=>'PATIENT_REPORTED','PRESCRIPTION_DERIVED_CREATE'=>'PRESCRIPTION_DERIVED_EXPLICIT_ENTRY',default=>$encounter!==null?'ENCOUNTER_DERIVED_EXPLICIT_ENTRY':'EXPLICIT_LONGITUDINAL_ENTRY'};
        $s=$this->pdo->prepare('INSERT INTO clinical_patient_medications (doctor_id,patient_id,medication_name,code_system,code_value,dose,dose_unit,route,frequency,started_at,state,provenance,source_encounter_id,source_document_id,created_by,updated_by,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $s->execute([$doctor,$patient,...array_values($regimen),$state,$provenance,$encounter,$document,$actor,$actor,$now,$now]);
        return $this->row($doctor,$patient,(int)$this->pdo->lastInsertId());
    }
    private function change(string $doctor,string $patient,string $actor,string $operation,array $body,int $id,string $now): array {
        $before=$this->row($doctor,$patient,$id,true);
        $expected=self::version($body,'expected_version');
        if ((int)$before['row_version']!==$expected) self::fail('STALE_VERSION',409);
        if (!in_array($before['state'],self::CURRENT,true)) self::fail('TERMINAL_EPISODE',409);
        if ($operation==='UPDATE_REGIMEN') {
            $regimen=self::regimen($body);
            $s=$this->pdo->prepare('UPDATE clinical_patient_medications SET medication_name=?,code_system=?,code_value=?,dose=?,dose_unit=?,route=?,frequency=?,started_at=?,updated_by=?,updated_at=?,row_version=row_version+1 WHERE medication_id=? AND doctor_id=? AND patient_id=? AND row_version=?');
            $s->execute([...array_values($regimen),$actor,$now,$id,$doctor,$patient,$expected]);
        } else {
            $state=match($operation) {'CONFIRM_ACTIVE'=>'ACTIVE_CONFIRMED','DISCONTINUE'=>'DISCONTINUED','COMPLETE'=>'COMPLETED',default=>self::fail('INVALID_COMMAND')};
            $allowed=match($operation) {
                'CONFIRM_ACTIVE'=>in_array($before['state'],['REPORTED_BY_PATIENT','PRESCRIBED_NOT_CONFIRMED_ACTIVE'],true),
                'DISCONTINUE'=>in_array($before['state'],self::CURRENT,true),
                'COMPLETE'=>in_array($before['state'],['ACTIVE_CONFIRMED','PRESCRIBED_NOT_CONFIRMED_ACTIVE'],true),
                default=>false
            };
            if (!$allowed) self::fail('INVALID_TRANSITION',409);
            $terminal=in_array($state,['DISCONTINUED','COMPLETED'],true);
            $s=$this->pdo->prepare('UPDATE clinical_patient_medications SET state=?,ended_at=?,ended_by=?,updated_by=?,updated_at=?,row_version=row_version+1 WHERE medication_id=? AND doctor_id=? AND patient_id=? AND row_version=?');
            $s->execute([$state,$terminal?$now:null,$terminal?$actor:null,$actor,$now,$id,$doctor,$patient,$expected]);
        }
        if($s->rowCount()!==1) self::fail('STALE_VERSION',409);
        return [$before,$this->row($doctor,$patient,$id)];
    }
    private function audit(string $doctor,string $patient,string $actor,string $operation,?array $before,array $after,?string $reason,?int $reconciliationId=null): void {
        $s=$this->pdo->prepare('INSERT INTO clinical_patient_medication_audit_events (doctor_id,patient_id,medication_id,entity_version,operation,actor_user_id,reconciliation_id,reason,before_json,after_json,occurred_at) VALUES (?,?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP())');
        $s->execute([$doctor,$patient,(int)$after['medication_id'],(int)$after['row_version'],$operation,$actor,$reconciliationId,$reason,$before===null?null:self::json($before),self::json($after)]);
    }
    private function receipt(string $doctor,string $patient,string $operation,string $key,string $hash,?array $result=null): ?array {
        if($result===null) {
            $s=$this->pdo->prepare('SELECT request_hash,response_json FROM clinical_longitudinal_idempotency WHERE doctor_id=? AND patient_id=? AND operation=? AND idempotency_key=?');
            $s->execute([$doctor,$patient,$operation,$key]);$old=$s->fetch(PDO::FETCH_ASSOC);
            if(!is_array($old)) return null;
            if(!hash_equals($old['request_hash'],$hash)) self::fail('IDEMPOTENCY_PAYLOAD_CONFLICT',409);
            return json_decode($old['response_json'],true,512,JSON_THROW_ON_ERROR);
        }
        $s=$this->pdo->prepare('INSERT INTO clinical_longitudinal_idempotency (doctor_id,patient_id,operation,idempotency_key,request_hash,response_json,created_at) VALUES (?,?,?,?,?,?,UTC_TIMESTAMP())');
        $s->execute([$doctor,$patient,$operation,$key,$hash,self::json($result)]);return null;
    }
    private static function command(string $doctor,string $patient,string $actor,string $key): void {
        if(getenv('MXMED_LON05A_WRITE_ENABLED')!=='1') self::fail('LON05A_WRITE_DISABLED',503);
        if(clinical_m6_write_window_blocks_writes()) self::fail('M6_WRITE_WINDOW_BLOCKED',503);
        if($doctor===''||$patient===''||$actor===''||$key===''||strlen($key)>128||strlen($doctor)>64||strlen($patient)>64) self::fail('INVALID_COMMAND');
    }
    public function read(string $doctor,string $patient,?int $id=null,string $resource='medications',bool $history=false): array {
        $this->scope($doctor,$patient);
        if($resource==='medication-reconciliations') {
            if($id!==null) {
                $s=$this->pdo->prepare('SELECT * FROM clinical_medication_reconciliations WHERE reconciliation_id=? AND doctor_id=? AND patient_id=?');
                $s->execute([$id,$doctor,$patient]);$row=$s->fetch(PDO::FETCH_ASSOC);if(!is_array($row)) self::fail('NOT_FOUND',404);
                $s=$this->pdo->prepare('SELECT * FROM clinical_medication_reconciliation_items WHERE reconciliation_id=? ORDER BY item_id');$s->execute([$id]);
                return ['reconciliation'=>$row,'items'=>$s->fetchAll(PDO::FETCH_ASSOC)];
            }
            $s=$this->pdo->prepare('SELECT * FROM clinical_medication_reconciliations WHERE doctor_id=? AND patient_id=? ORDER BY reconciliation_id DESC');$s->execute([$doctor,$patient]);
            return ['reconciliations'=>$s->fetchAll(PDO::FETCH_ASSOC),'list_version'=>$this->listVersion($doctor,$patient,false)];
        }
        if($history) {
            $sql='SELECT * FROM clinical_patient_medication_audit_events WHERE doctor_id=? AND patient_id=?';$args=[$doctor,$patient];
            if($id!==null) {$this->row($doctor,$patient,$id);$sql.=' AND medication_id=?';$args[]=$id;}
            $s=$this->pdo->prepare($sql.' ORDER BY event_id');$s->execute($args);return ['events'=>$s->fetchAll(PDO::FETCH_ASSOC)];
        }
        if($id!==null) return ['item'=>$this->row($doctor,$patient,$id)];
        $s=$this->pdo->prepare('SELECT * FROM clinical_patient_medications WHERE doctor_id=? AND patient_id=? ORDER BY medication_id');$s->execute([$doctor,$patient]);
        return ['items'=>$s->fetchAll(PDO::FETCH_ASSOC),'list_version'=>$this->listVersion($doctor,$patient,false),'knowledge_state'=>'UNREVIEWED'];
    }
    public function mutate(string $doctor,string $patient,string $actor,string $operation,array $body,string $key,?int $id=null): array {
        self::command($doctor,$patient,$actor,$key);
        $create=in_array($operation,['CREATE','PATIENT_REPORTED_CREATE','PRESCRIPTION_DERIVED_CREATE'],true);
        if(!in_array($operation,['CREATE','PATIENT_REPORTED_CREATE','PRESCRIPTION_DERIVED_CREATE','UPDATE_REGIMEN','CONFIRM_ACTIVE','DISCONTINUE','COMPLETE'],true)||$create!==($id===null)) self::fail('INVALID_COMMAND');
        $allowed=$create?[...self::REGIMEN,'source_encounter_id','source_document_id','initial_state']:($operation==='UPDATE_REGIMEN'?[...self::REGIMEN,'expected_version','reason']:['expected_version','reason']);
        self::only($body,$allowed);
        if($operation!=='PRESCRIPTION_DERIVED_CREATE' && array_key_exists('initial_state',$body)) self::fail('UNKNOWN_FIELD');
        $hash=hash('sha256',self::json(self::canonical(['v'=>1,'operation'=>$operation,'id'=>$id,'body'=>$body])));
        $receiptOperation='medications_'.$operation;
        $this->pdo->beginTransaction();
        try {
            $this->scope($doctor,$patient,true);$listVersion=$this->listVersion($doctor,$patient,true);
            $prior=$this->receipt($doctor,$patient,$receiptOperation,$key,$hash);if($prior!==null){$this->pdo->commit();return $prior;}
            $now=gmdate('Y-m-d H:i:s');$reason=$create?null:self::value($body,'reason',255,true);
            if($create){$before=null;$after=$this->insert($doctor,$patient,$actor,$operation,$body,$now);}
            else [$before,$after]=$this->change($doctor,$patient,$actor,$operation,$body,$id,$now);
            $this->audit($doctor,$patient,$actor,$operation,$before,$after,$reason);
            $this->advanceList($doctor,$patient,$listVersion);
            $result=['item'=>$after,'list_version'=>$listVersion+1];$this->receipt($doctor,$patient,$receiptOperation,$key,$hash,$result);
            $this->pdo->commit();return $result;
        } catch(Throwable $e) {if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }
    public function reconcile(string $doctor,string $patient,string $actor,array $body,string $key): array {
        self::command($doctor,$patient,$actor,$key);
        self::only($body,['expected_list_version','note','decisions']);
        $expected=self::version($body,'expected_list_version');$note=self::value($body,'note',1000);
        $decisions=$body['decisions']??null;
        if(!is_array($decisions)||!array_is_list($decisions)||$decisions===[]||count($decisions)>100)self::fail('INVALID_DECISIONS');
        $hash=hash('sha256',self::json(self::canonical(['v'=>1,'body'=>$body])));
        $this->pdo->beginTransaction();
        try {
            $this->scope($doctor,$patient,true);$version=$this->listVersion($doctor,$patient,true);
            $prior=$this->receipt($doctor,$patient,'medication_reconciliation',$key,$hash);if($prior!==null){$this->pdo->commit();return $prior;}
            if($version!==$expected)self::fail('STALE_LIST_VERSION',409);
            $now=gmdate('Y-m-d H:i:s');
            $s=$this->pdo->prepare('INSERT INTO clinical_medication_reconciliations (doctor_id,patient_id,actor_user_id,starting_list_version,resulting_list_version,note,created_at) VALUES (?,?,?,?,?,?,?)');
            $s->execute([$doctor,$patient,$actor,$version,$version+1,$note,$now]);$reconciliationId=(int)$this->pdo->lastInsertId();
            $seen=[];$results=[];
            foreach($decisions as $decision) {
                if(!is_array($decision)||array_is_list($decision))self::fail('INVALID_DECISION');
                $action=self::value($decision,'action',32,true);
                if(!in_array($action,['ADD','CONFIRM_ACTIVE','UPDATE_REGIMEN','DISCONTINUE','COMPLETE','KEEP_UNCONFIRMED','UNRESOLVED'],true))self::fail('INVALID_DECISION');
                $allowed=match($action) {
                    'ADD'=>['action','reason','source_encounter_id',...self::REGIMEN],
                    'UPDATE_REGIMEN'=>['action','medication_id','expected_version','reason',...self::REGIMEN],
                    default=>['action','medication_id','expected_version','reason']
                };
                self::only($decision,$allowed);
                $id=$decision['medication_id']??null;$before=null;$after=null;$reason=self::value($decision,'reason',255);
                if($action==='ADD') {
                    if($id!==null)self::fail('INVALID_DECISION');
                    $after=$this->insert($doctor,$patient,$actor,'CREATE',$decision,$now);$id=(int)$after['medication_id'];
                } else {
                    if(!is_int($id)||$id<1||isset($seen[$id]))self::fail('INVALID_DECISION');
                    $seen[$id]=true;$before=$this->row($doctor,$patient,$id,true);
                    if((int)$before['row_version']!==self::version($decision,'expected_version'))self::fail('STALE_VERSION',409);
                    if(in_array($action,['KEEP_UNCONFIRMED','UNRESOLVED'],true)) {
                        if(!in_array($before['state'],['REPORTED_BY_PATIENT','PRESCRIBED_NOT_CONFIRMED_ACTIVE'],true))self::fail('INVALID_DECISION');
                    } else {
                        if($reason===null)self::fail('REASON_REQUIRED');
                        [$before,$after]=$this->change($doctor,$patient,$actor,$action,$decision,$id,$now);
                    }
                }
                $s=$this->pdo->prepare('INSERT INTO clinical_medication_reconciliation_items (reconciliation_id,medication_id,decision,before_json,after_json,reason) VALUES (?,?,?,?,?,?)');
                $s->execute([$reconciliationId,$id,$action,$before===null?null:self::json($before),$after===null?null:self::json($after),$reason]);
                if($after!==null)$this->audit($doctor,$patient,$actor,'RECONCILIATION_DECISION',$before,$after,$action.($reason!==null?': '.$reason:''),$reconciliationId);
                $results[]=['medication_id'=>$id,'decision'=>$action,'row_version'=>(int)($after['row_version']??$before['row_version'])];
            }
            $this->advanceList($doctor,$patient,$version);
            $result=['reconciliation_id'=>$reconciliationId,'starting_list_version'=>$version,'list_version'=>$version+1,'decisions'=>$results];
            $this->receipt($doctor,$patient,'medication_reconciliation',$key,$hash,$result);
            $this->pdo->commit();return $result;
        } catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }
}
