<?php
declare(strict_types=1);

require_once __DIR__ . '/clinical_idempotency.php';

final class ClinicalTreatmentException extends RuntimeException
{
    public function __construct(public readonly string $codeName, public readonly int $httpStatus = 400)
    {
        parent::__construct($codeName);
    }
}

/** Canonical TRT04 writers. The caller supplies only a server-authenticated account and doctor scope. */
final class ClinicalTreatments
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $doctorId,
        private readonly string $patientId,
        private readonly string $actorAccountId
    ) {
        if ($doctorId === '' || $patientId === '' || $actorAccountId === '') {
            throw new ClinicalTreatmentException('AUTHENTICATION_REQUIRED', 401);
        }
        $this->assertScope();
    }

    private function one(string $sql, array $params = []): ?array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function execute(string $sql, array $params = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    private function assertScope(): void
    {
        $row = $this->one("SELECT a.account_id FROM auth_accounts a
            JOIN auth_account_memberships m ON m.account_id=a.account_id
            JOIN patients_doctor_links l ON l.doctor_id=m.profile_doctor_id
            WHERE a.account_id=? AND a.status='active' AND m.status='active'
              AND m.profile_doctor_id=? AND l.patient_id=? AND l.status='active' LIMIT 1",
            [$this->actorAccountId, $this->doctorId, $this->patientId]);
        if ($row === null) throw new ClinicalTreatmentException('DOCTOR_PATIENT_SCOPE_DENIED', 403);
    }

    private function authorizedIdentity(string $accountId, string $capability): array
    {
        if ($accountId === '') throw new ClinicalTreatmentException('CLINICAL_GRANT_REQUIRED', 403);
        $row = $this->one("SELECT a.account_id,a.email_address,p.display_name,g.clinical_role_label
            FROM auth_accounts a
            JOIN auth_account_memberships m ON m.account_id=a.account_id AND m.profile_doctor_id=? AND m.status='active'
            JOIN clinical_performer_authorizations g ON g.account_id=a.account_id AND g.doctor_id=m.profile_doctor_id
              AND g.capability=? AND g.status='ACTIVE' AND g.revoked_at IS NULL
            LEFT JOIN profiles_doctors p ON p.doctor_id=m.profile_doctor_id AND m.role_code='owner'
            WHERE a.account_id=? AND a.status='active' LIMIT 1 FOR UPDATE",
            [$this->doctorId, $capability, $accountId]);
        if ($row === null) throw new ClinicalTreatmentException('CLINICAL_GRANT_REQUIRED', 403);
        $name = trim((string)($row['display_name'] ?? ''));
        if ($name === '') $name = trim((string)$row['email_address']);
        if ($name === '') throw new ClinicalTreatmentException('IDENTITY_SNAPSHOT_UNAVAILABLE', 409);
        return ['account_id'=>$accountId, 'name'=>$name, 'role'=>$row['clinical_role_label']];
    }

    private function requireText(mixed $value, string $field, int $max = 255): string
    {
        if (!is_string($value)) throw new ClinicalTreatmentException('INVALID_' . strtoupper($field));
        $text = trim($value);
        if ($text === '' || mb_strlen($text) > $max) throw new ClinicalTreatmentException('INVALID_' . strtoupper($field));
        return $text;
    }

    private function optionalText(mixed $value, int $max): ?string
    {
        if ($value === null || $value === '') return null;
        if (!is_string($value) || mb_strlen(trim($value)) > $max) throw new ClinicalTreatmentException('INVALID_TEXT');
        return trim($value);
    }

    private function optionalId(mixed $value): ?string
    {
        if ($value === null || $value === '') return null;
        if (!is_string($value) || strlen($value) > 64 || trim($value) === '') throw new ClinicalTreatmentException('INVALID_REFERENCE');
        return trim($value);
    }

    private function optionalNumericId(mixed $value): ?int
    {
        if ($value === null || $value === '') return null;
        if (!ctype_digit((string)$value) || (int)$value < 1) throw new ClinicalTreatmentException('INVALID_REFERENCE');
        return (int)$value;
    }

    private function assertRelated(?int $planId, ?int $encounterId, ?string $appointmentId, ?string $consultorioId, bool $historicalCorrection = false): void
    {
        if ($planId !== null) {
            $plan = $this->one('SELECT status FROM clinical_treatment_plans WHERE plan_id=? AND doctor_id=? AND patient_id=?', [$planId,$this->doctorId,$this->patientId]);
            if ($plan === null || (!$historicalCorrection && !in_array($plan['status'], ['ACTIVE','PAUSED'], true))) throw new ClinicalTreatmentException('TREATMENT_PLAN_SCOPE_OR_STATE_INVALID', 409);
        }
        if ($encounterId !== null && $this->one('SELECT encounter_id FROM clinical_encounters WHERE encounter_id=? AND doctor_id=? AND patient_id=?', [$encounterId,$this->doctorId,$this->patientId]) === null) {
            throw new ClinicalTreatmentException('ENCOUNTER_SCOPE_INVALID', 409);
        }
        if ($appointmentId !== null && $this->one('SELECT appointment_id FROM agenda_appointments WHERE appointment_id=? AND doctor_id=? AND patient_id=?', [$appointmentId,$this->doctorId,$this->patientId]) === null) {
            throw new ClinicalTreatmentException('APPOINTMENT_SCOPE_INVALID', 409);
        }
        if ($consultorioId !== null && $this->one('SELECT consultorio_id FROM consultorios WHERE doctor_id=? AND consultorio_id=?', [$this->doctorId,$consultorioId]) === null) {
            throw new ClinicalTreatmentException('CONSULTORIO_SCOPE_INVALID', 409);
        }
    }

    private function normalizedItems(mixed $items, bool $required): string
    {
        if (!is_array($items) || !array_is_list($items) || count($items) > 100 || ($required && count($items) === 0)) {
            throw new ClinicalTreatmentException('INVALID_PROCEDURE_ITEMS');
        }
        $clean = [];
        foreach ($items as $index => $item) {
            if (!is_array($item) || array_is_list($item) || ($item['sequence'] ?? null) !== $index + 1) {
                throw new ClinicalTreatmentException('INVALID_PROCEDURE_SEQUENCE');
            }
            $key = $this->requireText($item['type_key'] ?? null, 'type_key', 64);
            if (!preg_match('/^[a-z][a-z0-9_]{0,63}$/', $key)) throw new ClinicalTreatmentException('INVALID_TYPE_KEY');
            $clean[] = [
                'sequence'=>$index + 1,
                'type_key'=>$key,
                'title'=>$this->requireText($item['title'] ?? null, 'procedure_title', 190),
                'note'=>$this->optionalText($item['note'] ?? null, 2000),
            ];
        }
        return json_encode($clean, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /** Resolve even a repeated wall clock hour by checking the supplied offset against the IANA zone. */
    private function performedInstant(array $input): array
    {
        $local = $this->requireText($input['performed_local'] ?? null, 'performed_local', 19);
        $zoneName = $this->requireText($input['performed_timezone'] ?? null, 'performed_timezone', 100);
        $offset = $input['performed_utc_offset_minutes'] ?? null;
        if (!preg_match('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/', $local) || !is_int($offset) || $offset < -840 || $offset > 840) {
            throw new ClinicalTreatmentException('INVALID_PERFORMED_TIME');
        }
        try { $zone = new DateTimeZone($zoneName); }
        catch (Throwable) { throw new ClinicalTreatmentException('INVALID_PERFORMED_TIMEZONE'); }
        if (!in_array($zoneName, DateTimeZone::listIdentifiers(), true)) throw new ClinicalTreatmentException('INVALID_PERFORMED_TIMEZONE');
        $sign = $offset < 0 ? '-' : '+';
        $abs = abs($offset);
        $fixed = sprintf('%s%02d:%02d', $sign, intdiv($abs,60), $abs % 60);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s P', $local . ' ' . $fixed);
        if (!$date || $date->format('Y-m-d H:i:s') !== $local || $date->setTimezone($zone)->format('Y-m-d H:i:s') !== $local || intdiv($zone->getOffset($date),60) !== $offset) {
            throw new ClinicalTreatmentException('INVALID_PERFORMED_TIME');
        }
        return [$date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'), $zoneName, $offset];
    }

    private function row(array $row): array
    {
        if (isset($row['procedure_items']) && is_string($row['procedure_items'])) $row['procedure_items'] = json_decode($row['procedure_items'], true, 512, JSON_THROW_ON_ERROR);
        return $row;
    }

    private function plan(int $id, bool $lock = false): array
    {
        $row = $this->one('SELECT * FROM clinical_treatment_plans WHERE plan_id=? AND doctor_id=? AND patient_id=?' . ($lock ? ' FOR UPDATE' : ''), [$id,$this->doctorId,$this->patientId]);
        if ($row === null) throw new ClinicalTreatmentException('PLAN_NOT_FOUND',404);
        return $row;
    }

    private function session(int $id, bool $lock = false): array
    {
        $row = $this->one('SELECT * FROM clinical_treatment_sessions WHERE session_id=? AND doctor_id=? AND patient_id=?' . ($lock ? ' FOR UPDATE' : ''), [$id,$this->doctorId,$this->patientId]);
        if ($row === null) throw new ClinicalTreatmentException('SESSION_NOT_FOUND',404);
        return $row;
    }

    private function requireVersion(array $row, int $expected): void
    {
        if ($expected < 1 || (int)$row['row_version'] !== $expected) throw new ClinicalTreatmentException('ROW_VERSION_CONFLICT',409);
    }

    /** Idempotency claim and clinical mutation use one transaction; replay returns the original snapshot. */
    private function command(string $operation, string $key, array $semantic, callable $write): array
    {
        $operation = clinical_idempotency_operation_validate($operation);
        $key = clinical_idempotency_key_validate($key);
        $hash = clinical_idempotency_request_hash(['actor_account_id'=>$this->actorAccountId,'request'=>$semantic]);
        $this->pdo->beginTransaction();
        try {
            try {
                $this->execute('INSERT INTO clinical_idempotency_requests (operation_type,doctor_id,context_type,context_id,idempotency_key,canonicalization_version,request_hash,actor_user_id,created_at) VALUES (?,?,?,?,?,1,?,?,UTC_TIMESTAMP())',
                    [$operation,$this->doctorId,'PATIENT',$this->patientId,$key,$hash,$this->actorAccountId]);
                $requestId = (int)$this->pdo->lastInsertId();
            } catch (PDOException $e) {
                if ($this->pdo->inTransaction()) $this->pdo->rollBack();
                if ((string)$e->getCode() !== '23000') throw $e;
                $prior = $this->one('SELECT request_hash,committed_at,treatment_result_json FROM clinical_idempotency_requests WHERE operation_type=? AND doctor_id=? AND context_type=? AND context_id=? AND idempotency_key=?',
                    [$operation,$this->doctorId,'PATIENT',$this->patientId,$key]);
                if ($prior === null || !hash_equals((string)$prior['request_hash'],$hash)) throw new ClinicalTreatmentException('IDEMPOTENCY_KEY_REUSED',409);
                if ($prior['committed_at'] === null || $prior['treatment_result_json'] === null) throw new ClinicalTreatmentException('IDEMPOTENCY_RESULT_NOT_READY',409);
                $result = json_decode((string)$prior['treatment_result_json'],true,512,JSON_THROW_ON_ERROR);
                return ['record'=>$result,'idempotency_replay'=>true];
            }
            [$record,$resultColumn,$resultId] = $write($requestId);
            if (!in_array($resultColumn,['treatment_plan_id','treatment_plan_event_id','treatment_session_id'],true) || $resultId < 1) throw new LogicException('INVALID_TREATMENT_RESULT');
            $json = json_encode($record,JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            $this->execute('UPDATE clinical_idempotency_requests SET ' . $resultColumn . '=?,treatment_result_json=?,committed_at=UTC_TIMESTAMP() WHERE request_id=? AND committed_at IS NULL', [$resultId,$json,$requestId]);
            $this->pdo->commit();
            return ['record'=>$record,'idempotency_replay'=>false];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    private function uuid(): string
    {
        $h = bin2hex(random_bytes(16));
        return substr($h,0,8).'-'.substr($h,8,4).'-4'.substr($h,13,3).'-'.dechex((hexdec($h[16]) & 3) | 8).substr($h,17,3).'-'.substr($h,20,12);
    }

    public function createPlan(array $input, string $key): array
    {
        return $this->command('CREATE_TREATMENT_PLAN',$key,$input,function (int $requestId) use ($input): array {
            $providerId = $this->requireText($input['responsible_provider_account_id'] ?? null,'responsible_provider_account_id',64);
            $provider = $this->authorizedIdentity($providerId,'TREATMENT_RESPONSIBLE_PROVIDER');
            $encounterId = $this->optionalNumericId($input['source_encounter_id'] ?? null);
            $this->assertRelated(null,$encounterId,null,null);
            $title = $this->requireText($input['title'] ?? null,'title');
            $planned = $input['planned_start_date'] ?? null;
            if ($planned !== null && (!is_string($planned) || !preg_match('/^\d{4}-\d\d-\d\d$/',$planned) || !checkdate((int)substr($planned,5,2),(int)substr($planned,8,2),(int)substr($planned,0,4)))) throw new ClinicalTreatmentException('INVALID_PLANNED_START_DATE');
            $this->execute("INSERT INTO clinical_treatment_plans (plan_uuid,doctor_id,patient_id,responsible_provider_account_id,responsible_provider_name_snapshot,responsible_provider_role_snapshot,title,treatment_type,goals,source_encounter_id,status,planned_start_date,created_at,updated_at,created_by_account_id,updated_by_account_id,row_version) VALUES (?,?,?,?,?,?,?,?,?,?,'PLANNED',?,UTC_TIMESTAMP(),UTC_TIMESTAMP(),?,?,1)",
                [$this->uuid(),$this->doctorId,$this->patientId,$providerId,$provider['name'],$provider['role'],$title,$this->optionalText($input['treatment_type'] ?? null,100),$this->optionalText($input['goals'] ?? null,5000),$encounterId,$planned,$this->actorAccountId,$this->actorAccountId]);
            $id = (int)$this->pdo->lastInsertId();
            $this->execute("INSERT INTO clinical_treatment_plan_audit_events (plan_id,doctor_id,patient_id,actor_account_id,operation,previous_status,new_status,plan_row_version,idempotency_request_id,occurred_at) VALUES (?,?,?,?,'CREATE',NULL,'PLANNED',1,?,UTC_TIMESTAMP())",
                [$id,$this->doctorId,$this->patientId,$this->actorAccountId,$requestId]);
            return [$this->plan($id),'treatment_plan_id',$id];
        });
    }

    public function transitionPlan(int $id, string $target, int $expected, ?string $reason, string $key): array
    {
        $target = strtoupper($target);
        return $this->command('TRANSITION_TREATMENT_PLAN',$key,['plan_id'=>$id,'target'=>$target,'expected_version'=>$expected,'reason'=>$reason],function (int $requestId) use ($id,$target,$expected,$reason): array {
            $plan = $this->plan($id,true);
            $this->requireVersion($plan,$expected);
            $allowed = ['PLANNED'=>['ACTIVE'=>'ACTIVATE','CANCELED'=>'CANCEL'], 'ACTIVE'=>['PAUSED'=>'PAUSE','COMPLETED'=>'COMPLETE','CANCELED'=>'CANCEL'], 'PAUSED'=>['ACTIVE'=>'REACTIVATE','COMPLETED'=>'COMPLETE','CANCELED'=>'CANCEL']];
            $operation = $allowed[$plan['status']][$target] ?? null;
            if ($operation === null) throw new ClinicalTreatmentException('INVALID_PLAN_TRANSITION',409);
            $reason = $this->optionalText($reason,1000);
            $stamp = match($target) {'ACTIVE'=>'started_at','COMPLETED'=>'completed_at','CANCELED'=>'canceled_at',default=>null};
            $stampSql = $stamp === null ? '' : ','.$stamp.'=COALESCE('.$stamp.',UTC_TIMESTAMP())';
            $affected = $this->execute('UPDATE clinical_treatment_plans SET status=?,updated_by_account_id=?,updated_at=UTC_TIMESTAMP(),row_version=row_version+1'.$stampSql.' WHERE plan_id=? AND doctor_id=? AND patient_id=? AND row_version=? AND status=?',
                [$target,$this->actorAccountId,$id,$this->doctorId,$this->patientId,$expected,$plan['status']]);
            if ($affected !== 1) throw new ClinicalTreatmentException('ROW_VERSION_CONFLICT',409);
            $this->execute('INSERT INTO clinical_treatment_plan_audit_events (plan_id,doctor_id,patient_id,actor_account_id,operation,previous_status,new_status,plan_row_version,reason,idempotency_request_id,occurred_at) VALUES (?,?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP())',
                [$id,$this->doctorId,$this->patientId,$this->actorAccountId,$operation,$plan['status'],$target,$expected+1,$reason,$requestId]);
            $eventId = (int)$this->pdo->lastInsertId();
            return [$this->plan($id),'treatment_plan_event_id',$eventId];
        });
    }

    private function sessionFields(array $input, ?array $previous, bool $completed, bool $historicalCorrection = false): array
    {
        $scope = strtoupper((string)($input['encounter_scope'] ?? $previous['encounter_scope'] ?? ''));
        $encounterId = $this->optionalNumericId($input['encounter_ref_id'] ?? $previous['encounter_ref_id'] ?? null);
        if (!in_array($scope,['ENCOUNTER','STANDALONE'],true) || ($scope==='ENCOUNTER') !== ($encounterId!==null)) throw new ClinicalTreatmentException('INVALID_ENCOUNTER_SCOPE');
        $planId = $this->optionalNumericId($input['treatment_plan_id'] ?? $previous['treatment_plan_id'] ?? null);
        $appointmentId = $this->optionalId($input['appointment_id'] ?? $previous['appointment_id'] ?? null);
        $consultorioId = $this->optionalId($input['consultorio_id'] ?? $previous['consultorio_id'] ?? null);
        $this->assertRelated($planId,$encounterId,$appointmentId,$consultorioId,$historicalCorrection);
        $performedBy = $this->requireText($input['performed_by_account_id'] ?? $previous['performed_by_account_id'] ?? null,'performed_by_account_id',64);
        $identity = $historicalCorrection && $previous !== null && $performedBy === $previous['performed_by_account_id']
            ? ['name'=>$previous['performed_by_name_snapshot'],'role'=>$previous['performed_by_role_snapshot']]
            : $this->authorizedIdentity($performedBy,'TREATMENT_PERFORMER');
        $items = $input['procedure_items'] ?? (isset($previous['procedure_items']) ? json_decode((string)$previous['procedure_items'],true,512,JSON_THROW_ON_ERROR) : []);
        $itemsJson = $this->normalizedItems($items,$completed);
        $instant = [$previous['performed_at'] ?? null,$previous['performed_timezone'] ?? null,$previous['performed_utc_offset_minutes'] ?? null];
        $timeKeys = array_intersect(['performed_local','performed_timezone','performed_utc_offset_minutes'],array_keys($input));
        if ($timeKeys !== []) {
            if (count($timeKeys)!==3) throw new ClinicalTreatmentException('INCOMPLETE_PERFORMED_TIME');
            $instant = $this->performedInstant($input);
        }
        if ($completed && ($instant[0] === null || $instant[1] === null || $instant[2] === null)) throw new ClinicalTreatmentException('PERFORMED_TIME_REQUIRED');
        return [
            'performed_by_account_id'=>$performedBy,
            'performed_by_name_snapshot'=>$identity['name'],
            'performed_by_role_snapshot'=>$identity['role'],
            'treatment_plan_id'=>$planId,
            'encounter_ref_id'=>$encounterId,
            'encounter_scope'=>$scope,
            'appointment_id'=>$appointmentId,
            'consultorio_id'=>$consultorioId,
            'place_label_snapshot'=>$this->optionalText(array_key_exists('place_label_snapshot',$input) ? $input['place_label_snapshot'] : ($previous['place_label_snapshot'] ?? null),190),
            'performed_at'=>$instant[0],
            'performed_timezone'=>$instant[1],
            'performed_utc_offset_minutes'=>$instant[2],
            'title'=>$this->requireText($input['title'] ?? $previous['title'] ?? null,'title'),
            'procedure_items'=>$itemsJson,
            'note'=>$this->optionalText(array_key_exists('note',$input) ? $input['note'] : ($previous['note'] ?? null),10000),
            'outcome'=>$this->optionalText(array_key_exists('outcome',$input) ? $input['outcome'] : ($previous['outcome'] ?? null),10000),
            'complications'=>$this->optionalText(array_key_exists('complications',$input) ? $input['complications'] : ($previous['complications'] ?? null),10000),
        ];
    }

    private function insertSession(array $fields, string $uuid, int $version, ?int $root, ?int $replaces, string $status, ?string $correctionReason): int
    {
        $columns = ['session_uuid','version_number','lineage_root_id','replaces_session_id','doctor_id','patient_id'];
        $values = [$uuid,$version,$root,$replaces,$this->doctorId,$this->patientId];
        foreach ($fields as $column=>$value) { $columns[]=$column; $values[]=$value; }
        $columns = array_merge($columns,['status','created_by_account_id','updated_by_account_id','correction_reason','correction_actor_account_id','correction_at','completed_at']);
        array_push($values,$status,$this->actorAccountId,$this->actorAccountId,$correctionReason,$correctionReason===null?null:$this->actorAccountId,$correctionReason===null?null:gmdate('Y-m-d H:i:s'),$status==='COMPLETED'?gmdate('Y-m-d H:i:s'):null);
        $quoted = array_map(static fn($c)=>'`'.$c.'`',$columns);
        $this->execute('INSERT INTO clinical_treatment_sessions ('.implode(',',$quoted).',created_at,updated_at,row_version) VALUES ('.implode(',',array_fill(0,count($values),'?')).',UTC_TIMESTAMP(),UTC_TIMESTAMP(),1)',$values);
        return (int)$this->pdo->lastInsertId();
    }

    public function createDraft(array $input, string $key): array
    {
        return $this->command('CREATE_TREATMENT_SESSION',$key,$input,function () use ($input): array {
            $fields=$this->sessionFields($input,null,false);
            $id=$this->insertSession($fields,$this->uuid(),1,null,null,'DRAFT',null);
            $this->execute('UPDATE clinical_treatment_sessions SET lineage_root_id=? WHERE session_id=?',[$id,$id]);
            return [$this->row($this->session($id)),'treatment_session_id',$id];
        });
    }

    public function editDraft(int $id, int $expected, array $input): array
    {
        $this->pdo->beginTransaction();
        try {
            $current=$this->session($id,true);
            $this->requireVersion($current,$expected);
            if ($current['status']!=='DRAFT' || $this->hasSuccessor($id)) throw new ClinicalTreatmentException('SESSION_NOT_EDITABLE',409);
            $fields=$this->sessionFields($input,$current,false);
            $assign=[];$values=[];
            foreach ($fields as $column=>$value) { $assign[]='`'.$column.'`=?'; $values[]=$value; }
            array_push($values,$this->actorAccountId,$id,$this->doctorId,$this->patientId,$expected);
            $affected=$this->execute('UPDATE clinical_treatment_sessions SET '.implode(',',$assign).',updated_by_account_id=?,updated_at=UTC_TIMESTAMP(),row_version=row_version+1 WHERE session_id=? AND doctor_id=? AND patient_id=? AND row_version=? AND status="DRAFT"',$values);
            if ($affected!==1) throw new ClinicalTreatmentException('ROW_VERSION_CONFLICT',409);
            $row=$this->row($this->session($id));$this->pdo->commit();return $row;
        } catch (Throwable $e) { if ($this->pdo->inTransaction()) $this->pdo->rollBack(); throw $e; }
    }

    private function hasSuccessor(int $id): bool
    {
        return $this->one('SELECT session_id FROM clinical_treatment_sessions WHERE replaces_session_id=? LIMIT 1',[$id]) !== null;
    }

    public function complete(int $id, int $expected, array $input, string $key): array
    {
        return $this->command('COMPLETE_TREATMENT_SESSION',$key,['session_id'=>$id,'expected_version'=>$expected,'payload'=>$input],function () use ($id,$expected,$input): array {
            $current=$this->session($id,true);$this->requireVersion($current,$expected);
            if ($current['status']!=='DRAFT' || $this->hasSuccessor($id)) throw new ClinicalTreatmentException('SESSION_NOT_COMPLETABLE',409);
            $fields=$this->sessionFields($input,$current,true);
            $assign=[];$values=[];foreach($fields as $column=>$value){$assign[]='`'.$column.'`=?';$values[]=$value;}
            array_push($values,$this->actorAccountId,$id,$this->doctorId,$this->patientId,$expected);
            $affected=$this->execute('UPDATE clinical_treatment_sessions SET '.implode(',',$assign).',status="COMPLETED",completed_at=UTC_TIMESTAMP(),updated_by_account_id=?,updated_at=UTC_TIMESTAMP(),row_version=row_version+1 WHERE session_id=? AND doctor_id=? AND patient_id=? AND row_version=? AND status="DRAFT"',$values);
            if($affected!==1)throw new ClinicalTreatmentException('ROW_VERSION_CONFLICT',409);
            return [$this->row($this->session($id)),'treatment_session_id',$id];
        });
    }

    public function void(int $id, int $expected, string $reason, string $key): array
    {
        return $this->command('VOID_TREATMENT_SESSION',$key,['session_id'=>$id,'expected_version'=>$expected,'reason'=>$reason],function () use ($id,$expected,$reason): array {
            $current=$this->session($id,true);$this->requireVersion($current,$expected);
            if (!in_array($current['status'],['DRAFT','COMPLETED'],true) || $this->hasSuccessor($id)) throw new ClinicalTreatmentException('SESSION_NOT_VOIDABLE',409);
            $reason=$this->requireText($reason,'void_reason',1000);
            $affected=$this->execute('UPDATE clinical_treatment_sessions SET status="VOIDED",void_reason=?,voided_by_account_id=?,voided_at=UTC_TIMESTAMP(),updated_by_account_id=?,updated_at=UTC_TIMESTAMP(),row_version=row_version+1 WHERE session_id=? AND doctor_id=? AND patient_id=? AND row_version=? AND status=?',
                [$reason,$this->actorAccountId,$this->actorAccountId,$id,$this->doctorId,$this->patientId,$expected,$current['status']]);
            if($affected!==1)throw new ClinicalTreatmentException('ROW_VERSION_CONFLICT',409);
            return [$this->row($this->session($id)),'treatment_session_id',$id];
        });
    }

    public function correct(int $id, int $expected, array $input, string $key): array
    {
        return $this->command('CORRECT_TREATMENT_SESSION',$key,['session_id'=>$id,'expected_version'=>$expected,'payload'=>$input],function () use ($id,$expected,$input): array {
            $current=$this->session($id,true);$this->requireVersion($current,$expected);
            if($current['status']!=='COMPLETED' || $this->hasSuccessor($id))throw new ClinicalTreatmentException('SESSION_NOT_CORRECTABLE',409);
            $reason=$this->requireText($input['correction_reason'] ?? null,'correction_reason',1000);
            $fields=$this->sessionFields($input,$current,true,true);
            $new=$this->insertSession($fields,(string)$current['session_uuid'],(int)$current['version_number']+1,(int)($current['lineage_root_id'] ?: $id),$id,'COMPLETED',$reason);
            return [$this->row($this->session($new)),'treatment_session_id',$new];
        });
    }

    public function listPlans(): array
    {
        $stmt=$this->pdo->prepare('SELECT * FROM clinical_treatment_plans WHERE doctor_id=? AND patient_id=? ORDER BY created_at DESC,plan_id DESC');
        $stmt->execute([$this->doctorId,$this->patientId]);return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function getPlan(int $id): array { return $this->plan($id); }
    public function listSessions(): array
    {
        $stmt=$this->pdo->prepare('SELECT s.* FROM clinical_treatment_sessions s WHERE s.doctor_id=? AND s.patient_id=? AND NOT EXISTS (SELECT 1 FROM clinical_treatment_sessions next WHERE next.replaces_session_id=s.session_id) ORDER BY s.performed_at DESC,s.session_id DESC');
        $stmt->execute([$this->doctorId,$this->patientId]);return array_map($this->row(...),$stmt->fetchAll(PDO::FETCH_ASSOC));
    }
    public function getSession(int $id): array { return $this->row($this->session($id)); }
    public function standaloneHistory(): array
    {
        $stmt=$this->pdo->prepare("SELECT s.* FROM clinical_treatment_sessions s WHERE s.doctor_id=? AND s.patient_id=? AND s.status='COMPLETED' AND s.encounter_scope='STANDALONE' AND s.encounter_ref_id IS NULL AND NOT EXISTS (SELECT 1 FROM clinical_treatment_sessions next WHERE next.replaces_session_id=s.session_id) ORDER BY s.performed_at DESC,s.session_id DESC");
        $stmt->execute([$this->doctorId,$this->patientId]);return array_map($this->row(...),$stmt->fetchAll(PDO::FETCH_ASSOC));
    }
}
