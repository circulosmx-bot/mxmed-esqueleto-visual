<?php
declare(strict_types=1);

require_once __DIR__ . '/clinical_legal_document_presentation.php';

const CLINICAL_FREE_DOCUMENT_TOTAL_MAX = 50000;

function clinical_free_document_text(mixed $value, int $limit, string $field): string
{
    if (!is_string($value) || !preg_match('//u', $value))
        throw new InvalidArgumentException('FREE_DOCUMENT_' . $field . '_UTF8_INVALID');
    $text = str_replace(["\r\n", "\r"], "\n", $value);
    if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $text))
        throw new InvalidArgumentException('FREE_DOCUMENT_' . $field . '_CONTROL_INVALID');
    $text = trim($text);
    if (mb_strlen($text, 'UTF-8') > $limit)
        throw new InvalidArgumentException('FREE_DOCUMENT_' . $field . '_TOO_LONG');
    return $text;
}

/** Normalize once for both unsaved preview and persistence. No rendered HTML is accepted as input. */
function clinical_free_document_normalize(array $body, PDO $pdo, array $doctor, string $patientId): array
{
    if (($body['document_type'] ?? null) !== 'documento_libre'
        || ($body['type'] ?? 'documento_libre') !== 'documento_libre'
        || !is_array($body['payload'] ?? null)
        || ($body['payload']['contract_version'] ?? null) !== 1)
        throw new InvalidArgumentException('FREE_DOCUMENT_CONTRACT_INVALID');
    if ((string)($body['context']['patient_id'] ?? '') !== $patientId
        || !empty($body['context']['encounter_id']) || !empty($body['context']['appointment_id'])
        || (string)($body['actor']['user_id'] ?? '') !== (string)$doctor['user_id'])
        throw new InvalidArgumentException('FREE_DOCUMENT_SCOPE_INVALID');
    $source = $body['payload'];
    if (($source['status'] ?? null) !== 'draft' || !is_array($source['document'] ?? null)
        || !clinical_legal_document_presentation_valid($source))
        throw new InvalidArgumentException('FREE_DOCUMENT_PAYLOAD_INVALID');
    $document = $source['document'];
    $title = clinical_free_document_text($document['title'] ?? null, 120, 'TITLE');
    if ($title === '') throw new InvalidArgumentException('FREE_DOCUMENT_TITLE_REQUIRED');
    $purpose = clinical_free_document_text($document['purpose'] ?? '', 240, 'PURPOSE');
    $closing = clinical_free_document_text($document['closing_note'] ?? '', 2000, 'CLOSING_NOTE');
    $rawSections = $document['sections'] ?? null;
    if (!is_array($rawSections) || !array_is_list($rawSections)
        || count($rawSections) < 1 || count($rawSections) > 10)
        throw new InvalidArgumentException('FREE_DOCUMENT_SECTION_COUNT_INVALID');
    $sections = []; $ids = []; $orders = []; $total = mb_strlen($title . $purpose . $closing, 'UTF-8');
    foreach ($rawSections as $raw) {
        if (!is_array($raw)) throw new InvalidArgumentException('FREE_DOCUMENT_SECTION_INVALID');
        $id = (string)($raw['section_id'] ?? '');
        $order = $raw['section_order'] ?? null;
        if (!preg_match('/^[a-z0-9][a-z0-9_-]{7,63}$/iD', $id)
            || isset($ids[$id]) || !is_int($order) || $order < 1 || $order > count($rawSections)
            || isset($orders[$order]))
            throw new InvalidArgumentException('FREE_DOCUMENT_SECTION_ID_ORDER_INVALID');
        $ids[$id] = true; $orders[$order] = true;
        $heading = clinical_free_document_text($raw['section_title'] ?? null, 100, 'SECTION_TITLE');
        $bodyText = clinical_free_document_text($raw['section_body'] ?? null, 10000, 'SECTION_BODY');
        if ($heading === '' || $bodyText === '')
            throw new InvalidArgumentException('FREE_DOCUMENT_SECTION_REQUIRED');
        $total += mb_strlen($heading . $bodyText, 'UTF-8');
        $sections[] = ['section_id'=>$id, 'section_order'=>$order,
            'section_title'=>$heading, 'section_body'=>$bodyText];
    }
    if ($total > CLINICAL_FREE_DOCUMENT_TOTAL_MAX)
        throw new InvalidArgumentException('FREE_DOCUMENT_TOTAL_TOO_LONG');
    usort($sections, static fn($a,$b) => $a['section_order'] <=> $b['section_order']);
    $patient = $pdo->prepare('SELECT patient_id,display_name,birthdate,sex FROM patients_patients WHERE patient_id=? LIMIT 1');
    $patient->execute([$patientId]); $person = $patient->fetch(PDO::FETCH_ASSOC);
    if (!is_array($person)) throw new InvalidArgumentException('FREE_DOCUMENT_PATIENT_NOT_FOUND');
    $profile = $pdo->prepare('SELECT display_name,professional_license,specialty_license,specialty_primary,logo_url
        FROM profiles_doctors WHERE doctor_id=? LIMIT 1');
    $profile->execute([(string)$doctor['doctor_id']]); $professional = $profile->fetch(PDO::FETCH_ASSOC) ?: [];
    $age = '';
    if (!empty($person['birthdate'])) {
        try { $age = (string)(new DateTimeImmutable((string)$person['birthdate']))->diff(new DateTimeImmutable('today'))->y; }
        catch (Throwable) { $age = ''; }
    }
    $logo = (string)($professional['logo_url'] ?? '');
    if (!preg_match('#^/storage/clinical_uploads/branding/[^/]+/logo-opt\.webp$#D', $logo)) $logo = '';
    return [
        'contract_version'=>1, 'status'=>'draft',
        'document'=>['title'=>$title,'purpose'=>$purpose,'sections'=>$sections,'closing_note'=>$closing],
        'patient_snapshot'=>['id'=>$patientId,'full_name'=>(string)$person['display_name'],
            'age'=>$age,'sex'=>(string)($person['sex'] ?? '')],
        'actor_snapshot'=>['user_id'=>(string)$doctor['user_id'],
            'full_name'=>(string)(($professional['display_name'] ?? '') ?: 'Médico tratante'),
            'license'=>(string)($professional['professional_license'] ?? ''),
            'specialty_license'=>(string)($professional['specialty_license'] ?? ''),
            'specialty'=>(string)($professional['specialty_primary'] ?? '')],
        'branding'=>['logo_url_resolved'=>$logo],
        'presentation'=>['version'=>1,'professional_header'=>
            ($source['presentation']['professional_header'] ?? 'shown') === 'hidden' ? 'hidden' : 'shown'],
    ];
}

function clinical_free_document_render_html(array $payload): string
{
    $h = static fn(mixed $v): string => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $doc = (array)($payload['document'] ?? []);
    $patient = (array)($payload['patient_snapshot'] ?? []);
    $actor = (array)($payload['actor_snapshot'] ?? []);
    $logo = (string)($payload['branding']['logo_url_resolved'] ?? '');
    $html = '<article class="informe-doc-sheet free-doc-sheet document-sheet doc-base-sheet doc-base-sheet--letter doc-base-print-safe"><div class="doc-base-sheet-inner doc-base-body">';
    if (clinical_legal_document_professional_header_mode($payload) === 'shown') {
        $html .= '<header class="clinical-doc-head doc-base-medical-header doc-base-header-block doc-base-print-safe">';
        if (preg_match('#^/storage/clinical_uploads/branding/[^/]+/logo-opt\.webp$#D', $logo))
            $html .= '<div class="clinical-doc-head-logo-slot"><img src="'.$h($logo).'" alt="Logo médico"></div>';
        $html .= '<div class="clinical-doc-head-main"><div class="clinical-doc-doctor-name">'.$h($actor['full_name'] ?? '').'</div>';
        foreach (['specialty','license','specialty_license'] as $key)
            if (!empty($actor[$key])) $html .= '<div class="clinical-doc-doctor-site">'.$h($actor[$key]).'</div>';
        $html .= '</div></header>';
    }
    $html .= '<div class="doc-base-title-block-wrap"><section class="doc-base-title-block"><div class="doc-base-title">'.$h($doc['title'] ?? '').'</div>';
    if (!empty($doc['purpose'])) $html .= '<div class="doc-base-text">'.$h($doc['purpose']).'</div>';
    $html .= '</section></div><div class="doc-base-patient-block-wrap"><section class="doc-base-patient-meta"><div><strong>Paciente:</strong> '.$h($patient['full_name'] ?? '').'</div><div><strong>ID:</strong> '.$h($patient['id'] ?? '').' · <strong>Edad:</strong> '.$h($patient['age'] ?? '').' · <strong>Sexo:</strong> '.$h($patient['sex'] ?? '').'</div></section></div><div class="doc-base-body-block">';
    $sections = (array)($doc['sections'] ?? []);
    usort($sections, static fn($a,$b) => ($a['section_order'] ?? 0) <=> ($b['section_order'] ?? 0));
    foreach ($sections as $section) {
        $html .= '<section class="informe-doc-body-section doc-base-section"><div class="informe-doc-section-title doc-base-section-title">'.$h($section['section_title'] ?? '').'</div><div class="informe-doc-text doc-base-text">'.nl2br($h($section['section_body'] ?? '')).'</div></section>';
    }
    if (!empty($doc['closing_note'])) $html .= '<section class="informe-doc-body-section doc-base-section"><div class="informe-doc-section-title doc-base-section-title">Nota de cierre</div><div class="informe-doc-text doc-base-text">'.nl2br($h($doc['closing_note'])).'</div></section>';
    $html .= '<section class="informe-doc-sign doc-base-signature doc-base-print-safe"><div class="informe-doc-section-title doc-base-section-title">Médico responsable</div><div class="informe-doc-sign-line"></div><div class="informe-doc-sign-meta doc-base-signature-meta">'.$h($actor['full_name'] ?? '').'</div></section></div><div class="doc-base-flex-spacer" aria-hidden="true"></div><footer class="doc-base-footer-block doc-base-print-safe">Documento libre · Borrador</footer></div></article>';
    return $html;
}
