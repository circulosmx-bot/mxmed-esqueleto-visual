<?php
declare(strict_types=1);

final class ClinicalObservationValidationException extends InvalidArgumentException {}

function clinical_observation_catalog(): array
{
    return [
        'blood_pressure' => ['unit' => 'mmHg', 'kind' => 'blood_pressure'],
        'heart_rate' => ['unit' => 'bpm', 'kind' => 'numeric'],
        'respiratory_rate' => ['unit' => 'rpm', 'kind' => 'numeric'],
        'temperature' => ['unit' => '°C', 'kind' => 'numeric'],
        'oxygen_saturation' => ['unit' => '%', 'kind' => 'numeric'],
        'pain' => ['unit' => 'score', 'kind' => 'numeric'],
        'weight' => ['unit' => 'kg', 'kind' => 'numeric'],
        'height' => ['unit' => 'cm', 'kind' => 'numeric'],
        'waist' => ['unit' => 'cm', 'kind' => 'numeric'],
    ];
}

function clinical_observation_validate(array $input): array
{
    foreach (['invalidated_at','invalidated_by_user_id','invalidation_reason'] as $field) {
        if (array_key_exists($field,$input)) throw new ClinicalObservationValidationException('OBSERVATION_INVALIDATION_COMMAND_REQUIRED');
    }
    if (array_key_exists('effective_at_authority', $input)) {
        throw new ClinicalObservationValidationException('OBSERVATION_TIME_AUTHORITY_SERVER_ONLY');
    }
    $code=trim((string)($input['code'] ?? ''));
    $catalog=clinical_observation_catalog();
    if (!isset($catalog[$code])) throw new ClinicalObservationValidationException('OBSERVATION_CODE_UNSUPPORTED');
    $unit=trim((string)($input['unit'] ?? ''));
    if ($unit !== $catalog[$code]['unit']) throw new ClinicalObservationValidationException('OBSERVATION_UNIT_INVALID');
    if ($code === 'blood_pressure') {
        $s=$input['systolic_mm_hg'] ?? null; $d=$input['diastolic_mm_hg'] ?? null;
        if (!is_numeric($s) || !is_numeric($d) || (float)$s <= 0 || (float)$d <= 0) {
            throw new ClinicalObservationValidationException('BLOOD_PRESSURE_COMPONENTS_REQUIRED');
        }
        if (isset($input['value_text']) && trim((string)$input['value_text']) !== '') {
            throw new ClinicalObservationValidationException('BLOOD_PRESSURE_TEXT_ONLY_FORBIDDEN');
        }
    } elseif (!is_numeric($input['value_numeric'] ?? null)) {
        throw new ClinicalObservationValidationException('OBSERVATION_NUMERIC_VALUE_REQUIRED');
    }
    $source=trim((string)($input['source'] ?? ''));
    if (!in_array($source, ['direct_measurement','patient_report','import'], true)) {
        throw new ClinicalObservationValidationException('OBSERVATION_PROVENANCE_INVALID');
    }
    if (array_key_exists('effective_at', $input)) {
        $time=$input['effective_at'];
        if (!is_string($time) || !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D',$time)
            || !($parsed=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$time,new DateTimeZone('UTC')))
            || $parsed->format('Y-m-d H:i:s')!==$time) {
            throw new ClinicalObservationValidationException('OBSERVATION_EFFECTIVE_AT_INVALID');
        }
    }
    return $input;
}

function clinical_observation_time_trend_eligible(string $authority): bool
{
    return $authority === 'EXPLICIT_EFFECTIVE_TIME';
}

final class ClinicalObservationsRepository
{
    public function __construct(private PDO $pdo) {}

    public function createOpen(int $encounterId, array $input, string $actor): array
    {
        $input=clinical_observation_validate($input);
        $this->pdo->beginTransaction();
        try {
            $id=$this->createOpenInTransaction($encounterId,$input,$actor);
            $row=$this->fetch($id); $this->pdo->commit(); return $row;
        } catch (Throwable $e) { if($this->pdo->inTransaction())$this->pdo->rollBack(); throw $e; }
    }

    public function createOpenInTransaction(int $encounterId, array $input, string $actor): int
    {
        $input=clinical_observation_validate($input);
        $this->assertOpen($encounterId);
        $capturedAt=gmdate('Y-m-d H:i:s');
        $explicitTime=array_key_exists('effective_at',$input);
        $stmt=$this->pdo->prepare('INSERT INTO clinical_observations
          (encounter_id, code, value_numeric, value_text, unit, systolic_mm_hg, diastolic_mm_hg, effective_at, effective_at_authority, recorded_at,
           recorded_by_user_id, source, provenance_json, row_version, created_at, updated_at)
          VALUES (:encounter_id,:code,:value_numeric,NULL,:unit,:systolic,:diastolic,:effective_at,:time_authority,:recorded_at,:actor,:source,:provenance,1,UTC_TIMESTAMP(),UTC_TIMESTAMP())');
        $stmt->execute([
            ':encounter_id'=>$encounterId, ':code'=>$input['code'], ':value_numeric'=>$input['value_numeric'] ?? null,
            ':unit'=>$input['unit'], ':systolic'=>$input['systolic_mm_hg'] ?? null, ':diastolic'=>$input['diastolic_mm_hg'] ?? null,
            ':effective_at'=>$explicitTime?$input['effective_at']:$capturedAt,
            ':time_authority'=>$explicitTime?'EXPLICIT_EFFECTIVE_TIME':'CAPTURE_TIME_FALLBACK',
            ':recorded_at'=>$capturedAt, ':actor'=>$actor, ':source'=>$input['source'],
            ':provenance'=>json_encode($input['provenance'] ?? [], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function updateOpen(int $encounterId, int $observationId, array $input, int $expectedVersion, string $actor): array
    {
        $input=clinical_observation_validate($input); $this->pdo->beginTransaction();
        try {
            $this->assertOpen($encounterId);
            $explicitTime=array_key_exists('effective_at',$input);
            $timeSet=$explicitTime?'effective_at=:effective_at,effective_at_authority=\'EXPLICIT_EFFECTIVE_TIME\',':'';
            $stmt=$this->pdo->prepare('UPDATE clinical_observations SET code=:code,value_numeric=:value_numeric,value_text=NULL,unit=:unit,
              systolic_mm_hg=:systolic,diastolic_mm_hg=:diastolic,'.$timeSet.'recorded_by_user_id=:actor,
              source=:source,provenance_json=:provenance,row_version=row_version+1,updated_at=UTC_TIMESTAMP()
              WHERE observation_id=:observation_id AND encounter_id=:encounter_id AND row_version=:expected_version AND invalidated_at IS NULL');
            $params=[':code'=>$input['code'],':value_numeric'=>$input['value_numeric']??null,':unit'=>$input['unit'],
              ':systolic'=>$input['systolic_mm_hg']??null,':diastolic'=>$input['diastolic_mm_hg']??null,
              ':actor'=>$actor,':source'=>$input['source'],
              ':provenance'=>json_encode($input['provenance']??[],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),
              ':observation_id'=>$observationId,':encounter_id'=>$encounterId,':expected_version'=>$expectedVersion];
            if($explicitTime)$params[':effective_at']=$input['effective_at'];
            $stmt->execute($params);
            if ($stmt->rowCount() !== 1) throw new RuntimeException('VERSION_CONFLICT');
            $row=$this->fetch($observationId); $this->pdo->commit(); return $row;
        } catch (Throwable $e) { if($this->pdo->inTransaction())$this->pdo->rollBack(); throw $e; }
    }

    /** One-way invalidation. Lock order matches PATCH and encounter finalization. */
    public function invalidateOpen(int $encounterId, int $observationId, int $expectedVersion, string $actor, string $doctor, string $patient, string $reason): array
    {
        if ($expectedVersion<1) throw new InvalidArgumentException('ROW_VERSION_REQUIRED');
        $reason=trim($reason);
        if ($reason==='' || mb_strlen($reason)>1000) throw new InvalidArgumentException('INVALIDATION_REASON_REQUIRED');
        if (trim($actor)==='' || strlen($actor)>64) throw new InvalidArgumentException('INVALIDATION_ACTOR_REQUIRED');
        $this->pdo->beginTransaction();
        try {
            $lock=$this->pdo->prepare('SELECT status,doctor_id,patient_id FROM clinical_encounters WHERE encounter_id=:id FOR UPDATE');
            $lock->execute([':id'=>$encounterId]);$encounter=$lock->fetch(PDO::FETCH_ASSOC);
            if (!$encounter || (string)$encounter['doctor_id']!==$doctor || (string)$encounter['patient_id']!==$patient) throw new RuntimeException('OBSERVATION_CONTEXT_MISMATCH');
            if ($encounter['status']!=='open') throw new RuntimeException('ENCOUNTER_TERMINAL');
            $stmt=$this->pdo->prepare('UPDATE clinical_observations SET invalidated_at=UTC_TIMESTAMP(),invalidated_by_user_id=:actor,
                invalidation_reason=:reason,row_version=row_version+1,updated_at=UTC_TIMESTAMP()
                WHERE observation_id=:id AND encounter_id=:encounter AND row_version=:version AND invalidated_at IS NULL');
            $stmt->execute([':actor'=>$actor,':reason'=>$reason,':id'=>$observationId,':encounter'=>$encounterId,':version'=>$expectedVersion]);
            if ($stmt->rowCount()!==1) throw new RuntimeException('VERSION_CONFLICT');
            $row=$this->fetch($observationId);$this->pdo->commit();return $row;
        } catch (Throwable $e) { if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e; }
    }

    private function assertOpen(int $encounterId): void
    {
        $stmt=$this->pdo->prepare('SELECT status FROM clinical_encounters WHERE encounter_id=:id FOR UPDATE');
        $stmt->execute([':id'=>$encounterId]);
        if ($stmt->fetchColumn() !== 'open') throw new RuntimeException('ENCOUNTER_TERMINAL');
    }

    public function fetch(int $id): array
    {
        $stmt=$this->pdo->prepare('SELECT * FROM clinical_observations WHERE observation_id=:id'); $stmt->execute([':id'=>$id]);
        $row=$stmt->fetch(PDO::FETCH_ASSOC); if(!is_array($row)) throw new RuntimeException('OBSERVATION_NOT_FOUND'); return $row;
    }
}
