<?php
declare(strict_types=1);

/** The sole composition for new Alta previews, final review, viewer and print. */
function clinical_alta_render_html(array $payload): string
{
    $h = static fn($value): string => htmlspecialchars(trim((string)($value ?? '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $patient = (array)($payload['patient_snapshot'] ?? []);
    $actor = (array)($payload['actor_snapshot'] ?? []);
    $report = (array)($payload['report'] ?? []);
    $alta = (array)($payload['alta'] ?? []);
    $content = (array)($payload['content'] ?? []);
    $signature = (array)($payload['signatures']['doctor'] ?? []);
    $image = (string)($signature['image_data'] ?? '');
    if (preg_match('#^data:image/png;base64,[A-Za-z0-9+/=]+$#D', $image) !== 1) $image = '';

    $types = ['mejoria' => 'Mejoría', 'voluntaria' => 'Voluntaria',
        'administrativa' => 'Administrativa', 'referencia' => 'Referencia'];
    $type = (string)($alta['type'] ?? ($content['tipo_alta'] ?? 'mejoria'));
    $typeLabel = $types[$type] ?? 'Mejoría';
    $html = '<article class="informe-doc-sheet alta-doc-sheet document-sheet doc-base-sheet doc-base-sheet--letter doc-base-print-safe">'
        . '<div class="doc-base-sheet-inner doc-base-body">'
        . '<header class="clinical-doc-head doc-base-medical-header doc-base-header-block doc-base-print-safe">'
        . '<div class="clinical-doc-head-main"><div class="clinical-doc-doctor-name">' . $h($actor['full_name'] ?? '') . '</div>';
    foreach ([$actor['specialty'] ?? '',
        empty($actor['license']) ? '' : 'Cédula: ' . $actor['license'],
        empty($actor['specialty_license']) ? '' : 'Cédula esp.: ' . $actor['specialty_license'],
        $actor['facility'] ?? '', $actor['place'] ?? ''] as $line) {
        if (trim((string)$line) !== '') $html .= '<div class="clinical-doc-doctor-site">' . $h($line) . '</div>';
    }
    $html .= '</div></header><div class="doc-base-title-block-wrap"><section class="doc-base-title-block">'
        . '<div class="doc-base-title">ALTA MÉDICA</div></section></div>'
        . '<div class="doc-base-patient-block-wrap"><section class="doc-base-patient-meta">'
        . '<div><strong>Paciente:</strong> ' . $h($patient['full_name'] ?? '') . '</div>'
        . '<div><strong>Edad:</strong> ' . $h($patient['age'] ?? '')
        . ' · <strong>Sexo:</strong> ' . $h($patient['sex'] ?? '') . '</div>'
        . '<div><strong>Fecha:</strong> ' . $h($report['emission_date'] ?? '')
        . ' · <strong>Tipo:</strong> ' . $h($typeLabel) . '</div>'
        . '</section></div><div class="doc-base-body-block">';
    $sections = [
        ['Motivo de egreso', 'motivo_egreso'],
        ['Resumen clínico', 'resumen_evolucion'],
        ['Diagnóstico final', 'diagnostico_final'],
        ['Estado actual del paciente', 'estado_paciente'],
        ['Datos relevantes', 'datos_relevantes'],
        ['Tratamiento', 'tratamiento'],
        ['Cuidados generales', 'cuidados_generales'],
        ['Signos de alarma', 'signos_alarma'],
        ['Cita de control', 'cita_control'],
        ['Motivo de control', 'followup_note'],
        ['Recomendaciones', 'recomendaciones'],
    ];
    foreach ($sections as [$label, $key]) {
        $value = trim((string)($content[$key] ?? ''));
        if ($value === '') continue;
        $html .= '<section class="informe-doc-body-section doc-base-section">'
            . '<div class="informe-doc-section-title doc-base-section-title">' . $h($label) . '</div>'
            . '<div class="informe-doc-text doc-base-text">' . nl2br($h($value)) . '</div></section>';
    }
    $html .= '<section class="informe-doc-sign doc-base-signature doc-base-print-safe">'
        . '<div class="informe-doc-section-title doc-base-section-title">Médico tratante</div>';
    if ($image !== '') {
        $html .= '<img class="informe-doc-sign-image" src="' . $h($image) . '" alt="Firma del médico">';
    } else {
        $html .= '<div class="informe-doc-sign-line"></div>';
    }
    $html .= '<div class="informe-doc-sign-meta doc-base-signature-meta">'
        . $h($signature['signer_name'] ?? ($actor['full_name'] ?? '')) . '</div></section>'
        . '</div><div class="doc-base-flex-spacer" aria-hidden="true"></div></div></article>';
    return $html;
}
