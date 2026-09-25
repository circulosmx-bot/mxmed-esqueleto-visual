<?php
declare(strict_types=1);

require_once __DIR__.'/clinical_longitudinal_antecedents.php';

/** LON06A: explicit clinical task authority. Agenda and encounters never call this writer. */
final class ClinicalLongitudinalTasks {
    public function __construct(private PDO $pdo) {}
    private static function fail(string $code,int $status=400): never {throw new ClinicalLongitudinalException($code,$status);}
    private static function json(array $value): string {return json_encode($value,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}
    private static function canonical(mixed $value): mixed {
        if(!is_array($value))return $value;
        if(!array_is_list($value))ksort($value,SORT_STRING);
        foreach($value as $key=>$part)$value[$key]=self::canonical($part);
        return $value;
    }
    private static function only(array $body,array $allowed): void {foreach($body as $key=>$_)if(!in_array($key,$allowed,true))self::fail('UNKNOWN_FIELD');}
    private static function value(array $body,string $key,int $limit,bool $required=false): ?string {
        $raw=$body[$key]??null;if($raw===null&&!$required)return null;
        if(!is_string($raw))self::fail('INVALID_'.strtoupper($key));
        $v=trim($raw);if(($required&&$v==='')||mb_strlen($v)>$limit)self::fail('INVALID_'.strtoupper($key));
        return $v===''?null:$v;
    }
    private static function version(array $body): int {
        $v=$body['expected_version']??null;if(!is_int($v)||$v<1)self::fail('EXPECTED_VERSION_REQUIRED');return $v;
    }
    private static function date(?string $value): ?string {
        if($value===null)return null;
        $parsed=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$value,new DateTimeZone('UTC'));
        if(!$parsed||$parsed->format('Y-m-d H:i:s')!==$value)self::fail('INVALID_DUE_AT');
        return $value;
    }
    private function scope(string $doctor,string $patient,bool $lock=false): void {
        if($doctor===''||$patient==='')self::fail('NOT_FOUND',404);
        $s=$this->pdo->prepare("SELECT link_id FROM patients_doctor_links WHERE doctor_id=? AND patient_id=? AND status='active'".($lock?' FOR UPDATE':''));
        $s->execute([$doctor,$patient]);if(!$s->fetchColumn())self::fail('NOT_FOUND',404);
    }
    private function row(string $doctor,string $patient,int $id,bool $lock=false): array {
        $s=$this->pdo->prepare('SELECT * FROM clinical_patient_tasks WHERE task_id=? AND doctor_id=? AND patient_id=?'.($lock?' FOR UPDATE':''));
        $s->execute([$id,$doctor,$patient]);$row=$s->fetch(PDO::FETCH_ASSOC);if(!is_array($row))self::fail('NOT_FOUND',404);return $row;
    }
    private function source(string $doctor,string $patient,mixed $encounter): ?int {
        if($encounter===null)return null;
        if(!is_int($encounter)||$encounter<1)self::fail('INVALID_SOURCE_ENCOUNTER_ID');
        $s=$this->pdo->prepare('SELECT 1 FROM clinical_encounters WHERE encounter_id=? AND doctor_id=? AND patient_id=?');
        $s->execute([$encounter,$doctor,$patient]);if(!$s->fetchColumn())self::fail('FOREIGN_SOURCE',409);
        return $encounter;
    }
    private static function dueState(array $row,?string $now=null): string {
        if($row['state']!=='OPEN')return (string)$row['state'];
        return $row['due_at']!==null && (string)$row['due_at']<=($now??gmdate('Y-m-d H:i:s'))?'OVERDUE':'PENDING';
    }
    private static function project(array $row): array {
        $row['derived_due_state']=self::dueState($row);
        return $row;
    }
    private function appointment(string $doctor,string $patient,mixed $appointment,bool $requireEligible=false): ?string {
        if($appointment===null)return null;
        if(!is_string($appointment)||trim($appointment)===''||mb_strlen(trim($appointment))>64)self::fail('INVALID_APPOINTMENT_ID');
        $id=trim($appointment);
        $s=$this->pdo->prepare('SELECT status,start_at FROM agenda_appointments WHERE appointment_id=? AND doctor_id=? AND patient_id=?');
        $s->execute([$id,$doctor,$patient]);$row=$s->fetch(PDO::FETCH_ASSOC);
        if(!is_array($row))self::fail('FOREIGN_APPOINTMENT',409);
        if($requireEligible){
            $now=(new DateTimeImmutable('now',new DateTimeZone('America/Mexico_City')))->format('Y-m-d H:i:s');
            if(!in_array(strtolower((string)$row['status']),['pending_otp','pending','scheduled','confirmed'],true)||(string)$row['start_at']<=$now)self::fail('INELIGIBLE_APPOINTMENT',409);
        }
        return $id;
    }
    private static function responsible(array $body,string $actor): ?string {
        $responsible=self::value($body,'responsible_user_id',64);
        // No verified team-assignment authority exists yet; assignment is limited to the authenticated actor.
        if($responsible!==null&&!hash_equals($actor,$responsible))self::fail('RESPONSIBLE_AUTHORITY_UNAVAILABLE',409);
        return $responsible;
    }
    private function receipt(string $doctor,string $patient,string $operation,string $key,string $hash,?array $result=null): ?array {
        if($result===null){
            $s=$this->pdo->prepare('SELECT request_hash,response_json FROM clinical_longitudinal_idempotency WHERE doctor_id=? AND patient_id=? AND operation=? AND idempotency_key=?');
            $s->execute([$doctor,$patient,$operation,$key]);$row=$s->fetch(PDO::FETCH_ASSOC);
            if(!is_array($row))return null;
            if(!hash_equals((string)$row['request_hash'],$hash))self::fail('IDEMPOTENCY_PAYLOAD_CONFLICT',409);
            return json_decode((string)$row['response_json'],true,512,JSON_THROW_ON_ERROR);
        }
        $s=$this->pdo->prepare('INSERT INTO clinical_longitudinal_idempotency (doctor_id,patient_id,operation,idempotency_key,request_hash,response_json,created_at) VALUES (?,?,?,?,?,?,UTC_TIMESTAMP())');
        $s->execute([$doctor,$patient,$operation,$key,$hash,self::json($result)]);return null;
    }
    public function read(string $doctor,string $patient,?int $id=null,bool $history=false): array {
        $this->scope($doctor,$patient);
        if($history){
            $sql='SELECT * FROM clinical_patient_task_audit_events WHERE doctor_id=? AND patient_id=?';$args=[$doctor,$patient];
            if($id!==null){$this->row($doctor,$patient,$id);$sql.=' AND task_id=?';$args[]=$id;}
            $s=$this->pdo->prepare($sql.' ORDER BY event_id');$s->execute($args);return ['events'=>$s->fetchAll(PDO::FETCH_ASSOC)];
        }
        if($id!==null)return ['item'=>self::project($this->row($doctor,$patient,$id))];
        $s=$this->pdo->prepare('SELECT * FROM clinical_patient_tasks WHERE doctor_id=? AND patient_id=? ORDER BY (state="OPEN") DESC,due_at IS NULL,due_at,task_id');
        $s->execute([$doctor,$patient]);return ['items'=>array_map(self::project(...),$s->fetchAll(PDO::FETCH_ASSOC))];
    }
    /** Physician-scoped Agenda projection. Task rows still pass through the canonical per-patient reader. */
    public function readAgendaFollowUps(string $doctor): array {
        if($doctor==='')self::fail('NOT_FOUND',404);
        $s=$this->pdo->prepare("SELECT DISTINCT t.patient_id,p.display_name FROM clinical_patient_tasks t
            JOIN patients_doctor_links l ON l.doctor_id=t.doctor_id AND l.patient_id=t.patient_id AND l.status='active'
            JOIN patients_patients p ON p.patient_id=t.patient_id
            WHERE t.doctor_id=? AND t.task_type='FOLLOW_UP' AND t.state='OPEN' ORDER BY p.display_name,t.patient_id");
        $s->execute([$doctor]);
        $groups=['overdue'=>[],'today'=>[],'upcoming'=>[],'no_due'=>[]];
        $zone=new DateTimeZone('America/Mexico_City');
        $today=(new DateTimeImmutable('now',$zone))->format('Y-m-d');
        $nowLocal=(new DateTimeImmutable('now',$zone))->format('Y-m-d H:i:s');
        $appointment=null;
        foreach($s->fetchAll(PDO::FETCH_ASSOC) as $patient){
            foreach($this->read($doctor,(string)$patient['patient_id'])['items'] as $row){
                if($row['task_type']!=='FOLLOW_UP'||$row['state']!=='OPEN')continue;
                $due=$row['due_at']===null?null:DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',(string)$row['due_at'],new DateTimeZone('UTC'));
                $localDue=$due?$due->setTimezone($zone):null;
                $group=$row['derived_due_state']==='OVERDUE'?'overdue':($localDue===null?'no_due':($localDue->format('Y-m-d')===$today?'today':'upcoming'));
                $linkedDisplay=null;
                if($row['appointment_id']!==null){
                    try{
                        $appointment??=$this->pdo->prepare('SELECT start_at,status FROM agenda_appointments WHERE appointment_id=? AND doctor_id=? AND patient_id=?');
                        $appointment->execute([$row['appointment_id'],$doctor,$patient['patient_id']]);
                        $linked=$appointment->fetch(PDO::FETCH_ASSOC);
                        if(is_array($linked)&&in_array(strtolower((string)$linked['status']),['pending_otp','pending','scheduled','confirmed'],true)&&(string)$linked['start_at']>$nowLocal){
                            $linkedDisplay='Cita vinculada · '.self::localDisplay((string)$linked['start_at']);
                        }
                    }catch(PDOException $ignored){$linkedDisplay=null;}
                }
                $groups[$group][]=[
                    'task_id'=>(int)$row['task_id'],
                    'patient_id'=>(string)$row['patient_id'],
                    'patient_name'=>(string)$patient['display_name'],
                    'title'=>(string)$row['title'],
                    'due_at'=>$row['due_at'],
                    'due_display'=>$localDue===null?null:self::localDisplay($localDue->format('Y-m-d H:i:s'),true),
                    'derived_due_state'=>$row['derived_due_state'],
                    'linked_appointment_display'=>$linkedDisplay,
                ];
            }
        }
        foreach(['overdue','today','upcoming'] as $group)usort($groups[$group],static fn($a,$b)=>strcmp((string)$a['due_at'],(string)$b['due_at'])?:($a['task_id']<=>$b['task_id']));
        usort($groups['no_due'],static fn($a,$b)=>$a['task_id']<=>$b['task_id']);
        return ['groups'=>$groups,'today_local'=>$today];
    }
    private static function localDisplay(string $value,bool $year=false): string {
        $date=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$value,new DateTimeZone('America/Mexico_City'));
        if(!$date)return '';
        $months=[1=>'ene',2=>'feb',3=>'mar',4=>'abr',5=>'may',6=>'jun',7=>'jul',8=>'ago',9=>'sep',10=>'oct',11=>'nov',12=>'dic'];
        return $date->format('j').' '.$months[(int)$date->format('n')].($year?' '.$date->format('Y'):'').' · '.$date->format('g:i').' '.((int)$date->format('G')<12?'a. m.':'p. m.');
    }
    public function mutate(string $doctor,string $patient,string $actor,string $operation,array $body,string $key,?int $id=null): array {
        if(getenv('MXMED_LON06A_WRITE_ENABLED')!=='1')self::fail('LON06A_WRITE_DISABLED',503);
        if(clinical_m6_write_window_blocks_writes())self::fail('M6_WRITE_WINDOW_BLOCKED',503);
        if($doctor===''||$patient===''||$actor===''||$key===''||strlen($key)>128||strlen($doctor)>64||strlen($patient)>64)self::fail('INVALID_COMMAND');
        $create=$operation==='CREATE';
        if(!in_array($operation,['CREATE','UPDATE','RESOLVE','CANCEL'],true)||$create!==($id===null))self::fail('INVALID_COMMAND');
        $allowed=match($operation){
            'CREATE'=>['task_type','title','responsible_user_id','due_at','source_encounter_id','appointment_id'],
            'UPDATE'=>['title','responsible_user_id','due_at','appointment_id','expected_version','reason'],
            default=>['expected_version','reason']
        };
        self::only($body,$allowed);
        if($operation==='UPDATE'&&!array_intersect(array_keys($body),['title','responsible_user_id','due_at','appointment_id']))self::fail('NO_CHANGES');
        $hash=hash('sha256',self::json(self::canonical(['v'=>1,'operation'=>$operation,'id'=>$id,'body'=>$body])));
        $command='tasks_'.$operation;
        $this->pdo->beginTransaction();
        try{
            $this->scope($doctor,$patient,true);
            $prior=$this->receipt($doctor,$patient,$command,$key,$hash);if($prior!==null){$this->pdo->commit();$prior['item']=self::project($prior['item']);return $prior;}
            $now=gmdate('Y-m-d H:i:s');$before=null;$reason=null;
            if($create){
                $type=self::value($body,'task_type',32,true);
                if(!in_array($type,['CLINICAL_ACTION','FOLLOW_UP'],true))self::fail('INVALID_TASK_TYPE');
                $title=self::value($body,'title',500,true);
                $responsible=self::responsible($body,$actor);
                $due=self::date(self::value($body,'due_at',19));
                $source=$this->source($doctor,$patient,$body['source_encounter_id']??null);
                $appointment=$this->appointment($doctor,$patient,$body['appointment_id']??null,$type==='FOLLOW_UP');
                $provenance=$source===null?'EXPLICIT_LONGITUDINAL_ENTRY':'ENCOUNTER_DERIVED_EXPLICIT_ENTRY';
                $s=$this->pdo->prepare('INSERT INTO clinical_patient_tasks (doctor_id,patient_id,task_type,title,responsible_user_id,due_at,state,provenance,source_encounter_id,appointment_id,created_by,updated_by,created_at,updated_at) VALUES (?,?,?,?,?,?,"OPEN",?,?,?,?,?,?,?)');
                $s->execute([$doctor,$patient,$type,$title,$responsible,$due,$provenance,$source,$appointment,$actor,$actor,$now,$now]);
                $id=(int)$this->pdo->lastInsertId();
            }else{
                $before=$this->row($doctor,$patient,$id,true);$expected=self::version($body);
                if((int)$before['row_version']!==$expected)self::fail('STALE_VERSION',409);
                if($before['state']!=='OPEN')self::fail('TERMINAL_TASK',409);
                $reason=self::value($body,'reason',255,true);
                if($operation==='UPDATE'){
                    $title=array_key_exists('title',$body)?self::value($body,'title',500,true):$before['title'];
                    $responsible=array_key_exists('responsible_user_id',$body)?self::responsible($body,$actor):$before['responsible_user_id'];
                    $due=array_key_exists('due_at',$body)?self::date(self::value($body,'due_at',19)):$before['due_at'];
                    $requestedAppointment=$body['appointment_id']??null;
                    $sameAppointment=is_string($requestedAppointment)&&trim($requestedAppointment)===(string)$before['appointment_id'];
                    $appointment=array_key_exists('appointment_id',$body)&&!$sameAppointment?$this->appointment($doctor,$patient,$requestedAppointment,$before['task_type']==='FOLLOW_UP'):$before['appointment_id'];
                    $s=$this->pdo->prepare('UPDATE clinical_patient_tasks SET title=?,responsible_user_id=?,due_at=?,appointment_id=?,updated_by=?,updated_at=?,row_version=row_version+1 WHERE task_id=? AND doctor_id=? AND patient_id=? AND state="OPEN" AND row_version=?');
                    $s->execute([$title,$responsible,$due,$appointment,$actor,$now,$id,$doctor,$patient,$expected]);
                }else{
                    $to=$operation==='RESOLVE'?'RESOLVED':'CANCELED';
                    $s=$this->pdo->prepare('UPDATE clinical_patient_tasks SET state=?,ended_at=?,ended_by=?,end_reason=?,updated_by=?,updated_at=?,row_version=row_version+1 WHERE task_id=? AND doctor_id=? AND patient_id=? AND state="OPEN" AND row_version=?');
                    $s->execute([$to,$now,$actor,$reason,$actor,$now,$id,$doctor,$patient,$expected]);
                }
                if($s->rowCount()!==1)self::fail('STALE_VERSION',409);
            }
            $after=$this->row($doctor,$patient,$id);
            $s=$this->pdo->prepare('INSERT INTO clinical_patient_task_audit_events (doctor_id,patient_id,task_id,entity_version,operation,actor_user_id,reason,before_json,after_json,occurred_at) VALUES (?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP())');
            $s->execute([$doctor,$patient,$id,(int)$after['row_version'],$operation,$actor,$reason,$before===null?null:self::json($before),self::json($after)]);
            $result=['item'=>$after];$this->receipt($doctor,$patient,$command,$key,$hash,$result);
            $this->pdo->commit();$result['item']=self::project($result['item']);return $result;
        }catch(Throwable $error){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $error;}
    }
}
