<?php
declare(strict_types=1);

require_once __DIR__ . '/clinical_m6_write_window.php';

final class ClinicalLongitudinalException extends RuntimeException {
    public function __construct(public string $errorCode, public int $httpStatus) { parent::__construct($errorCode); }
}

/** LON03A only. All SQL and clinical invariants for this authority live here. */
final class ClinicalLongitudinalAntecedents {
    private const CATEGORIES = ['PERSONAL_PATHOLOGICAL','PERSONAL_NON_PATHOLOGICAL','SURGICAL','FAMILY','HABITS','VACCINATION','GYNECOLOGICAL','OTHER'];
    private const PROVENANCE = ['EXPLICIT_LONGITUDINAL_ENTRY','ENCOUNTER_DERIVED_EXPLICIT_PROMOTION','PATIENT_REPORTED','LEGACY_IMPORTED_CONFIRMED','EXTERNAL_SOURCE'];
    private const RESOURCES = [
        'antecedents' => ['table'=>'clinical_patient_antecedent_facts','id'=>'fact_id','type'=>'ANTECEDENT_FACT'],
        'antecedent-reviews' => ['table'=>'clinical_patient_antecedent_reviews','id'=>'review_id','type'=>'ANTECEDENT_REVIEW'],
        'allergies' => ['table'=>'clinical_patient_allergies','id'=>'allergy_id','type'=>'ALLERGY'],
        'allergy-reviews' => ['table'=>'clinical_patient_allergy_reviews','id'=>'review_id','type'=>'ALLERGY_REVIEW'],
    ];

    public function __construct(private PDO $pdo) {}

    private static function fail(string $code, int $status = 400): never { throw new ClinicalLongitudinalException($code, $status); }
    private static function str(array $body, string $key, int $max, bool $required = true): ?string {
        $raw = $body[$key] ?? null;
        if ($raw === null && !$required) return null;
        if (!is_string($raw)) self::fail('INVALID_' . strtoupper($key));
        $v = trim($raw);
        if (($required && $v === '') || strlen($v) > $max) self::fail('INVALID_' . strtoupper($key));
        return $v === '' ? null : $v;
    }
    private static function oneOf(?string $v, array $allowed, string $name): string {
        if ($v === null || !in_array($v, $allowed, true)) self::fail('INVALID_' . $name);
        return $v;
    }
    private static function version(array $body): int {
        $v = $body['expected_version'] ?? null;
        if (!is_int($v) || $v < 1) self::fail('EXPECTED_VERSION_REQUIRED');
        return $v;
    }
    private static function noExtras(array $body, array $keys): void {
        foreach ($body as $key => $_) if (!in_array($key, $keys, true)) self::fail('UNKNOWN_FIELD');
    }
    private function scope(string $doctor, string $patient, bool $lock = false): void {
        if ($doctor === '' || $patient === '') self::fail('NOT_FOUND', 404);
        $sql = "SELECT link_id FROM patients_doctor_links WHERE doctor_id=? AND patient_id=? AND status='active'" . ($lock ? ' FOR UPDATE' : '');
        $s = $this->pdo->prepare($sql); $s->execute([$doctor,$patient]);
        if (!$s->fetchColumn()) self::fail('NOT_FOUND', 404);
    }
    private function source(array $body, string $doctor, string $patient): array {
        $kind = self::str($body, 'provenance', 48);
        self::oneOf($kind, self::PROVENANCE, 'PROVENANCE');
        $type = self::str($body, 'source_type', 24, false);
        $id = self::str($body, 'source_id', 128, false);
        if (($type === null) !== ($id === null)) self::fail('INVALID_SOURCE');
        if ($kind === 'ENCOUNTER_DERIVED_EXPLICIT_PROMOTION' && $type === null) self::fail('SOURCE_REQUIRED');
        // These sources lack a doctor-attributable registry in the current schema.
        // Keep their catalog values reserved, but fail closed until a separate authority is approved.
        if (in_array($kind,['LEGACY_IMPORTED_CONFIRMED','EXTERNAL_SOURCE'],true)) self::fail('SOURCE_AUTHORITY_UNAVAILABLE',409);
        if ($type === null) return [$kind,null,null];
        if ($type === 'ENCOUNTER') {
            if (!ctype_digit($id)) self::fail('INVALID_SOURCE');
            $sql='SELECT 1 FROM clinical_encounters WHERE encounter_id=? AND doctor_id=? AND patient_id=?';
        } elseif ($type === 'SECTION') {
            if (!ctype_digit($id)) self::fail('INVALID_SOURCE');
            $sql='SELECT 1 FROM clinical_encounter_sections s JOIN clinical_encounters e ON e.encounter_id=s.encounter_id WHERE s.section_id=? AND e.doctor_id=? AND e.patient_id=?';
        } elseif ($type === 'DOCUMENT') {
            if (!ctype_digit($id)) self::fail('INVALID_SOURCE');
            $sql="SELECT 1 FROM clinical_documents d JOIN clinical_encounters e ON e.encounter_id=CAST(d.encounter_id AS UNSIGNED) WHERE d.id=? AND e.doctor_id=? AND e.patient_id=? AND d.patient_id=e.patient_id AND d.encounter_id REGEXP '^[0-9]+$'";
        } else self::fail('INVALID_SOURCE');
        $s=$this->pdo->prepare($sql); $s->execute([$id,$doctor,$patient]);
        if (!$s->fetchColumn()) self::fail('FOREIGN_SOURCE', 409);
        return [$kind,$type,$id];
    }
    private static function config(string $resource): array {
        if (!isset(self::RESOURCES[$resource])) self::fail('NOT_FOUND',404);
        return self::RESOURCES[$resource];
    }
    private function row(array $cfg, string $doctor, string $patient, int $id): array {
        $s=$this->pdo->prepare('SELECT * FROM '.$cfg['table'].' WHERE '.$cfg['id'].'=? AND doctor_id=? AND patient_id=?');
        $s->execute([$id,$doctor,$patient]); $r=$s->fetch(PDO::FETCH_ASSOC);
        if (!is_array($r)) self::fail('NOT_FOUND',404);
        return $r;
    }
    private static function json(array $v): string {
        $s=json_encode($v, JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        return $s;
    }
    private static function canonical(mixed $value): mixed {
        if (!is_array($value)) return $value;
        if (!array_is_list($value)) ksort($value, SORT_STRING);
        foreach ($value as $key=>$part) $value[$key]=self::canonical($part);
        return $value;
    }
    private function audit(array $cfg, string $doctor, string $patient, int $id, int $version, string $operation, string $actor, ?array $before, array $after, ?string $reason): void {
        $s=$this->pdo->prepare('INSERT INTO clinical_longitudinal_audit_events (doctor_id,patient_id,entity_type,entity_id,entity_version,operation,actor_user_id,provenance,source_type,source_id,reason,before_json,after_json,occurred_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP())');
        $s->execute([$doctor,$patient,$cfg['type'],$id,$version,$operation,$actor,$after['provenance']??null,$after['source_type']??null,$after['source_id']??null,$reason,$before===null?null:self::json($before),self::json($after)]);
    }
    private function invalidateReview(string $resource, string $doctor, string $patient, string $actor, ?array $before, array $after): void {
        $reviewResource=$resource==='antecedents'?'antecedent-reviews':'allergy-reviews';
        $cfg=self::config($reviewResource);
        $categories=$resource==='antecedents'?array_unique(array_filter([$before['category']??null,$after['category']??null])):[null];
        foreach ($categories as $category) {
            $sql='SELECT * FROM '.$cfg['table'].' WHERE doctor_id=? AND patient_id=?';
            $params=[$doctor,$patient];
            if ($category!==null) {$sql.=' AND category=?';$params[]=$category;}
            $sql.=' FOR UPDATE';$s=$this->pdo->prepare($sql);$s->execute($params);
            $old=$s->fetch(PDO::FETCH_ASSOC);
            if (!is_array($old) || $old['review_state']==='NEEDS_REVIEW') continue;
            $s=$this->pdo->prepare('UPDATE '.$cfg['table']." SET review_state='NEEDS_REVIEW',row_version=row_version+1 WHERE ".$cfg['id'].'=?');
            $s->execute([$old[$cfg['id']]]);
            $new=$this->row($cfg,$doctor,$patient,(int)$old[$cfg['id']]);
            $this->audit($cfg,$doctor,$patient,(int)$old[$cfg['id']],(int)$new['row_version'],'INVALIDATE',$actor,$old,$new,'Clinical fact changed');
        }
    }
    private function validateReview(string $resource, array $body, string $doctor, string $patient): array {
        if ($resource === 'antecedent-reviews') {
            $category=self::oneOf(self::str($body,'category',48),self::CATEGORIES,'CATEGORY');
            $state=self::oneOf(self::str($body,'review_state',24),['REVIEWED_WITH_FACTS','CONFIRMED_NONE'],'REVIEW_STATE');
            $s=$this->pdo->prepare("SELECT COUNT(*) FROM clinical_patient_antecedent_facts WHERE doctor_id=? AND patient_id=? AND category=? AND state='CURRENT'");
            $s->execute([$doctor,$patient,$category]);
            $has=(int)$s->fetchColumn()>0;
            if (($state==='CONFIRMED_NONE' && $has) || ($state==='REVIEWED_WITH_FACTS' && !$has)) self::fail('REVIEW_FACT_CONFLICT',409);
            return ['category'=>$category,'review_state'=>$state];
        }
        $state=self::oneOf(self::str($body,'review_state',24),['REVIEWED_WITH_ALLERGIES','CONFIRMED_NONE'],'REVIEW_STATE');
        $s=$this->pdo->prepare("SELECT COUNT(*) FROM clinical_patient_allergies WHERE doctor_id=? AND patient_id=? AND state='CURRENT'");
        $s->execute([$doctor,$patient]); $has=(int)$s->fetchColumn()>0;
        if (($state==='CONFIRMED_NONE' && $has) || ($state==='REVIEWED_WITH_ALLERGIES' && !$has)) self::fail('REVIEW_ALLERGY_CONFLICT',409);
        return ['review_state'=>$state];
    }
    private function validate(string $resource, array $body, string $doctor, string $patient): array {
        if (str_ends_with($resource,'reviews')) return $this->validateReview($resource,$body,$doctor,$patient);
        [$provenance,$sourceType,$sourceId]=$this->source($body,$doctor,$patient);
        $common=['provenance'=>$provenance,'source_type'=>$sourceType,'source_id'=>$sourceId];
        if ($resource==='antecedents') return $common+[
            'category'=>self::oneOf(self::str($body,'category',48),self::CATEGORIES,'CATEGORY'),
            'content'=>self::str($body,'content',10000),
            'state'=>self::oneOf(self::str($body,'state',16),['CURRENT','INACTIVE'],'STATE')];
        $validation=self::oneOf(self::str($body,'validation_state',24),['REPORTED','CLINICIAN_REVIEWED'],'VALIDATION_STATE');
        return $common+['substance'=>self::str($body,'substance',255),'reaction'=>self::str($body,'reaction',10000,false),
            'state'=>self::oneOf(self::str($body,'state',16),['CURRENT','INACTIVE'],'STATE'),'validation_state'=>$validation];
    }
    private static function keys(string $resource, bool $update): array {
        $keys=str_ends_with($resource,'reviews') ? ['review_state'] : ['provenance','source_type','source_id','state'];
        if ($resource==='antecedents' || $resource==='antecedent-reviews') $keys[]='category';
        if ($resource==='antecedents') $keys[]='content';
        if ($resource==='allergies') array_push($keys,'substance','reaction','validation_state');
        if ($update) $keys[]='expected_version';
        $keys[]='reason';
        return $keys;
    }
    public function read(string $resource, string $doctor, string $patient, ?int $id=null, bool $history=false): array {
        $cfg=self::config($resource); $this->scope($doctor,$patient);
        if ($history) {
            $sql='SELECT * FROM clinical_longitudinal_audit_events WHERE doctor_id=? AND patient_id=? AND entity_type=?';
            $params=[$doctor,$patient,$cfg['type']];
            if ($id!==null) {$sql.=' AND entity_id=?';$params[]=$id;}
            $sql.=' ORDER BY event_id ASC';
            $s=$this->pdo->prepare($sql);$s->execute($params);
            return ['events'=>$s->fetchAll(PDO::FETCH_ASSOC)];
        }
        if ($id!==null) return ['item'=>$this->row($cfg,$doctor,$patient,$id)];
        $s=$this->pdo->prepare('SELECT * FROM '.$cfg['table'].' WHERE doctor_id=? AND patient_id=? ORDER BY '.$cfg['id'].' ASC');
        $s->execute([$doctor,$patient]);$items=$s->fetchAll(PDO::FETCH_ASSOC);
        if (str_ends_with($resource,'reviews')) return ['items'=>$items];
        $reviewResource=$resource==='antecedents'?'antecedent-reviews':'allergy-reviews';
        $reviewCfg=self::config($reviewResource);
        $r=$this->pdo->prepare('SELECT * FROM '.$reviewCfg['table'].' WHERE doctor_id=? AND patient_id=?');
        $r->execute([$doctor,$patient]);$reviews=$r->fetchAll(PDO::FETCH_ASSOC);
        if ($resource==='allergies') return ['items'=>$items,'review'=>$reviews[0]??null,'knowledge_state'=>isset($reviews[0])?$reviews[0]['review_state']:'UNKNOWN'];
        $states=[];
        foreach (self::CATEGORIES as $category) $states[$category]='UNKNOWN';
        foreach ($reviews as $review) $states[$review['category']]=$review['review_state'];
        return ['items'=>$items,'reviews'=>$reviews,'knowledge_state_by_category'=>$states];
    }
    public function mutate(string $resource, string $doctor, string $patient, string $actor, string $operation, array $body, string $key, ?int $id=null): array {
        $cfg=self::config($resource);
        if (getenv('MXMED_LON03A_WRITE_ENABLED')!=='1') self::fail('LON03A_WRITE_DISABLED',503);
        if (clinical_m6_write_window_blocks_writes()) self::fail('M6_WRITE_WINDOW_BLOCKED',503);
        if ($actor==='' || $key==='' || strlen($key)>128 || strlen($doctor)>64 || strlen($patient)>64) self::fail('INVALID_COMMAND');
        if (!in_array($operation,['CREATE','UPDATE'],true) || ($operation==='CREATE')!==($id===null)) self::fail('INVALID_COMMAND');
        self::noExtras($body,self::keys($resource,$operation==='UPDATE'));
        $expected=$operation==='UPDATE'?self::version($body):null;
        $reason=self::str($body,'reason',255,false);
        if ($operation==='UPDATE' && $reason===null) self::fail('REASON_REQUIRED');
        $hash=hash('sha256',self::json(self::canonical(['v'=>1,'resource'=>$resource,'operation'=>$operation,'id'=>$id,'body'=>$body])));
        $command=$resource.'_'.$operation;
        $this->pdo->beginTransaction();
        try {
            $this->scope($doctor,$patient,true);
            $s=$this->pdo->prepare('SELECT request_hash,response_json FROM clinical_longitudinal_idempotency WHERE doctor_id=? AND patient_id=? AND operation=? AND idempotency_key=?');
            $s->execute([$doctor,$patient,$command,$key]);$prior=$s->fetch(PDO::FETCH_ASSOC);
            if (is_array($prior)) {
                if (!hash_equals($prior['request_hash'],$hash)) self::fail('IDEMPOTENCY_PAYLOAD_CONFLICT',409);
                $result=json_decode($prior['response_json'],true,512,JSON_THROW_ON_ERROR);
                $this->pdo->commit();return $result;
            }
            $data=$this->validate($resource,$body,$doctor,$patient);
            $before=null;
            if ($operation==='CREATE') {
                if (str_ends_with($resource,'reviews')) {
                    $where=$resource==='antecedent-reviews'?' AND category=?':'';
                    $params=[$doctor,$patient];if ($where!=='') $params[]=$data['category'];
                    $s=$this->pdo->prepare('SELECT COUNT(*) FROM '.$cfg['table'].' WHERE doctor_id=? AND patient_id=?'.$where);
                    $s->execute($params);if ((int)$s->fetchColumn()>0) self::fail('REVIEW_EXISTS',409);
                }
                $data=['doctor_id'=>$doctor,'patient_id'=>$patient]+$data;
                if (str_ends_with($resource,'reviews')) $data+=['reviewed_by'=>$actor,'reviewed_at'=>gmdate('Y-m-d H:i:s')];
                else {
                    $now=gmdate('Y-m-d H:i:s');$data+=['created_by'=>$actor,'updated_by'=>$actor,'created_at'=>$now,'updated_at'=>$now];
                    if ($resource==='allergies' && $data['validation_state']==='CLINICIAN_REVIEWED') $data+=['reviewed_by'=>$actor,'reviewed_at'=>$now];
                }
                $cols=array_keys($data);$sql='INSERT INTO '.$cfg['table'].' ('.implode(',',$cols).') VALUES ('.implode(',',array_fill(0,count($cols),'?')).')';
                $s=$this->pdo->prepare($sql);$s->execute(array_values($data));$id=(int)$this->pdo->lastInsertId();
            } else {
                $before=$this->row($cfg,$doctor,$patient,$id);
                if ((int)$before['row_version']!==$expected) self::fail('STALE_VERSION',409);
                if ($resource==='antecedent-reviews' && $before['category']!==$data['category']) self::fail('CATEGORY_IMMUTABLE',409);
                if (str_ends_with($resource,'reviews')) $data+=['reviewed_by'=>$actor,'reviewed_at'=>gmdate('Y-m-d H:i:s')];
                else {
                    $data+=['updated_by'=>$actor,'updated_at'=>gmdate('Y-m-d H:i:s')];
                    if ($resource==='allergies' && $data['validation_state']==='CLINICIAN_REVIEWED') $data+=['reviewed_by'=>$actor,'reviewed_at'=>gmdate('Y-m-d H:i:s')];
                }
                $set=[];foreach (array_keys($data) as $col) $set[]=$col.'=?';
                $set[]='row_version=row_version+1';
                $s=$this->pdo->prepare('UPDATE '.$cfg['table'].' SET '.implode(',',$set).' WHERE '.$cfg['id'].'=? AND doctor_id=? AND patient_id=? AND row_version=?');
                $s->execute([...array_values($data),$id,$doctor,$patient,$expected]);
                if ($s->rowCount()!==1) self::fail('STALE_VERSION',409);
            }
            $after=$this->row($cfg,$doctor,$patient,$id);
            $this->audit($cfg,$doctor,$patient,$id,(int)$after['row_version'],$operation,$actor,$before,$after,$reason);
            if (!str_ends_with($resource,'reviews')) $this->invalidateReview($resource,$doctor,$patient,$actor,$before,$after);
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
