<?php
declare(strict_types=1);

function clinical_professional_presentation_text(mixed $value): string
{
    return is_scalar($value) ? trim((string)$value) : '';
}

function clinical_professional_presentation_regular_phones(mixed $encoded): array
{
    $values = is_array($encoded) ? $encoded : json_decode((string)$encoded, true);
    if (!is_array($values)) return [];
    $phones = [];
    $seen = [];
    foreach ($values as $value) {
        $phone = clinical_professional_presentation_text($value);
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($phone === '' || strlen($digits) < 7 || strlen($digits) > 15 || isset($seen[$digits])) continue;
        $seen[$digits] = true;
        $phones[] = $phone;
    }
    return $phones;
}

function clinical_professional_presentation_address(array $row): string
{
    $street = clinical_professional_presentation_text($row['calle'] ?? '');
    $external = clinical_professional_presentation_text($row['num_ext'] ?? '');
    $internal = clinical_professional_presentation_text($row['num_int'] ?? '');
    if ($external !== '') $street .= ($street === '' ? '' : ' ') . $external;
    if ($internal !== '') $street .= ($street === '' ? '' : ', ') . 'interior ' . $internal;
    $colonia = clinical_professional_presentation_text($row['colonia'] ?? '');
    $postalCode = clinical_professional_presentation_text($row['cp'] ?? '');
    return implode(', ', array_values(array_filter([
        $street,
        $colonia === '' ? '' : 'Col. ' . $colonia,
        clinical_professional_presentation_text($row['municipio'] ?? ''),
        clinical_professional_presentation_text($row['estado'] ?? ''),
        $postalCode === '' ? '' : 'C.P. ' . $postalCode,
    ], static fn(string $part): bool => $part !== '')));
}

/**
 * Resolve the only locations explicitly authorized for clinical documents.
 * Secondaries without an explicit display order are intentionally ineligible.
 */
function clinical_professional_presentation_resolve(PDO $pdo, string $doctorId): array
{
    try {
        $schema = $pdo->query(
            "SELECT COUNT(*) FROM information_schema.columns
              WHERE table_schema = DATABASE() AND table_name = 'consultorios'
                AND column_name IN ('document_is_primary','document_display_order','document_publication_enabled')"
        );
        if ((int)$schema->fetchColumn() !== 3) return ['version' => 2, 'offices' => []];
    } catch (Throwable) {
        return ['version' => 2, 'offices' => []];
    }
    $stmt = $pdo->prepare(
        'SELECT consultorio_id,titulo,grupo_nombre,calle,num_ext,num_int,cp,colonia,municipio,estado,
                telefonos_json,document_is_primary,document_display_order
           FROM consultorios
          WHERE doctor_id = ?
            AND document_publication_enabled = 1
            AND (document_is_primary = 1 OR document_display_order IS NOT NULL)
          ORDER BY document_is_primary DESC, document_display_order ASC'
    );
    $stmt->execute([$doctorId]);
    $offices = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $name = clinical_professional_presentation_text($row['titulo'] ?? '');
        if ($name === '') $name = clinical_professional_presentation_text($row['grupo_nombre'] ?? '');
        $address = clinical_professional_presentation_address($row);
        $phones = clinical_professional_presentation_regular_phones($row['telefonos_json'] ?? null);
        if ($name === '' && $address === '' && $phones === []) continue;
        $offices[] = [
            'name' => $name,
            'address' => $address,
            'phones' => $phones,
            'is_primary' => (int)($row['document_is_primary'] ?? 0) === 1,
            'display_order' => ($row['document_display_order'] ?? null) === null
                ? null : (int)$row['document_display_order'],
        ];
        if (count($offices) === 3) break;
    }
    return ['version' => 2, 'offices' => $offices];
}
