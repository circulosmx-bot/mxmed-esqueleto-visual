<?php
declare(strict_types=1);
require_once __DIR__ . '/clinical_legal_document_presentation.php';

/** One escaped composition for unsaved preview, frozen mobile review, viewer and print. */
function clinical_nota_render_html(array $payload): string
{
    $h = static fn($value): string => htmlspecialchars(trim((string)($value ?? '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $patient = (array)($payload['patient_snapshot'] ?? []);
    $actor = (array)($payload['actor_snapshot'] ?? []);
    $brand = (array)($payload['branding'] ?? []);
    $report = (array)($payload['report'] ?? []);
    $content = (array)($payload['content'] ?? []);
    $signature = (array)($payload['signatures']['doctor'] ?? []);
    $logo = (string)($brand['logo_url_resolved'] ?? '');
    if (!((str_starts_with($logo, '/') && !str_starts_with($logo, '//'))
        || preg_match('#^https://[^\s]+$#i', $logo) === 1
        || preg_match('#^data:image/(?:png|jpeg|webp);base64,[A-Za-z0-9+/=]+$#D', $logo) === 1)) $logo = '';
    $image = (string)($signature['image_data'] ?? '');
    if (preg_match('#^data:image/png;base64,[A-Za-z0-9+/=]+$#D', $image) !== 1) $image = '';

    $html = '<article class="informe-doc-sheet document-sheet doc-base-sheet doc-base-sheet--letter doc-base-print-safe"><div class="doc-base-sheet-inner doc-base-body">';
    if (clinical_legal_document_professional_header_mode($payload) === 'shown') {
        $html .= '<header class="clinical-doc-head doc-base-medical-header doc-base-header-block doc-base-print-safe">';
        if ($logo !== '') $html .= '<div class="clinical-doc-head-logo-slot"><img src="' . $h($logo) . '" alt="Logo médico"></div>';
        $html .= '<div class="clinical-doc-head-main"><div class="clinical-doc-doctor-name">' . $h($actor['full_name'] ?? '') . '</div>';
        foreach ([$actor['specialty'] ?? '',
            empty($actor['license']) ? '' : 'Cédula: ' . $actor['license'],
            empty($actor['specialty_license']) ? '' : 'Cédula esp.: ' . $actor['specialty_license'],
            $brand['facility_visible'] ?? '', $brand['location_line_visible'] ?? ''] as $line) {
            if (trim((string)$line) !== '') $html .= '<div class="clinical-doc-doctor-site">' . $h($line) . '</div>';
        }
        $html .= '</div></header>';
    }
    $html .= '<div class="doc-base-title-block-wrap"><section class="doc-base-title-block"><div class="doc-base-title">NOTA MÉDICA</div></section></div>';
    $typeLabels = ['consulta_inicial' => 'Consulta inicial', 'nota_evolucion' => 'Nota de evolución', 'nota_subsecuente' => 'Nota subsecuente'];
    $typeLabel = $typeLabels[(string)($payload['note']['type'] ?? '')] ?? 'Nota médica';
    $html .= '<div class="doc-base-patient-block-wrap"><section class="doc-base-patient-meta">'
        . '<div><strong>Paciente:</strong> ' . $h($patient['full_name'] ?? '') . '</div>'
        . '<div><strong>Edad:</strong> ' . $h($patient['age'] ?? '') . ' · <strong>Sexo:</strong> ' . $h($patient['sex'] ?? '') . '</div>'
        . '<div><strong>Fecha:</strong> ' . $h($report['emission_date'] ?? '') . ' · <strong>Tipo:</strong> ' . $h($typeLabel) . '</div>'
        . '</section></div><div class="doc-base-body-block">';
    $sections = [
        ['Motivo de consulta', 'motivo_consulta'],
        ['Padecimiento actual', 'padecimiento_actual'],
        ['Síntomas relevantes', 'sintomas_relevantes'],
        ['Tiempo de evolución', 'tiempo_evolucion'],
        ['Información referida por paciente', 'informacion_referida_paciente'],
        ['Signos vitales', 'signos_vitales'],
        ['Exploración física', 'exploracion_fisica'],
        ['Resultados relevantes', 'resultados_relevantes'],
        ['Impresión diagnóstica', 'impresion_diagnostica'],
        ['Análisis clínico', 'analisis_clinico'],
        ['Plan de manejo', 'tratamiento_indicaciones'],
        ['Estudios sugeridos', 'estudios_sugeridos'],
        ['Seguimiento', 'seguimiento'],
    ];
    foreach ($sections as [$label, $key]) {
        $value = trim((string)($content[$key] ?? ''));
        if ($value === '') continue;
        $html .= '<section class="doc-base-section"><div class="doc-base-section-title">' . $h($label)
            . '</div><div class="doc-base-text">' . nl2br($h($value)) . '</div></section>';
    }
    $html .= '<section class="doc-base-signature doc-base-print-safe"><div class="doc-base-section-title">Firma del médico</div>';
    if ($image !== '') $html .= '<img class="informe-doc-sign-image" src="' . $h($image) . '" alt="Firma del médico">';
    else $html .= '<div class="informe-doc-sign-line"></div>';
    $html .= '<div class="doc-base-signature-meta">' . $h($signature['signer_name'] ?? ($actor['full_name'] ?? '')) . '</div>';
    if (!empty($actor['license'])) $html .= '<div class="doc-base-signature-meta">Cédula profesional: ' . $h($actor['license']) . '</div>';
    $html .= '</section></div><div class="doc-base-flex-spacer" aria-hidden="true"></div></div></article>';
    return $html;
}

function clinical_nota_canonical_text(array $payload): string
{
    $lines = ['NOTA MÉDICA', (string)($payload['patient_snapshot']['full_name'] ?? '')];
    foreach ((array)($payload['content'] ?? []) as $value) {
        if (is_scalar($value) && trim((string)$value) !== '') $lines[] = trim((string)$value);
    }
    return implode("\n\n", $lines);
}
