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

function clinical_professional_presentation_origin(): string
{
    foreach (['MXMED_PUBLIC_PROFILE_ORIGIN','MXMED_IDENTITY_ORIGIN','MXMED_NORMAL_LOCAL_IDENTITY_ORIGIN'] as $key) {
        $origin = rtrim(trim((string)(getenv($key) ?: '')), '/');
        $parts = $origin === '' ? false : parse_url($origin);
        if (!is_array($parts) || strtolower((string)($parts['scheme'] ?? '')) !== 'https'
            || trim((string)($parts['host'] ?? '')) === '' || isset($parts['user']) || isset($parts['pass'])
            || trim((string)($parts['path'] ?? '')) !== '' || isset($parts['query']) || isset($parts['fragment'])) continue;
        return $origin;
    }
    return '';
}

/** Resolve a published profile URL without activating a candidate SEO route. */
function clinical_professional_presentation_public_profile(PDO $pdo, string $doctorId): ?array
{
    $origin = clinical_professional_presentation_origin();
    if ($origin === '') return null;
    try {
        $stmt = $pdo->prepare(
            "SELECT profile_status,is_public_candidate
               FROM profiles_doctors
              WHERE doctor_id=? LIMIT 1"
        );
        $stmt->execute([$doctorId]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($profile) || !in_array(strtolower(trim((string)$profile['profile_status'])), ['active','published'], true)
            || (int)$profile['is_public_candidate'] !== 1) return null;

        $path = '/profiles/doctor.php?doctor_id=' . rawurlencode($doctorId);
        $source = 'published_profile_current_url';
        try {
            $route = $pdo->prepare(
                "SELECT canonical_path,status,route_enabled,canonical_enabled
                   FROM public_profile_seo_routes
                  WHERE entity_type='doctor' AND entity_id=? LIMIT 1"
            );
            $route->execute([$doctorId]);
            $canonical = $route->fetch(PDO::FETCH_ASSOC);
            if (is_array($canonical) && strtolower(trim((string)$canonical['status'])) === 'active'
                && (int)$canonical['route_enabled'] === 1 && (int)$canonical['canonical_enabled'] === 1
                && preg_match('#^/[a-zA-Z0-9/_-]+$#D', trim((string)$canonical['canonical_path'])) === 1) {
                $path = trim((string)$canonical['canonical_path']);
                $source = 'active_canonical_profile_route';
            }
        } catch (Throwable) {
            // The currently published profile remains authoritative when the optional SEO route read model is absent.
        }
        return ['url'=>$origin.$path,'source'=>$source];
    } catch (Throwable) {
        return null;
    }
}

/**
 * Resolve the only locations explicitly authorized for clinical documents.
 * Secondaries without an explicit display order are intentionally ineligible.
 */
function clinical_professional_presentation_resolve(PDO $pdo, string $doctorId): array
{
    $publicProfile = clinical_professional_presentation_public_profile($pdo, $doctorId);
    $offices = [];
    try {
        $schema = $pdo->query(
            "SELECT COUNT(*) FROM information_schema.columns
              WHERE table_schema = DATABASE() AND table_name = 'consultorios'
                AND column_name IN ('document_is_primary','document_display_order','document_publication_enabled')"
        );
        if ((int)$schema->fetchColumn() !== 3)
            return array_filter(['version'=>2,'offices'=>[],'public_profile'=>$publicProfile], static fn(mixed $v): bool => $v !== null);
    } catch (Throwable) {
        return array_filter(['version'=>2,'offices'=>[],'public_profile'=>$publicProfile], static fn(mixed $v): bool => $v !== null);
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
    return array_filter(['version'=>2,'offices'=>$offices,'public_profile'=>$publicProfile], static fn(mixed $v): bool => $v !== null);
}
