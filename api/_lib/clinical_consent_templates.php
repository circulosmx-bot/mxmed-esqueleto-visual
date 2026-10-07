<?php
declare(strict_types=1);

final class ClinicalConsentTemplateException extends RuntimeException
{
    public function __construct(public readonly string $reason, public readonly int $httpStatus)
    {
        parent::__construct($reason);
    }
}

/** Explicit copy boundary; no patient, provider identity, signer, signature or attachment keys. */
function clinical_consent_template_fields(): array
{
    return ['title','procedimiento','template_key','objetivo','riesgos','risk_comunes',
        'risk_poco_frecuentes','risk_raros_graves','beneficios_esperados','alternativas',
        'consecuencias_no_aceptar','autorizacion_contingencias'];
}

function clinical_consent_template_validate(array $body): array
{
    foreach (array_keys($body) as $key) {
        if (!in_array($key, ['template_name','content','expected_version'], true)) {
            throw new ClinicalConsentTemplateException('unsupported_field', 400);
        }
    }
    $name = $body['template_name'] ?? null;
    $content = $body['content'] ?? null;
    if (!is_string($name) || trim($name) === '' || mb_strlen(trim($name)) > 160 || !is_array($content)) {
        throw new ClinicalConsentTemplateException('invalid_template', 400);
    }
    $clean = [];
    foreach ($content as $key => $value) {
        if (!in_array($key, clinical_consent_template_fields(), true)) {
            throw new ClinicalConsentTemplateException('unsupported_content_field', 400);
        }
        if ($key === 'autorizacion_contingencias') {
            if (!is_bool($value)) throw new ClinicalConsentTemplateException('invalid_content', 400);
        } elseif (!is_string($value) || mb_strlen($value) > match ($key) {
            'title' => 180,
            'objetivo' => 500,
            'template_key' => 80,
            default => 12000,
        }) {
            throw new ClinicalConsentTemplateException('invalid_content', 400);
        }
        $clean[$key] = $value;
    }
    return ['template_name' => trim($name), 'content' => $clean];
}

function clinical_consent_template_row(array $row): array
{
    return [
        'uuid' => (string)$row['template_uuid'],
        'template_name' => (string)$row['template_name'],
        'document_type' => (string)$row['document_type'],
        'content' => json_decode((string)$row['content_json'], true) ?: [],
        'status' => (string)$row['status'],
        'version' => (int)$row['version'],
        'created_at' => (string)$row['created_at'],
        'updated_at' => (string)$row['updated_at'],
    ];
}

function clinical_consent_template_get(PDO $pdo, string $doctorId, string $uuid): array
{
    $stmt = $pdo->prepare('SELECT * FROM clinical_consent_templates WHERE doctor_id=? AND template_uuid=? LIMIT 1');
    $stmt->execute([$doctorId, $uuid]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new ClinicalConsentTemplateException('not_found', 404);
    return clinical_consent_template_row($row);
}

function clinical_consent_template_list(PDO $pdo, string $doctorId, bool $includeArchived): array
{
    $sql = 'SELECT * FROM clinical_consent_templates WHERE doctor_id=?'.($includeArchived ? '' : " AND status='active'")
        .' ORDER BY template_name ASC, template_id ASC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$doctorId]);
    return array_map('clinical_consent_template_row', $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function clinical_consent_template_create(PDO $pdo, string $doctorId, array $body): array
{
    $data = clinical_consent_template_validate($body);
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);
    $uuid = sprintf('%s-%s-%s-%s-%s', substr($hex,0,8), substr($hex,8,4), substr($hex,12,4), substr($hex,16,4), substr($hex,20));
    $stmt = $pdo->prepare("INSERT INTO clinical_consent_templates (template_uuid,doctor_id,template_name,content_json) VALUES (?,?,?,?)");
    $stmt->execute([$uuid,$doctorId,$data['template_name'],json_encode($data['content'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
    return clinical_consent_template_get($pdo,$doctorId,$uuid);
}

function clinical_consent_template_update(PDO $pdo, string $doctorId, string $uuid, array $body): array
{
    $data = clinical_consent_template_validate($body);
    $expected = $body['expected_version'] ?? null;
    if (!is_int($expected) || $expected < 1) throw new ClinicalConsentTemplateException('expected_version_required', 400);
    $current = clinical_consent_template_get($pdo,$doctorId,$uuid);
    if ($current['status'] !== 'active') throw new ClinicalConsentTemplateException('template_archived', 409);
    $stmt = $pdo->prepare("UPDATE clinical_consent_templates SET template_name=?,content_json=?,version=version+1 WHERE doctor_id=? AND template_uuid=? AND version=? AND status='active'");
    $stmt->execute([$data['template_name'],json_encode($data['content'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),$doctorId,$uuid,$expected]);
    if ($stmt->rowCount() !== 1) throw new ClinicalConsentTemplateException('version_conflict', 409);
    return clinical_consent_template_get($pdo,$doctorId,$uuid);
}

function clinical_consent_template_duplicate(PDO $pdo, string $doctorId, string $uuid): array
{
    $source = clinical_consent_template_get($pdo,$doctorId,$uuid);
    if ($source['status'] !== 'active') throw new ClinicalConsentTemplateException('template_archived', 409);
    return clinical_consent_template_create($pdo,$doctorId,[
        'template_name' => 'Copia de '.mb_substr($source['template_name'], 0, 151), 'content' => $source['content']]);
}

function clinical_consent_template_archive(PDO $pdo, string $doctorId, string $uuid, array $body): array
{
    $expected = $body['expected_version'] ?? null;
    if (!is_int($expected) || $expected < 1 || count($body) !== 1) throw new ClinicalConsentTemplateException('expected_version_required', 400);
    clinical_consent_template_get($pdo,$doctorId,$uuid);
    $stmt = $pdo->prepare("UPDATE clinical_consent_templates SET status='archived',version=version+1 WHERE doctor_id=? AND template_uuid=? AND version=? AND status='active'");
    $stmt->execute([$doctorId,$uuid,$expected]);
    if ($stmt->rowCount() !== 1) throw new ClinicalConsentTemplateException('version_conflict', 409);
    return clinical_consent_template_get($pdo,$doctorId,$uuid);
}

function clinical_consent_template_delete(PDO $pdo, string $doctorId, string $uuid, array $body): array
{
    $expected = $body['expected_version'] ?? null;
    if (!is_int($expected) || $expected < 1 || count($body) !== 1) {
        throw new ClinicalConsentTemplateException('expected_version_required', 400);
    }
    // This authority has no clinical-document FK; patient documents own their copied payloads.
    clinical_consent_template_get($pdo, $doctorId, $uuid);
    $stmt = $pdo->prepare('DELETE FROM clinical_consent_templates WHERE doctor_id=? AND template_uuid=? AND version=?');
    $stmt->execute([$doctorId, $uuid, $expected]);
    if ($stmt->rowCount() !== 1) throw new ClinicalConsentTemplateException('version_conflict', 409);
    return ['uuid' => $uuid];
}
