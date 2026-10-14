<?php
declare(strict_types=1);

final class ClinicalPrescriptionTemplateException extends RuntimeException
{
    public function __construct(public readonly string $reason, public readonly int $httpStatus)
    {
        parent::__construct($reason);
    }
}

/** Patient-independent reusable medication fields. Private notes and provenance are deliberately absent. */
function clinical_prescription_template_medication_fields(): array
{
    return ['name','presentation','dose','route','frequency','duration','quantity','instructions'];
}

function clinical_prescription_template_string(mixed $value, int $limit): string
{
    if (!is_string($value) || mb_strlen($value) > $limit) {
        throw new ClinicalPrescriptionTemplateException('invalid_content', 400);
    }
    return trim($value);
}

/** Explicit recursive copy boundary. Unknown fields fail closed. */
function clinical_prescription_template_validate(array $body): array
{
    foreach (array_keys($body) as $key) {
        if (!in_array($key, ['template_name','content','expected_version'], true)) {
            throw new ClinicalPrescriptionTemplateException('unsupported_field', 400);
        }
    }
    $name = $body['template_name'] ?? null;
    $content = $body['content'] ?? null;
    if (!is_string($name) || trim($name) === '' || mb_strlen(trim($name)) > 160 || !is_array($content)) {
        throw new ClinicalPrescriptionTemplateException('invalid_template', 400);
    }
    foreach (array_keys($content) as $key) {
        if (!in_array($key, ['medications','diagnostic_context','observations','professional_header'], true)) {
            throw new ClinicalPrescriptionTemplateException('unsupported_content_field', 400);
        }
    }
    $medications = $content['medications'] ?? null;
    if (!is_array($medications) || $medications === [] || count($medications) > 100 || !array_is_list($medications)) {
        throw new ClinicalPrescriptionTemplateException('invalid_medications', 400);
    }
    $cleanMedications = [];
    foreach ($medications as $medication) {
        if (!is_array($medication)) throw new ClinicalPrescriptionTemplateException('invalid_medication', 400);
        foreach (array_keys($medication) as $key) {
            if (!in_array($key, clinical_prescription_template_medication_fields(), true)) {
                throw new ClinicalPrescriptionTemplateException('unsupported_medication_field', 400);
            }
        }
        $clean = [];
        foreach (clinical_prescription_template_medication_fields() as $field) {
            $clean[$field] = clinical_prescription_template_string($medication[$field] ?? '', $field === 'instructions' ? 4000 : 1000);
        }
        if ($clean['name'] === '') throw new ClinicalPrescriptionTemplateException('medication_name_required', 400);
        $cleanMedications[] = $clean;
    }
    $cleanContent = ['medications' => $cleanMedications];
    if (array_key_exists('diagnostic_context', $content)) {
        $diagnostic = $content['diagnostic_context'];
        if (!is_array($diagnostic) || array_diff(array_keys($diagnostic), ['mode','text'])) {
            throw new ClinicalPrescriptionTemplateException('invalid_diagnostic_context', 400);
        }
        $mode = clinical_prescription_template_string($diagnostic['mode'] ?? '', 40);
        if (!in_array($mode, ['diagnostico','prediagnostico','sin_diagnostico'], true)) {
            throw new ClinicalPrescriptionTemplateException('invalid_diagnostic_context', 400);
        }
        $cleanContent['diagnostic_context'] = [
            'mode' => $mode,
            'text' => clinical_prescription_template_string($diagnostic['text'] ?? '', 2000),
        ];
    }
    if (array_key_exists('observations', $content)) {
        $cleanContent['observations'] = clinical_prescription_template_string($content['observations'], 12000);
    }
    if (array_key_exists('professional_header', $content)) {
        $header = clinical_prescription_template_string($content['professional_header'], 20);
        if (!in_array($header, ['shown','hidden','data_only','logo_and_data'], true)) {
            throw new ClinicalPrescriptionTemplateException('invalid_professional_header', 400);
        }
        $cleanContent['professional_header'] = $header;
    }
    return ['template_name' => trim($name), 'content' => $cleanContent];
}

function clinical_prescription_template_row(array $row): array
{
    return [
        'uuid' => (string)$row['template_uuid'],
        'template_name' => (string)$row['template_name'],
        'content' => json_decode((string)$row['content_json'], true) ?: [],
        'status' => (string)$row['status'],
        'version' => (int)$row['version'],
        'created_at' => (string)$row['created_at'],
        'updated_at' => (string)$row['updated_at'],
    ];
}

function clinical_prescription_template_get(PDO $pdo, string $doctorId, string $uuid): array
{
    $stmt = $pdo->prepare('SELECT * FROM clinical_prescription_templates WHERE doctor_id=? AND template_uuid=? LIMIT 1');
    $stmt->execute([$doctorId, $uuid]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new ClinicalPrescriptionTemplateException('not_found', 404);
    return clinical_prescription_template_row($row);
}

function clinical_prescription_template_list(PDO $pdo, string $doctorId, bool $includeArchived): array
{
    $sql = 'SELECT * FROM clinical_prescription_templates WHERE doctor_id=?'.($includeArchived ? '' : " AND status='active'")
        .' ORDER BY template_name ASC, template_id ASC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$doctorId]);
    return array_map('clinical_prescription_template_row', $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function clinical_prescription_template_uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);
    return sprintf('%s-%s-%s-%s-%s', substr($hex,0,8), substr($hex,8,4), substr($hex,12,4), substr($hex,16,4), substr($hex,20));
}

function clinical_prescription_template_create(PDO $pdo, string $doctorId, array $body): array
{
    $data = clinical_prescription_template_validate($body);
    $uuid = clinical_prescription_template_uuid();
    $stmt = $pdo->prepare('INSERT INTO clinical_prescription_templates (template_uuid,doctor_id,template_name,content_json) VALUES (?,?,?,?)');
    $stmt->execute([$uuid,$doctorId,$data['template_name'],json_encode($data['content'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
    return clinical_prescription_template_get($pdo,$doctorId,$uuid);
}

function clinical_prescription_template_update(PDO $pdo, string $doctorId, string $uuid, array $body): array
{
    $data = clinical_prescription_template_validate($body);
    $expected = $body['expected_version'] ?? null;
    if (!is_int($expected) || $expected < 1) throw new ClinicalPrescriptionTemplateException('expected_version_required', 400);
    $current = clinical_prescription_template_get($pdo,$doctorId,$uuid);
    if ($current['status'] !== 'active') throw new ClinicalPrescriptionTemplateException('template_archived', 409);
    $stmt = $pdo->prepare("UPDATE clinical_prescription_templates SET template_name=?,content_json=?,version=version+1 WHERE doctor_id=? AND template_uuid=? AND version=? AND status='active'");
    $stmt->execute([$data['template_name'],json_encode($data['content'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),$doctorId,$uuid,$expected]);
    if ($stmt->rowCount() !== 1) throw new ClinicalPrescriptionTemplateException('version_conflict', 409);
    return clinical_prescription_template_get($pdo,$doctorId,$uuid);
}

function clinical_prescription_template_duplicate(PDO $pdo, string $doctorId, string $uuid): array
{
    $source = clinical_prescription_template_get($pdo,$doctorId,$uuid);
    if ($source['status'] !== 'active') throw new ClinicalPrescriptionTemplateException('template_archived', 409);
    return clinical_prescription_template_create($pdo,$doctorId,[
        'template_name' => 'Copia de '.mb_substr($source['template_name'], 0, 151),
        'content' => $source['content'],
    ]);
}

function clinical_prescription_template_archive(PDO $pdo, string $doctorId, string $uuid, array $body): array
{
    $expected = $body['expected_version'] ?? null;
    if (!is_int($expected) || $expected < 1 || count($body) !== 1) throw new ClinicalPrescriptionTemplateException('expected_version_required', 400);
    clinical_prescription_template_get($pdo,$doctorId,$uuid);
    $stmt = $pdo->prepare("UPDATE clinical_prescription_templates SET status='archived',version=version+1 WHERE doctor_id=? AND template_uuid=? AND version=? AND status='active'");
    $stmt->execute([$doctorId,$uuid,$expected]);
    if ($stmt->rowCount() !== 1) throw new ClinicalPrescriptionTemplateException('version_conflict', 409);
    return clinical_prescription_template_get($pdo,$doctorId,$uuid);
}

function clinical_prescription_template_delete(PDO $pdo, string $doctorId, string $uuid, array $body): array
{
    $expected = $body['expected_version'] ?? null;
    if (!is_int($expected) || $expected < 1 || count($body) !== 1) throw new ClinicalPrescriptionTemplateException('expected_version_required', 400);
    clinical_prescription_template_get($pdo,$doctorId,$uuid);
    $stmt = $pdo->prepare('DELETE FROM clinical_prescription_templates WHERE doctor_id=? AND template_uuid=? AND version=?');
    $stmt->execute([$doctorId,$uuid,$expected]);
    if ($stmt->rowCount() !== 1) throw new ClinicalPrescriptionTemplateException('version_conflict', 409);
    return ['uuid' => $uuid];
}
