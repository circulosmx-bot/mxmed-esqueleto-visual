<?php
declare(strict_types=1);

require_once __DIR__ . '/clinical_documents.php';
require_once __DIR__ . '/clinical_study_contract.php';
require_once __DIR__ . '/clinical_order_result_read.php';
require_once __DIR__ . '/clinical_private_binary_storage.php';
require_once __DIR__ . '/clinical_multipart_document_service.php';

/** Implemented only by a future clinical provider release authority, or a disposable QA adapter. */
interface ProviderReleasedResultAuthority
{
    public function assertReleased(array $release): void;
}

/** Internal domain boundary. No HTTP route or ordinary provider role calls this service. */
final class HealthcareStudyInteropService
{
    public function __construct(private PDO $pdo) {}

    private function tx(callable $work): mixed
    {
        if ($this->pdo->inTransaction()) throw new LogicException('INTEROP_TRANSACTION_OWNERSHIP_REQUIRED');
        $this->pdo->beginTransaction();
        try {
            $result=$work();
            $this->pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    private static function required(string $value, int $max, string $code): string
    {
        $value=trim($value);
        if ($value==='' || strlen($value)>$max || preg_match('/[\x00-\x1F\x7F]/',$value)) throw new InvalidArgumentException($code);
        return $value;
    }

    private static function uuid(string $value): string
    {
        if (!clinical_study_valid_uuid($value)) throw new InvalidArgumentException('INTEROP_UUID_INVALID');
        return strtolower($value);
    }

    private function one(string $sql, array $args): ?array
    {
        $stmt=$this->pdo->prepare($sql);$stmt->execute($args);
        $row=$stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row)?$row:null;
    }

    private function assertDiagnosticProcedurePolicies(array $studyIds): void
    {
        if ($studyIds===[]) throw new RuntimeException('REFERRAL_ITEMS_INVALID');
        $query=$this->pdo->prepare('SELECT study_type_key,category_key FROM clinical_study_types WHERE study_type_id=?');
        foreach ($studyIds as $studyId) {
            $query->execute([(int)$studyId]);
            $row=$query->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row) || ((string)$row['category_key']==='PROCEDIMIENTOS_DIAGNOSTICOS'
                && clinical_diagnostic_procedure_policy((string)$row['study_type_key'])===null)) {
                throw new RuntimeException('DIAGNOSTIC_PROCEDURE_SCOPE_INVALID');
            }
        }
    }

    private static function digest(array $data): string
    {
        return hash('sha256',json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
    }

    /** A bounded order-only view; no patient chart, encounters, prescriptions or unrelated documents. */
    public function referralRead(string $referralUuid): array
    {
        $ref=$this->one('SELECT referral_uuid,doctor_id,patient_id,source_order_document_id,source_order_uuid,
            source_order_version,group_id,location_id,state,sent_at FROM healthcare_study_referrals WHERE referral_uuid=?',
            [self::uuid($referralUuid)]);
        if ($ref===null) throw new RuntimeException('REFERRAL_NOT_FOUND');
        $rows=$this->pdo->prepare('SELECT source_order_item_id,study_type_id,study_display_name,study_category
            FROM healthcare_study_referral_items WHERE referral_id=(SELECT referral_id FROM healthcare_study_referrals WHERE referral_uuid=?) ORDER BY source_order_item_id');
        $rows->execute([$referralUuid]);
        $ref['items']=$rows->fetchAll(PDO::FETCH_ASSOC);
        return $ref;
    }

    /** One physician order can be explicitly transmitted to many locations, by exact item subset. */
    public function sendReferral(string $doctorId, string $patientId, string $orderUuid, int $version,
        string $groupId, int $locationId, array $itemIds, string $actorId, string $idempotencyKey): array
    {
        $doctorId=self::required($doctorId,64,'DOCTOR_REQUIRED');
        $patientId=self::required($patientId,128,'PATIENT_REQUIRED');
        $orderUuid=self::uuid($orderUuid);
        $groupId=self::required($groupId,64,'PROVIDER_REQUIRED');
        $actorId=self::required($actorId,128,'ACTOR_REQUIRED');
        $idempotencyKey=self::required($idempotencyKey,128,'IDEMPOTENCY_KEY_REQUIRED');
        if ($version<1 || $locationId<1 || !array_is_list($itemIds) || $itemIds===[] || count($itemIds)>100)
            throw new InvalidArgumentException('REFERRAL_ITEMS_INVALID');
        $selected=[];
        foreach ($itemIds as $itemId) {
            if (!is_string($itemId)) throw new InvalidArgumentException('REFERRAL_ITEMS_INVALID');
            $id=self::uuid($itemId);
            if (isset($selected[$id])) throw new InvalidArgumentException('REFERRAL_ITEMS_DUPLICATE');
            $selected[$id]=true;
        }
        $itemIds=array_keys($selected);sort($itemIds,SORT_STRING);
        $hash=self::digest([$doctorId,$patientId,$orderUuid,$version,$groupId,$locationId,$itemIds]);
        try {
            return $this->tx(function () use ($doctorId,$patientId,$orderUuid,$version,$groupId,$locationId,$itemIds,$actorId,$idempotencyKey,$hash): array {
            $existing=$this->one('SELECT referral_id,referral_uuid,request_sha256 FROM healthcare_study_referrals
                WHERE doctor_id=? AND idempotency_key=?',[$doctorId,$idempotencyKey]);
            if ($existing!==null) {
                if (!hash_equals($existing['request_sha256'],$hash)) throw new RuntimeException('REFERRAL_IDEMPOTENCY_CONFLICT');
                return ['referral_id'=>(int)$existing['referral_id'],'referral_uuid'=>$existing['referral_uuid'],'replayed'=>true];
            }
            $link=$this->one("SELECT link_id FROM patients_doctor_links WHERE doctor_id=? AND patient_id=? AND status='active' LIMIT 1",
                [$doctorId,$patientId]);
            if ($link===null) throw new RuntimeException('REFERRAL_PATIENT_SCOPE_DENIED');
            $order=$this->one('SELECT id,document_uuid,version,document_type,status,generated_at,payload_json
                FROM clinical_documents WHERE document_uuid=? AND patient_id=? AND version=? FOR UPDATE',
                [$orderUuid,$patientId,$version]);
            if ($order===null || !clinical_study_order_type((string)$order['document_type'])) throw new RuntimeException('REFERRAL_ORDER_NOT_FOUND');
            $payload=json_decode((string)$order['payload_json'],true);
            if (!is_array($payload) || (int)($payload['order_payload_version']??0)!==2)
                throw new RuntimeException('REFERRAL_V2_ITEMS_REQUIRED');
            if (!in_array($order['status'],['generated','signed'],true) || !$order['generated_at']
                || str_starts_with((string)$order['generated_at'],'0000-')
                || in_array(strtolower((string)($payload['status']??'')),['replaced','voided'],true)
                || !empty($payload['replaced_by_document_id']) || !empty($payload['replaced_by_document_uuid']))
                throw new RuntimeException('REFERRAL_ORDER_NOT_ISSUED');
            $successor=$this->one('SELECT revision_id FROM clinical_document_revisions WHERE supersedes_document_id=?
                OR (original_document_id=? AND supersedes_document_id IS NULL) LIMIT 1',[$order['id'],$order['id']]);
            if ($successor!==null) throw new RuntimeException('REFERRAL_ORDER_REPLACED');
            $byId=[];
            foreach (($payload['order_items']??[]) as $item) {
                if (is_array($item) && is_string($item['order_item_id']??null)) $byId[strtolower($item['order_item_id'])]=$item;
            }
            $studyIds=[];$items=[];
            foreach ($itemIds as $id) {
                $item=$byId[$id]??null;
                if (!is_array($item) || !is_numeric($item['study_type_id']??null) || (int)$item['study_type_id']<1)
                    throw new RuntimeException('REFERRAL_ITEM_NOT_CANONICAL_SOURCE');
                $studyId=(int)$item['study_type_id'];$studyIds[$studyId]=true;$items[$id]=$item;
            }
            $this->assertDiagnosticProcedurePolicies(array_keys($studyIds));
            $target=$this->one("SELECT l.location_id FROM healthcare_organization_locations l
                JOIN healthcare_organization_provider_status ps ON ps.group_id=l.group_id
                WHERE l.location_id=? AND l.group_id=? AND l.operational_state='ACTIVE' AND l.verification_state='VERIFIED'
                  AND ps.operational_state='ACTIVE' AND ps.verification_state='VERIFIED' FOR UPDATE",[$locationId,$groupId]);
            if ($target===null) throw new RuntimeException('REFERRAL_TARGET_INELIGIBLE');
            $coverage=$this->pdo->prepare("SELECT o.study_type_id FROM healthcare_organization_location_study_offerings o
                JOIN healthcare_organization_master_services m ON m.master_service_id=o.master_service_id
                  AND m.group_id=o.group_id AND m.study_type_id=o.study_type_id
                JOIN clinical_study_types s ON s.study_type_id=o.study_type_id
                WHERE o.location_id=? AND o.group_id=? AND o.study_type_id=? AND o.operational_state='ACTIVE'
                  AND o.verification_state='VERIFIED' AND o.service_mode='ON_SITE'
                  AND m.operational_state='ACTIVE' AND s.is_active=1 LIMIT 1");
            foreach (array_keys($studyIds) as $studyId) {
                $coverage->execute([$locationId,$groupId,$studyId]);
                if ($coverage->fetchColumn()===false) throw new RuntimeException('REFERRAL_STUDY_NOT_OFFERED');
            }
            $uuid=mxmed_uuidv4();
            $this->pdo->prepare('INSERT INTO healthcare_study_referrals
                (referral_uuid,doctor_id,patient_id,source_order_document_id,source_order_uuid,source_order_version,
                 group_id,location_id,idempotency_key,request_sha256,created_by_user_id)
                VALUES (?,?,?,?,?,?,?,?,?,?,?)')->execute([$uuid,$doctorId,$patientId,(int)$order['id'],$orderUuid,
                    $version,$groupId,$locationId,$idempotencyKey,$hash,$actorId]);
            $referralId=(int)$this->pdo->lastInsertId();
            $insert=$this->pdo->prepare('INSERT INTO healthcare_study_referral_items
                (referral_id,source_order_item_id,study_type_id,study_display_name,study_category) VALUES (?,?,?,?,?)');
            foreach ($items as $id=>$item) $insert->execute([$referralId,$id,(int)$item['study_type_id'],
                (string)$item['study_display_name'],(string)($item['study_category']??'OTROS')]);
            $this->event($referralId,'SENT','PHYSICIAN',$actorId,null);
            return ['referral_id'=>$referralId,'referral_uuid'=>$uuid,'replayed'=>false];
            });
        } catch (PDOException $e) {
            if ($e->getCode()!=='23000') throw $e;
            $existing=$this->one('SELECT referral_id,referral_uuid,request_sha256 FROM healthcare_study_referrals
                WHERE doctor_id=? AND idempotency_key=?',[$doctorId,$idempotencyKey]);
            if ($existing===null) throw $e;
            if (!hash_equals($existing['request_sha256'],$hash)) throw new RuntimeException('REFERRAL_IDEMPOTENCY_CONFLICT',0,$e);
            return ['referral_id'=>(int)$existing['referral_id'],'referral_uuid'=>$existing['referral_uuid'],'replayed'=>true];
        }
    }

    private function event(int $referralId,string $type,string $actorType,string $actorId,?string $reason): void
    {
        $this->pdo->prepare('INSERT INTO healthcare_study_referral_events
            (referral_id,event_type,actor_type,actor_id,reason) VALUES (?,?,?,?,?)')
            ->execute([$referralId,$type,$actorType,$actorId,$reason]);
    }

    /** Internal acceptance bridge; provider staff authorization is intentionally not exposed yet. */
    public function acceptReferral(string $referralUuid,string $actorId): array
    {
        $referralUuid=self::uuid($referralUuid);$actorId=self::required($actorId,128,'ACTOR_REQUIRED');
        return $this->tx(function () use ($referralUuid,$actorId): array {
            $ref=$this->one('SELECT * FROM healthcare_study_referrals WHERE referral_uuid=? FOR UPDATE',[$referralUuid]);
            if ($ref===null) throw new RuntimeException('REFERRAL_NOT_FOUND');
            $existing=$this->one('SELECT service_order_id,service_order_uuid FROM healthcare_provider_service_orders WHERE referral_id=?',[$ref['referral_id']]);
            if ($ref['state']==='ACCEPTED') {
                if ($existing===null) throw new RuntimeException('REFERRAL_SERVICE_ORDER_MISSING');
                return ['service_order_id'=>(int)$existing['service_order_id'],'service_order_uuid'=>$existing['service_order_uuid'],'replayed'=>true];
            }
            if ($ref['state']!=='SENT' || $existing!==null) throw new RuntimeException('REFERRAL_NOT_ACCEPTABLE');
            $eligible=$this->one("SELECT l.location_id FROM healthcare_organization_locations l
                JOIN healthcare_organization_provider_status ps ON ps.group_id=l.group_id
                WHERE l.location_id=? AND l.group_id=? AND l.operational_state='ACTIVE'
                  AND l.verification_state='VERIFIED' AND ps.operational_state='ACTIVE'
                  AND ps.verification_state='VERIFIED' LIMIT 1",[$ref['location_id'],$ref['group_id']]);
            if ($eligible===null) throw new RuntimeException('REFERRAL_TARGET_INELIGIBLE');
            $ids=$this->pdo->prepare('SELECT DISTINCT study_type_id FROM healthcare_study_referral_items WHERE referral_id=?');
            $ids->execute([$ref['referral_id']]);
            $this->assertDiagnosticProcedurePolicies($ids->fetchAll(PDO::FETCH_COLUMN));
            $items=$this->one("SELECT COUNT(*) AS total,
                SUM(CASE WHEN o.offering_id IS NOT NULL AND o.operational_state='ACTIVE'
                    AND o.verification_state='VERIFIED' AND o.service_mode='ON_SITE'
                    AND m.operational_state='ACTIVE' AND st.is_active=1 THEN 1 ELSE 0 END) AS eligible
                FROM healthcare_study_referral_items ri
                LEFT JOIN healthcare_organization_location_study_offerings o
                  ON o.location_id=? AND o.group_id=? AND o.study_type_id=ri.study_type_id
                LEFT JOIN healthcare_organization_master_services m ON m.master_service_id=o.master_service_id
                  AND m.group_id=o.group_id AND m.study_type_id=o.study_type_id
                LEFT JOIN clinical_study_types st ON st.study_type_id=ri.study_type_id
                WHERE ri.referral_id=?",[$ref['location_id'],$ref['group_id'],$ref['referral_id']]);
            if ($items===null || (int)$items['total']<1 || (int)$items['eligible']!==(int)$items['total'])
                throw new RuntimeException('REFERRAL_STUDY_NOT_OFFERED');
            $uuid=mxmed_uuidv4();
            $this->pdo->prepare("INSERT INTO healthcare_provider_service_orders
                (service_order_uuid,group_id,location_id,patient_id,origin,referral_id)
                VALUES (?,?,?,?,'PHYSICIAN_REFERRAL',?)")
                ->execute([$uuid,$ref['group_id'],$ref['location_id'],$ref['patient_id'],$ref['referral_id']]);
            $serviceId=(int)$this->pdo->lastInsertId();
            $this->pdo->prepare('INSERT INTO healthcare_provider_service_order_items
                (service_order_id,referral_id,source_order_item_id,study_type_id)
                SELECT ?,referral_id,source_order_item_id,study_type_id FROM healthcare_study_referral_items WHERE referral_id=?')
                ->execute([$serviceId,$ref['referral_id']]);
            $this->pdo->prepare("UPDATE healthcare_study_referrals SET state='ACCEPTED' WHERE referral_id=? AND state='SENT'")
                ->execute([$ref['referral_id']]);
            $this->event((int)$ref['referral_id'],'ACCEPTED','PROVIDER_SYSTEM',$actorId,null);
            return ['service_order_id'=>$serviceId,'service_order_uuid'=>$uuid,'replayed'=>false];
        });
    }

    public function closeReferral(string $referralUuid,string $state,string $actorType,string $actorId,?string $reason): void
    {
        if (!in_array($state,['DECLINED','CANCELED'],true) || !in_array($actorType,['PHYSICIAN','PROVIDER_SYSTEM','INTERNAL'],true))
            throw new InvalidArgumentException('REFERRAL_TRANSITION_INVALID');
        $referralUuid=self::uuid($referralUuid);$actorId=self::required($actorId,128,'ACTOR_REQUIRED');
        if ($reason!==null && strlen($reason)>500) throw new InvalidArgumentException('REFERRAL_REASON_INVALID');
        $this->tx(function () use ($referralUuid,$state,$actorType,$actorId,$reason): void {
            $ref=$this->one('SELECT referral_id,state FROM healthcare_study_referrals WHERE referral_uuid=? FOR UPDATE',[$referralUuid]);
            if ($ref===null || $ref['state']!=='SENT') throw new RuntimeException('REFERRAL_TRANSITION_DENIED');
            $this->pdo->prepare('UPDATE healthcare_study_referrals SET state=? WHERE referral_id=?')->execute([$state,$ref['referral_id']]);
            $this->event((int)$ref['referral_id'],$state,$actorType,$actorId,$reason);
        });
    }

    /** Only a future release authority may attest; there is deliberately no HTTP release endpoint. */
    public function publishReleasedResult(array $release,ProviderReleasedResultAuthority $authority,
        ClinicalPrivateBinaryStorage $storage): array
    {
        $authority->assertReleased($release);
        $uuid=self::uuid((string)($release['provider_release_uuid']??''));
        $serviceUuid=self::uuid((string)($release['service_order_uuid']??''));
        $referralUuid=self::uuid((string)($release['referral_uuid']??''));
        $sourceOrderUuid=self::uuid((string)($release['source_order_document_uuid']??''));
        $patientId=self::required((string)($release['patient_id']??''),128,'PROVIDER_PATIENT_REQUIRED');
        $groupId=self::required((string)($release['group_id']??''),64,'PROVIDER_GROUP_REQUIRED');
        $locationId=(int)($release['location_id']??0);
        if ($locationId<1) throw new InvalidArgumentException('PROVIDER_LOCATION_REQUIRED');
        $type=(string)($release['document_type']??'');
        if (!clinical_study_result_type($type)) throw new InvalidArgumentException('PROVIDER_RESULT_TYPE_INVALID');
        $title=self::required((string)($release['title']??''),128,'PROVIDER_RESULT_TITLE_INVALID');
        $at=(string)($release['released_at']??'');
        if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D',$at) || strtotime($at.' UTC')===false)
            throw new InvalidArgumentException('PROVIDER_RELEASE_TIME_INVALID');
        $professional=trim((string)($release['released_by_professional_id']??''));
        if (strlen($professional)>128) throw new InvalidArgumentException('PROVIDER_PROFESSIONAL_INVALID');
        $items=$release['related_order_item_ids']??null;
        if (!is_array($items) || !array_is_list($items) || $items===[] || count($items)>100)
            throw new InvalidArgumentException('PROVIDER_RESULT_ITEMS_INVALID');
        $ids=[];
        foreach ($items as $id) {
            if (!is_string($id)) throw new InvalidArgumentException('PROVIDER_RESULT_ITEMS_INVALID');
            $id=self::uuid($id);
            if (isset($ids[$id])) throw new InvalidArgumentException('PROVIDER_RESULT_ITEMS_DUPLICATE');
            $ids[$id]=true;
        }
        $ids=array_keys($ids);sort($ids,SORT_STRING);
        $artifact=$release['artifact']??null;
        if (!is_array($artifact) || !str_starts_with((string)($artifact['storage_key']??''),'clinical/')
            || !preg_match('/^[a-f0-9]{64}$/D',(string)($artifact['sha256']??''))
            || !is_int($artifact['byte_length']??null) || $artifact['byte_length']<1
            || !in_array($artifact['mime_type']??null,['application/pdf','image/jpeg','image/png','image/webp'],true))
            throw new InvalidArgumentException('PROVIDER_ARTIFACT_INVALID');
        $stat=$storage->stat((string)$artifact['storage_key']);
        if (!($stat['exists']??false) || !hash_equals($artifact['sha256'],$stat['sha256'])
            || $artifact['byte_length']!==$stat['byte_length']) throw new RuntimeException('PROVIDER_ARTIFACT_INTEGRITY');
        $hash=self::digest([$serviceUuid,$referralUuid,$sourceOrderUuid,$patientId,$groupId,$locationId,
            $type,$title,$at,$professional,$ids,$artifact['sha256'],
            $artifact['byte_length'],$artifact['mime_type']]);
        try {
            return $this->tx(function () use ($uuid,$serviceUuid,$referralUuid,$sourceOrderUuid,$patientId,$groupId,
            $locationId,$type,$title,$at,$professional,$ids,$artifact,$hash): array {
            $existing=$this->one('SELECT clinical_result_document_id,release_sha256 FROM healthcare_provider_result_sources
                WHERE provider_release_uuid=?',[$uuid]);
            if ($existing!==null) {
                if (!hash_equals($existing['release_sha256'],$hash)) throw new RuntimeException('PROVIDER_RELEASE_CONFLICT');
                return ['document_id'=>(int)$existing['clinical_result_document_id'],'replayed'=>true];
            }
            $service=$this->one('SELECT so.*,r.source_order_document_id,r.source_order_uuid,r.source_order_version,
                r.doctor_id,r.referral_uuid,r.state AS referral_state FROM healthcare_provider_service_orders so
                JOIN healthcare_study_referrals r ON r.referral_id=so.referral_id
                WHERE so.service_order_uuid=? FOR UPDATE',[$serviceUuid]);
            if ($service===null || $service['origin']!=='PHYSICIAN_REFERRAL' || $service['referral_state']!=='ACCEPTED'
                || $service['state']==='CANCELED') throw new RuntimeException('PROVIDER_SERVICE_ORDER_NOT_PUBLISHABLE');
            if ($service['referral_uuid']!==$referralUuid || $service['source_order_uuid']!==$sourceOrderUuid
                || $service['patient_id']!==$patientId || $service['group_id']!==$groupId
                || (int)$service['location_id']!==$locationId) throw new RuntimeException('PROVIDER_RELEASE_SCOPE_DENIED');
            $marks=implode(',',array_fill(0,count($ids),'?'));
            $stmt=$this->pdo->prepare('SELECT source_order_item_id FROM healthcare_provider_service_order_items
                WHERE service_order_id=? AND referral_id=? AND source_order_item_id IN ('.$marks.')');
            $stmt->execute(array_merge([(int)$service['service_order_id'],(int)$service['referral_id']],$ids));
            if (count($stmt->fetchAll(PDO::FETCH_COLUMN))!==count($ids)) throw new RuntimeException('PROVIDER_RESULT_ITEM_SCOPE_DENIED');
            $order=$this->one('SELECT id,document_uuid,version,patient_id,encounter_id,encounter_ref_id,
                appointment_id,hospital_stay_id,care_setting,document_type FROM clinical_documents WHERE id=?',
                [$service['source_order_document_id']]);
            if ($order===null || !clinical_study_order_type((string)$order['document_type'])
                || $order['document_uuid']!==$service['source_order_uuid']
                || (int)$order['version']!==(int)$service['source_order_version']
                || $order['patient_id']!==$service['patient_id']) throw new RuntimeException('PROVIDER_SOURCE_ORDER_MISMATCH');
            $payload=clinical_study_validate_result_payload($this->pdo,$type,[
                'source'=>'provider_released_result','result_origin'=>'provider_release',
                'related_order_document_id'=>(string)$order['id'],
                'related_order_document_uuid'=>$order['document_uuid'],
                'related_order_item_ids'=>$ids,'provider_release_uuid'=>$uuid,
            ],(string)$order['patient_id'],(string)($order['encounter_ref_id']??$order['encounter_id']??''));
            $encounterId=(int)($order['encounter_ref_id']??0);
            $doc=mxmed_build_clinical_document(['type'=>$type,'title'=>$title,'summary'=>'',
                'event_datetime'=>$at,'context'=>['patient_id'=>$order['patient_id'],
                    'encounter_id'=>$encounterId>0?(string)$encounterId:($order['encounter_id']?:null),
                    'appointment_id'=>$order['appointment_id'],'hospital_stay_id'=>$order['hospital_stay_id'],
                    'care_setting'=>$order['care_setting']],
                'payload'=>$payload,'actor'=>['user_id'=>'provider_interop_service']]);
            $doc['participants']=[];
            $doc['timestamps']['created_at']=$at;$doc['timestamps']['generated_at']=$at;
            $doc['audit']['created_by_user_id']='provider_interop_service';
            $documentId=mxmed_persist_clinical_document_in_transaction($this->pdo,$doc,
                $encounterId>0?['encounter_ref_id'=>$encounterId]:[]);
            (new ClinicalMultipartBinaryManifestRepository($this->pdo))->insertOriginal($documentId,mxmed_uuidv4(),
                $artifact['storage_key'],$artifact);
            $this->pdo->prepare('INSERT INTO healthcare_provider_result_sources
                (provider_release_uuid,release_sha256,clinical_result_document_id,service_order_id,referral_id,
                 group_id,location_id,patient_id,released_at,released_by_professional_id)
                VALUES (?,?,?,?,?,?,?,?,?,?)')->execute([$uuid,$hash,$documentId,$service['service_order_id'],
                    $service['referral_id'],$service['group_id'],$service['location_id'],$service['patient_id'],$at,
                    $professional===''?null:$professional]);
            return ['document_id'=>$documentId,'document_uuid'=>$doc['document_id'],'replayed'=>false];
            });
        } catch (PDOException $e) {
            if ($e->getCode()!=='23000') throw $e;
            $existing=$this->one('SELECT clinical_result_document_id,release_sha256 FROM healthcare_provider_result_sources
                WHERE provider_release_uuid=?',[$uuid]);
            if ($existing===null) throw $e;
            if (!hash_equals($existing['release_sha256'],$hash)) throw new RuntimeException('PROVIDER_RELEASE_CONFLICT',0,$e);
            return ['document_id'=>(int)$existing['clinical_result_document_id'],'replayed'=>true];
        }
    }
}
