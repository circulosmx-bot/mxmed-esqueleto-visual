<?php
declare(strict_types=1);

final class ClinicalSectionValidationException extends InvalidArgumentException {}

function clinical_encounter_section_types(): array
{
    return ['reason_evolution', 'review_of_systems', 'physical_exam', 'assessment', 'plan', 'follow_up'];
}

function clinical_encounter_section_type_validate(string $type): string
{
    $type = trim($type);
    if (!in_array($type, clinical_encounter_section_types(), true)) {
        throw new ClinicalSectionValidationException('SECTION_TYPE_UNSUPPORTED');
    }
    return $type;
}

function clinical_encounter_section_payload_validate(string $type, int $schemaVersion, array $payload): array
{
    $type = clinical_encounter_section_type_validate($type);
    if ($schemaVersion !== 1) {
        throw new ClinicalSectionValidationException('PAYLOAD_SCHEMA_VERSION_UNSUPPORTED');
    }
    if ($type === 'physical_exam') {
        $systems = $payload['systems'] ?? [];
        if (!is_array($systems)) {
            throw new ClinicalSectionValidationException('PHYSICAL_EXAM_SYSTEMS_INVALID');
        }
        foreach ($systems as $system => $finding) {
            if (!is_string($system) || trim($system) === '' || !is_array($finding)) {
                throw new ClinicalSectionValidationException('PHYSICAL_EXAM_FINDING_INVALID');
            }
            $state = strtoupper(trim((string)($finding['state'] ?? '')));
            if (!in_array($state, ['NORMAL', 'ABNORMAL'], true)) {
                throw new ClinicalSectionValidationException('PHYSICAL_EXAM_STATE_INVALID');
            }
            if ($state === 'ABNORMAL' && trim((string)($finding['finding'] ?? '')) === '') {
                throw new ClinicalSectionValidationException('PHYSICAL_EXAM_ABNORMAL_FINDING_REQUIRED');
            }
        }
        // Missing systems intentionally remain NOT_REVIEWED; none are synthesized.
    }
    return $payload;
}

function clinical_physical_exam_state(array $payload, string $system): string
{
    $systems = is_array($payload['systems'] ?? null) ? $payload['systems'] : [];
    if (!array_key_exists($system, $systems)) {
        return 'NOT_REVIEWED';
    }
    return strtoupper(trim((string)($systems[$system]['state'] ?? '')));
}

function clinical_section_write_error_code(Throwable $error): string
{
    return $error instanceof PDOException && (string)$error->getCode() === '23000'
        ? 'VERSION_CONFLICT'
        : $error->getMessage();
}

final class ClinicalEncounterSectionsRepository
{
    public function __construct(private PDO $pdo) {}

    public function saveOpen(int $encounterId, string $type, int $schemaVersion, array $payload, string $narrative, string $actor, ?int $expectedVersion): array
    {
        clinical_encounter_section_payload_validate($type, $schemaVersion, $payload);
        $this->pdo->beginTransaction();
        try {
            $this->assertOpen($encounterId);
            $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            if ($expectedVersion === null) {
                $stmt = $this->pdo->prepare('INSERT INTO clinical_encounter_sections
                    (encounter_id, section_type, payload_schema_version, payload_json, narrative_text, created_by_user_id, updated_by_user_id, row_version, created_at, updated_at)
                    VALUES (:encounter_id, :section_type, :schema_version, :payload_json, :narrative, :actor, :actor, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP())');
                $stmt->execute([':encounter_id'=>$encounterId, ':section_type'=>$type, ':schema_version'=>$schemaVersion, ':payload_json'=>$json, ':narrative'=>$narrative, ':actor'=>$actor]);
            } else {
                $stmt = $this->pdo->prepare('UPDATE clinical_encounter_sections SET payload_schema_version=:schema_version, payload_json=:payload_json,
                    narrative_text=:narrative, updated_by_user_id=:actor, row_version=row_version+1, updated_at=UTC_TIMESTAMP()
                    WHERE encounter_id=:encounter_id AND section_type=:section_type AND row_version=:expected_version');
                $stmt->execute([':schema_version'=>$schemaVersion, ':payload_json'=>$json, ':narrative'=>$narrative, ':actor'=>$actor,
                    ':encounter_id'=>$encounterId, ':section_type'=>$type, ':expected_version'=>$expectedVersion]);
                if ($stmt->rowCount() !== 1) throw new RuntimeException('VERSION_CONFLICT');
            }
            $row = $this->fetch($encounterId, $type);
            $this->pdo->commit();
            return $row;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            if (clinical_section_write_error_code($e) === 'VERSION_CONFLICT') {
                throw new RuntimeException('VERSION_CONFLICT', 409, $e);
            }
            throw $e;
        }
    }

    private function assertOpen(int $encounterId): void
    {
        $stmt=$this->pdo->prepare("SELECT status FROM clinical_encounters WHERE encounter_id=:id FOR UPDATE");
        $stmt->execute([':id'=>$encounterId]);
        if ($stmt->fetchColumn() !== 'open') throw new RuntimeException('ENCOUNTER_TERMINAL');
    }

    public function fetch(int $encounterId, string $type): array
    {
        $stmt=$this->pdo->prepare('SELECT * FROM clinical_encounter_sections WHERE encounter_id=:id AND section_type=:type');
        $stmt->execute([':id'=>$encounterId, ':type'=>$type]);
        $row=$stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) throw new RuntimeException('SECTION_NOT_FOUND');
        return $row;
    }
}
