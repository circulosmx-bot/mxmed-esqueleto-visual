<?php
declare(strict_types=1);

/** One escaped composition for unsaved preview and persisted Responsiva documents. */
function clinical_responsiva_render_html(array $payload): string
{
    $h = static fn(mixed $v): string => htmlspecialchars(trim((string)($v ?? '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $patient = (array)($payload['patient_snapshot'] ?? []);
    $actor = (array)($payload['actor_snapshot'] ?? []);
    $brand = (array)($payload['branding'] ?? []);
    $report = (array)($payload['report'] ?? []);
    $type = (array)($payload['responsiva'] ?? []);
    $content = (array)($payload['content'] ?? []);
    $signer = (array)($payload['signer'] ?? []);
    $signatures = (array)($payload['signatures'] ?? []);
    $section = static function (string $label, mixed $value) use ($h): string {
        $text = trim((string)($value ?? ''));
        return $text === '' ? '' : '<section class="informe-doc-body-section doc-base-section"><div class="informe-doc-section-title doc-base-section-title">'
            . $h($label) . '</div><div class="informe-doc-text doc-base-text">'
            . nl2br($h($text)) . '</div></section>';
    };
    $doctorName = $h($actor['full_name'] ?? 'Médico tratante');
    $doctorMeta = array_filter([(string)($actor['specialty'] ?? ''),
        ($actor['license'] ?? '') !== '' ? 'Cédula: ' . $actor['license'] : '',
        ($actor['specialty_license'] ?? '') !== '' ? 'Cédula esp.: ' . $actor['specialty_license'] : '',
        (string)($brand['facility_visible'] ?? ($actor['facility'] ?? '')),
        (string)($brand['location_line_visible'] ?? ($actor['place'] ?? ''))]);
    $logo = trim((string)($brand['logo_url_resolved'] ?? ''));
    $logoHtml = ($logo !== '' && (str_starts_with($logo, '/') && !str_starts_with($logo, '//')
        || preg_match('#^https://[^\s]+$#i', $logo) === 1))
        ? '<img src="' . $h($logo) . '" alt="Logo médico" style="max-height:64px;max-width:120px;object-fit:contain">' : '';
    $date = trim((string)($report['emission_date'] ?? ''));
    $signerMeta = implode(' · ', array_filter([(string)($signer['role'] ?? ''),
        (string)($signer['character'] ?? ''), (string)($signer['relationship'] ?? '')]));
    $signature = static function (string $label, array $entry, string $name) use ($h): string {
        $image = trim((string)($entry['image_data'] ?? ''));
        $imageHtml = preg_match('#^data:image/png;base64,[A-Za-z0-9+/]+={0,2}$#D', $image) === 1
            ? '<img class="informe-doc-sign-image" src="' . $h($image) . '" alt="' . $h($label) . '">'
            : '<div class="informe-doc-sign-line"></div>';
        return '<div class="informe-doc-section-title doc-base-section-title">' . $h($label) . '</div>'
            . $imageHtml . '<div class="informe-doc-sign-meta doc-base-signature-meta">' . $h($name) . '</div>';
    };
    $html = '<article class="informe-doc-sheet responsiva-doc-sheet document-sheet doc-base-sheet doc-base-sheet--letter doc-base-print-safe">'
        . '<div class="doc-base-sheet-inner doc-base-body"><header class="clinical-doc-head informe-doc-head responsiva-doc-head doc-base-medical-header doc-base-header-block doc-base-print-safe">'
        . ($logoHtml !== '' ? '<div class="responsiva-doc-logo">' . $logoHtml . '</div>' : '')
        . '<div class="responsiva-doc-physician"><div class="clinical-doc-doctor-name">' . $doctorName . '</div>';
    foreach ($doctorMeta as $line) $html .= '<div class="clinical-doc-doctor-site">' . $h($line) . '</div>';
    $html .= '</div></header><div class="doc-base-title-block-wrap"><section class="informe-doc-title-block doc-base-title-block">'
        . '<div class="informe-doc-title doc-base-title">Responsiva médica</div></section></div>'
        . '<div class="doc-base-patient-block-wrap"><section class="informe-doc-patient-block doc-base-patient-meta">'
        . '<div class="informe-doc-patient-line"><strong>Paciente:</strong> ' . $h($patient['full_name'] ?? '') . '</div>'
        . '<div class="informe-doc-patient-line"><strong>Edad:</strong> ' . $h($patient['age'] ?? '')
        . ' · <strong>Sexo:</strong> ' . $h($patient['sex'] ?? '') . '</div>'
        . '<div class="informe-doc-patient-line"><strong>Fecha:</strong> ' . $h($date) . '</div>'
        . '</section></div><div class="doc-base-body-block">'
        . $section('Tipo de responsiva', $type['type_label'] ?? '')
        . $section('Situación clínica', $content['clinical_situation'] ?? '')
        . $section('Conducta indicada', $content['indicated_conduct'] ?? '')
        . $section('Riesgo relevante informado', $content['relevant_risk'] ?? '')
        . $section('Declaración documental', $content['declaration_text'] ?? '')
        . $section('Manifestación adicional', $content['additional_manifestation'] ?? '')
        . '<section class="informe-doc-body-section doc-base-section"><div class="informe-doc-section-title doc-base-section-title">Firmante principal</div>'
        . '<div class="informe-doc-text doc-base-text">' . $h($signer['name'] ?? '') . '</div>';
    if ($signerMeta !== '') $html .= '<div class="informe-doc-text doc-base-text">' . $h($signerMeta) . '</div>';
    $html .= '</section>' . $section('Declaración de cierre', $content['closing_statement'] ?? '')
        . '<section class="informe-doc-sign doc-base-signature doc-base-print-safe">'
        . $signature('Firma del paciente o responsable', (array)($signatures['signer'] ?? []),
            (string)($signer['name'] ?? 'Firmante principal'))
        . $signature('Firma del médico tratante (opcional)', (array)($signatures['doctor'] ?? []),
            (string)($actor['full_name'] ?? 'Médico tratante'))
        . '</section></div><div class="doc-base-footer-block">';
    $footer = implode(' · ', array_filter([(string)($actor['institution'] ?? ''),
        (string)($actor['facility'] ?? ''), (string)($actor['place'] ?? '')]));
    $html .= $h($footer) . '</div></div></article>';
    return $html;
}
